<?php
/**
 * Inline SVG icon set (no external requests).
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Icons {

	/** 24x24 stroke paths. */
	private const PATHS = array(
		'web'       => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18M8 21h8M12 18v3"/>',
		'code'      => '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 5l-4 14"/>',
		'cart'      => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.7 12.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6"/>',
		'logo'      => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 13 9 5 9-5M3 17.5l9 5 9-5"/>',
		'design'    => '<path d="M12 19l7-7-3-3-7 7v3h3Z"/><path d="m14 7 3 3M5 21h14"/>',
		'seo'       => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M8 12l2 2 4-4"/>',
		'domain'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'hosting'   => '<rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01M7 17h.01"/>',
		'social'    => '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.3 10.8 7.4-3.6M8.3 13.2l7.4 3.6"/>',
		'ads'       => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M15 9a4 4 0 0 1 0 6M18 6.5a8 8 0 0 1 0 11"/>',
		'promo'     => '<path d="m3 11 18-8-8 18-2-8-8-2Z"/>',
		'support'   => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.1-.6-.6-2.1 2.1-2.7Z"/>',
		'content'   => '<path d="M5 4h14v16H5z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
		'check'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'cross'     => '<path d="m6 6 12 12M18 6 6 18"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'star'      => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/>',
		'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
		'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'pin'       => '<path d="M12 21s7-6.2 7-11.5a7 7 0 0 0-14 0C5 14.800 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'whatsapp'  => '<path d="M3 21l1.6-4.6A8.5 8.5 0 1 1 8 19.6L3 21Z"/><path d="M9 9.500c.3 2 2.500 4.200 4.500 4.500l1.200-1.200-1.800-1-.9.700c-.8-.3-1.600-1.100-1.900-1.900l.7-.9-1-1.800L9 9.500Z"/>',
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'shield'    => '<path d="M12 3 4 6v6c0 4.500 3.300 8 8 9 4.700-1 8-4.500 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
		'rocket'    => '<path d="M5 15c-1 1-1.500 4-1.500 4s3-.5 4-1.500M14 4c3-1.500 6-1 6-1s.5 3-1 6l-6 6-5-5 6-6Z"/><circle cx="15" cy="9" r="1.200"/>',
		'chart'     => '<path d="M4 20V4M4 20h16"/><path d="m8 15 3-4 3 2 5-6"/>',
		'bolt'      => '<path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/>',
		'users'     => '<circle cx="9" cy="8" r="3.500"/><path d="M2 20a7 7 0 0 1 14 0M16 4.500a3.500 3.500 0 0 1 0 7M18 14a7 7 0 0 1 4 6"/>',
		'target'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
		'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'plus'      => '<path d="M12 5v14M5 12h14"/>',
		'download'  => '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>',
		'ticket'    => '<path d="M3 9a2 2 0 0 0 0 6v3h18v-3a2 2 0 0 1 0-6V6H3v3Z"/><path d="M13 6v12"/>',
		'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v7h4v-7h3l1-4h-4V8Z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.500 6.500h.01"/>',
		'linkedin'  => '<path d="M6 9v11M6 4.500v.01M10 20v-7a3 3 0 0 1 6 0v7M10 9v11"/>',
		'x'         => '<path d="m4 4 16 16M20 4 4 20"/>',
		'youtube'   => '<rect x="2.500" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3V9Z"/>',
		'link'      => '<path d="M10 14a4 4 0 0 0 5.700 0l3-3a4 4 0 0 0-5.700-5.700l-1 1M14 10a4 4 0 0 0-5.700 0l-3 3a4 4 0 0 0 5.700 5.700l1-1"/>',
	);

	public static function names(): array {
		return array_keys( self::PATHS );
	}

	public static function svg( string $name, int $size = 24, string $class = '' ): string {
		$p = self::PATHS[ $name ] ?? self::PATHS['web'];
		return sprintf(
			'<svg class="ci-icon %1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $class ),
			$size,
			$p // phpcs:ignore WordPress.Security.EscapeOutput -- static trusted markup.
		);
	}
}
