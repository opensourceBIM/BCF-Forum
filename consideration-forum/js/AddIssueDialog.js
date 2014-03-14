define(['dojo/_base/declare', 'dojo/_base/lang', 'dijit/Dialog', "dojo/dom", 'dojo/query', 'dojo/_base/Deferred', 'dojo/on', 'put-selector/put', 'dojo/text!../templates/add_issue.html'], function(declare, lang, Dialog, dom, query, Deferred, on, put, dialogContent) {
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
    
    startup: function() {
        this.inherited(arguments);
        var form = dom.byId("addIssueForm");
        ['TopicType', 'Priority'].forEach(lang.hitch(this, function(x) {
            this.extensions[x].forEach(function(y) {
                put(form[x], 'option[value='+y+']', y);
            });
        }));
        on(dom.byId('addIssueForm'), 'submit', lang.hitch(this, function(e) {
            e.preventDefault();

            var vals = {};
            var map = function(el) {
                vals[el.name] = el.value;
            };
            var textType = function(i) {
                return i.type.toLowerCase() === "text";
            };
            query("input", form).filter(textType).forEach(map);
            query("select", form).forEach(map);

            this.deferred.resolve(vals);

            this.hide();
        }));
    },
    
    show: function() {
        this.inherited(arguments);
        return this.deferred = new Deferred();
    }
});
});
