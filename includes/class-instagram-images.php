<?php
/**
 * Bilder till Instagram: väljer bland resesidans egna bilder och gör beskurna JPEG-kopior.
 *
 * Instagram tar bara emot JPEG med bildformat mellan 4:5 och 1.91:1, och i en karusell
 * beskärs alla bilder till samma format. Resornas utvalda bilder är 1200×630 (1.9:1),
 * vilket blir litet i flödet – därför beskärs kopiorna till valt format (standard 4:5).
 * Originalen ändras aldrig. Kopiorna ligger i uploads/cvkp-instagram/.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Instagram_Images {

	const DIR = 'cvkp-instagram';

	/** Tillåtna format: bredd / höjd. */
	const RATIOS = array(
		'4:5'    => 0.8,
		'1:1'    => 1.0,
		'1.91:1' => 1.91,
	);

	/** Bredd på de färdiga bilderna (Instagrams rekommendation). */
	const WIDTH = 1080;

	/**
	 * Bilder att välja mellan för en resa: utvald bild först, sedan bilderna i sidtexten.
	 *
	 * @return int[] Bilagornas ID.
	 */
	public static function candidates( $trip_id, $limit = 16 ) {
		$post = get_post( $trip_id );
		if ( ! $post ) {
			return array();
		}
		$ids   = array();
		$thumb = (int) get_post_thumbnail_id( $post );
		if ( $thumb ) {
			$ids[] = $thumb;
		}

		$content = (string) $post->post_content;
		// Klassisk editor och Gutenberg: class="wp-image-123". Avada: image_id="123|full".
		if ( preg_match_all( '/wp-image-(\d+)|image_id="(\d+)/', $content, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$ids[] = (int) ( ! empty( $match[1] ) ? $match[1] : $match[2] );
			}
		}
		// Bilder som bara finns som adress (t.ex. i Avadas bildelement).
		if ( preg_match_all( '#(?:src|image|link)="(https?://[^"]+/wp-content/uploads/[^"]+\.(?:jpe?g|png|webp))"#i', $content, $m ) ) {
			foreach ( array_slice( array_unique( $m[1] ), 0, 40 ) as $url ) {
				$id = attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', $url ) );
				if ( $id ) {
					$ids[] = (int) $id;
				}
			}
		}

		$out = array();
		foreach ( array_unique( $ids ) as $id ) {
			if ( $id && wp_attachment_is_image( $id ) ) {
				$out[] = $id;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	public static function ratio_key() {
		$key = (string) CVKP_Settings::get( 'ig_ratio' );
		return isset( self::RATIOS[ $key ] ) ? $key : '4:5';
	}

	/**
	 * Gör (eller återanvänder) en beskuren JPEG-kopia.
	 *
	 * @return array|WP_Error array( 'url', 'path', 'width', 'height', 'attachment_id' )
	 */
	public static function prepare( $attachment_id, $ratio_key = null ) {
		$ratio_key = null === $ratio_key ? self::ratio_key() : $ratio_key;
		$ratio     = self::RATIOS[ isset( self::RATIOS[ $ratio_key ] ) ? $ratio_key : '4:5' ];
		$source    = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $attachment_id ) : get_attached_file( $attachment_id );
		if ( ! $source || ! file_exists( $source ) ) {
			$source = get_attached_file( $attachment_id );
		}
		if ( ! $source || ! file_exists( $source ) ) {
			return new WP_Error( 'cvkp_ig_image', 'Bildfilen hittades inte (bilaga ' . (int) $attachment_id . ').' );
		}

		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::DIR;
		$url     = trailingslashit( $uploads['baseurl'] ) . self::DIR;
		$name    = sprintf( '%d-%s-%s.jpg', $attachment_id, str_replace( array( ':', '.' ), array( 'x', '_' ), $ratio_key ), substr( md5( $source . filemtime( $source ) ), 0, 8 ) );
		$path    = $dir . '/' . $name;

		if ( ! file_exists( $path ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'cvkp_ig_image', 'Kunde inte skapa mappen ' . $dir . '.' );
			}
			$editor = wp_get_image_editor( $source );
			if ( is_wp_error( $editor ) ) {
				return $editor;
			}
			$size = $editor->get_size();
			$w    = (int) $size['width'];
			$h    = (int) $size['height'];
			if ( $w < 1 || $h < 1 ) {
				return new WP_Error( 'cvkp_ig_image', 'Okänd bildstorlek.' );
			}

			// Största möjliga utsnitt i mitten med rätt format.
			if ( $w / $h > $ratio ) {
				$crop_h = $h;
				$crop_w = (int) round( $h * $ratio );
			} else {
				$crop_w = $w;
				$crop_h = (int) round( $w / $ratio );
			}
			$x = (int) floor( ( $w - $crop_w ) / 2 );
			$y = (int) floor( ( $h - $crop_h ) / 2 );

			// Förstora aldrig – Instagram skalar själv upp små bilder.
			$dst_w = min( self::WIDTH, $crop_w );
			$dst_h = (int) round( $dst_w / $ratio );

			$cropped = $editor->crop( $x, $y, $crop_w, $crop_h, $dst_w, $dst_h );
			if ( is_wp_error( $cropped ) ) {
				return $cropped;
			}
			$editor->set_quality( 88 );
			$saved = $editor->save( $path, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$path = $saved['path'];
			$name = basename( $path );
		}

		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return array(
			'attachment_id' => (int) $attachment_id,
			'path'          => $path,
			'url'           => $url . '/' . $name,
			'width'         => $info ? (int) $info[0] : 0,
			'height'        => $info ? (int) $info[1] : 0,
		);
	}
}
