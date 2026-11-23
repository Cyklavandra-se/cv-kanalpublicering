<?php
/**
 * Innehållstypen "Utskick": ett utskick skrivs en gång och blir utkast i valda kanaler.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Mailing {

	const POST_TYPE   = 'cvkp_utskick';
	const META_DATA   = '_cvkp_data';
	const META_MC     = '_cvkp_mailchimp';
	const META_BLOG   = '_cvkp_blog_post_id';
	const META_SOURCE = '_cvkp_mailing_id'; // På blogginlägget: vilket utskick det kom från.

	/** Kanaler. Facebook och Instagram blir utkast i WordPress och schemaläggs separat. */
	const CHANNELS = array(
		'mailchimp' => 'Mailchimp – utkast till nyhetsbrev',
		'blog'      => 'Blogginlägg – utkast på cyklavandra.se',
		'facebook'  => 'Facebook – utkast till länkinlägg',
		'instagram' => 'Instagram – utkast till karusell',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_preview' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'force_status' ), 10, 2 );
		add_action( 'wp_ajax_cvkp_trip_row', array( __CLASS__, 'ajax_trip_row' ) );
	}

	/** Utskick är interna och har ingen publiceringsstatus – de sparas alltid som "publicerade". */
	public static function force_status( $data, $postarr ) {
		if ( self::POST_TYPE === $data['post_type'] && in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) && ! empty( $postarr['ID'] ) && isset( $_POST['cvkp_nonce'] ) ) {
			$data['post_status'] = 'publish';
		}
		return $data;
	}

	/** Ny rad i reselistan, med resans beskrivning som starttext. */
	public static function ajax_trip_row() {
		check_ajax_referer( 'cvkp_trip', 'nonce' );
		if ( ! current_user_can( CVKP_Settings::CAPABILITY ) ) {
			wp_send_json_error( 'Behörighet saknas.', 403 );
		}
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = $id ? CVKP_Trip_Data::get( $id ) : null;
		if ( ! $data ) {
			wp_send_json_error( 'Resan hittades inte.', 404 );
		}
		ob_start();
		self::trip_row_html( array( 'id' => $id, 'text' => $data['description'] ), $data, uniqid() );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	public static function register() {
		$cap = CVKP_Settings::CAPABILITY;
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => 'Utskick',
					'singular_name' => 'Utskick',
					'menu_name'     => 'Utskick',
					'add_new'       => 'Nytt utskick',
					'add_new_item'  => 'Nytt utskick',
					'edit_item'     => 'Redigera utskick',
					'all_items'     => 'Kanalpublicering', // Enda raden i menyn Cykla&Vandra.
					'search_items'  => 'Sök utskick',
					'not_found'     => 'Inga utskick ännu.',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Cyklavandra_Meny::SLUG, // Under menyn Cykla&Vandra.
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-megaphone',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array(
					'create_posts' => $cap,
					'edit_posts'   => $cap,
				),
			)
		);
	}

	/** Fälten i ett utskick, med standardvärden från inställningarna. */
	public static function defaults() {
		$s = CVKP_Settings::get();
		return array(
			'subject'       => '',
			'preheader'     => '',
			'eyebrow'       => '',
			'heading'       => '',
			'intro'         => '',
			'code'          => '',
			'code_note'     => '',
			'after_offer'   => '',
			'trips_heading' => '',
			'trips_intro'   => '',
			'trips'         => array(),
			'closing'       => '',
			'greeting'      => $s['greeting'],
			'ps'            => $s['ps'],
			'button_text'   => $s['button_text'],
			'button_url'    => $s['button_url'],
			'blog_title'    => '',
			'blog_intro'    => '',
			'blog_excerpt'  => '',
			'fb_text'       => '',
			'fb_link'       => '',
			'ig_signer'     => '',
			'ig_text'       => '',
			'channels'      => array_keys( self::CHANNELS ),
		);
	}

	public static function get_data( $post_id ) {
		$data = get_post_meta( $post_id, self::META_DATA, true );
		return wp_parse_args( is_array( $data ) ? $data : array(), self::defaults() );
	}

	public static function title_placeholder( $text, $post ) {
		return self::POST_TYPE === $post->post_type ? 'Internt namn, t.ex. "Boka tidigt – november 2026"' : $text;
	}

	public static function updated_messages( $messages ) {
		$messages[ self::POST_TYPE ] = array_fill( 0, 11, 'Utskicket är sparat.' );
		$messages[ self::POST_TYPE ][0] = '';
		return $messages;
	}

	/* ------------------------------------------------------------------ */
	/* Redigeringsvyn                                                       */
	/* ------------------------------------------------------------------ */

	public static function meta_boxes( $post ) {
		add_meta_box( 'cvkp-content', 'Innehåll', array( __CLASS__, 'box_content' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'cvkp-trips', 'Resor', array( __CLASS__, 'box_trips' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'cvkp-ending', 'Avslutning', array( __CLASS__, 'box_ending' ), self::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'cvkp-blog', 'Blogginlägg', array( __CLASS__, 'box_blog' ), self::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'cvkp-social', 'Facebook och Instagram', array( 'CVKP_Social', 'box' ), self::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'cvkp-channels', 'Kanaler', array( __CLASS__, 'box_channels' ), self::POST_TYPE, 'side', 'high' );
		remove_meta_box( 'submitdiv', self::POST_TYPE, 'side' );
	}

	private static function field( $key, $label, $value, $help = '', $placeholder = '' ) {
		printf(
			'<p class="cvkp-field"><label for="cvkp-%1$s"><strong>%2$s</strong></label><input type="text" class="widefat" id="cvkp-%1$s" name="cvkp[%1$s]" value="%3$s" placeholder="%5$s">%4$s</p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $value ),
			$help ? '<span class="description">' . esc_html( $help ) . '</span>' : '',
			esc_attr( $placeholder )
		);
	}

	private static function editor( $key, $label, $value, $rows = 5, $help = '' ) {
		echo '<div class="cvkp-field"><p><strong>' . esc_html( $label ) . '</strong>';
		if ( $help ) {
			echo '<br><span class="description">' . esc_html( $help ) . '</span>';
		}
		echo '</p>';
		wp_editor(
			$value,
			'cvkp_' . $key,
			array(
				'textarea_name' => 'cvkp[' . $key . ']',
				'textarea_rows' => $rows,
				'media_buttons' => false,
				'teeny'         => true,
				'quicktags'     => array( 'buttons' => 'strong,em,link,ul,li' ),
			)
		);
		echo '</div>';
	}

	public static function box_content( $post ) {
		$d = self::get_data( $post->ID );
		wp_nonce_field( 'cvkp_save', 'cvkp_nonce' );
		self::field( 'subject', 'Ämnesrad', $d['subject'], 'Syns i inkorgen. Emoji går bra.' );
		self::field( 'preheader', 'Förhandstext', $d['preheader'], 'Grå text efter ämnesraden i inkorgen.' );
		self::field( 'eyebrow', 'Överrubrik (valfri)', $d['eyebrow'], '', 'ERBJUDANDE PÅ SJÄLVGUIDADE RESOR' );
		self::field( 'heading', 'Rubrik', $d['heading'] );
		self::editor( 'intro', 'Inledning', $d['intro'], 5 );
		echo '<div class="cvkp-cols">';
		self::field( 'code', 'Rabattkod (valfri)', $d['code'], 'Visas i en egen ruta.' );
		self::field( 'code_note', 'Text under koden', $d['code_note'], '', 'Gäller till den 6 december – först till kvarn!' );
		echo '</div>';
		self::editor( 'after_offer', 'Text efter rabattkoden (valfri)', $d['after_offer'], 3 );
	}

	public static function box_trips( $post ) {
		$d = self::get_data( $post->ID );
		self::field( 'trips_heading', 'Rubrik för resorna', $d['trips_heading'], '', 'Nya självguidade resor' );
		self::editor( 'trips_intro', 'Text före resorna', $d['trips_intro'], 4 );

		echo '<p><strong>Resor i utskicket</strong><br><span class="description">Bild, länk, typ och antal dagar hämtas från resesidan. Tomma fält = resans egna uppgifter. "Läs mer" läggs till sist i texten automatiskt.</span></p>';
		echo '<div id="cvkp-trip-list">';
		foreach ( $d['trips'] as $row ) {
			$data = CVKP_Trip_Data::get( (int) $row['id'] );
			if ( $data ) {
				self::trip_row_html( $row, $data, uniqid() );
			}
		}
		echo '</div>';

		echo '<p class="cvkp-add-trip"><select id="cvkp-trip-select"><option value="">— Välj resa —</option>';
		foreach ( CVKP_Trip_Data::all_trips() as $trip ) {
			printf( '<option value="%d">%s</option>', (int) $trip->ID, esc_html( html_entity_decode( get_the_title( $trip ), ENT_QUOTES, 'UTF-8' ) ) );
		}
		echo '</select> <button type="button" class="button" id="cvkp-add-trip">Lägg till resa</button> <span class="spinner"></span></p>';
	}

	/** En rad i reselistan. $data null = mall för JavaScript. Används också av Claude-rutan. */
	public static function trip_row_html( array $row, $data, $key ) {
		$row  = wp_parse_args(
			$row,
			array(
				'id'      => '',
				'heading' => '',
				'label'    => '',
				'text'     => '',
				'ig'       => '1',
				'ig_image' => 0,
			)
		);
		$name = 'cvkp[trips][' . $key . ']';
		$imgs = $row['id'] ? CVKP_Instagram_Images::candidates( (int) $row['id'] ) : array();
		$pick = (int) $row['ig_image'] && in_array( (int) $row['ig_image'], $imgs, true ) ? (int) $row['ig_image'] : ( $imgs ? $imgs[0] : 0 );
		?>
		<div class="cvkp-trip" data-id="<?php echo esc_attr( $row['id'] ); ?>">
			<div class="cvkp-trip-head">
				<span class="cvkp-trip-thumb"><?php if ( $data && $data['image_url'] ) : ?><img src="<?php echo esc_url( $data['image_url'] ); ?>" alt=""><?php endif; ?></span>
				<span class="cvkp-trip-title"><?php echo $data ? esc_html( $data['title'] ) : ''; ?></span>
				<a class="cvkp-trip-link" href="<?php echo $data ? esc_url( $data['url'] ) : '#'; ?>" target="_blank" rel="noopener">Visa resan ↗</a>
				<span class="cvkp-trip-actions">
					<button type="button" class="button-link cvkp-up" aria-label="Flytta upp">↑</button>
					<button type="button" class="button-link cvkp-down" aria-label="Flytta ner">↓</button>
					<button type="button" class="button-link cvkp-remove">Ta bort</button>
				</span>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $row['id'] ); ?>" class="cvkp-trip-id">
			<div class="cvkp-cols">
				<p><label>Rubrik</label><input type="text" class="widefat cvkp-trip-heading" name="<?php echo esc_attr( $name ); ?>[heading]" value="<?php echo esc_attr( $row['heading'] ); ?>" placeholder="<?php echo $data ? esc_attr( $data['title'] ) : ''; ?>"></p>
				<p><label>Etikett ovanför rubriken</label><input type="text" class="widefat cvkp-trip-label" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php echo $data ? esc_attr( $data['label'] ) : ''; ?>"></p>
			</div>
			<p><label>Text</label><textarea class="widefat cvkp-trip-text" rows="5" name="<?php echo esc_attr( $name ); ?>[text]"><?php echo esc_textarea( $row['text'] ); ?></textarea></p>
			<div class="cvkp-trip-ig">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[ig]" value="0">
				<label class="cvkp-inline"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ig]" value="1" <?php checked( '0' !== (string) $row['ig'] ); ?>> Med på Instagram, med bilden:</label>
				<?php if ( $imgs ) : ?>
				<div class="cvkp-ig-choices">
					<?php foreach ( $imgs as $img ) : ?>
					<label title="<?php echo esc_attr( get_the_title( $img ) ); ?>"><input type="radio" name="<?php echo esc_attr( $name ); ?>[ig_image]" value="<?php echo (int) $img; ?>" <?php checked( $pick, $img ); ?>><?php echo wp_get_attachment_image( $img, 'thumbnail' ); ?></label>
					<?php endforeach; ?>
				</div>
				<?php else : ?>
				<span class="description">Resan har inga bilder.</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function box_ending( $post ) {
		$d = self::get_data( $post->ID );
		self::editor( 'closing', 'Avslutande ruta (valfri)', $d['closing'], 3, 'Till exempel en påminnelse om rabattkoden.' );
		echo '<div class="cvkp-cols">';
		echo '<p class="cvkp-field"><label for="cvkp-greeting"><strong>Hälsning</strong></label><textarea class="widefat" rows="2" id="cvkp-greeting" name="cvkp[greeting]">' . esc_textarea( $d['greeting'] ) . '</textarea></p>';
		echo '<p class="cvkp-field"><label for="cvkp-ps"><strong>PS</strong></label><textarea class="widefat" rows="2" id="cvkp-ps" name="cvkp[ps]">' . esc_textarea( $d['ps'] ) . '</textarea></p>';
		echo '</div><div class="cvkp-cols">';
		self::field( 'button_text', 'Knapptext', $d['button_text'] );
		self::field( 'button_url', 'Knapplänk', $d['button_url'] );
		echo '</div>';
	}

	public static function box_blog( $post ) {
		$d = self::get_data( $post->ID );
		self::field( 'blog_title', 'Rubrik på blogginlägget', $d['blog_title'], 'Tomt = samma som rubriken ovan.' );
		self::editor( 'blog_intro', 'Egen inledning för bloggen (valfri)', $d['blog_intro'], 4, 'Tomt = samma inledning som i nyhetsbrevet. Skriv en egen om inledningen talar om "mejlet".' );
		echo '<p class="cvkp-field"><label for="cvkp-blog_excerpt"><strong>Utdrag / beskrivning</strong></label><textarea class="widefat" rows="2" id="cvkp-blog_excerpt" name="cvkp[blog_excerpt]">' . esc_textarea( $d['blog_excerpt'] ) . '</textarea><span class="description">Visas i bloggflödet och när inlägget delas.</span></p>';
	}

	public static function box_channels( $post ) {
		$d       = self::get_data( $post->ID );
		$mc      = get_post_meta( $post->ID, self::META_MC, true );
		$blog_id = (int) get_post_meta( $post->ID, self::META_BLOG, true );
		$preview = add_query_arg(
			array(
				'cvkp_preview' => $post->ID,
				'cvkp_pnonce'  => wp_create_nonce( 'cvkp_preview_' . $post->ID ),
			),
			admin_url( 'admin.php' )
		);

		echo '<p>Välj kanaler och klicka på knappen. Allt skapas som <strong>utkast</strong> – inget skickas eller publiceras.</p>';
		foreach ( self::CHANNELS as $key => $label ) {
			printf(
				'<p><label><input type="checkbox" name="cvkp[channels][]" value="%s" %s> %s</label></p>',
				esc_attr( $key ),
				checked( in_array( $key, (array) $d['channels'], true ), true, false ),
				esc_html( $label )
			);
		}
		echo '<p class="description">Facebook och Instagram blir utkast här i WordPress. De publiceras först när någon granskat dem och klickat <em>Schemalägg</em> i rutan Facebook och Instagram.</p>';

		echo '<p class="cvkp-buttons"><button type="submit" name="save" value="Spara" class="button button-large">Spara</button> ';
		echo '<button type="submit" name="cvkp_run" value="1" class="button button-primary button-large cvkp-run">Spara och skapa utkast</button></p>';
		printf(
			'<p><button type="submit" class="button" formaction="%s" formtarget="_blank">Förhandsgranska nyhetsbrevet</button></p>',
			esc_url( $preview )
		);

		echo '<hr><p><strong>Status</strong></p><ul class="cvkp-status">';
		if ( is_array( $mc ) && ! empty( $mc['url'] ) ) {
			printf(
				'<li>Mailchimp: <a href="%s" target="_blank" rel="noopener">öppna utkastet ↗</a><br><span class="description">Uppdaterat %s</span></li>',
				esc_url( $mc['url'] ),
				esc_html( wp_date( 'j M H:i', (int) $mc['time'] ) )
			);
		} else {
			echo '<li>Mailchimp: inget utkast ännu</li>';
		}
		if ( $blog_id && get_post( $blog_id ) ) {
			printf(
				'<li>Blogg: <a href="%s" target="_blank" rel="noopener">%s ↗</a> (%s)</li>',
				esc_url( get_edit_post_link( $blog_id ) ),
				esc_html( get_the_title( $blog_id ) ),
				esc_html( 'publish' === get_post_status( $blog_id ) ? 'publicerat' : 'utkast' )
			);
		} else {
			echo '<li>Blogg: inget inlägg ännu</li>';
		}
		$labels = array(
			'none'       => 'utkast',
			'scheduled'  => 'schemalagt',
			'publishing' => 'publiceras just nu',
			'published'  => 'publicerat',
			'failed'     => 'inte publicerat – se nedan',
		);
		foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram' ) as $channel => $label ) {
			$state = CVKP_Social::get_state( $post->ID, $channel );
			if ( empty( $state['draft'] ) && 'none' === $state['status'] ) {
				printf( '<li>%s: inget utkast ännu</li>', esc_html( $label ) );
				continue;
			}
			$when = 'scheduled' === $state['status'] ? ' ' . wp_date( 'j M H:i', (int) $state['scheduled']['at'] ) : '';
			printf( '<li>%s: <a href="#cvkp-social">%s%s</a></li>', esc_html( $label ), esc_html( $labels[ $state['status'] ] ), esc_html( $when ) );
		}
		echo '</ul>';
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$post = 'post' === $screen->base ? get_post() : null;
		// Utskick har inget innehållsfält; WordPress autospar ger annars falska varningar om säkerhetskopior.
		wp_dequeue_script( 'autosave' );
		wp_enqueue_style( 'cvkp-admin', CVKP_URL . 'assets/admin.css', array(), CVKP_VERSION );
		wp_enqueue_script( 'cvkp-admin', CVKP_URL . 'assets/admin.js', array(), CVKP_VERSION, true );
		wp_localize_script(
			'cvkp-admin',
			'cvkpAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cvkp_trip' ),
				// För att spara osparade ändringar i webbläsaren (se admin.js).
				'postId'   => $post ? (int) $post->ID : 0,
				'isNew'    => ! $post || 'auto-draft' === $post->post_status,
				'modified' => $post ? (int) get_post_modified_time( 'U', true, $post ) : 0,
				'userId'   => get_current_user_id(),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Spara och skapa utkast                                               */
	/* ------------------------------------------------------------------ */

	/** Läser och tvättar formulärets fält. */
	public static function from_request( array $raw ) {
		$d     = self::defaults();
		$text  = array( 'subject', 'preheader', 'eyebrow', 'heading', 'code', 'code_note', 'trips_heading', 'button_text', 'blog_title', 'ig_signer' );
		$rich  = array( 'intro', 'after_offer', 'trips_intro', 'closing', 'greeting', 'ps', 'blog_intro' );
		$out   = array();
		foreach ( $text as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
		}
		foreach ( $rich as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) ? wp_kses_post( $raw[ $key ] ) : '';
		}
		$out['button_url']   = isset( $raw['button_url'] ) ? esc_url_raw( $raw['button_url'] ) : $d['button_url'];
		$out['blog_excerpt'] = isset( $raw['blog_excerpt'] ) ? sanitize_textarea_field( $raw['blog_excerpt'] ) : '';
		$out['fb_text']      = isset( $raw['fb_text'] ) ? sanitize_textarea_field( $raw['fb_text'] ) : '';
		$out['ig_text']      = isset( $raw['ig_text'] ) ? sanitize_textarea_field( $raw['ig_text'] ) : '';
		$out['fb_link']      = isset( $raw['fb_link'] ) ? esc_url_raw( trim( $raw['fb_link'] ) ) : '';
		if ( '' !== $out['ig_signer'] && ! in_array( $out['ig_signer'], CVKP_Settings::team(), true ) ) {
			$out['ig_signer'] = '';
		}
		$out['channels']     = isset( $raw['channels'] ) ? array_values( array_intersect( array_keys( self::CHANNELS ), (array) $raw['channels'] ) ) : array();

		$out['trips'] = array();
		if ( ! empty( $raw['trips'] ) && is_array( $raw['trips'] ) ) {
			foreach ( $raw['trips'] as $row ) {
				$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( ! $id ) {
					continue;
				}
				$out['trips'][] = array(
					'id'      => $id,
					'heading' => isset( $row['heading'] ) ? sanitize_text_field( $row['heading'] ) : '',
					'label'   => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
					'text'    => isset( $row['text'] ) ? wp_kses_post( $row['text'] ) : '',
					'ig'       => isset( $row['ig'] ) && '0' === (string) $row['ig'] ? '0' : '1',
					'ig_image' => isset( $row['ig_image'] ) ? absint( $row['ig_image'] ) : 0,
				);
			}
		}
		return $out;
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['cvkp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cvkp_nonce'] ), 'cvkp_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw  = isset( $_POST['cvkp'] ) && is_array( $_POST['cvkp'] ) ? wp_unslash( $_POST['cvkp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- tvättas i from_request().
		$data = self::from_request( $raw );
		update_post_meta( $post_id, self::META_DATA, $data );

		$notices = array();
		if ( ! empty( $_POST['cvkp_schedule'] ) || ! empty( $_POST['cvkp_cancel'] ) ) {
			$notices = self::run_social_action( $post_id, $data );
		}
		if ( $notices ) {
			set_transient( 'cvkp_notices_' . get_current_user_id(), $notices, 120 );
		}
		if ( empty( $_POST['cvkp_run'] ) ) {
			return;
		}
		$notices = self::run( $post_id, $data, html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) );
		set_transient( 'cvkp_notices_' . get_current_user_id(), $notices, 120 );
	}

	/** Schemalägg eller avbryt Facebook/Instagram. Det här är godkännandet. */
	private static function run_social_action( $post_id, array $data ) {
		// phpcs:disable WordPress.Security.NonceVerification -- nonce kontrollerad i save().
		if ( ! empty( $_POST['cvkp_cancel'] ) ) {
			$channel = sanitize_key( $_POST['cvkp_cancel'] );
			return in_array( $channel, array( 'facebook', 'instagram' ), true ) ? array( CVKP_Social::cancel( $post_id, $channel ) ) : array();
		}
		$channel = sanitize_key( $_POST['cvkp_schedule'] );
		if ( ! in_array( $channel, array( 'facebook', 'instagram' ), true ) ) {
			return array();
		}
		$when = isset( $_POST['cvkp_when'][ $channel ] ) ? sanitize_text_field( wp_unslash( $_POST['cvkp_when'][ $channel ] ) ) : '';
		// phpcs:enable

		// Utkastet byggs om av det som står i fälten just nu, så att det som godkänns är det som syns.
		$data     = CVKP_Social::fill_suggestions( $post_id, $data, array( $channel ) );
		$prepared = 'facebook' === $channel ? CVKP_Social::prepare_facebook( $post_id, $data ) : CVKP_Social::prepare_instagram( $post_id, $data );
		$result   = CVKP_Social::schedule( $post_id, $channel, CVKP_Social::parse_time( $when ) );
		return 'success' === $result['type'] ? array( $result ) : array( $result, $prepared );
	}

	/**
	 * Skapar eller uppdaterar utkasten i de valda kanalerna.
	 *
	 * @return array Lista med array( 'type' => success|error|warning, 'message' => '' ).
	 */
	public static function run( $post_id, array $data, $title ) {
		$notices = array();
		if ( ! $data['channels'] ) {
			return array( array( 'type' => 'warning', 'message' => 'Ingen kanal vald – utskicket sparades men inga utkast skapades.' ) );
		}
		if ( '' === trim( $data['heading'] ) ) {
			return array( array( 'type' => 'error', 'message' => 'Fyll i en rubrik innan utkasten skapas.' ) );
		}

		if ( in_array( 'blog', $data['channels'], true ) ) {
			$notices[] = self::run_blog( $post_id, $data );
		}

		$social = array_values( array_intersect( array( 'facebook', 'instagram' ), $data['channels'] ) );
		if ( $social ) {
			$data = CVKP_Social::fill_suggestions( $post_id, $data, $social );
			if ( in_array( 'facebook', $social, true ) ) {
				$notices[] = CVKP_Social::prepare_facebook( $post_id, $data );
			}
			if ( in_array( 'instagram', $social, true ) ) {
				$notices[] = CVKP_Social::prepare_instagram( $post_id, $data );
				$hint      = CVKP_Social::hashtag_hint( $data['ig_text'] );
				if ( $hint ) {
					$notices[] = array( 'type' => 'warning', 'message' => 'Instagram: ' . $hint );
				}
			}
		}

		if ( in_array( 'mailchimp', $data['channels'], true ) ) {
			if ( '' === trim( $data['subject'] ) ) {
				$notices[] = array( 'type' => 'error', 'message' => 'Mailchimp: fyll i en ämnesrad.' );
			} else {
				$mc       = new CVKP_Mailchimp();
				$existing = get_post_meta( $post_id, self::META_MC, true );
				$result   = $mc->save_draft(
					array(
						'subject_line' => $data['subject'],
						'preview_text' => $data['preheader'],
						'title'        => '' !== trim( $title ) ? $title : $data['heading'],
					),
					CVKP_Email_Renderer::render( $data ),
					is_array( $existing ) && ! empty( $existing['id'] ) ? $existing['id'] : null
				);
				if ( is_wp_error( $result ) ) {
					$notices[] = array( 'type' => 'error', 'message' => 'Mailchimp: ' . $result->get_error_message() );
				} else {
					update_post_meta( $post_id, self::META_MC, array_merge( $result, array( 'time' => time() ) ) );
					$notices[] = array(
						'type'    => 'success',
						'message' => sprintf( 'Mailchimp: utkastet är %s. Öppna det i Mailchimp, skicka ett test och skicka sedan som vanligt.', $result['created'] ? 'skapat' : 'uppdaterat' ),
						'link'    => $result['url'],
						'linkText' => 'Öppna i Mailchimp',
					);
				}
			}
		}
		return $notices;
	}

	private static function run_blog( $post_id, array $data ) {
		$blog_id = (int) get_post_meta( $post_id, self::META_BLOG, true );
		$current = $blog_id ? get_post( $blog_id ) : null;

		if ( $current && 'trash' !== $current->post_status && 'publish' === $current->post_status ) {
			return array(
				'type'     => 'warning',
				'message'  => 'Blogg: inlägget är redan publicerat och ändrades inte. Gör ändringar direkt i inlägget.',
				'link'     => get_edit_post_link( $blog_id, 'raw' ),
				'linkText' => 'Öppna inlägget',
			);
		}

		$postarr = array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => '' !== trim( $data['blog_title'] ) ? $data['blog_title'] : $data['heading'],
			'post_content' => CVKP_Blog_Renderer::render( $data ),
			'post_excerpt' => $data['blog_excerpt'],
		);
		$category = (int) CVKP_Settings::get( 'blog_category' );
		if ( $category ) {
			$postarr['post_category'] = array( $category );
		}
		if ( $current && 'trash' !== $current->post_status ) {
			$postarr['ID'] = $blog_id;
			unset( $postarr['post_status'] );
		} else {
			// Adressen sätts direkt, så att länken blir rätt även om inlägget publiceras via schemaläggning.
			$postarr['post_name'] = sanitize_title( $postarr['post_title'] );
		}

		$new_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $new_id ) ) {
			return array( 'type' => 'error', 'message' => 'Blogg: ' . $new_id->get_error_message() );
		}
		if ( ! isset( $postarr['ID'] ) ) {
			CVKP_Wpml::set_default_language( $new_id, 'post' );
		}
		update_post_meta( $new_id, self::META_SOURCE, $post_id );
		update_post_meta( $post_id, self::META_BLOG, $new_id );

		foreach ( $data['trips'] as $row ) {
			$thumb = (int) get_post_thumbnail_id( (int) $row['id'] );
			if ( $thumb ) {
				set_post_thumbnail( $new_id, $thumb );
				break;
			}
		}

		return array(
			'type'     => 'success',
			'message'  => sprintf( 'Blogg: utkastet "%s" är %s.', html_entity_decode( get_the_title( $new_id ), ENT_QUOTES, 'UTF-8' ), isset( $postarr['ID'] ) ? 'uppdaterat' : 'skapat' ),
			'link'     => get_edit_post_link( $new_id, 'raw' ),
			'linkText' => 'Granska och publicera',
		);
	}

	public static function notices() {
		$key     = 'cvkp_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( ! $notices ) {
			return;
		}
		delete_transient( $key );
		foreach ( $notices as $n ) {
			// Länkarna leder till Mailchimp eller blogginlägget – alltid i ny flik, så utskicket är kvar.
			$link = ! empty( $n['link'] ) ? sprintf( ' <a href="%s" target="_blank" rel="noopener">%s ↗</a>', esc_url( $n['link'] ), esc_html( $n['linkText'] ) ) : '';
			printf( '<div class="notice notice-%s is-dismissible"><p>%s%s</p></div>', esc_attr( $n['type'] ), esc_html( $n['message'] ), $link ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Förhandsgranskning av nyhetsbrevet. Knappen skickar formuläret hit (osparade ändringar
	 * syns direkt); en vanlig länk visar den sparade versionen.
	 */
	public static function maybe_preview() {
		if ( empty( $_GET['cvkp_preview'] ) ) {
			return;
		}
		$post_id = absint( $_GET['cvkp_preview'] );
		$nonce   = isset( $_GET['cvkp_pnonce'] ) ? sanitize_key( $_GET['cvkp_pnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cvkp_preview_' . $post_id ) ) {
			wp_die( 'Länken har gått ut. Ladda om sidan och försök igen.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		$data = isset( $_POST['cvkp'] ) && is_array( $_POST['cvkp'] )
			? self::from_request( wp_unslash( $_POST['cvkp'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			: self::get_data( $post_id );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo CVKP_Email_Renderer::render( $data ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
