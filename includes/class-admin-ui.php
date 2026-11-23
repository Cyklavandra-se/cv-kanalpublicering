<?php
/**
 * Gemensamt utseende för tilläggets sidor: flikarna Utskick | Inställningar
 * och versionsnumret längst ner.
 *
 * Menyn Cykla&Vandra har bara en rad, "Kanalpublicering", som öppnar listan
 * med utskick. Inställningarna nås via fliken (sidan finns kvar men visas inte
 * i menyn).
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Admin_Ui {

	public static function init() {
		add_action( 'all_admin_notices', array( __CLASS__, 'list_tabs' ), 1 );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ), 20 );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
	}

	public static function list_url() {
		return admin_url( 'edit.php?post_type=' . CVKP_Mailing::POST_TYPE );
	}

	/** Är vi på någon av tilläggets sidor? */
	private static function screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return '';
		}
		if ( 'edit-' . CVKP_Mailing::POST_TYPE === $screen->id ) {
			return 'list';
		}
		if ( CVKP_Mailing::POST_TYPE === $screen->id ) {
			return 'edit';
		}
		if ( false !== strpos( $screen->id, CVKP_Settings::PAGE ) ) {
			return 'settings';
		}
		return '';
	}

	/**
	 * Flikarna. Bara administratörer ser fliken Inställningar; för andra
	 * visas inga flikar alls (en ensam flik säger ingenting).
	 */
	public static function tabs( $active ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = array(
			'list'     => array( 'Utskick', self::list_url() ),
			'settings' => array( 'Inställningar', CVKP_Settings::page_url() ),
		);
		echo '<nav class="nav-tab-wrapper cvkp-tabs" aria-label="Kanalpublicering">';
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<a href="%s" class="nav-tab%s"%s>%s</a>',
				esc_url( $tab[1] ),
				$key === $active ? ' nav-tab-active' : '',
				$key === $active ? ' aria-current="page"' : '',
				esc_html( $tab[0] )
			);
		}
		echo '</nav>';
	}

	/** Flikarna överst i listan med utskick (före WordPress rubrik). */
	public static function list_tabs() {
		if ( 'list' !== self::screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap cvkp-tabs-wrap" style="margin-bottom:0">';
		self::tabs( 'list' );
		echo '</div>';
	}

	public static function footer_text( $text ) {
		if ( '' === self::screen() ) {
			return $text;
		}
		return sprintf(
			'Cykla &amp; Vandra – Kanalpublicering, version %s',
			esc_html( CVKP_VERSION )
		);
	}

	/** Håll menyn Cykla&Vandra → Kanalpublicering markerad på inställningssidan. */
	public static function parent_file( $parent_file ) {
		return 'settings' === self::screen() ? Cyklavandra_Meny::SLUG : $parent_file;
	}

	public static function submenu_file( $submenu_file ) {
		return 'settings' === self::screen() ? 'edit.php?post_type=' . CVKP_Mailing::POST_TYPE : $submenu_file;
	}
}
