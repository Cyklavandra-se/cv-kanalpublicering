<?php
/**
 * Bygger nyhetsbrevets HTML (640 px, logga, resor med "Läs mer", footer med Mailchimp-taggar).
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Email_Renderer {

	const GREEN      = '#1E8F45';
	const GREEN_DARK = '#2AB858';
	const TEXT       = '#4A524D';
	const HEADING    = '#1F2A24';
	const FONT       = 'Helvetica,Arial,sans-serif';

	/**
	 * @param array $m Utskickets fält (se CVKP_Mailing::defaults()).
	 * @return string Komplett HTML-dokument.
	 */
	public static function render( array $m ) {
		$s     = CVKP_Settings::get();
		$trips = array();
		foreach ( (array) $m['trips'] as $row ) {
			$data = CVKP_Trip_Data::get( (int) $row['id'] );
			if ( ! $data ) {
				continue;
			}
			$trips[] = self::trip_row( $row, $data );
		}

		ob_start();
		include CVKP_DIR . 'templates/email.php';
		return (string) ob_get_clean();
	}

	/** Slår ihop det som skrivits i utskicket med resans fakta. Tomma fält = resans egna värden. */
	public static function trip_row( array $row, array $data ) {
		$heading = isset( $row['heading'] ) ? trim( (string) $row['heading'] ) : '';
		$label   = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
		if ( '' === $label ) {
			// Resans etikett, men inte om den bara är typen ("Vandring") – den säger ingenting.
			$label = trim( (string) $data['label'] ) === trim( (string) $data['type'] ) ? '' : $data['label'];
		}
		return array(
			'data'    => $data,
			'heading' => '' !== $heading ? $heading : $data['title'],
			'label'   => $label,
			'text'    => isset( $row['text'] ) ? (string) $row['text'] : '',
		);
	}

	/**
	 * Gör text från editorn mejlvänlig: stycken, inline-stilar på p, a, ul, li.
	 */
	public static function rich( $html, $align = 'left' ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}
		$html  = wpautop( wp_kses_post( $html ) );
		$p     = sprintf( 'margin:0 0 14px 0;font-family:%s;font-size:16px;line-height:25px;color:%s;text-align:%s;', self::FONT, self::TEXT, $align );
		$a     = sprintf( 'color:%s;font-weight:bold;text-decoration:underline;', self::GREEN );
		$list  = sprintf( 'margin:0 0 14px 0;padding-left:22px;font-family:%s;font-size:16px;line-height:25px;color:%s;', self::FONT, self::TEXT );
		$html  = preg_replace( '/<p(\s[^>]*)?>/i', '<p style="' . $p . '">', $html );
		$html  = preg_replace( '/<(ul|ol)(\s[^>]*)?>/i', '<$1 style="' . $list . '">', $html );
		$html  = preg_replace( '/<a\s(?![^>]*style=)/i', '<a style="' . $a . '" target="_blank" ', $html );
		return $html;
	}

	/** Lägger till "Läs mer"-länken sist i resans text, inne i sista stycket. */
	public static function trip_text( $text, $url ) {
		$link = sprintf(
			' <a href="%s" target="_blank" style="color:%s;font-weight:bold;text-decoration:underline;">Läs mer</a>.',
			esc_url( $url ),
			self::GREEN
		);
		$html = self::rich( $text );
		if ( '' === $html ) {
			return '<p style="margin:0 0 14px 0;font-family:' . self::FONT . ';font-size:16px;line-height:25px;">' . ltrim( $link ) . '</p>';
		}
		$pos = strrpos( $html, '</p>' );
		return false === $pos ? $html . $link : substr( $html, 0, $pos ) . $link . substr( $html, $pos );
	}

	/** Bryter Gmails automatiska adresslänkar (blå text på mörk botten). */
	public static function unlinkable( $text ) {
		$text = esc_html( $text );
		return preg_replace( '/(\S)\s(\d)/u', '$1&zwnj; $2', $text );
	}
}
