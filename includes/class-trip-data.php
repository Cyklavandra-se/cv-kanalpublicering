<?php
/**
 * Hämtar fakta om en resa (en WordPress-sida) för utskick:
 * titel, länk, bild, typ, nivå, dagar, nätter, land och beskrivning.
 *
 * Ordning: först fälten i Resefakta, sedan taggar, sist tolkning av sidtexten.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Trip_Data {

	const META_DAYS    = '_cvkp_days';
	const META_NIGHTS  = '_cvkp_nights';
	const META_COUNTRY = '_cvkp_country';
	const META_LABEL   = '_cvkp_label';

	/** Taggar som markerar att en sida är en resa. */
	const TRIP_TAGS = array( 'cykla', 'vandra' );

	/**
	 * Alla publicerade resesidor, sorterade efter titel.
	 *
	 * @return WP_Post[]
	 */
	public static function all_trips() {
		$tag_ids = array();
		foreach ( self::TRIP_TAGS as $slug ) {
			$term = get_term_by( 'slug', $slug, 'post_tag' );
			if ( $term ) {
				$tag_ids[] = (int) $term->term_id;
			}
		}

		$args = array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 500,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		);
		if ( $tag_ids ) {
			$args['tag__in'] = $tag_ids;
		}
		CVKP_Wpml::switch_to_default();
		$trips = get_posts( $args );
		CVKP_Wpml::restore();
		return $trips;
	}

	public static function is_trip( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'page' !== $post->post_type ) {
			return false;
		}
		return (bool) array_intersect( self::TRIP_TAGS, self::tag_slugs( $post->ID ) );
	}

	public static function tag_slugs( $post_id ) {
		$terms = get_the_terms( $post_id, 'post_tag' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return wp_list_pluck( $terms, 'slug' );
	}

	/**
	 * Allt som behövs för ett utskick.
	 *
	 * @return array|null
	 */
	public static function get( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$tags   = self::tag_slugs( $post->ID );
		$parsed = self::parse_content( $post->post_content );

		$days    = (int) get_post_meta( $post->ID, self::META_DAYS, true );
		$nights  = (int) get_post_meta( $post->ID, self::META_NIGHTS, true );
		$country = trim( (string) get_post_meta( $post->ID, self::META_COUNTRY, true ) );

		$data = array(
			'id'          => $post->ID,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'url'         => get_permalink( $post ),
			'image_id'    => (int) get_post_thumbnail_id( $post ),
			'image_url'   => (string) get_the_post_thumbnail_url( $post, 'large' ),
			'image_full'  => self::email_image_url( (int) get_post_thumbnail_id( $post ) ),
			'type'        => self::type_from_tags( $tags ),
			'level'       => self::level_from_tags( $tags ),
			'self_guided' => in_array( 'sjalvguidad', $tags, true ),
			'days'        => $days ? $days : $parsed['days'],
			'nights'      => $nights ? $nights : $parsed['nights'],
			'country'     => $country,
			'description' => self::description( $post ),
		);

		$custom        = trim( (string) get_post_meta( $post->ID, self::META_LABEL, true ) );
		$data['label'] = '' !== $custom ? $custom : self::default_label( $data );

		return $data;
	}

	/** Bild för mejlet: originalet om det är rimligt stort, annars storleken "large". */
	public static function email_image_url( $attachment_id ) {
		if ( ! $attachment_id ) {
			return '';
		}
		$full = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( $full && (int) $full[1] <= 1600 ) {
			return (string) $full[0];
		}
		$large = wp_get_attachment_image_src( $attachment_id, 'large' );
		return $large ? (string) $large[0] : ( $full ? (string) $full[0] : '' );
	}

	public static function type_from_tags( array $tags ) {
		if ( in_array( 'cykla', $tags, true ) ) {
			return 'Cykling';
		}
		if ( in_array( 'vandra', $tags, true ) ) {
			return 'Vandring';
		}
		return '';
	}

	/** Nivå från taggarna bike1–bike4 och hike1–hike4. */
	public static function level_from_tags( array $tags ) {
		foreach ( $tags as $slug ) {
			if ( preg_match( '/^(?:bike|hike)([1-4])$/', $slug, $m ) ) {
				return (int) $m[1];
			}
		}
		return 0;
	}

	/** "Cykling · 7 dagar · Ungern" – delar som saknas hoppas över. */
	public static function default_label( array $data ) {
		$parts = array_filter(
			array(
				$data['type'],
				$data['days'] ? $data['days'] . ' dagar' : '',
				$data['country'],
			)
		);
		return implode( ' · ', $parts );
	}

	/**
	 * Försöker läsa antal dagar och nätter ur sidtexten, t.ex. "7 dagar och 6 nätter"
	 * eller "7 dagar / 6 nätter". Används som förslag när Resefakta är tomt.
	 */
	public static function parse_content( $content ) {
		$text = self::plain_text( $content );

		$days   = 0;
		$nights = 0;
		if ( preg_match( '/(\d{1,2})\s*dagar\s*(?:och|\/|,|–|-)\s*(\d{1,2})\s*nätter/iu', $text, $m ) ) {
			$days   = (int) $m[1];
			$nights = (int) $m[2];
		} elseif ( preg_match( '/(\d{1,2})\s*dagar/iu', $text, $m ) ) {
			$days = (int) $m[1];
		}
		if ( ! $nights && preg_match( '/(\d{1,2})\s*nätter/iu', $text, $m ) ) {
			$nights = (int) $m[1];
		}
		return array(
			'days'   => $days,
			'nights' => $nights,
		);
	}

	/** Startförslag till resans stycke: utdrag, SEO-beskrivning eller första stycket. */
	public static function description( $post ) {
		$post = get_post( $post );
		if ( has_excerpt( $post ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		$seo = self::aioseo_description( $post->ID );
		if ( '' !== $seo ) {
			return $seo;
		}
		return wp_trim_words( self::plain_text( $post->post_content ), 60, ' …' );
	}

	/** Sidtext utan HTML och kortkoder (Avada/Fusion), med mellanslag mellan stycken. */
	public static function plain_text( $content ) {
		$text = strip_shortcodes( (string) $content );
		$text = preg_replace( '/\[\/?[a-z_]+[^\]]*\]/i', ' ', $text ); // Kortkoder från avaktiverade byggare.
		$text = preg_replace( '/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $text );
		$text = preg_replace( '/<[^>]+>/', ' ', $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** AIOSEO sparar beskrivningen i en egen tabell. Smarta taggar (#…) hoppas över. */
	private static function aioseo_description( $post_id ) {
		if ( ! function_exists( 'aioseo' ) ) {
			return '';
		}
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $found !== $table ) {
			return '';
		}
		$desc = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM {$table} WHERE post_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$desc = is_string( $desc ) ? trim( $desc ) : '';
		return ( '' === $desc || false !== strpos( $desc, '#' ) ) ? '' : $desc;
	}
}
