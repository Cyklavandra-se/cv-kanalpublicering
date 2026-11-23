<?php
// Test av Claude-rutan: länkar → resor med första stycket, ingress från Claude, egen ingress, fel.
$_SERVER['HTTP_HOST'] = 'localhost:8899'; $_SERVER['REQUEST_URI'] = '/';
require getenv( 'W' ) . '/wp-load.php';
wp_set_current_user( 1 );

// En resesida som liknar Avadas uppbyggnad.
$avada = get_page_by_path( 'mosel-avada' );
if ( ! $avada ) {
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'mosel-avada', 'post_title' => 'Mosel – från Metz till Koblenz',
		'post_content' => '[fusion_builder_container][fusion_builder_row][fusion_builder_column type="1_1"][fusion_title title_type="text" size="2"]Cykla längs Mosel[/fusion_title][fusion_text]<p>Resan börjar i Metz, Lothringens historiska huvudstad med gotisk katedral, gamla befästningar och ett levande kulturliv. Därifrån följer ni Mosel norrut.</p>
<p>Efter gränsen tar vinodlingarna över.</p>[/fusion_text][fusion_text]ca 63 km[/fusion_text][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]' ) );
	wp_set_post_tags( $id, array( 'cykla', 'bike2' ) );
	$avada = get_post( $id );
}
$base  = home_url( '/' );
$links = get_permalink( $avada ) . "\n" . get_permalink( get_page_by_title( 'Madeira – vandra', OBJECT, 'page' ) ) . "\nhttps://example.com/annan-sajt/\n" . $base . 'finns-inte/';

// Som Wachau-sidan på sajten: bildram med adress först, rubrik, två stycken, en kort rad.
$wachau = get_page_by_path( 'wachau-avada' );
if ( ! $wachau ) {
	$wid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'wachau-avada', 'post_title' => 'Wachau 5 dagar',
		'post_content' => '[fusion_builder_container][fusion_builder_row][fusion_builder_column type="1_1"][fusion_imageframe image_id="123|full" lightbox="yes"]https://cyklavandra.se/wp-content/uploads/2026/06/Mosel-Weinberge-8-1200x750.jpg[/fusion_imageframe][fusion_title size="2"]Vandra i Wachau[/fusion_title][fusion_text]<p>En härlig vandringsresa genom UNESCO-världsarvet Wachau. Turen följer Welterbesteig Wachau – Världsarvsleden, en av Österrikes finaste vandringsleder – högt över Donau, genom vingårdsterrasser, aprikosodlingar och charmiga vinbyar.</p>
<p>Utforska pittoreska byar som Dürnstein, Weißenkirchen och Spitz, besök mäktiga kloster och smaka på regionens berömda aprikosknödlar.</p>
<p>5 dagar med charm – från Spitz till Krems med boende på utvalda charmhotell, och mycket annat.</p>[/fusion_text][fusion_gallery][fusion_gallery_image image="https://cyklavandra.se/wp-content/uploads/2026/06/bild-med-ett-mycket-langt-filnamn-som-blir-over-80-tecken.jpg"][/fusion_gallery_image][/fusion_gallery][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]' ) );
	wp_set_post_tags( $wid, array( 'vandra', 'hike2' ) );
	$wachau = get_post( $wid );
}
echo "== Wachau (två stycken, ingen bildadress)\n" . CVKP_Claude::first_paragraphs( $wachau->ID ) . "\n";
$d   = CVKP_Trip_Data::get( $wachau->ID );
$row = CVKP_Email_Renderer::trip_row( array( 'id' => $wachau->ID ), $d );
echo "== Etikett: data='{$d['label']}' visas='{$row['label']}'\n";
$blog = CVKP_Blog_Renderer::render( array_merge( CVKP_Mailing::defaults(), array( 'trips' => array( array( 'id' => $wachau->ID, 'text' => CVKP_Claude::first_paragraphs( $wachau->ID ) ) ) ) ) );
echo "== Blogg: " . ( false === strpos( $blog, '<em>' ) ? 'ingen etikett' : 'ETIKETT KVAR' ) . ', ' . ( false === strpos( $blog, 'Mosel-Weinberge' ) ? 'ingen bildadress' : 'BILDADRESS KVAR' ) . ', stycken=' . substr_count( $blog, '<p>' ) . "\n";
$mail = CVKP_Email_Renderer::render( array_merge( CVKP_Mailing::defaults(), array( 'heading' => 'X', 'trips' => array( array( 'id' => $wachau->ID, 'text' => CVKP_Claude::first_paragraphs( $wachau->ID ) ) ) ) ) );
echo "== Mejl: " . ( false === strpos( $mail, 'aprikosknödlar. <a' ) ? 'Läs mer EJ sist i andra stycket' : 'Läs mer sist i andra stycket' ) . ', ' . ( preg_match( '/>Vandring</', $mail ) ? 'ETIKETT KVAR' : 'ingen ensam etikett' ) . "\n";
echo "== Första stycket (Avada)\n" . CVKP_Claude::first_paragraphs( $avada->ID ) . "\n";
echo "== Nyckelkälla: " . CVKP_Claude::key_source() . "\n";

update_option( 'cvkp_mock_claude', '' );
$r = CVKP_Claude::fill( '', $links, array( 'ig_signer' => 'Sarah', 'code' => 'TIDIG26' ) );
echo "== Utan ingress\nav: {$r['by']}\ningress: {$r['intro']}\ntexter: " . implode( ', ', array_keys( $r['texts'] ) ) . "\nämnesrad: {$r['texts']['subject']}\n";
foreach ( $r['trips'] as $t ) { echo "resa {$t['id']} {$t['title']}: " . mb_substr( $t['text'], 0, 60 ) . "\n"; }
echo 'varningar: ' . implode( ' | ', $r['warnings'] ) . "\n";
$req = json_decode( file_get_contents( WP_CONTENT_DIR . '/cvkp-mock-claude.json' ), true );
echo "anrop: model={$req['model']} effort={$req['output_config']['effort']} format={$req['output_config']['format']['type']} fält=" . count( $req['output_config']['format']['schema']['required'] ) . " fallbacks={$req['fallbacks']} thinking=" . ( isset( $req['thinking'] ) ? 'satt' : 'utelämnad' ) . "\n";
echo 'underlag nämner: ' . implode( ', ', array_filter( array( false !== strpos( $req['messages'][0]['content'], 'TIDIG26' ) ? 'rabattkod' : '', false !== strpos( $req['messages'][0]['content'], 'Sarah' ) ? 'Sarah' : '', false !== strpos( $req['messages'][0]['content'], 'Resan börjar i Metz' ) ? 'första stycket' : '' ) ) ) . "\n";

$r = CVKP_Claude::fill( "Vår egen ingress.\n\nAndra stycket.", get_permalink( $avada ) );
echo "== Egen ingress\nav: {$r['by']} | {$r['intro']} | texter: " . count( $r['texts'] ) . " (intro ingår inte: " . ( isset( $r['texts']['intro'] ) ? 'FEL' : 'ok' ) . ")\n";

update_option( 'cvkp_mock_claude', 'refusal' );
$r = CVKP_Claude::fill( '', get_permalink( $avada ) );
echo "== Nekad\nav: {$r['by']} | varningar: " . implode( ' | ', $r['warnings'] ) . "\n";
update_option( 'cvkp_mock_claude', '401' );
$r = CVKP_Claude::fill( '', get_permalink( $avada ) );
echo "== Fel nyckel\nav: {$r['by']} | varningar: " . implode( ' | ', $r['warnings'] ) . "\n";
update_option( 'cvkp_mock_claude', '' );

$r = CVKP_Claude::fill( '', "inga länkar här" );
echo "== Inga länkar: " . ( is_wp_error( $r ) ? $r->get_error_message() : 'FEL' ) . "\n";
$r = CVKP_Claude::fill( '', 'https://example.com/x/' );
echo "== Bara okända: " . ( is_wp_error( $r ) ? $r->get_error_message() : 'FEL' ) . "\n";
