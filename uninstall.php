<?php
/**
 * Tar bort inställningarna när tillägget avinstalleras.
 * Utskick, blogginlägg, Resefakta på resesidorna och Instagram-bilderna i
 * uploads/cvkp-instagram ligger kvar.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cvkp_settings' );
wp_unschedule_hook( 'cvkp_instagram_publish' );
