<?php
// Blogginlägget: bilderna i originalstorlek (1200 px) och Avadas utvalda bild dold överst.
$_SERVER['HTTP_HOST'] = 'localhost:8899'; $_SERVER['REQUEST_URI'] = '/';
require getenv( 'W' ) . '/wp-load.php';
wp_set_current_user( get_user_by( 'login', 'sarah' )->ID );
$trip = get_page_by_title( 'Mosel – cykla en vecka', OBJECT, 'page' );
$id   = wp_insert_post( array( 'post_type' => 'cvkp_utskick', 'post_title' => 'Bloggtest', 'post_status' => 'draft' ) );
$_POST = array( 'cvkp_nonce' => wp_create_nonce( 'cvkp_save' ), 'cvkp_run' => 1, 'cvkp' => wp_slash( array( 'heading' => 'Bloggtest', 'channels' => array( 'blog' ), 'trips' => array( 'a' => array( 'id' => $trip->ID, 'text' => 'Text om resan.' ) ) ) ) );
wp_update_post( array( 'ID' => $id, 'post_title' => 'Bloggtest' ) );
$_POST = array();
$blog = (int) get_post_meta( $id, '_cvkp_blog_post_id', true );
$html = get_post_field( 'post_content', $blog );
preg_match( '/<img[^>]+>/', $html, $img );
preg_match( '/width="(\d+)"/', $img[0], $w );
preg_match( '/ src="([^"]+)"/', $img[0], $src );
echo 'bild i inlägget: bredd ' . $w[1] . ' | src ' . basename( $src[1] ) . ' | ' . ( preg_match( '/-\d+x\d+\.\w+$/', $src[1] ) ? 'MINDRE STORLEK' : 'original' ) . "\n";
echo 'utvald bild satt: ' . ( has_post_thumbnail( $blog ) ? 'ja' : 'nej' ) . "\n";
$fusion = get_post_meta( $blog, '_fusion', true );
echo 'Avada show_first_featured_image: ' . ( isset( $fusion['show_first_featured_image'] ) ? $fusion['show_first_featured_image'] : '-' ) . "\n";
// Andra Avada-inställningar på inlägget ska vara kvar när utkastet uppdateras.
update_post_meta( $blog, '_fusion', array( 'show_first_featured_image' => 'no', 'page_title_bar' => 'no' ) );
$_POST = array( 'cvkp_nonce' => wp_create_nonce( 'cvkp_save' ), 'cvkp_run' => 1, 'cvkp' => wp_slash( array( 'heading' => 'Bloggtest 2', 'channels' => array( 'blog' ), 'trips' => array( 'a' => array( 'id' => $trip->ID, 'text' => 'Text.' ) ) ) ) );
wp_update_post( array( 'ID' => $id, 'post_title' => 'Bloggtest' ) );
$_POST = array();
$fusion = get_post_meta( $blog, '_fusion', true );
echo 'efter uppdatering: ' . json_encode( $fusion ) . "\n";
delete_transient( 'cvkp_notices_' . get_current_user_id() );
