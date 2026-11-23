<?php
/**
 * WPML-hjälp. Sajten har svenska (standard) och norska. Utskick, resor och blogginlägg
 * ska alltid hanteras på standardspråket, oavsett vilket språk som är valt i adminfältet.
 * Allt här gör ingenting om WPML inte är aktivt.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Wpml {

	/** @var string|null Språket som var valt innan switch_to_default(). */
	private static $previous = null;

	public static function active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || has_filter( 'wpml_default_language' );
	}

	/** Standardspråket, t.ex. "sv". Tom sträng utan WPML. */
	public static function default_language() {
		if ( ! self::active() ) {
			return '';
		}
		return (string) apply_filters( 'wpml_default_language', null );
	}

	/** Byter tillfälligt till standardspråket (t.ex. om adminfältet står på "Alla språk" eller norska). */
	public static function switch_to_default() {
		$default = self::default_language();
		if ( '' === $default ) {
			return;
		}
		self::$previous = (string) apply_filters( 'wpml_current_language', null );
		if ( self::$previous !== $default ) {
			do_action( 'wpml_switch_language', $default );
		}
	}

	public static function restore() {
		if ( null === self::$previous ) {
			return;
		}
		$default = self::default_language();
		if ( self::$previous !== $default ) {
			// WPML använder "all" för "Alla språk".
			do_action( 'wpml_switch_language', '' === self::$previous ? 'all' : self::$previous );
		}
		self::$previous = null;
	}

	/** Sätter ett nytt inlägg till standardspråket. */
	public static function set_default_language( $post_id, $post_type = 'post' ) {
		$default = self::default_language();
		if ( '' === $default ) {
			return;
		}
		$details = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => $post_id,
				'element_type' => $post_type,
			)
		);
		if ( is_object( $details ) && ! empty( $details->language_code ) && $details->language_code === $default ) {
			return;
		}
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $post_id,
				'element_type'         => 'post_' . $post_type,
				'trid'                 => is_object( $details ) && ! empty( $details->trid ) ? $details->trid : false,
				'language_code'        => $default,
				'source_language_code' => null,
			)
		);
	}
}
