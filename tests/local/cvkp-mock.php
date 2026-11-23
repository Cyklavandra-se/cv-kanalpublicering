<?php
// Testmock: Mailchimp och Graph API. Loggar alla anrop till wp-content/cvkp-mock.log.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! preg_match( '/(api\.mailchimp\.com|graph\.facebook\.com)$/', (string) $host ) ) {
		return $pre;
	}
	$state = get_option( 'cvkp_mock_state', array( 'n' => 0, 'campaigns' => array(), 'fail' => '' ) );
	$state['n']++;
	$method = $args['method'];
	$path   = wp_parse_url( $url, PHP_URL_PATH );
	$body   = isset( $args['body'] ) ? $args['body'] : null;
	file_put_contents( WP_CONTENT_DIR . '/cvkp-mock.log', $method . ' ' . $url . ' ' . ( is_array( $body ) ? http_build_query( $body ) : (string) $body ) . "\n", FILE_APPEND );
	$code = 200; $out = array();
	if ( $state['fail'] && false !== strpos( $path, $state['fail'] ) ) {
		$code = 400; $out = array( 'error' => array( 'message' => 'Simulerat fel', 'code' => 100 ) );
	} elseif ( false !== strpos( $host, 'mailchimp' ) ) {
		if ( preg_match( '#/campaigns/([a-z0-9]+)$#', $path, $m ) && 'GET' === $method ) {
			if ( isset( $state['campaigns'][ $m[1] ] ) ) { $out = $state['campaigns'][ $m[1] ]; } else { $code = 404; $out = array( 'detail' => 'Not found' ); }
		} elseif ( preg_match( '#/campaigns$#', $path ) && 'POST' === $method ) {
			$id = 'c' . $state['n']; $out = array( 'id' => $id, 'web_id' => 1000 + $state['n'], 'status' => 'save' ); $state['campaigns'][ $id ] = $out;
		} elseif ( preg_match( '#/campaigns/([a-z0-9]+)$#', $path, $m ) && 'PATCH' === $method ) {
			$out = $state['campaigns'][ $m[1] ];
		} elseif ( preg_match( '#/content$#', $path ) ) {
			file_put_contents( WP_CONTENT_DIR . '/cvkp-mock-email.html', json_decode( $body, true )['html'] );
		} elseif ( preg_match( '#/lists/#', $path ) ) { $out = array( 'name' => 'Cykla & Vandra', 'stats' => array( 'member_count' => 1234 ) ); }
	} else {
		if ( preg_match( '#/(me|\d+)$#', $path ) && 'GET' === $method && false !== strpos( $url, 'instagram_business_account' ) ) {
			$out = array( 'id' => '111', 'name' => 'Cykla & Vandra', 'instagram_business_account' => array( 'id' => '999', 'username' => 'cyklavandra.se' ) );
		} elseif ( preg_match( '#/111/feed$#', $path ) ) { $out = array( 'id' => '111_' . $state['n'] );
		} elseif ( preg_match( '#/999/media$#', $path ) ) { $out = array( 'id' => 'cont' . $state['n'] );
		} elseif ( preg_match( '#/999/media_publish$#', $path ) ) { $out = array( 'id' => 'media' . $state['n'] );
		} elseif ( 'GET' === $method && false !== strpos( $url, 'status_code' ) ) { $out = array( 'status_code' => 'FINISHED' );
		} elseif ( 'GET' === $method && false !== strpos( $url, 'permalink' ) ) { $out = array( 'permalink' => 'https://www.instagram.com/p/TEST/', 'is_published' => true, 'permalink_url' => 'https://facebook.com/111/posts/1' );
		} elseif ( 'DELETE' === $method ) { $out = array( 'success' => true ); }
	}
	update_option( 'cvkp_mock_state', $state );
	return array( 'headers' => array(), 'body' => wp_json_encode( $out ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
}, 10, 3 );
add_filter( 'cvkp_meta_status_wait', '__return_zero' );
add_filter( 'pre_wp_mail', function ( $r, $atts ) { file_put_contents( WP_CONTENT_DIR . '/cvkp-mock.log', 'MAIL ' . $atts['to'] . ' | ' . $atts['subject'] . "\n", FILE_APPEND ); return true; }, 10, 2 );
