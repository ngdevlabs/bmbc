<?php
/** Run locally with wp eval-file; no accounts are created. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
if ( 'local' !== wp_get_environment_type() ) { WP_CLI::error( 'Run registration checks locally.' ); }
$count = 0;
$assert = static function ( $ok, $message ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$count; WP_CLI::log( 'PASS: ' . $message ); };
$countries = bmb_signup_countries();
$assert( 'US' === array_key_first( $countries ), 'United States is the default country.' );
$assert( isset( $countries['GB'], $countries['FR'], $countries['DE'] ) && ! isset( $countries['CA'] ) && ! isset( $countries['AU'] ) && ! isset( $countries['BR'] ), 'The country list includes Europe and the U.S. exception.' );
$assert( 51 === count( bmb_signup_states() ) && isset( bmb_signup_states()['DC'] ), 'All 50 U.S. states and D.C. are available.' );
$valid = array( 'first_name' => 'Test', 'last_name' => 'Student', 'country' => 'US', 'state' => 'CA', 'password' => 'Example-Strong!3917', 'password_confirmation' => 'Example-Strong!3917' );
$assert( ! bmb_signup_validate( $valid )->has_errors(), 'Valid U.S. signup fields pass server validation.' );
$assert( ! bmb_signup_validate( array_merge( $valid, array( 'country' => 'GB', 'state' => 'West Yorkshire' ) ) )->has_errors(), 'A European country accepts a typed state.' );
$assert( bmb_signup_validate( array_merge( $valid, array( 'country' => 'AU' ) ) )->get_error_message( 'country' ), 'A forged country outside the allowlist is rejected.' );
$assert( bmb_signup_validate( array_merge( $valid, array( 'state' => 'Not a state' ) ) )->get_error_message( 'state' ), 'An invalid U.S. state is rejected.' );
$assert( bmb_signup_validate( array_merge( $valid, array( 'country' => 'GB', 'state' => '' ) ) )->get_error_message( 'state' ), 'A foreign state cannot be omitted.' );
$assert( bmb_signup_validate( array_merge( $valid, array( 'first_name' => '', 'last_name' => '' ) ) )->get_error_message( 'first_name' ), 'Names are required on the server.' );
$assert( bmb_signup_validate( array_merge( $valid, array( 'password_confirmation' => 'different' ) ) )->get_error_message( 'password_confirmation' ), 'A password confirmation mismatch is rejected.' );
$assert( bmb_signup_validate( array_merge( $valid, array( 'password' => 'short', 'password_confirmation' => 'short' ) ) )->get_error_message( 'password' ), 'The minimum password length is enforced on the server.' );
$user_id = get_current_user_id();
try {
    wp_set_current_user( 0 );
    ob_start(); bmb_student_navigation(); $nav = ob_get_clean();
    $assert( false === strpos( $nav, '>Dashboard</a>' ) && false === strpos( $nav, '>Syllabus</a>' ), 'Guest navigation hides Dashboard and Syllabus.' );
    $assert( false !== strpos( $nav, 'href="https://ybhstore.company.site/" target="_blank"' ) && false !== strpos( $nav, 'href="' . esc_url( home_url( '/contact/' ) ) . '"' ) && false !== strpos( $nav, '>Log In</a>' ), 'Guest Store, Contact Us and Log In links are correct.' );
} finally { wp_set_current_user( $user_id ); }
WP_CLI::success( $count . ' registration checks passed.' );
