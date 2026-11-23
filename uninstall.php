<?php
/**
 * Tar bort inställningarna när tillägget avinstalleras.
 * Utskick, blogginlägg och Resefakta på resesidorna ligger kvar.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cvkp_settings' );
