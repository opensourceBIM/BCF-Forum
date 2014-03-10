<?php
include( '../../../wp-config.php' );

if( isset( $_POST[ 'method' ] ) ) {
	$serverId = -1;
	$token = false;
	$response = Array();
	// Server selected by id
	if( isset( $_POST[ 'serverId' ] ) && ctype_digit( $_POST[ 'serverId' ] ) ) {
		$serverId = $_POST[ 'serverId' ];
		$server = BIMBCFManagement::getBimsieServerById( $serverId );
		if( $server !== false ) {
			$uri = $server[ 'uri' ];
			$noServer = false;
			if( $server[ 'remember' ] == 1 ) {
				$username = $server[ 'username' ];
				$password = $server[ 'password' ];
				if( isset( $server[ 'tokenValid' ] ) && $server[ 'tokenValid' ] > time() ) {
					$token = $server[ 'token' ];
				}
			} else {
				$username = isset( $_POST[ 'username' ] ) ? $_POST[ 'username' ] : '';
				$password = isset( $_POST[ 'password' ] ) ? $_POST[ 'password' ] : '';
			}
		}
	}
	// New server added
	if( $serverId == -1 && isset( $_POST[ 'serverURI' ] ) && $_POST[ 'serverURI' ] != '' && isset( $_POST[ 'username' ] ) && isset( $_POST[ 'password' ] ) ) {
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
				$serverId = $key;
				break;
			}
		}
		if( !$found ) {
			$serverId = count( $servers );
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
	}
	
	if( $serverId != -1 ) {
		$response[ 'serverId' ] = $serverId;
		// No token known or the token is expired
		if( $token === false ) {
			$token = BIMsie::publicRequest( $uri, 'Bimsie1AuthInterface', 'login', Array( 'username' => $username, 'password' => $password ) );
			if( isset( $token ) && isset( $token->response ) && isset( $token->response->result ) && BIMsie::getErrorMessage( $token ) === false ) {
				$token = $token->response->result;
				$servers = BIMBCFManagement::getBimsieServers( false );
				foreach( $servers as $server ) {
					if( $server[ 'uri' ] == $uri ) {
						$oldServer = $server;
						$server[ 'token' ] = $token;
						$server[ 'tokenValid' ] = time() + 900; // TODO: Tokens set for 15 minutes atm, maybe I should retrieve this per server
						update_user_meta( get_current_user_id(), 'bimsie-servers', $server, $oldServer );
						break;
					}
				}
			} elseif( isset( $token ) ) {
				$response[ 'error' ] = BIMsie::getErrorMessage( $token );
			} else {
				$response[ 'error' ] = __( 'Could not retrieve token from the server with your credentials', 'bim-bcf-management' );
			}
		}
		
		if( $_POST[ 'method' ] == 'selectServer' ) {
			// TODO: maybe only retrieve active/top level projects?
			$projects = BIMsie::request( $uri, $token, 'Bimsie1ServiceInterface', 'getAllProjects', Array( 'onlyTopLevel' => 'false', 'onlyActive' => 'false' ) );
			$error = BIMsie::getErrorMessage( $projects );
			if( $error === false && isset( $projects ) && isset( $projects->response ) && isset( $projects->response->result ) ) {
			 	$projects = $projects->response->result;
			} else {
				$projects = null;
			}
	
			if( isset( $projects ) ) {
				$projects = ( Array ) $projects;
				$response[ 'projects' ] = $projects;
				// at this point we set the  bimsie server per pending issue
				if( !isset( $_POST[ 'type' ] ) || $_POST[ 'type' ] == 'pending' ) {
					BIMBCFManagement::setBimsieUriForPendingIssues( $uri );
				}
			}
			if( isset( $error ) && $error !== false ) {
				$response[ 'error' ] = $error;
			}
		} elseif( $_POST[ 'method' ] == 'submitProjects' ) {
			// set this project for this issue and retrieve a list of revisions for this project from the BIMSie server
			$projects = isset( $_POST[ 'projects' ] ) ? $_POST[ 'projects' ] : '';
			$projects = explode( ',', $projects );
			array_walk( $projects, 'intval' ); // I think project oid should always be an integer
			$names = isset( $_POST[ 'names' ] ) ? $_POST[ 'names' ] : '';
			$names = explode( ',', $names );
			$revisions = isset( $_POST[ 'revisions' ] ) ? $_POST[ 'revisions' ] : '';
			$revisions = explode( ',', $revisions );
			$projectsLackingRevision = BIMBCFManagement::setProjectForPendingIssues( $projects, $names, $revisions );
			foreach( $projectsLackingRevision as $key => $project ) {
				if( $project[ 'oid' ] != '' ) {
					$bimsieResponse = BIMsie::request( $uri, $token, 'Bimsie1ServiceInterface', 'getAllRevisionsOfProject', Array( 'poid' => $project[ 'oid' ] ) );
					$error = BIMsie::getErrorMessage( $bimsieResponse );
					if( $error === false && isset( $bimsieResponse->response ) && isset( $bimsieResponse->response->result ) ) {
						$projectsLackingRevision[$key][ 'revisions' ] = $bimsieResponse->response->result;
						foreach( $projectsLackingRevision[$key][ 'revisions' ] as $key2 => $revision ) {
							if( isset( $revision->date ) && is_numeric( $revision->date ) ) {
								$projectsLackingRevision[$key][ 'revisions' ][$key2]->dateString = date( 'd-m-Y H:i', $revision->date * 0.001 );
							} else {
								$projectsLackingRevision[$key][ 'revisions' ][$key2]->dateString = __( 'unknown', 'bim-bcf-management' );
							}
						}
					} else {
						if( isset( $response[ 'error' ] ) ) {
							$response[ 'error' ] .= '<br />' . $error;
						} else {
							$response[ 'error' ] = $error;
						}
					}
				}
			}
			$response[ 'projects' ] = $projectsLackingRevision;
		} elseif( $_POST[ 'method' ] == 'getRevisions' ) {
			// set this project for this issue and retrieve a list of revisions for this project from the BIMSie server
			$poid = isset( $_POST[ 'poid' ] ) ? intval( $_POST[ 'poid' ] ) : -1;
			$bimsieResponse = BIMsie::request( $uri, $token, 'Bimsie1ServiceInterface', 'getAllRevisionsOfProject', Array( 'poid' => $poid ) );
			$error = BIMsie::getErrorMessage( $bimsieResponse );
			if( $error === false && isset( $bimsieResponse->response ) && isset( $bimsieResponse->response->result ) ) {
				$response[ 'revisions' ] = $bimsieResponse->response->result;
				foreach( $response[ 'revisions' ] as $key => $revision ) {
					if( isset( $revision->date ) && is_numeric( $revision->date ) ) {
						$response[ 'revisions' ][$key]->dateString = date( 'd-m-Y H:i', $revision->date * 0.001 );
					} else {
						$response[ 'revisions' ][$key]->dateString = __( 'unknown', 'bim-bcf-management' );
					}
				}
			} else {
				$response[ 'error' ] = $error;
			}
		}
	} else {
		// We have no BIMsie server information so cannot perform request
		$response[ 'error' ] = __( 'No BIMsie server selected, select a server or add a new one', 'bim-bcf-management' );
	}
	print( json_encode( $response ) );
}