define(['dojo/_base/declare', 'dojo/_base/lang', "dijit/Menu",
"dijit/MenuItem", "dijit/PopupMenuItem", "dojo/Evented", "dijit/popup",
"dojo/query", "dijit/registry"],

function(declare, lang, Menu, MenuItem, PopupMenuItem, Evented, popup,
query, registry) {

var prettify = function(s) {
    return s === 'UserIdType' ? 'Assignee' : s.replace(/^Topic/, '');
}

// A small utility class to populate a Dijit menu based on the BCF
// extension schema and bind it to a button.

return declare([Evented], {
    constructor: function(args) {
        lang.mixin(this, args);
    },
    init: function(extensions) {
        // Clean-up any left-over menus from (potentially
        // different) extension schema's.
        query(".dijitMenu", this.dijit.dropDown.domNode).forEach(function(node) { 
            registry.byNode(node).destroyRecursive();
        });
        
        var menu = new Menu({});
        
        var self = this;
        ['TopicType', 'TopicStatus', 'TopicLabel', 'UserIdType'].forEach(function(x) {
            var key = prettify(x);
            var m = new Menu();
            extensions[x].forEach(function(y) {
                m.addChild(new MenuItem({label: y, onClick:function() {
                    popup.hide(self.dijit.dropDown);
                    self.emit('click', {key: key, value: y});
                }}));
            });
            m.startup();
            menu.addChild(new PopupMenuItem({label: key, popup:m}));
        });
        menu.startup();
        this.dijit.dropDown.addChild(menu);
    }
});

});
