<?php
/**
 * Admin: menu, overview dashboard, enquiry & ticket screens, setup checker.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes_ci_enquiry', array( __CLASS__, 'enquiry_boxes' ) );
		add_action( 'add_meta_boxes_ci_ticket', array( __CLASS__, 'ticket_boxes' ) );
		add_action( 'save_post_ci_enquiry', array( __CLASS__, 'save_enquiry' ), 10, 2 );
		add_action( 'save_post_ci_ticket', array( __CLASS__, 'save_ticket' ), 10, 2 );
		foreach ( array( 'ci_enquiry', 'ci_ticket' ) as $t ) {
			add_filter( "manage_{$t}_posts_columns", array( __CLASS__, 'ops_columns' ) );
			add_action( "manage_{$t}_posts_custom_column", array( __CLASS__, 'ops_column' ), 10, 2 );
		}
		add_action( 'admin_post_ci_setup', array( __CLASS__, 'handle_setup' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'wp_dashboard_widget' ) );
	}

	public static function menu(): void {
		add_menu_page( 'CirilInfo', 'CirilInfo', 'edit_ci_ops', 'cirilinfo', '__return_null', 'dashicons-chart-area', 30 );
		add_submenu_page( 'cirilinfo', __( 'Overview', 'cirilinfo' ), __( 'Overview', 'cirilinfo' ), 'manage_cirilinfo', 'cirilinfo', array( __CLASS__, 'render_overview' ) );
		add_submenu_page( 'cirilinfo', __( 'Setup', 'cirilinfo' ), __( 'Setup', 'cirilinfo' ), 'manage_options', 'cirilinfo-setup', array( __CLASS__, 'render_setup' ) );
	}

	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		$ours   = $screen && ( false !== strpos( (string) $screen->id, 'cirilinfo' ) || 0 === strpos( (string) $screen->post_type, 'ci_' ) );
		if ( $ours ) {
			wp_enqueue_style( 'cirilinfo-admin', CIRILINFO_URL . 'assets/css/admin.css', array(), CIRILINFO_VERSION );
			wp_enqueue_script( 'cirilinfo-admin', CIRILINFO_URL . 'assets/js/admin.js', array(), CIRILINFO_VERSION, true );
		}
	}

	/* ----------------------------------------------------------- Overview */

	private static function counts(): array {
		$wc = Plugin::wc_active();
		$cust = count_users();
		$c    = array(
			'customers'   => (int) ( $cust['avail_roles']['customer'] ?? 0 ),
			'orders'      => 0,
			'completed'   => 0,
			'pending_pay' => 0,
			'enquiries'   => 0,
			'projects'    => 0,
		);
		if ( $wc ) {
			foreach ( array( 'pending', 'processing', 'on-hold', 'completed', 'refunded', 'failed', 'cancelled' ) as $s ) {
				$c['orders'] += (int) wc_orders_count( $s );
			}
			$c['completed']   = (int) wc_orders_count( 'completed' );
			$c['pending_pay'] = (int) wc_orders_count( 'pending' ) + (int) wc_orders_count( 'on-hold' ) + (int) wc_orders_count( 'failed' );
		}
		$q = new \WP_Query( array( 'post_type' => 'ci_enquiry', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_ci_status', 'value' => 'new' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$c['enquiries'] = (int) $q->found_posts;
		$p = new \WP_Query( array( 'post_type' => 'ci_project', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_ci_status', 'value' => array( 'confirmed', 'assigned', 'in_progress', 'review', 'awaiting_requirements' ), 'compare' => 'IN' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$c['projects'] = (int) $p->found_posts;
		return $c;
	}

	public static function chart_svg( array $series ): string {
		$w = 720;
		$h = 180;
		$n = max( 1, count( $series ) );
		$max = max( 1, max( $series ) );
		$bw = ( $w - 40 ) / $n;
		$bars = '';
		$i = 0;
		foreach ( $series as $day => $val ) {
			$bh    = max( 0, ( $val / $max ) * ( $h - 30 ) );
			$bars .= sprintf( '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3" fill="#5b3df5"><title>%s: %s</title></rect>', 30 + $i * $bw + 2, $h - 20 - $bh, max( 2, $bw - 4 ), $bh, esc_attr( $day ), esc_attr( Pricing::money( (float) $val ) ) );
			++$i;
		}
		$labels = sprintf( '<text x="30" y="%d" font-size="11" fill="#6b7390">%s</text><text x="%d" y="%d" font-size="11" fill="#6b7390" text-anchor="end">%s</text><text x="26" y="14" font-size="11" fill="#6b7390" text-anchor="end">%s</text>', $h - 4, esc_html( wp_date( 'd M', strtotime( (string) array_key_first( $series ) ) ) ), $w - 10, $h - 4, esc_html( wp_date( 'd M', strtotime( (string) array_key_last( $series ) ) ) ), esc_html( Pricing::money( (float) $max ) ) );
		return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" role="img" aria-label="' . esc_attr__( 'Revenue, last 30 days', 'cirilinfo' ) . '"><line x1="30" y1="' . ( $h - 20 ) . '" x2="' . ( $w - 10 ) . '" y2="' . ( $h - 20 ) . '" stroke="#dfe3f0"/>' . $bars . $labels . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function render_overview(): void {
		if ( ! current_user_can( 'manage_cirilinfo' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ) );
		}
		$wc  = Plugin::wc_active();
		$c   = self::counts();
		$sum = $wc ? Transactions::summary( array( 'status' => '', 'search' => '', 'from' => '', 'to' => '' ) ) : array( 'net' => 0 );
		$m30 = $wc ? Transactions::summary( array( 'status' => '', 'search' => '', 'from' => wp_date( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ), 'to' => '' ) ) : array( 'net' => 0 );

		echo '<div class="wrap ci-admin"><h1>' . esc_html__( 'CirilInfo Overview', 'cirilinfo' ) . '</h1>';
		if ( ! $wc ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Install and activate WooCommerce to enable orders, payments and checkout. See CirilInfo → Setup.', 'cirilinfo' ) . '</p></div>';
		}
		echo '<div class="ci-cards">';
		foreach ( array(
			array( __( 'Total customers', 'cirilinfo' ), (string) $c['customers'] ),
			array( __( 'Total orders', 'cirilinfo' ), (string) $c['orders'] ),
			array( __( 'Total sales (net)', 'cirilinfo' ), Pricing::money( (float) $sum['net'] ) ),
			array( __( 'Last 30 days', 'cirilinfo' ), Pricing::money( (float) $m30['net'] ) ),
			array( __( 'Pending payments', 'cirilinfo' ), (string) $c['pending_pay'] ),
			array( __( 'Completed orders', 'cirilinfo' ), (string) $c['completed'] ),
			array( __( 'New enquiries', 'cirilinfo' ), (string) $c['enquiries'] ),
			array( __( 'Active projects', 'cirilinfo' ), (string) $c['projects'] ),
		) as $card ) {
			echo '<div class="ci-card"><span>' . esc_html( $card[0] ) . '</span><strong>' . esc_html( $card[1] ) . '</strong></div>';
		}
		echo '</div>';

		echo '<div class="ci-two">';
		echo '<div class="ci-box"><h2>' . esc_html__( 'Revenue – last 30 days', 'cirilinfo' ) . '</h2>';
		echo $wc ? self::chart_svg( Transactions::daily_totals( 30 ) ) : '<p>—</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		echo '<div class="ci-box"><h2>' . esc_html__( 'Recent transactions', 'cirilinfo' ) . '</h2><table class="widefat striped"><tbody>';
		if ( $wc ) {
			foreach ( Transactions::recent( 8 ) as $r ) {
				printf( '<tr><td>#%d</td><td>%s</td><td><span class="ci-badge ci-badge--%s">%s</span></td><td style="text-align:right">%s</td></tr>', (int) $r->order_id, esc_html( get_date_from_gmt( $r->created_at, 'd M' ) ), esc_attr( $r->status ), esc_html( $r->status ), esc_html( Pricing::money( (float) $r->amount, true ) ) );
			}
		}
		echo '</tbody></table><p><a href="' . esc_url( admin_url( 'admin.php?page=cirilinfo-payments' ) ) . '">' . esc_html__( 'All transactions →', 'cirilinfo' ) . '</a></p></div></div>';

		echo '<div class="ci-two"><div class="ci-box"><h2>' . esc_html__( 'Latest enquiries', 'cirilinfo' ) . '</h2><table class="widefat striped"><tbody>';
		foreach ( get_posts( array( 'post_type' => 'ci_enquiry', 'numberposts' => 6 ) ) as $e ) {
			$s = (string) get_post_meta( $e->ID, '_ci_status', true );
			printf( '<tr><td><a href="%s">%s</a></td><td><span class="ci-badge ci-badge--%s">%s</span></td></tr>', esc_url( get_edit_post_link( $e->ID ) ), esc_html( $e->post_title ), esc_attr( $s ), esc_html( $s ) );
		}
		echo '</tbody></table></div><div class="ci-box"><h2>' . esc_html__( 'Projects needing attention', 'cirilinfo' ) . '</h2><table class="widefat striped"><tbody>';
		foreach ( get_posts( array( 'post_type' => 'ci_project', 'numberposts' => 6, 'meta_query' => array( array( 'key' => '_ci_status', 'value' => array( 'confirmed', 'awaiting_requirements', 'review' ), 'compare' => 'IN' ) ) ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$s = (string) get_post_meta( $p->ID, '_ci_status', true );
			printf( '<tr><td><a href="%s">%s</a></td><td><span class="ci-badge ci-badge--%s">%s</span></td></tr>', esc_url( get_edit_post_link( $p->ID ) ), esc_html( $p->post_title ), esc_attr( $s ), esc_html( Post_Types::project_statuses()[ $s ] ?? $s ) );
		}
		echo '</tbody></table></div></div></div>';
	}

	public static function wp_dashboard_widget(): void {
		if ( current_user_can( 'manage_cirilinfo' ) ) {
			wp_add_dashboard_widget( 'cirilinfo_widget', 'CirilInfo', static function () {
				$c = self::counts();
				printf( '<p><strong>%d</strong> %s · <strong>%d</strong> %s · <strong>%d</strong> %s</p><p><a class="button button-primary" href="%s">%s</a></p>', (int) $c['enquiries'], esc_html__( 'new enquiries', 'cirilinfo' ), (int) $c['projects'], esc_html__( 'active projects', 'cirilinfo' ), (int) $c['pending_pay'], esc_html__( 'pending payments', 'cirilinfo' ), esc_url( admin_url( 'admin.php?page=cirilinfo' ) ), esc_html__( 'Open overview', 'cirilinfo' ) );
			} );
		}
	}

	/* ------------------------------------------------- Enquiry & ticket UI */

	public static function enquiry_boxes(): void {
		add_meta_box( 'ci_enq', __( 'Enquiry', 'cirilinfo' ), array( __CLASS__, 'box_enquiry' ), 'ci_enquiry', 'normal', 'high' );
	}

	public static function box_enquiry( \WP_Post $post ): void {
		wp_nonce_field( 'ci_enq_save', '_ci_enq_nonce' );
		$m = static fn( $k ) => (string) get_post_meta( $post->ID, $k, true );
		$s = $m( '_ci_status' ) ?: 'new';
		$svc = (int) $m( '_ci_service_id' );
		$pkg = (int) $m( '_ci_package_id' );
		echo '<table class="form-table"><tbody>';
		$rows = array(
			__( 'Type', 'cirilinfo' )    => ucfirst( $m( '_ci_type' ) ),
			__( 'Name', 'cirilinfo' )    => $m( '_ci_name' ),
			__( 'Email', 'cirilinfo' )   => '<a href="mailto:' . esc_attr( $m( '_ci_email' ) ) . '">' . esc_html( $m( '_ci_email' ) ) . '</a>',
			__( 'Phone', 'cirilinfo' )   => esc_html( $m( '_ci_phone' ) ) . ( $m( '_ci_phone' ) ? ' · <a target="_blank" rel="noopener" href="https://wa.me/' . esc_attr( preg_replace( '/\D/', '', $m( '_ci_phone' ) ) ) . '">WhatsApp</a>' : '' ),
			__( 'Service', 'cirilinfo' ) => esc_html( $svc ? get_the_title( $svc ) : '—' ) . ( $pkg ? ' / ' . esc_html( get_the_title( $pkg ) ) : '' ),
			__( 'Budget', 'cirilinfo' )  => esc_html( $m( '_ci_budget' ) ?: '—' ),
			__( 'Timeline', 'cirilinfo' ) => esc_html( $m( '_ci_timeline' ) ?: '—' ),
			__( 'Message', 'cirilinfo' ) => nl2br( esc_html( $m( '_ci_message' ) ) ),
			__( 'Source page', 'cirilinfo' ) => esc_html( $m( '_ci_source_url' ) ),
		);
		foreach ( $rows as $k => $v ) {
			$is_html = in_array( $k, array( __( 'Email', 'cirilinfo' ), __( 'Phone', 'cirilinfo' ), __( 'Message', 'cirilinfo' ), __( 'Service', 'cirilinfo' ), __( 'Budget', 'cirilinfo' ), __( 'Timeline', 'cirilinfo' ), __( 'Source page', 'cirilinfo' ) ), true );
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . ( $is_html ? $v : esc_html( $v ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values escaped above.
		}
		echo '<tr><th>' . esc_html__( 'Files', 'cirilinfo' ) . '</th><td>' . ( Uploads::render_list( $post->ID, '_ci_files' ) ?: '—' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th>' . esc_html__( 'Status', 'cirilinfo' ) . '</th><td><select name="ci_enq_status">';
		foreach ( Post_Types::enquiry_statuses() as $k => $l ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $s, $k, false ), esc_html( $l ) );
		}
		echo '</select></td></tr><tr><th>' . esc_html__( 'Internal notes', 'cirilinfo' ) . '</th><td><textarea name="ci_enq_notes" rows="4" class="large-text">' . esc_textarea( $m( '_ci_notes' ) ) . '</textarea></td></tr></tbody></table>';
	}

	public static function save_enquiry( int $post_id, \WP_Post $post ): void {
		$nonce = isset( $_POST['_ci_enq_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ci_enq_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ci_enq_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$status = isset( $_POST['ci_enq_status'] ) ? sanitize_key( wp_unslash( $_POST['ci_enq_status'] ) ) : 'new';
		if ( isset( Post_Types::enquiry_statuses()[ $status ] ) ) {
			update_post_meta( $post_id, '_ci_status', $status );
		}
		update_post_meta( $post_id, '_ci_notes', isset( $_POST['ci_enq_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ci_enq_notes'] ) ) : '' );
	}

	public static function ticket_boxes(): void {
		add_meta_box( 'ci_tkt', __( 'Ticket', 'cirilinfo' ), array( __CLASS__, 'box_ticket' ), 'ci_ticket', 'normal', 'high' );
	}

	public static function box_ticket( \WP_Post $post ): void {
		wp_nonce_field( 'ci_tkt_save', '_ci_tkt_nonce' );
		$s    = (string) get_post_meta( $post->ID, '_ci_status', true ) ?: 'open';
		$u    = get_userdata( (int) $post->post_author );
		$proj = (int) get_post_meta( $post->ID, '_ci_project_id', true );
		echo '<p><strong>' . esc_html__( 'Customer', 'cirilinfo' ) . ':</strong> ' . ( $u ? esc_html( $u->display_name ) . ' &lt;' . esc_html( $u->user_email ) . '&gt;' : '—' ) . ' · <strong>' . esc_html__( 'Priority', 'cirilinfo' ) . ':</strong> ' . esc_html( (string) get_post_meta( $post->ID, '_ci_priority', true ) );
		if ( $proj ) {
			echo ' · <strong>' . esc_html__( 'Project', 'cirilinfo' ) . ':</strong> <a href="' . esc_url( get_edit_post_link( $proj ) ) . '">' . esc_html( get_the_title( $proj ) ) . '</a>';
		}
		echo '</p><p><label><strong>' . esc_html__( 'Status', 'cirilinfo' ) . '</strong> <select name="ci_tkt_status">';
		foreach ( Post_Types::ticket_statuses() as $k => $l ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $s, $k, false ), esc_html( $l ) );
		}
		echo '</select></label></p>';
		echo Threads::render( $post->ID ) . Uploads::render_list( $post->ID, '_ci_files' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p><textarea name="ci_tkt_reply" rows="4" class="large-text" placeholder="' . esc_attr__( 'Reply to customer (emailed on Update)…', 'cirilinfo' ) . '"></textarea></p>';
	}

	public static function save_ticket( int $post_id, \WP_Post $post ): void {
		$nonce = isset( $_POST['_ci_tkt_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ci_tkt_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ci_tkt_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$reply  = isset( $_POST['ci_tkt_reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ci_tkt_reply'] ) ) : '';
		$status = isset( $_POST['ci_tkt_status'] ) ? sanitize_key( wp_unslash( $_POST['ci_tkt_status'] ) ) : 'open';
		if ( ! isset( Post_Types::ticket_statuses()[ $status ] ) ) {
			$status = 'open';
		}
		if ( '' !== trim( $reply ) ) {
			Threads::add( $post_id, get_current_user_id(), $reply, true );
			if ( 'open' === $status ) {
				$status = 'pending';
			}
			$u = get_userdata( (int) $post->post_author );
			if ( $u ) {
				Mailer::send( 'ticket_reply', $u->user_email, array(
					'customer_name' => $u->first_name ?: $u->display_name,
					'ticket_title'  => $post->post_title,
					'ticket_id'     => (string) $post_id,
					'message'       => $reply,
					'ticket_url'    => trailingslashit( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'support' ) : home_url( '/my-account/support/' ) ) . $post_id . '/',
				) );
				Notifications::add( $u->ID, 'Reply on ticket #' . $post_id . ': ' . $post->post_title );
			}
		}
		update_post_meta( $post_id, '_ci_status', $status );
	}

	public static function ops_columns( array $cols ): array {
		$cols['ci_status'] = __( 'Status', 'cirilinfo' );
		$cols['ci_who']    = __( 'From', 'cirilinfo' );
		unset( $cols['date'] );
		$cols['date'] = __( 'Date', 'cirilinfo' );
		return $cols;
	}

	public static function ops_column( string $col, int $post_id ): void {
		if ( 'ci_status' === $col ) {
			$s = (string) get_post_meta( $post_id, '_ci_status', true );
			echo '<span class="ci-badge ci-badge--' . esc_attr( $s ) . '">' . esc_html( $s ) . '</span>';
		} elseif ( 'ci_who' === $col ) {
			$name = (string) get_post_meta( $post_id, '_ci_name', true );
			if ( ! $name ) {
				$u    = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
				$name = $u ? $u->display_name : '';
			}
			echo esc_html( $name ) . '<br><small>' . esc_html( (string) get_post_meta( $post_id, '_ci_email', true ) ) . '</small>';
		}
	}

	/* ----------------------------------------------------------- Setup */

	public static function setup_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || get_option( 'cirilinfo_setup_done' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'cirilinfo_page_cirilinfo-setup' === $screen->id ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>CirilInfo:</strong> ' . esc_html__( 'Run the guided setup to create pages, menus, services, packages and WooCommerce settings.', 'cirilinfo' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=cirilinfo-setup' ) ) . '">' . esc_html__( 'Open setup', 'cirilinfo' ) . '</a></p></div>';
	}

	/** @return array<int,array{0:string,1:string,2:string}> label, state(ok|warn|fail), detail */
	public static function checks(): array {
		$c   = array();
		$add = static function ( string $label, string $state, string $detail = '' ) use ( &$c ) {
			$c[] = array( $label, $state, $detail );
		};
		$add( 'PHP ≥ 8.1', version_compare( PHP_VERSION, '8.1', '>=' ) ? 'ok' : 'fail', PHP_VERSION );
		$add( 'WooCommerce active', Plugin::wc_active() ? 'ok' : 'fail', Plugin::wc_active() ? 'v' . WC()->version : 'Install & activate WooCommerce' );
		$add( 'Pretty permalinks', get_option( 'permalink_structure' ) ? 'ok' : 'fail', get_option( 'permalink_structure' ) ?: 'Set Settings → Permalinks → Post name' );
		$add( 'HTTPS', is_ssl() ? 'ok' : 'warn', is_ssl() ? '' : 'Enable SSL before accepting live payments' );
		$add( 'Search engines visible', get_option( 'blog_public' ) ? 'ok' : 'warn', get_option( 'blog_public' ) ? '' : 'Site is set to discourage indexing (Settings → Reading)' );
		$add( 'Services & packages', wp_count_posts( 'ci_service' )->publish > 0 && wp_count_posts( 'ci_package' )->publish > 0 ? 'ok' : 'warn', 'Run "Import services & packages" below' );
		$add( 'Company contact details', Settings::get( 'phone' ) && Settings::get( 'email' ) ? 'ok' : 'warn', 'CirilInfo → Settings → Company' );
		$add( 'SMTP configured', Settings::get( 'smtp_enabled' ) && Settings::get( 'smtp_host' ) ? 'ok' : 'warn', 'CirilInfo → Settings → Email & SMTP' );
		$add( 'Private uploads protected', file_exists( Uploads::base_dir()['path'] . '/.htaccess' ) ? 'ok' : 'warn', 'On Nginx add the deny rule from docs/INSTALL.md' );
		$add( 'Analytics IDs', Settings::get( 'ga4_id' ) ? 'ok' : 'warn', 'Optional: GA4 / Meta Pixel in Settings' );
		if ( Plugin::wc_active() ) {
			$gw    = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
			$found = array();
			foreach ( $gw as $id => $g ) {
				if ( 'yes' === $g->enabled ) {
					$found[] = $g->get_method_title();
				}
			}
			$has_razor = isset( $gw['razorpay'] );
			$add( 'Razorpay gateway installed', $has_razor ? 'ok' : 'warn', $has_razor ? ( 'yes' === $gw['razorpay']->enabled ? 'Enabled' : 'Installed – enable & add keys' ) : 'Install "Razorpay for WooCommerce"' );
			$add( 'Enabled payment methods', $found ? 'ok' : 'fail', $found ? implode( ', ', $found ) : 'None enabled' );
			$add( 'GST rate synced to WooCommerce', get_option( 'cirilinfo_gst_rate_id' ) ? 'ok' : 'warn', get_option( 'cirilinfo_gst_rate_id' ) ? '' : 'Run "Configure WooCommerce"' );
			$checkout = (int) wc_get_page_id( 'checkout' );
			$content  = $checkout > 0 ? (string) get_post_field( 'post_content', $checkout ) : '';
			$add( 'Classic checkout shortcode', false !== strpos( $content, '[woocommerce_checkout]' ) ? 'ok' : 'warn', 'Checkout page must use [woocommerce_checkout] (Run "Configure WooCommerce")' );
		}
		return $c;
	}

	public static function render_setup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ) );
		}
		echo '<div class="wrap ci-admin"><h1>' . esc_html__( 'CirilInfo Setup', 'cirilinfo' ) . '</h1>';
		if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['done'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		echo '<div class="ci-box"><h2>' . esc_html__( 'Health check', 'cirilinfo' ) . '</h2><table class="widefat striped"><tbody>';
		foreach ( self::checks() as $c ) {
			printf( '<tr><td style="width:36px"><span class="ci-dot ci-dot--%s"></span></td><td>%s</td><td class="description">%s</td></tr>', esc_attr( $c[1] ), esc_html( $c[0] ), esc_html( $c[2] ) );
		}
		echo '</tbody></table></div>';

		$actions = array(
			'pages'    => array( __( '1. Create pages, menus & legal pages', 'cirilinfo' ), __( 'Creates Home, About, Services, Pricing, Portfolio, Blog, Contact, FAQ, Terms, Privacy, Refund pages, the navigation menus and sets the front page. Existing pages with the same slug are left untouched.', 'cirilinfo' ) ),
			'woo'      => array( __( '2. Configure WooCommerce for CirilInfo', 'cirilinfo' ), __( 'Sets INR currency, guest checkout + account creation, coupons, classic [woocommerce_checkout]/[woocommerce_cart] pages, terms page, and syncs the GST rate to the tax table.', 'cirilinfo' ) ),
			'services' => array( __( '3. Import services, packages, add-ons & FAQs', 'cirilinfo' ), __( 'Adds 14 services with 3 indicative packages each (prices are placeholders – edit under CirilInfo → Packages). Skips anything that already exists.', 'cirilinfo' ) ),
			'demo'     => array( __( 'Import labelled demo content (optional)', 'cirilinfo' ), __( 'Adds clearly marked SAMPLE testimonials, portfolio items and client names so you can preview the layout. Remove before launch.', 'cirilinfo' ) ),
			'demo_off' => array( __( 'Remove demo content', 'cirilinfo' ), __( 'Deletes everything imported by the demo button.', 'cirilinfo' ) ),
		);
		echo '<div class="ci-box"><h2>' . esc_html__( 'Guided setup', 'cirilinfo' ) . '</h2>';
		foreach ( $actions as $key => $a ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ci-setup-row">';
			wp_nonce_field( 'ci_setup_' . $key );
			echo '<input type="hidden" name="action" value="ci_setup"><input type="hidden" name="step" value="' . esc_attr( $key ) . '"><div><strong>' . esc_html( $a[0] ) . '</strong><p class="description">' . esc_html( $a[1] ) . '</p></div><button class="button ' . ( 'demo_off' === $key ? '' : 'button-primary' ) . '">' . esc_html__( 'Run', 'cirilinfo' ) . '</button></form>';
		}
		echo '</div></div>';
	}

	public static function handle_setup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ), 403 );
		}
		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		check_admin_referer( 'ci_setup_' . $step );
		$msg = '';
		switch ( $step ) {
			case 'pages':
				$n   = Seeder::pages_and_menus();
				$msg = sprintf( 'Created/updated %d pages and the navigation menus.', $n );
				break;
			case 'woo':
				if ( ! Plugin::wc_active() ) {
					$msg = 'WooCommerce is not active.';
					break;
				}
				Seeder::configure_woocommerce();
				$msg = 'WooCommerce configured and GST synced.';
				break;
			case 'services':
				$r   = Seeder::services_and_packages();
				$msg = sprintf( 'Imported %d services, %d packages and %d FAQs.', $r['services'], $r['packages'], $r['faqs'] );
				update_option( 'cirilinfo_setup_done', 1 );
				break;
			case 'demo':
				$msg = sprintf( 'Imported %d demo items (labelled SAMPLE).', Seeder::demo( true ) );
				break;
			case 'demo_off':
				$msg = sprintf( 'Removed %d demo items.', Seeder::demo( false ) );
				break;
			default:
				wp_die( esc_html__( 'Unknown step.', 'cirilinfo' ), 400 );
		}
		wp_safe_redirect( add_query_arg( 'done', rawurlencode( $msg ), admin_url( 'admin.php?page=cirilinfo-setup' ) ) );
		exit;
	}
}
