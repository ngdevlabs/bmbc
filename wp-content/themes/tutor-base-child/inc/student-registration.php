<?php
/** Student signup fields using Tutor's existing registration and consent flow. */
defined( 'ABSPATH' ) || exit;

function bmb_signup_countries() {
    $countries = WC()->countries->get_countries();
    $europe = WC()->countries->get_continents()['EU']['countries'];
    $allowed = array_intersect_key( $countries, array_fill_keys( $europe, true ) );
    asort( $allowed );
    return array( 'US' => $countries['US'] ) + $allowed;
}

function bmb_signup_states() {
    return array_diff_key( WC()->countries->get_states( 'US' ), array_flip( array( 'AA', 'AE', 'AP' ) ) );
}

function bmb_signup_submission() {
    return bmb_is_student_registration_page() && ! is_user_logged_in() && 'tutor_register_student' === ( $_POST['tutor_action'] ?? '' );
}

function bmb_signup_value( $name, $default = '' ) {
    return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : $default;
}

function bmb_signup_validate( $data ) {
    $errors = new WP_Error();
    foreach ( array( 'first_name' => 'First Name', 'last_name' => 'Last Name' ) as $key => $label ) {
        if ( empty( trim( $data[ $key ] ?? '' ) ) || mb_strlen( $data[ $key ] ) > 100 ) {
            $errors->add( $key, $label . ' is required and must be 100 characters or fewer.' );
        }
    }
    $country = $data['country'] ?? '';
    $state = trim( $data['state'] ?? '' );
    if ( ! isset( bmb_signup_countries()[ $country ] ) ) {
        $errors->add( 'country', 'Please select United States or a European country.' );
    }
    if ( 'US' === $country ) {
        if ( ! isset( bmb_signup_states()[ $state ] ) ) { $errors->add( 'state', 'Please select a valid U.S. state.' ); }
    } elseif ( '' === $state || mb_strlen( $state ) > 100 ) {
        $errors->add( 'state', 'Please enter your state or region (100 characters or fewer).' );
    }
    $password = $data['password'] ?? '';
    if ( strlen( sanitize_text_field( $password ) ) < 8 || strlen( $password ) > 4096 ) {
        $errors->add( 'password', 'Please use a password with at least 8 characters.' );
    }
    if ( $password !== ( $data['password_confirmation'] ?? '' ) ) {
        $errors->add( 'password_confirmation', 'The passwords do not match.' );
    }
    return $errors;
}

// Create the internal username; students sign in with their email address.
add_action( 'template_redirect', static function () {
    if ( ! bmb_signup_submission() ) { return; }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ tutor()->nonce ] ?? '' ) ), tutor()->nonce_action ) ) { return; }
    $email = bmb_signup_value( 'email' );
    $base = substr( sanitize_user( strtok( $email, '@' ), true ), 0, 40 ) ?: 'student';
    do { $login = $base . '-' . strtolower( wp_generate_password( 8, false ) ); } while ( username_exists( $login ) );
    $_POST['user_login'] = $login;
    $_REQUEST['user_login'] = $login;
}, 0 );

add_filter( 'registration_errors', static function ( $errors ) {
    if ( ! bmb_signup_submission() ) { return $errors; }
    $data = array();
    foreach ( array( 'first_name', 'last_name', 'country', 'state' ) as $key ) { $data[ $key ] = bmb_signup_value( $key ); }
    foreach ( array( 'password', 'password_confirmation' ) as $key ) { $data[ $key ] = is_string( $_POST[ $key ] ?? null ) ? wp_unslash( $_POST[ $key ] ) : ''; }
    $errors->merge_from( bmb_signup_validate( $data ) );
    return $errors;
}, 20 );

add_action( 'user_register', static function ( $user_id ) {
    if ( ! bmb_signup_submission() ) { return; }
    update_user_meta( $user_id, 'billing_country', bmb_signup_value( 'country' ) );
    update_user_meta( $user_id, 'billing_state', bmb_signup_value( 'state' ) );
    ( new WP_User( $user_id ) )->set_role( 'subscriber' );
} );

add_filter( 'template_include', static function ( $template ) {
    if ( ! bmb_is_student_registration_page() || ( isset( $_GET['action'] ) && 'kirki' === $_GET['action'] && current_user_can( 'edit_post', get_queried_object_id() ) ) ) { return $template; }
    return get_stylesheet_directory() . '/student-registration.php';
}, 120 );
add_action( 'template_redirect', static function () {
    if ( bmb_is_student_registration_page() ) {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        nocache_headers();
    }
}, 1 );
add_action( 'wp_enqueue_scripts', static function () {
    if ( ! bmb_is_student_registration_page() ) { return; }
    $dir = get_stylesheet_directory(); $uri = get_stylesheet_directory_uri();
    wp_enqueue_style( 'bmb-student-registration', $uri . '/assets/css/student-registration.css', array( 'bmb-student-navigation' ), filemtime( $dir . '/assets/css/student-registration.css' ) );
    wp_enqueue_script( 'bmb-student-registration', $uri . '/assets/js/student-registration.js', array(), filemtime( $dir . '/assets/js/student-registration.js' ), true );
} );
