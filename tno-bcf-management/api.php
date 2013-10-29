<?php
include( '../../../wp-config.php' );

// TODO: add functions and implement functions
$supportedCalls = Array(
//		 'Bimsie1AuthInterface' => Array( 
//		 		'login',
//		 		'logout',
//				'getAccessMethod',
//		 ),
		);

if( $_SERVER[ 'REQUEST_METHOD' ] == 'POST' ) {
	$input = $_POST;
} else {
	$input = $_GET;
}
if( isset( $input[ 'request' ] ) ) {
	$request = json_decode( $input[ 'request' ], true );
}

if( isset( $request ) ) {
	if( isset( $request[ 'request' ] ) 
			&& isset( $request[ 'request' ][ 'interface' ] ) 
			&& isset( $request[ 'request' ][ 'method' ] ) 
			&& isset( $request[ 'request' ][ 'parameters' ] ) ) {
		if( isset( $supportedCalls[$request[ 'request' ][ 'interface' ]] ) 
				&& in_array( $request[ 'request' ][ 'method' ], $supportedCalls[$request[ 'request' ][ 'interface' ]] ) ) {
			if( $request[ 'request' ][ 'interface' ] == 'Bimsie1AuthInterface' ) {
				if( $request[ 'request' ][ 'method' ] == 'getAccessMethod' ) {
					$response = Array( 'supported_methods' => 'JSON' );
				}
			}
			
			if( isset( $response ) ) {
				print( json_encode( $response ) );
			} else {
				print( "interface: {$request[ 'request' ][ 'interface' ]}<br />" );
				print( "method: {$request[ 'request' ][ 'method' ]}<br />" );
				print( "parameters:<br />" );
				foreach( $request[ 'request' ][ 'parameters' ] as $key => $value ) {
					print( " - $key: $value<br />" );
				}
			}
		} else {
			print( 'Unsupported interface or method, check supported methods by browsing to: ' . plugins_url( 'api.php', __FILE__ ) );
		}
	} else {
		print( 'Invalid request paramaters. Supply your request parameters in this format: { "request": { "interface": "","method": "","parameters": {} } }' );
	}
} else {
?>
<html>
	<head>
		<title>BIMSie API implementation with BCF extention</title>
	</head>
	<body>
		<h1>BIMSie API implementation with BCF extention</h1>
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
		foreach( $methods as $method ) {
?>
					<li><?php print( $method ); ?></li>
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
		request={ "request": { "interface": "","method": "","parameters": {} } }
	</body>
</html>
<?php
}