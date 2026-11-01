<?php
/**
 * Branded transactional email, editable templates and SMTP.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	public static function init(): void {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
	}

	/* --------------------------------------------------------------- SMTP */

	public static function configure_smtp( $phpmailer ): void {
		if ( ! Settings::get( 'smtp_enabled', 0 ) || ! Settings::get( 'smtp_host' ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = (string) Settings::get( 'smtp_host' );
		$phpmailer->Port       = (int) Settings::get( 'smtp_port', 587 );
		$phpmailer->SMTPSecure = (string) Settings::get( 'smtp_secure', 'tls' );
		$user                  = (string) Settings::get( 'smtp_user' );
		$pass                  = Settings::smtp_password();
		if ( '' !== $user ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $user;
			$phpmailer->Password = $pass;
		}
	}

	public static function from_email( string $email ): string {
		$from = Settings::get( 'from_email' );
		return is_email( $from ) ? $from : $email;
	}

	public static function from_name( string $name ): string {
		return (string) Settings::get( 'from_name', Settings::get( 'company_name', $name ) );
	}

	/* ---------------------------------------------------------- Templates */

	public static function template_defs(): array {
		$common = array( 'site_name', 'customer_name', 'account_url' );
		return array(
			'verify_email'     => array(
				'label' => __( 'Registration & email verification', 'cirilinfo' ), 'audience' => 'customer',
				'subject' => 'Welcome to {site_name} – please verify your email',
				'body'  => "<p>Hi {customer_name},</p><p>Thanks for registering with {site_name}. Please confirm your email address to activate your account:</p><p><a href=\"{verify_url}\" style=\"display:inline-block;background:#5b3df5;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none\">Verify my email</a></p><p>This link is valid for 48 hours. If you did not create this account you can ignore this email.</p>",
				'vars' => array_merge( $common, array( 'verify_url' ) ),
			),
			'enquiry_ack'      => array(
				'label' => __( 'Enquiry acknowledgement', 'cirilinfo' ), 'audience' => 'customer',
				'subject' => 'We received your enquiry – {site_name}',
				'body'  => "<p>Hi {customer_name},</p><p>Thank you for contacting {site_name}. We have received your request (<strong>{enquiry_ref}</strong>) and a member of our team will get back to you within one business day.</p><p><strong>Your message:</strong><br>{message}</p>",
				'vars' => array_merge( $common, array( 'enquiry_ref', 'message', 'service' ) ),
			),
			'payment_received' => array(
				'label' => __( 'Payment confirmation', 'cirilinfo' ), 'audience' => 'customer',
				'subject' => 'Payment received for order #{order_number}',
				'body'  => "<p>Hi {customer_name},</p><p>We have received your payment of <strong>{amount}</strong> for order <strong>#{order_number}</strong>. Thank you!</p><p>Your invoice <strong>{invoice_number}</strong> is available in your dashboard:</p><p><a href=\"{invoice_url}\">Download invoice</a> &nbsp;|&nbsp; <a href=\"{account_url}\">Open my dashboard</a></p><p>Next step: please submit your project requirements so we can start work.</p>",
				'vars' => array_merge( $common, array( 'order_number', 'amount', 'invoice_number', 'invoice_url' ) ),
			),
			'project_update'   => array(
				'label' => __( 'Project progress update', 'cirilinfo' ), 'audience' => 'customer',
				'subject' => 'Update on your project: {project_title}',
				'body'  => "<p>Hi {customer_name},</p><p>The status of your project <strong>{project_title}</strong> is now: <strong>{status}</strong>.</p><p>{note}</p><p><a href=\"{project_url}\">View project</a></p>",
				'vars' => array_merge( $common, array( 'project_title', 'status', 'note', 'project_url' ) ),
			),
			'project_complete' => array(
				'label' => __( 'Project completion', 'cirilinfo' ), 'audience' => 'customer',
				'subject' => 'Your project is complete: {project_title}',
				'body'  => "<p>Hi {customer_name},</p><p>Great news – your project <strong>{project_title}</strong> is <strong>{status}</strong>. You can download the deliverables from your dashboard.</p><p><a href=\"{project_url}\">Open project</a></p><p>We would love your feedback – it takes less than a minute: <a href=\"{feedback_url}\">leave a review</a>.</p>",
				'vars' => array_merge( $common, array( 'project_title', 'status', 'project_url', 'feedback_url' ) ),
			),
			'ticket_reply'     => array(
				'label' => __( 'Support ticket reply', 'cirilinfo' ), 'audience' => 'customer',
				'subject' => 'Re: {ticket_title} [Ticket #{ticket_id}]',
				'body'  => "<p>Hi {customer_name},</p><p>Our team replied to your support ticket:</p><blockquote style=\"border-left:3px solid #5b3df5;margin:0;padding:6px 14px\">{message}</blockquote><p><a href=\"{ticket_url}\">View and reply</a></p>",
				'vars' => array_merge( $common, array( 'ticket_title', 'ticket_id', 'message', 'ticket_url' ) ),
			),
			'newsletter_confirm' => array(
				'label' => __( 'Newsletter confirmation', 'cirilinfo' ), 'audience' => 'subscriber',
				'subject' => 'Confirm your subscription – {site_name}',
				'body'  => "<p>Hi,</p><p>Please confirm your subscription to the {site_name} newsletter:</p><p><a href=\"{confirm_url}\">Confirm subscription</a></p>",
				'vars' => array( 'site_name', 'confirm_url' ),
			),
			'admin_event'      => array(
				'label' => __( 'Admin notification (all events)', 'cirilinfo' ), 'audience' => 'admin',
				'subject' => '[{site_name}] {event_title}',
				'body'  => '<p><strong>{event_title}</strong></p>{event_body}<p><a href="{admin_url}">Open in admin</a></p>',
				'vars' => array( 'site_name', 'event_title', 'event_body', 'admin_url' ),
			),
		);
	}

	private static function template( string $key ): array {
		$defs  = self::template_defs();
		$def   = $defs[ $key ] ?? array( 'subject' => '', 'body' => '' );
		$saved = Settings::all()['templates'][ $key ] ?? array();
		return array(
			'subject' => '' !== ( $saved['subject'] ?? '' ) ? $saved['subject'] : $def['subject'],
			'body'    => '' !== ( $saved['body'] ?? '' ) ? $saved['body'] : $def['body'],
		);
	}

	/** Replace {placeholders}. Values are escaped unless keyed in $raw. */
	private static function fill( string $text, array $vars, array $raw = array() ): string {
		$map = array();
		foreach ( $vars as $k => $v ) {
			$map[ '{' . $k . '}' ] = in_array( $k, $raw, true ) ? (string) $v : esc_html( (string) $v );
		}
		return strtr( $text, $map );
	}

	/**
	 * Send a templated email.
	 *
	 * @param string $key   Template key.
	 * @param string $to    Recipient.
	 * @param array  $vars  Placeholder values.
	 * @param array  $raw   Placeholder names whose values are trusted HTML.
	 */
	public static function send( string $key, string $to, array $vars = array(), array $raw = array() ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$vars += array(
			'site_name'   => (string) Settings::get( 'company_name', get_bloginfo( 'name' ) ),
			'account_url' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
		);
		$tpl     = self::template( $key );
		$subject = wp_strip_all_tags( html_entity_decode( self::fill( $tpl['subject'], $vars ), ENT_QUOTES ) );
		// URLs must not be double-escaped inside href attributes.
		foreach ( array( 'verify_url', 'invoice_url', 'project_url', 'ticket_url', 'confirm_url', 'admin_url', 'feedback_url', 'account_url' ) as $u ) {
			if ( isset( $vars[ $u ] ) ) {
				$raw[]       = $u;
				$vars[ $u ]  = esc_url( (string) $vars[ $u ] );
			}
		}
		$body = self::fill( wp_kses_post( $tpl['body'] ), $vars, $raw );
		return self::send_raw( $to, $subject, $body );
	}

	public static function send_raw( string $to, string $subject, string $html_body, array $attachments = array() ): bool {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply   = Settings::business_email();
		if ( is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
		return (bool) wp_mail( $to, $subject, self::layout( $subject, $html_body ), $headers, $attachments );
	}

	/** Admin notification helper. */
	public static function notify_admin( string $title, string $body_html, string $admin_url = '' ): void {
		self::send(
			'admin_event',
			Settings::notify_email(),
			array(
				'event_title' => $title,
				'event_body'  => $body_html,
				'admin_url'   => $admin_url ?: admin_url( 'admin.php?page=cirilinfo' ),
			),
			array( 'event_body' )
		);
	}

	public static function layout( string $title, string $content ): string {
		$brand = esc_html( (string) Settings::get( 'company_name', 'CirilInfo' ) );
		$tag   = esc_html( (string) Settings::get( 'tagline', '' ) );
		$addr  = esc_html( (string) Settings::get( 'address', '' ) );
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $title ) . '</title></head>' .
			'<body style="margin:0;background:#f3f5fb;font-family:Arial,Helvetica,sans-serif;color:#1c2540">' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">' .
			'<table role="presentation" width="600" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden">' .
			'<tr><td style="background:linear-gradient(135deg,#1b1464,#5b3df5);padding:22px 28px;color:#fff"><div style="font-size:22px;font-weight:700">' . $brand . '</div><div style="font-size:12px;opacity:.85">' . $tag . '</div></td></tr>' .
			'<tr><td style="padding:28px;font-size:15px;line-height:1.6">' . $content . '</td></tr>' . // phpcs:ignore WordPress.Security.EscapeOutput -- templates sanitised with wp_kses_post.
			'<tr><td style="padding:16px 28px;background:#f7f8fc;font-size:12px;color:#6b7390">' . $brand . ( $addr ? ' · ' . $addr : '' ) . '</td></tr>' .
			'</table></td></tr></table></body></html>';
	}
}
