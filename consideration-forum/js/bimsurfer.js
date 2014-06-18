define(['dojo/_base/declare', 'dojo/dom', 'dojo/dom-construct',
'dojo/dom-class', 'dojo/dom-style', 'unit', 'dojo/Evented', 'dojo/Deferred',
"bimsurfer/api/BIMSURFER.js", "bimsurfer/api/SceneJS.js",
"bimsurfer/api/Constants.js", "bimsurfer/api/ProgressLoader.js",
"bimsurfer/api/Types/Light.js", "bimsurfer/api/Types/Light/Ambient.js",
"bimsurfer/api/Types/Light/Sun.js", "bimsurfer/api/Control.js",
"bimsurfer/api/Control.js", "bimsurfer/api/Control/ClickSelect.js",
"bimsurfer/api/Control/LayerList.js",
"bimsurfer/api/Control/ProgressBar.js",
"bimsurfer/api/Control/PickFlyOrbit.js",
"bimsurfer/api/Control/ObjectTreeView.js", "bimsurfer/api/Events.js",
"bimsurfer/api/StringView.js", "bimsurfer/api/GeometryLoader.js",
"bimsurfer/api/AsyncStream.js", "bimsurfer/api/DataInputStream.js",
"bimsurfer/api/Viewer.js", "bimsurfer/api/Util.js",
"bimsurfer/lib/scenejs-3.2/scenejs.js"], 

function(declare, dom, domConstruct, domClass, domStyle, unit, Evented,
Deferred) {

// This class provides a thin wrapper around the BIMsurfer API to make
// it into an AMD module. In addition, it provides several acces points
// to the dashboard such as obtaining and restoring camera position.

var BIMServer = null;
var BIMSurfer = null;
var lengthUnit = null;
var clickSelect = null;

var URL = 'http://localhost:8080/';
var USERNAME = 'admin@admin.admin';
var PASSWORD = 'admin';

var loadGeom;

var selectedOids = [];

var viewerContainer = dom.byId("cpViewer");
var loadingPercentage = dom.byId("loadingPercentage");

var bimsurfer_init = function(listener, api, server, email, password, project) {
	
	var revisions_loaded = [];
	
	var getLengthUnit = function(roid) {
            if (project.subProjects && project.subProjects.length) {
                var d = new Deferred();
                d.resolve(unit.getMagnitude(project.exportLengthMeasurePrefix));
                return d;
            } else {
                return unit.get(api, project.oid, roid, "LENGTHUNIT").then(function(v) { return lengthUnit = v; });
            }
	};
	
	// Most of this code comes from the original BIMsurfer examples.	
	loadGeom = function(roid) {
	        domConstruct.empty('viewport');
        	BIMSurfer = new BIMSURFER.Viewer(api, 'viewport');
	        getLengthUnit(roid).then(function() {
		api.call("ServiceInterface", "getRevisionSummary", {roid: roid}, function(summary){
		        summary.list.forEach(function(item){
		                if (item.name == "IFC Entities") {
		                        BIMSurfer.loadScene(function(){
	        	                        clickSelect = new BIMSURFER.Control.ClickSelect();
	        	                        clickSelect.events.register('select', function(a, b) {
	        	                                listener.emit('select', {oid: b.id});
	        	                                selectedOids.push(b.id);
	        	                        });
	        	                        clickSelect.events.register('unselect', function(a, b) {
	        	                                listener.emit('deselect', {});
	        	                                selectedOids = selectedOids.filter(function(oid) {return oid != b.id;});
	        	                        });
	        	                        BIMSurfer.addControl(clickSelect);
	        	                        clickSelect.activate();
	        	                        var toLoad = {};
	                                        item.types.forEach(function(e) { toLoad[e.name] = {mode: 0}; });
	        	                        if (item.types.length > 0) {
	                                                var geometryLoader = new GeometryLoader(api, BIMSurfer);
	                                                // domClass.add(viewerContainer, 'loading');
	                                                domStyle.set(loadingPercentage, 'display', 'block');
	                                                geometryLoader.addProgressListener(function(progress){
	                                                        loadingPercentage.innerHTML = parseInt(progress, 10) + "%";
                                                                if (progress == 100 || progress == 'done') {
                                                                        // domClass.remove(viewerContainer, 'loading');
                                                                        domStyle.set(loadingPercentage, 'display', 'none');
                                                                }
	                                                });
	                                                revisions_loaded.forEach(function(r) {
	                                                        debugger;
	                                                });
	                                                if (revisions_loaded.indexOf(roid) === -1) {
	                                                        geometryLoader.setLoadRevision(roid, toLoad);
	                                                        BIMSurfer.loadGeometry(geometryLoader);
		                                                // revisions_loaded.push(roid);
	                                                } else {
	                                                
	                                                }
	                                        }
	                                });
	                        }
	                });
	        });
	        });
        };
        
        loadGeom(project.lastRevisionId);
        
        var activeRevision = project.lastRevisionId;
        
	function revisionClick(e, roid) {
		e.preventDefault();
		var li = $(this).closest('li');
		if($(li).is('.selected')) {
			return;
		}
		$(li).closest('ul').find('li.selected').removeClass('selected');
		$(li).addClass('selected');
		loadGeom(roid);
		listener.emit('revisionChange', {revisionId: roid});
	}
};

// The functions below are some utility functions that are used to save
// and restore the camera orientation. The Yaw-Pitch-Target model of the
// BIMsurfer does not map precisely to the LookAt model of the BCF spec.
// In addition since the BIMsurfer does provide a comprehensive API to
// operate on the camera orientation from code, this functionality is
// currently disabled.
var lc_vec = function(v) {
        return {x:parseFloat(v.X), y:parseFloat(v.Y), z:parseFloat(v.Z)};
};
var uc_vec = function(v) {
        return {X:v.x, Y:v.y, Z:v.z};
};
var vec_add = function(a,b) {
        return {x:a.x+b.x, y:a.y+b.y, z:a.z+b.z};
};
var vec_sub = function(a,b) {
        return {x:a.x-b.x, y:a.y-b.y, z:a.z-b.z};
};
var vec_mult = function(v, d) {
        return {x:v.x*d, y:v.y*d, z:v.z*d};
};
var vec_norm = function(v) {
        var l = Math.sqrt(v.x*v.x + v.y*v.y + v.z*v.z);
        return vec_mult(v, 1.0 / l);
};

return declare([Evented], {
	start: function(api, server, email, password, project) {
		bimsurfer_init(this, api, server, email, password, project);
	},
	resize: function() {
	        if (BIMSurfer) {
	                BIMSurfer.resize($(BIMSurfer.div).width(), $(BIMSurfer.div).height());
	        }
	},
	restoreCamera: function(cam) {
	        return;
                var lookAt = BIMSurfer.SYSTEM.scene.findNode('main-lookAt');
                var eye = vec_mult(lc_vec(cam.CameraViewPoint), 1.0 / lengthUnit);
                var lk = vec_add(eye, vec_mult(lc_vec(cam.CameraDirection), 20));
                var pfo = BIMSurfer.controls["BIMSURFER.Control.PickFlyOrbit"][0];
                pfo.startEye = eye;
                pfo.pick({worldPos: [lk.x, lk.y, lk.z]});
                // These values are not maintained once the user navigates the camera:
                // lookAt.setEye(eye);
                // lookAt.setLook();
                // lookAt.setUp(lc_vec(cam.CameraUpVector));
	},
	obtainCamera: function() {
	        if (!BIMSurfer.SYSTEM.scene) {
	                return null;
	        }
	        var lookAt = BIMSurfer.SYSTEM.scene.findNode('main-lookAt');
	        return {
	                CameraViewPoint: uc_vec(vec_mult(lookAt.getEye(), lengthUnit)),
	                CameraDirection: uc_vec(vec_norm(vec_sub(lookAt.getLook(), lookAt.getEye()))),
	                CameraUpVector:  uc_vec(lookAt.getUp())
	        };
	},
	setRevision: function(r) {
	        loadGeom(r.oid);
	},
	obtainSelectedOids: function() {
	        return selectedOids;
	},
	setSelection: function(oid) {
	        clickSelect.pick({nodeId:oid});
	}
});
});
