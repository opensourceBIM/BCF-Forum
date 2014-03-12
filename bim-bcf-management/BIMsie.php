<?php
class BIMsie {
	public static $tokenTimeout = 3600; // Token timeout in seconds
	
	public static function publicRequest( $uri, $interface, $method, $parameters = Array() ) {
		if( function_exists( 'curl_version' ) ) {
			$curlResource = curl_init();
			$postData = Array(
					'request' => Array(
							'interface' => $interface,
							'method' => $method,
							'parameters' => $parameters
					) );
			if( strtolower( substr( $uri, strlen( $uri ) - 4 ) ) != 'json' ) {
				if( strtolower( substr( $uri, strlen( $uri ) - 1 ) ) != '/' ) {
					$uri .= '/';
				}
				$uri .= 'json';
			}
			curl_setopt( $curlResource, CURLOPT_URL, $uri );
			curl_setopt( $curlResource, CURLOPT_HEADER, 0 );
			curl_setopt( $curlResource, CURLOPT_HTTPHEADER, Array( 'Content-type: application/json; charset=UTF-8' ) );
			curl_setopt( $curlResource, CURLOPT_POST, true );
			curl_setopt( $curlResource, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curlResource, CURLOPT_POSTFIELDS, json_encode( $postData ) );
			$result = curl_exec( $curlResource );
			$result = json_decode( $result );
			// Return json if valid, null if not json
			return $result;
		} else {
			print( 'This plugin requires cURL to be enabled, contact a system administrator about this.' );
			return null;
		}
	}
	
	public static function request( $uri, $token, $interface, $method, $parameters = Array() ) {
		if( function_exists( 'curl_version' ) ) {
			$curlResource = curl_init();
			$postData = Array(
					'request' => Array(
							'interface' => $interface,
							'method' => $method,
							'parameters' => $parameters
					),
					'token' => $token );
			// TODO: Have to check if we always need to end in json for json requests
			if( strtolower( substr( $uri, strlen( $uri ) - 4 ) ) != 'json' ) {
				if( strtolower( substr( $uri, strlen( $uri ) - 1 ) ) != '/' ) {
					$uri .= '/';
				}
				$uri .= 'json';
			}
			curl_setopt( $curlResource, CURLOPT_URL, $uri );
			curl_setopt( $curlResource, CURLOPT_HEADER, 0 );
			curl_setopt( $curlResource, CURLOPT_HTTPHEADER, Array( 'Content-type: application/json; charset=UTF-8' ) );
			curl_setopt( $curlResource, CURLOPT_POST, true );
			curl_setopt( $curlResource, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curlResource, CURLOPT_POSTFIELDS, json_encode( $postData ) );
			$result = curl_exec( $curlResource );
			$result = json_decode( $result );
			// Return json if valid, null if not json
			return $result;
		} else {
			print( 'This plugin requires cURL to be enabled, contact a system administrator about this.' );
			return null;
		}
	}
	
	public static function getErrorMessage( $bimsieResponse ) {
		if( isset( $bimsieResponse ) && is_array( $bimsieResponse ) 
				&& isset( $bimsieResponse[ 'response' ] ) && is_array( $bimsieResponse[ 'response' ] ) 
				&& isset( $bimsieResponse[ 'response' ][ 'exception' ] ) && is_array( $bimsieResponse[ 'response' ][ 'exception' ] ) 
				&& isset( $bimsieResponse[ 'response' ][ 'exception' ][ 'message' ] ) && $bimsieResponse[ 'response' ][ 'exception' ][ 'message' ] != '' ) {
			return $bimsieResponse[ 'response' ][ 'exception' ][ 'message' ];
		} elseif( isset( $bimsieResponse ) && is_object( $bimsieResponse ) 
				&& isset( $bimsieResponse->response ) && is_object( $bimsieResponse->response ) 
				&& isset( $bimsieResponse->response->exception ) && is_object( $bimsieResponse->response->exception ) 
				&& isset( $bimsieResponse->response->exception->message ) && $bimsieResponse->response->exception->message != '' ) {
			return $bimsieResponse->response->exception->message;
		} else {
			return false;
		}
	}
	
	public static function login( $username, $password ) {
		global $wpdb;
		$user = $wpdb->get_row( $wpdb->prepare( "SELECT ID, user_pass
				FROM {$wpdb->users}
				WHERE user_login = %s OR user_email = %s", $username, $username ) );
		if( isset( $user ) && wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			$tokenData = get_user_meta( $user->ID, 'bimsie_token', true );
			if( isset( $tokenData ) && $tokenData != '' && $tokenData[ 'timestamp' ] > time() - Bimsie::$tokenTimeout ) { // Token is still valid
				return BIMsie::updateTokenTimestamp( $user->ID );
			} else {
				return BIMsie::updateTokenTimestamp( $user->ID, BIMSie::generateToken() );
			}
		} else {
			return false;
		}
	}
	
	public static function updateTokenTimestamp( $userId, $token = false ) {
		// bump the token timestamp
		if( $token === false ) {
			$tokenData = get_user_meta( $userId, 'bimsie_token', true );
			if( isset( $tokenData ) && $tokenData != '' ) {
				$tokenData[ 'timestamp' ] = time();
				update_user_meta( $userId, 'bimsie_token', $tokenData );
				return $tokenData[ 'token' ];
			} else {
				// No token is set and 
				return false;
			}
		} else {
			$tokenData = Array( 'timestamp' => time(), 'token' => BIMSie::generateToken() );
			update_user_meta( $userId, 'bimsie_token', $tokenData );
			return $tokenData[ 'token' ];
		}
	}
	
	public static function generateToken( $length = 32 ) {
		// We prefer to use openssl for our random token
		if( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return base64_encode( openssl_random_pseudo_bytes( $length ) );
		} else {
			// fallback to mt_rand if php < 5.3 or no openssl available
			$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			$charactersLength = strlen( $characters ) - 1;
			$token = '';
			// select some random characters
			for ( $i = 0; $i < $length ; $i ++ ) {
				$token .= $characters[ mt_rand( 0, $charactersLength ) ];
			}
			return $token;
		}		
	}
	
	public static function getUserIdByToken( $token ) {
		global $wpdb;
		if( isset( $token ) && strlen( $token ) >= 32 ) {
			$userId = $wpdb->get_var( $wpdb->prepare( "SELECT user_id 
					FROM {$wpdb->usermeta}
					WHERE meta_key = 'bimsie_token' AND meta_value LIKE '%s'", '%' . $token . '%' ) );
			if( isset( $userId ) && $userId != '' ) {
				return $userId;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public static function createBimsieCacheItem( $uri, $projects ) {
		$bimsieCacheItem = Array( 'uri' => $uri, 'projects' => Array() );
		foreach( $projects as $project ) {
			$bimsieCacheItem[ 'projects' ][ $project->oid ] = $project;
		}
		return $bimsieCacheItem;
	}
	
	public static function getProjects( $uri, $userId = -1 ) {
		$server = BIMsie::getServerByUri( $uri, $userId );
		if( $server === false ) {
			return false;
		} elseif( isset( $server[ 'token' ] ) && isset( $server[ 'tokenValid' ] ) && $server[ 'tokenValid' ] > time() ) {
			$userId = $userId == -1 ? get_current_user_id() : $userId;
			$projects = BIMsie::request( $server[ 'uri' ], $server[ 'token' ], 'Bimsie1ServiceInterface', 'getAllProjects', Array( 'onlyTopLevel' => 'false', 'onlyActive' => 'false' ) );
			$error = BIMsie::getErrorMessage( $projects );
			if( $error === false && isset( $projects ) && isset( $projects->response ) && isset( $projects->response->result ) ) {
				$projects = $projects->response->result;
				$bimsieCache = BIMsie::createBimsieCacheItem( $uri, $projects );
				BIMsie::updateCache( $bimsieCache, $userId );
				if( isset( $bimsieCache[ 'projects' ] ) ) {
					return $bimsieCache[ 'projects' ];
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public static function getProject( $uri, $poid, $userId = -1 ) {
		$server = BIMsie::getServerByUri( $uri, $userId );
		if( $server === false ) {
			return false;
		} else {
			$userId = $userId == -1 ? get_current_user_id() : $userId;
			$bimsieCache = BIMsie::getCacheByUri( $uri, $userId );
			if( $bimsieCache === false || !isset( $bimsieCache[ 'projects' ][$poid] ) ) {
				// Retrieve data from Bimsie server if possible
				if( isset( $server[ 'token' ] ) && isset( $server[ 'tokenValid' ] ) && $server[ 'tokenValid' ] > time() ) {
					$projects = BIMSie::getProjects( $uri, $userId );
					if( $projects && isset( $projects[ 'projects' ][$poid] ) ) {
						return $projects[ 'projects' ][$poid];
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return $bimsieCache[ 'projects' ][$poid];
			}
		}
	}
	
	public static function getRevision( $uri, $poid, $roid, $userId = -1 ) {
		$server = BIMsie::getServerByUri( $uri, $userId );
		if( $server === false ) {
			return false;
		} else {
			$userId = $userId == -1 ? get_current_user_id() : $userId;
			$bimsieCache = BIMsie::getCacheByUri( $uri, $userId );
			if( $bimsieCache === false || !isset( $bimsieCache[ 'projects' ][$poid] ) ) {
				$project = BIMsie::getProject( $uri, $poid, $userId );
				if( $project ) {
					$bimsieCache = BIMsie::getCacheByUri( $uri, $userId );
					if( isset( $server[ 'token' ] ) && isset( $server[ 'tokenValid' ] ) && $server[ 'tokenValid' ] > time() ) {
						$revisions = BIMsie::request( $server[ 'uri' ], $server[ 'token' ], 'Bimsie1ServiceInterface', 'getAllRevisionsOfProject', Array( 'poid' => $poid ) );
						$error = BIMsie::getErrorMessage( $revisions );
						if( $error === false && isset( $revisions ) && isset( $revisions->response ) && isset( $revisions->response->result ) ) {
							$revisions = $revisions->response->result;
							$bimsieCache[ 'projects' ][$poid]->revisions = Array();
							foreach( $revisions as $revision ) {
								$revision->dateString = date( 'd-m-Y H:i', $revision->date * 0.001 );
								$revision->name = $revision->dateString . ' - ' . $revision->comment;
								$bimsieCache[ 'projects' ][$poid]->revisions[$revision->oid] = $revision;
							}
							BIMsie::updateCache( $bimsieCache, $userId );
							if( isset( $bimsieCache[ 'projects' ][$poid]->revisions[$roid] ) ) {
								return $bimsieCache[ 'projects' ][$poid]->revisions[$roid];
							} else {
								return false;
							}
						} else {
							return false;
						}
					} else {
						return false;
					}
				} else {
					return false;
				}
			} elseif( isset( $bimsieCache[ 'projects' ] ) && isset( $bimsieCache[ 'projects' ][$poid] ) && isset( $bimsieCache[ 'projects' ][$poid]->revisions ) && isset( $bimsieCache[ 'projects' ][$poid]->revisions[$roid] ) ) {
				return $bimsieCache[ 'projects' ][$poid]->revisions[$roid];
			} else {
				if( !isset( $bimsieCache[ 'projects' ][$poid]->revisions ) || !isset( $bimsieCache[ 'projects' ][$poid]->revisions[$roid] ) ) {
					if( isset( $server[ 'token' ] ) && isset( $server[ 'tokenValid' ] ) && $server[ 'tokenValid' ] > time() ) {
						$revisions = BIMsie::request( $server[ 'uri' ], $server[ 'token' ], 'Bimsie1ServiceInterface', 'getAllRevisionsOfProject', Array( 'poid' => $poid ) );
						$error = BIMsie::getErrorMessage( $revisions );
						if( $error === false && isset( $revisions ) && isset( $revisions->response ) && isset( $revisions->response->result ) ) {
							$revisions = $revisions->response->result;
							$bimsieCache[ 'projects' ][$poid]->revisions = Array();
							foreach( $revisions as $revision ) {
								$revision->dateString = date( 'd-m-Y H:i', $revision->date * 0.001 );
								$revision->name = $revision->dateString . ' - ' . $revision->comment;
								$bimsieCache[ 'projects' ][$poid]->revisions[$revision->oid] = $revision;
							}
							BIMsie::updateCache( $bimsieCache, $userId );
							if( isset( $bimsieCache[ 'projects' ][$poid]->revisions[$roid] ) ) {
								return $bimsieCache[ 'projects' ][$poid]->revisions[$roid];
							} else {
								return false;
							}
						} else {
							return false;
						}
					} else {
						return false;
					}
				} else {
					return false;
				}
			}
		}
	}
		
	private static function getCacheByUri( $uri, $userId ) {
		$bimsieCache = get_user_meta( $userId, 'bimsie-cache' );
		$cacheItem = false;
		foreach( $bimsieCache as $bimsieCacheItem ) {
			if( $bimsieCacheItem[ 'uri' ] == $uri ) {
				$cacheItem = $bimsieCacheItem;
				break;
			}
		}
		return $cacheItem;
	}
	
	private static function updateCache( $cacheItem, $userId ) {
		$bimsieCache = get_user_meta( $userId, 'bimsie-cache' );
		$found = false;
		foreach( $bimsieCache as $key => $bimsieCacheItem ) {
			if( $bimsieCacheItem[ 'uri' ] == $cacheItem[ 'uri' ] ) {
				$bimsieCache[$key] = $cacheItem;
				$found = true;
				break;
			}
		}
		if( !$found ) {
			$bimsieCache[] = $cacheItem;
		}
		delete_user_meta( $userId, 'bimsie-cache' );
		foreach( $bimsieCache as $bimsieCacheItem ) {
			add_user_meta( $userId, 'bimsie-cache', $bimsieCacheItem );
		}
	}

	public static function getServers( $excludeAuthInfo = true, $userId = -1 ) {
		$userId = $userId == -1 ? get_current_user_id() : $userId;
		$bimsieServers = get_user_meta( $userId, 'bimsie-servers' );
		if( $excludeAuthInfo ) {
			$servers = Array();
			foreach( $bimsieServers as $bimsieServer ) {
				$server = Array( 'uri' => $bimsieServer[ 'uri' ], 'remember' => $bimsieServer[ 'remember' ] );
				if( $bimsieServer[ 'remember' ] == 1 && isset( $bimsieServer[ 'username' ] ) ) {
					$server[ 'username' ] = $bimsieServer[ 'username' ];
				}
				$servers[] = $server;
			}
			return $servers;
		} else {
			return $bimsieServers;
		}
	}

	public static function getServerById( $serverId, $userId = -1 ) {
		$servers = BIMsie::getServers( false, $userId );
		if( isset( $servers[$serverId] ) ) {
			return $servers[$serverId];
		} else {
			return false;
		}
	}
	
	public static function getServerByUri( $uri, $userId = -1 ) {
		$servers = BIMsie::getServers( false, $userId );
		$foundServer = false;
		foreach( $servers as $server ) {
			$oldServer = $server;
			if( $server[ 'uri' ] == $uri ) {
				$foundServer = $server;
				if( $foundServer[ 'remember' ] == 0 ) {
					if( isset( $_POST[ 'username' ] ) && isset( $_POST[ 'password' ] ) ) {
						$foundServer[ 'username' ] = $_POST[ 'username' ];
						$foundServer[ 'password' ] = $_POST[ 'password' ];
					}
					if( isset( $_POST[ 'remember' ] ) && $_POST[ 'remember' ] != '' ) {
						$foundServer[ 'remember' ] = 1;
					}
				}
				if( $foundServer[ 'remember' ] == 0 || !isset( $foundServer[ 'token' ] ) || $foundServer[ 'tokenValid' ] < time() ) {
					$token = BIMsie::publicRequest( $foundServer[ 'uri' ], 'Bimsie1AuthInterface', 'login', 
							Array( 'username' => isset( $foundServer[ 'username' ] ) ? $foundServer[ 'username' ] : '', 'password' => isset( $foundServer[ 'password' ] ) ? $foundServer[ 'password' ] : '' ) );
					if( isset( $token ) && isset( $token->response ) && isset( $token->response->result ) && BIMsie::getErrorMessage( $token ) === false ) {
						$token = $token->response->result;
						$foundServer[ 'token' ] = $token;
						$foundServer[ 'tokenValid' ] = time() + BIMsie::$tokenTimeout;
						if( $foundServer[ 'remember' ] == 1 ) {
							update_user_meta( get_current_user_id(), 'BIMsie-servers', $foundServer, $oldServer );
						}
					}					
				}
				break;
			}
		}
		return $foundServer;
	}
		
	public static function updateServer( $uri, $username, $password, $remember, $userId = -1 ) {
		$servers = BIMsie::getBimsieServers( false, $userId );
		$found = false;
		foreach( $servers as $key => $server ) {
			if( $server[ 'uri' ] == $uri ) {
				if( $remember == 1 ) {
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
			if( $remember == 1 ) {
				add_user_meta( get_current_user_id(), 'BIMsie-servers', Array( 'uri' => $uri, 'remember' => 1, 'username' => $username, 'password' => $password ) );
			} else {
				add_user_meta( get_current_user_id(), 'BIMsie-servers', Array( 'uri' => $uri, 'remember' => 0 ) );
			}
		} else {
			delete_user_meta( get_current_user_id(), 'BIMsie-servers' );
			foreach( $servers as $server ) {
				add_user_meta( get_current_user_id(), 'BIMsie-servers', $server );
			}
		}
		return $servers[$serverId];
	}
	
	public static function getHierarchicalProjects( $projects ) {
		$hierarchicalProjects = Array();
		$added = Array();
		foreach( $projects as $project ) {
			if( $project->parentId == -1 && !in_array( $project->oid, $added ) ) {
				$added[] = $project->oid;
				$hierarchicalProjects[] = $project;
				foreach( $project->subProjects as $subProjectId ) {
					foreach( $projects as $subProject ) {
						if( $subProjectId == $subProject->oid && !in_array( $subProject->oid, $added ) ) {
							$added[] = $subProject->oid;
							$subProject->name = ' - ' . $subProject->name;
							$hierarchicalProjects[] = $subProject;
						}
					}
				}
			}
		}
		return $hierarchicalProjects;
	}
}