<?php
/**
 * Already logged in template - Custom Override
 *
 * Shows a styled message with a button that redirects to NextGen Settings redirect URL.
 *
 * @package Tutor\Templates
 * @subpackage Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();

// Get redirect URL from NextGen Settings.
$redirect_url = '';
$custom_url   = trim( get_option( 'jeffrey_tutor_login_redirect_custom_url', '' ) );
$page_id      = (int) get_option( 'jeffrey_tutor_login_redirect_page_id', 0 );

if ( ! empty( $custom_url ) ) {
	$redirect_url = $custom_url;
} elseif ( $page_id > 0 ) {
	$redirect_url = get_permalink( $page_id );
}

// Fallback: Home page.
if ( empty( $redirect_url ) ) {
	$redirect_url = home_url( '/' );
}
?>

<style>
.tlp-logged-in-wrap {
	text-align: center;
	padding: 40px 20px;
	max-width: 520px;
	margin: 0 auto;
}
.tlp-logged-in-wrap .tlp-checkmark {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 64px;
	height: 64px;
	border-radius: 50%;
	background: linear-gradient(135deg, #bc18e1 0%, #9b10c2 100%);
	margin-bottom: 20px;
}
.tlp-logged-in-wrap .tlp-checkmark svg {
	width: 32px;
	height: 32px;
	fill: none;
	stroke: #fff;
	stroke-width: 3;
	stroke-linecap: round;
	stroke-linejoin: round;
}
.tlp-logged-in-wrap h2 {
	font-size: 22px;
	font-weight: 700;
	color: #1d2327;
	margin: 0 0 8px;
}
.tlp-logged-in-wrap .tlp-subtitle {
	font-size: 15px;
	color: #666;
	margin: 0 0 28px;
	line-height: 1.5;
}
.tlp-logged-in-wrap .tlp-continue-btn {
	display: inline-block;
	padding: 14px 36px;
	background: linear-gradient(135deg, #bc18e1 0%, #9b10c2 100%);
	color: #fff !important;
	text-decoration: none !important;
	border-radius: 8px;
	font-size: 16px;
	font-weight: 600;
	transition: all 0.3s ease;
	box-shadow: 0 4px 15px rgba(188, 24, 225, 0.3);
	letter-spacing: 0.3px;
}
.tlp-logged-in-wrap .tlp-continue-btn:hover {
	transform: translateY(-2px);
	box-shadow: 0 6px 20px rgba(188, 24, 225, 0.45);
	opacity: 0.95;
}
.tlp-logged-in-wrap .tlp-welcome {
	font-size: 14px;
	color: #999;
	margin-top: 20px;
}
.tlp-logged-in-wrap .tlp-logout-btn {
	display: inline-block;
	padding: 10px 28px;
	background: transparent;
	color: #888 !important;
	text-decoration: none !important;
	border: 1px solid #ddd;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 500;
	transition: all 0.3s ease;
	margin-top: 12px;
}
.tlp-logged-in-wrap .tlp-logout-btn:hover {
	border-color: #dc3545;
	color: #dc3545 !important;
	background: #fff5f5;
}
</style>

<div class="tlp-logged-in-wrap">
	<div class="tlp-checkmark">
		<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
	</div>

	<h2><?php esc_html_e( "You're Already Logged In", 'tutor' ); ?></h2>

	<p class="tlp-subtitle">
		<?php esc_html_e( 'Click below to continue where you left off.', 'tutor' ); ?>
	</p>

	<a href="<?php echo esc_url( $redirect_url ); ?>" class="tlp-continue-btn">
		<?php esc_html_e( 'Continue', 'tutor' ); ?> &rarr;
	</a>

	<p class="tlp-welcome">
		<?php
		printf(
			/* translators: %s: user display name */
			esc_html__( 'Welcome back, %s!', 'tutor' ),
			'<strong>' . esc_html( $current_user->display_name ) . '</strong>'
		);
		?>
	</p>

	<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="tlp-logout-btn">
		<?php esc_html_e( 'Logout', 'tutor' ); ?>
	</a>
</div>
