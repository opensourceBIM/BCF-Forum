require([
"dojo/parser", "dijit/registry", "dojo/on", "./bimsurfer",
"dojo/dom-style", "dojo/query", "dojo/dom", "dojo/dom-construct",
"put-selector/put", "dojo/aspect", "./bcfapi", "AddIssueDialog",

"dojo/domReady!", "dojo/_base/event",
"dijit/layout/BorderContainer", "dijit/layout/ContentPane",
"dijit/layout/TabContainer"
], function(parser, registry, on, BimSurferClient, domStyle, query, dom, domConstruct, put, aspect, BcfApi, AddIssueDialog) {
    parser.parse();
    domStyle.set('supermain', {visibility: 'visible'});
    var api = null;
    var poid = null;
    var roid = null;
    on(dom.byId('loginForm'), 'submit', function(e) {
        e.preventDefault();
    
        var inputs = query("#loginForm input");
        
        var i = 0;
        var bimServerUrlWithoutProtocol = inputs[i++].value;
        var bimServerUrl = 'http://' + bimServerUrlWithoutProtocol;
        var bimUsername = inputs[i++].value;
        var bimPassword = inputs[i++].value;

        var bcfServerUrlWithoutProtocol = inputs[i++].value;
        var bcfServerUrl = 'http://' + bcfServerUrlWithoutProtocol;
        var bcfUsername = inputs[i++].value;
        var bcfPassword = inputs[i++].value;
        
        var tc = registry.byId("supermain");
        var cpLogin = registry.byId("cpLogin");
        var cpProjects = registry.byId("cpProjects");
        var cpView = registry.byId("cpView");
        var cpViewer = registry.byId("cpViewer");
        var projectsBody = dom.byId("projectsBody");
        var issuesBody = dom.byId("issuesBody");
        var addIssueButton = dom.byId("addIssueButton");
        var surf = new BimSurferClient();
        
        aspect.after(cpViewer, 'resize', function() {
            surf.resize();
        });
        
        var bcfApi = new BcfApi({
            serverUrl: bcfServerUrl,
            username: bcfUsername,
            password: bcfPassword
        });
        
        var selectIssue = function(issue) {
            if (issue.visualizationinfo && issue.visualizationinfo.length) {
                if (issue.visualizationinfo[0].PerspectiveCamera) {
                    surf.restoreCamera(issue.visualizationinfo[0].PerspectiveCamera);
                } else {
                    surf.restoreCamera(issue.visualizationinfo[0][0].PerspectiveCamera);                
                }
            }
        };
        
        var addIssueDialog = null; 
        
        var addIssueToTable = function(issue) {
            var comment = issue.markup.Comment[0];
            var existingRows = query("tr", issuesBody);
            var tr;
            if (false && existingRows.length) {
                tr = put(existingRows[0], "-tr");
            } else {
                tr = put(issuesBody, "tr");
            }
            put(tr, "td", issue.markup.Topic.Title || "<no title>");
            put(tr, "td", comment.Author);
            put(tr, "td", comment.Comment);
            var d = comment.Date;
            try {
                d = (new Date(d)).toLocaleString();
            } catch (e) {}
            put(tr, "td", d);
            on(tr, 'click', function() {
                selectIssue(issue);
            });
        }
        
        on(addIssueButton, 'click', function() {
            var vizInfo = surf.obtainCamera();
            addIssueDialog.setCameraInfo(vizInfo);
            addIssueDialog.show().then(function(values) {
                bcfApi.bcf.addIssue({issue:{
                    markup: {
                        Comment: [{
                            Author: bimUsername,
                            Comment: values.Comment,
                            Priority: values.Priority,
                            TopicType: values.TopicType
                        }],
                        Header: {
                            File: [{
                                poid: poid,
                                roid: roid,
                                bimserver: bimServerUrlWithoutProtocol
                            }]
                        },
                        Topic: {
                            "Title": values.Title
                        }
                    },
                    visualizationinfo: [{
                        Components: {
                            Component: []
                        },
                        PerspectiveCamera: vizInfo
                    }]
                }}).then(function(result) {
                    dom.byId('issueCount').innerHTML = (parseInt(dom.byId('issueCount').innerHTML, 10) || 0) + 1;
                    addIssueToTable(result.response.result);
                });
            });
        });
        
        var displayIssues = function(bimsieUrl, poid, roid) {
            bcfApi.bcf.getIssuesByProjectRevision({
                bimsieUrl : bimsieUrl,
                poid      : poid,
                roid      : roid
            }).then(function(result) {
                var issues = result.response.result;
                dom.byId('issueCount').innerHTML = issues.length;
                domConstruct.empty(issuesBody);
                issues.forEach(addIssueToTable);
            });
        };
        
        var selectProject = function(project) {
            cpView.set('disabled', false);
            tc.selectChild(cpView);
            surf.start(api, bimServerUrl, bimUsername, bimPassword, project);
            var rs = project.revisions;
            var r = project.lastRevisionId;
            poid = project.oid;
            roid = r;
            displayIssues(bimServerUrl, project.oid, r);
        };
        
        on(surf, 'revisionChange', function(e) {
            roid = e.revisionId;
            displayIssues(bimServerUrl, poid, roid);
        });
        
        require([bimServerUrl + "/js/bimserverapi.js", bimServerUrl + "/js/String.js"], function() {
            api = new BimServerApi(bimServerUrl, null);
            api.init(function() {
                console.log('init', arguments);
                api.call("AdminInterface", "getServerInfo", {}, function(serverInfo){
                    console.log('serverInfo', serverInfo);
                    if (serverInfo.serverState === "RUNNING") {
                        api.login(bimUsername, bimPassword, false, function() {
                            console.log('login', arguments);
                            api.resolveUser(function(user) {
                                console.log('user', user);
                                
                                bcfApi.auth.login().then(function(result) {
                        
                                    if (result.response.exception) {
                                        alert('BCF: ' + result.response.exception.message);                                            
                                    } else {
                                    
                                        bcfApi.token = result.response.result;
                                        
                                        bcfApi.bcf.getExtensions().then(function(result) {
                                        
                                            console.log('extensions', result);
                                            addIssueDialog = new AddIssueDialog({extensions:result.response.result});
                                        
                                            api.call("Bimsie1ServiceInterface", "getAllProjects", {onlyTopLevel: true, onlyActive: true}, function(data){
                                                console.log('projects', data);
                                                cpProjects.set('disabled', false);
                                                tc.selectChild(cpProjects);
                                                domConstruct.empty(projectsBody);
                                                data.forEach(function(project) {
                                                    var tr = put(projectsBody, "tr");
                                                    put(tr, "td", project.name);
                                                    put(tr, "td", project.subProjects.length);
                                                    put(tr, "td", project.revisions.length);
                                                    on(tr, 'click', function() {
                                                        selectProject(project);
                                                    });
                                                });    
                                            });
                                        
                                        });
                                        
                                    }
                                    
                                });
                            });
                        }, function(exception) { alert('BIM: ' + exception.message); });
                    }
                });
            });            
        });
    });
});
