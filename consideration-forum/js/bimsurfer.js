define(['dojo/_base/declare', 'dojo/dom-construct', 'unit', 'dojo/Evented'], function(declare, domConstruct, unit, Evented) {
// Based on example 4
var BIMServer = null;
var BIMSurfer = null;
var lengthUnit = null;

var URL = 'http://localhost:8080/';
var USERNAME = 'admin@admin.admin';
var PASSWORD = 'admin';

var bimsurfer_init = function(listener, api, server, email, password, project) {

	
	/*
	
	var layerListContainer = $('div#layer_list');
	var layerList = new BIMSURFER.Control.LayerList($(layerListContainer).find('.data'));
	BIMSurfer.addControl(layerList);
	layerList.events.register('activated', function() {
			$(layerListContainer).addClass('open');

			var screenHeight = $('div#viewer_container').height();

			$(layerListContainer.find('.data').slideDown());
			$(layerListContainer).css('max-height', screenHeight - 50);
	});
	layerList.events.register('deactivated', function() {
			$(layerListContainer).removeClass('open');
	});

	$(layerListContainer).click(function(e) {
		e.preventDefault();
		if($(layerListContainer).is('.open')) {
			$(layerListContainer).find('h2').animate({'font-size': 12});
			$(layerListContainer.find('.data').slideUp('normal', function() {
				layerList.deactivate();
			}));
		} else {
			layerList.activate();
		}
	});
	*/
	
	var revisions_loaded = [];
	
	var getLengthUnit = function(roid) {
            return unit.get(api, project.oid, roid, "LENGTHUNIT").then(function(v) { return lengthUnit = v; });
	};
	
	var loadGeom = function(roid) {
	        domConstruct.empty('viewport');
        	BIMServer = new BIMSURFER.Server(api);
        	BIMSurfer = new BIMSURFER.Viewer(api, 'viewport');
	        getLengthUnit(roid).then(function() {
		api.call("ServiceInterface", "getRevisionSummary", {roid: roid}, function(summary){
		        summary.list.forEach(function(item){
		                if (item.name == "IFC Entities") {
		                        BIMSurfer.loadScene(function(){
	        	                        var clickSelect = new BIMSURFER.Control.ClickSelect();
	        	                        clickSelect.events.register('select', function() {});
	        	                        clickSelect.events.register('unselect', function() {});
	        	                        BIMSurfer.addControl(clickSelect);
	        	                        clickSelect.activate();
	        	                        var ty = item.types.map(function(e) { return e.name; });
	                                        if (ty.length > 0) {
	                                                revisions_loaded.forEach(function(r) {
	                                                        debugger;
	                                                });
	                                                if (revisions_loaded.indexOf(roid) === -1) {
		                                                BIMSurfer.loadGeometry({
		                                                        groupId: roid,
		                                                        roids: [roid], 
		                                                        types: ty
		                                                });
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
        
        api.call("Bimsie1ServiceInterface", "getAllRevisionsOfProject", {poid: project.oid}, function(revisions) {
                domConstruct.empty('timelineul');
                revisions.map(build_timeline);
        }); 

	function build_timeline(revision, i) {
		var li = $('<li />');
		if(revision.oid == activeRevision) {
		        $(li).addClass('selected');
                }
		$(li).data('revision', revision);

		$('<div />').addClass('IDs').append(
			$('<span />').addClass('number').text('#' + (i+1))
		).append(
			$('<span />').addClass('ID').text('ID: ' + revision.oid)
		).appendTo(li);
			
		var fn = (function(li, roid) { return function(e) { revisionClick.call(li, e, roid); }; })(li, revision.oid);

		$('<div />').addClass('description').append(
			$('<a />').attr('href', '#').attr('title', 'Show the revision: ' + revision.comment).text(revision.comment).click(fn).appendTo(li)
		).appendTo(li);
			
		var d = revision.date;
		try {
			d = (new Date(d)).toLocaleString();
		} catch(e) {}
			
		$('<div />').addClass('date').text(d).appendTo(li);
		$(li).appendTo($('#timeline').find('ul'));
	}
	
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
}
var vec_norm = function(v) {
        var l = Math.sqrt(v.x*v.x + v.y*v.y + v.z*v.z);
        return vec_mult(v, 1.0 / l);
}
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
                var lookAt = BIMSurfer.SYSTEM.scene.findNode('main-lookAt');
                var eye = vec_mult(lc_vec(cam.CameraViewPoint), 1.0 / lengthUnit);
                lookAt.setEye(eye);
                lookAt.setLook(vec_add(eye, lc_vec(cam.CameraDirection)));
                lookAt.setUp(lc_vec(cam.CameraUpVector));
	},
	obtainCamera: function() {
	        var lookAt = BIMSurfer.SYSTEM.scene.findNode('main-lookAt');
	        return {
	                CameraViewPoint: uc_vec(vec_mult(lookAt.getEye(), lengthUnit)),
	                CameraDirection: uc_vec(vec_norm(vec_sub(lookAt.getLook(), lookAt.getEye()))),
	                CameraUpVector:  uc_vec(lookAt.getUp())
	        };
	}
});
});
                                