require(["dojo/parser", "dijit/registry", "dojo/on", "./bimsurfer",
"dojo/dom-style", "dojo/query", "dojo/dom", "dojo/dom-construct",
"put-selector/put", "dojo/aspect", "./bcfapi", "AddIssueDialog", "Products",
"dojo/dom-class", "dojo/dom-geometry", "storage", "FilterButton",
"tableFilter", "util","dojo/domReady!", "dojo/_base/event",
"dijit/layout/BorderContainer", "dijit/layout/ContentPane",
"dijit/layout/TabContainer", "RevisionList"],

function(parser, registry, on, BimSurferClient, domStyle, query, dom,
domConstruct, put, aspect, BcfApi, AddIssueDialog, Products, domClass,
domGeom, storage, FilterButton, tableFilter, util)
{
    parser.parse();
    
    storage.restore("#loginForm input");
    $("table").tablesorter();
    
    var api = null;
    var products = null;
    var poid = null;
    var roid = null;
    var loginForm = dom.byId('loginForm');
    var cpProjects = registry.byId("cpProjects");
    var cpView = registry.byId("cpView");
    var loginButtonContainer = dom.byId("loginButtonContainer");
    var selectedIssueGuid = null;
    
    // Apply some timeout to make sure layouting is finished to
    // prevent flickering during loading.
    setTimeout(function() {
        domStyle.set('supermain', {visibility: 'visible'});
        domClass.remove(document.body, 'loading');
    }, 200);
    
    // Enable just the 'Select Server' tab and re-enable the
    // controls when the user clicks the 'Sign out' button.
    on(loginForm, 'reset', function(e) {
        e.preventDefault();
        domClass.toggle(loginForm, 'login logout');
        
        storage.clear("#loginForm input");
        storage.restore("#loginForm input");
        storage.enable("#loginForm input");
        
        cpProjects.set('disabled', true);
        cpView.set('disabled', true);
    });
    
    // When the user clicks the 'Sign in' button:        
    on(loginForm, 'submit', function(e) {
        e.preventDefault();
        
        domClass.add(loginButtonContainer, 'loading hide_children');
        
        storage.persist("#loginForm input");
    
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
        
        var addIssueDialog = null; 
        
        var tc = registry.byId("supermain");
        var revisionList = registry.byId("revisionList");
        var cpLogin = registry.byId("cpLogin");
        var cpIssues = registry.byId("cpIssues");
        var cpViewer = registry.byId("cpViewer");
        var projectsBody = dom.byId("projectsBody");
        var issuesBody = dom.byId("issuesBody");
        var issuesTable = dom.byId("issuesTable");
        var addIssueButton = dom.byId("addIssueButton");
        var addCommentButton = dom.byId("addCommentButton");
        var loadingPercentage = dom.byId("loadingPercentage");
        var filterText = dom.byId("filterText");
        var clearFilterButton = dom.byId("clearFilterButton");
        var clearFilterButtonContainer = dom.byId("clearFilterButtonContainer");
        var surf = new BimSurferClient();
        var filterButton = new FilterButton({dijit:registry.byId("filterButton")});
        var cpIssueComments = dom.byId("cpIssueComments");
        var bcIssues = registry.byId("bcIssues");
        var commentsBody = dom.byId("commentsBody");
        var addCommentForm = dom.byId("addCommentForm");
        
        var filteredView = function(b) {
            domClass.toggle("filteredList", b);
            domClass.toggle("unfilteredList", !b);
            domStyle.set(filterButton.dijit.domNode, "display", b ? "none" : "inline-block");
            domStyle.set(clearFilterButtonContainer, "display", b ? "block" : "none");
        };
        
        // Disable the button until dialog and extension 
        // schema are succesfully initialized
        addIssueButton.disabled = true;
        
        // Bind the resize event of the content panel to
        // the BIMsurfer wrapper and reposition the loading
        // percentage indication.
        aspect.after(cpViewer, 'resize', function() {
            surf.resize();
            var wh1 = {w: 100, h: 100}; 
            var wh2 = domGeom.position(cpViewer.domNode);
            domStyle.set(loadingPercentage, {
                left: parseInt((wh2.w - wh1.w) / 2) + 'px',
                top: parseInt((wh2.h - wh1.h) / 2) + 'px'
            });
        });
        
        // Initialize a new BCF api wrapper with the
        // credentials supplied by the user.
        var bcfApi = new BcfApi({
            serverUrl: bcfServerUrl,
            username: bcfUsername,
            password: bcfPassword
        });
        
        // When an issue is selected the related components should
        // be highlighted in the BIMsurfer and the camera should be
        // restored. The camera currently isn't restored due to
        // difficulties with the BIMsurfer API. For a related elements
        // only a single element can currently be selected.
        var selectIssue = function(issue) {
            if (issue.visualizationinfo && issue.visualizationinfo.length) {
                if (issue.visualizationinfo[0].PerspectiveCamera.length) {
                    surf.restoreCamera(issue.visualizationinfo[0].PerspectiveCamera[0]);
                } else if (issue.visualizationinfo[0].PerspectiveCamera) {
                    surf.restoreCamera(issue.visualizationinfo[0].PerspectiveCamera);
                } else {
                    surf.restoreCamera(issue.visualizationinfo[0][0].PerspectiveCamera);                
                }
                if (issue.visualizationinfo[0].Components && issue.visualizationinfo[0].Components.length) {
                    var c = issue.visualizationinfo[0].Components[0];
                    var guid = c["@attributes"].IfcGuid;
                    var oid = products.revision(roid).guid2oid[guid];
                    surf.setSelection(oid);
                }
            }
        };
        
        // Populates the table of comments from an array. The
        // issueGuid is persisted for a potentially newly
        // created issue.        
        var showComments = function(issueGuid, comments) {
            domStyle.set(cpIssueComments, "display", "block");
            domConstruct.empty(commentsBody);
            (comments || []).forEach(function(comment) {
                var tr = put(commentsBody, "tr");
                put(tr, "td", comment.Author || "<no author>");
                put(tr, "td", comment.Comment || "<no comment>");
                put(tr, "td", comment.VerbalStatus || "<no status>");
                put(tr, "td", comment.Priority || "<no priority>");
                put(tr, "td", util.dateFormat(comment.Date));
            });
            $(commentsBody.parentNode).trigger("update");
            bcIssues.layout();
            selectedIssueGuid = issueGuid;
        };
        
        // Add an issue row to the table. Takes a before argument
        // in case an issue is meant as a replacement for another
        // one, for example when the comment count is incremented.
        var addIssueToTable = function(issue, before) {
            var existingRows = query("tr", issuesBody);
            var tr;
            if (false && existingRows.length) {
                tr = put(existingRows[0], "-tr");
            } else if (before) {
                tr = put(before, "-tr");
            } else {
                tr = put(issuesBody, "tr");
            }
            put(tr, "td", issue.markup.Topic.Title || "<no title>");
            put(tr, "td", issue.markup.Topic.AssignedTo || "<no assignee>");
            put(tr, "td", issue.markup.Topic.Label || "<no label>");
            put(tr, "td", issue.markup.Topic["@attributes"].TopicStatus || "<no status>");
            put(tr, "td", issue.markup.Topic["@attributes"].TopicType || "<no type>");
            put(tr, "td", issue.markup.Topic.Priority || "<no priority>");
            put(tr, "td", util.dateFormat(issue.markup.Topic.CreationDate));
            var ncomments = "0";
            if (issue.markup.Comment) { ncomments = issue.markup.Comment.length; }
            put(tr, "td.ncomments", ncomments);
            console.log(issue.markup.Comment);
            var guid = issue.markup.Topic["@attributes"].Guid;
            on(tr, 'click', function() {
                query("tr.selected", issuesBody).forEach(function(tr) { domClass.remove(tr, "selected"); });
                domClass.add(tr, "selected");
                selectIssue(issue);
                showComments(guid, issue.markup.Comment);
                util.formValues(addCommentForm, {
                    Priority: issue.markup.Topic.Priority,
                    TopicStatus: issue.markup.Topic["@attributes"].TopicStatus
                });
            });
            $(issuesBody.parentNode).trigger("update"); 
        }
        
        // The add comment form is submitted.
        on(addCommentForm, 'submit', function(e) {
            e.preventDefault();
            
            // TODO: Populate fields from Issue
            var vals = util.formValues(addCommentForm);
            
            bcfApi.bcf.addComment({issueGuid:selectedIssueGuid, comment:{
                Comment: vals.commentText,
                Priority: vals.Priority,
                VerbalStatus: vals.TopicStatus,
                Status: "."
            }}).then(function(result) {
                var selected = query("tr.selected", issuesBody);
                if (selected.length === 1) {
                    var tr = selected[0];
                    var issue = result.response.result
                    addIssueToTable(issue, tr);
                    put(tr, "!");
                    domClass.add(tr, "selected");
                    showComments(selectedIssueGuid, issue.markup.Comment);
                }
            });
        });
        
        // TODO: If login fails a non-functional event attachment lingers around        
        // When the add issue button is pressed the AddIssueDialog is presented
        // to the user, which is populated with data from the current view port.
        // No data is persisted at this point yet.
        on(addIssueButton, 'click', function() {
            var vizInfo = surf.obtainCamera();
            var selectedOids = surf.obtainSelectedOids();
            var maps = products.revision(roid);
            var selectedGuids = selectedOids.map(function(oid) { return {guid: maps.oid2guid[oid], name: maps.oid2name[oid]}; });
            if (vizInfo) {
                addIssueDialog.setCameraInfo(vizInfo);
            }
            addIssueDialog.setSelectedGuids(selectedGuids);
            var components = selectedGuids.map(function(o) {
                return {
                    OriginatingSystem: "",
                    AuthoringToolId: "",
                    "@attributes": {
                        IfcGuid: o.guid,
                        Selected: true,
                        Visible: true,
                        Color: ""
                    }
                }
            });
            addIssueDialog.show().then(function(values) {
                bcfApi.bcf.addIssue({issue:{
                    markup: {
                        Header: {
                            File: [{
                                poid: poid,
                                roid: roid,
                                bimserver: bimServerUrlWithoutProtocol
                            }]
                        },
                        Topic: {
                            "@attributes": {
                                TopicType: values.TopicType,
                                TopicStatus: values.TopicStatus
                            },
                            Title: values.Title,
                            AssignedTo: values.Assignee,
                            Label: values.TopicLabel,
                            Priority: values.Priority
                        }
                    },
                    visualizationinfo: {
                        Components: components,
                        PerspectiveCamera: [vizInfo]
                    }
                }}).then(function(result) {
                    dom.byId('issueCount').innerHTML = (parseInt(dom.byId('issueCount').innerHTML, 10) || 0) + 1;
                    addIssueToTable(result.response.result);
                    tableFilter.filter(issuesTable);
                    // Ugly way to re-apply the sorting
                    // var tb = $("#issuesTable");
                    // tb.trigger("sorton", tb[0].config.sortList);
                    var idx = issuesTable.config.sortList[0][0];
                    var th = $("#issuesTable th")[idx];
                    th.click(); th.click();
                });
            });
        });
        
        // Load all issues from the BCF server for the combination of
        // server and project- and revision id. Displays a loading
        // indication until the response from the server is received.
        var displayIssues = function(bimsieUrl, poid, roid) {
            domClass.add(cpIssues.domNode, "loading hide_children");
            bcfApi.bcf.getIssuesByProjectRevision({
                bimsieUrl : bimsieUrl,
                poid      : poid,
                roid      : roid
            }).then(function(result) {
                var issues = result.response.result;
                dom.byId('issueCount').innerHTML = issues.length;
                domConstruct.empty(issuesBody);
                issues.forEach(function(x) { addIssueToTable(x); });
                // Ugly way to apply predefined sorting on date 
                // Since doesn't work:
                // $("#issuesTable").trigger("sorton", [5,1]);
                $("th.date").click();
                $("th.date").click();
                domClass.remove(cpIssues.domNode, "loading hide_children");
                revisionList.setIssues(issues);
            });
        };
        
        // Advance to the 'View Model' tab and initialize the revision
        // issues and 3d model.
        var selectProject = function(project) {
            cpView.set('disabled', false);
            tc.selectChild(cpView);
            surf.start(api, bimServerUrl, bimUsername, bimPassword, project);
            var rs = project.revisions;
            var r = project.lastRevisionId;
            poid = project.oid;
            roid = r;
            displayIssues(bimServerUrl, project.oid, r);
            products.load(poid, roid);
            api.call("Bimsie1ServiceInterface", "getAllRevisionsOfProject", {poid: project.oid}, function(revisions) {
                revisionList.setRevisions(revisions);
                revisionList.selectLast();
            });
        };
        
        // When a different revision is selected update the 3d model.
        on(revisionList, 'select', function(e) {
            roid = e.revision.oid;
            displayIssues(bimServerUrl, poid, roid);
            surf.setRevision(e.revision);
        });
        
        // Filter the issues
        filterButton.on('click', function(evt) {
            tableFilter.filter(issuesTable, evt.key, evt.value);
            filterText.innerHTML = "Filtered on: " + evt.key + " = " + evt.value;
            filteredView(true);
        });
        
        // Clear the filtering
        on(clearFilterButton, 'click', function() {
            tableFilter.clear(issuesTable);
            filteredView(false);
        });
        
        // Create a list of BIMserver JavaScript files we are interested in
        var bimServerModules = ['bimserverapi', 'String', 'utils'].map(function(s) { 
            return bimServerUrl + "/js/" + s + ".js"; 
        });
        
        // Load the BIMserver API
        require(bimServerModules, function() {
            api = new BimServerApi(bimServerUrl, null);
            products = new Products(api);
            
            // Initialize
            api.init(function() {
                console.log('init', arguments);
                
                // Obtain server info
                api.call("AdminInterface", "getServerInfo", {}, function(serverInfo){
                    console.log('serverInfo', serverInfo);
                    if (serverInfo.serverState === "RUNNING") {
                    
                        // Login to the BIM server
                        api.login(bimUsername, bimPassword, false, function() {
                            console.log('login', arguments);
                            api.resolveUser(function(user) {
                                console.log('user', user);
                                
                                // Login to the BCF server                                
                                bcfApi.auth.login().then(function(result) {
                        
                                    if (result.response.exception) {
                                        alert('BCF: ' + result.response.exception.message);
                                        domClass.remove(loginButtonContainer, 'loading hide_children');
                                    } else {
                                        bcfApi.token = result.response.result;
                                        
                                        // Get the BCF extension schema
                                        bcfApi.bcf.getExtensions().then(function(result) {
                                            var extensions = result.response.result;
                                        
                                            console.log('extensions', result);
                                            addIssueDialog = new AddIssueDialog({extensions:extensions});
                                            
                                            var form = addCommentForm;
                                            
                                            // TODO: Remove redundancy with AddIssueForm
                                            ['TopicStatus', 'Priority'].forEach(function(x) {
                                                extensions[x].forEach(function(y) {
                                                    put(form[x], 'option[value='+y+']', y);
                                                });
                                            });
                                            
                                            addIssueButton.disabled = false;
                                            filterButton.init(extensions);
                                            
                                            // Get all BIM server projects                                        
                                            api.call("Bimsie1ServiceInterface", "getAllProjects", {onlyTopLevel: true, onlyActive: true}, function(data){
                                                console.log('projects', data);
                                                domClass.remove(loginButtonContainer, 'loading hide_children');
                                                cpProjects.set('disabled', false);
                                                tc.selectChild(cpProjects);
                                                domConstruct.empty(projectsBody);

                                                domClass.toggle(loginForm, 'login logout');
                                                storage.disable("#loginForm input");

                                                data.forEach(function(project) {
                                                    var tr = put(projectsBody, "tr");
                                                    put(tr, "td", project.name);
                                                    put(tr, "td", project.subProjects.length);
                                                    put(tr, "td", project.revisions.length);
                                                    on(tr, 'click', function() {
                                                        selectProject(project);
                                                    });
                                                });
                                                $(projectsBody.parentNode).trigger("update");    
                                            });
                                        });
                                        
                                    }
                                    
                                });
                            });
                        }, function(exception) { 
                            alert('BIM: ' + exception.message); 
                            domClass.remove(loginButtonContainer, 'loading hide_children');
                        });
                    }
                });
            });            
        });
    });
});
    