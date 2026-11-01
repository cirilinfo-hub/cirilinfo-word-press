<?php
/**
 * Central settings store + admin screen.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION = 'cirilinfo_settings';

	/** @var array|null */
	private static $cache = null;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_ci_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_ci_test_email', array( __CLASS__, 'handle_test_email' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
	}

	/* ------------------------------------------------------------------ Data */

	public static function flush_cache(): void {
		self::$cache = null;
	}

	/** Field schema, grouped by tab. type: text,email,url,textarea,number,checkbox,select,password,code */
	public static function schema(): array {
		return array(
			'general'  => array(
				'label'  => __( 'Company', 'cirilinfo' ),
				'fields' => array(
					'company_name' => array( 'Company name', 'text' ),
					'legal_name'   => array( 'Legal name (invoices)', 'text' ),
					'tagline'      => array( 'Tagline', 'text' ),
					'email'        => array( 'Business email', 'email', 'Shown on the site and used as reply-to. Falls back to the WordPress admin email.' ),
					'notify_email' => array( 'Admin notification email', 'email', 'Receives enquiries, orders, tickets. Falls back to the business email.' ),
					'phone'        => array( 'Phone', 'text' ),
					'whatsapp'     => array( 'WhatsApp number', 'text', 'International format, digits only, e.g. 919876543210.' ),
					'address'      => array( 'Address', 'textarea' ),
					'city'         => array( 'City / Location', 'text' ),
					'state_code'   => array( 'Business state code', 'text', 'Two-letter Indian state code (e.g. KL, MH, KA). Used to split GST into CGST+SGST vs IGST on invoices.' ),
					'gstin'        => array( 'GSTIN', 'text' ),
					'map_embed'    => array( 'Google Maps embed URL', 'url', 'The src of the Google Maps "Embed a map" iframe.' ),
					'social_facebook'  => array( 'Facebook URL', 'url' ),
					'social_instagram' => array( 'Instagram URL', 'url' ),
					'social_linkedin'  => array( 'LinkedIn URL', 'url' ),
					'social_x'         => array( 'X / Twitter URL', 'url' ),
					'social_youtube'   => array( 'YouTube URL', 'url' ),
				),
			),
			'commerce' => array(
				'label'  => __( 'GST & Invoices', 'cirilinfo' ),
				'fields' => array(
					'gst_enabled'    => array( 'Charge GST', 'checkbox' ),
					'gst_rate'       => array( 'GST rate (%)', 'number', 'Synced to the WooCommerce tax table on save. Default 18% for IT services.' ),
					'gst_scope'      => array( 'Apply GST to', 'select', '', array( 'india' => 'Customers in India only (exports zero-rated)', 'all' => 'All customers' ) ),
					'invoice_prefix' => array( 'Invoice number prefix', 'text' ),
					'invoice_terms'  => array( 'Invoice footer / terms', 'textarea' ),
					'bank_details'   => array( 'Bank transfer details (NEFT/IMPS)', 'textarea', 'Shown on invoices and in the bank transfer payment instructions.' ),
				),
			),
			'email'    => array(
				'label'  => __( 'Email & SMTP', 'cirilinfo' ),
				'fields' => array(
					'smtp_enabled' => array( 'Use SMTP', 'checkbox' ),
					'smtp_host'    => array( 'SMTP host', 'text' ),
					'smtp_port'    => array( 'SMTP port', 'number' ),
					'smtp_secure'  => array( 'Encryption', 'select', '', array( 'tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', '' => 'None' ) ),
					'smtp_user'    => array( 'SMTP username', 'text' ),
					'smtp_pass'    => array( 'SMTP password', 'password', 'Stored encrypted. Better: define CIRILINFO_SMTP_PASS in wp-config.php and leave this blank.' ),
					'from_email'   => array( 'From email', 'email' ),
					'from_name'    => array( 'From name', 'text' ),
				),
			),
			'tracking' => array(
				'label'  => __( 'Analytics & Chat', 'cirilinfo' ),
				'fields' => array(
					'ga4_id'           => array( 'Google Analytics 4 ID', 'text', 'G-XXXXXXXXXX' ),
					'gsc_verification' => array( 'Search Console verification token', 'text', 'The content value of the google-site-verification meta tag.' ),
					'pixel_id'         => array( 'Meta Pixel ID', 'text' ),
					'consent_required' => array( 'Load tracking only after cookie consent', 'checkbox' ),
					'chat_enabled'     => array( 'Enable live chat widget', 'checkbox' ),
					'chat_script'      => array( 'Live chat embed code', 'code', 'Paste the snippet from Tawk.to, Crisp, Tidio etc. Output on every public page. Administrators only.' ),
				),
			),
			'seo'      => array(
				'label'  => __( 'SEO', 'cirilinfo' ),
				'fields' => array(
					'seo_default_desc' => array( 'Default meta description', 'textarea' ),
					'seo_og_image'     => array( 'Default social share image URL', 'url' ),
				),
			),
		);
	}

	public static function defaults(): array {
		return array(
			'company_name'     => 'CirilInfo',
			'legal_name'       => 'CirilInfo',
			'tagline'          => 'Your Digital Growth Partner',
			'state_code'       => 'KL',
			'gst_enabled'      => 1,
			'gst_rate'         => '18',
			'gst_scope'        => 'india',
			'invoice_prefix'   => 'INV',
			'invoice_terms'    => 'Services are delivered as per the agreed scope. This is a computer-generated invoice.',
			'smtp_port'        => 587,
			'smtp_secure'      => 'tls',
			'consent_required' => 1,
			'seo_default_desc' => 'CirilInfo helps businesses establish, grow and strengthen their online presence through innovative web solutions and result-oriented digital marketing services.',
			'templates'        => array(),
		);
	}

	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( string $key, $default = '' ) {
		$all = self::all();
		return ( isset( $all[ $key ] ) && '' !== $all[ $key ] ) ? $all[ $key ] : $default;
	}

	public static function business_email(): string {
		return (string) self::get( 'email', get_option( 'admin_email' ) );
	}

	public static function notify_email(): string {
		return (string) self::get( 'notify_email', self::business_email() );
	}

	public static function smtp_password(): string {
		if ( defined( 'CIRILINFO_SMTP_PASS' ) && CIRILINFO_SMTP_PASS ) {
			return (string) CIRILINFO_SMTP_PASS;
		}
		return self::decrypt( (string) self::get( 'smtp_pass', '' ) );
	}

	/* ---------------------------------------------------------------- Crypto */

	private static function key(): string {
		$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ) . wp_salt( 'auth' );
		return hash( 'sha256', $salt, true );
	}

	public static function encrypt( string $plain ): string {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $cipher ? '' : 'enc:' . base64_encode( $iv . $cipher );
	}

	public static function decrypt( string $stored ): string {
		if ( 0 !== strpos( $stored, 'enc:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $stored;
		}
		$raw = base64_decode( substr( $stored, 4 ), true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		return false === $plain ? '' : $plain;
	}

	/* ----------------------------------------------------------------- Admin */

	public static function menu(): void {
		add_submenu_page( 'cirilinfo', __( 'Settings', 'cirilinfo' ), __( 'Settings', 'cirilinfo' ), 'manage_options', 'cirilinfo-settings', array( __CLASS__, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'cirilinfo' ) );
		}
		$schema = self::schema();
		$tabs   = array_keys( $schema ) + array();
		$tabs[] = 'templates';
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $tab, $tabs, true ) ) {
			$tab = 'general';
		}
		$values = self::all();

		echo '<div class="wrap ci-admin"><h1>' . esc_html__( 'CirilInfo Settings', 'cirilinfo' ) . '</h1>';
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cirilinfo' ) . '</p></div>';
		}
		if ( isset( $_GET['mailtest'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$ok = '1' === $_GET['mailtest']; // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $ok ? __( 'Test email sent. Check your inbox.', 'cirilinfo' ) : __( 'Test email failed. Check SMTP settings.', 'cirilinfo' ) ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $schema as $slug => $def ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', esc_attr( $slug === $tab ? 'nav-tab-active' : '' ), esc_url( admin_url( 'admin.php?page=cirilinfo-settings&tab=' . $slug ) ), esc_html( $def['label'] ) );
		}
		printf( '<a class="nav-tab %s" href="%s">%s</a>', esc_attr( 'templates' === $tab ? 'nav-tab-active' : '' ), esc_url( admin_url( 'admin.php?page=cirilinfo-settings&tab=templates' ) ), esc_html__( 'Email templates', 'cirilinfo' ) );
		echo '</nav>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:900px;margin-top:16px">';
		wp_nonce_field( 'ci_save_settings' );
		echo '<input type="hidden" name="action" value="ci_save_settings"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';

		if ( 'templates' === $tab ) {
			self::render_templates( $values );
		} else {
			echo '<table class="form-table" role="presentation">';
			foreach ( $schema[ $tab ]['fields'] as $key => $f ) {
				self::render_field( $key, $f, $values );
			}
			echo '</table>';
		}
		submit_button();
		echo '</form>';

		if ( 'email' === $tab ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'ci_test_email' );
			echo '<input type="hidden" name="action" value="ci_test_email"><p><button class="button">' . esc_html__( 'Send test email to me', 'cirilinfo' ) . '</button></p></form>';
		}
		echo '</div>';
	}

	private static function render_field( string $key, array $f, array $values ): void {
		list( $label, $type ) = $f;
		$help    = $f[2] ?? '';
		$options = $f[3] ?? array();
		$val     = $values[ $key ] ?? '';
		$name    = 'ci[' . $key . ']';
		echo '<tr><th scope="row"><label for="ci_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" id="ci_%1$s" name="%2$s" value="1" %3$s> %4$s</label>', esc_attr( $key ), esc_attr( $name ), checked( ! empty( $val ), true, false ), esc_html__( 'Enabled', 'cirilinfo' ) );
				break;
			case 'textarea':
				printf( '<textarea class="large-text" rows="3" id="ci_%1$s" name="%2$s">%3$s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( (string) $val ) );
				break;
			case 'code':
				printf( '<textarea class="large-text code" rows="6" id="ci_%1$s" name="%2$s" %4$s>%3$s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( (string) $val ), current_user_can( 'unfiltered_html' ) ? '' : 'readonly' );
				break;
			case 'select':
				printf( '<select id="ci_%1$s" name="%2$s">', esc_attr( $key ), esc_attr( $name ) );
				foreach ( $options as $ov => $ol ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( (string) $ov ), selected( (string) $val, (string) $ov, false ), esc_html( $ol ) );
				}
				echo '</select>';
				break;
			case 'password':
				printf( '<input type="password" class="regular-text" id="ci_%1$s" name="%2$s" value="" autocomplete="new-password" placeholder="%3$s">', esc_attr( $key ), esc_attr( $name ), esc_attr( $val ? '•••••••• (saved)' : '' ) );
				break;
			default:
				printf( '<input type="%1$s" class="regular-text" id="ci_%2$s" name="%3$s" value="%4$s">', esc_attr( 'number' === $type ? 'number' : $type ), esc_attr( $key ), esc_attr( $name ), esc_attr( (string) $val ) );
		}
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function render_templates( array $values ): void {
		echo '<p>' . esc_html__( 'Customise the emails sent by CirilInfo. Placeholders in {braces} are replaced automatically. WooCommerce order emails (new order, processing, completed, refunds, password reset) are customised under WooCommerce → Settings → Emails.', 'cirilinfo' ) . '</p>';
		foreach ( Mailer::template_defs() as $key => $def ) {
			$saved = $values['templates'][ $key ] ?? array();
			$subj  = $saved['subject'] ?? $def['subject'];
			$body  = $saved['body'] ?? $def['body'];
			echo '<div class="postbox" style="padding:12px 16px;margin-bottom:12px"><h3 style="margin-top:0">' . esc_html( $def['label'] ) . ' <small style="font-weight:400;color:#666">(' . esc_html( $def['audience'] ) . ')</small></h3>';
			printf( '<p><input type="text" class="large-text" name="ci_tpl[%s][subject]" value="%s"></p>', esc_attr( $key ), esc_attr( $subj ) );
			printf( '<p><textarea class="large-text" rows="6" name="ci_tpl[%s][body]">%s</textarea></p>', esc_attr( $key ), esc_textarea( $body ) );
			echo '<p class="description">' . esc_html__( 'Available:', 'cirilinfo' ) . ' <code>' . esc_html( implode( ' ', array_map( static fn( $p ) => '{' . $p . '}', $def['vars'] ) ) ) . '</code></p></div>';
		}
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ), 403 );
		}
		check_admin_referer( 'ci_save_settings' );

		$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		$schema = self::schema();
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		if ( 'templates' === $tab ) {
			$in = isset( $_POST['ci_tpl'] ) && is_array( $_POST['ci_tpl'] ) ? wp_unslash( $_POST['ci_tpl'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			foreach ( Mailer::template_defs() as $key => $def ) {
				if ( isset( $in[ $key ] ) && is_array( $in[ $key ] ) ) {
					$stored['templates'][ $key ] = array(
						'subject' => sanitize_text_field( $in[ $key ]['subject'] ?? '' ),
						'body'    => wp_kses_post( $in[ $key ]['body'] ?? '' ),
					);
				}
			}
		} elseif ( isset( $schema[ $tab ] ) ) {
			$in = isset( $_POST['ci'] ) && is_array( $_POST['ci'] ) ? wp_unslash( $_POST['ci'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			foreach ( $schema[ $tab ]['fields'] as $key => $f ) {
				$type = $f[1];
				$raw  = $in[ $key ] ?? '';
				$raw  = is_array( $raw ) ? '' : (string) $raw;
				switch ( $type ) {
					case 'checkbox':
						$stored[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
						break;
					case 'email':
						$stored[ $key ] = sanitize_email( $raw );
						break;
					case 'url':
						$stored[ $key ] = esc_url_raw( $raw );
						break;
					case 'number':
						$stored[ $key ] = is_numeric( $raw ) ? 0 + $raw : '';
						break;
					case 'textarea':
						$stored[ $key ] = sanitize_textarea_field( $raw );
						break;
					case 'select':
						$stored[ $key ] = array_key_exists( $raw, $f[3] ) ? $raw : (string) array_key_first( $f[3] );
						break;
					case 'password':
						if ( '' !== $raw ) {
							$stored[ $key ] = self::encrypt( $raw );
						}
						break;
					case 'code':
						if ( current_user_can( 'unfiltered_html' ) ) {
							$stored[ $key ] = $raw;
						}
						break;
					default:
						$stored[ $key ] = sanitize_text_field( $raw );
				}
			}
			if ( 'commerce' === $tab ) {
				$rate = (float) ( $stored['gst_rate'] ?? 18 );
				$stored['gst_rate'] = (string) max( 0, min( 40, $rate ) );
			}
			if ( 'general' === $tab && ! empty( $stored['state_code'] ) ) {
				$stored['state_code'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $stored['state_code'] ), 0, 3 ) );
			}
		}

		update_option( self::OPTION, $stored );
		self::flush_cache();

		if ( 'commerce' === $tab && class_exists( 'WooCommerce' ) ) {
			Checkout::sync_gst();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=cirilinfo-settings&tab=' . $tab . '&saved=1' ) );
		exit;
	}

	public static function handle_test_email(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ), 403 );
		}
		check_admin_referer( 'ci_test_email' );
		$user = wp_get_current_user();
		$ok   = Mailer::send_raw( $user->user_email, 'CirilInfo test email', '<p>If you can read this, your email configuration works.</p>' );
		wp_safe_redirect( admin_url( 'admin.php?page=cirilinfo-settings&tab=email&mailtest=' . ( $ok ? '1' : '0' ) ) );
		exit;
	}
}
