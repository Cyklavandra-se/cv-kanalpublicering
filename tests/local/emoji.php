<?php
// Emoji i utskicket på en databas med utf8 (3 byte), som äldre sajter har.
// Utan kodningen sparades inget alls (0.5.3); nu ska allt sparas och läsas tillbaka.
$_SERVER['HTTP_HOST'] = 'localhost:8899'; $_SERVER['REQUEST_URI'] = '/';
require getenv( 'W' ) . '/wp-load.php';
add_filter( 'pre_get_col_charset', function () { return 'utf8'; } );
add_filter( 'pre_get_table_charset', function () { return 'utf8'; } );
wp_set_current_user( get_user_by( 'login', 'sarah' )->ID );

$id   = wp_insert_post( array( 'post_type' => 'cvkp_utskick', 'post_title' => 'Emoji-test', 'post_status' => 'draft' ) );
$cvkp = array( 'subject' => 'Rulla söderut 🚲', 'heading' => 'Rulla söderut – fyra resor', 'ig_text' => "Hej från Sarah! 👋\n🥾 Madeira", 'fb_text' => 'Kod: VINTER ☀️', 'code' => 'VINTER', 'channels' => array( 'blog', 'facebook', 'instagram' ) );
$_POST = array( 'cvkp_nonce' => wp_create_nonce( 'cvkp_save' ), 'cvkp_run' => 1, 'cvkp' => wp_slash( $cvkp ) );
wp_update_post( array( 'ID' => $id, 'post_title' => 'Emoji-test' ) );
$_POST = array();

$d = CVKP_Mailing::get_data( $id );
echo 'ämnesrad: ' . $d['subject'] . ' | ' . ( $cvkp['subject'] === $d['subject'] ? 'OK' : 'FEL' ) . "\n";
echo 'instagram: ' . str_replace( "\n", ' / ', $d['ig_text'] ) . ' | ' . ( $cvkp['ig_text'] === $d['ig_text'] ? 'OK' : 'FEL' ) . "\n";
echo 'facebook (☀ är 3 byte, ska vara orört): ' . ( $cvkp['fb_text'] === $d['fb_text'] ? 'OK' : 'FEL' ) . "\n";
$ig = CVKP_Social::get_state( $id, 'instagram' );
echo 'IG-utkast: ' . ( isset( $ig['draft']['caption'] ) && $cvkp['ig_text'] === $ig['draft']['caption'] ? 'OK' : 'FEL' ) . "\n";
echo 'i databasen: ' . substr( get_post_meta( $id, CVKP_Mailing::META_DATA, true )['subject'], 0, 40 ) . "\n";
$n = get_transient( 'cvkp_notices_' . get_current_user_id() );
foreach ( (array) $n as $x ) { echo "  [{$x['type']}] " . mb_substr( $x['message'], 0, 70 ) . "\n"; }
delete_transient( 'cvkp_notices_' . get_current_user_id() );

// Om databasen ändå vägrar ska det synas, inte tömmas tyst.
add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key ) { return CVKP_Mailing::META_DATA === $meta_key ? false : $check; }, 10, 3 );
$id2  = wp_insert_post( array( 'post_type' => 'cvkp_utskick', 'post_title' => 'Fel-test', 'post_status' => 'draft' ) );
$_POST = array( 'cvkp_nonce' => wp_create_nonce( 'cvkp_save' ), 'cvkp' => wp_slash( array( 'subject' => 'Ämne', 'heading' => 'Rubrik' ) ) );
wp_update_post( array( 'ID' => $id2, 'post_title' => 'Fel-test' ) );
$_POST = array();
$n = get_transient( 'cvkp_notices_' . get_current_user_id() );
echo 'när sparningen misslyckas: ' . ( $n && 'error' === $n[0]['type'] ? 'FELMEDDELANDE: ' . mb_substr( $n[0]['message'], 0, 60 ) : 'INGET MEDDELANDE' ) . "\n";
