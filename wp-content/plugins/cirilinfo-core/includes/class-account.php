<?php
/**
 * Customer account: email verification, WooCommerce My Account extensions
 * (projects, payments, invoices, support tickets, enquiries, notifications).
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Account {

	const ENDPOINTS = array(
		'projects'  => 'My Projects',
		'payments'  => 'My Payments',
		'invoices'  => 'My Invoices',
		'support'   => 'Support Tickets',
		'enquiries' => 'My Enquiries',
	);

	public static function init(): void {
		// Endpoints.
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ) );
		add_filter( 'woocommerce_endpoint_orders_title', static fn() => __( 'My Orders', 'cirilinfo' ) );
		foreach ( self::ENDPOINTS as $ep => $title ) {
			add_action( 'woocommerce_account_' . $ep . '_endpoint', array( __CLASS__, 'render_' . $ep ) );
			add_filter( 'woocommerce_endpoint_' . $ep . '_title', static fn() => __( $title, 'cirilinfo' ) ); // phpcs:ignore WordPress.WP.I18n
		}
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'dashboard' ), 20 );

		// Registration extras + verification.
		add_action( 'woocommerce_register_form_start', array( __CLASS__, 'register_fields' ) );
		add_filter( 'woocommerce_registration_errors', array( __CLASS__, 'register_validate' ), 10, 3 );
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'on_created' ), 10, 1 );
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_unverified' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_nopriv_ci_resend_verification', array( __CLASS__, 'resend' ) );
		add_action( 'admin_post_ci_resend_verification', array( __CLASS__, 'resend' ) );
		add_action( 'after_password_reset', array( __CLASS__, 'verify_on_reset' ), 10, 1 );

		// Profile extras.
		add_action( 'woocommerce_edit_account_form', array( __CLASS__, 'profile_phone' ) );
		add_action( 'woocommerce_save_account_details', array( __CLASS__, 'save_phone' ), 10, 1 );
	}

	public static function query_vars( array $vars ): array {
		foreach ( array_keys( self::ENDPOINTS ) as $ep ) {
			$vars[ $ep ] = $ep;
		}
		return $vars;
	}

	public static function menu_items( array $items ): array {
		$new = array(
			'dashboard'       => __( 'Dashboard', 'cirilinfo' ),
			'orders'          => __( 'My Orders', 'cirilinfo' ),
			'projects'        => __( 'My Projects', 'cirilinfo' ),
			'payments'        => __( 'My Payments', 'cirilinfo' ),
			'invoices'        => __( 'My Invoices', 'cirilinfo' ),
			'support'         => __( 'Support Tickets', 'cirilinfo' ),
			'enquiries'       => __( 'My Enquiries', 'cirilinfo' ),
			'edit-account'    => __( 'My Profile', 'cirilinfo' ),
			'customer-logout' => __( 'Log out', 'cirilinfo' ),
		);
		return $new;
	}

	/* --------------------------------------------------------- Dashboard */

	public static function dashboard(): void {
		$uid      = get_current_user_id();
		$projects = Projects::for_user( $uid );
		$active   = count( array_filter( $projects, static fn( $p ) => ! in_array( get_post_meta( $p->ID, '_ci_status', true ), array( 'delivered', 'cancelled' ), true ) ) );
		$tickets  = count( self::tickets_for( $uid, array( 'open', 'pending' ) ) );
		$orders   = (int) wc_get_customer_order_count( $uid );
		$tiles    = array(
			array( 'my-orders', __( 'Orders', 'cirilinfo' ), $orders, 'orders', 'cart' ),
			array( 'projects', __( 'Active projects', 'cirilinfo' ), $active, 'projects', 'rocket' ),
			array( 'support', __( 'Open tickets', 'cirilinfo' ), $tickets, 'support', 'support' ),
		);
		echo '<div class="ci-tiles">';
		foreach ( $tiles as $t ) {
			printf( '<a class="ci-tile" href="%s">%s<strong>%d</strong><span>%s</span></a>', esc_url( wc_get_account_endpoint_url( $t[3] ) ), Icons::svg( $t[4], 22 ), (int) $t[2], esc_html( $t[1] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';

		$notes = Notifications::get( $uid, 8 );
		echo '<section class="ci-panel"><div class="ci-panel__head"><h3>' . esc_html__( 'Notifications', 'cirilinfo' ) . '</h3>';
		if ( Notifications::unread( $uid ) ) {
			echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ci_notifications_read' ), 'ci_notifications_read' ) ) . '">' . esc_html__( 'Mark all read', 'cirilinfo' ) . '</a>';
		}
		echo '</div>';
		if ( ! $notes ) {
			echo '<p class="ci-muted">' . esc_html__( 'You are all caught up.', 'cirilinfo' ) . '</p>';
		} else {
			echo '<ul class="ci-notes">';
			foreach ( $notes as $n ) {
				$inner = esc_html( $n['m'] ) . ' <time>' . esc_html( human_time_diff( (int) $n['t'] ) ) . ' ' . esc_html__( 'ago', 'cirilinfo' ) . '</time>';
				printf( '<li class="%s">%s</li>', empty( $n['r'] ) ? 'is-unread' : '', $n['u'] ? '<a href="' . esc_url( $n['u'] ) . '">' . $inner . '</a>' : $inner ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	/* ----------------------------------------------------------- Projects */

	public static function render_projects( $value ): void {
		$uid = get_current_user_id();
		$id  = absint( $value );
		if ( $id ) {
			self::project_detail( $id, $uid );
			return;
		}
		$projects = Projects::for_user( $uid );
		echo '<h2>' . esc_html__( 'My Projects', 'cirilinfo' ) . '</h2>';
		if ( ! $projects ) {
			echo '<div class="ci-empty">' . Icons::svg( 'rocket', 36 ) . '<p>' . esc_html__( 'No projects yet. Projects are created automatically when you purchase a service.', 'cirilinfo' ) . '</p><a class="ci-btn ci-btn--primary" href="' . esc_url( home_url( '/services/' ) ) . '">' . esc_html__( 'Browse services', 'cirilinfo' ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		echo '<div class="ci-cards-list">';
		foreach ( $projects as $p ) {
			$s = (string) get_post_meta( $p->ID, '_ci_status', true );
			printf(
				'<a class="ci-item" href="%s"><div><strong>%s</strong><span class="ci-badge ci-badge--%s">%s</span></div><div class="ci-bar"><span style="width:%d%%"></span></div><small>%d%% · %s</small></a>',
				esc_url( Projects::project_url( $p->ID ) ),
				esc_html( $p->post_title ),
				esc_attr( $s ),
				esc_html( Post_Types::project_statuses()[ $s ] ?? '—' ),
				(int) Projects::progress( $p->ID ),
				(int) Projects::progress( $p->ID ),
				esc_html( get_the_date( 'd M Y', $p ) )
			);
		}
		echo '</div>';
	}

	private static function project_detail( int $id, int $uid ): void {
		$p = get_post( $id );
		if ( ! $p || 'ci_project' !== $p->post_type || ! Projects::user_owns( $id, $uid ) ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Project not found.', 'cirilinfo' ) . '</div>';
			return;
		}
		$s     = (string) get_post_meta( $id, '_ci_status', true );
		$order = Projects::order_for( $id );
		echo '<p><a href="' . esc_url( wc_get_account_endpoint_url( 'projects' ) ) . '">&larr; ' . esc_html__( 'All projects', 'cirilinfo' ) . '</a></p>';
		echo '<h2>' . esc_html( $p->post_title ) . ' <span class="ci-badge ci-badge--' . esc_attr( $s ) . '">' . esc_html( Post_Types::project_statuses()[ $s ] ?? '' ) . '</span></h2>';
		echo '<div class="ci-bar ci-bar--lg"><span style="width:' . (int) Projects::progress( $id ) . '%"></span></div><p class="ci-muted">' . (int) Projects::progress( $id ) . '% ' . esc_html__( 'complete', 'cirilinfo' ) . ( $order ? ' · ' . sprintf( /* translators: %s: order number */ esc_html__( 'Order #%s', 'cirilinfo' ), esc_html( $order->get_order_number() ) ) : '' ) . '</p>';

		// Milestones.
		$ms = get_post_meta( $id, '_ci_milestones', true );
		if ( is_array( $ms ) && $ms ) {
			echo '<section class="ci-panel"><h3>' . esc_html__( 'Milestones', 'cirilinfo' ) . '</h3><ol class="ci-timeline">';
			foreach ( $ms as $m ) {
				printf( '<li class="%s"><strong>%s</strong>%s</li>', ! empty( $m['done'] ) ? 'is-done' : '', esc_html( $m['label'] ), ! empty( $m['due'] ) ? ' <small>' . esc_html( mysql2date( 'd M Y', $m['due'] ) ) . '</small>' : '' );
			}
			echo '</ol></section>';
		}

		// Requirements.
		$req = trim( (string) get_post_meta( $id, '_ci_requirements', true ) );
		echo '<section class="ci-panel"><h3>' . esc_html__( 'Your requirements', 'cirilinfo' ) . '</h3>';
		if ( $req ) {
			echo '<div class="ci-pre">' . esc_html( $req ) . '</div>';
		} else {
			echo '<p class="ci-muted">' . esc_html__( 'We have not received your requirements yet. Please submit them below so we can start.', 'cirilinfo' ) . '</p>';
		}
		echo Uploads::render_list( $id, '_ci_req_files' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! in_array( $s, array( 'delivered', 'cancelled' ), true ) ) {
			echo '<details' . ( $req ? '' : ' open' ) . '><summary>' . esc_html__( $req ? 'Add more details or files' : 'Submit requirements', 'cirilinfo' ) . '</summary>' . Forms::render_requirements( $id ) . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.WP.I18n
		}
		echo '</section>';

		// Deliverables.
		$deliv = Uploads::render_list( $id, '_ci_deliverables' );
		echo '<section class="ci-panel"><h3>' . esc_html__( 'Deliverables', 'cirilinfo' ) . '</h3>' . ( $deliv ?: '<p class="ci-muted">' . esc_html__( 'Nothing delivered yet.', 'cirilinfo' ) . '</p>' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput

		// Messages.
		echo '<section class="ci-panel"><h3>' . esc_html__( 'Messages', 'cirilinfo' ) . '</h3>' . Threads::render( $id ) . Forms::render_reply( 'project_message', $id ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput

		// Feedback after delivery.
		if ( in_array( $s, array( 'completed', 'delivered' ), true ) ) {
			echo '<section class="ci-panel" id="feedback"><h3>' . esc_html__( 'How did we do?', 'cirilinfo' ) . '</h3>' . Forms::render_feedback() . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/* ----------------------------------------------------------- Payments */

	public static function render_payments(): void {
		global $wpdb;
		$uid = get_current_user_id();
		echo '<h2>' . esc_html__( 'My Payments', 'cirilinfo' ) . '</h2>';
		$ids = (array) wc_get_orders( array( 'customer_id' => $uid, 'limit' => -1, 'return' => 'ids' ) );
		if ( ! $ids ) {
			echo '<div class="ci-empty"><p>' . esc_html__( 'No payments yet.', 'cirilinfo' ) . '</p></div>';
			return;
		}
		$ids   = array_map( 'intval', $ids );
		$table = Transactions::table();
		$in    = implode( ',', $ids );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE order_id IN ({$in}) AND status <> 'pending' ORDER BY id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL
		echo '<table class="shop_table shop_table_responsive ci-table"><thead><tr><th>' . esc_html__( 'Date', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Order', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Method', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Reference', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Status', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Amount', 'cirilinfo' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $rows as $r ) {
			$order = wc_get_order( (int) $r->order_id );
			$retry = '';
			if ( 'failed' === $r->status && $order && $order->needs_payment() ) {
				$retry = ' <a class="ci-link" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Retry payment', 'cirilinfo' ) . '</a>';
			}
			printf(
				'<tr><td data-title="Date">%s</td><td data-title="Order">#%s</td><td data-title="Method">%s</td><td data-title="Reference"><code>%s</code></td><td data-title="Status"><span class="ci-badge ci-badge--%s">%s</span>%s</td><td data-title="Amount">%s%s</td></tr>',
				esc_html( get_date_from_gmt( $r->created_at, 'd M Y' ) ),
				esc_html( $order ? $order->get_order_number() : (string) $r->order_id ),
				esc_html( $r->method_title ?: $r->gateway ),
				esc_html( $r->txn_ref ),
				esc_attr( $r->status ),
				esc_html( ucfirst( $r->status ) ),
				$retry, // phpcs:ignore WordPress.Security.EscapeOutput
				'refund' === $r->type ? '−' : '',
				esc_html( Pricing::money( (float) $r->amount, true ) )
			);
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No payment activity yet. Orders awaiting payment are listed under My Orders.', 'cirilinfo' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$unpaid = wc_get_orders( array( 'customer_id' => $uid, 'status' => array( 'pending', 'failed' ), 'limit' => 10 ) );
		foreach ( $unpaid as $o ) {
			echo '<p class="ci-notice ci-notice--warn">' . sprintf( /* translators: 1: order number */ esc_html__( 'Order #%1$s is awaiting payment.', 'cirilinfo' ), esc_html( $o->get_order_number() ) ) . ' <a href="' . esc_url( $o->get_checkout_payment_url() ) . '">' . esc_html__( 'Pay now', 'cirilinfo' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/* ----------------------------------------------------------- Invoices */

	public static function render_invoices(): void {
		$orders = wc_get_orders( array( 'customer_id' => get_current_user_id(), 'status' => array( 'processing', 'completed', 'on-hold', 'refunded' ), 'limit' => 100 ) );
		echo '<h2>' . esc_html__( 'My Invoices', 'cirilinfo' ) . '</h2>';
		if ( ! $orders ) {
			echo '<div class="ci-empty"><p>' . esc_html__( 'Invoices appear here after you place an order.', 'cirilinfo' ) . '</p></div>';
			return;
		}
		echo '<table class="shop_table shop_table_responsive ci-table"><thead><tr><th>' . esc_html__( 'Invoice', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Order', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Date', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Total', 'cirilinfo' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $orders as $o ) {
			printf(
				'<tr><td data-title="Invoice">%s</td><td data-title="Order">#%s</td><td data-title="Date">%s</td><td data-title="Total">%s</td><td><a class="ci-btn ci-btn--sm" target="_blank" rel="noopener" href="%s">%s %s</a></td></tr>',
				esc_html( (string) ( $o->get_meta( '_ci_invoice_number' ) ?: '—' ) ),
				esc_html( $o->get_order_number() ),
				esc_html( wc_format_datetime( $o->get_date_created(), 'd M Y' ) ),
				esc_html( Invoices::fmt( $o, (float) $o->get_total() ) ),
				esc_url( Invoices::url( $o ) ),
				Icons::svg( 'download', 16 ), // phpcs:ignore WordPress.Security.EscapeOutput
				esc_html__( 'View / Download', 'cirilinfo' )
			);
		}
		echo '</tbody></table>';
	}

	/* ----------------------------------------------------------- Support */

	/** @return \WP_Post[] */
	public static function tickets_for( int $uid, array $statuses = array() ): array {
		$args = array( 'post_type' => 'ci_ticket', 'post_status' => 'publish', 'author' => $uid, 'numberposts' => 100 );
		if ( $statuses ) {
			$args['meta_query'] = array( array( 'key' => '_ci_status', 'value' => $statuses, 'compare' => 'IN' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return get_posts( $args );
	}

	public static function render_support( $value ): void {
		$uid = get_current_user_id();
		$id  = absint( $value );
		if ( $id ) {
			$t = get_post( $id );
			if ( ! $t || 'ci_ticket' !== $t->post_type || (int) $t->post_author !== $uid ) {
				echo '<div class="woocommerce-error">' . esc_html__( 'Ticket not found.', 'cirilinfo' ) . '</div>';
				return;
			}
			$s = (string) get_post_meta( $id, '_ci_status', true );
			echo '<p><a href="' . esc_url( wc_get_account_endpoint_url( 'support' ) ) . '">&larr; ' . esc_html__( 'All tickets', 'cirilinfo' ) . '</a></p><h2>' . esc_html( $t->post_title ) . ' <span class="ci-badge ci-badge--' . esc_attr( $s ) . '">' . esc_html( Post_Types::ticket_statuses()[ $s ] ?? '' ) . '</span></h2>';
			echo Threads::render( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo Uploads::render_list( $id, '_ci_files' ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'closed' !== $s ) {
				echo Forms::render_reply( 'ticket_reply', $id ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			return;
		}
		echo '<h2>' . esc_html__( 'Support Tickets', 'cirilinfo' ) . '</h2>';
		$tickets = self::tickets_for( $uid );
		if ( $tickets ) {
			echo '<div class="ci-cards-list">';
			foreach ( $tickets as $t ) {
				$s = (string) get_post_meta( $t->ID, '_ci_status', true );
				printf( '<a class="ci-item" href="%s"><div><strong>#%d %s</strong><span class="ci-badge ci-badge--%s">%s</span></div><small>%s</small></a>', esc_url( trailingslashit( wc_get_account_endpoint_url( 'support' ) ) . $t->ID . '/' ), (int) $t->ID, esc_html( $t->post_title ), esc_attr( $s ), esc_html( Post_Types::ticket_statuses()[ $s ] ?? '' ), esc_html( get_the_date( 'd M Y', $t ) ) );
			}
			echo '</div>';
		}
		echo '<section class="ci-panel"><h3>' . esc_html__( 'Create a new ticket', 'cirilinfo' ) . '</h3>' . Forms::render_ticket() . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* --------------------------------------------------------- Enquiries */

	public static function render_enquiries(): void {
		$user = wp_get_current_user();
		$q    = array(
			'post_type' => 'ci_enquiry', 'post_status' => 'publish', 'numberposts' => 100,
			'meta_query' => array( 'relation' => 'OR', array( 'key' => '_ci_email', 'value' => $user->user_email ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		$posts = get_posts( $q );
		$posts = array_filter( $posts, static fn( $p ) => (int) $p->post_author === $user->ID || '0' !== (string) get_user_meta( $user->ID, '_ci_email_verified', true ) );
		echo '<h2>' . esc_html__( 'My Enquiries', 'cirilinfo' ) . '</h2>';
		if ( ! $posts ) {
			echo '<div class="ci-empty"><p>' . esc_html__( 'You have not sent any enquiries yet.', 'cirilinfo' ) . '</p><a class="ci-btn ci-btn--primary" href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html__( 'Contact us', 'cirilinfo' ) . '</a></div>';
			return;
		}
		echo '<table class="shop_table shop_table_responsive ci-table"><thead><tr><th>' . esc_html__( 'Ref', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Type', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Service', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Date', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Status', 'cirilinfo' ) . '</th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$s   = (string) get_post_meta( $p->ID, '_ci_status', true );
			$svc = (int) get_post_meta( $p->ID, '_ci_service_id', true );
			printf( '<tr><td>ENQ-%d</td><td>%s</td><td>%s</td><td>%s</td><td><span class="ci-badge ci-badge--%s">%s</span></td></tr>', (int) $p->ID, esc_html( ucfirst( (string) get_post_meta( $p->ID, '_ci_type', true ) ) ), esc_html( $svc ? get_the_title( $svc ) : '—' ), esc_html( get_the_date( 'd M Y', $p ) ), esc_attr( $s ), esc_html( Post_Types::enquiry_statuses()[ $s ] ?? '' ) );
		}
		echo '</tbody></table>';
	}

	/* -------------------------------------------------------- Profile */

	public static function profile_phone(): void {
		$phone = get_user_meta( get_current_user_id(), 'billing_phone', true );
		echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"><label for="ci_phone">' . esc_html__( 'Phone / WhatsApp', 'cirilinfo' ) . '</label><input type="tel" class="woocommerce-Input input-text" name="ci_phone" id="ci_phone" value="' . esc_attr( (string) $phone ) . '"></p>';
	}

	public static function save_phone( $user_id ): void {
		if ( isset( $_POST['ci_phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies its nonce before this hook.
			update_user_meta( $user_id, 'billing_phone', preg_replace( '/[^0-9+\-\s()]/', '', sanitize_text_field( wp_unslash( $_POST['ci_phone'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}

	/* ------------------------------------- Registration & verification */

	public static function register_fields(): void {
		$first = isset( $_POST['ci_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ci_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$last  = isset( $_POST['ci_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ci_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<p class="woocommerce-form-row form-row form-row-first"><label for="ci_first_name">' . esc_html__( 'First name', 'cirilinfo' ) . ' <span class="required">*</span></label><input type="text" class="woocommerce-Input input-text" name="ci_first_name" id="ci_first_name" autocomplete="given-name" value="' . esc_attr( $first ) . '" required></p>';
		echo '<p class="woocommerce-form-row form-row form-row-last"><label for="ci_last_name">' . esc_html__( 'Last name', 'cirilinfo' ) . ' <span class="required">*</span></label><input type="text" class="woocommerce-Input input-text" name="ci_last_name" id="ci_last_name" autocomplete="family-name" value="' . esc_attr( $last ) . '" required></p>';
	}

	public static function register_validate( $errors, $username, $email ) {
		if ( empty( $_POST['ci_first_name'] ) || empty( $_POST['ci_last_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$errors->add( 'ci_name', __( 'Please enter your first and last name.', 'cirilinfo' ) );
		}
		if ( ! Rate_Limiter::hit( 'register', 5, HOUR_IN_SECONDS ) ) {
			$errors->add( 'ci_rate', __( 'Too many registrations from your network. Please try again later.', 'cirilinfo' ) );
		}
		return $errors;
	}

	public static function on_created( $customer_id ): void {
		$customer_id = (int) $customer_id;
		if ( ! empty( $_POST['ci_first_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$first = sanitize_text_field( wp_unslash( $_POST['ci_first_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$last  = sanitize_text_field( wp_unslash( $_POST['ci_last_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			wp_update_user( array( 'ID' => $customer_id, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim( $first . ' ' . $last ) ) );
			update_user_meta( $customer_id, 'billing_first_name', $first );
			update_user_meta( $customer_id, 'billing_last_name', $last );
		}
		update_user_meta( $customer_id, '_ci_email_verified', 0 );
		self::send_verification( $customer_id );
		$u = get_userdata( $customer_id );
		if ( $u ) {
			Mailer::notify_admin( 'New customer registration', '<p>' . esc_html( $u->display_name ) . ' &lt;' . esc_html( $u->user_email ) . '&gt;</p>', admin_url( 'user-edit.php?user_id=' . $customer_id ) );
		}
	}

	public static function send_verification( int $user_id ): void {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return;
		}
		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, '_ci_verify_hash', hash( 'sha256', $token ) );
		update_user_meta( $user_id, '_ci_verify_exp', time() + 2 * DAY_IN_SECONDS );
		Mailer::send( 'verify_email', $u->user_email, array(
			'customer_name' => $u->first_name ?: $u->display_name,
			'verify_url'    => add_query_arg( array( 'ci_verify' => $user_id, 'k' => $token ), home_url( '/' ) ),
		) );
	}

	public static function block_unverified( $user, $password ) {
		if ( is_wp_error( $user ) || ! $user instanceof \WP_User ) {
			return $user;
		}
		if ( '0' === (string) get_user_meta( $user->ID, '_ci_email_verified', true ) && in_array( 'customer', (array) $user->roles, true ) ) {
			$link = wp_nonce_url( add_query_arg( array( 'action' => 'ci_resend_verification', 'u' => rawurlencode( $user->user_login ) ), admin_url( 'admin-post.php' ) ), 'ci_resend' );
			return new \WP_Error( 'ci_unverified', sprintf(
				/* translators: %s: resend link */
				__( '<strong>Please verify your email address</strong> before logging in. Check your inbox for the verification link, or <a href="%s">send it again</a>.', 'cirilinfo' ),
				esc_url( $link )
			) );
		}
		return $user;
	}

	public static function handle_verify(): void {
		if ( empty( $_GET['ci_verify'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$uid  = absint( $_GET['ci_verify'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$key  = sanitize_text_field( wp_unslash( $_GET['k'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$hash = (string) get_user_meta( $uid, '_ci_verify_hash', true );
		$exp  = (int) get_user_meta( $uid, '_ci_verify_exp', true );
		$ok   = $hash && $exp > time() && hash_equals( $hash, hash( 'sha256', $key ) );
		if ( $ok ) {
			update_user_meta( $uid, '_ci_email_verified', 1 );
			delete_user_meta( $uid, '_ci_verify_hash' );
			delete_user_meta( $uid, '_ci_verify_exp' );
		}
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'ci_verified', $ok ? '1' : '0', $url ) );
		exit;
	}

	public static function resend(): void {
		check_admin_referer( 'ci_resend' );
		$login = isset( $_GET['u'] ) ? sanitize_user( wp_unslash( $_GET['u'] ) ) : '';
		if ( $login && Rate_Limiter::hit( 'resend_verify', 3, HOUR_IN_SECONDS, Rate_Limiter::ip() . '|' . $login ) ) {
			$u = get_user_by( 'login', $login );
			if ( $u && '0' === (string) get_user_meta( $u->ID, '_ci_email_verified', true ) ) {
				self::send_verification( $u->ID );
			}
		}
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'ci_resent', '1', $url ) );
		exit;
	}

	/** Completing a password reset proves control of the mailbox. */
	public static function verify_on_reset( $user ): void {
		if ( $user instanceof \WP_User ) {
			update_user_meta( $user->ID, '_ci_email_verified', 1 );
		}
	}
}
