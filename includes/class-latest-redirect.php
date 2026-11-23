<?php
/**
 * Fast adress (standard /aktuellt) som alltid leder till det senast publicerade
 * blogginlägget från ett utskick. Läggs i Linktree en gång för alla.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Latest_Redirect {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	public static function maybe_redirect() {
		$slug = (string) CVKP_Settings::get( 'latest_slug' );
		if ( '' === $slug || ! is_404() ) {
			return; // En riktig sida med samma adress vinner alltid.
		}

		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		if ( $path !== $slug ) {
			return;
		}

		$url = self::latest_url();
		nocache_headers(); // Får aldrig cachas – målet byts när nästa inlägg publiceras.
		wp_safe_redirect( $url ? $url : home_url( '/' ), 302 );
		exit;
	}

	/** Senaste publicerade inlägg som skapats från ett utskick; annars senaste inlägget. */
	public static function latest_url() {
		$base  = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'suppress_filters' => false, // WPML: bara inlägg på standardspråket.
		);
		CVKP_Wpml::switch_to_default();
		$posts = get_posts( array_merge( $base, array( 'meta_key' => CVKP_Mailing::META_SOURCE ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $posts ) {
			$posts = get_posts( $base );
		}
		CVKP_Wpml::restore();
		return $posts ? get_permalink( $posts[0] ) : '';
	}
}
