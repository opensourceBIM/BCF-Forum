define(['dojo/_base/declare', 'dojo/_base/lang', 'dijit/Dialog', "dojo/dom",
'dojo/query', 'dojo/_base/Deferred', 'dojo/on', 'put-selector/put', 'util',
'dojo/text!../templates/add_issue.html'],

function(declare, lang, Dialog, dom, query, Deferred, on, put, util,
dialogContent) {

var fmt = function(d) {
    var f = function(v) { return parseFloat(v).toFixed(2); };
    return "(" + f(d.X) + ", " + f(d.Y) + ", " + f(d.Z) + ")";
}
return declare([Dialog], {
    title: 'Add issue',
    content: dialogContent,
    style: 'width: 500px',
    deferred: null,
    
    setCameraInfo: function(cam) {
        var viewpointData = dom.byId('viewpointData');
        viewpointData.innerHTML = "Camera Direction: " + fmt(cam.CameraDirection) + "<br>" +
            "Camera Up Vector: " + fmt(cam.CameraUpVector) + "<br>" +
            "Camera View Point: " + fmt(cam.CameraViewPoint);
    },
    
    setSelectedGuids: function(gs) {
        var guidData = dom.byId('guidData');
        guidData.innerHTML = gs.map(function(o) {
            return o.name ? (o.name + " (" + o.guid + ")") : o.guid;
        }).join("<br>");
    },
    
    startup: function() {
        this.inherited(arguments);
        var form = dom.byId("addIssueForm");
        ['TopicType', 'TopicStatus', 'TopicLabel', 'UserIdType', 'Priority'].forEach(lang.hitch(this, function(x) {
            this.extensions[x].forEach(function(y) {
                put(form[x], 'option[value='+y+']', y);
            });
        }));
        on(dom.byId('addIssueForm'), 'submit', lang.hitch(this, function(e) {
            e.preventDefault();
            this.deferred.resolve(util.formValues(form));
            this.hide();
        }));
    },
    
    show: function() {
        this.inherited(arguments);
        return this.deferred = new Deferred();
    }
});
});
