<?php
/**
 * Public & customer form handling (enquiries, quotes, tickets, requirements, feedback, newsletter).
 * Every submission: nonce + honeypot + time trap + rate limit + sanitisation + secure uploads.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Forms {

	const PUBLIC_TYPES = array( 'contact', 'quote', 'feedback', 'newsletter' );
	const AUTH_TYPES   = array( 'ticket', 'ticket_reply', 'project_message', 'requirements' );

	public static function init(): void {
		add_action( 'admin_post_nopriv_ci_form', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_ci_form', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_ci_nonce', array( __CLASS__, 'ajax_nonce' ) );
		add_action( 'wp_ajax_ci_nonce', array( __CLASS__, 'ajax_nonce' ) );
	}

	/** Fresh nonce for cached pages (JS calls this on first interaction). */
	public static function ajax_nonce(): void {
		$type = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		nocache_headers();
		if ( 'cart' === $type ) {
			wp_send_json_success( array( 'nonce' => wp_create_nonce( 'ci_add_to_cart' ) ) );
		}
		if ( ! in_array( $type, array_merge( self::PUBLIC_TYPES, self::AUTH_TYPES ), true ) ) {
			wp_send_json_error();
		}
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'ci_form_' . $type ) ) );
	}

	/* ------------------------------------------------------------ Messages */

	private static function messages(): array {
		return array(
			'nonce'    => __( 'Your session expired. Please reload the page and try again.', 'cirilinfo' ),
			'spam'     => __( 'We could not verify this submission. Please try again.', 'cirilinfo' ),
			'rate'     => __( 'Too many attempts. Please wait a few minutes and try again.', 'cirilinfo' ),
			'name'     => __( 'Please enter your name.', 'cirilinfo' ),
			'email'    => __( 'Please enter a valid email address.', 'cirilinfo' ),
			'phone'    => __( 'Please enter a valid phone number.', 'cirilinfo' ),
			'message'  => __( 'Please enter a message (at least 10 characters).', 'cirilinfo' ),
			'subject'  => __( 'Please enter a subject.', 'cirilinfo' ),
			'rating'   => __( 'Please choose a rating.', 'cirilinfo' ),
			'login'    => __( 'Please log in to continue.', 'cirilinfo' ),
			'perm'     => __( 'You do not have access to that item.', 'cirilinfo' ),
			'upload'   => __( 'One of your files was rejected (type or size). Allowed: images, PDF, Office documents, ZIP up to 10 MB.', 'cirilinfo' ),
			'fail'     => __( 'Something went wrong. Please try again or contact us directly.', 'cirilinfo' ),
		);
	}

	private static function success_messages(): array {
		return array(
			'contact'         => __( 'Thank you! We have received your message and will reply within one business day.', 'cirilinfo' ),
			'quote'           => __( 'Thank you! Your quotation request is in. We will send you a detailed quote shortly.', 'cirilinfo' ),
			'ticket'          => __( 'Your support ticket has been created.', 'cirilinfo' ),
			'ticket_reply'    => __( 'Your reply has been sent.', 'cirilinfo' ),
			'project_message' => __( 'Your message has been sent to the project team.', 'cirilinfo' ),
			'requirements'    => __( 'Requirements received. Our team will review them shortly.', 'cirilinfo' ),
			'feedback'        => __( 'Thank you for your feedback! It will appear on the site after review.', 'cirilinfo' ),
			'newsletter'      => __( 'Almost done! Check your inbox to confirm your subscription.', 'cirilinfo' ),
		);
	}

	/** Render success/error notice for a form on the current request. */
	public static function notice( string $type ): string {
		$sent = isset( $_GET['ci_sent'] ) ? sanitize_key( wp_unslash( $_GET['ci_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$err  = isset( $_GET['ci_err'] ) ? sanitize_key( wp_unslash( $_GET['ci_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$for  = isset( $_GET['ci_form'] ) ? sanitize_key( wp_unslash( $_GET['ci_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $for !== $type ) {
			return '';
		}
		if ( $sent ) {
			return '<div class="ci-notice ci-notice--ok" role="status">' . esc_html( self::success_messages()[ $type ] ?? __( 'Done.', 'cirilinfo' ) ) . '</div>';
		}
		if ( $err ) {
			return '<div class="ci-notice ci-notice--err" role="alert">' . esc_html( self::messages()[ $err ] ?? self::messages()['fail'] ) . '</div>';
		}
		return '';
	}

	/* ----------------------------------------------------------- Rendering */

	private static function open( string $type, array $extra = array(), bool $multipart = false ): string {
		global $wp;
		$here = home_url( '/' . ltrim( (string) ( $wp->request ?? '' ), '/' ) );
		$out  = '<form class="ci-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ci-form="' . esc_attr( $type ) . '"' . ( $multipart ? ' enctype="multipart/form-data"' : '' ) . ' novalidate>';
		$out .= '<input type="hidden" name="action" value="ci_form"><input type="hidden" name="ci_form" value="' . esc_attr( $type ) . '">';
		$out .= '<input type="hidden" name="_ci_nonce" value="' . esc_attr( wp_create_nonce( 'ci_form_' . $type ) ) . '">';
		$out .= '<input type="hidden" name="ci_ts" value="' . esc_attr( (string) time() ) . '">';
		$out .= '<input type="hidden" name="ci_back" value="' . esc_url( $here ) . '">';
		$out .= '<div class="ci-hp" aria-hidden="true"><label>Leave this empty<input type="text" name="ci_website_url" tabindex="-1" autocomplete="off"></label></div>';
		foreach ( $extra as $k => $v ) {
			$out .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '">';
		}
		return $out;
	}

	private static function field( string $name, string $label, string $type = 'text', array $o = array() ): string {
		$id  = 'ci-' . $name . '-' . wp_unique_id();
		$req = ! empty( $o['required'] );
		$val = $o['value'] ?? '';
		$out = '<div class="ci-field' . ( ! empty( $o['class'] ) ? ' ' . esc_attr( $o['class'] ) : '' ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $req ? ' <span class="ci-req" aria-hidden="true">*</span>' : '' ) . '</label>';
		$common = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( $req ? ' required' : '' ) . ( ! empty( $o['placeholder'] ) ? ' placeholder="' . esc_attr( $o['placeholder'] ) . '"' : '' ) . ( ! empty( $o['autocomplete'] ) ? ' autocomplete="' . esc_attr( $o['autocomplete'] ) . '"' : '' );
		if ( 'textarea' === $type ) {
			$out .= '<textarea rows="' . (int) ( $o['rows'] ?? 5 ) . '"' . $common . '>' . esc_textarea( (string) $val ) . '</textarea>';
		} elseif ( 'select' === $type ) {
			$out .= '<select' . $common . '>';
			foreach ( $o['options'] as $ov => $ol ) {
				$out .= '<option value="' . esc_attr( (string) $ov ) . '"' . selected( (string) $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
			}
			$out .= '</select>';
		} elseif ( 'file' === $type ) {
			$out .= '<input type="file" name="' . esc_attr( $name ) . '[]" id="' . esc_attr( $id ) . '" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip">';
			$out .= '<small class="ci-help">' . esc_html__( 'Up to 5 files, 10 MB each (images, PDF, Office, ZIP).', 'cirilinfo' ) . '</small>';
		} else {
			$out .= '<input type="' . esc_attr( $type ) . '" value="' . esc_attr( (string) $val ) . '"' . $common . '>';
		}
		return $out . '</div>';
	}

	private static function services_options(): array {
		$opts = array( 0 => __( 'Select a service…', 'cirilinfo' ) );
		foreach ( get_posts( array( 'post_type' => 'ci_service', 'numberposts' => 50, 'orderby' => 'menu_order title', 'order' => 'ASC', 'post_status' => 'publish' ) ) as $s ) {
			$opts[ $s->ID ] = $s->post_title;
		}
		return $opts;
	}

	public static function render_enquiry( string $type = 'contact', array $args = array() ): string {
		$user     = wp_get_current_user();
		$is_quote = 'quote' === $type;
		$service  = (int) ( $args['service'] ?? 0 );
		$package  = (int) ( $args['package'] ?? 0 );
		$out      = self::notice( $type ) . self::open( $type, array( 'ci_package' => $package ), $is_quote );
		$out     .= '<div class="ci-grid-2">';
		$out     .= self::field( 'ci_name', __( 'Full name', 'cirilinfo' ), 'text', array( 'required' => true, 'value' => $user->exists() ? $user->display_name : '', 'autocomplete' => 'name' ) );
		$out     .= self::field( 'ci_email', __( 'Email', 'cirilinfo' ), 'email', array( 'required' => true, 'value' => $user->exists() ? $user->user_email : '', 'autocomplete' => 'email' ) );
		$out     .= self::field( 'ci_phone', __( 'Phone / WhatsApp', 'cirilinfo' ), 'tel', array( 'autocomplete' => 'tel' ) );
		$out     .= self::field( 'ci_service', __( 'Service of interest', 'cirilinfo' ), 'select', array( 'options' => self::services_options(), 'value' => $service ) );
		if ( $is_quote ) {
			$out .= self::field( 'ci_budget', __( 'Estimated budget (₹)', 'cirilinfo' ), 'select', array(
				'options' => array( '' => __( 'Select…', 'cirilinfo' ), 'lt10' => 'Under ₹10,000', '10-25' => '₹10,000 – ₹25,000', '25-50' => '₹25,000 – ₹50,000', '50-100' => '₹50,000 – ₹1,00,000', 'gt100' => 'Above ₹1,00,000' ),
			) );
			$out .= self::field( 'ci_timeline', __( 'Expected timeline', 'cirilinfo' ), 'text', array( 'placeholder' => __( 'e.g. within 4 weeks', 'cirilinfo' ) ) );
		}
		$out .= '</div>';
		$out .= self::field( 'ci_message', $is_quote ? __( 'Project requirements', 'cirilinfo' ) : __( 'How can we help?', 'cirilinfo' ), 'textarea', array( 'required' => true, 'rows' => $is_quote ? 6 : 5 ) );
		if ( $is_quote ) {
			$out .= self::field( 'ci_files', __( 'Reference files (optional)', 'cirilinfo' ), 'file' );
		}
		$out .= '<button type="submit" class="ci-btn ci-btn--primary">' . esc_html( $is_quote ? __( 'Request my quote', 'cirilinfo' ) : __( 'Send message', 'cirilinfo' ) ) . ' ' . Icons::svg( 'arrow', 18 ) . '</button>';
		$out .= '<p class="ci-help ci-privacy">' . esc_html__( 'We only use your details to respond to this request.', 'cirilinfo' ) . '</p></form>';
		return $out;
	}

	public static function render_newsletter(): string {
		$out  = self::notice( 'newsletter' ) . self::open( 'newsletter' );
		$out .= '<div class="ci-inline"><input type="email" name="ci_email" required placeholder="' . esc_attr__( 'Your email address', 'cirilinfo' ) . '" aria-label="' . esc_attr__( 'Email address', 'cirilinfo' ) . '" autocomplete="email">';
		$out .= '<button type="submit" class="ci-btn ci-btn--accent">' . esc_html__( 'Subscribe', 'cirilinfo' ) . '</button></div></form>';
		return $out;
	}

	public static function render_feedback(): string {
		$user = wp_get_current_user();
		$out  = self::notice( 'feedback' ) . self::open( 'feedback' );
		$out .= '<div class="ci-grid-2">' . self::field( 'ci_name', __( 'Your name', 'cirilinfo' ), 'text', array( 'required' => true, 'value' => $user->exists() ? $user->display_name : '' ) );
		$out .= self::field( 'ci_email', __( 'Email (not published)', 'cirilinfo' ), 'email', array( 'required' => true, 'value' => $user->exists() ? $user->user_email : '' ) );
		$out .= self::field( 'ci_company', __( 'Company / role', 'cirilinfo' ), 'text' );
		$out .= self::field( 'ci_rating', __( 'Rating', 'cirilinfo' ), 'select', array( 'options' => array( 0 => __( 'Select…', 'cirilinfo' ), 5 => '★★★★★', 4 => '★★★★☆', 3 => '★★★☆☆', 2 => '★★☆☆☆', 1 => '★☆☆☆☆' ), 'required' => true ) ) . '</div>';
		$out .= self::field( 'ci_message', __( 'Your experience', 'cirilinfo' ), 'textarea', array( 'required' => true ) );
		return $out . '<button type="submit" class="ci-btn ci-btn--primary">' . esc_html__( 'Submit feedback', 'cirilinfo' ) . '</button></form>';
	}

	public static function render_ticket(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to raise a support ticket.', 'cirilinfo' ) . '</p>';
		}
		$projects = array( 0 => __( 'Not related to a project', 'cirilinfo' ) );
		foreach ( Projects::for_user( get_current_user_id() ) as $p ) {
			$projects[ $p->ID ] = $p->post_title;
		}
		$out  = self::notice( 'ticket' ) . self::open( 'ticket', array(), true );
		$out .= self::field( 'ci_subject', __( 'Subject', 'cirilinfo' ), 'text', array( 'required' => true ) );
		$out .= '<div class="ci-grid-2">' . self::field( 'ci_priority', __( 'Priority', 'cirilinfo' ), 'select', array( 'options' => array( 'normal' => __( 'Normal', 'cirilinfo' ), 'high' => __( 'High', 'cirilinfo' ), 'low' => __( 'Low', 'cirilinfo' ) ) ) );
		$out .= self::field( 'ci_project', __( 'Related project', 'cirilinfo' ), 'select', array( 'options' => $projects ) ) . '</div>';
		$out .= self::field( 'ci_message', __( 'Describe the issue', 'cirilinfo' ), 'textarea', array( 'required' => true ) );
		$out .= self::field( 'ci_files', __( 'Attachments (optional)', 'cirilinfo' ), 'file' );
		return $out . '<button type="submit" class="ci-btn ci-btn--primary">' . esc_html__( 'Create ticket', 'cirilinfo' ) . '</button></form>';
	}

	/** Reply form for a ticket or project thread. */
	public static function render_reply( string $type, int $post_id ): string {
		$out  = self::notice( $type ) . self::open( $type, array( 'ci_post' => $post_id ) );
		$out .= self::field( 'ci_message', __( 'Write a message', 'cirilinfo' ), 'textarea', array( 'required' => true, 'rows' => 4 ) );
		return $out . '<button type="submit" class="ci-btn ci-btn--primary">' . esc_html__( 'Send', 'cirilinfo' ) . '</button></form>';
	}

	public static function render_requirements( int $project_id ): string {
		$out  = self::notice( 'requirements' ) . self::open( 'requirements', array( 'ci_post' => $project_id ), true );
		$out .= self::field( 'ci_message', __( 'Project requirements', 'cirilinfo' ), 'textarea', array( 'required' => true, 'rows' => 7, 'placeholder' => __( 'Goals, pages/features, target audience, brand colours, competitors, deadlines, login details for existing assets…', 'cirilinfo' ) ) );
		$out .= self::field( 'ci_files', __( 'Reference files, logos, content', 'cirilinfo' ), 'file' );
		return $out . '<button type="submit" class="ci-btn ci-btn--primary">' . esc_html__( 'Submit requirements', 'cirilinfo' ) . '</button></form>';
	}

	/* ------------------------------------------------------------ Handling */

	private static function wants_json(): bool {
		$h = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		return false !== strpos( $h, 'application/json' );
	}

	private static function back(): string {
		$back = isset( $_POST['ci_back'] ) ? esc_url_raw( wp_unslash( $_POST['ci_back'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$back = wp_validate_redirect( $back, home_url( '/' ) );
		return $back;
	}

	private static function fail( string $type, string $code ): void {
		if ( self::wants_json() ) {
			wp_send_json_error( array( 'message' => self::messages()[ $code ] ?? self::messages()['fail'] ), 'nonce' === $code ? 403 : 422 );
		}
		wp_safe_redirect( add_query_arg( array( 'ci_err' => $code, 'ci_form' => $type ), self::back() ) . '#ci-notice' );
		exit;
	}

	private static function succeed( string $type ): void {
		$msg = self::success_messages()[ $type ] ?? '';
		if ( self::wants_json() ) {
			wp_send_json_success( array( 'message' => $msg, 'event' => $type ) );
		}
		wp_safe_redirect( add_query_arg( array( 'ci_sent' => 1, 'ci_form' => $type ), self::back() ) . '#ci-notice' );
		exit;
	}

	public static function handle(): void {
		$type = isset( $_POST['ci_form'] ) ? sanitize_key( wp_unslash( $_POST['ci_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $type, array_merge( self::PUBLIC_TYPES, self::AUTH_TYPES ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'cirilinfo' ), 400 );
		}
		$nonce = isset( $_POST['_ci_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ci_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ci_form_' . $type ) ) {
			self::fail( $type, 'nonce' );
		}
		// Honeypot + minimum fill time.
		if ( ! empty( $_POST['ci_website_url'] ) ) {
			self::succeed( $type ); // Pretend success to bots.
		}
		$ts = isset( $_POST['ci_ts'] ) ? absint( $_POST['ci_ts'] ) : 0;
		if ( $ts && ( time() - $ts ) < 3 ) {
			self::fail( $type, 'spam' );
		}
		if ( in_array( $type, self::AUTH_TYPES, true ) && ! is_user_logged_in() ) {
			self::fail( $type, 'login' );
		}
		$limit = 'newsletter' === $type ? array( 5, HOUR_IN_SECONDS ) : array( 8, 10 * MINUTE_IN_SECONDS );
		if ( ! Rate_Limiter::hit( 'form_' . $type, $limit[0], $limit[1] ) ) {
			self::fail( $type, 'rate' );
		}

		$method = 'process_' . ( in_array( $type, array( 'contact', 'quote' ), true ) ? 'enquiry' : $type );
		self::$method( $type );
		self::succeed( $type );
	}

	private static function post_str( string $key, string $filter = 'text' ): string {
		$v = isset( $_POST[ $key ] ) && ! is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security
		return 'textarea' === $filter ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
	}

	private static function process_enquiry( string $type ): void {
		$name    = self::post_str( 'ci_name' );
		$email   = sanitize_email( self::post_str( 'ci_email' ) );
		$phone   = preg_replace( '/[^0-9+\-\s()]/', '', self::post_str( 'ci_phone' ) );
		$message = self::post_str( 'ci_message', 'textarea' );
		$service = absint( $_POST['ci_service'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$package = absint( $_POST['ci_package'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( strlen( $name ) < 2 ) {
			self::fail( $type, 'name' );
		}
		if ( ! is_email( $email ) ) {
			self::fail( $type, 'email' );
		}
		if ( $phone && strlen( preg_replace( '/\D/', '', $phone ) ) < 7 ) {
			self::fail( $type, 'phone' );
		}
		if ( strlen( $message ) < 10 || strlen( $message ) > 5000 ) {
			self::fail( $type, 'message' );
		}
		if ( $service && 'ci_service' !== get_post_type( $service ) ) {
			$service = 0;
		}
		if ( $package && 'ci_package' !== get_post_type( $package ) ) {
			$package = 0;
		}

		$up = array( 'files' => array(), 'errors' => array() );
		if ( 'quote' === $type ) {
			$up = Uploads::store( 'ci_files' );
			if ( $up['errors'] ) {
				self::fail( $type, 'upload' );
			}
		}

		$service_title = $service ? get_the_title( $service ) : '';
		$id = wp_insert_post( array(
			'post_type'   => 'ci_enquiry',
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s – %s%s', 'quote' === $type ? 'Quote' : 'Enquiry', $name, $service_title ? ' – ' . $service_title : '' ),
			'post_author' => get_current_user_id(),
		), true );
		if ( is_wp_error( $id ) ) {
			self::fail( $type, 'fail' );
		}
		update_post_meta( $id, '_ci_type', $type );
		update_post_meta( $id, '_ci_name', $name );
		update_post_meta( $id, '_ci_email', $email );
		update_post_meta( $id, '_ci_phone', $phone );
		update_post_meta( $id, '_ci_service_id', $service );
		update_post_meta( $id, '_ci_package_id', $package );
		update_post_meta( $id, '_ci_budget', self::post_str( 'ci_budget' ) );
		update_post_meta( $id, '_ci_timeline', self::post_str( 'ci_timeline' ) );
		update_post_meta( $id, '_ci_message', $message );
		update_post_meta( $id, '_ci_status', 'new' );
		update_post_meta( $id, '_ci_source_url', esc_url_raw( self::back() ) );
		Uploads::attach( $id, '_ci_files', $up['files'] );

		$ref = 'ENQ-' . $id;
		Mailer::send( 'enquiry_ack', $email, array(
			'customer_name' => $name,
			'enquiry_ref'   => $ref,
			'message'       => $message,
			'service'       => $service_title,
		) );
		$body = '<table cellpadding="4"><tr><td>Name</td><td>' . esc_html( $name ) . '</td></tr><tr><td>Email</td><td>' . esc_html( $email ) . '</td></tr><tr><td>Phone</td><td>' . esc_html( $phone ) . '</td></tr><tr><td>Service</td><td>' . esc_html( $service_title ) . '</td></tr></table><p>' . nl2br( esc_html( $message ) ) . '</p>';
		Mailer::notify_admin( ( 'quote' === $type ? 'New quotation request ' : 'New enquiry ' ) . $ref, $body, admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		do_action( 'cirilinfo_enquiry_created', $id, $type );
	}

	private static function process_ticket( string $type ): void {
		$subject = self::post_str( 'ci_subject' );
		$message = self::post_str( 'ci_message', 'textarea' );
		$project = absint( $_POST['ci_project'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$prio    = self::post_str( 'ci_priority' );
		$uid     = get_current_user_id();
		if ( strlen( $subject ) < 3 ) {
			self::fail( $type, 'subject' );
		}
		if ( strlen( $message ) < 10 ) {
			self::fail( $type, 'message' );
		}
		if ( $project && ! Projects::user_owns( $project, $uid ) ) {
			self::fail( $type, 'perm' );
		}
		$up = Uploads::store( 'ci_files' );
		if ( $up['errors'] ) {
			self::fail( $type, 'upload' );
		}
		$id = wp_insert_post( array( 'post_type' => 'ci_ticket', 'post_status' => 'publish', 'post_title' => $subject, 'post_author' => $uid ), true );
		if ( is_wp_error( $id ) ) {
			self::fail( $type, 'fail' );
		}
		update_post_meta( $id, '_ci_status', 'open' );
		update_post_meta( $id, '_ci_priority', in_array( $prio, array( 'low', 'normal', 'high' ), true ) ? $prio : 'normal' );
		update_post_meta( $id, '_ci_project_id', $project );
		Uploads::attach( $id, '_ci_files', $up['files'] );
		Threads::add( $id, $uid, $message, false );
		Mailer::notify_admin( 'New support ticket #' . $id . ': ' . $subject, '<p>' . nl2br( esc_html( $message ) ) . '</p>', admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		do_action( 'cirilinfo_ticket_created', $id );
	}

	private static function process_ticket_reply( string $type ): void {
		$id  = absint( $_POST['ci_post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$uid = get_current_user_id();
		$msg = self::post_str( 'ci_message', 'textarea' );
		$t   = get_post( $id );
		if ( ! $t || 'ci_ticket' !== $t->post_type || (int) $t->post_author !== $uid ) {
			self::fail( $type, 'perm' );
		}
		if ( strlen( $msg ) < 2 ) {
			self::fail( $type, 'message' );
		}
		Threads::add( $id, $uid, $msg, false );
		update_post_meta( $id, '_ci_status', 'open' );
		Mailer::notify_admin( 'Ticket reply #' . $id . ': ' . $t->post_title, '<p>' . nl2br( esc_html( $msg ) ) . '</p>', admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}

	private static function process_project_message( string $type ): void {
		$id  = absint( $_POST['ci_post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$uid = get_current_user_id();
		$msg = self::post_str( 'ci_message', 'textarea' );
		if ( ! $id || 'ci_project' !== get_post_type( $id ) || ! Projects::user_owns( $id, $uid ) ) {
			self::fail( $type, 'perm' );
		}
		if ( strlen( $msg ) < 2 ) {
			self::fail( $type, 'message' );
		}
		Threads::add( $id, $uid, $msg, false );
		Projects::notify_team( $id, 'Customer message on ' . get_the_title( $id ), '<p>' . nl2br( esc_html( $msg ) ) . '</p>' );
	}

	private static function process_requirements( string $type ): void {
		$id  = absint( $_POST['ci_post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$uid = get_current_user_id();
		$msg = self::post_str( 'ci_message', 'textarea' );
		if ( ! $id || 'ci_project' !== get_post_type( $id ) || ! Projects::user_owns( $id, $uid ) ) {
			self::fail( $type, 'perm' );
		}
		if ( strlen( $msg ) < 10 ) {
			self::fail( $type, 'message' );
		}
		$up = Uploads::store( 'ci_files' );
		if ( $up['errors'] ) {
			self::fail( $type, 'upload' );
		}
		Uploads::attach( $id, '_ci_req_files', $up['files'] );
		Projects::add_requirements( $id, $msg );
		Projects::notify_team( $id, 'Requirements submitted: ' . get_the_title( $id ), '<p>' . nl2br( esc_html( $msg ) ) . '</p>' );
	}

	private static function process_feedback( string $type ): void {
		$name    = self::post_str( 'ci_name' );
		$email   = sanitize_email( self::post_str( 'ci_email' ) );
		$message = self::post_str( 'ci_message', 'textarea' );
		$rating  = absint( $_POST['ci_rating'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( strlen( $name ) < 2 ) {
			self::fail( $type, 'name' );
		}
		if ( ! is_email( $email ) ) {
			self::fail( $type, 'email' );
		}
		if ( $rating < 1 || $rating > 5 ) {
			self::fail( $type, 'rating' );
		}
		if ( strlen( $message ) < 10 || strlen( $message ) > 1500 ) {
			self::fail( $type, 'message' );
		}
		$id = wp_insert_post( array( 'post_type' => 'ci_testimonial', 'post_status' => 'pending', 'post_title' => $name, 'post_content' => $message ), true );
		if ( is_wp_error( $id ) ) {
			self::fail( $type, 'fail' );
		}
		update_post_meta( $id, '_ci_rating', $rating );
		update_post_meta( $id, '_ci_company', self::post_str( 'ci_company' ) );
		update_post_meta( $id, '_ci_email', $email );
		Mailer::notify_admin( 'New customer feedback (' . $rating . '/5)', '<p>' . nl2br( esc_html( $message ) ) . '</p><p>' . esc_html( $name ) . '</p>', admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}

	private static function process_newsletter( string $type ): void {
		$email = sanitize_email( self::post_str( 'ci_email' ) );
		if ( ! is_email( $email ) ) {
			self::fail( $type, 'email' );
		}
		Marketing::subscribe( $email, '', 'website' );
	}
}
