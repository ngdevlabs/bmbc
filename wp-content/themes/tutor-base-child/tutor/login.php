<?php
/**
 * Custom Override: Tutor LMS Login Page Template
 *
 * @package Tutor\Templates
 */

use TUTOR\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tutor_utils()->get_option( 'enable_tutor_native_login', null, true, true ) ) {
	header( 'Location: ' . wp_login_url( tutor_utils()->get_current_url() ) );
	exit;
}

tutor_utils()->tutor_custom_header();
$login_url = tutor_utils()->get_option( 'enable_tutor_native_login', null, true, true ) ? '' : wp_login_url( tutor()->current_url );
$should_load_legacy_scripts = Assets::should_load_legacy_scripts();
?>

<?php do_action( 'tutor/template/login/before/wrap' ); ?>
<div <?php tutor_post_class( 'tutor-page-wrap tutor-w-full' ); ?>>
	<div class="tutor-template-segment tutor-login-wrap tutor-card tutor-shadow-md" style="max-width: 440px; width: 100%; margin: 60px auto; background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08);">
		<div class="tutor-login-form-wrapper" style="padding: 36px 32px;">
			<div class="tutor-h3" style="font-size: 24px; font-weight: 700; color: #111827; margin-bottom: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
				<?php esc_html_e( 'Hi, Welcome back!', 'tutor' ); ?>
			</div>
			<?php
				tutor_load_template( 'login-form' );
			?>
		</div>
		<?php do_action( 'tutor_after_login_form_wrapper' ); ?>
	</div>
</div>
<?php
	do_action( 'tutor/template/login/after/wrap' );
	tutor_utils()->tutor_custom_footer();
?>
