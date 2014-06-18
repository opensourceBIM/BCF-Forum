define(['dojo/query'], function(query) {

// A small set of utilities that wraps the HTML5 LocalStorage API, if
// available. And allows to easily store, retrieve, enable and disable form
// fields based on their name.

if (!window.localStorage) return {persist:function(){}, restore:function(){}};

var map = function(q, f, p) {
    query(q).forEach(function(el) {
        var ty = el.type.toLowerCase();
        if ((ty !== 'password' || p || true) && ty !== 'submit' && ty !== 'reset') f(el);
    });
};

var disable = function(b) { return function(q) {
    map(q, function(el) {
        el.disabled = b;
    }, true);
}; };

return {

    persist: function(q) {
        map(q, function(el) {
            localStorage["cf."+el.name] = el.value;        
        });        
    },
    
    restore: function(q) {
        map(q, function(el) {
            var v = localStorage["cf."+el.name];
            if (v) el.value = v;
        });        
    },
    
    clear: function(q) {
        map(q, function(el) {
            el.value = '';
        }, true);
    },
    
    disable: disable(true),
    
    enable: disable(false)
    
};

});
