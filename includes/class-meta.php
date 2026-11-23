<?php
/**
 * Minimal klient för Metas Graph API: Facebook-sidan och Instagram-kontot.
 *
 * - Facebook: länkinlägg som schemaläggs hos Meta (scheduled_publish_time). Syns och kan
 *   ändras eller tas bort i Meta Business Suite fram till publiceringen.
 * - Instagram: API:t saknar utkast och schemaläggning. Tillägget sparar därför inlägget i
 *   WordPress och publicerar det först vid den tid som en person har godkänt (WP-Cron).
 *
 * Ingenting här anropas utan att en person har klickat på "Schemalägg".
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Meta {

	/** @var string */
	private $token;

	/** @var string */
	private $page_id;

	/** @var string */
	private $ig_id;

	/** @var string */
	private $version;

	public function __construct() {
		$this->token   = CVKP_Settings::meta_token();
		$this->page_id = preg_replace( '/[^0-9]/', '', (string) CVKP_Settings::get( 'meta_page_id' ) );
		$this->ig_id   = preg_replace( '/[^0-9]/', '', (string) CVKP_Settings::get( 'meta_ig_id' ) );
		$version       = (string) CVKP_Settings::get( 'meta_version' );
		$this->version = preg_match( '/^v\d+\.\d+$/', $version ) ? $version : 'v24.0';
	}

	public function has_token() {
		return '' !== $this->token;
	}

	public function facebook_ready() {
		return $this->has_token() && '' !== $this->page_id;
	}

	public function instagram_ready() {
		return $this->has_token() && '' !== $this->ig_id;
	}

	/**
	 * Kontrollerar token, sidan och det kopplade Instagram-kontot. Med en sidtoken och tomt
	 * sid-ID hämtas sidan via /me.
	 *
	 * @return array|WP_Error array( 'message', 'page_id', 'ig_id' )
	 */
	public function test() {
		if ( ! $this->has_token() ) {
			return new WP_Error( 'cvkp_meta_token', 'Ingen åtkomsttoken. Lägg CVKP_META_PAGE_TOKEN i wp-config.php.' );
		}
		$page = $this->request( 'GET', '' !== $this->page_id ? $this->page_id : 'me', array( 'fields' => 'id,name,instagram_business_account{id,username}' ) );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( empty( $page['id'] ) ) {
			return new WP_Error( 'cvkp_meta_page', 'Meta svarade utan sid-ID. Är det en sidtoken (Page access token)?' );
		}
		$message = sprintf( 'Facebook-sidan "%s" (ID %s) hittades.', isset( $page['name'] ) ? $page['name'] : '', $page['id'] );
		$ig_id   = '';
		if ( ! empty( $page['instagram_business_account']['id'] ) ) {
			$ig_id    = (string) $page['instagram_business_account']['id'];
			$username = isset( $page['instagram_business_account']['username'] ) ? $page['instagram_business_account']['username'] : $ig_id;
			$message .= sprintf( ' Instagram-kontot @%s är kopplat (ID %s).', $username, $ig_id );
		} else {
			$message .= ' Men inget Instagram-konto är kopplat till sidan. Instagram måste vara ett företags- eller kreatörskonto som är kopplat till Facebook-sidan.';
		}
		return array(
			'message' => $message,
			'page_id' => (string) $page['id'],
			'ig_id'   => $ig_id,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Facebook                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Schemalägger ett länkinlägg på sidan. Meta hämtar länkens förhandsvisning nu,
	 * så länken måste redan vara publicerad.
	 *
	 * @return string|WP_Error Inläggets ID.
	 */
	public function facebook_schedule( $message, $link, $timestamp ) {
		if ( ! $this->facebook_ready() ) {
			return new WP_Error( 'cvkp_meta_fb', 'Facebook är inte konfigurerat (token och sid-ID).' );
		}
		$result = $this->request(
			'POST',
			$this->page_id . '/feed',
			array(
				'message'               => $message,
				'link'                  => $link,
				'published'             => 'false',
				'scheduled_publish_time' => (string) (int) $timestamp,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['id'] ) ? (string) $result['id'] : new WP_Error( 'cvkp_meta_fb', 'Meta svarade utan inläggs-ID.' );
	}

	/** @return array|WP_Error is_published, scheduled_publish_time, permalink_url */
	public function facebook_get( $post_id ) {
		return $this->request( 'GET', $post_id, array( 'fields' => 'is_published,scheduled_publish_time,permalink_url' ) );
	}

	/** @return true|WP_Error */
	public function facebook_delete( $post_id ) {
		$result = $this->request( 'DELETE', $post_id );
		return is_wp_error( $result ) ? $result : true;
	}

	/* ------------------------------------------------------------------ */
	/* Instagram                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Publicerar en bild eller en karusell (2–10 bilder). Anropas bara från den
	 * schemalagda händelsen, vid den tid som en person har godkänt.
	 *
	 * @param string   $caption Bildtext.
	 * @param string[] $image_urls Publika adresser till JPEG-bilder.
	 * @return array|WP_Error array( 'id', 'permalink' )
	 */
	public function instagram_publish( $caption, array $image_urls ) {
		if ( ! $this->instagram_ready() ) {
			return new WP_Error( 'cvkp_meta_ig', 'Instagram är inte konfigurerat (token och Instagram-konto-ID).' );
		}
		$image_urls = array_values( $image_urls );
		$count      = count( $image_urls );
		if ( $count < 1 || $count > 10 ) {
			return new WP_Error( 'cvkp_meta_ig', 'Instagram kräver 1–10 bilder.' );
		}

		if ( 1 === $count ) {
			$container = $this->request(
				'POST',
				$this->ig_id . '/media',
				array(
					'image_url' => $image_urls[0],
					'caption'   => $caption,
				)
			);
		} else {
			$children = array();
			foreach ( $image_urls as $url ) {
				$child = $this->request(
					'POST',
					$this->ig_id . '/media',
					array(
						'image_url'        => $url,
						'is_carousel_item' => 'true',
					)
				);
				if ( is_wp_error( $child ) ) {
					return $child;
				}
				$children[] = $child['id'];
			}
			foreach ( $children as $child_id ) {
				$ready = $this->wait_for_container( $child_id );
				if ( is_wp_error( $ready ) ) {
					return $ready;
				}
			}
			$container = $this->request(
				'POST',
				$this->ig_id . '/media',
				array(
					'media_type' => 'CAROUSEL',
					'children'   => implode( ',', $children ),
					'caption'    => $caption,
				)
			);
		}
		if ( is_wp_error( $container ) ) {
			return $container;
		}
		if ( empty( $container['id'] ) ) {
			return new WP_Error( 'cvkp_meta_ig', 'Meta svarade utan container-ID.' );
		}
		$ready = $this->wait_for_container( $container['id'] );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$published = $this->request( 'POST', $this->ig_id . '/media_publish', array( 'creation_id' => $container['id'] ) );
		if ( is_wp_error( $published ) ) {
			return $published;
		}
		$media_id  = isset( $published['id'] ) ? (string) $published['id'] : '';
		$permalink = '';
		if ( $media_id ) {
			$media = $this->request( 'GET', $media_id, array( 'fields' => 'permalink' ) );
			if ( ! is_wp_error( $media ) && ! empty( $media['permalink'] ) ) {
				$permalink = (string) $media['permalink'];
			}
		}
		return array(
			'id'        => $media_id,
			'permalink' => $permalink,
		);
	}

	/** Väntar tills Meta har hämtat och behandlat bilden (oftast några sekunder). */
	private function wait_for_container( $container_id ) {
		$tries = (int) apply_filters( 'cvkp_meta_status_tries', 10 );
		for ( $i = 0; $i < $tries; $i++ ) {
			$status = $this->request( 'GET', $container_id, array( 'fields' => 'status_code,status' ) );
			if ( is_wp_error( $status ) ) {
				return $status;
			}
			$code = isset( $status['status_code'] ) ? $status['status_code'] : '';
			if ( 'FINISHED' === $code || 'PUBLISHED' === $code ) {
				return true;
			}
			if ( 'ERROR' === $code || 'EXPIRED' === $code ) {
				return new WP_Error( 'cvkp_meta_ig', 'Instagram kunde inte behandla bilden: ' . ( isset( $status['status'] ) ? $status['status'] : $code ) );
			}
			if ( $i < $tries - 1 ) {
				sleep( (int) apply_filters( 'cvkp_meta_status_wait', 3 ) );
			}
		}
		return new WP_Error( 'cvkp_meta_ig', 'Instagram blev inte klart i tid med att behandla bilderna. Försök igen.' );
	}

	/* ------------------------------------------------------------------ */

	/**
	 * @return array|WP_Error
	 */
	private function request( $method, $path, array $params = array() ) {
		$url     = 'https://graph.facebook.com/' . $this->version . '/' . ltrim( $path, '/' );
		$secret  = defined( 'CVKP_META_APP_SECRET' ) ? (string) CVKP_META_APP_SECRET : '';
		$params['access_token'] = $this->token;
		if ( '' !== $secret ) {
			$params['appsecret_proof'] = hash_hmac( 'sha256', $this->token, $secret );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 45,
		);
		if ( 'GET' === $method || 'DELETE' === $method ) {
			$url = $params ? add_query_arg( array_map( 'rawurlencode', $params ), $url ) : $url;
		} else {
			$args['body'] = $params;
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 && is_array( $data ) && empty( $data['error'] ) ) {
			return $data;
		}

		$message = 'HTTP ' . $code;
		if ( is_array( $data ) && ! empty( $data['error'] ) ) {
			$e       = $data['error'];
			$message = ! empty( $e['error_user_msg'] ) ? $e['error_user_msg'] : ( isset( $e['message'] ) ? $e['message'] : $message );
			if ( isset( $e['code'] ) && in_array( (int) $e['code'], array( 102, 190 ), true ) ) {
				$message .= ' – token har gått ut eller saknar behörighet. Skapa en ny enligt docs/meta-installation.md.';
			} elseif ( isset( $e['code'] ) && in_array( (int) $e['code'], array( 10, 200 ), true ) ) {
				$message .= ' – appen saknar behörighet (pages_manage_posts, instagram_content_publish m.fl.).';
			}
		}
		return new WP_Error( 'cvkp_meta_' . $code, $message );
	}
}
