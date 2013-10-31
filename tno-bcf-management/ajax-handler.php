<?php
include( '../../../wp-config.php' );

if( isset( $_POST[ 'method' ] ) ) {
	if( $_POST[ 'method' ] == 'selectServer' ) {
		$noServer = true;
		if( isset( $_POST[ 'serverId' ] ) && ctype_digit( $_POST[ 'serverId' ] ) ) {
			$servers = TNOBCFManagement::getBimsieServers( false );
			if( isset( $servers[$_POST[ 'serverId' ]] ) ) {
				$uri = $servers[$_POST[ 'serverId' ]][ 'uri' ];
				$noServer = false;
				if( $servers[$_POST[ 'serverId' ]][ 'remember' ] == 1 ) {
					$username = $servers[$_POST[ 'serverId' ]][ 'username' ];
					$password = $servers[$_POST[ 'serverId' ]][ 'password' ];
				}
			}
		}
		
		if( $noServer && isset( $_POST[ 'serverURI' ] ) && $_POST[ 'serverURI' ] != '' && isset( $_POST[ 'username' ] ) && isset( $_POST[ 'password' ] ) ) {
			$uri = $_POST[ 'serverURI' ];
			$username = $_POST[ 'username' ];
			$password = $_POST[ 'password' ];
			$noServer = false;
			if( isset( $_POST[ 'remember' ] ) && $_POST[ 'remember' ] == 'true' ) {
				add_user_meta( get_current_user_id(), 'bimsie-servers', Array( 'uri' => $uri, 'remember' => 1, 'username' => $username, 'password' => $password ) );
			} else {
				add_user_meta( get_current_user_id(), 'bimsie-servers', Array( 'uri' => $uri, 'remember' => 0 ) );
			}
		}
		
		if( !$noServer ) {
			if( !isset( $token ) ) {
				$token = TNOBCFManagement::bimsieAPIRequest( $uri, false, 'Bimsie1AuthInterface', 'login', Array( 'username' => $username, 'password' => $password ) );
			}
			if( isset( $token ) && TNOBCFManagement::getBimsieErrorMessage( $token ) === false ) {
				$servers = TNOBCFManagement::getBimsieServers( false );
				foreach( $servers as $server ) {
					if( $server[ 'uri'] == $uri ) {
						$oldServer = $server;
						$server[ 'token' ] = $token;
						update_user_meta( get_current_user_id(), 'bimsie-servers', $server, $oldServer );
						break;
					}
				}
				// TODO: maybe only retrieve active projects?
				$projects = TNOBCFManagement::bimsieAPIRequest( $uri, $token, 'Bimsie1ServiceInterface', 'getAllProjects', Array( 'onlyTopLevel' => false, 'onlyActive' => false ) );
			} else {
				if( isset( $token ) ) {
					$error = TNOBCFManagement::getBimsieErrorMessage( $token );
				} else {
					$error = __( 'No valid BIMSie response from server' );
				}
			}
		}
		
		$response = Array(
			'servers' => TNOBCFManagement::getBimsieServers(),
		);
		if( isset( $projects ) ) {
			$projectsError = TNOBCFManagement::getBimsieErrorMessage( $projects );
			if( $projectsError === false ) {
				$response[ 'projects' ] = ( Array ) $projects;
			} else {
				$error = $projectsError;
			}
		}
		if( isset( $error ) ) {
			$response[ 'error' ] = $error;
		}
		
		print( json_encode( $response ) );
	} elseif( $_POST[ 'method' ] == 'selectProject' ) {
		// TODO: set this project for this issue and retrieve a list of revisions for this project from the BIMSie server
	}
}