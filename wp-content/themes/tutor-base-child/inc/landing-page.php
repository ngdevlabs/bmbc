<?php
/** Landing actions and accessible dialogs, retaining the authored Kirki design. */
defined( 'ABSPATH' ) || exit;

function bmb_use_landing_actions() {
    return is_front_page() && 'kirki' !== ( $_GET['action'] ?? '' );
}

function bmb_introduction_video_url() {
    return trailingslashit( wp_get_upload_dir()['baseurl'] ) . '2026/05/Introduction.mp4';
}

add_filter( 'kirki_element_generator_container', static function ( $html, $context ) {
    if ( ! bmb_use_landing_actions() || 'dp6mc90p' !== ( $context['element']['id'] ?? '' ) ) { return $html; }
    $account = is_user_logged_in()
        ? '<a class="bmb-header-login" href="' . esc_url( tutor_utils()->tutor_dashboard_url() ) . '">Dashboard</a>'
        : '<a class="bmb-header-login" href="' . esc_url( wp_login_url( tutor_utils()->tutor_dashboard_url() ) ) . '" data-bmb-dialog="bmb-login-dialog" aria-haspopup="dialog" aria-controls="bmb-login-dialog">Log In</a>';
    return '<nav class="bmb-landing-header-nav" aria-label="Main navigation"><a class="bmb-landing-contact" href="' . esc_url( home_url( '/contact/' ) ) . '">Contact Us</a>' . $account . '</nav>';
}, 20, 2 );

add_filter( 'kirki_element_generator_div', static function ( $html, $context ) {
    if ( ! bmb_use_landing_actions() || 'k-tpir1vvx' !== ( $context['element']['id'] ?? '' ) ) { return $html; }
    $element = $context['element'];
    // Keep the original title, description, and background; move authentication out of the hero.
    $element['children'] = array_values( array_diff( $element['children'], array( 'k-3qop7tuf', 'k-x762yk7e' ) ) );
    $wrapper = new WP_HTML_Tag_Processor( '<div ' . $context['attributes'] . '>' );
    $wrapper->next_tag();
    $wrapper->add_class( 'bmb-landing-hero' );
    $hero = $wrapper->get_updated_html();
    foreach ( $element['children'] as $child ) {
        $hero .= call_user_func( $context['generate_child_element'], $child, $context['options'] );
    }
    $play = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
    $arrow = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>';
    $signup = function_exists( 'tutor_utils' ) ? tutor_utils()->student_register_url() : home_url( '/student-registration-page/' );
    $actions = '<div class="bmb-landing-actions" aria-label="Get started"><a class="bmb-landing-action bmb-watch-intro" href="' . esc_url( bmb_introduction_video_url() ) . '" data-bmb-dialog="bmb-introduction-dialog" aria-haspopup="dialog" aria-controls="bmb-introduction-dialog">' . $play . '<span>Watch Introduction Video</span></a><a class="bmb-landing-action bmb-start-free" href="' . esc_url( $signup ) . '"><span>Start My 2 FREE Classes</span>' . $arrow . '</a></div>';
    $hero .= $actions . '</div>';
    $login = '';
    if ( ! is_user_logged_in() ) {
        // Render through Kirki so its nonce, login handler, and error messages remain intact.
        $form = call_user_func( $context['generate_child_element'], 'k-3qop7tuf', $context['options'] );
        $tags = new WP_HTML_Tag_Processor( $form );
        while ( $tags->next_tag() ) {
            $name = $tags->get_attribute( 'name' );
            if ( 'INPUT' === $tags->get_tag() && in_array( $name, array( 'email', 'password' ), true ) ) {
                $tags->set_attribute( 'id', 'bmb-login-' . $name );
                $tags->set_attribute( 'autocomplete', 'email' === $name ? 'username' : 'current-password' );
            }
            $label = $tags->get_attribute( 'data-kirki' );
            if ( 'LABEL' === $tags->get_tag() && in_array( $label, array( 'k-6na9dizn', 'k-3sr07oon' ), true ) ) {
                $tags->set_attribute( 'for', 'bmb-login-' . ( 'k-6na9dizn' === $label ? 'email' : 'password' ) );
            }
        }
        $form = $tags->get_updated_html();
        $login = '<dialog id="bmb-login-dialog" class="bmb-landing-dialog bmb-login-dialog" aria-label="Student log in"><button class="bmb-dialog-close" type="button" data-bmb-close aria-label="Close sign-in form">&times;</button>' . $form . '</dialog>';
    }
    $poster = trailingslashit( wp_get_upload_dir()['baseurl'] ) . '2026/09/hero-image.jpg';
    $video = '<dialog id="bmb-introduction-dialog" class="bmb-landing-dialog bmb-video-dialog" aria-labelledby="bmb-introduction-title"><div class="bmb-video-dialog-header"><h2 id="bmb-introduction-title">Welcome to Bible Mastery Bootcamp</h2><button class="bmb-dialog-close" type="button" data-bmb-close aria-label="Close introduction video">&times;</button></div><video id="bmb-introduction-video" controls playsinline preload="none" poster="' . esc_url( $poster ) . '" data-introduction-src="' . esc_url( bmb_introduction_video_url() ) . '" aria-label="Bible Mastery Bootcamp introduction"><p>Your browser does not support this video. <a href="' . esc_url( bmb_introduction_video_url() ) . '">Watch the introduction video</a>.</p></video></dialog>';
    return $hero . $login . $video;
}, 20, 2 );

add_filter( 'kirki_element_generator_image', static function ( $html, $context ) {
    if ( $html || ! bmb_use_landing_actions() || 'dpry67b2' !== ( $context['element']['id'] ?? '' ) ) { return $html; }
    $element = $context['element'];
    $element['properties']['load'] = 'eager';
    $element['properties']['attributes']['alt'] = 'THE BIBLE. UNDERSTOOD.';
    return call_user_func( $context['generate_element'], $element, $context['options'] );
}, 20, 2 );

add_action( 'wp_enqueue_scripts', static function () {
    if ( ! bmb_use_landing_actions() ) { return; }
    $dir = get_stylesheet_directory(); $uri = get_stylesheet_directory_uri();
    wp_enqueue_style( 'bmb-landing-page', $uri . '/assets/css/landing-page.css', array( 'child-style' ), filemtime( $dir . '/assets/css/landing-page.css' ) );
    wp_enqueue_script( 'bmb-landing-page', $uri . '/assets/js/landing-page.js', array( 'bmb-landing-login' ), filemtime( $dir . '/assets/js/landing-page.js' ), true );
    wp_localize_script( 'bmb-landing-page', 'bmbLanding', array( 'dashboardUrl' => tutor_utils()->tutor_dashboard_url() ) );
}, 20 );
