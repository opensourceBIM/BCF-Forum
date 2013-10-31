TNOBCFManagement = function() {};
TNOBCFManagement = TNOBCFManagement.prototype = function() {};

// Store the list of projects for a picked server in this global
TNOBCFManagement.projectList = new Array();
// Store the list of revisions for a picked project in this global
TNOBCFManagement.revisionList = new Array();

jQuery( document ).ready( function() {
	if( document.getElementById( "update-issue-revision-table" ) ) {
		TNOBCFManagement.issueUpdate();
	}
} );

TNOBCFManagement.issueUpdate = function() {
	var nextIssue = jQuery( "#update-issue-revision-table .issue-pending:first" );
	
	if( nextIssue.length > 0 ) {
		// There is still a pending issue
		if( nextIssue.find( ".project" ).html() == "" ) {
			// No project is set
			nextIssue.find( ".project" ).html( tnoBCFManagementSettings.loadingImage );
			TNOBCFManagement.showProjectList();
		} else if( nextIssue.find( ".revision" ).html() == "" ) {
			nextIssue.find( ".revision" ).html( tnoBCFManagementSettings.loadingImage );
			TNOBCFManagement.showRevisionList();
		}
	}
};

TNOBCFManagement.showRevisionList = function() {
	if( TNOBCFManagement.revisionList.length > 0 ) {
		// TODO: Show dropdown with revisions
	} else {
		// TODO: project does not contain revisions or no project selected, handle it here
	}
};

TNOBCFManagement.showProjectList = function() {
	if( TNOBCFManagement.projectList.length > 0 ) {
		// TODO: Show dropdown with projects and other server button next to it
	} else {
		// TODO: handle server connection here
		TNOBCFManagement.showServerSelection();
	}
};

TNOBCFManagement.showServerSelection = function() {
	// shows a dropdown with existing servers and option to add new URI
	var overlay = TNOBCFManagement.showOverlay();
	overlay.find( ".title" ).html( tnoBCFManagementSettings.text.selectServerTitle );
	var serverOptions = "";
	for( var i = 0; i < tnoBCFManagementSettings.bimsieServers.length; i ++ ) {
		serverOptions += "<option value=\"" + i + "\">" + tnoBCFManagementSettings.bimsieServers[i].uri + " - " + tnoBCFManagementSettings.bimsieServers[i].username + "</option>";
	}
	overlay.find( ".content" ).html( ( serverOptions != "" ? "<label for=\"server-selection\">" + tnoBCFManagementSettings.text.selectServerLabel + 
			"</label> <select id=\"server-selection\" onchange=\"TNOBCFManagement.serverSelected();\"><option value=\"\">" + tnoBCFManagementSettings.text.noServerOption + "</option>" + serverOptions + "</select><br />" : "" ) +
			"<div class=\"new-server-container\"><label for=\"new-bimsie-server\">" + tnoBCFManagementSettings.text.newServerLabel + 
			"</label> <input id=\"new-bimsie-server\" type=\"text\" /></div><br />" +
			"<div class=\"toggle-server-info hidden\">" +
				"<input type=\"checkbox\" id=\"server-remember-user\" /> <label for=\"server-remember-user\">" + tnoBCFManagementSettings.text.rememberServerLabel +
				"</label><br />" +
				"<label for=\"bimsie-username\">" + tnoBCFManagementSettings.text.serverUserLabel + 
				"</label> <input id=\"bimsie-username\" type=\"text\" /><br />" +
				"<label for=\"bimsie-password\">" + tnoBCFManagementSettings.text.serverPasswordLabel + 
				"</label> <input id=\"bimsie-password\" type=\"password\" /><br />" +
			"</div>" + 
			"<input type=\"button\" value=\"" + tnoBCFManagementSettings.text.submitServer + "\" onclick=\"TNOBCFManagement.submitServerSelection();\" />"
	);
	
	jQuery( "#new-bimsie-server" ).on( "keyup keypress blur", function() {
		// TODO: maybe add some validation for the URI
		if( this.value.length > 4 ) {
			jQuery( "#tno-bcf-management-overlay .toggle-server-info" ).removeClass( "hidden" );
		} else {
			jQuery( "#tno-bcf-management-overlay .toggle-server-info" ).addClass( "hidden" );
		}
	} );
	
};

TNOBCFManagement.serverSelected = function() {
	if( jQuery( "#server-selection" ).val() != "" ) {
		jQuery( "#tno-bcf-management-overlay .new-server-container" ).addClass( "hidden" );
		for( var i = 0; i < tnoBCFManagementSettings.bimsieServers.length; i ++ ) {
			if( jQuery( "#server-selection" ).val() == i ) {
				if( !tnoBCFManagementSettings.bimsieServers[i].remember ) {
					// We still need a username and or password
					jQuery( "#tno-bcf-management-overlay .toggle-server-info" ).removeClass( "hidden" );
				} else {
					jQuery( "#tno-bcf-management-overlay .toggle-server-info" ).addClass( "hidden" );
				}
				break;
			}
		}
	} else {
		jQuery( "#tno-bcf-management-overlay .new-server-container" ).removeClass( "hidden" );
	}
};

TNOBCFManagement.submitServerSelection = function() {
	var valid = false;
	if( jQuery( "#new-bimsie-server" ).val() != "" && jQuery( "#bimsie-username" ).val() != "" && jQuery( "#bimsie-password" ).val() != "" ) {
		valid = true;
	}
	if( jQuery( "#server-selection" ).length > 0 ) {
		var serverId = jQuery( "#server-selection" ).val();
		if( serverId != "" && tnoBCFManagementSettings.bimsieServers[serverId] ) {
			if( tnoBCFManagementSettings.bimsieServers[serverId].remember ) {
				valid = true;
			} else if( jQuery( "#bimsie-username" ).val() != "" && jQuery( "#bimsie-password" ).val() != "" ) {
				valid = true;
			}
		}
	}
	
	if( valid ) {
		// add selected server to data and if needed password and username
		var data = "method=selectServer";
		if( jQuery( "#server-selection" ).length > 0 && jQuery( "#server-selection" ).val() != "" ) {
			data += "&serverId=" + jQuery( "#server-selection" ).val();
		} else {
			data += "&serverURI=" + jQuery( "#new-bimsie-server" ).val();
		}
		if( jQuery( "#bimsie-username" ).val() != "" && jQuery( "#bimsie-password" ).val() != "" ) {
			data += "&username=" + jQuery( "#bimsie-username" ).val() + 
				"&password=" + jQuery( "#bimsie-password" ).val();
			if( jQuery( "#server-remember-user:checked" ).length == 1 ) {
				data += "&remember=true";
			}
		}
		jQuery.ajax( {
			type: "POST", 
			url: tnoBCFManagementSettings.ajaxURI, 
			data: data, 
			success: TNOBCFManagement.selectServer,
			dataType: "json"
		} );
	} else {
		jQuery( "#tno-bcf-management-overlay .status" ).html( tnoBCFManagementSettings.text.serverSubmitError );
	}
};

TNOBCFManagement.selectServer = function( response ) {
	// ajax callback for server selection, gives us a list of projects
	if( response.error ) {
		jQuery( "#tno-bcf-management-overlay .status" ).html( response.error );
	}
	if( response.projects ) {
		TNOBCFManagement.projectList = response.projects;
		if( TNOBCFManagement.projectList.length == 0 ) {
			jQuery( "#tno-bcf-management-overlay .status" ).html( tnoBCFManagementSettings.text.noProjectsFoundMessage );
			return false;
		}
	}
	TNOBCFManagement.showProjectList();
};

TNOBCFManagement.showOverlay = function() {
	var overlay = jQuery( "#tno-bcf-management-overlay" );
	if( overlay.length == 0 ) {
		jQuery( "body" ).append( "<div id=\"tno-bcf-management-overlay\"><div class=\"title\"></div><div class=\"status\"></div><div class=\"content\"></div></div>" );
		overlay = jQuery( "#tno-bcf-management-overlay" );
	}
	overlay.css( { 
		"top": Math.abs( ( jQuery( window ).height() - jQuery( overlay ).outerHeight() ) * 0.5 ) + jQuery( window ).scrollTop(),
		"left": Math.abs( ( jQuery( window ).width() - jQuery( overlay ).outerWidth() ) * 0.5 ) + jQuery( window ).scrollLeft()
	} );
	return overlay;
};

TNOBCFManagement.hideOverlay = function() {
	jQuery( "#tno-bcf-management-overlay" ).addClass( "hidden" );
};
