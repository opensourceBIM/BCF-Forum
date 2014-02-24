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
				&& isset( $bimsieResponse[ 'response' ][ 'exception' ][ 'message' ] ) ) {
			return $bimsieResponse[ 'response' ][ 'exception' ][ 'message' ];
		} else {
			return false;
		}
	}
	
	public static function login( $username, $password ) {
		global $wpdb;
		$user = $wpdb->get_row( $wpdb->prepare( "SELECT ID, user_pass
				FROM {$wpdb->users}
				WHERE user_login = %s", $username ) );
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
		$userId = $wpdb->get_var( $wpdb->prepare( "SELECT user_id 
				FROM {$wpdb->usermeta}
				WHERE meta_key = 'bimsie_token' AND meta_value LIKE '%s'", '%' . $token . '%' ) );
		if( isset( $userId ) && $userId != '' ) {
			return $userId;
		} else {
			return false;
		}
	}
}