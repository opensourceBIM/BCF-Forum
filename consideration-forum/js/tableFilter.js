define(["dojo/query","dojo/dom-style", "dojo/NodeList-manipulate"], function(query, style) {

// A small set of utility functions that allow to hide table rows based on
// some key-value filter. The key name is mapped to a row's cell index by
// looking at the <th> elements of the table. For all <tr>s, which have a
// different value in the <td> indexed by the provided key, its style is set
// to display:none.

var lastKey, lastValue;

return {
    filter: function(el, key, value) {
        if (!key && !lastKey) return;
        
        var filterIndex = query("th", el).map(function(th, idx) {
            return {el:th, idx:idx};
        }).filter(function(obj) {
            var nl = new query.NodeList();
            nl.push(obj.el);
            return nl.text() === (key || lastKey);
        }).map(function(obj) {
            return obj.idx;
        })[0];
        
        query("tbody tr", el).forEach(function(tr) {
            var t = query("td", tr).at(filterIndex).text();
            style.set(tr, "display", t === (value || lastValue) ? "table-row" : "none");
        });
        
        lastKey = key;
        lastValue = value;
    },
    
    clear: function(el) {
        query("tbody tr", el).forEach(function(tr) {
            style.set(tr, "display", "table-row");
        });
    }
};

});
