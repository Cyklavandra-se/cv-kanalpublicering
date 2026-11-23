<?php
// Testmock: Mailchimp, Graph API och GitHub-releaser. Loggar alla anrop till wp-content/cvkp-mock.log.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	// GitHub: senaste release (versionen styrs av optionen cvkp_mock_release, t.ex. "0.4.0").
	if ( 'api.github.com' === $host && false !== strpos( $url, '/cv-kanalpublicering/releases/latest' ) ) {
		$v = get_option( 'cvkp_mock_release', '' );
		$body = $v ? array( 'tag_name' => 'v' . $v, 'html_url' => 'https://github.com/cyklavandra-se/cv-kanalpublicering/releases/tag/v' . $v, 'body' => "## Nytt\n- **Test** av uppdatering", 'published_at' => '2026-11-23T10:00:00Z', 'assets' => array( array( 'name' => 'cv-kanalpublicering.zip', 'browser_download_url' => 'https://github.com/cyklavandra-se/cv-kanalpublicering/releases/download/v' . $v . '/cv-kanalpublicering.zip' ) ) ) : array( 'message' => 'Not Found' );
		return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => $v ? 200 : 404, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}
	// Claude (Messages API). Optionen cvkp_mock_claude styr svaret: '' = text, 'refusal', '401'.
	if ( 'api.anthropic.com' === $host ) {
		file_put_contents( WP_CONTENT_DIR . '/cvkp-mock-claude.json', $args['body'] );
		file_put_contents( WP_CONTENT_DIR . '/cvkp-mock.log', 'POST ' . $url . ' beta=' . ( isset( $args['headers']['anthropic-beta'] ) ? $args['headers']['anthropic-beta'] : '-' ) . ' key=' . ( empty( $args['headers']['x-api-key'] ) ? 'saknas' : 'finns' ) . "\n", FILE_APPEND );
		$mode = get_option( 'cvkp_mock_claude', '' );
		if ( '401' === $mode ) {
			return array( 'headers' => array(), 'body' => wp_json_encode( array( 'type' => 'error', 'error' => array( 'type' => 'authentication_error', 'message' => 'invalid x-api-key' ) ) ), 'response' => array( 'code' => 401, 'message' => '' ), 'cookies' => array(), 'filename' => null );
		}
		$out = 'refusal' === $mode
			? array( 'stop_reason' => 'refusal', 'content' => array() )
			: array( 'stop_reason' => 'end_turn', 'content' => array( array( 'type' => 'thinking', 'thinking' => '' ), array( 'type' => 'text', 'text' => "Längtar ni också ut? Här är några av våra favoriter för nästa säsong.\n\nFloder, vinbyar och ert eget tempo." ) ) );
		return array( 'headers' => array(), 'body' => wp_json_encode( $out ), 'response' => array( 'code' => 200, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}
	if ( 'api.wordpress.org' === $host ) {
		return array( 'headers' => array(), 'body' => wp_json_encode( array( 'plugins' => array(), 'translations' => array(), 'no_update' => array() ) ), 'response' => array( 'code' => 200, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}
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
