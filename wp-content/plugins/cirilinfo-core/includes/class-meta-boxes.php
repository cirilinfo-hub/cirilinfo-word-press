<?php
/**
 * Declarative meta box engine for the CirilInfo content types.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Meta_Boxes {

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/** Field types: text,textarea,number,select,checkbox,url,service,repeater */
	public static function boxes(): array {
		return array(
			'ci_service'     => array(
				array( 'id' => 'ci_service_details', 'title' => __( 'Service details', 'cirilinfo' ), 'fields' => array(
					'_ci_icon'    => array( __( 'Icon', 'cirilinfo' ), 'select', array( 'options' => 'icons' ) ),
					'_ci_tagline' => array( __( 'Tagline', 'cirilinfo' ), 'text' ),
					'_ci_highlights' => array( __( 'Key benefits (one per line)', 'cirilinfo' ), 'textarea' ),
					'_ci_process'    => array( __( 'Process steps (one per line: Title | description)', 'cirilinfo' ), 'textarea' ),
				) ),
				array( 'id' => 'ci_service_addons', 'title' => __( 'Optional add-ons', 'cirilinfo' ), 'fields' => array(
					'_ci_addons' => array( __( 'Add-ons customers can attach to any package', 'cirilinfo' ), 'repeater', array(
						'cols' => array(
							'label'     => array( __( 'Add-on name', 'cirilinfo' ), 'text' ),
							'price'     => array( __( 'Price (₹)', 'cirilinfo' ), 'number' ),
							'recurring' => array( __( 'Repeats each billing period', 'cirilinfo' ), 'checkbox' ),
						),
					) ),
				) ),
			),
			'ci_package'     => array(
				array( 'id' => 'ci_package_details', 'title' => __( 'Package details', 'cirilinfo' ), 'fields' => array(
					'_ci_service_id' => array( __( 'Service', 'cirilinfo' ), 'service' ),
					'_ci_tier'       => array( __( 'Tier', 'cirilinfo' ), 'select', array( 'options' => 'tiers' ) ),
					'_ci_billing'    => array( __( 'Pricing model', 'cirilinfo' ), 'select', array( 'options' => 'billing' ) ),
					'_ci_price'      => array( __( 'Price (₹, excl. GST)', 'cirilinfo' ), 'number' ),
					'_ci_sale_price' => array( __( 'Discounted price (₹)', 'cirilinfo' ), 'number' ),
					'_ci_price_note' => array( __( 'Price note (e.g. "+" for starting-from)', 'cirilinfo' ), 'text' ),
					'_ci_revisions'  => array( __( 'Number of revisions', 'cirilinfo' ), 'text' ),
					'_ci_delivery'   => array( __( 'Delivery timeline', 'cirilinfo' ), 'text' ),
					'_ci_support'    => array( __( 'Support duration', 'cirilinfo' ), 'text' ),
					'_ci_features'   => array( __( 'Included features (one per line)', 'cirilinfo' ), 'textarea' ),
					'_ci_featured'   => array( __( 'Highlight as "Most popular"', 'cirilinfo' ), 'checkbox' ),
					'_ci_active'     => array( __( 'Active (available for purchase)', 'cirilinfo' ), 'checkbox' ),
				) ),
			),
			'ci_portfolio'   => array(
				array( 'id' => 'ci_portfolio_details', 'title' => __( 'Project details', 'cirilinfo' ), 'fields' => array(
					'_ci_client' => array( __( 'Client', 'cirilinfo' ), 'text' ),
					'_ci_url'    => array( __( 'Live project URL', 'cirilinfo' ), 'url' ),
					'_ci_result' => array( __( 'Key result (e.g. "+180% organic traffic")', 'cirilinfo' ), 'text' ),
				) ),
			),
			'ci_testimonial' => array(
				array( 'id' => 'ci_testimonial_details', 'title' => __( 'Testimonial details', 'cirilinfo' ), 'fields' => array(
					'_ci_role'    => array( __( 'Role / Designation', 'cirilinfo' ), 'text' ),
					'_ci_company' => array( __( 'Company', 'cirilinfo' ), 'text' ),
					'_ci_rating'  => array( __( 'Rating (1-5)', 'cirilinfo' ), 'number' ),
				) ),
			),
			'ci_client'      => array(
				array( 'id' => 'ci_client_details', 'title' => __( 'Client', 'cirilinfo' ), 'fields' => array(
					'_ci_url' => array( __( 'Website', 'cirilinfo' ), 'url' ),
				) ),
			),
			'ci_banner'      => array(
				array( 'id' => 'ci_banner_details', 'title' => __( 'Banner settings', 'cirilinfo' ), 'fields' => array(
					'_ci_placement' => array( __( 'Placement', 'cirilinfo' ), 'select', array( 'options' => array( 'hero' => 'Home hero', 'promo' => 'Promo strip (top of site)' ) ) ),
					'_ci_cta_text'  => array( __( 'Button text', 'cirilinfo' ), 'text' ),
					'_ci_cta_url'   => array( __( 'Button link', 'cirilinfo' ), 'url' ),
					'_ci_active'    => array( __( 'Active', 'cirilinfo' ), 'checkbox' ),
				) ),
			),
			'ci_team'        => array(
				array( 'id' => 'ci_team_details', 'title' => __( 'Team member', 'cirilinfo' ), 'fields' => array(
					'_ci_role' => array( __( 'Role / Designation', 'cirilinfo' ), 'text' ),
				) ),
			),
			'page'           => array(
				array( 'id' => 'ci_seo', 'title' => __( 'SEO (CirilInfo)', 'cirilinfo' ), 'context' => 'normal', 'priority' => 'low', 'fields' => self::seo_fields() ),
			),
			'post'           => array(
				array( 'id' => 'ci_seo', 'title' => __( 'SEO (CirilInfo)', 'cirilinfo' ), 'context' => 'normal', 'priority' => 'low', 'fields' => self::seo_fields() ),
			),
		);
	}

	private static function seo_fields(): array {
		return array(
			'_ci_seo_title'  => array( __( 'SEO title (≈60 chars)', 'cirilinfo' ), 'text' ),
			'_ci_seo_desc'   => array( __( 'Meta description (≈155 chars)', 'cirilinfo' ), 'textarea' ),
			'_ci_seo_noindex' => array( __( 'Hide from search engines (noindex)', 'cirilinfo' ), 'checkbox' ),
		);
	}

	/** Boxes for a post type, adding the SEO box to the public CPTs too. */
	private static function boxes_for( string $type ): array {
		$all   = self::boxes();
		$boxes = $all[ $type ] ?? array();
		if ( in_array( $type, array( 'ci_service', 'ci_portfolio' ), true ) ) {
			$boxes[] = array( 'id' => 'ci_seo', 'title' => __( 'SEO (CirilInfo)', 'cirilinfo' ), 'context' => 'normal', 'priority' => 'low', 'fields' => self::seo_fields() );
		}
		return $boxes;
	}

	public static function register( string $post_type ): void {
		foreach ( self::boxes_for( $post_type ) as $box ) {
			add_meta_box(
				$box['id'],
				$box['title'],
				static function ( $post ) use ( $box ) {
					wp_nonce_field( 'ci_meta_' . $box['id'], '_ci_nonce_' . $box['id'] );
					echo '<table class="form-table ci-meta" role="presentation">';
					foreach ( $box['fields'] as $key => $f ) {
						self::render_field( $post->ID, $key, $f );
					}
					echo '</table>';
				},
				$post_type,
				$box['context'] ?? 'normal',
				$box['priority'] ?? 'high'
			);
		}
	}

	private static function options( $spec ): array {
		if ( 'icons' === $spec ) {
			return array_combine( Icons::names(), Icons::names() );
		}
		if ( 'tiers' === $spec ) {
			return Post_Types::tiers();
		}
		if ( 'billing' === $spec ) {
			return array_map( static fn( $l ) => __( $l, 'cirilinfo' ), Pricing::BILLING ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		}
		return (array) $spec;
	}

	private static function render_field( int $post_id, string $key, array $f ): void {
		list( $label, $type ) = $f;
		$opts = $f[2] ?? array();
		$val  = get_post_meta( $post_id, $key, true );
		$id   = 'f' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'textarea':
				printf( '<textarea class="large-text" rows="5" id="%1$s" name="%1$s">%2$s</textarea>', esc_attr( $id ), esc_textarea( (string) $val ) );
				break;
			case 'number':
				printf( '<input type="number" step="0.01" min="0" class="regular-text" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $id ), esc_attr( (string) $val ) );
				break;
			case 'url':
				printf( '<input type="url" class="large-text" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $id ), esc_attr( (string) $val ) );
				break;
			case 'checkbox':
				$checked = ( '' === $val && in_array( $key, array( '_ci_active' ), true ) ) ? true : ! empty( $val );
				printf( '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>', esc_attr( $id ), checked( $checked, true, false ), esc_html__( 'Yes', 'cirilinfo' ) );
				break;
			case 'select':
				printf( '<select id="%1$s" name="%1$s">', esc_attr( $id ) );
				foreach ( self::options( $opts['options'] ?? array() ) as $ov => $ol ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( (string) $ov ), selected( (string) $val, (string) $ov, false ), esc_html( (string) $ol ) );
				}
				echo '</select>';
				break;
			case 'service':
				$services = get_posts( array( 'post_type' => 'ci_service', 'numberposts' => -1, 'post_status' => array( 'publish', 'draft', 'private' ), 'orderby' => 'title', 'order' => 'ASC' ) );
				printf( '<select id="%1$s" name="%1$s"><option value="0">—</option>', esc_attr( $id ) );
				foreach ( $services as $s ) {
					printf( '<option value="%d" %s>%s</option>', (int) $s->ID, selected( (int) $val, $s->ID, false ), esc_html( $s->post_title ) );
				}
				echo '</select>';
				break;
			case 'repeater':
				self::render_repeater( $id, is_array( $val ) ? $val : array(), $opts['cols'] );
				break;
			default:
				printf( '<input type="text" class="large-text" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $id ), esc_attr( (string) $val ) );
		}
		echo '</td></tr>';
	}

	private static function render_repeater( string $id, array $rows, array $cols ): void {
		echo '<div class="ci-repeater" data-name="' . esc_attr( $id ) . '"><table class="widefat striped"><thead><tr>';
		foreach ( $cols as $c ) {
			echo '<th>' . esc_html( $c[0] ) . '</th>';
		}
		echo '<th></th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			$rows = array( array() );
		}
		foreach ( $rows as $i => $row ) {
			echo self::repeater_row( $id, (int) $i, $row, $cols ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		}
		echo '</tbody></table>';
		echo '<template class="ci-repeater-tpl">' . self::repeater_row( $id, '__i__', array(), $cols ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p><button type="button" class="button ci-repeater-add">' . esc_html__( 'Add row', 'cirilinfo' ) . '</button></p></div>';
	}

	/** @param int|string $i */
	private static function repeater_row( string $id, $i, array $row, array $cols ): string {
		$html = '<tr>';
		foreach ( $cols as $ckey => $c ) {
			$name = $id . '[' . $i . '][' . $ckey . ']';
			$v    = $row[ $ckey ] ?? '';
			if ( 'checkbox' === $c[1] ) {
				$html .= '<td><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $v ), true, false ) . '></td>';
			} else {
				$html .= '<td><input type="' . ( 'number' === $c[1] ? 'number" step="0.01" min="0' : 'text' ) . '" class="widefat" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $v ) . '"></td>';
			}
		}
		return $html . '<td><button type="button" class="button-link-delete ci-repeater-del">&times;</button></td></tr>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		foreach ( self::boxes_for( $post->post_type ) as $box ) {
			$nonce = $_POST[ '_ci_nonce_' . $box['id'] ] ?? ''; // phpcs:ignore WordPress.Security
			if ( ! $nonce || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'ci_meta_' . $box['id'] ) ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			foreach ( $box['fields'] as $key => $f ) {
				self::save_field( $post_id, $key, $f );
			}
		}
		if ( 'ci_package' === $post->post_type ) {
			do_action( 'cirilinfo_package_saved', $post_id );
		}
		if ( 'ci_service' === $post->post_type ) {
			do_action( 'cirilinfo_service_saved', $post_id );
		}
	}

	private static function save_field( int $post_id, string $key, array $f ): void {
		$type = $f[1];
		$id   = 'f' . $key;
		$raw  = $_POST[ $id ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		switch ( $type ) {
			case 'checkbox':
				update_post_meta( $post_id, $key, empty( $raw ) ? 0 : 1 );
				return;
			case 'repeater':
				$rows = array();
				if ( is_array( $raw ) ) {
					foreach ( wp_unslash( $raw ) as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$clean = array();
						foreach ( $f[2]['cols'] as $ckey => $c ) {
							$v = $row[ $ckey ] ?? '';
							$clean[ $ckey ] = 'checkbox' === $c[1] ? ( empty( $v ) ? 0 : 1 ) : ( 'number' === $c[1] ? max( 0, (float) $v ) : sanitize_text_field( (string) $v ) );
						}
						if ( '' !== (string) ( $clean['label'] ?? '' ) ) {
							$rows[] = $clean;
						}
					}
				}
				update_post_meta( $post_id, $key, $rows );
				return;
		}
		if ( null === $raw || is_array( $raw ) ) {
			return;
		}
		$raw = wp_unslash( (string) $raw );
		switch ( $type ) {
			case 'textarea':
				$val = sanitize_textarea_field( $raw );
				break;
			case 'number':
				$val = '' === $raw ? '' : max( 0, (float) $raw );
				break;
			case 'url':
				$val = esc_url_raw( $raw );
				break;
			case 'service':
				$val = absint( $raw );
				break;
			case 'select':
				$allowed = array_keys( self::options( $f[2]['options'] ?? array() ) );
				$val     = in_array( $raw, array_map( 'strval', $allowed ), true ) ? $raw : (string) ( $allowed[0] ?? '' );
				break;
			default:
				$val = sanitize_text_field( $raw );
		}
		update_post_meta( $post_id, $key, $val );
	}

	public static function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'cirilinfo-admin', CIRILINFO_URL . 'assets/css/admin.css', array(), CIRILINFO_VERSION );
		wp_enqueue_script( 'cirilinfo-admin', CIRILINFO_URL . 'assets/js/admin.js', array(), CIRILINFO_VERSION, true );
	}
}
