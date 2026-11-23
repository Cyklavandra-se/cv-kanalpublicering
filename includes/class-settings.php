<?php
/**
 * Inställningar: Mailchimp, avsändare, standardtexter och behörighet.
 */

defined( 'ABSPATH' ) || exit;

class CVKP_Settings {

	const OPTION = 'cvkp_settings';

	/** Behörighet för att använda verktyget (Redaktör och Administratör). */
	const CAPABILITY = 'edit_others_posts';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_cvkp_test_mailchimp', array( __CLASS__, 'test_mailchimp' ) );
	}

	public static function defaults() {
		return array(
			'mailchimp_api_key' => '',
			'mailchimp_list_id' => 'aacb39e5d3',
			'from_name'         => 'Cykla & Vandra',
			'reply_to'          => 'nyhetsbrev@cyklavandra.se',
			'logo_url'          => 'https://mcusercontent.com/53aa8a6b777985242e4edb03b/images/5de13cd2-4e87-64e5-70b5-28b6d39c3919.png',
			'company_name'      => 'Cyklavandra Sverige AB',
			'address'           => "Kvarnvägen 3A\nUmeå 903 20\nSweden",
			'greeting'          => 'Soliga hälsningar från Anders, Ingrid, Sarah och Pär',
			'ps'                => 'PS! Följ oss också gärna på <a href="https://www.facebook.com/cyklavandra/">Facebook</a> och <a href="https://www.instagram.com/cyklavandra.se/">Instagram</a>!',
			'button_text'       => 'Läs mer på vår hemsida',
			'button_url'        => 'https://cyklavandra.se/',
			'facebook_url'      => 'https://www.facebook.com/cyklavandra/',
			'instagram_url'     => 'https://www.instagram.com/cyklavandra.se/',
			'blog_category'     => 0,
			'latest_slug'       => 'aktuellt',
		);
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		return null === $key ? $opts : ( isset( $opts[ $key ] ) ? $opts[ $key ] : null );
	}

	/** Nyckeln läses helst från wp-config.php (define( 'CVKP_MAILCHIMP_API_KEY', '...' );). */
	public static function mailchimp_api_key() {
		if ( defined( 'CVKP_MAILCHIMP_API_KEY' ) && CVKP_MAILCHIMP_API_KEY ) {
			return (string) CVKP_MAILCHIMP_API_KEY;
		}
		return (string) self::get( 'mailchimp_api_key' );
	}

	public static function menu() {
		add_options_page(
			'Kanalpublicering',
			'Kanalpublicering',
			'manage_options',
			'cvkp-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			'cvkp_settings_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	public static function sanitize( $input ) {
		$old = self::get();
		$out = self::defaults();
		foreach ( $out as $key => $default ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : $default;
			switch ( $key ) {
				case 'mailchimp_api_key':
					// Tomt fält = behåll sparad nyckel.
					$value = trim( (string) $value );
					$out[ $key ] = '' === $value ? $old['mailchimp_api_key'] : sanitize_text_field( $value );
					break;
				case 'reply_to':
					$out[ $key ] = sanitize_email( $value );
					break;
				case 'logo_url':
				case 'button_url':
				case 'facebook_url':
				case 'instagram_url':
					$out[ $key ] = esc_url_raw( $value );
					break;
				case 'address':
					$out[ $key ] = sanitize_textarea_field( $value );
					break;
				case 'ps':
				case 'greeting':
					$out[ $key ] = wp_kses_post( $value );
					break;
				case 'blog_category':
					$out[ $key ] = absint( $value );
					break;
				case 'latest_slug':
					$out[ $key ] = sanitize_title( $value );
					break;
				default:
					$out[ $key ] = sanitize_text_field( $value );
			}
		}
		if ( ! empty( $input['clear_api_key'] ) ) {
			$out['mailchimp_api_key'] = '';
		}
		return $out;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o          = self::get();
		$from_const = defined( 'CVKP_MAILCHIMP_API_KEY' ) && CVKP_MAILCHIMP_API_KEY;
		$has_key    = '' !== self::mailchimp_api_key();
		$name       = self::OPTION;
		?>
		<div class="wrap">
			<h1>Kanalpublicering – inställningar</h1>
			<?php
			$test = get_transient( 'cvkp_test_' . get_current_user_id() );
			if ( $test ) {
				delete_transient( 'cvkp_test_' . get_current_user_id() );
				printf(
					'<div class="notice notice-%s"><p>%s</p></div>',
					esc_attr( $test['ok'] ? 'success' : 'error' ),
					esc_html( $test['message'] )
				);
			}
			?>
			<form method="post" action="options.php">
				<?php settings_fields( 'cvkp_settings_group' ); ?>

				<h2>Mailchimp</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cvkp-key">API-nyckel</label></th>
						<td>
							<?php if ( $from_const ) : ?>
								<p><strong>Nyckeln är satt i wp-config.php.</strong></p>
							<?php else : ?>
								<input type="password" id="cvkp-key" class="regular-text" autocomplete="off"
									name="<?php echo esc_attr( $name ); ?>[mailchimp_api_key]" value=""
									placeholder="<?php echo esc_attr( $has_key ? '•••••••• (sparad – lämna tomt för att behålla)' : 'xxxxxxxx-us1' ); ?>">
								<?php if ( $has_key ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[clear_api_key]" value="1"> Ta bort sparad nyckel</label>
								<?php endif; ?>
								<p class="description">Säkrast är att lägga nyckeln i wp-config.php: <code>define( 'CVKP_MAILCHIMP_API_KEY', '...' );</code></p>
							<?php endif; ?>
						</td>
					</tr>
					<?php
					self::text_row( 'mailchimp_list_id', 'Audience-ID (lista)', $o, 'Hittas i Mailchimp under Audience → Settings → Audience name and defaults.' );
					self::text_row( 'from_name', 'Avsändarnamn', $o );
					self::text_row( 'reply_to', 'Avsändaradress', $o, 'Måste vara verifierad i Mailchimp.' );
					?>
				</table>

				<h2>Nyhetsbrevets utseende</h2>
				<table class="form-table" role="presentation">
					<?php
					self::text_row( 'logo_url', 'Logga (URL)', $o );
					self::text_row( 'company_name', 'Företagsnamn i footern', $o );
					self::textarea_row( 'address', 'Adress i footern', $o, 'En rad per rad i adressen.' );
					self::textarea_row( 'greeting', 'Standardhälsning', $o );
					self::textarea_row( 'ps', 'Standard-PS', $o, 'HTML-länkar är tillåtna.' );
					self::text_row( 'button_text', 'Knapptext längst ner', $o );
					self::text_row( 'button_url', 'Knapplänk', $o );
					self::text_row( 'facebook_url', 'Facebook-sida', $o );
					self::text_row( 'instagram_url', 'Instagram-konto', $o );
					?>
				</table>

				<h2>Blogg</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cvkp-cat">Kategori för blogginlägg</label></th>
						<td>
							<?php
							wp_dropdown_categories(
								array(
									'name'             => $name . '[blog_category]',
									'id'               => 'cvkp-cat',
									'selected'         => (int) $o['blog_category'],
									'show_option_none' => '— Standardkategori —',
									'option_none_value' => 0,
									'hide_empty'       => false,
								)
							);
							?>
						</td>
					</tr>
					<?php self::text_row( 'latest_slug', 'Fast adress till senaste inlägget', $o, 'Till exempel "aktuellt" ger ' . home_url( '/aktuellt' ) . ' – lägg den i Linktree.' ); ?>
				</table>

				<?php submit_button( 'Spara inställningar' ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cvkp_test_mailchimp">
				<?php wp_nonce_field( 'cvkp_test_mailchimp' ); ?>
				<?php submit_button( 'Testa anslutningen till Mailchimp', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	private static function text_row( $key, $label, $o, $help = '' ) {
		$id = 'cvkp-' . $key;
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="%1$s" name="%3$s[%4$s]" value="%5$s">%6$s</td></tr>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( $o[ $key ] ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);
	}

	private static function textarea_row( $key, $label, $o, $help = '' ) {
		$id = 'cvkp-' . $key;
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><textarea class="large-text" rows="3" id="%1$s" name="%3$s[%4$s]">%5$s</textarea>%6$s</td></tr>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_textarea( $o[ $key ] ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);
	}

	public static function test_mailchimp() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		check_admin_referer( 'cvkp_test_mailchimp' );

		$mc     = new CVKP_Mailchimp();
		$result = $mc->test();
		set_transient(
			'cvkp_test_' . get_current_user_id(),
			is_wp_error( $result )
				? array( 'ok' => false, 'message' => 'Mailchimp svarade inte som väntat: ' . $result->get_error_message() )
				: array( 'ok' => true, 'message' => $result ),
			60
		);
		wp_safe_redirect( admin_url( 'options-general.php?page=cvkp-settings' ) );
		exit;
	}
}
