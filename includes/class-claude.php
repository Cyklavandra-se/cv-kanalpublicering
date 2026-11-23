<?php
/**
 * Claude-rutan i ett utskick: klistra in länkar till resor (och gärna en egen ingress),
 * så fylls utskicket i.
 *
 * - Varje länk blir en resa i utskicket. Texten är resesidans första riktiga stycke
 *   (inte SEO-beskrivningen).
 * - Ingressen: den som skrivs i rutan används som den är. Lämnas den tom skriver
 *   Claude en kort ingress utifrån resorna.
 *
 * Nyckeln är densamma som AI-sök använder (CVAI_API_KEY eller AI-söks inställningar),
 * eller en egen CVKP_ANTHROPIC_API_KEY i wp-config.php. Den läggs aldrig i repot.
 *
 * Anropet görs med wp_remote_post mot Messages API, som i AI-sök: tillägget ska
 * fungera på PHP 7.4 utan Composer, och Anthropics PHP-SDK kräver båda.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Claude {

	const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/** Kortaste stycke som räknas som resans text (rubriker och "ca 63 km" hoppas över). */
	const MIN_PARAGRAPH = 80;

	public static function init() {
		add_action( 'edit_form_after_title', array( __CLASS__, 'box' ) );
		add_action( 'wp_ajax_cvkp_claude_fill', array( __CLASS__, 'ajax_fill' ) );
	}

	/** Nyckeln: egen konstant, annars AI-söks konstant eller inställning. */
	public static function api_key() {
		if ( defined( 'CVKP_ANTHROPIC_API_KEY' ) && CVKP_ANTHROPIC_API_KEY ) {
			return (string) CVKP_ANTHROPIC_API_KEY;
		}
		if ( defined( 'CVAI_API_KEY' ) && CVAI_API_KEY ) {
			return (string) CVAI_API_KEY;
		}
		$ai = get_option( 'cvai_options', array() );
		return is_array( $ai ) && ! empty( $ai['api_key'] ) ? (string) $ai['api_key'] : '';
	}

	/** Var nyckeln kommer ifrån, för inställningssidan. */
	public static function key_source() {
		if ( defined( 'CVKP_ANTHROPIC_API_KEY' ) && CVKP_ANTHROPIC_API_KEY ) {
			return 'CVKP_ANTHROPIC_API_KEY i wp-config.php';
		}
		if ( defined( 'CVAI_API_KEY' ) && CVAI_API_KEY ) {
			return 'AI-söks nyckel (CVAI_API_KEY i wp-config.php)';
		}
		return '' !== self::api_key() ? 'AI-söks inställningar' : '';
	}

	public static function model() {
		$model = trim( (string) CVKP_Settings::get( 'claude_model' ) );
		return preg_match( '/^claude-[a-z0-9.-]+$/', $model ) ? $model : 'claude-opus-5-5';
	}

	/* ------------------------------------------------------------------ */
	/* Rutan                                                                */
	/* ------------------------------------------------------------------ */

	public static function box( $post ) {
		if ( CVKP_Mailing::POST_TYPE !== $post->post_type ) {
			return;
		}
		$has_key = '' !== self::api_key();
		?>
		<div id="cvkp-claude" class="postbox cvkp-claude">
			<div class="postbox-header"><h2>Claude – fyll i utskicket</h2></div>
			<div class="inside">
				<p class="description">Klistra in länkar till resorna ni vill lyfta fram, en per rad. Varje resa läggs till under Resor med första stycket från resesidan som text. Skriv en ingress om ni vill – annars skriver Claude en kort ingress utifrån resorna.</p>
				<div class="cvkp-cols">
					<p class="cvkp-field">
						<label for="cvkp-claude-intro"><strong>Ingress (valfri)</strong></label>
						<textarea id="cvkp-claude-intro" class="widefat" rows="6" placeholder="Tomt = Claude skriver en ingress"></textarea>
					</p>
					<p class="cvkp-field">
						<label for="cvkp-claude-links"><strong>Länkar till resor</strong></label>
						<textarea id="cvkp-claude-links" class="widefat" rows="6" placeholder="https://cyklavandra.se/cykla/cykla-pa-egen-hand/mosel-vecka/"></textarea>
					</p>
				</div>
				<p>
					<button type="button" class="button button-primary" id="cvkp-claude-run">Fyll i utskicket</button>
					<span class="spinner"></span>
					<span class="cvkp-claude-status" role="status"></span>
				</p>
				<?php if ( ! $has_key ) : ?>
					<p class="description cvkp-warn">Ingen nyckel till Claude hittades. Resorna och en egen ingress fungerar ändå, men Claude kan inte skriva ingressen. Se Kanalpublicering → Inställningar.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Fyll i                                                               */
	/* ------------------------------------------------------------------ */

	public static function ajax_fill() {
		check_ajax_referer( 'cvkp_trip', 'nonce' );
		if ( ! current_user_can( CVKP_Settings::CAPABILITY ) ) {
			wp_send_json_error( 'Behörighet saknas.', 403 );
		}
		$intro = isset( $_POST['intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['intro'] ) ) : '';
		$links = isset( $_POST['links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['links'] ) ) : '';

		$result = self::fill( $intro, $links );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$rows = '';
		foreach ( $result['trips'] as $trip ) {
			ob_start();
			CVKP_Mailing::trip_row_html(
				array(
					'id'   => $trip['id'],
					'text' => $trip['text'],
				),
				CVKP_Trip_Data::get( $trip['id'] ),
				uniqid()
			);
			$rows .= ob_get_clean();
		}
		wp_send_json_success(
			array(
				'intro'    => $result['intro'],
				'rows'     => $rows,
				'warnings' => $result['warnings'],
				'by'       => $result['by'],
			)
		);
	}

	/**
	 * @return array|WP_Error array( 'intro', 'trips' => [ id, title, text ], 'warnings', 'by' )
	 */
	public static function fill( $intro, $links ) {
		$urls = array();
		foreach ( preg_split( '/\s+/', (string) $links ) as $candidate ) {
			$candidate = trim( $candidate, " \t\n\r<>\"'" );
			if ( preg_match( '#^https?://#i', $candidate ) ) {
				$urls[] = esc_url_raw( $candidate );
			}
		}
		$urls = array_values( array_unique( $urls ) );
		if ( ! $urls ) {
			return new WP_Error( 'cvkp_claude_links', 'Klistra in minst en länk till en resa.' );
		}

		$trips    = array();
		$warnings = array();
		foreach ( $urls as $url ) {
			$id = self::page_from_url( $url );
			if ( ! $id ) {
				$warnings[] = 'Hittade ingen sida för ' . $url;
				continue;
			}
			$text = self::first_paragraph( $id );
			if ( '' === $text ) {
				$warnings[] = 'Hittade inget stycke på ' . get_the_title( $id ) . ' – fyll i texten själv';
			}
			if ( ! CVKP_Trip_Data::is_trip( $id ) ) {
				$warnings[] = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) . ' är inte taggad cykla/vandra – kontrollera att det är en resa';
			}
			$trips[] = array(
				'id'    => $id,
				'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'text'  => $text,
			);
		}
		if ( ! $trips ) {
			return new WP_Error( 'cvkp_claude_none', 'Ingen av länkarna ledde till en sida på sajten. ' . implode( '. ', $warnings ) . '.' );
		}

		$by = 'egen';
		if ( '' === trim( $intro ) ) {
			$written = self::write_intro( $trips );
			if ( is_wp_error( $written ) ) {
				$warnings[] = 'Claude kunde inte skriva ingressen: ' . $written->get_error_message();
				$intro      = '';
				$by         = 'ingen';
			} else {
				$intro = $written;
				$by    = 'claude';
			}
		}

		return array(
			'intro'    => $intro,
			'trips'    => $trips,
			'warnings' => $warnings,
			'by'       => $by,
		);
	}

	/** Sidans ID från en länk. Norska länkar (WPML) ger den svenska sidan. */
	public static function page_from_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return 0;
		}
		$id = (int) url_to_postid( $url );
		if ( ! $id ) {
			// Sidor med föräldrar: slå upp sökvägen direkt.
			$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			$page = '' !== $path ? get_page_by_path( $path ) : null;
			$id   = $page ? (int) $page->ID : 0;
		}
		if ( $id && CVKP_Wpml::active() ) {
			$sv = (int) apply_filters( 'wpml_object_id', $id, 'page', true, CVKP_Wpml::default_language() );
			$id = $sv ? $sv : $id;
		}
		return ( $id && 'page' === get_post_type( $id ) ) ? $id : 0;
	}

	/**
	 * Första riktiga stycket i sidtexten. Avadas kortkoder blir styckebrytningar, och
	 * korta stycken (rubriker, "ca 63 km") hoppas över.
	 */
	public static function first_paragraph( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$html = (string) $post->post_content;
		$html = preg_replace( '/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html );
		$html = preg_replace( '/\[\/?[a-z_]+[^\]]*\]/i', "\n\n", $html );
		$html = wpautop( $html );
		if ( ! preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
			return '';
		}
		foreach ( $m[1] as $paragraph ) {
			$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $paragraph ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( CVKP_Social::length( $text ) >= self::MIN_PARAGRAPH ) {
				return $text;
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Claude                                                               */
	/* ------------------------------------------------------------------ */

	/** @return string|WP_Error Ingressen. */
	public static function write_intro( array $trips ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'cvkp_claude_key', 'ingen nyckel (se Kanalpublicering → Inställningar)' );
		}

		$list = array();
		foreach ( $trips as $trip ) {
			$data   = CVKP_Trip_Data::get( $trip['id'] );
			$list[] = '<resa>' . "\nNamn: " . $trip['title'] . ( $data && '' !== $data['label'] ? "\nFakta: " . $data['label'] : '' ) . "\nFrån resesidan: " . $trip['text'] . "\n</resa>";
		}

		$system = 'Du skriver ingressen till Cykla & Vandras nyhetsbrev. Cykla & Vandra är en svensk researrangör med cykel- och vandringsresor i Europa, många självguidade. '
			. 'Ingressen står överst i mejlet under rubriken, före resorna, och samma text används i blogginlägget. '
			. "Skriv på svenska och tilltala läsarna med ni. Tonen är varm, personlig och konkret, som från ett litet team som själva har rest. "
			. 'Två till fyra meningar, högst 70 ord. Väck lust att läsa vidare om resorna, men beskriv dem inte en och en – det görs längre ner. '
			. 'Ingen rubrik, inga länkar, inga hashtaggar, inga emoji och ingen hälsningsfras. Hitta inte på priser, datum, rabatter eller fakta som inte står i underlaget. '
			. 'Svara bara med ingressens text.';

		$body = array(
			'model'         => self::model(),
			'max_tokens'    => 16000,
			// Kort text: låg ansträngning räcker och ger snabbt svar.
			'output_config' => array( 'effort' => 'low' ),
			'system'        => $system,
			'messages'      => array(
				array(
					'role'    => 'user',
					'content' => "Resorna i utskicket (underlag från resesidorna):\n\n" . implode( "\n\n", $list ) . "\n\nSkriv ingressen.",
				),
			),
			// Om säkerhetsfiltret skulle neka svarar en annan modell i stället för att inget händer.
			'fallbacks'     => 'default',
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 90,
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
					'anthropic-beta'    => 'server-side-fallback-2026-07-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$message = is_array( $json ) && isset( $json['error']['message'] ) ? $json['error']['message'] : 'HTTP ' . $code;
			if ( 401 === $code ) {
				$message = 'nyckeln godtogs inte';
			} elseif ( 429 === $code || 529 === $code ) {
				$message = 'Claude är upptagen just nu, försök igen om en stund';
			}
			return new WP_Error( 'cvkp_claude_' . $code, $message );
		}
		if ( isset( $json['stop_reason'] ) && 'refusal' === $json['stop_reason'] ) {
			return new WP_Error( 'cvkp_claude_refusal', 'Claude avböjde att skriva texten. Skriv ingressen själv.' );
		}

		$text = '';
		foreach ( (array) ( isset( $json['content'] ) ? $json['content'] : array() ) as $block ) {
			if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}
		$text = trim( $text );
		return '' === $text ? new WP_Error( 'cvkp_claude_empty', 'tomt svar' ) : $text;
	}
}
