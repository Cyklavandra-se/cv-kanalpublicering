<?php
/**
 * Plugin Name:       Cykla & Vandra – Kanalpublicering
 * Plugin URI:        https://github.com/Cyklavandra-se/cv-kanalpublicering
 * Description:       Skriv ett utskick en gång och skapa utkast för nyhetsbrev (Mailchimp), blogginlägg, Facebook och Instagram. Inget publiceras utan godkännande.
 * Version:           0.5.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Cykla & Vandra
 * License:           GPL-2.0-or-later
 * Text Domain:       cv-kanalpublicering
 * Update URI:        https://github.com/cyklavandra-se/cv-kanalpublicering
 */

defined( 'ABSPATH' ) || exit;

define( 'CVKP_VERSION', '0.5.3' );
define( 'CVKP_FILE', __FILE__ );
define( 'CVKP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CVKP_URL', plugin_dir_url( __FILE__ ) );

require_once CVKP_DIR . 'cyklavandra-meny.php'; // Gemensam meny Cykla&Vandra, samma fil i alla våra tillägg.
require_once CVKP_DIR . 'updater.php'; // Uppdateringar via GitHub-releaser, som Bokningsformulär.
CVKP_Updater::init( __FILE__ );
require_once CVKP_DIR . 'includes/class-settings.php';
require_once CVKP_DIR . 'includes/class-wpml.php';
require_once CVKP_DIR . 'includes/class-trip-data.php';
require_once CVKP_DIR . 'includes/class-trip-meta-box.php';
require_once CVKP_DIR . 'includes/class-email-renderer.php';
require_once CVKP_DIR . 'includes/class-blog-renderer.php';
require_once CVKP_DIR . 'includes/class-mailchimp.php';
require_once CVKP_DIR . 'includes/class-meta.php';
require_once CVKP_DIR . 'includes/class-instagram-images.php';
require_once CVKP_DIR . 'includes/class-social.php';
require_once CVKP_DIR . 'includes/class-mailing.php';
require_once CVKP_DIR . 'includes/class-latest-redirect.php';
require_once CVKP_DIR . 'includes/class-admin-ui.php';
require_once CVKP_DIR . 'includes/class-claude.php';

add_action(
	'plugins_loaded',
	static function () {
		CVKP_Settings::init();
		CVKP_Trip_Meta_Box::init();
		CVKP_Mailing::init();
		CVKP_Latest_Redirect::init();
		CVKP_Social::init();
		CVKP_Admin_Ui::init();
		CVKP_Claude::init();
	}
);

register_deactivation_hook( __FILE__, array( 'CVKP_Social', 'deactivate' ) );
