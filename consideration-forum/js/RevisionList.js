define(['dojo/_base/declare', 'dojo/_base/lang', 'dojo/on', "dojo/_base/fx",
"dojo/Evented", "dijit/layout/ContentPane", "dojo/dom-geometry",
"dojo/dom-style", "dojo/dom-class", "dojo/dom", 'dojo/query',
'put-selector/put', 'dojox/gfx', "dijit/Tooltip"],

function(declare, lang, on, fx, Evented, ContentPane, domGeom, domStyle,
domClass, dom, query, put, gfx, Tooltip) {

// A Dijit widget that plots model revisions along a horizontal timeline.
// The widget responds to resize events. In addition it contains an SVG
// surface that plots the amount of issues created on the vertical axis.
// Both rely on the BCF timestamp to determine the spacing between the
// elements.

var r = 280;
var l = 20;
var pw = 32;
var parentOffset = 18;
var rh = 128;
        
return declare('RevisionList', [ContentPane, Evented], {
    'class': 'RevisionList',
    revisionCounter: 1,
    tooSmall: false,
    
    constructor: function() {
        this.revisions = [];
        this.issueCircles = [];
    },
    
    setRevisions: function(rs) {
        this.clearRevisions();
        this.calcTimespan(rs);
        rs.sort(function(a, b) { return a.date - b.date; });
        rs.forEach(lang.hitch(this, this.addRevision));
        this._layoutChildren();
    },
    
    resizeGfx: function() {
        var wh = domGeom.position(this.domNode);
        var h = Math.max(wh.h - rh, 1);
        this.gfx.setDimensions(wh.w, h);
        domStyle.set(this.gfxNode, 'display', wh.h < rh ? 'none' : 'block');
        if (this.chartData) {
            this.polyline.setShape(this.chartData.map(function(p) {
                return {x: this.calcOffset(p.x), y: h - (p.y / this.nIssues * (h - 20) + 10)};
            }, this));
            this.issueCircles.forEach(function(circ) {
                circ.c.setShape({cx: this.calcOffset(circ.i.t), cy: h - (circ.i.n / this.nIssues * (h - 20) + 10)});
            }, this);
        }
    },
    
    setIssues: function(issues) {
        this.issueCircles.forEach(function(c) { c.c.removeShape(); c.c.destroy(); });
        this.issueCircles.length = 0;
        
        if (!issues.length) {
            this.chartData = [];
            return this.resizeGfx();
        }
        this.nIssues = 0;
        var timeStampFromIssue = function(issue) { return +new Date(issue.markup.Topic.CreationDate); };
        var titleFromIssue = function(issue) { return issue.markup.Topic.Title; };
        var issueDates = issues.slice(0).map(function(issue) { return {t:timeStampFromIssue(issue), i:issue}; }).sort(function(a, b) { return a.t - b.t; });
        var d = this.chartData = [];
        
        d.push({x: -Infinity, y:0});
        
        issueDates.forEach(function(i) {
            d.push({x: i.t, y:   this.nIssues});
            d.push({x: i.t, y: ++this.nIssues});
            i.n = this.nIssues;
        }, this);
        
        d.push({x: +Infinity, y: this.nIssues});
        d.push({x: +Infinity, y: -1});
        d.push({x: -Infinity, y: -1});
        
        var graphicRegion = lang.hitch(this, function() { return domGeom.position(this.gfx.rawNode); });
        
        this.issueCircles = issueDates.map(function(i) {
            var circ = this.gfx.createCircle({cx:0, cy:0, r: 3}).setStroke("#ccc").setFill("#fff");
            var setRadius = function(r) { circ.setShape({'r':r}); };
            var around = null;
            on(circ, 'mouseover', function() {
                setRadius(6);
                var bbox = circ.getTransformedBoundingBox();
                var xy = graphicRegion();
                around = {x: bbox[1].x+xy.x+6, y: bbox[1].y+xy.y+4, w: 1, h: 1};
                Tooltip.show(titleFromIssue(i.i), around, ['after-centered', 'before-centered']);
            });
            on(circ, 'mouseout', function() {
                setRadius(3);
                Tooltip.hide(around);
            });
            return {i:i, c:circ};
        }, this);
        
        this.resizeGfx();
    },
    
    startup: function() {
        this.inherited(arguments);
        this.contentNode = put(this.containerNode, 'div');
        this.pointerNode = put(this.contentNode, 'div.pointerContainer');
        put(this.pointerNode, 'div.pointer');
        this.moveLeft = put(this.pointerNode, 'a.move.moveLeft');
        this.moveRight = put(this.pointerNode, 'a.move.moveRight');
        var move = function(d) {
            this.selectRevision(this.revisions[this.selectedRevision.number - 1 + d], true, true);
        };
        on(this.moveLeft,  'click', lang.hitch(this, move, -1));
        on(this.moveRight, 'click', lang.hitch(this, move, +1));
        this.gfx = gfx.createSurface(this.gfxNode = put(this.containerNode, 'div.gfxContainer'), 100, 100);
        this.polyline = this.gfx.createPolyline([]).setStroke("#ccc").setFill("#eee");
        this.resizeGfx();
        window.gfx = gfx;
        window.rs = this;
        this.tooltip = new Tooltip()
    },
    
    setLeftRightArrowVisibility: function() {
        domStyle.set(this.moveLeft, 'display', this.selectedRevision.number != 1 ? 'block' : 'none');
        domStyle.set(this.moveRight, 'display', this.selectedRevision.number < this.revisions.length ? 'block' : 'none');
    },
    
    clearRevisions: function() {
        this.revisions.forEach(function(r) {
            put(r.node, '!');
        });
        this.revisionCounter = 1;
        this.revisions.length = 0;
    },
    
    addRevision: function(r) {
        var div = put(this.contentNode, 'div.revision');
        put(div, 'div.number', this.revisionCounter);
        put(div, 'div.id', '(#' + r.oid + ')');
        put(div, 'div.comment', r.comment);
        put(div, 'div.date', (new Date(r.date)).toLocaleString() + " | " + moment(r.date).fromNow());
        var obj = {data: r, node: div, number: this.revisionCounter ++};
        on(div, 'click', lang.hitch(this, this.selectRevision, obj, true, true));
        this.revisions.push(obj);
    },
    
    _layoutChildren: function() {
        this.revisions.forEach(lang.hitch(this, function(r) {
            domStyle.set(r.node, {
                left: this.calcOffset(r.data.date) + 'px'
            });
        }));
        domClass[this.tooSmall ? 'add' : 'remove'](this.containerNode, 'hide_children');
        this.movePointer();
        this.resizeGfx();
    },
    
    calcTimespan: function(rs) {
        var times = rs.map(function(r) { return r.date; });
        var start = Math.min.apply(null, times);
        var end = Math.max.apply(null, times);
        
        this.calcOffset = function(d) {
            if (d === -Infinity) return - 10;
            var w = domGeom.position(this.domNode).w;
            if (d === +Infinity) return w + 10;
            w -= (r + l);
            this.tooSmall = w < 10;
            var dt = (end - start) / w;
            return dt ? parseInt((d - start) / dt + l) : l;
        };
    },
    
    selectRevision: function(R, anim, fire) {
        var n = this.revisions.length + 1;
        this.revisions.forEach(lang.hitch(this, function(r) {
            domStyle.set(r.node, {
                zIndex: R == r ? n : r.number
            });
        }));
        this.selectedRevision = R;
        this.movePointer(anim);
        this.setLeftRightArrowVisibility();
        if (fire) {
            this.emit('select', {revision: R.data});
        }
    },
    
    movePointer: function(anim) {
        var R = this.selectedRevision;
        if (!R) return;
        var numberPos = domGeom.position(query('.number', R.node)[0]);
        var left = numberPos.x + numberPos.w / 2 - pw / 2 - parentOffset;
        var props;
        if (anim) {
            props = {left: parseInt(left)};
            fx.animateProperty({node: this.pointerNode, properties: props}).play();
        } else {
            props = {left: parseInt(left) + 'px'};
            domStyle.set(this.pointerNode, props);
        }
    },
    
    selectLast: function() {
        var r = this.revisions[this.revisions.length-1];
        this.selectRevision(r, true, false);
    }
    
});
});
