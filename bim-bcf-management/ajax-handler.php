<?php
include( '../../../wp-config.php' );

if( isset( $_POST[ 'method' ] ) ) {
	if( $_POST[ 'method' ] == 'selectServer' ) {
		$noServer = true;
		if( isset( $_POST[ 'serverId' ] ) && ctype_digit( $_POST[ 'serverId' ] ) ) {
			$servers = BIMBCFManagement::getBimsieServers( false );
			if( isset( $servers[$_POST[ 'serverId' ]] ) ) {
				$uri = $servers[$_POST[ 'serverId' ]][ 'uri' ];
				$noServer = false;
				if( $servers[$_POST[ 'serverId' ]][ 'remember' ] == 1 ) {
					$username = $servers[$_POST[ 'serverId' ]][ 'username' ];
					$password = $servers[$_POST[ 'serverId' ]][ 'password' ];
					if( isset( $servers[$_POST[ 'serverId' ]][ 'tokenValid' ] ) && $servers[$_POST[ 'serverId' ]][ 'tokenValid' ] > time() ) {
						$token = $servers[$_POST[ 'serverId' ]][ 'token' ];
					}
				} else {
					$username = isset( $_POST[ 'username' ] ) ? $_POST[ 'username' ] : '';
					$password = isset( $_POST[ 'password' ] ) ? $_POST[ 'password' ] : '';
				}
			}
		}

		if( $noServer && isset( $_POST[ 'serverURI' ] ) && $_POST[ 'serverURI' ] != '' && isset( $_POST[ 'username' ] ) && isset( $_POST[ 'password' ] ) ) {
			$uri = $_POST[ 'serverURI' ];
			$username = $_POST[ 'username' ];
			$password = $_POST[ 'password' ];
			$servers = BIMBCFManagement::getBimsieServers( false );
			$found = false;
			foreach( $servers as $key => $server ) {
				if( $server[ 'uri' ] == $uri ) {
					if( isset( $_POST[ 'remember' ] ) && $_POST[ 'remember' ] == 'true' ) {
						$servers[$key] = Array( 'uri' => $uri, 'remember' => 1, 'username' => $username, 'password' => $password );
					} else {
						$servers[$key] = Array( 'uri' => $uri, 'remember' => 0 );
					}
					$found = true;
					break;
				}
			}
			if( !$found ) {
				if( isset( $_POST[ 'remember' ] ) && $_POST[ 'remember' ] == 'true' ) {
					add_user_meta( get_current_user_id(), 'bimsie-servers', Array( 'uri' => $uri, 'remember' => 1, 'username' => $username, 'password' => $password ) );
				} else {
					add_user_meta( get_current_user_id(), 'bimsie-servers', Array( 'uri' => $uri, 'remember' => 0 ) );
				}
			} else {
				delete_user_meta( get_current_user_id(), 'bimsie-servers' );
				foreach( $servers as $server ) {
					add_user_meta( get_current_user_id(), 'bimsie-servers', $server );
				}
			}
			$noServer = false;
		}

		if( !$noServer ) {
			if( !isset( $token ) ) {
				$token = BIMsie::publicRequest( $uri, 'Bimsie1AuthInterface', 'login', Array( 'username' => $username, 'password' => $password ) );
				if( isset( $token ) && isset( $token->response ) && isset( $token->response->result ) && BIMsie::getErrorMessage( $token ) === false ) {
					$token = $token->response->result;
					$servers = BIMBCFManagement::getBimsieServers( false );
					foreach( $servers as $server ) {
						if( $server[ 'uri' ] == $uri ) {
							$oldServer = $server;
							$server[ 'token' ] = $token;
							$server[ 'tokenValid' ] = time() + 60;
							update_user_meta( get_current_user_id(), 'bimsie-servers', $server, $oldServer );
							break;
						}
					}
				}
			}

			if( isset( $token ) ) {
				// TODO: maybe only retrieve active projects?
				$projects = BIMsie::request( $uri, $token, 'Bimsie1ServiceInterface', 'getAllProjects', Array( 'onlyTopLevel' => 'false', 'onlyActive' => 'false' ) );
				$error = BIMsie::getErrorMessage( $projects );
				if( isset( $projects ) && isset( $projects->response ) && isset( $projects->response->result ) && $error === false ) {
				 	$projects = $projects->response->result;
				} else {
					$projects = null;
				}
			} else {
				if( isset( $token ) ) {
					$error = BIMsie::getErrorMessage( $token );
				} else {
					$error = __( 'No valid BIMSie response from server' );
				}
			}
		}

		$response = Array(
			'servers' => BIMBCFManagement::getBimsieServers(),
		);
		if( isset( $projects ) ) {
			$projectsError = BIMsie::getErrorMessage( $projects );
			if( $projectsError === false ) {
				$projects = ( Array ) $projects;
				$response[ 'projects' ] = $projects;
			} else {
				$error = $projectsError;
			}
		}
		if( isset( $error ) && $error !== false ) {
			$response[ 'error' ] = $error;
		}

		print( json_encode( $response ) );
	} elseif( $_POST[ 'method' ] == 'submitProjects' ) {
		// set this project for this issue and retrieve a list of revisions for this project from the BIMSie server
		$projects = isset( $_POST[ 'projects' ] ) ? $_POST[ 'projects' ] : '';
		$projects = explode( ',', $projects );
		array_walk( $projects, 'intval' ); // I think project oid should always be an integer
		$projectsLackingRevision = BIMBCFManagement::setProjectForPendingIssues( $projects );
	}
}