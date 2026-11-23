<?php
/**
 * Bygger blogginläggets innehåll (vanlig HTML) av samma utskick.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Blog_Renderer {

	/**
	 * @param array $m Utskickets fält.
	 * @return string
	 */
	public static function render( array $m ) {
		$out   = array();
		$intro = '' !== trim( $m['blog_intro'] ) ? $m['blog_intro'] : $m['intro'];

		if ( '' !== trim( $intro ) ) {
			$out[] = wpautop( wp_kses_post( $intro ) );
		}

		if ( '' !== trim( $m['code'] ) ) {
			$note  = '' !== trim( $m['code_note'] ) ? '<br>' . esc_html( $m['code_note'] ) : '';
			$out[] = '<div class="cvkp-rabattkod" style="border:2px dashed #2AB858;background:#EAF6EE;padding:20px;text-align:center;margin:0 0 24px 0;">'
				. '<p style="margin:0;">Rabattkod: <strong style="font-size:1.4em;letter-spacing:2px;">' . esc_html( $m['code'] ) . '</strong>' . $note . '</p></div>';
		}

		if ( '' !== trim( $m['after_offer'] ) ) {
			$out[] = wpautop( wp_kses_post( $m['after_offer'] ) );
		}

		if ( '' !== trim( $m['trips_heading'] ) ) {
			$out[] = '<h2>' . esc_html( $m['trips_heading'] ) . '</h2>';
		}
		if ( '' !== trim( $m['trips_intro'] ) ) {
			$out[] = wpautop( wp_kses_post( $m['trips_intro'] ) );
		}

		foreach ( (array) $m['trips'] as $row ) {
			$data = CVKP_Trip_Data::get( (int) $row['id'] );
			if ( ! $data ) {
				continue;
			}
			$trip  = CVKP_Email_Renderer::trip_row( $row, $data );
			$out[] = '<h3>' . esc_html( $trip['heading'] ) . '</h3>';
			if ( $data['image_id'] ) {
				$out[] = '<p><a href="' . esc_url( $data['url'] ) . '">' . wp_get_attachment_image( $data['image_id'], CVKP_Trip_Data::image_size( $data['image_id'] ), false, array( 'alt' => $data['title'] ) ) . '</a></p>';
			}
			$text  = wpautop( wp_kses_post( $trip['text'] ) );
			$link  = ' <a href="' . esc_url( $data['url'] ) . '">Läs mer</a>.';
			$pos   = strrpos( $text, '</p>' );
			$out[] = false === $pos ? '<p>' . ltrim( $link ) . '</p>' : substr( $text, 0, $pos ) . $link . substr( $text, $pos );
		}

		if ( '' !== trim( $m['closing'] ) ) {
			$out[] = wpautop( wp_kses_post( $m['closing'] ) );
		}

		return implode( "\n\n", $out );
	}
}
