define(['dojo/_base/declare', 'dojo/request', 'dojo/_base/lang', 'dojo/_base/array'], function(declare, request, lang, array) {
    var methods = [
        {              name: 'test',                       method: 'get',  args: []                           },
        {itfc: 'auth', name: 'login',                      method: 'post', args: ['username', 'password']     },
        {itfc: 'bcf',  name: 'getExtensions', 		   method: 'get',  args: []                           },
        {itfc: 'bcf',  name: 'getIssuesByProjectRevision', method: 'get',  args: ['bimsieUrl', 'poid', 'roid']},
        {itfc: 'bcf',  name: 'addIssue', 		   method: 'post', args: ['issue']                    }
    ];
    var interfaces = {
        auth: 'Bimsie1AuthInterface',
        bcf : 'Bimsie1BcfInterface'
    };
    return declare([], {
        token: null,
        constructor: function(args) {
            lang.mixin(this, args);
            if (!this.serverUrl || !this.username || !this.password) {
                throw new Error("serverUrl, username and password required");
            }
            var apiUrl = this.serverUrl + '/wp-content/plugins/bim-bcf-management/api.php';
            array.forEach(methods, function(method) {
                var itfc = method.itfc ? this[method.itfc] || (this[method.itfc] = {}) : this;
                itfc[method.name] = lang.hitch(this, function(args) {
                    var params = {};
                    array.forEach(method.args, function(a) {
                        params[a] = (args || {})[a] || this[a];
                        if (!params[a]) {
                            throw new Error(a);
                        }
                    }, this);
                    var req = method.itfc ? { request: {
                        interface : interfaces[method.itfc],
                        method    : method.name,
                        parameters: params
                    }} : null;
                    if (req && this.token !== null) {
                        req['token'] = this.token;
                    }
                    var query = method.itfc ? {handleAs: 'json'} : {};
                    if (req) {
                        query[method.method === 'get' ? 'query' : 'data'] = JSON.stringify(req);
                    }
                    return request[method.method](apiUrl, query);
                });
            }, this);
        }
    });
});
