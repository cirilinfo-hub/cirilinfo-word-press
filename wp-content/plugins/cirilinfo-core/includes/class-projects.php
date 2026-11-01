<?php
/**
 * Project workflow: order -> project, statuses, milestones, deliverables, customer comms.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Projects {

	public static function init(): void {
		add_action( 'add_meta_boxes_ci_project', array( __CLASS__, 'add_boxes' ) );
		add_action( 'save_post_ci_project', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_ci_project_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_ci_project_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'status_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		add_action( 'post_edit_form_tag', array( __CLASS__, 'multipart' ), 10, 1 );

		if ( Plugin::wc_active() ) {
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_created' ), 20, 1 );
			add_action( 'woocommerce_new_order', array( __CLASS__, 'on_order_created' ), 30, 1 );
			add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_order_status' ), 20, 4 );
		}
	}

	/** File inputs in the project screen need a multipart form. */
	public static function multipart( $post = null ): void {
		if ( $post instanceof \WP_Post && 'ci_project' === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	/* ------------------------------------------------------ Ownership */

	public static function order_for( int $project_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}
		$oid = (int) get_post_meta( $project_id, '_ci_order_id', true );
		return $oid ? wc_get_order( $oid ) : false;
	}

	public static function user_owns( int $project_id, int $user_id ): bool {
		$order = self::order_for( $project_id );
		if ( ! $order || $user_id <= 0 ) {
			return false;
		}
		if ( (int) $order->get_customer_id() === $user_id ) {
			return true;
		}
		// Guest checkout order claimed by the same (verified) email.
		$user = get_userdata( $user_id );
		return $user && 0 === (int) $order->get_customer_id()
			&& '0' !== (string) get_user_meta( $user_id, '_ci_email_verified', true )
			&& strtolower( $order->get_billing_email() ) === strtolower( $user->user_email );
	}

	/** @return \WP_Post[] */
	public static function for_user( int $user_id ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$user  = get_userdata( $user_id );
		$ids   = (array) wc_get_orders( array( 'customer_id' => $user_id, 'limit' => -1, 'return' => 'ids' ) );
		if ( $user && '0' !== (string) get_user_meta( $user_id, '_ci_email_verified', true ) ) {
			$ids = array_merge( $ids, (array) wc_get_orders( array( 'billing_email' => $user->user_email, 'customer_id' => 0, 'limit' => -1, 'return' => 'ids' ) ) );
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		return get_posts( array(
			'post_type'   => 'ci_project',
			'post_status' => 'publish',
			'numberposts' => 100,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array( array( 'key' => '_ci_order_id', 'value' => $ids, 'compare' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
	}

	/* --------------------------------------------- Order -> Project sync */

	public static function on_order_created( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::ensure_for_order( $order );
		}
	}

	public static function ensure_for_order( \WC_Order $order ): void {
		foreach ( $order->get_items() as $item_id => $item ) {
			$pkg_id = (int) $item->get_meta( '_ci_package_id' );
			if ( ! $pkg_id ) {
				continue;
			}
			$existing = get_posts( array(
				'post_type' => 'ci_project', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
				'meta_query' => array( array( 'key' => '_ci_order_item_id', 'value' => (int) $item_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			) );
			if ( $existing ) {
				continue;
			}
			$id = wp_insert_post( array(
				'post_type'   => 'ci_project',
				'post_status' => 'publish',
				'post_title'  => sprintf( '#%s – %s', $order->get_order_number(), $item->get_name() ),
			), true );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_ci_order_id', $order->get_id() );
			update_post_meta( $id, '_ci_order_item_id', (int) $item_id );
			update_post_meta( $id, '_ci_package_id', $pkg_id );
			update_post_meta( $id, '_ci_service_id', (int) get_post_meta( $pkg_id, '_ci_service_id', true ) );
			update_post_meta( $id, '_ci_requirements', (string) $order->get_meta( '_ci_requirements' ) ? 'Submitted at checkout (' . wp_date( 'd M Y' ) . "):\n" . $order->get_meta( '_ci_requirements' ) : '' );
			update_post_meta( $id, '_ci_status', $order->is_paid() ? 'new' : 'payment_pending' );
			self::log( $id, 'Project created from order #' . $order->get_order_number() );
			if ( $order->is_paid() ) {
				self::apply_paid_state( $id, $order );
			}
			do_action( 'cirilinfo_project_created', $id, $order );
		}
	}

	private static function apply_paid_state( int $project_id, \WC_Order $order ): void {
		$status = (string) get_post_meta( $project_id, '_ci_status', true );
		if ( ! in_array( $status, array( 'new', 'payment_pending', 'awaiting_requirements' ), true ) ) {
			return;
		}
		$has_req = '' !== trim( (string) get_post_meta( $project_id, '_ci_requirements', true ) );
		self::set_status( $project_id, $has_req ? 'confirmed' : 'awaiting_requirements', $has_req ? '' : __( 'Payment received. Please submit your project requirements so we can start.', 'cirilinfo' ) );
	}

	public static function on_order_status( $order_id, $from, $to, $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		self::ensure_for_order( $order );
		$projects = get_posts( array( 'post_type' => 'ci_project', 'post_status' => 'any', 'numberposts' => -1, 'meta_query' => array( array( 'key' => '_ci_order_id', 'value' => $order->get_id() ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $projects as $p ) {
			$current = (string) get_post_meta( $p->ID, '_ci_status', true );
			if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
				self::apply_paid_state( $p->ID, $order );
			} elseif ( in_array( $to, array( 'cancelled', 'refunded' ), true ) && ! in_array( $current, array( 'delivered', 'completed', 'cancelled' ), true ) ) {
				self::set_status( $p->ID, 'cancelled', sprintf( /* translators: %s: order status */ __( 'Order %s.', 'cirilinfo' ), wc_get_order_status_name( $to ) ) );
			} elseif ( in_array( $to, array( 'pending', 'on-hold', 'failed' ), true ) && 'new' === $current ) {
				self::set_status( $p->ID, 'payment_pending' );
			}
		}
	}

	/* ------------------------------------------------------- Operations */

	public static function log( int $project_id, string $text ): void {
		$h   = get_post_meta( $project_id, '_ci_history', true );
		$h   = is_array( $h ) ? $h : array();
		$h[] = array( 't' => time(), 'u' => get_current_user_id(), 'm' => wp_strip_all_tags( $text ) );
		update_post_meta( $project_id, '_ci_history', array_slice( $h, -100 ) );
	}

	public static function customer_info( int $project_id ): array {
		$order = self::order_for( $project_id );
		if ( ! $order ) {
			return array( 'id' => 0, 'name' => '', 'email' => '' );
		}
		return array(
			'id'    => (int) $order->get_customer_id(),
			'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email' => $order->get_billing_email(),
		);
	}

	public static function project_url( int $project_id ): string {
		$base = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'projects' ) : home_url( '/my-account/projects/' );
		return trailingslashit( $base ) . $project_id . '/';
	}

	/** Change status, log it, and notify the customer. */
	public static function set_status( int $project_id, string $new, string $note = '', bool $notify = true ): void {
		$statuses = Post_Types::project_statuses();
		if ( ! isset( $statuses[ $new ] ) ) {
			return;
		}
		$old = (string) get_post_meta( $project_id, '_ci_status', true );
		if ( $old === $new ) {
			return;
		}
		update_post_meta( $project_id, '_ci_status', $new );
		self::log( $project_id, sprintf( 'Status: %s → %s%s', $statuses[ $old ] ?? '—', $statuses[ $new ], $note ? ' (' . $note . ')' : '' ) );
		do_action( 'cirilinfo_project_status_changed', $project_id, $new, $old );

		if ( ! $notify || in_array( $new, array( 'new', 'payment_pending' ), true ) ) {
			return;
		}
		$cust = self::customer_info( $project_id );
		if ( ! $cust['email'] ) {
			return;
		}
		$done = in_array( $new, array( 'completed', 'delivered' ), true );
		Mailer::send( $done ? 'project_complete' : 'project_update', $cust['email'], array(
			'customer_name' => $cust['name'] ?: __( 'there', 'cirilinfo' ),
			'project_title' => get_the_title( $project_id ),
			'status'        => $statuses[ $new ],
			'note'          => $note,
			'project_url'   => self::project_url( $project_id ),
			'feedback_url'  => home_url( '/#feedback' ),
		) );
		Notifications::add( $cust['id'], sprintf( '%s: %s', get_the_title( $project_id ), $statuses[ $new ] ), self::project_url( $project_id ) );
	}

	public static function add_requirements( int $project_id, string $text ): void {
		$cur = (string) get_post_meta( $project_id, '_ci_requirements', true );
		update_post_meta( $project_id, '_ci_requirements', trim( $cur . "\n\n" . 'Submitted ' . wp_date( 'd M Y, H:i' ) . ":\n" . $text ) );
		self::log( $project_id, 'Customer submitted requirements' );
		$order = self::order_for( $project_id );
		if ( 'awaiting_requirements' === get_post_meta( $project_id, '_ci_status', true ) && $order && $order->is_paid() ) {
			self::set_status( $project_id, 'confirmed', '', false );
		}
	}

	public static function notify_team( int $project_id, string $title, string $html ): void {
		$url = admin_url( 'post.php?post=' . $project_id . '&action=edit' );
		Mailer::notify_admin( $title, $html, $url );
		$assignee = (int) get_post_meta( $project_id, '_ci_assigned_to', true );
		if ( $assignee ) {
			$u = get_userdata( $assignee );
			if ( $u && $u->user_email !== Settings::notify_email() ) {
				Mailer::send_raw( $u->user_email, $title, $html . '<p><a href="' . esc_url( $url ) . '">Open project</a></p>' );
			}
		}
	}

	public static function progress( int $project_id ): int {
		$status = (string) get_post_meta( $project_id, '_ci_status', true );
		if ( in_array( $status, array( 'completed', 'delivered' ), true ) ) {
			return 100;
		}
		$ms = get_post_meta( $project_id, '_ci_milestones', true );
		if ( is_array( $ms ) && $ms ) {
			$done = count( array_filter( $ms, static fn( $m ) => ! empty( $m['done'] ) ) );
			return (int) round( $done / count( $ms ) * 100 );
		}
		$map = array( 'payment_pending' => 0, 'awaiting_requirements' => 5, 'new' => 5, 'confirmed' => 10, 'assigned' => 15, 'in_progress' => 50, 'review' => 85 );
		return $map[ $status ] ?? 0;
	}

	/* ----------------------------------------------------- Admin screen */

	public static function team_users(): array {
		return get_users( array( 'role__in' => array( 'administrator', 'ci_manager', 'ci_team' ), 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) );
	}

	public static function add_boxes(): void {
		add_meta_box( 'ci_proj_main', __( 'Project status & assignment', 'cirilinfo' ), array( __CLASS__, 'box_main' ), 'ci_project', 'side', 'high' );
		add_meta_box( 'ci_proj_ms', __( 'Milestones', 'cirilinfo' ), array( __CLASS__, 'box_milestones' ), 'ci_project', 'normal', 'high' );
		add_meta_box( 'ci_proj_req', __( 'Customer requirements', 'cirilinfo' ), array( __CLASS__, 'box_requirements' ), 'ci_project', 'normal', 'default' );
		add_meta_box( 'ci_proj_deliv', __( 'Deliverables', 'cirilinfo' ), array( __CLASS__, 'box_deliverables' ), 'ci_project', 'normal', 'default' );
		add_meta_box( 'ci_proj_thread', __( 'Messages with customer', 'cirilinfo' ), array( __CLASS__, 'box_thread' ), 'ci_project', 'normal', 'default' );
		add_meta_box( 'ci_proj_hist', __( 'Activity log', 'cirilinfo' ), array( __CLASS__, 'box_history' ), 'ci_project', 'side', 'low' );
	}

	public static function box_main( \WP_Post $post ): void {
		wp_nonce_field( 'ci_project_save', '_ci_project_nonce' );
		$status = (string) get_post_meta( $post->ID, '_ci_status', true ) ?: 'new';
		$assign = (int) get_post_meta( $post->ID, '_ci_assigned_to', true );
		$order  = self::order_for( $post->ID );
		$cust   = self::customer_info( $post->ID );
		$locked = Roles::is_team_only();
		echo '<p><label><strong>' . esc_html__( 'Status', 'cirilinfo' ) . '</strong><br><select name="ci_status" style="width:100%">';
		foreach ( Post_Types::project_statuses() as $k => $l ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $l ) );
		}
		echo '</select></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Status note (sent to customer)', 'cirilinfo' ) . '</strong><br><textarea name="ci_status_note" rows="2" style="width:100%"></textarea></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Assigned to', 'cirilinfo' ) . '</strong><br><select name="ci_assigned_to" style="width:100%" ' . ( $locked ? 'disabled' : '' ) . '><option value="0">—</option>';
		foreach ( self::team_users() as $u ) {
			printf( '<option value="%d" %s>%s</option>', (int) $u->ID, selected( $assign, (int) $u->ID, false ), esc_html( $u->display_name ) );
		}
		echo '</select></label></p>';
		echo '<p><label><input type="checkbox" name="ci_notify" value="1" checked> ' . esc_html__( 'Email customer about status changes', 'cirilinfo' ) . '</label></p>';
		echo '<hr><p><strong>' . esc_html__( 'Progress', 'cirilinfo' ) . ':</strong> ' . (int) self::progress( $post->ID ) . '%</p>';
		if ( $order ) {
			echo '<p><strong>' . esc_html__( 'Order', 'cirilinfo' ) . ':</strong> <a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a> (' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . ')</p>';
			echo '<p><strong>' . esc_html__( 'Customer', 'cirilinfo' ) . ':</strong><br>' . esc_html( $cust['name'] ) . '<br><a href="mailto:' . esc_attr( $cust['email'] ) . '">' . esc_html( $cust['email'] ) . '</a><br>' . esc_html( $order->get_billing_phone() ) . '</p>';
		}
	}

	public static function box_milestones( \WP_Post $post ): void {
		$ms = get_post_meta( $post->ID, '_ci_milestones', true );
		$ms = is_array( $ms ) && $ms ? $ms : array();
		echo '<div class="ci-repeater" data-name="ci_milestones"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Milestone', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Due date', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Done', 'cirilinfo' ) . '</th><th></th></tr></thead><tbody>';
		$row = static function ( $i, $m ) {
			return '<tr><td><input type="text" class="widefat" name="ci_milestones[' . esc_attr( (string) $i ) . '][label]" value="' . esc_attr( (string) ( $m['label'] ?? '' ) ) . '"></td>' .
				'<td><input type="date" name="ci_milestones[' . esc_attr( (string) $i ) . '][due]" value="' . esc_attr( (string) ( $m['due'] ?? '' ) ) . '"></td>' .
				'<td><input type="checkbox" name="ci_milestones[' . esc_attr( (string) $i ) . '][done]" value="1" ' . checked( ! empty( $m['done'] ), true, false ) . '></td>' .
				'<td><button type="button" class="button-link-delete ci-repeater-del">&times;</button></td></tr>';
		};
		foreach ( $ms as $i => $m ) {
			echo $row( $i, $m ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in closure.
		}
		echo '</tbody></table><template class="ci-repeater-tpl">' . $row( '__i__', array() ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p><button type="button" class="button ci-repeater-add">' . esc_html__( 'Add milestone', 'cirilinfo' ) . '</button></p></div>';
	}

	public static function box_requirements( \WP_Post $post ): void {
		$req = (string) get_post_meta( $post->ID, '_ci_requirements', true );
		echo $req ? '<div style="background:#f6f7fb;padding:12px;border-radius:6px;white-space:pre-wrap">' . esc_html( $req ) . '</div>' : '<p><em>' . esc_html__( 'No requirements submitted yet.', 'cirilinfo' ) . '</em></p>';
		echo Uploads::render_list( $post->ID, '_ci_req_files' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function box_deliverables( \WP_Post $post ): void {
		$list = get_post_meta( $post->ID, '_ci_deliverables', true );
		if ( is_array( $list ) && $list ) {
			echo '<ul>';
			foreach ( $list as $i => $f ) {
				printf( '<li><a href="%s">%s</a> (%s) &nbsp; <label><input type="checkbox" name="ci_remove_deliv[]" value="%d"> %s</label></li>', esc_url( Uploads::download_url( $post->ID, '_ci_deliverables', (int) $i ) ), esc_html( $f['name'] ), esc_html( size_format( (int) $f['size'] ) ), (int) $i, esc_html__( 'remove', 'cirilinfo' ) );
			}
			echo '</ul>';
		}
		echo '<p><label>' . esc_html__( 'Upload deliverables (visible to the customer):', 'cirilinfo' ) . '<br><input type="file" name="ci_deliverables[]" multiple></label></p>';
		echo '<p class="description">' . esc_html__( 'Allowed: images, PDF, Office docs, CSV, ZIP up to 10 MB each. Files are stored privately and only the customer and staff can download them.', 'cirilinfo' ) . '</p>';
	}

	public static function box_thread( \WP_Post $post ): void {
		echo Threads::render( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p><textarea name="ci_staff_reply" rows="3" class="large-text" placeholder="' . esc_attr__( 'Reply to the customer…', 'cirilinfo' ) . '"></textarea></p><p class="description">' . esc_html__( 'The message is saved when you click Update and emailed to the customer.', 'cirilinfo' ) . '</p>';
	}

	public static function box_history( \WP_Post $post ): void {
		$h = get_post_meta( $post->ID, '_ci_history', true );
		if ( ! is_array( $h ) || ! $h ) {
			echo '<p>—</p>';
			return;
		}
		echo '<ul style="font-size:12px">';
		foreach ( array_reverse( $h ) as $e ) {
			echo '<li><strong>' . esc_html( wp_date( 'd M H:i', (int) $e['t'] ) ) . '</strong> ' . esc_html( $e['m'] ) . '</li>';
		}
		echo '</ul>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$nonce = isset( $_POST['_ci_project_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ci_project_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ci_project_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$is_team = Roles::is_team_only();

		if ( ! $is_team && isset( $_POST['ci_assigned_to'] ) ) {
			$old = (int) get_post_meta( $post_id, '_ci_assigned_to', true );
			$new = absint( $_POST['ci_assigned_to'] );
			if ( $new && ! user_can( $new, 'read' ) ) {
				$new = 0;
			}
			update_post_meta( $post_id, '_ci_assigned_to', $new );
			if ( $new && $new !== $old ) {
				self::log( $post_id, 'Assigned to ' . get_userdata( $new )->display_name );
				$u = get_userdata( $new );
				if ( $u ) {
					Mailer::send_raw( $u->user_email, 'Project assigned: ' . $post->post_title, '<p>You have been assigned to project <strong>' . esc_html( $post->post_title ) . '</strong>.</p><p><a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '">Open project</a></p>' );
				}
				if ( 'confirmed' === get_post_meta( $post_id, '_ci_status', true ) ) {
					self::set_status( $post_id, 'assigned', '', ! empty( $_POST['ci_notify'] ) );
				}
			}
		}

		// Milestones.
		$ms = array();
		if ( isset( $_POST['ci_milestones'] ) && is_array( $_POST['ci_milestones'] ) ) {
			foreach ( wp_unslash( $_POST['ci_milestones'] ) as $m ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( ! is_array( $m ) || '' === trim( (string) ( $m['label'] ?? '' ) ) ) {
					continue;
				}
				$due  = sanitize_text_field( (string) ( $m['due'] ?? '' ) );
				$ms[] = array(
					'label' => sanitize_text_field( (string) $m['label'] ),
					'due'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ? $due : '',
					'done'  => empty( $m['done'] ) ? 0 : 1,
				);
			}
		}
		update_post_meta( $post_id, '_ci_milestones', $ms );

		// Deliverables.
		if ( ! empty( $_POST['ci_remove_deliv'] ) && is_array( $_POST['ci_remove_deliv'] ) ) {
			$rm = array_map( 'absint', wp_unslash( $_POST['ci_remove_deliv'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			rsort( $rm );
			foreach ( $rm as $i ) {
				Uploads::remove( $post_id, '_ci_deliverables', $i );
			}
		}
		$up = Uploads::store( 'ci_deliverables' );
		if ( $up['files'] ) {
			Uploads::attach( $post_id, '_ci_deliverables', $up['files'] );
			self::log( $post_id, count( $up['files'] ) . ' deliverable(s) uploaded' );
			$cust = self::customer_info( $post_id );
			Notifications::add( $cust['id'], 'New deliverables on ' . $post->post_title, self::project_url( $post_id ) );
		}

		// Staff reply.
		$reply = isset( $_POST['ci_staff_reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ci_staff_reply'] ) ) : '';
		if ( '' !== trim( $reply ) ) {
			Threads::add( $post_id, get_current_user_id(), $reply, true );
			$cust = self::customer_info( $post_id );
			if ( $cust['email'] ) {
				Mailer::send_raw( $cust['email'], 'New message on your project: ' . $post->post_title, '<p>Hi ' . esc_html( $cust['name'] ) . ',</p><blockquote style="border-left:3px solid #5b3df5;margin:0;padding:6px 14px">' . nl2br( esc_html( $reply ) ) . '</blockquote><p><a href="' . esc_url( self::project_url( $post_id ) ) . '">View and reply</a></p>' );
				Notifications::add( $cust['id'], 'New message on ' . $post->post_title, self::project_url( $post_id ) );
			}
		}

		// Status (last, so milestone/progress is up to date).
		if ( isset( $_POST['ci_status'] ) ) {
			$note = isset( $_POST['ci_status_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ci_status_note'] ) ) : '';
			self::set_status( $post_id, sanitize_key( wp_unslash( $_POST['ci_status'] ) ), $note, ! empty( $_POST['ci_notify'] ) );
		}
	}

	/* --------------------------------------------------- List table UI */

	public static function columns( array $cols ): array {
		return array(
			'cb'       => $cols['cb'] ?? '',
			'title'    => __( 'Project', 'cirilinfo' ),
			'ci_status' => __( 'Status', 'cirilinfo' ),
			'ci_cust'  => __( 'Customer', 'cirilinfo' ),
			'ci_assignee' => __( 'Assigned to', 'cirilinfo' ),
			'ci_progress' => __( 'Progress', 'cirilinfo' ),
			'date'     => __( 'Created', 'cirilinfo' ),
		);
	}

	public static function column( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'ci_status':
				$s = (string) get_post_meta( $post_id, '_ci_status', true );
				echo '<span class="ci-badge ci-badge--' . esc_attr( $s ) . '">' . esc_html( Post_Types::project_statuses()[ $s ] ?? '—' ) . '</span>';
				break;
			case 'ci_cust':
				$c = self::customer_info( $post_id );
				echo esc_html( $c['name'] ) . '<br><small>' . esc_html( $c['email'] ) . '</small>';
				break;
			case 'ci_assignee':
				$u = get_userdata( (int) get_post_meta( $post_id, '_ci_assigned_to', true ) );
				echo $u ? esc_html( $u->display_name ) : '—';
				break;
			case 'ci_progress':
				$p = self::progress( $post_id );
				echo '<div class="ci-bar"><span style="width:' . (int) $p . '%"></span></div> ' . (int) $p . '%';
				break;
		}
	}

	public static function status_filter( string $post_type ): void {
		if ( 'ci_project' !== $post_type ) {
			return;
		}
		$cur = isset( $_GET['ci_status'] ) ? sanitize_key( wp_unslash( $_GET['ci_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="ci_status"><option value="">' . esc_html__( 'All statuses', 'cirilinfo' ) . '</option>';
		foreach ( Post_Types::project_statuses() as $k => $l ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $cur, $k, false ), esc_html( $l ) );
		}
		echo '</select>';
	}

	public static function filter_query( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || 'ci_project' !== $q->get( 'post_type' ) || empty( $_GET['ci_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$meta   = (array) $q->get( 'meta_query' );
		$meta[] = array( 'key' => '_ci_status', 'value' => sanitize_key( wp_unslash( $_GET['ci_status'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$q->set( 'meta_query', $meta );
	}
}
