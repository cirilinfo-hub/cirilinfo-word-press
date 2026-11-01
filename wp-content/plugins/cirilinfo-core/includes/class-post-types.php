<?php
/**
 * Custom post types & taxonomies.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Post_Types {

	/** Project workflow statuses. */
	public static function project_statuses(): array {
		return array(
			'new'                   => __( 'New', 'cirilinfo' ),
			'awaiting_requirements' => __( 'Awaiting Requirements', 'cirilinfo' ),
			'payment_pending'       => __( 'Payment Pending', 'cirilinfo' ),
			'confirmed'             => __( 'Confirmed', 'cirilinfo' ),
			'assigned'              => __( 'Assigned', 'cirilinfo' ),
			'in_progress'           => __( 'In Progress', 'cirilinfo' ),
			'review'                => __( 'Review', 'cirilinfo' ),
			'completed'             => __( 'Completed', 'cirilinfo' ),
			'delivered'             => __( 'Delivered', 'cirilinfo' ),
			'cancelled'             => __( 'Cancelled', 'cirilinfo' ),
		);
	}

	public static function enquiry_statuses(): array {
		return array(
			'new'       => __( 'New', 'cirilinfo' ),
			'contacted' => __( 'Contacted', 'cirilinfo' ),
			'quoted'    => __( 'Quoted', 'cirilinfo' ),
			'won'       => __( 'Won', 'cirilinfo' ),
			'lost'      => __( 'Lost', 'cirilinfo' ),
		);
	}

	public static function ticket_statuses(): array {
		return array(
			'open'     => __( 'Open', 'cirilinfo' ),
			'pending'  => __( 'Awaiting customer', 'cirilinfo' ),
			'resolved' => __( 'Resolved', 'cirilinfo' ),
			'closed'   => __( 'Closed', 'cirilinfo' ),
		);
	}

	public static function tiers(): array {
		return array(
			'basic'        => __( 'Basic', 'cirilinfo' ),
			'professional' => __( 'Professional', 'cirilinfo' ),
			'enterprise'   => __( 'Enterprise', 'cirilinfo' ),
		);
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	public static function maybe_flush(): void {
		if ( get_option( 'cirilinfo_flush_rewrite' ) ) {
			flush_rewrite_rules( false );
			delete_option( 'cirilinfo_flush_rewrite' );
		}
	}

	public static function title_placeholder( string $title, $post ): string {
		$map = array(
			'ci_faq'         => __( 'Question', 'cirilinfo' ),
			'ci_testimonial' => __( 'Customer name', 'cirilinfo' ),
			'ci_package'     => __( 'Package name, e.g. Website Development – Professional', 'cirilinfo' ),
		);
		return $map[ $post->post_type ] ?? $title;
	}

	public static function register(): void {
		$content_caps = array( 'ci_content_item', 'ci_content_items' );
		$ops_caps     = array( 'ci_op', 'ci_ops' );

		self::cpt( 'ci_service', 'Services', 'Service', array(
			'public'          => true,
			'has_archive'     => false,
			'rewrite'         => array( 'slug' => 'service', 'with_front' => false ),
			'menu_icon'       => 'dashicons-admin-tools',
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions' ),
			'show_in_rest'    => true,
			'capability_type' => $content_caps,
			'menu_position'   => 31,
		) );
		self::cpt( 'ci_package', 'Packages', 'Package', array(
			'public'              => false,
			'publicly_queryable'  => false,
			'supports'            => array( 'title', 'editor' ),
			'capability_type'     => $content_caps,
			'show_in_rest'        => false,
		) );
		self::cpt( 'ci_portfolio', 'Portfolio', 'Project', array(
			'public'          => true,
			'has_archive'     => false,
			'rewrite'         => array( 'slug' => 'work', 'with_front' => false ),
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'show_in_rest'    => true,
			'capability_type' => $content_caps,
		) );
		self::cpt( 'ci_testimonial', 'Testimonials', 'Testimonial', array(
			'supports'        => array( 'title', 'editor', 'thumbnail' ),
			'capability_type' => $content_caps,
		) );
		self::cpt( 'ci_faq', 'FAQs', 'FAQ', array(
			'supports'        => array( 'title', 'editor', 'page-attributes' ),
			'capability_type' => $content_caps,
		) );
		self::cpt( 'ci_client', 'Client logos', 'Client', array(
			'supports'        => array( 'title', 'thumbnail' ),
			'capability_type' => $content_caps,
		) );
		self::cpt( 'ci_banner', 'Banners', 'Banner', array(
			'supports'        => array( 'title', 'editor', 'thumbnail' ),
			'capability_type' => $content_caps,
		) );
		self::cpt( 'ci_team', 'Team', 'Team member', array(
			'supports'        => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'capability_type' => $content_caps,
		) );

		// Operational (private, customer data) types.
		self::cpt( 'ci_project', 'Projects', 'Project', array(
			'supports'        => array( 'title' ),
			'capability_type' => $ops_caps,
		) );
		self::cpt( 'ci_ticket', 'Support tickets', 'Ticket', array(
			'supports'        => array( 'title' ),
			'capability_type' => $ops_caps,
		) );
		self::cpt( 'ci_enquiry', 'Enquiries', 'Enquiry', array(
			'supports'        => array( 'title' ),
			'capability_type' => $ops_caps,
		) );

		self::tax( 'ci_service_cat', 'Service Categories', 'Service Category', array( 'ci_service' ), true );
		self::tax( 'ci_portfolio_cat', 'Project Categories', 'Project Category', array( 'ci_portfolio' ), true );
		self::tax( 'ci_tech', 'Technologies', 'Technology', array( 'ci_portfolio' ), false );
		self::tax( 'ci_faq_cat', 'FAQ Categories', 'FAQ Category', array( 'ci_faq' ), true );
	}

	private static function cpt( string $slug, string $plural, string $singular, array $args ): void {
		$labels = array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New ' . $singular,
			'edit_item'          => 'Edit ' . $singular,
			'new_item'           => 'New ' . $singular,
			'view_item'          => 'View ' . $singular,
			'search_items'       => 'Search ' . $plural,
			'not_found'          => 'No ' . strtolower( $plural ) . ' found',
			'not_found_in_trash' => 'No ' . strtolower( $plural ) . ' found in Trash',
			'all_items'          => $plural,
			'menu_name'          => $plural,
		);
		$defaults = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => 'cirilinfo',
			'show_in_nav_menus'  => false,
			'exclude_from_search' => true,
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'show_in_rest'       => false,
		);
		$args = wp_parse_args( $args, $defaults );
		if ( ! empty( $args['public'] ) ) {
			$args['exclude_from_search'] = false;
			$args['show_in_nav_menus']   = true;
			$args['query_var']           = true;
		}
		register_post_type( $slug, $args );
	}

	private static function tax( string $slug, string $plural, string $singular, array $types, bool $hierarchical ): void {
		register_taxonomy( $slug, $types, array(
			'labels'            => array( 'name' => $plural, 'singular_name' => $singular ),
			'hierarchical'      => $hierarchical,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'public'            => in_array( 'ci_service', $types, true ) || in_array( 'ci_portfolio', $types, true ),
			'rewrite'           => array( 'slug' => str_replace( '_', '-', $slug ), 'with_front' => false ),
			'capabilities'      => array(
				'manage_terms' => 'manage_cirilinfo',
				'edit_terms'   => 'manage_cirilinfo',
				'delete_terms' => 'manage_cirilinfo',
				'assign_terms' => 'edit_ci_content_items',
			),
		) );
	}
}
