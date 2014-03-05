<?php
include( '../../../wp-config.php' );
header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: GET, POST' );
header( 'Access-Control-Allow-Headers: X-Requested-With, Content-Type' );

// TODO: add functions and implement functions
$supportedCalls = Array(
	'Bimsie1AuthInterface' => Array(
		'getAccessMethod' => Array(),
		'isLoggedIn' => Array(),
		'login' => Array( 'username', 'password' ),
		'logout' => Array(),
	),
	'Bimsie1BcfInterface' => Array(
		'getIssuesByProjectRevision' => Array( 'bimsieUrl', 'poid', 'roid' )
	)
);

$input = file_get_contents( 'php://input' );
if( ( !isset( $input ) || $input == '' ) && isset( $_SERVER[ 'QUERY_STRING' ] ) && $_SERVER[ 'QUERY_STRING' ] != '' ) {
	$input = urldecode( $_SERVER[ 'QUERY_STRING' ] );
}
if( isset( $input ) && $input != '' ) {
	$request = json_decode( $input, true );
}
if( isset( $request ) ) {
	$invalid = false;
	$errorMessage = '';
	$errorType = '';
	if( isset( $request[ 'request' ] )
			&& isset( $request[ 'request' ][ 'interface' ] )
			&& isset( $request[ 'request' ][ 'method' ] )
			&& isset( $request[ 'request' ][ 'parameters' ] ) ) {
		if( isset( $supportedCalls[$request[ 'request' ][ 'interface' ]] )
				&& isset( $supportedCalls[$request[ 'request' ][ 'interface' ]][$request[ 'request' ][ 'method' ]] ) ) {
			if( $request[ 'request' ][ 'interface' ] == 'Bimsie1AuthInterface' ) {
				if( $request[ 'request' ][ 'method' ] == 'getAccessMethod' ) {
					$result = 'JSON';
				} elseif( $request[ 'request' ][ 'method' ] == 'login' ) {
					if( !isset( $request[ 'request' ][ 'parameters' ][ 'username' ] ) || $request[ 'request' ][ 'parameters' ][ 'username' ] == '' ||
							!isset( $request[ 'request' ][ 'parameters' ][ 'password' ] ) || $request[ 'request' ][ 'parameters' ][ 'password' ] == ''  ) {
						$invalid = true;
						$errorType = 'UserException';
						$errorMessage = __( 'Invalid username/password combination', 'bim-bcf-management' );
					} else {
						$token = BIMsie::login( $request[ 'request' ][ 'parameters' ][ 'username' ], $request[ 'request' ][ 'parameters' ][ 'password' ] );
						// get user id and hash, if it exists
						if( $token === false ) {
							$invalid = true;
							$errorType = 'UserException';
							$errorMessage = __( 'Invalid username/password combination', 'bim-bcf-management' );
						} else {
							$result = $token;
						}
					}
				} elseif( $request[ 'request' ][ 'method' ] == 'logout' ) {
					if( isset( $request[ 'token' ] ) && $request[ 'token' ] != '' ) {
						$userId = BIMsie::getUserIdByToken( $request[ 'token' ] );
						if( $userId !== false ) {
							delete_user_meta( $userId, 'bimsie_token' );
							$result = true;
						} else {
							$result = false;
						}
					} else {
						$invalid = true;
						$errorType = 'UserException';
						$errorMessage = __( 'Invalid token', 'bim-bcf-management' );
					}
				} elseif( $request[ 'request' ][ 'method' ] == 'isLoggedIn' ) {
					if( isset( $request[ 'token' ] ) && $request[ 'token' ] != '' ) {
						$userId = BIMsie::getUserIdByToken( $request[ 'token' ] );
						if( $userId !== false ) {
							$result = true;
						} else {
							$result = false;
						}
					} else {
						$invalid = true;
						$errorType = 'UserException';
						$errorMessage = __( 'Invalid token', 'bim-bcf-management' );
					}
				}
			} elseif( $request[ 'request' ][ 'interface' ] == 'Bimsie1BcfInterface' ) {
				// TODO: BCF methods here
				if( $request[ 'request' ][ 'method' ] == 'getIssuesByProjectRevision' ) {
					if( isset( $request[ 'request' ][ 'parameters' ][ 'bimsieUrl' ] ) && $request[ 'request' ][ 'parameters' ][ 'bimsieUrl' ] != '' &&
							isset( $request[ 'request' ][ 'parameters' ][ 'poid' ] ) && is_numeric( $request[ 'request' ][ 'parameters' ][ 'poid' ] ) &&
							isset( $request[ 'request' ][ 'parameters' ][ 'roid' ] ) && is_numeric( $request[ 'request' ][ 'parameters' ][ 'roid' ] ) ) {
						$userId = BIMsie::getUserIdByToken( $request[ 'token' ] );
						if( $userId !== false ) {
							// return list of issues for the current user with the right server/poid/roid combi
							$issues = BIMBCFManagement::getIssuesByProjectRevision( $userId, $request[ 'request' ][ 'parameters' ][ 'bimsieUrl' ], $request[ 'request' ][ 'parameters' ][ 'poid' ], $request[ 'request' ][ 'parameters' ][ 'roid' ] );
							$result = Array();
							foreach( $issues as $issue ) {
								$result[] = BIMBCFManagement::getJSONFromIssue( $issue );
							}
						} else {
							$invalid = true;
							$errorType = 'UserException';
							$errorMessage = __( 'Invalid token', 'bim-bcf-management' );
						}
					} else {
						$invalid = true;
						$errorType = 'InvalidRequest';
						$errorMessage  = __( 'Unsupported interface or method, check supported methods by browsing to: ', 'bim-bcf-management' ) . plugins_url( 'api.php', __FILE__ );
					}
				}
			}
		} else {
			$invalid = true;
			$errorType = 'InvalidRequest';
			$errorMessage  = __( 'Unsupported interface or method, check supported methods by browsing to: ', 'bim-bcf-management' ) . plugins_url( 'api.php', __FILE__ );
		}
	} else {
		$invalid = true;
		$errorType = 'InvalidRequest';
		$errorMessage  = __( 'Invalid request paramaters. Supply your request parameters in this format: ', 'bim-bcf-management' ) . '{"request":{"interface":"","method":"","parameters":{}}}';
	}
	if( $invalid ) {
		$response = Array( 'response' => Array( 'exception' => Array( '__type' => $errorType, 'message' => $errorMessage ) ) );
	} elseif( !isset( $result ) ) {
		// TODO: this is only temporary for testing
		print( "Method is not yet implemented even though it is in the supported array... my bad<br />" );
		print( "interface: {$request[ 'request' ][ 'interface' ]}<br />" );
		print( "method: {$request[ 'request' ][ 'method' ]}<br />" );
		print( "parameters:<br />" );
		foreach( $request[ 'request' ][ 'parameters' ] as $key => $value ) {
			print( " - $key: $value<br />" );
		}
		exit();
	} else {
		$response = Array( 'response' => Array( 'result' => $result ) );
	}
	print( json_encode( $response ) );
} else {
?>
<html>
	<head>
		<title>BIMSie API implementation with BCF extension</title>
	</head>
	<body>
		<h1>BIMSie API implementation with BCF extension</h1>
		Supported method:<br />
		<ul><li>JSON</li></ul>
		Supported functions:<br />
		<ul>
<?php
	foreach( $supportedCalls as $interface => $methods ) {
?>
			<li>
				<?php print( $interface ); ?>
				<ul>
<?php
		foreach( $methods as $method => $parameters ) {
?>
					<li><?php print( $method . '(' . implode( ', ', $parameters ) . ')' ); ?></li>
<?php
		}
?>
				</ul>
			</li>
<?php
	}
?>
		</ul>
		<h2>How to use</h2>
		Supply a JSON encoded string as request parameter, POST or GET are both supported, like:<br />
		<?php print( plugins_url( 'api.php', __FILE__ ) ); ?>?{"request":{"interface":"Bimsie1AuthInterface","method":"getAccessMethod","parameters":{}}}
	</body>
</html>
<?php
}
