<?php
/**
 * Claude-rutan i ett utskick: klistra in länkar till resor (och gärna en egen ingress),
 * så fylls hela utskicket i.
 *
 * - Varje länk blir en resa i utskicket. Texten är resesidans första riktiga stycke
 *   (inte SEO-beskrivningen).
 * - Ingressen: den som skrivs i rutan används som den är. Lämnas den tom skriver
 *   Claude en.
 * - Claude skriver också ämnesrad, förhandstext, rubriker, blogg-utdrag och texterna
 *   till Facebook och Instagram. Webbläsaren fyller bara i fält som är tomma – det
 *   som redan står skrivs aldrig över, och rabattkoden rörs aldrig.
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

	/**
	 * Fälten som Claude skriver: fältnamn => beskrivning till Claude.
	 * Samma namn som i utskicket (CVKP_Mailing::defaults()).
	 */
	const FIELDS = array(
		'subject'       => 'Ämnesrad i inkorgen. Högst 60 tecken, lockande och konkret. Högst en emoji, gärna ingen.',
		'preheader'     => 'Förhandstext som syns efter ämnesraden i inkorgen. En mening, högst 100 tecken, som kompletterar ämnesraden utan att upprepa den.',
		'eyebrow'       => 'Kort överrubrik ovanför rubriken i mejlet, 2–5 ord, till exempel "Nya självguidade resor". Skrivs med vanliga bokstäver (mallen gör versaler).',
		'heading'       => 'Rubrik överst i mejlet och på blogginlägget. Högst 8 ord.',
		'intro'         => 'Ingress under rubriken, före resorna. 2–4 meningar, högst 70 ord. Väck lust att läsa vidare men beskriv inte resorna en och en. Ingen hälsningsfras. Är ingressen redan given: returnera den oförändrad.',
		'trips_heading' => 'Rubrik ovanför resorna i mejlet, högst 5 ord. Tom sträng om det bara är en resa.',
		'trips_intro'   => 'En kort mening före resorna, eller tom sträng om den inte behövs.',
		'blog_excerpt'  => 'Utdrag till blogginlägget, visas i bloggflödet och när inlägget delas. 1–2 meningar, högst 160 tecken.',
		'fb_text'       => 'Text till Facebook-inlägget. Inlägget visar länken till blogginlägget med bild, så texten ska inte innehålla någon adress. 2–4 korta stycken: en inledning, resorna med en rad var (gärna med emoji 🚲 för cykling och 🥾 för vandring) och en uppmaning att läsa mer. Högst 2 hashtaggar, gärna inga.',
		'ig_text'       => 'Bildtext till Instagram-karusellen med resornas bilder. Personlig: börja med en hälsning från avsändaren, skriv som en människa i teamet. Nämn resorna (en rad var, gärna med emoji). Avsluta med länkraden och sedan 3–5 hashtaggar på sista raden. Inga adresser – länkar går inte att klicka på Instagram. Högst 1 500 tecken.',
	);

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
				<p class="description">Klistra in länkar till resorna ni vill lyfta fram, en per rad. Varje resa läggs till under Resor med de två första styckena från resesidan som text. Claude skriver sedan ingress (om ni inte skriver en själva), ämnesrad, förhandstext, rubriker, blogg-utdrag och texterna till Facebook och Instagram. <strong>Bara tomma fält fylls i</strong> – det som redan står ändras inte, och rabattkoden skriver ni själva. Fyll gärna i rabattkod och vem som hälsar på Instagram först, så kommer de med i texterna.</p>
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
					<?php $named = '' !== trim( (string) $post->post_title ); ?>
					<button type="button" class="button button-primary" id="cvkp-claude-run" <?php disabled( ! $named ); ?>>Fyll i utskicket</button>
					<span class="description cvkp-claude-needs-title"<?php echo $named ? ' hidden' : ''; ?>>Ge utskicket ett namn först (fältet ovanför).</span>
					<span class="spinner"></span>
					<span class="cvkp-claude-status" role="status"></span>
				</p>
				<?php if ( ! $has_key ) : ?>
					<p class="description cvkp-warn">Ingen nyckel till Claude hittades. Resorna och en egen ingress fungerar ändå, men Claude kan inte skriva texterna. Se Kanalpublicering → Inställningar.</p>
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
		$intro   = isset( $_POST['intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['intro'] ) ) : '';
		$links   = isset( $_POST['links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['links'] ) ) : '';
		$context = array();
		foreach ( array( 'heading', 'subject', 'code', 'code_note', 'ig_signer' ) as $key ) {
			$context[ $key ] = isset( $_POST['context'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['context'][ $key ] ) ) : '';
		}

		$result = self::fill( $intro, $links, $context );
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
				'texts'    => $result['texts'],
				'rows'     => $rows,
				'warnings' => $result['warnings'],
				'by'       => $result['by'],
			)
		);
	}

	/**
	 * @param string $intro   Egen ingress (tom = Claude skriver).
	 * @param string $links   Länkar, en per rad.
	 * @param array  $context Det som redan står i utskicket: heading, subject, code, code_note, ig_signer.
	 * @return array|WP_Error array( 'intro', 'texts' => fält => text, 'trips' => [ id, title, text ], 'warnings', 'by' )
	 */
	public static function fill( $intro, $links, array $context = array() ) {
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
			$text = self::first_paragraphs( $id, 2 );
			if ( '' === $text ) {
				$warnings[] = 'Hittade ingen text på ' . get_the_title( $id ) . ' – fyll i texten själv';
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

		$intro = trim( $intro );
		$texts = self::write_texts( $trips, $intro, $context );
		if ( is_wp_error( $texts ) ) {
			$warnings[] = 'Claude kunde inte skriva texterna (' . $texts->get_error_message() . '). Resorna' . ( '' !== $intro ? ' och er ingress' : '' ) . ' är ifyllda, resten får ni skriva själva';
			$texts      = array();
			$by         = '' !== $intro ? 'egen' : 'ingen';
		} else {
			$by = '' !== $intro ? 'egen+claude' : 'claude';
			if ( '' === $intro && isset( $texts['intro'] ) ) {
				$intro = $texts['intro'];
			}
			unset( $texts['intro'] ); // Ingressen skickas separat (egen ingress vinner alltid).
		}

		return array(
			'intro'    => $intro,
			'texts'    => $texts,
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

	/** Avadas kortkoder vars innehåll är en bild, ett galleri eller en video – aldrig text. */
	const MEDIA_SHORTCODES = 'fusion_imageframe|fusion_image|fusion_gallery_image|fusion_gallery|fusion_image_before_after|fusion_images|fusion_youtube|fusion_vimeo|fusion_video|fusion_map|fusion_code|fusion_slide|fusion_slider|fusion_post_slider';

	/**
	 * De första riktiga styckena i sidtexten (standard två), med en tom rad emellan.
	 *
	 * Avadas bild- och videokortkoder tas bort med sitt innehåll (det är bildadresser),
	 * övriga kortkoder blir styckebrytningar. Korta stycken (rubriker, "ca 63 km") och
	 * stycken som bara är en adress hoppas över.
	 */
	public static function first_paragraphs( $post_id, $count = 2 ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$html = (string) $post->post_content;
		$html = preg_replace( '/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html );
		$html = preg_replace( '/\[(' . self::MEDIA_SHORTCODES . ')\b[^\]]*\].*?\[\/\1\]/is', "\n\n", $html );
		$html = preg_replace( '/\[\/?[a-z_]+[^\]]*\]/i', "\n\n", $html );
		$html = wpautop( $html );
		if ( ! preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
			return '';
		}
		$found = array();
		foreach ( $m[1] as $paragraph ) {
			$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $paragraph ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( ! self::is_prose( $text ) ) {
				continue;
			}
			$found[] = $text;
			if ( count( $found ) >= $count ) {
				break;
			}
		}
		return implode( "\n\n", $found );
	}

	/** Riktig löptext: tillräckligt lång, flera ord och inte bara en adress eller ett filnamn. */
	public static function is_prose( $text ) {
		if ( CVKP_Social::length( $text ) < self::MIN_PARAGRAPH ) {
			return false;
		}
		if ( preg_match( '#^\S*(https?://|www\.|\.(jpe?g|png|webp|gif|pdf)\b)\S*$#i', $text ) ) {
			return false;
		}
		// Minst åtta ord, och mindre än hälften av texten får vara adresser.
		if ( count( preg_split( '/\s+/u', $text ) ) < 8 ) {
			return false;
		}
		$urls = preg_match_all( '#https?://\S+#i', $text, $u ) ? strlen( implode( '', $u[0] ) ) : 0;
		return $urls * 2 < strlen( $text );
	}

	/* ------------------------------------------------------------------ */
	/* Claude                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Ber Claude skriva utskickets texter i ett anrop, som JSON enligt FIELDS.
	 *
	 * @return array|WP_Error fält => text (bara fält i FIELDS).
	 */
	public static function write_texts( array $trips, $intro, array $context ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'cvkp_claude_key', 'ingen nyckel, se Kanalpublicering → Inställningar' );
		}
		$context = wp_parse_args(
			$context,
			array(
				'heading'   => '',
				'subject'   => '',
				'code'      => '',
				'code_note' => '',
				'ig_signer' => '',
			)
		);

		$list = array();
		foreach ( $trips as $trip ) {
			$data   = CVKP_Trip_Data::get( $trip['id'] );
			$list[] = "<resa>\nNamn: " . $trip['title']
				. ( $data && '' !== $data['label'] ? "\nFakta: " . $data['label'] . ( $data['level'] ? ', nivå ' . $data['level'] . ' av 4' : '' ) : '' )
				. "\nFrån resesidan: " . ( '' !== $trip['text'] ? $trip['text'] : '(inget stycke hittades)' )
				. "\n</resa>";
		}

		$facts = array();
		if ( '' !== $intro ) {
			$facts[] = "Ingress som redaktören har skrivit (använd den, returnera den oförändrad som intro):\n" . $intro;
		}
		if ( '' !== $context['heading'] ) {
			$facts[] = 'Rubriken är redan bestämd: ' . $context['heading'];
		}
		if ( '' !== $context['subject'] ) {
			$facts[] = 'Ämnesraden är redan bestämd: ' . $context['subject'];
		}
		if ( '' !== $context['code'] ) {
			$facts[] = 'Rabattkod: ' . $context['code'] . ( '' !== $context['code_note'] ? ' (' . $context['code_note'] . ')' : '' ) . '. Nämn den gärna i ämnesrad, förhandstext, Facebook och Instagram.';
		} else {
			$facts[] = 'Det finns ingen rabattkod – nämn inga rabatter eller erbjudanden.';
		}
		$signer   = '' !== $context['ig_signer'] ? $context['ig_signer'] : '';
		$facts[]  = '' !== $signer
			? 'Instagram-texten skrivs av ' . $signer . ' i teamet. Börja med en hälsning från ' . $signer . ' och avsluta med "/' . $signer . '" före hashtaggarna.'
			: 'Instagram-texten skrivs från hela teamet (' . implode( ', ', CVKP_Settings::team() ) . '). Börja med en hälsning från oss på Cykla & Vandra.';
		$facts[]  = 'Länkraden på Instagram ska vara exakt: ' . CVKP_Settings::get( 'ig_link_text' );
		$fixed    = CVKP_Social::hashtags_in( (string) CVKP_Settings::get( 'ig_hashtags' ) );
		if ( $fixed ) {
			$facts[] = 'Dessa hashtaggar ska alltid med på Instagram: ' . implode( ' ', $fixed ) . '. Totalt 3–5 hashtaggar.';
		}

		$system = 'Du skriver texterna till ett utskick från Cykla & Vandra, en svensk researrangör med cykel- och vandringsresor i Europa, många självguidade. '
			. 'Samma utskick blir nyhetsbrev, blogginlägg, Facebook-inlägg och Instagram-karusell. Resornas egna texter från resesidorna läggs in separat i nyhetsbrevet, så du skriver bara de övriga texterna. '
			. 'Skriv på svenska och tilltala läsarna med ni. Tonen är varm, personlig och konkret, som från ett litet team som själva har cyklat och vandrat. '
			. 'Hitta inte på priser, datum, rabatter, avgångar eller fakta som inte står i underlaget. Inga adresser eller länkar i texterna. '
			. 'Följ beskrivningen för varje fält i JSON-schemat.';

		$properties = array();
		foreach ( self::FIELDS as $field => $description ) {
			$properties[ $field ] = array(
				'type'        => 'string',
				'description' => $description,
			);
		}

		$body = array(
			'model'         => self::model(),
			'max_tokens'    => 16000,
			'output_config' => array(
				// Flera korta texter: medel ansträngning ger bra språk utan lång väntan.
				'effort' => 'medium',
				'format' => array(
					'type'   => 'json_schema',
					'schema' => array(
						'type'                 => 'object',
						'properties'           => $properties,
						'required'             => array_keys( self::FIELDS ),
						'additionalProperties' => false,
					),
				),
			),
			'system'        => $system,
			'messages'      => array(
				array(
					'role'    => 'user',
					'content' => "Resorna i utskicket (underlag från resesidorna):\n\n" . implode( "\n\n", $list ) . "\n\n" . implode( "\n\n", $facts ) . "\n\nSkriv texterna.",
				),
			),
			// Om säkerhetsfiltret skulle neka svarar en annan modell i stället för att inget händer.
			'fallbacks'     => 'default',
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 120,
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
		$stop = isset( $json['stop_reason'] ) ? $json['stop_reason'] : '';
		if ( 'refusal' === $stop ) {
			return new WP_Error( 'cvkp_claude_refusal', 'Claude avböjde' );
		}
		if ( 'max_tokens' === $stop ) {
			return new WP_Error( 'cvkp_claude_long', 'svaret blev för långt' );
		}

		$text = '';
		foreach ( (array) ( isset( $json['content'] ) ? $json['content'] : array() ) as $block ) {
			if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}
		$data = json_decode( trim( $text ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'cvkp_claude_json', 'oväntat svar' );
		}

		$out = array();
		foreach ( array_keys( self::FIELDS ) as $field ) {
			$value = isset( $data[ $field ] ) && is_string( $data[ $field ] ) ? trim( $data[ $field ] ) : '';
			if ( '' !== $value ) {
				$out[ $field ] = sanitize_textarea_field( $value );
			}
		}
		return $out;
	}
}
