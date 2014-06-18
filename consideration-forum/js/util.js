define(['dojo/query'], function(query) {

// A set of uncategorized utility functions:
//
// - formValues() returns or applies a dictionary of key-value pairs to form elements
// - dateFormat() formats a date string according to the current locale

return {
    formValues: function(form, values) {
        if (values) {
            Object.keys(values).forEach(function(name) {
                form[name].value = values[name];
            });
        } else {
            var vals = {};
            var map = function(el) {
                var k = el.name === 'UserIdType' ? 'Assignee' : el.name;
                vals[k] = el.value;
            };
            var textType = function(i) {
                return i.type.toLowerCase() === "text";
            };
            query("input", form).filter(textType).forEach(map);
            query("select", form).forEach(map);
            return vals;
        }
    },
    dateFormat: function(d) {
        try {
            d = (new Date(d)).toLocaleString();
        } catch (e) {}
        return d;
    }
};

});
