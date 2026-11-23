<?php
/**
 * Minimal klient för Mailchimp Marketing API v3: skapar och uppdaterar kampanjutkast.
 * Inget skickas härifrån – utkastet granskas och skickas i Mailchimp.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Mailchimp {

	/** @var string */
	private $key;

	/** @var string */
	private $dc;

	public function __construct( $key = null ) {
		$this->key = null === $key ? CVKP_Settings::mailchimp_api_key() : (string) $key;
		$pos       = strrpos( $this->key, '-' );
		$this->dc  = false === $pos ? '' : preg_replace( '/[^a-z0-9]/', '', substr( $this->key, $pos + 1 ) );
	}

	public function is_configured() {
		return '' !== $this->key && '' !== $this->dc;
	}

	/** Adress till utkastet i Mailchimps redigerare. */
	public function edit_url( $web_id ) {
		return sprintf( 'https://%s.admin.mailchimp.com/campaigns/edit?id=%d', $this->dc, (int) $web_id );
	}

	/**
	 * @return string|WP_Error Kort beskrivning av kontot och listan.
	 */
	public function test() {
		$ping = $this->request( 'GET', '/ping' );
		if ( is_wp_error( $ping ) ) {
			return $ping;
		}
		$list_id = CVKP_Settings::get( 'mailchimp_list_id' );
		$list    = $this->request( 'GET', '/lists/' . rawurlencode( $list_id ) . '?fields=name,stats.member_count' );
		if ( is_wp_error( $list ) ) {
			return new WP_Error( 'cvkp_list', 'Anslutningen fungerar, men listan ' . $list_id . ' hittades inte: ' . $list->get_error_message() );
		}
		return sprintf(
			'Anslutningen fungerar. Listan "%s" har %d prenumeranter.',
			isset( $list['name'] ) ? $list['name'] : $list_id,
			isset( $list['stats']['member_count'] ) ? (int) $list['stats']['member_count'] : 0
		);
	}

	/**
	 * Skapar ett nytt utkast, eller uppdaterar det befintliga om det fortfarande är ett utkast.
	 *
	 * @param array       $settings subject_line, preview_text, title.
	 * @param string      $html     Mejlets HTML.
	 * @param string|null $existing_id Tidigare kampanj-ID.
	 * @return array|WP_Error array( 'id', 'web_id', 'url', 'created' )
	 */
	public function save_draft( array $settings, $html, $existing_id = null ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'cvkp_no_key', 'Mailchimp-nyckeln saknas eller har fel format (ska sluta med t.ex. -us1). Se Cykla&Vandra → Kanalpublicering → Inställningar.' );
		}

		$body = array(
			'settings' => array(
				'subject_line' => $settings['subject_line'],
				'preview_text' => $settings['preview_text'],
				'title'        => $settings['title'],
				'from_name'    => CVKP_Settings::get( 'from_name' ),
				'reply_to'     => CVKP_Settings::get( 'reply_to' ),
			),
		);

		$campaign = null;
		$created  = false;
		if ( $existing_id ) {
			$current = $this->request( 'GET', '/campaigns/' . rawurlencode( $existing_id ) . '?fields=id,web_id,status' );
			if ( is_wp_error( $current ) && 'cvkp_mailchimp_404' !== $current->get_error_code() ) {
				// Nätverksfel o.d.: skapa inte en dubblett, be användaren försöka igen.
				return new WP_Error( 'cvkp_mc_check', 'Kunde inte kontrollera det befintliga utkastet (' . $current->get_error_message() . '). Försök igen om en stund.' );
			}
			if ( ! is_wp_error( $current ) && isset( $current['status'] ) && 'save' === $current['status'] ) {
				$campaign = $this->request( 'PATCH', '/campaigns/' . rawurlencode( $existing_id ), $body );
			}
			// Annars (skickat, schemalagt eller raderat i Mailchimp): skapa ett nytt utkast.
		}

		if ( null === $campaign ) {
			$body['type']       = 'regular';
			$body['recipients'] = array( 'list_id' => CVKP_Settings::get( 'mailchimp_list_id' ) );
			$campaign           = $this->request( 'POST', '/campaigns', $body );
			$created            = true;
		}
		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$content = $this->request( 'PUT', '/campaigns/' . rawurlencode( $campaign['id'] ) . '/content', array( 'html' => $html ) );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return array(
			'id'      => $campaign['id'],
			'web_id'  => (int) $campaign['web_id'],
			'url'     => $this->edit_url( $campaign['web_id'] ),
			'created' => $created,
		);
	}

	/**
	 * @return array|WP_Error
	 */
	private function request( $method, $path, $body = null ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'cvkp_no_key', 'Mailchimp-nyckeln saknas.' );
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'cvkp:' . $this->key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( 'https://' . $this->dc . '.api.mailchimp.com/3.0' . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 ) {
			return is_array( $data ) ? $data : array();
		}

		$message = is_array( $data ) && isset( $data['detail'] ) ? $data['detail'] : 'HTTP ' . $code;
		if ( is_array( $data ) && ! empty( $data['errors'] ) ) {
			foreach ( $data['errors'] as $error ) {
				$message .= ' (' . ( isset( $error['field'] ) ? $error['field'] . ': ' : '' ) . ( isset( $error['message'] ) ? $error['message'] : '' ) . ')';
			}
		}
		return new WP_Error( 'cvkp_mailchimp_' . $code, $message );
	}
}
