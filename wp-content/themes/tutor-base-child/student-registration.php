<?php
/** BMBC signup using Tutor's native registration handler. */
defined( 'ABSPATH' ) || exit;
use Tutor\GDPR\Controllers\LegalConsent;
$country = bmb_signup_value( 'country', 'US' );
$state = bmb_signup_value( 'state' );
get_header();
?>
<main class="bmb-signup-main">
    <div class="bmb-portal-container"><?php bmb_student_navigation(); ?></div>
    <section class="bmb-signup-card" aria-labelledby="signup-title">
    <?php if ( is_user_logged_in() ) : ?>
        <h1 id="signup-title">You're Already Logged In</h1>
        <p>Continue to your student dashboard.</p>
        <a class="bmb-signup-submit" href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url() ); ?>">Continue →</a>
    <?php elseif ( ! get_option( 'users_can_register', false ) ) : ?>
        <h1 id="signup-title">Registration is currently closed</h1>
        <p>Please <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact us</a> for help.</p>
    <?php else : ?>
        <h1 id="signup-title">Create an Account</h1>
        <?php do_action( 'tutor_before_student_reg_form' ); ?>
        <?php $errors = apply_filters( 'tutor_student_register_validation_errors', array() ); ?>
        <?php if ( $errors ) : ?><div class="bmb-signup-errors" role="alert"><ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( is_array( $error ) ? implode( ' ', $error ) : $error ); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" id="tutor-registration-form" class="bmb-signup-form">
            <?php wp_nonce_field( tutor()->nonce_action, tutor()->nonce ); ?>
            <input type="hidden" name="tutor_action" value="tutor_register_student">
            <input type="hidden" name="tutor_course_enroll_attempt" value="<?php echo esc_attr( absint( $_GET['enrol_course_id'] ?? 0 ) ); ?>">
            <?php do_action( 'tutor_student_reg_form_start' ); ?>
            <div class="bmb-signup-row">
                <div><label for="signup-first-name">First Name</label><input id="signup-first-name" name="first_name" autocomplete="given-name" maxlength="100" required value="<?php echo esc_attr( bmb_signup_value( 'first_name' ) ); ?>"></div>
                <div><label for="signup-last-name">Last Name</label><input id="signup-last-name" name="last_name" autocomplete="family-name" maxlength="100" required value="<?php echo esc_attr( bmb_signup_value( 'last_name' ) ); ?>"></div>
            </div>
            <div><label for="signup-country">Country</label><select id="signup-country" name="country" autocomplete="country" required><?php foreach ( bmb_signup_countries() as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $country, $code ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
            <div>
                <label id="signup-state-label" for="<?php echo 'US' === $country ? 'signup-state-us' : 'signup-state-other'; ?>">State</label>
                <select id="signup-state-us" name="state" autocomplete="address-level1" <?php echo 'US' === $country ? 'required' : 'hidden disabled'; ?>>
                    <option value="">Select your state</option><?php foreach ( bmb_signup_states() as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $state, $code ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                </select>
                <input id="signup-state-other" name="state" autocomplete="address-level1" maxlength="100" value="<?php echo esc_attr( 'US' !== $country ? $state : '' ); ?>" <?php echo 'US' === $country ? 'hidden disabled' : 'required'; ?>>
            </div>
            <div><label for="signup-email">Email</label><input id="signup-email" type="email" name="email" autocomplete="email" required value="<?php echo esc_attr( bmb_signup_value( 'email' ) ); ?>"></div>
            <div><label for="signup-password">Password</label><input id="signup-password" type="password" name="password" autocomplete="new-password" minlength="8" required aria-describedby="signup-password-hint"><small id="signup-password-hint">Use at least 8 characters.</small></div>
            <div><label for="signup-confirm-password">Confirm Password</label><input id="signup-confirm-password" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></div>
            <?php do_action( 'tutor_student_reg_form_middle' ); do_action( 'register_form' ); do_action( 'tutor_student_reg_form_end' ); ?>
            <?php $consents = LegalConsent::get_consent_by_display_key( LegalConsent::DISPLAY_ON_STD_REG ); ?>
            <?php foreach ( $consents as $consent ) { LegalConsent::render_consent_field( $consent, 'bmb-signup-consent' ); } ?>
            <?php $terms = tutor_utils()->get_toc_page_link(); if ( ! $consents && $terms ) : ?>
                <label class="bmb-signup-consent"><input type="checkbox" name="terms_conditions" required> I agree to the <a href="<?php echo esc_url( $terms ); ?>" target="_blank" rel="noopener">Terms and Conditions</a>.</label>
            <?php endif; ?>
            <button class="bmb-signup-submit" type="submit" name="tutor_register_student_btn" value="register">Create an Account</button>
            <?php do_action( 'tutor_after_register_button' ); ?>
        </form>
        <p class="bmb-signup-login">Already have an account? <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Log In</a></p>
        <?php do_action( 'tutor_after_registration_form_wrap' ); do_action( 'tutor_after_student_reg_form' ); ?>
    <?php endif; ?>
    </section>
</main>
<?php get_footer(); ?>
