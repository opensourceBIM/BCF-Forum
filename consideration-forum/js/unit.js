define(['dojo/_base/Deferred'], function(Deferred) {

// A small set of utility functions that queries the BIMserver model for the
// (length) unit referenced by the project and its magnitude.

var prefixes = {
    EXA   : 1e18,
    PETA  : 1e15,
    TERA  : 1e12,
    GIGA  : 1e9,
    MEGA  : 1e6,
    KILO  : 1e3,
    HECTO : 1e2,
    DECA  : 10,
    DECI  : 1e-1,
    CENTI : 1e-2,
    MILLI : 1e-3,
    MICRO : 1e-6,
    NANO  : 1e-9,
    PICO  : 1e-12,
    FEMTO : 1e-15,
    ATTO  : 1e-18
};

var getUnitValue = function(unit, callback) {
      var type = unit.object.__type;
      if (type == "IfcSIUnit") {
          callback(prefixes[unit.getPrefix()] || 1.);
      } else if (type == "IfcConversionBasedUnit") {
          unit.getConversionFactor(function(measureWithUnit) {
              measureWithUnit.getUnitComponent(function(unitComponent) {
                  measureWithUnit.getValueComponent(function(valueComponent) {
                      getUnitValue(unitComponent, function(v) {
                          callback(valueComponent.value * v);
                      });
                  });
              });
          });
      }
};

return {
    get: function(api, poid, roid, type) {
        var d = new Deferred();
        api.getModel(poid, roid, null, false, function(model){
            model.getAllOfType("IfcProject", true, function(project){
                project.getUnitsInContext(function(unitsInContext){
                    unitsInContext.getUnits(function(unit){
                        if (unit.getUnitType() == type) {
                            getUnitValue(unit, function(v) { 
                              console.log('Found unit ' + type + ' = ' + v);
                              d.resolve(v); 
                            } );
                        }
                    });
                });
            });
        });
        return d;
    },
    getMagnitude: function(s) {
        var prefix = s.toUpperCase().replace(/METER$/, '');
        return prefixes[prefix] || 1.;
    }
};

});
