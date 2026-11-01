<?php
/**
 * Private file storage + authenticated downloads.
 *
 * Files live in wp-content/uploads/cirilinfo-private/ with randomised names,
 * are never linked directly, and are streamed only after a permission check.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Uploads {

	const MAX_BYTES = 10485760; // 10 MB.
	const MAX_FILES = 5;

	public static function init(): void {
		add_action( 'admin_post_ci_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_ci_download', array( __CLASS__, 'handle_download' ) );
	}

	public static function allowed_mimes(): array {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'          => 'application/vnd.ms-excel',
			'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'ppt'          => 'application/vnd.ms-powerpoint',
			'pptx'         => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'txt'          => 'text/plain',
			'csv'          => 'text/csv',
			'zip'          => 'application/zip',
		);
	}

	public static function base_dir(): array {
		$u    = wp_upload_dir();
		$path = trailingslashit( $u['basedir'] ) . 'cirilinfo-private';
		return array( 'path' => $path, 'url' => trailingslashit( $u['baseurl'] ) . 'cirilinfo-private' );
	}

	/** Create the protected directory with deny rules. */
	public static function ensure_dir(): string {
		$dir = self::base_dir()['path'];
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			// Apache 2.4 + 2.2 compatible.
			file_put_contents( $ht, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$ix = $dir . '/index.php';
		if ( ! file_exists( $ix ) ) {
			file_put_contents( $ix, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	/**
	 * Store uploads from $_FILES[$field] (single or multiple).
	 *
	 * @return array{files: array, errors: string[]}
	 */
	public static function store( string $field ): array {
		$result = array( 'files' => array(), 'errors' => array() );
		if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $result;
		}
		$raw = $_FILES[ $field ]; // phpcs:ignore WordPress.Security
		$list = array();
		if ( is_array( $raw['name'] ) ) {
			foreach ( array_keys( $raw['name'] ) as $i ) {
				$list[] = array(
					'name'     => $raw['name'][ $i ],
					'type'     => $raw['type'][ $i ],
					'tmp_name' => $raw['tmp_name'][ $i ],
					'error'    => $raw['error'][ $i ],
					'size'     => $raw['size'][ $i ],
				);
			}
		} else {
			$list[] = $raw;
		}

		self::ensure_dir();
		$base_info = self::base_dir();
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$count = 0;
		foreach ( $list as $file ) {
			if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] || '' === $file['name'] ) {
				continue;
			}
			if ( ++$count > self::MAX_FILES ) {
				$result['errors'][] = sprintf( /* translators: %d: max files */ __( 'Only %d files can be uploaded at a time.', 'cirilinfo' ), self::MAX_FILES );
				break;
			}
			if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
				$result['errors'][] = sprintf( /* translators: %s: file name */ __( '"%s" could not be uploaded.', 'cirilinfo' ), sanitize_file_name( $file['name'] ) );
				continue;
			}
			if ( (int) $file['size'] > self::MAX_BYTES ) {
				$result['errors'][] = sprintf( /* translators: %s: file name */ __( '"%s" is larger than 10 MB.', 'cirilinfo' ), sanitize_file_name( $file['name'] ) );
				continue;
			}

			$base       = $base_info; // Resolved before the filter exists (wp_upload_dir() re-applies it).
			$dir_filter = static function ( $dirs ) use ( $base ) {
				$sub  = '/' . gmdate( 'Y' );
				$dirs['path']   = $base['path'] . $sub;
				$dirs['url']    = $base['url'] . $sub;
				$dirs['subdir'] = '';
				return $dirs;
			};
			$name_cb = static function ( $dir, $name, $ext ) {
				return bin2hex( random_bytes( 8 ) ) . $ext;
			};
			add_filter( 'upload_dir', $dir_filter );
			$moved = wp_handle_upload(
				$file,
				array(
					'test_form'                => false,
					'mimes'                    => self::allowed_mimes(),
					'unique_filename_callback' => $name_cb,
				)
			);
			remove_filter( 'upload_dir', $dir_filter );

			if ( isset( $moved['error'] ) ) {
				$result['errors'][] = sprintf( '"%s": %s', sanitize_file_name( $file['name'] ), $moved['error'] );
				continue;
			}
			$result['files'][] = array(
				'name' => sanitize_file_name( $file['name'] ),
				'rel'  => ltrim( str_replace( wp_normalize_path( $base_info['path'] ), '', wp_normalize_path( $moved['file'] ) ), '/' ),
				'mime' => $moved['type'],
				'size' => (int) $file['size'],
				'time' => time(),
			);
		}
		return $result;
	}

	/** Append stored file records to a post meta list. */
	public static function attach( int $post_id, string $meta_key, array $files ): void {
		if ( empty( $files ) ) {
			return;
		}
		$existing = get_post_meta( $post_id, $meta_key, true );
		$existing = is_array( $existing ) ? $existing : array();
		update_post_meta( $post_id, $meta_key, array_merge( $existing, $files ) );
	}

	public static function remove( int $post_id, string $meta_key, int $index ): void {
		$list = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_array( $list ) || ! isset( $list[ $index ] ) ) {
			return;
		}
		$path = self::abs_path( $list[ $index ]['rel'] );
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
		unset( $list[ $index ] );
		update_post_meta( $post_id, $meta_key, array_values( $list ) );
	}

	/** Resolve a stored relative path to an absolute one, rejecting traversal. */
	public static function abs_path( string $rel ): string {
		$base = wp_normalize_path( self::base_dir()['path'] );
		$full = wp_normalize_path( $base . '/' . ltrim( $rel, '/' ) );
		if ( false !== strpos( $rel, '..' ) || 0 !== strpos( $full, $base . '/' ) ) {
			return '';
		}
		return $full;
	}

	public static function download_url( int $post_id, string $meta_key, int $index ): string {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'ci_download', 'post' => $post_id, 'key' => $meta_key, 'i' => $index ), admin_url( 'admin-post.php' ) ),
			'ci_dl_' . $post_id . '_' . $meta_key . '_' . $index
		);
	}

	/** Who may download files belonging to a post. */
	public static function can_access( \WP_Post $post, int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_cirilinfo' ) ) {
			return true;
		}
		switch ( $post->post_type ) {
			case 'ci_project':
				if ( (int) get_post_meta( $post->ID, '_ci_assigned_to', true ) === $user_id ) {
					return true;
				}
				return Projects::user_owns( $post->ID, $user_id );
			case 'ci_ticket':
			case 'ci_enquiry':
				return (int) $post->post_author === $user_id;
		}
		return false;
	}

	public static function handle_download(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$key     = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
		$idx     = isset( $_GET['i'] ) ? absint( $_GET['i'] ) : 0;

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/' ) ) );
			exit;
		}
		check_admin_referer( 'ci_dl_' . $post_id . '_' . $key . '_' . $idx );

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $key, array( '_ci_req_files', '_ci_deliverables', '_ci_files' ), true ) || ! self::can_access( $post, get_current_user_id() ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'cirilinfo' ), 403 );
		}
		$list = get_post_meta( $post_id, $key, true );
		if ( ! is_array( $list ) || ! isset( $list[ $idx ] ) ) {
			wp_die( esc_html__( 'File not found.', 'cirilinfo' ), 404 );
		}
		$file = $list[ $idx ];
		$path = self::abs_path( (string) $file['rel'] );
		if ( ! $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'cirilinfo' ), 404 );
		}
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . ( $file['mime'] ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( (string) $file['name'] ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Render a list of download links. */
	public static function render_list( int $post_id, string $meta_key ): string {
		$list = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_array( $list ) || ! $list ) {
			return '';
		}
		$out = '<ul class="ci-files">';
		foreach ( $list as $i => $f ) {
			$out .= sprintf(
				'<li><a href="%s">%s %s</a> <small>(%s)</small></li>',
				esc_url( self::download_url( $post_id, $meta_key, (int) $i ) ),
				Icons::svg( 'download', 16 ),
				esc_html( $f['name'] ),
				esc_html( size_format( (int) $f['size'] ) )
			);
		}
		return $out . '</ul>';
	}
}
