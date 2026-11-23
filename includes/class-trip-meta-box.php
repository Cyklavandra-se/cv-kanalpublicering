<?php
/**
 * Rutan "Resefakta" på resesidor: dagar, nätter, land och etikett.
 * Tomma fält visar det som tolkats ur sidtexten som förslag.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Trip_Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes_page', array( __CLASS__, 'add' ) );
		add_action( 'save_post_page', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/** Gör fälten läsbara via REST API så att t.ex. AI-sökningen kan använda dem. */
	public static function register_meta() {
		$fields = array(
			CVKP_Trip_Data::META_DAYS    => 'integer',
			CVKP_Trip_Data::META_NIGHTS  => 'integer',
			CVKP_Trip_Data::META_COUNTRY => 'string',
			CVKP_Trip_Data::META_LABEL   => 'string',
		);
		foreach ( $fields as $key => $type ) {
			register_post_meta(
				'page',
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static function () {
						return current_user_can( 'edit_pages' );
					},
				)
			);
		}
	}

	public static function add( $post ) {
		// Bara på resesidor (taggade cykla/vandra) och på nya sidor som ännu inte har taggar.
		if ( 'auto-draft' !== $post->post_status && ! CVKP_Trip_Data::is_trip( $post ) ) {
			return;
		}
		add_meta_box( 'cvkp-resefakta', 'Resefakta', array( __CLASS__, 'render' ), 'page', 'side', 'default' );
	}

	public static function render( $post ) {
		wp_nonce_field( 'cvkp_resefakta', 'cvkp_resefakta_nonce' );
		$parsed  = CVKP_Trip_Data::parse_content( $post->post_content );
		$days    = get_post_meta( $post->ID, CVKP_Trip_Data::META_DAYS, true );
		$nights  = get_post_meta( $post->ID, CVKP_Trip_Data::META_NIGHTS, true );
		$country = get_post_meta( $post->ID, CVKP_Trip_Data::META_COUNTRY, true );
		$label   = get_post_meta( $post->ID, CVKP_Trip_Data::META_LABEL, true );
		$data    = CVKP_Trip_Data::get( $post->ID );
		?>
		<p class="description">Används i nyhetsbrev, blogginlägg och sociala medier. Grå siffror är tolkade ur sidtexten – fyll i bara om de är fel.</p>
		<p>
			<label for="cvkp-days">Dagar</label><br>
			<input type="number" min="0" max="60" id="cvkp-days" name="cvkp_days" value="<?php echo esc_attr( $days ); ?>" placeholder="<?php echo esc_attr( $parsed['days'] ? $parsed['days'] : '' ); ?>" style="width:6em">
		</p>
		<p>
			<label for="cvkp-nights">Nätter</label><br>
			<input type="number" min="0" max="60" id="cvkp-nights" name="cvkp_nights" value="<?php echo esc_attr( $nights ); ?>" placeholder="<?php echo esc_attr( $parsed['nights'] ? $parsed['nights'] : '' ); ?>" style="width:6em">
		</p>
		<p>
			<label for="cvkp-country">Land</label><br>
			<input type="text" id="cvkp-country" name="cvkp_country" value="<?php echo esc_attr( $country ); ?>" placeholder="t.ex. Spanien &amp; Frankrike" class="widefat">
		</p>
		<p>
			<label for="cvkp-label">Egen etikett (valfritt)</label><br>
			<input type="text" id="cvkp-label" name="cvkp_label" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php echo esc_attr( $data ? CVKP_Trip_Data::default_label( $data ) : '' ); ?>" class="widefat">
		</p>
		<?php if ( $data && $data['level'] ) : ?>
			<p class="description">Nivå <?php echo (int) $data['level']; ?> (från taggarna)</p>
		<?php endif; ?>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['cvkp_resefakta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cvkp_resefakta_nonce'] ), 'cvkp_resefakta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		$ints = array(
			'cvkp_days'   => CVKP_Trip_Data::META_DAYS,
			'cvkp_nights' => CVKP_Trip_Data::META_NIGHTS,
		);
		foreach ( $ints as $field => $key ) {
			$value = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
			$value ? update_post_meta( $post_id, $key, $value ) : delete_post_meta( $post_id, $key );
		}

		$texts = array(
			'cvkp_country' => CVKP_Trip_Data::META_COUNTRY,
			'cvkp_label'   => CVKP_Trip_Data::META_LABEL,
		);
		foreach ( $texts as $field => $key ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			'' !== $value ? update_post_meta( $post_id, $key, $value ) : delete_post_meta( $post_id, $key );
		}
	}
}
