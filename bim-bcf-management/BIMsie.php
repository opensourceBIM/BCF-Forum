<?php
class BIMsie {
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
}