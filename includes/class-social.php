<?php
/**
 * Facebook och Instagram för ett utskick.
 *
 * Flöde:
 * 1. "Spara och skapa utkast" fyller i textförslag (om fälten är tomma) och gör beskurna
 *    bilder. Utkastet ligger kvar i WordPress – ingenting skickas till Meta.
 * 2. En person granskar text och bilder, väljer tid och klickar "Schemalägg". Det är
 *    godkännandet. Tiden måste ligga minst 15 minuter fram, så att det går att ångra.
 *    - Facebook: schemaläggs hos Meta och syns i Meta Business Suite.
 *    - Instagram: Metas API saknar schemaläggning, så WordPress publicerar vid tiden (WP-Cron).
 * 3. "Avbryt" tar bort schemaläggningen. Ändringar efter schemaläggningen kommer inte med
 *    förrän man avbryter och schemalägger igen.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Social {

	const META_FB = '_cvkp_facebook';
	const META_IG = '_cvkp_instagram';
	const CRON    = 'cvkp_instagram_publish';

	/** Minsta tid fram till publicering (Meta kräver 10 minuter för Facebook). */
	const MIN_LEAD = 900;

	/** Längsta tid fram till publicering (Metas gräns för schemalagda sidinlägg är 30 dagar). */
	const MAX_LEAD = 2505600; // 29 dagar.

	/** Ett Instagram-inlägg som blivit så här försenat (t.ex. om WP-Cron stått still) publiceras inte. */
	const LATE_LIMIT = 21600; // 6 timmar.

	const CAPTION_MAX  = 2200;
	const HASHTAGS_MAX = 30;

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'publish_instagram' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Textförslag                                                          */
	/* ------------------------------------------------------------------ */

	/** HTML från editorn som ren text med stycken. */
	public static function plain( $html ) {
		$html = (string) $html;
		$html = preg_replace( '#</p>\s*<p[^>]*>#i', "\n\n", $html );
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
		$html = preg_replace( '#<li[^>]*>#i', "\n• ", $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t]+/u", ' ', $text );
		$text = preg_replace( "/ *\n */u", "\n", $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", $text );
		return trim( $text );
	}

	/** Kortar text till hela meningar, högst $max tecken. */
	public static function shorten( $text, $max ) {
		$text = trim( (string) $text );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) <= $max : strlen( $text ) <= $max ) {
			return $text;
		}
		$cut  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		$stop = max( strrpos( $cut, '. ' ), strrpos( $cut, '! ' ), strrpos( $cut, '? ' ), strrpos( $cut, "\n" ) );
		if ( $stop > $max / 3 ) {
			return rtrim( substr( $cut, 0, $stop + 1 ) );
		}
		return rtrim( preg_replace( '/\s+\S*$/u', '', $cut ) ) . ' …';
	}

	/** Resorna i utskicket, med fakta. */
	private static function trips( array $d ) {
		$out = array();
		foreach ( (array) $d['trips'] as $row ) {
			$data = CVKP_Trip_Data::get( (int) $row['id'] );
			if ( $data ) {
				$out[] = CVKP_Email_Renderer::trip_row( $row, $data ) + array( 'row' => $row );
			}
		}
		return $out;
	}

	public static function suggest_facebook( array $d ) {
		$intro = self::plain( '' !== trim( $d['blog_intro'] ) ? $d['blog_intro'] : $d['intro'] );
		$parts = array( trim( $d['heading'] ) );
		if ( '' !== $intro ) {
			$parts[] = self::shorten( $intro, 450 );
		}
		if ( '' !== trim( $d['code'] ) ) {
			$parts[] = 'Rabattkod: ' . trim( $d['code'] ) . ( '' !== trim( $d['code_note'] ) ? ' – ' . trim( $d['code_note'] ) : '' );
		}
		$lines = array();
		foreach ( self::trips( $d ) as $trip ) {
			$lines[] = self::trip_emoji( $trip['data'] ) . ' ' . $trip['heading'];
		}
		if ( $lines ) {
			$parts[] = implode( "\n", $lines );
		}
		return implode( "\n\n", array_filter( $parts ) );
	}

	public static function suggest_instagram( array $d ) {
		$signer = trim( (string) $d['ig_signer'] );
		$hello  = '' !== $signer ? sprintf( 'Hej från %s på Cykla & Vandra! 👋', $signer ) : 'Hej från oss på Cykla & Vandra! 👋';
		$intro  = self::plain( '' !== trim( $d['blog_intro'] ) ? $d['blog_intro'] : $d['intro'] );

		$parts = array( $hello );
		if ( '' !== $intro ) {
			$parts[] = self::shorten( $intro, 400 );
		}
		if ( '' !== trim( $d['code'] ) ) {
			$parts[] = 'Använd rabattkoden ' . trim( $d['code'] ) . ( '' !== trim( $d['code_note'] ) ? ' – ' . trim( $d['code_note'] ) : '' );
		}
		$lines = array();
		foreach ( self::trips( $d ) as $trip ) {
			if ( isset( $trip['row']['ig'] ) && '0' === (string) $trip['row']['ig'] ) {
				continue;
			}
			$lines[] = self::trip_emoji( $trip['data'] ) . ' ' . $trip['heading'] . ( '' !== trim( $trip['label'] ) && false === strpos( $trip['label'], $trip['heading'] ) ? ' (' . self::label_short( $trip ) . ')' : '' );
		}
		if ( $lines ) {
			$parts[] = ( count( $lines ) > 1 ? 'Svep för att se resorna:' : 'Resan:' ) . "\n" . implode( "\n", $lines );
		}
		$parts[] = (string) CVKP_Settings::get( 'ig_link_text' );
		if ( '' !== $signer ) {
			$parts[] = '/' . $signer;
		}
		$parts[] = implode( ' ', self::suggest_hashtags( $d ) );
		return implode( "\n\n", array_filter( array_map( 'trim', $parts ) ) );
	}

	/** Etiketten utan typen (som redan syns i emojin): "7 dagar · Ungern". */
	private static function label_short( array $trip ) {
		$label = $trip['label'];
		if ( '' !== $trip['data']['type'] && 0 === strpos( $label, $trip['data']['type'] . ' · ' ) ) {
			$label = substr( $label, strlen( $trip['data']['type'] . ' · ' ) );
		}
		return $label;
	}

	private static function trip_emoji( array $data ) {
		if ( 'Cykling' === $data['type'] ) {
			return '🚲';
		}
		return 'Vandring' === $data['type'] ? '🥾' : '📍';
	}

	/**
	 * 3–5 hashtaggar: de fasta från inställningarna, sedan typ och land från resorna.
	 *
	 * @return string[]
	 */
	public static function suggest_hashtags( array $d ) {
		$tags = self::hashtags_in( (string) CVKP_Settings::get( 'ig_hashtags' ) );
		$more = array();
		foreach ( self::trips( $d ) as $trip ) {
			if ( 'Cykling' === $trip['data']['type'] ) {
				$more[] = '#cykelsemester';
			} elseif ( 'Vandring' === $trip['data']['type'] ) {
				$more[] = '#vandringsresa';
			}
		}
		foreach ( self::trips( $d ) as $trip ) {
			foreach ( preg_split( '/\s*(?:&|,|\boch\b)\s*/u', (string) $trip['data']['country'] ) as $country ) {
				$tag = self::to_hashtag( $country );
				if ( '' !== $tag ) {
					$more[] = $tag;
				}
			}
		}
		foreach ( self::trips( $d ) as $trip ) {
			if ( $trip['data']['self_guided'] ) {
				$more[] = '#självguidat';
				break;
			}
		}
		$more[] = '#aktivsemester';
		$more[] = '#reseinspiration';

		$all = array();
		foreach ( array_merge( $tags, $more ) as $tag ) {
			$all[ function_exists( 'mb_strtolower' ) ? mb_strtolower( $tag ) : strtolower( $tag ) ] = $tag;
		}
		return array_slice( array_values( $all ), 0, 5 );
	}

	private static function to_hashtag( $word ) {
		$word = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $word ) ) : strtolower( trim( (string) $word ) );
		$word = preg_replace( '/[^\p{L}\p{N}]/u', '', $word );
		return '' === $word ? '' : '#' . $word;
	}

	/** @return string[] */
	public static function hashtags_in( $text ) {
		preg_match_all( '/#[\p{L}\p{N}_]+/u', (string) $text, $m );
		return $m[0];
	}

	public static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/**
	 * Fyller tomma textfält med förslag och sparar utskicket.
	 *
	 * @return array Uppdaterade fält.
	 */
	public static function fill_suggestions( $post_id, array $d, array $channels ) {
		$changed = false;
		if ( in_array( 'facebook', $channels, true ) && '' === trim( $d['fb_text'] ) ) {
			$d['fb_text'] = self::suggest_facebook( $d );
			$changed      = true;
		}
		if ( in_array( 'instagram', $channels, true ) && '' === trim( $d['ig_text'] ) ) {
			$d['ig_text'] = self::suggest_instagram( $d );
			$changed      = true;
		}
		if ( $changed ) {
			update_post_meta( $post_id, CVKP_Mailing::META_DATA, $d );
		}
		return $d;
	}

	/* ------------------------------------------------------------------ */
	/* Utkast                                                               */
	/* ------------------------------------------------------------------ */

	public static function get_state( $post_id, $channel ) {
		$state = get_post_meta( $post_id, 'facebook' === $channel ? self::META_FB : self::META_IG, true );
		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'status'    => 'none', // none, scheduled, publishing, published, failed.
				'draft'     => array(),
				'scheduled' => array(),
				'result'    => array(),
				'error'     => '',
			)
		);
	}

	private static function set_state( $post_id, $channel, array $state ) {
		update_post_meta( $post_id, 'facebook' === $channel ? self::META_FB : self::META_IG, $state );
	}

	/** Länken i Facebook-inlägget: egen länk, annars det publicerade blogginlägget. */
	public static function facebook_link( $post_id, array $d ) {
		if ( '' !== trim( $d['fb_link'] ) ) {
			return $d['fb_link'];
		}
		$blog_id = (int) get_post_meta( $post_id, CVKP_Mailing::META_BLOG, true );
		if ( $blog_id && 'publish' === get_post_status( $blog_id ) ) {
			return get_permalink( $blog_id );
		}
		return '';
	}

	/** @return array Notis. */
	public static function prepare_facebook( $post_id, array $d ) {
		$state          = self::get_state( $post_id, 'facebook' );
		$link           = self::facebook_link( $post_id, $d );
		$state['draft'] = array(
			'message' => trim( $d['fb_text'] ),
			'link'    => $link,
			'updated' => time(),
		);
		self::set_state( $post_id, 'facebook', $state );

		if ( '' === $link ) {
			return array(
				'type'    => 'warning',
				'message' => 'Facebook: texten är klar att granska. Publicera blogginlägget först – Facebook hämtar länkens förhandsvisning när inlägget schemaläggs.',
			);
		}
		return array( 'type' => 'success', 'message' => 'Facebook: utkastet är klart att granska under "Facebook och Instagram". Välj tid och schemalägg när det är godkänt.' );
	}

	/** @return array Notis. */
	public static function prepare_instagram( $post_id, array $d ) {
		$state  = self::get_state( $post_id, 'instagram' );
		$images = array();
		$errors = array();
		foreach ( (array) $d['trips'] as $row ) {
			if ( isset( $row['ig'] ) && '0' === (string) $row['ig'] ) {
				continue;
			}
			$attachment = ! empty( $row['ig_image'] ) ? (int) $row['ig_image'] : (int) get_post_thumbnail_id( (int) $row['id'] );
			if ( ! $attachment ) {
				$errors[] = get_the_title( (int) $row['id'] ) . ' saknar bild';
				continue;
			}
			$image = CVKP_Instagram_Images::prepare( $attachment );
			if ( is_wp_error( $image ) ) {
				$errors[] = $image->get_error_message();
				continue;
			}
			$images[] = array(
				'url'           => $image['url'],
				'attachment_id' => $image['attachment_id'],
				'trip_id'       => (int) $row['id'],
				'width'         => $image['width'],
				'height'        => $image['height'],
			);
		}
		$images = array_slice( $images, 0, 10 );

		$state['draft'] = array(
			'caption' => trim( $d['ig_text'] ),
			'images'  => $images,
			'updated' => time(),
		);
		self::set_state( $post_id, 'instagram', $state );

		$problems = self::instagram_problems( $state['draft'] );
		if ( $errors ) {
			$problems[] = implode( '; ', $errors );
		}
		if ( $problems ) {
			return array( 'type' => 'warning', 'message' => 'Instagram: utkastet är sparat men behöver ses över – ' . implode( ' ', $problems ) );
		}
		return array(
			'type'    => 'success',
			'message' => sprintf( 'Instagram: utkastet med %d %s är klart att granska under "Facebook och Instagram". Välj tid och schemalägg när det är godkänt.', count( $images ), 1 === count( $images ) ? 'bild' : 'bilder' ),
		);
	}

	/** @return string[] Det som hindrar eller bör ändras innan Instagram-inlägget schemaläggs. */
	public static function instagram_problems( array $draft ) {
		$problems = array();
		$caption  = isset( $draft['caption'] ) ? (string) $draft['caption'] : '';
		$count    = isset( $draft['images'] ) ? count( $draft['images'] ) : 0;
		if ( 0 === $count ) {
			$problems[] = 'Ingen bild – lägg till minst en resa med bild.';
		}
		if ( '' === trim( $caption ) ) {
			$problems[] = 'Bildtexten är tom.';
		}
		if ( self::length( $caption ) > self::CAPTION_MAX ) {
			$problems[] = sprintf( 'Bildtexten är %d tecken (högst %d).', self::length( $caption ), self::CAPTION_MAX );
		}
		$tags = count( self::hashtags_in( $caption ) );
		if ( $tags > self::HASHTAGS_MAX ) {
			$problems[] = sprintf( '%d hashtaggar (Instagram tillåter högst %d).', $tags, self::HASHTAGS_MAX );
		}
		return $problems;
	}

	/** Husstil: 3–5 hashtaggar. Bara en påminnelse, hindrar inte schemaläggning. */
	public static function hashtag_hint( $caption ) {
		$tags = count( self::hashtags_in( $caption ) );
		return ( $tags < 3 || $tags > 5 ) ? sprintf( 'Bildtexten har %d hashtaggar – husstilen är 3–5.', $tags ) : '';
	}

	/* ------------------------------------------------------------------ */
	/* Schemaläggning (= godkännande)                                       */
	/* ------------------------------------------------------------------ */

	/** Tolkar fältet datetime-local i sajtens tidszon. */
	public static function parse_time( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		try {
			$date = new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return 0;
		}
		return $date->getTimestamp();
	}

	private static function check_time( $timestamp ) {
		if ( ! $timestamp ) {
			return new WP_Error( 'cvkp_time', 'Välj datum och tid.' );
		}
		if ( $timestamp < time() + self::MIN_LEAD ) {
			return new WP_Error( 'cvkp_time', 'Tiden måste ligga minst 15 minuter fram, så att det går att ångra.' );
		}
		if ( $timestamp > time() + self::MAX_LEAD ) {
			return new WP_Error( 'cvkp_time', 'Det går att schemalägga högst 29 dagar fram.' );
		}
		return true;
	}

	/** Fingeravtryck av innehållet, för att se om något ändrats efter schemaläggningen. */
	public static function fingerprint( $channel, array $draft ) {
		if ( 'facebook' === $channel ) {
			return md5( ( isset( $draft['message'] ) ? $draft['message'] : '' ) . '|' . ( isset( $draft['link'] ) ? $draft['link'] : '' ) );
		}
		$urls = isset( $draft['images'] ) ? wp_list_pluck( $draft['images'], 'url' ) : array();
		return md5( ( isset( $draft['caption'] ) ? $draft['caption'] : '' ) . '|' . implode( ',', $urls ) );
	}

	/**
	 * @return array Notis.
	 */
	public static function schedule( $post_id, $channel, $timestamp ) {
		$label = 'facebook' === $channel ? 'Facebook' : 'Instagram';
		$state = self::get_state( $post_id, $channel );
		if ( in_array( $state['status'], array( 'scheduled', 'publishing' ), true ) ) {
			return array( 'type' => 'error', 'message' => $label . ': inlägget är redan schemalagt. Avbryt först om du vill ändra.' );
		}
		$ok = self::check_time( $timestamp );
		if ( is_wp_error( $ok ) ) {
			return array( 'type' => 'error', 'message' => $label . ': ' . $ok->get_error_message() );
		}
		$meta  = new CVKP_Meta();
		$draft = $state['draft'];
		$when  = wp_date( 'j F Y \k\l. H:i', $timestamp );

		if ( 'facebook' === $channel ) {
			if ( ! $meta->facebook_ready() ) {
				return array( 'type' => 'error', 'message' => 'Facebook: anslutningen till Meta är inte inställd (Cykla&Vandra → Kanalpublicering).' );
			}
			if ( empty( $draft['link'] ) ) {
				return array( 'type' => 'error', 'message' => 'Facebook: publicera blogginlägget först (eller fyll i en egen länk).' );
			}
			if ( '' === trim( (string) $draft['message'] ) ) {
				return array( 'type' => 'error', 'message' => 'Facebook: texten är tom.' );
			}
			$fb_id = $meta->facebook_schedule( $draft['message'], $draft['link'], $timestamp );
			if ( is_wp_error( $fb_id ) ) {
				return array( 'type' => 'error', 'message' => 'Facebook: ' . $fb_id->get_error_message() );
			}
			$state['scheduled'] = $draft + array(
				'at'          => (int) $timestamp,
				'by'          => get_current_user_id(),
				'id'          => $fb_id,
				'fingerprint' => self::fingerprint( 'facebook', $draft ),
			);
		} else {
			if ( ! $meta->instagram_ready() ) {
				return array( 'type' => 'error', 'message' => 'Instagram: anslutningen till Meta är inte inställd (Cykla&Vandra → Kanalpublicering). Bilderna och texten går att lägga upp för hand.' );
			}
			$problems = self::instagram_problems( $draft );
			if ( $problems ) {
				return array( 'type' => 'error', 'message' => 'Instagram: ' . implode( ' ', $problems ) );
			}
			wp_clear_scheduled_hook( self::CRON, array( (int) $post_id ) );
			$event = wp_schedule_single_event( (int) $timestamp, self::CRON, array( (int) $post_id ), true );
			if ( is_wp_error( $event ) || false === $event ) {
				return array( 'type' => 'error', 'message' => 'Instagram: kunde inte schemalägga i WordPress' . ( is_wp_error( $event ) ? ' (' . $event->get_error_message() . ')' : '' ) . '.' );
			}
			$state['scheduled'] = $draft + array(
				'at'          => (int) $timestamp,
				'by'          => get_current_user_id(),
				'fingerprint' => self::fingerprint( 'instagram', $draft ),
			);
		}

		$state['status'] = 'scheduled';
		$state['error']  = '';
		$state['result'] = array();
		self::set_state( $post_id, $channel, $state );

		return array(
			'type'    => 'success',
			'message' => 'facebook' === $channel
				? sprintf( 'Facebook: inlägget är schemalagt till %s. Det syns i Meta Business Suite och kan ändras eller tas bort där fram till dess.', $when )
				: sprintf( 'Instagram: inlägget publiceras %s. Fram till dess kan du avbryta här.', $when ),
		);
	}

	/** @return array Notis. */
	public static function cancel( $post_id, $channel ) {
		$label = 'facebook' === $channel ? 'Facebook' : 'Instagram';
		$state = self::get_state( $post_id, $channel );
		if ( 'scheduled' !== $state['status'] ) {
			return array( 'type' => 'warning', 'message' => $label . ': det finns inget schemalagt inlägg att avbryta.' );
		}
		if ( 'facebook' === $channel && ! empty( $state['scheduled']['id'] ) ) {
			$deleted = ( new CVKP_Meta() )->facebook_delete( $state['scheduled']['id'] );
			if ( is_wp_error( $deleted ) && 'cvkp_meta_404' !== $deleted->get_error_code() ) {
				return array( 'type' => 'error', 'message' => 'Facebook: kunde inte ta bort det schemalagda inlägget (' . $deleted->get_error_message() . '). Ta bort det i Meta Business Suite.' );
			}
		}
		if ( 'instagram' === $channel ) {
			wp_clear_scheduled_hook( self::CRON, array( (int) $post_id ) );
		}
		$state['status']    = 'none';
		$state['scheduled'] = array();
		self::set_state( $post_id, $channel, $state );
		return array( 'type' => 'success', 'message' => $label . ': schemaläggningen är avbruten. Inget publicerades.' );
	}

	/** WP-Cron: publicerar ett godkänt Instagram-inlägg. */
	public static function publish_instagram( $post_id ) {
		$post_id = (int) $post_id;
		$state   = self::get_state( $post_id, 'instagram' );
		if ( 'scheduled' !== $state['status'] || empty( $state['scheduled'] ) ) {
			return;
		}
		$lock = 'cvkp_ig_lock_' . $post_id;
		if ( get_transient( $lock ) ) {
			return;
		}
		set_transient( $lock, 1, 10 * MINUTE_IN_SECONDS );

		$scheduled = $state['scheduled'];
		$post      = get_post( $post_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			$state['status'] = 'failed';
			$state['error']  = 'Utskicket är borttaget – inlägget publicerades inte.';
		} elseif ( time() - (int) $scheduled['at'] > self::LATE_LIMIT ) {
			$state['status'] = 'failed';
			$state['error']  = 'Publiceringen blev mer än sex timmar försenad (WP-Cron har inte körts) och stoppades. Schemalägg igen.';
		} else {
			$state['status'] = 'publishing';
			self::set_state( $post_id, 'instagram', $state );

			$result = ( new CVKP_Meta() )->instagram_publish( $scheduled['caption'], wp_list_pluck( $scheduled['images'], 'url' ) );
			if ( is_wp_error( $result ) ) {
				$state['status'] = 'failed';
				$state['error']  = $result->get_error_message();
			} else {
				$state['status'] = 'published';
				$state['error']  = '';
				$state['result'] = $result + array( 'time' => time() );
			}
		}
		self::set_state( $post_id, 'instagram', $state );
		delete_transient( $lock );
		self::notify( $post_id, $state );
	}

	/** Mejlar den som schemalade om hur det gick. */
	private static function notify( $post_id, array $state ) {
		$user = get_userdata( isset( $state['scheduled']['by'] ) ? (int) $state['scheduled']['by'] : 0 );
		$to   = $user ? $user->user_email : get_option( 'admin_email' );
		$name = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
		$edit = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
		if ( 'published' === $state['status'] ) {
			$subject = 'Instagram: "' . $name . '" är publicerat';
			$body    = "Instagram-inlägget från utskicket \"{$name}\" är publicerat.\n\n" . ( ! empty( $state['result']['permalink'] ) ? $state['result']['permalink'] . "\n\n" : '' ) . "Utskicket: {$edit}\n";
		} else {
			$subject = 'Instagram: "' . $name . '" kunde inte publiceras';
			$body    = "Instagram-inlägget från utskicket \"{$name}\" publicerades inte.\n\nOrsak: {$state['error']}\n\nÖppna utskicket och schemalägg igen: {$edit}\n";
		}
		wp_mail( $to, $subject, $body );
	}

	/** Kollar om ett schemalagt Facebook-inlägg har publicerats (högst en gång var tionde minut). */
	public static function refresh_facebook( $post_id ) {
		$state = self::get_state( $post_id, 'facebook' );
		if ( 'scheduled' !== $state['status'] || empty( $state['scheduled']['id'] ) || (int) $state['scheduled']['at'] > time() ) {
			return $state;
		}
		$key = 'cvkp_fb_check_' . $post_id;
		if ( get_transient( $key ) ) {
			return $state;
		}
		set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
		$meta = new CVKP_Meta();
		if ( ! $meta->facebook_ready() ) {
			return $state;
		}
		$post = $meta->facebook_get( $state['scheduled']['id'] );
		if ( is_wp_error( $post ) ) {
			if ( 'cvkp_meta_404' === $post->get_error_code() || 'cvkp_meta_400' === $post->get_error_code() ) {
				$state['status'] = 'failed';
				$state['error']  = 'Inlägget finns inte längre hos Meta (borttaget i Business Suite?).';
				self::set_state( $post_id, 'facebook', $state );
			}
			return $state;
		}
		if ( ! empty( $post['is_published'] ) ) {
			$state['status'] = 'published';
			$state['result'] = array(
				'id'        => $state['scheduled']['id'],
				'permalink' => isset( $post['permalink_url'] ) ? $post['permalink_url'] : '',
				'time'      => (int) $state['scheduled']['at'],
			);
			self::set_state( $post_id, 'facebook', $state );
		}
		return $state;
	}

	/** Vid inaktivering: inga Instagram-inlägg får publiceras senare av gamla händelser. */
	public static function deactivate() {
		$posts = get_posts(
			array(
				'post_type'      => CVKP_Mailing::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_IG, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $posts as $post_id ) {
			$state = self::get_state( $post_id, 'instagram' );
			if ( 'scheduled' === $state['status'] ) {
				$state['status'] = 'failed';
				$state['error']  = 'Tillägget inaktiverades innan publiceringen. Schemalägg igen.';
				self::set_state( $post_id, 'instagram', $state );
			}
		}
		wp_unschedule_hook( self::CRON );
	}

	/* ------------------------------------------------------------------ */
	/* Redigeringsvyn                                                       */
	/* ------------------------------------------------------------------ */

	/** Standardtid i schemaläggningsfältet: nästa hela kvart, minst en timme fram. */
	private static function default_time() {
		$t = time() + HOUR_IN_SECONDS;
		$t = (int) ( ceil( $t / 900 ) * 900 );
		return wp_date( 'Y-m-d\TH:i', $t );
	}

	public static function box( $post ) {
		$d    = CVKP_Mailing::get_data( $post->ID );
		$meta = new CVKP_Meta();

		echo '<p class="description">Texterna fylls i med förslag när du klickar på <strong>Spara och skapa utkast</strong> med Facebook eller Instagram valt. Skriv om dem fritt. Töm ett fält och skapa utkast igen för att få ett nytt förslag. Ingenting publiceras förrän någon klickar på <strong>Schemalägg</strong>.</p>';
		if ( ! $meta->has_token() ) {
			echo '<p class="notice notice-info inline" style="padding:8px 12px">Anslutningen till Meta är inte inställd ännu. Utkasten fungerar ändå: kopiera texten och ladda ner bilderna för att lägga upp dem för hand.</p>';
		}

		echo '<div class="cvkp-social-cols">';

		// Facebook.
		$fb   = self::refresh_facebook( $post->ID );
		$link = self::facebook_link( $post->ID, $d );
		echo '<div class="cvkp-social-col"><h3>Facebook</h3>';
		echo '<p class="cvkp-field"><label for="cvkp-fb_text"><strong>Text</strong></label><textarea class="widefat" rows="9" id="cvkp-fb_text" name="cvkp[fb_text]">' . esc_textarea( $d['fb_text'] ) . '</textarea><span class="description">Inlägget visar länkens förhandsvisning (bild och rubrik från blogginlägget).</span></p>';
		printf(
			'<p class="cvkp-field"><label for="cvkp-fb_link"><strong>Länk</strong></label><input type="url" class="widefat" id="cvkp-fb_link" name="cvkp[fb_link]" value="%s" placeholder="%s"><span class="description">%s</span></p>',
			esc_attr( $d['fb_link'] ),
			esc_attr( $link ? $link : 'Blogginläggets adress när det är publicerat' ),
			esc_html( $link ? 'Tomt = blogginlägget.' : 'Blogginlägget är inte publicerat ännu – Facebook kan inte schemaläggas förrän det är det.' )
		);
		self::status_html( $post->ID, 'facebook', $fb );
		echo '</div>';

		// Instagram.
		$ig = self::get_state( $post->ID, 'instagram' );
		echo '<div class="cvkp-social-col"><h3>Instagram</h3>';
		echo '<p class="cvkp-field"><label for="cvkp-ig_signer"><strong>Hälsning från</strong></label><select id="cvkp-ig_signer" name="cvkp[ig_signer]"><option value="">— Hela teamet —</option>';
		foreach ( CVKP_Settings::team() as $member ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $member ), selected( $d['ig_signer'], $member, false ) );
		}
		echo '</select><span class="description">Används i förslaget. Personligt fungerar bäst på Instagram.</span></p>';
		echo '<p class="cvkp-field"><label for="cvkp-ig_text"><strong>Bildtext</strong></label><textarea class="widefat cvkp-ig-text" rows="12" id="cvkp-ig_text" name="cvkp[ig_text]">' . esc_textarea( $d['ig_text'] ) . '</textarea>';
		echo '<span class="description cvkp-ig-count" data-max="' . (int) self::CAPTION_MAX . '"></span> <button type="button" class="button-link cvkp-copy" data-target="cvkp-ig_text">Kopiera text</button></p>';

		$draft = $ig['draft'];
		if ( ! empty( $draft['images'] ) ) {
			echo '<p><strong>Bilder i karusellen</strong> <span class="description">(' . esc_html( CVKP_Instagram_Images::ratio_key() ) . ', i resornas ordning – välj bild per resa under Resor)</span></p><div class="cvkp-ig-preview">';
			foreach ( $draft['images'] as $i => $image ) {
				printf( '<a href="%1$s" target="_blank" title="Öppna/ladda ner"><img src="%1$s" alt=""><span>%2$d</span></a>', esc_url( $image['url'] ), (int) $i + 1 );
			}
			echo '</div>';
			$hint = self::hashtag_hint( isset( $draft['caption'] ) ? $draft['caption'] : '' );
			if ( $hint ) {
				echo '<p class="description cvkp-warn">' . esc_html( $hint ) . '</p>';
			}
		} else {
			echo '<p class="description">Inga bilder ännu – de skapas när du klickar på Spara och skapa utkast med Instagram valt.</p>';
		}
		self::status_html( $post->ID, 'instagram', $ig );
		echo '</div></div>';
	}

	private static function status_html( $post_id, $channel, array $state ) {
		$label = 'facebook' === $channel ? 'Facebook' : 'Instagram';
		echo '<div class="cvkp-schedule">';
		switch ( $state['status'] ) {
			case 'scheduled':
				printf( '<p><span class="cvkp-badge cvkp-badge-scheduled">Schemalagt</span> %s</p>', esc_html( wp_date( 'j F Y \k\l. H:i', (int) $state['scheduled']['at'] ) ) );
				if ( ! empty( $state['draft'] ) && self::fingerprint( $channel, $state['draft'] ) !== $state['scheduled']['fingerprint'] ) {
					echo '<p class="description cvkp-warn">Utkastet har ändrats efter schemaläggningen. Ändringarna kommer inte med – avbryt och schemalägg igen.</p>';
				}
				if ( 'facebook' === $channel ) {
					echo '<p class="description">Syns i Meta Business Suite under Planerare.</p>';
				}
				printf(
					'<p><button type="submit" name="cvkp_cancel" value="%s" class="button cvkp-confirm" data-confirm="%s">Avbryt schemaläggningen</button></p>',
					esc_attr( $channel ),
					esc_attr( 'Avbryta det schemalagda ' . $label . '-inlägget?' )
				);
				echo '</div>';
				return;
			case 'publishing':
				echo '<p><span class="cvkp-badge">Publiceras just nu …</span></p></div>';
				return;
			case 'published':
				printf(
					'<p><span class="cvkp-badge cvkp-badge-published">Publicerat</span> %s %s</p></div>',
					esc_html( wp_date( 'j F Y H:i', (int) ( isset( $state['result']['time'] ) ? $state['result']['time'] : 0 ) ) ),
					! empty( $state['result']['permalink'] ) ? '<a href="' . esc_url( $state['result']['permalink'] ) . '" target="_blank">Visa ↗</a>' : ''
				);
				echo '<p class="description" style="margin-top:-6px">Skapa ett nytt utskick för nästa inlägg.</p>';
				return;
			case 'failed':
				printf( '<p><span class="cvkp-badge cvkp-badge-failed">Inte publicerat</span> %s</p>', esc_html( $state['error'] ) );
				break;
		}

		if ( empty( $state['draft'] ) ) {
			echo '<p class="description">Inget utkast ännu.</p></div>';
			return;
		}
		$ready = 'facebook' === $channel
			? '' !== self::facebook_link( $post_id, CVKP_Mailing::get_data( $post_id ) )
			: ! self::instagram_problems( $state['draft'] );
		$id    = 'cvkp-when-' . $channel;
		printf(
			'<p><label for="%1$s"><strong>Publicera</strong></label><br><input type="datetime-local" id="%1$s" name="cvkp_when[%2$s]" value="%3$s"> <button type="submit" name="cvkp_schedule" value="%2$s" class="button button-primary cvkp-confirm" data-confirm="%4$s" %5$s>Schemalägg %6$s</button></p>',
			esc_attr( $id ),
			esc_attr( $channel ),
			esc_attr( self::default_time() ),
			esc_attr( 'Jag har granskat texten och bilderna. ' . $label . '-inlägget publiceras automatiskt vid vald tid. Fortsätta?' ),
			disabled( $ready, false, false ),
			esc_html( $label )
		);
		echo '<p class="description">Knappen sparar först, så det som står i fälten när du klickar är det som schemaläggs. Bilderna väljs under Resor.</p>';
		echo '</div>';
	}
}
