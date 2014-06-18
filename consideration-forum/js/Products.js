define(['dojo/_base/declare','dojo/_base/Deferred'], function(declare, Deferred) {

// This class provides a mapping from internal Bimserver oids to IFC guids.
// This is necessary to map the selected objects in BCF issues to BIMsurfer
// elements.

return declare(null, {
    constructor: function(api) {
        this.api = api;
        this.mapping = {};
    },
    
    load: function(poid, roid) {
        var d = new Deferred();
        var map = this.revision(roid);
        this.api.getModel(poid, roid, null, false, function(model){
            // Hmpf not really sure how many we can expect:
            var first = true;
            model.getAllOfType("IfcProduct", true, function(product){
                if (first) {
                    setTimeout(function() { d.resolve(true); }, 1);
                }
                first = false;
                var oid = product.oid;
                var guid = product.getGlobalId();
                map.oid2guid[oid] = guid;
                map.guid2oid[guid] = oid;
                map.oid2name[oid] = product.getName(); 
            });
        });
        return d;
    },
    
    revision: function(roid) {
        return this.mapping[roid] || (this.mapping[roid] = {
            oid2guid: {},
            oid2name: {},
            guid2oid: {}
        });
    }
});

});
