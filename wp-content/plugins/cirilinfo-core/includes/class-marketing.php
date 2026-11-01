<?php
/**
 * Newsletter subscribers + email campaigns (queued, batched via WP-Cron).
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Marketing {

	const QUEUE_OPTION = 'cirilinfo_campaigns';
	const BATCH        = 40;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ci_subscribers';
	}

	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'ci_campaign_tick', array( __CLASS__, 'tick' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_links' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_ci_campaign_send', array( __CLASS__, 'handle_send' ) );
		add_action( 'admin_post_ci_subscribers_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_ci_subscriber_delete', array( __CLASS__, 'handle_delete' ) );
		if ( ! wp_next_scheduled( 'ci_campaign_tick' ) ) {
			wp_schedule_event( time() + 120, 'ci_five_minutes', 'ci_campaign_tick' );
		}
	}

	public static function schedules( array $s ): array {
		$s['ci_five_minutes'] = array( 'interval' => 300, 'display' => __( 'Every 5 minutes', 'cirilinfo' ) );
		return $s;
	}

	public static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta( "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  email varchar(190) NOT NULL,
  name varchar(190) NOT NULL DEFAULT '',
  status varchar(16) NOT NULL DEFAULT 'pending',
  source varchar(32) NOT NULL DEFAULT 'website',
  token varchar(64) NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  KEY status (status)
) {$charset};" );
	}

	/* ------------------------------------------------------- Subscribers */

	public static function subscribe( string $email, string $name = '', string $source = 'website', bool $confirmed = false ): void {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return;
		}
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$now   = current_time( 'mysql', true );

		if ( $row && 'subscribed' === $row->status ) {
			return;
		}
		$token  = $row ? $row->token : wp_generate_password( 32, false );
		$status = $confirmed ? 'subscribed' : 'pending';
		if ( $row ) {
			$wpdb->update( $table, array( 'status' => $status, 'updated_at' => $now, 'name' => $name ?: $row->name ), array( 'id' => $row->id ) );
			$id = (int) $row->id;
		} else {
			$wpdb->insert( $table, array( 'email' => $email, 'name' => $name, 'status' => $status, 'source' => $source, 'token' => $token, 'created_at' => $now, 'updated_at' => $now ) );
			$id = (int) $wpdb->insert_id;
		}
		if ( ! $confirmed && $id ) {
			Mailer::send( 'newsletter_confirm', $email, array( 'confirm_url' => self::link( 'confirm', $id, $token ) ) );
		}
	}

	private static function link( string $action, int $id, string $token ): string {
		return add_query_arg( array( 'ci_newsletter' => $action, 'e' => $id, 't' => $token ), home_url( '/' ) );
	}

	public static function handle_links(): void {
		if ( empty( $_GET['ci_newsletter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		global $wpdb;
		$action = sanitize_key( wp_unslash( $_GET['ci_newsletter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$id     = absint( $_GET['e'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$token  = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$table  = self::table();
		$row    = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $row || ! hash_equals( (string) $row->token, $token ) || ! in_array( $action, array( 'confirm', 'unsubscribe' ), true ) ) {
			wp_die( esc_html__( 'This link is invalid or has expired.', 'cirilinfo' ), esc_html__( 'Newsletter', 'cirilinfo' ), array( 'response' => 400, 'back_link' => true ) );
		}
		$status = 'confirm' === $action ? 'subscribed' : 'unsubscribed';
		$wpdb->update( $table, array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		$msg = 'confirm' === $action ? __( 'Your subscription is confirmed. Thank you!', 'cirilinfo' ) : __( 'You have been unsubscribed. Sorry to see you go.', 'cirilinfo' );
		wp_die( esc_html( $msg ), esc_html__( 'Newsletter', 'cirilinfo' ), array( 'response' => 200, 'link_url' => esc_url( home_url( '/' ) ), 'link_text' => esc_html__( 'Back to website', 'cirilinfo' ) ) );
	}

	public static function counts(): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$table} GROUP BY status", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array(
			'subscribed'   => (int) ( $rows['subscribed']->c ?? 0 ),
			'pending'      => (int) ( $rows['pending']->c ?? 0 ),
			'unsubscribed' => (int) ( $rows['unsubscribed']->c ?? 0 ),
		);
	}

	/* ---------------------------------------------------------- Campaigns */

	private static function audience( string $which ): array {
		global $wpdb;
		$table = self::table();
		$where = "status = 'subscribed'";
		if ( 'customers' === $which ) {
			$where .= " AND source = 'checkout'";
		} elseif ( 'newsletter' === $which ) {
			$where .= " AND source <> 'checkout'";
		}
		return (array) $wpdb->get_results( "SELECT id, email, name, token FROM {$table} WHERE {$where}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function handle_send(): void {
		if ( ! current_user_can( 'manage_cirilinfo' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ), 403 );
		}
		check_admin_referer( 'ci_campaign_send' );
		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body     = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$audience = isset( $_POST['audience'] ) ? sanitize_key( wp_unslash( $_POST['audience'] ) ) : 'all';
		$test     = ! empty( $_POST['test'] );
		if ( '' === $subject || '' === trim( wp_strip_all_tags( $body ) ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=cirilinfo-marketing&tab=campaigns&err=1' ) );
			exit;
		}
		if ( $test ) {
			$u = wp_get_current_user();
			Mailer::send_raw( $u->user_email, '[TEST] ' . $subject, $body );
			wp_safe_redirect( admin_url( 'admin.php?page=cirilinfo-marketing&tab=campaigns&test=1' ) );
			exit;
		}
		$recipients = self::audience( $audience );
		$all        = get_option( self::QUEUE_OPTION, array() );
		$all        = is_array( $all ) ? $all : array();
		$all[]      = array(
			'id'         => wp_generate_uuid4(),
			'subject'    => $subject,
			'body'       => $body,
			'audience'   => $audience,
			'total'      => count( $recipients ),
			'queue'      => $recipients,
			'sent'       => 0,
			'status'     => $recipients ? 'sending' : 'done',
			'created_at' => time(),
		);
		update_option( self::QUEUE_OPTION, array_slice( $all, -30 ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=cirilinfo-marketing&tab=campaigns&queued=' . count( $recipients ) ) );
		exit;
	}

	/** Cron worker: send one batch of the oldest unfinished campaign. */
	public static function tick(): void {
		$all = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $all ) ) {
			return;
		}
		foreach ( $all as &$c ) {
			if ( 'sending' !== ( $c['status'] ?? '' ) ) {
				continue;
			}
			$batch = array_splice( $c['queue'], 0, self::BATCH );
			foreach ( $batch as $r ) {
				$unsub = self::link( 'unsubscribe', (int) $r['id'], (string) $r['token'] );
				$html  = $c['body'] . '<hr style="border:0;border-top:1px solid #e5e8f2;margin:24px 0"><p style="font-size:12px;color:#6b7390">You receive this because you subscribed to ' . esc_html( (string) Settings::get( 'company_name' ) ) . '. <a href="' . esc_url( $unsub ) . '">Unsubscribe</a></p>';
				if ( Mailer::send_raw( $r['email'], $c['subject'], $html ) ) {
					++$c['sent'];
				}
			}
			if ( empty( $c['queue'] ) ) {
				$c['status'] = 'done';
			}
			break; // One batch per tick.
		}
		unset( $c );
		update_option( self::QUEUE_OPTION, $all, false );
	}

	/* -------------------------------------------------------------- Admin */

	public static function menu(): void {
		add_submenu_page( 'cirilinfo', __( 'Marketing', 'cirilinfo' ), __( 'Marketing', 'cirilinfo' ), 'manage_cirilinfo', 'cirilinfo-marketing', array( __CLASS__, 'render' ) );
	}

	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_cirilinfo' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ), 403 );
		}
		check_admin_referer( 'ci_subscribers_export' );
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT email, name, status, source, created_at FROM {$table} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscribers-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'email', 'name', 'status', 'source', 'created_at' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), $r ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Neutralise spreadsheet formula injection. */
	public static function csv_safe( $v ): string {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_cirilinfo' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ), 403 );
		}
		$id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'ci_sub_del_' . $id );
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=cirilinfo-marketing' ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_cirilinfo' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ) );
		}
		global $wpdb;
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'subscribers'; // phpcs:ignore WordPress.Security.NonceVerification
		$c   = self::counts();
		echo '<div class="wrap ci-admin"><h1>' . esc_html__( 'Marketing', 'cirilinfo' ) . '</h1>';
		echo '<p>' . sprintf( /* translators: 1: subscribers, 2: pending */ esc_html__( '%1$d subscribed · %2$d awaiting confirmation. Coupons and discounts are managed in', 'cirilinfo' ), (int) $c['subscribed'], (int) $c['pending'] ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ) . '">' . esc_html__( 'Marketing → Coupons', 'cirilinfo' ) . '</a>.</p>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( array( 'subscribers' => __( 'Subscribers', 'cirilinfo' ), 'campaigns' => __( 'Campaigns', 'cirilinfo' ) ) as $k => $l ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', esc_attr( $k === $tab ? 'nav-tab-active' : '' ), esc_url( admin_url( 'admin.php?page=cirilinfo-marketing&tab=' . $k ) ), esc_html( $l ) );
		}
		echo '</nav>';

		if ( 'campaigns' === $tab ) {
			if ( isset( $_GET['queued'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Campaign queued for %d recipients. Emails are sent in batches by WP-Cron.', 'cirilinfo' ), absint( $_GET['queued'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
			}
			if ( isset( $_GET['test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Test email sent to you.', 'cirilinfo' ) . '</p></div>';
			}
			if ( isset( $_GET['err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Subject and message are required.', 'cirilinfo' ) . '</p></div>';
			}
			echo '<h2>' . esc_html__( 'New campaign', 'cirilinfo' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:800px">';
			wp_nonce_field( 'ci_campaign_send' );
			echo '<input type="hidden" name="action" value="ci_campaign_send">';
			echo '<p><input class="large-text" name="subject" placeholder="' . esc_attr__( 'Subject', 'cirilinfo' ) . '" required></p>';
			echo '<p><select name="audience"><option value="all">' . esc_html__( 'All subscribers', 'cirilinfo' ) . '</option><option value="newsletter">' . esc_html__( 'Newsletter subscribers only', 'cirilinfo' ) . '</option><option value="customers">' . esc_html__( 'Customers who opted in at checkout', 'cirilinfo' ) . '</option></select></p>';
			wp_editor( '', 'ci_campaign_body', array( 'textarea_name' => 'body', 'textarea_rows' => 12, 'media_buttons' => false ) );
			echo '<p><button class="button button-primary">' . esc_html__( 'Queue for sending', 'cirilinfo' ) . '</button> <button class="button" name="test" value="1">' . esc_html__( 'Send test to me', 'cirilinfo' ) . '</button></p></form>';
			echo '<h2>' . esc_html__( 'History', 'cirilinfo' ) . '</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Subject</th><th>Audience</th><th>Sent</th><th>Status</th></tr></thead><tbody>';
			$all = array_reverse( (array) get_option( self::QUEUE_OPTION, array() ) );
			foreach ( $all as $camp ) {
				printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%d / %d</td><td>%s</td></tr>', esc_html( wp_date( 'd M Y H:i', (int) $camp['created_at'] ) ), esc_html( $camp['subject'] ), esc_html( $camp['audience'] ), (int) $camp['sent'], (int) $camp['total'], esc_html( $camp['status'] ) );
			}
			if ( ! $all ) {
				echo '<tr><td colspan="5">' . esc_html__( 'No campaigns yet.', 'cirilinfo' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			$table = self::table();
			$paged = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$per   = 30;
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per, ( $paged - 1 ) * $per ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ci_subscribers_export' ), 'ci_subscribers_export' ) ) . '">' . esc_html__( 'Export CSV', 'cirilinfo' ) . '</a></p>';
			echo '<table class="widefat striped"><thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Source</th><th>Joined</th><th></th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=ci_subscriber_delete&id=' . (int) $r->id ), 'ci_sub_del_' . (int) $r->id );
				printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" onclick="return confirm(\'Delete?\')">%s</a></td></tr>', esc_html( $r->email ), esc_html( $r->name ), esc_html( $r->status ), esc_html( $r->source ), esc_html( get_date_from_gmt( $r->created_at, 'd M Y' ) ), esc_url( $del ), esc_html__( 'Delete', 'cirilinfo' ) );
			}
			if ( ! $rows ) {
				echo '<tr><td colspan="6">' . esc_html__( 'No subscribers yet.', 'cirilinfo' ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( (string) paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => max( 1, (int) ceil( $total / $per ) ) ) ) ) . '</div></div>';
		}
		echo '</div>';
	}
}
