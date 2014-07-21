<?php
$tool_user_name = 'stylize';

include_once ( 'shared/common.php' ) ;
error_reporting( E_ALL & ~E_NOTICE ); # Don't clutter the directory with unhelpful stuff

$prot = getProtocol();
if ( array_key_exists( 'HTTP_ORIGIN', $_SERVER ) ) {
	$origin = $_SERVER['HTTP_ORIGIN'];
}


// Response Headers
header('Content-type: application/json; charset=utf-8');
header('Cache-Control: private, s-maxage=0, max-age=0, must-revalidate');
header('x-content-type-options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-JSONAPI-VERSION: 0.0.0.0');

if ( isset( $origin ) ) {
	// Check protocol
	$protOrigin = parse_url( $origin, PHP_URL_SCHEME );
	if ( $protOrigin != $prot ) {
		header( 'HTTP/1.0 403 Forbidden' );
		if ( 'https' === $protOrigin ) {
			echo '{"error":"Please use this service over https."}';
		} else {
			echo '{"error":"Please use this service over http."}';
		}
		exit;
	}
	
	// Do we serve content to this origin?
	if ( matchOrigin( $origin ) ) {
		header('Access-Control-Allow-Origin: ' . $origin);
		header('Access-Control-Allow-Methods: GET');
	} else {
		header( 'HTTP/1.0 403 Forbidden' );
		echo '{"error":"Accessing this tool from the origin you are attempting to connect from is not allowed."}';
		exit;
	}
}

// There are more clever ways to achieve this but for now, it should be sufficient
$action = '';
if ( array_key_exists( 'action', $_REQUEST ) ) {
	$action = $_REQUEST['action'];
}
switch ( $action ) {
	case 'stylizephp':
		include_once ( 'php/stylize.php' );
		
		$ugly = '';
		
		if ( array_key_exists( 'code', $_REQUEST ) ) {
			$ugly = $_REQUEST['code'];
		}
		if ( strlen( $ugly ) > 1024 * 1024 * 2 ) {
			$res['error'] = 'input file too large';
		} else {
			$res['stylizephp'] = stylize_code( $ugly );
		}
		break;
	case 'stylizejs':
		$ugly = '';
		
		if ( array_key_exists( 'code', $_REQUEST ) ) {
			$ugly = $_REQUEST['code'];
		}
		if ( strlen( $ugly ) > 1024 * 1024 * 2 ) {
			$res['error'] = 'input file too large';
		} else {
			$cmd = '/data/project/stylize/node/bin/js-beautify --config /data/project/stylize/public_html/jsbeautify.cfg.json -f -';

			// http://stackoverflow.com/a/2390755
			$descriptorspec = array(
				0 => array( 'pipe', 'r' ), // stdin is a pipe that the child will read from
				1 => array( 'pipe', 'w' ), // stdout is a pipe that the child will write to
				//2 => null, // STDERR
			);

			$process = proc_open( $cmd, $descriptorspec, $pipes );

			if ( is_resource( $process ) ) {
				// $pipes now looks like this:
				// 0 => writeable handle connected to child stdin
				// 1 => readable handle connected to child stdout

				fwrite( $pipes[0], $ugly );
				fclose( $pipes[0] );

				$pretty = stream_get_contents( $pipes[1] );
				fclose( $pipes[1] );

				// It is important that you close any pipes before calling
				// proc_close in order to avoid a deadlock
				$return_value = proc_close( $process );


				$res['stylizephp'] = $pretty;
			} else {
				$res['error'] = 'cannot open js-beautify process';
			}
		}
		break;
	default:
		header( 'HTTP/1.0 501 Not implemented' );
		$res['error'] = 'Unknown action "' . $action . '". Allowed are stylizephp, stylizejs.';
		break;
}
if ( !isset( $res ) ) {
	$res[] = array();
}

echo json_encode($res);
?>
