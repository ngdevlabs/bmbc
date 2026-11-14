<?php
/**
 * Recommended way to include parent theme styles.
 * (Please see http://codex.wordpress.org/Child_Themes#How_to_Create_a_Child_Theme)
 *
 */  

add_action( 'wp_enqueue_scripts', 'tutor_base_child_style', 999 );
function tutor_base_child_style() {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );

	if ( function_exists( 'tutor' ) ) {
		wp_enqueue_style( 'tutor-core', tutor()->assets_url . 'css/tutor-core.min.css', array(), TUTOR_VERSION );
	}

	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array('parent-style', 'tutor-core'), filemtime( get_stylesheet_directory() . '/style.css' ) );
}

/**
 * Your code goes below.
 */

function bmb_logo_url() {
    return get_stylesheet_directory_uri() . '/assets/images/bbb-logo.webp';
}

// Render the shared header and landing page logos from a deployed theme asset.
add_filter( 'kirki_element_generator_image', 'bmb_render_header_logo', 10, 2 );
function bmb_render_header_logo( $html, $context ) {
    $element = $context['element'];
    $properties = $element['properties'] ?? array();
    // Kirki preserves this logo property ID across shared header instances.
    $is_header_logo = 'sepd6o1e1' === ( $properties['symbolElPropId'] ?? '' );
    $is_landing_logo = 'bmb-header-logo' === ( $element['id'] ?? '' );

    if ( $html || ( ! $is_header_logo && ! $is_landing_logo ) ) {
        return $html;
    }

    $element['properties']['attributes']['src'] = bmb_logo_url();
    $element['properties']['attributes']['alt'] = 'Bible Mastery Bootcamp';
    $element['properties']['load'] = 'eager';
    // The previous attachment's responsive sources must not restore the old logo.
    unset( $element['properties']['wp_attachment_id'], $element['properties']['dynamicContent'] );
    unset( $element['properties']['attributes']['srcset'], $element['properties']['attributes']['sizes'] );

    return call_user_func( $context['generate_element'], $element, $context['options'] );
}

// Tutor dashboard and invoice templates request the full-size custom logo directly.
add_filter( 'wp_get_attachment_image_src', 'bmb_custom_logo_image_source', 10, 3 );
function bmb_custom_logo_image_source( $image, $attachment_id, $size ) {
    $logo_id = (int) get_theme_mod( 'custom_logo' );
    if ( $logo_id && 'full' === $size && $logo_id === (int) $attachment_id ) {
        return array( bmb_logo_url(), 1146, 382, false );
    }
    return $image;
}

// Cover templates that use WordPress's standard custom logo instead of Kirki.
add_filter( 'get_custom_logo', 'bmb_render_wordpress_logo' );
function bmb_render_wordpress_logo( $html ) {
    $tags = new WP_HTML_Tag_Processor( $html );
    if ( $tags->next_tag( 'IMG' ) ) {
        $tags->set_attribute( 'src', bmb_logo_url() );
        $tags->set_attribute( 'alt', 'Bible Mastery Bootcamp' );
        $tags->set_attribute( 'width', 180 );
        $tags->set_attribute( 'height', 60 );
        $tags->remove_attribute( 'srcset' );
        $tags->remove_attribute( 'sizes' );
    }
    return $tags->get_updated_html();
}

// FTP deploys files individually; keep Tutor's dashboard until both new PHP files arrive.
if ( file_exists( get_stylesheet_directory() . '/inc/student-dashboard.php' ) && file_exists( get_stylesheet_directory() . '/student-dashboard.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/student-dashboard.php';
}

// Keep student registration as simple as the landing page, including when already logged in.
function bmb_is_student_registration_page() {
    $page_id = function_exists( 'tutor_utils' ) ? (int) tutor_utils()->get_option( 'student_register_page' ) : 0;
    return ( $page_id > 0 && is_page( $page_id ) ) || is_page( 'student-registration-page' );
}
add_filter( 'kirki_show_custom_section_footer', static function ( $show ) {
    return bmb_is_student_registration_page() ? false : $show;
} );
add_action( 'wp_footer', static function () {
    if ( bmb_is_student_registration_page() ) {
        echo '<footer class="bmb-simple-footer">Copyright &copy; ' . esc_html( wp_date( 'Y' ) ) . ' Israeltrue</footer>';
    }
}, 5 );

// Styles for the standalone contact form.
add_action( 'wp_enqueue_scripts', 'bmb_enqueue_contact_form' );
function bmb_enqueue_contact_form() {
    if ( ! is_page_template( 'page-contact-form.php' ) ) {
        return;
    }
    $style_path = '/assets/css/contact-form.css';
    wp_enqueue_style(
        'bmb-contact-form',
        get_stylesheet_directory_uri() . $style_path,
        array( 'child-style' ),
        filemtime( get_stylesheet_directory() . $style_path )
    );
}

// Show/hide password control for the landing page login form.
add_action( 'wp_enqueue_scripts', 'bmb_enqueue_landing_login' );
function bmb_enqueue_landing_login() {
    if ( ! is_front_page() ) {
        return;
    }

    $script_path = '/assets/js/landing-login.js';
    wp_enqueue_script(
        'bmb-landing-login',
        get_stylesheet_directory_uri() . $script_path,
        array(),
        filemtime( get_stylesheet_directory() . $script_path ),
        true
    );
}


/* ========================================================
   BMB Settings – Dashboard Settings Page
   ======================================================== */

/**
 * 1) Register Admin Menu
 */
add_action( 'admin_menu', 'jeffrey_nextgen_menu' );
function jeffrey_nextgen_menu() {
    add_menu_page(
        'BMB Settings',               // Page title
        'BMB Settings',               // Menu title
        'manage_options',             // Capability
        'jeffrey-nextgen-setting',    // Menu slug
        'jeffrey_nextgen_settings_page', // Callback
        'dashicons-admin-generic',    // Icon
        3                             // Position
    );
}

/**
 * 2) Register Settings
 */
add_action( 'admin_init', 'jeffrey_nextgen_register_settings' );
function jeffrey_nextgen_register_settings() {

    // --- Section: Non-Logged-In User Redirect ---
    add_settings_section(
        'jeffrey_non_login_section',
        'Non-Logged-In User Redirect',
        'jeffrey_non_login_section_cb',
        'jeffrey-nextgen-setting'
    );

    register_setting( 'jeffrey_nextgen_options', 'jeffrey_non_login_page_id', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    ) );

    add_settings_field(
        'jeffrey_non_login_page_id',
        'Select Page for Non-Logged-In Users',
        'jeffrey_non_login_page_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_non_login_section'
    );

    // Exclude pages option (multi-select)
    register_setting( 'jeffrey_nextgen_options', 'jeffrey_non_login_exclude_pages', array(
        'type'              => 'array',
        'sanitize_callback' => 'jeffrey_sanitize_exclude_pages',
        'default'           => array(),
    ) );

    add_settings_field(
        'jeffrey_non_login_exclude_pages',
        'Exclude Pages (Allow These Pages)',
        'jeffrey_non_login_exclude_pages_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_non_login_section'
    );

    // --- Section: Tutor LMS Login / Register Redirect ---
    add_settings_section(
        'jeffrey_tutor_redirect_section',
        'Tutor LMS – After Login / Register Redirect',
        'jeffrey_tutor_redirect_section_cb',
        'jeffrey-nextgen-setting'
    );

    register_setting( 'jeffrey_nextgen_options', 'jeffrey_tutor_login_redirect_page_id', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    ) );

    add_settings_field(
        'jeffrey_tutor_login_redirect_page_id',
        'Redirect Page After Login / Register',
        'jeffrey_tutor_login_redirect_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_tutor_redirect_section'
    );

    // Custom URL option
    register_setting( 'jeffrey_nextgen_options', 'jeffrey_tutor_login_redirect_custom_url', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ) );

    add_settings_field(
        'jeffrey_tutor_login_redirect_custom_url',
        'Or Enter Custom Redirect URL',
        'jeffrey_tutor_login_redirect_custom_url_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_tutor_redirect_section'
    );

    // --- Section: Post-Payment & Subscription Redirect ---
    add_settings_section(
        'jeffrey_payment_redirect_section',
        'Tutor LMS – Post-Payment / Subscription Redirect',
        'jeffrey_payment_redirect_section_cb',
        'jeffrey-nextgen-setting'
    );

    register_setting( 'jeffrey_nextgen_options', 'jeffrey_payment_redirect_page_id', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    ) );

    add_settings_field(
        'jeffrey_payment_redirect_page_id',
        'Redirect Page After Successful Payment',
        'jeffrey_payment_redirect_page_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_payment_redirect_section'
    );

    register_setting( 'jeffrey_nextgen_options', 'jeffrey_payment_redirect_custom_url', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ) );

    add_settings_field(
        'jeffrey_payment_redirect_custom_url',
        'Or Enter Custom Redirect URL',
        'jeffrey_payment_redirect_custom_url_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_payment_redirect_section'
    );

    // --- Section: Mark as Complete Button Control ---
    add_settings_section(
        'jeffrey_mark_complete_section',
        'Lesson – "Mark as Complete" Button Control',
        'jeffrey_mark_complete_section_cb',
        'jeffrey-nextgen-setting'
    );

    // --- Completion Mode (Flexible / Strict) ---
    register_setting( 'jeffrey_nextgen_options', 'jeffrey_completion_mode', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'flexible',
    ) );

    add_settings_field(
        'jeffrey_completion_mode',
        'Course Completion Mode',
        'jeffrey_completion_mode_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_mark_complete_section'
    );

    // --- Video Lesson Completion Control ---
    register_setting( 'jeffrey_nextgen_options', 'jeffrey_video_completion_control', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '0',
    ) );

    add_settings_field(
        'jeffrey_video_completion_control',
        'Video Lesson Completion Control',
        'jeffrey_video_completion_control_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_mark_complete_section'
    );

    // --- Required Watch Percentage ---
    register_setting( 'jeffrey_nextgen_options', 'jeffrey_video_required_percentage', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 80,
    ) );

    add_settings_field(
        'jeffrey_video_required_percentage',
        'Required Video Watch Percentage',
        'jeffrey_video_required_percentage_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_mark_complete_section'
    );

    // --- Section: Hide Admin Bar & Block wp-admin ---
    add_settings_section(
        'jeffrey_hide_adminbar_section',
        'Hide Admin Bar & Block wp-admin for Non-Admins',
        'jeffrey_hide_adminbar_section_cb',
        'jeffrey-nextgen-setting'
    );

    register_setting( 'jeffrey_nextgen_options', 'jeffrey_hide_adminbar_enabled', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '1',
    ) );

    add_settings_field(
        'jeffrey_hide_adminbar_enabled',
        'Enable Hide Top Bar & Block wp-admin',
        'jeffrey_hide_adminbar_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_hide_adminbar_section'
    );

    // --- Section: Auto-Enroll Courses on Registration --- (HIDDEN FOR NOW)
    /*
    add_settings_section(
        'jeffrey_auto_enroll_section',
        'Auto-Enroll on Registration (Preview Courses)',
        'jeffrey_auto_enroll_section_cb',
        'jeffrey-nextgen-setting'
    );

    register_setting( 'jeffrey_nextgen_options', 'jeffrey_auto_enroll_enabled', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '1',
    ) );

    add_settings_field(
        'jeffrey_auto_enroll_enabled',
        'Enable Auto-Enroll',
        'jeffrey_auto_enroll_enabled_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_auto_enroll_section'
    );

    add_settings_field(
        'jeffrey_auto_enroll_preview_courses',
        'Affected Courses (Preview Enabled)',
        'jeffrey_auto_enroll_preview_courses_field_cb',
        'jeffrey-nextgen-setting',
        'jeffrey_auto_enroll_section'
    );
    */
}

/**
 * 3) Section Descriptions
 */
function jeffrey_non_login_section_cb() {
    echo '<p>Select a page that will be shown to <strong>non-logged-in visitors</strong>. They will be forced to see only this page — all other pages will redirect here.<br><em>You can also exclude specific pages (like Student Login/Register) so those remain accessible.</em></p>';
}

/**
 * Sanitize exclude pages array
 */
function jeffrey_sanitize_exclude_pages( $input ) {
    if ( ! is_array( $input ) ) {
        return array();
    }
    return array_map( 'absint', $input );
}

function jeffrey_tutor_redirect_section_cb() {
    echo '<p>Select a page <strong>OR</strong> enter a custom URL where <strong>Tutor LMS students</strong> will be redirected after <strong>login or register</strong>.<br><em>Note: If a custom URL is entered, it will be used instead of the page dropdown.</em></p>';
}

function jeffrey_payment_redirect_section_cb() {
    $default_target = function_exists( 'jeffrey_get_default_post_payment_url' ) ? jeffrey_get_default_post_payment_url() : home_url( '/courses/bible-mastery-bootcamp/lessons/class-3-gods-national-covenant-with-israel/' );
    echo '<p>Configure where students are redirected immediately after completing their <strong>Stripe payment or subscription</strong>.<br>';
    echo 'By default, students are sent directly to <strong>Module One, Class 3</strong>: <code>' . esc_html( $default_target ) . '</code>.<br>';
    echo '<em>Note: If a custom URL or page is selected, it will override the default Module One, Class 3 redirect.</em></p>';
}

function jeffrey_hide_adminbar_section_cb() {
    echo '<p>When enabled, the <strong>WordPress top admin bar</strong> will be hidden for all non-admin users and they will be <strong>blocked from accessing /wp-admin</strong>. This is <strong>ON by default</strong>.</p>';
}

/**
 * Auto-Enroll Section Description (HIDDEN FOR NOW)
 */
/*
function jeffrey_auto_enroll_section_cb() {
    echo '<p>Automatically enroll new students into courses that have <strong>Free Preview</strong> lessons, so they see <strong>"Continue Learning"</strong> immediately after registration.</p>';
    echo '<p><strong>How it works:</strong></p>';
    echo '<ul style="list-style:disc; margin-left:20px;">';
    echo '<li>New students are auto-enrolled in preview courses on registration</li>';
    echo '<li>They see <strong>"Start Learning" / "Continue Learning"</strong> on the course page</li>';
    echo '<li>Only <strong>preview lessons</strong> are accessible — non-preview lessons <strong>remain locked</strong></li>';
    echo '<li>When the student actually purchases/enrolls normally, <strong>all lessons unlock</strong></li>';
    echo '</ul>';
}
*/

/**
 * Mark as Complete Section Description
 */
function jeffrey_mark_complete_section_cb() {
    echo '<p>Control the <strong>"Mark as Complete"</strong> button and <strong>Video Lesson Completion</strong> settings from here.</p>';
    echo '<p><strong>How it works:</strong></p>';
    echo '<ul style="list-style:disc; margin-left:20px;">';
    echo '<li><strong>Strict Mode + Video Control:</strong> Keeps the button visible but <strong>disables it</strong> until the student watches the required % of the video. The button will only become clickable after watching.</li>';
    echo '<li><strong>Flexible Mode:</strong> Students can mark lessons complete anytime (no video watch requirement).</li>';
    echo '</ul>';
}

/**
 * 4) Field Callbacks – Page Dropdowns
 */
function jeffrey_non_login_page_field_cb() {
    $selected = get_option( 'jeffrey_non_login_page_id', 0 );
    $pages    = get_pages();
    echo '<select name="jeffrey_non_login_page_id" id="jeffrey_non_login_page_id">';
    echo '<option value="0"' . selected( $selected, 0, false ) . '>— Disabled (no redirect) —</option>';
    foreach ( $pages as $page ) {
        echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( $selected, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Choose a page. Non-logged-in users will only see this page.</p>';
}

function jeffrey_non_login_exclude_pages_field_cb() {
    $selected_pages = get_option( 'jeffrey_non_login_exclude_pages', array() );
    if ( ! is_array( $selected_pages ) ) {
        $selected_pages = array();
    }
    $pages = get_pages();
    echo '<select name="jeffrey_non_login_exclude_pages[]" id="jeffrey_non_login_exclude_pages" multiple="multiple" style="min-width:350px; min-height:150px;">';
    foreach ( $pages as $page ) {
        $is_selected = in_array( $page->ID, $selected_pages, true ) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr( $page->ID ) . '"' . $is_selected . '>' . esc_html( $page->post_title ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Hold <strong>Ctrl</strong> (or <strong>Cmd</strong> on Mac) to select multiple pages.<br>These pages will remain accessible to non-logged-in users (e.g. Student Login, Register page).</p>';
}

function jeffrey_tutor_login_redirect_field_cb() {
    $selected = get_option( 'jeffrey_tutor_login_redirect_page_id', 0 );
    $pages    = get_pages();
    echo '<select name="jeffrey_tutor_login_redirect_page_id" id="jeffrey_tutor_login_redirect_page_id">';
    echo '<option value="0"' . selected( $selected, 0, false ) . '>— Default (no custom redirect) —</option>';
    foreach ( $pages as $page ) {
        echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( $selected, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">After Tutor LMS login/register, students will be sent to this page.</p>';
}

function jeffrey_tutor_login_redirect_custom_url_cb() {
    $value = get_option( 'jeffrey_tutor_login_redirect_custom_url', '' );
    echo '<input type="url" name="jeffrey_tutor_login_redirect_custom_url" id="jeffrey_tutor_login_redirect_custom_url" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://example.com/my-page" />';
    echo '<p class="description">Enter a full URL (e.g. <code>https://example.com/dashboard</code>). If filled, this will override the page dropdown above.</p>';
}

function jeffrey_payment_redirect_page_field_cb() {
    $selected = get_option( 'jeffrey_payment_redirect_page_id', 0 );
    $pages    = get_pages();
    echo '<select name="jeffrey_payment_redirect_page_id" id="jeffrey_payment_redirect_page_id">';
    echo '<option value="0"' . selected( $selected, 0, false ) . '>— Default: Module One, Class 3 —</option>';
    foreach ( $pages as $page ) {
        echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( $selected, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Select a page to send paid students to after checkout.</p>';
}

function jeffrey_payment_redirect_custom_url_cb() {
    $value = get_option( 'jeffrey_payment_redirect_custom_url', '' );
    $default_target = function_exists( 'jeffrey_get_default_post_payment_url' ) ? jeffrey_get_default_post_payment_url() : home_url( '/courses/bible-mastery-bootcamp/lessons/class-3-gods-national-covenant-with-israel/' );
    echo '<input type="url" name="jeffrey_payment_redirect_custom_url" id="jeffrey_payment_redirect_custom_url" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $default_target ) . '" />';
    echo '<p class="description">Enter a custom URL (e.g. <code>' . esc_html( $default_target ) . '</code>). If filled, this overrides the dropdown.</p>';
}

function jeffrey_hide_adminbar_field_cb() {
    $value = get_option( 'jeffrey_hide_adminbar_enabled', '1' );
    echo '<label>';
    echo '<input type="checkbox" name="jeffrey_hide_adminbar_enabled" value="1"' . checked( $value, '1', false ) . ' />';
    echo ' Hide admin bar &amp; block <code>/wp-admin</code> for all users except Administrators';
    echo '</label>';
    echo '<p class="description">Default: <strong>ON</strong>. Uncheck to allow non-admin users to see the top bar and access wp-admin.</p>';
}

/**
 * Completion Mode – Field Callback
 */
function jeffrey_completion_mode_field_cb() {
    $value = get_option( 'jeffrey_completion_mode', 'flexible' );
    echo '<fieldset>';
    echo '<label style="display:block; margin-bottom:8px;">';
    echo '<input type="radio" name="jeffrey_completion_mode" value="flexible"' . checked( $value, 'flexible', false ) . ' />';
    echo ' <strong>Flexible</strong> — Students can mark lessons/course complete anytime';
    echo '</label>';
    echo '<label style="display:block; margin-bottom:8px;">';
    echo '<input type="radio" name="jeffrey_completion_mode" value="strict"' . checked( $value, 'strict', false ) . ' />';
    echo ' <strong>Strict</strong> — Students must complete all lessons, quizzes &amp; assignments in order. Video watch requirement applies in this mode.';
    echo '</label>';
    echo '</fieldset>';
    echo '<p class="description">In <strong>Strict</strong> mode, the "Mark as Complete" button stays visible but is <strong>disabled</strong> until requirements are met (e.g., video watched).</p>';
}

/**
 * Video Lesson Completion Control – Field Callback
 */
function jeffrey_video_completion_control_field_cb() {
    $value = get_option( 'jeffrey_video_completion_control', '0' );
    echo '<label>';
    echo '<input type="checkbox" name="jeffrey_video_completion_control" value="1"' . checked( $value, '1', false ) . ' />';
    echo ' Enable Video Lesson Completion Control';
    echo '</label>';
    echo '<p class="description">When enabled (with <strong>Strict</strong> mode), students <strong>must watch the required percentage</strong> of the lesson video before they can mark the lesson as complete.<br>';
    echo '<strong>Note:</strong> Only works with <strong>Strict</strong> completion mode and Tutor Player videos.</p>';
}

/**
 * Required Watch Percentage – Field Callback
 */
function jeffrey_video_required_percentage_field_cb() {
    $value = get_option( 'jeffrey_video_required_percentage', 80 );
    echo '<input type="number" name="jeffrey_video_required_percentage" value="' . esc_attr( $value ) . '" min="1" max="100" step="1" class="small-text" />';
    echo ' <strong>%</strong>';
    echo '<p class="description">Set the minimum percentage of video that must be watched before the lesson can be marked complete.<br>';
    echo 'Default: <strong>80%</strong>. Set to <strong>100</strong> to require watching the entire video.</p>';
}

/**
 * 5) Settings Page HTML
 */
function jeffrey_nextgen_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php settings_errors( 'jeffrey_nextgen_options' ); ?>

        <form method="post" action="options.php">
            <?php
                settings_fields( 'jeffrey_nextgen_options' );
                do_settings_sections( 'jeffrey-nextgen-setting' );
                submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}

/* ========================================================
   FRONT-END LOGIC
   ======================================================== */

/**
 * 6) Force non-logged-in users to the selected page only
 *    - Skips wp-login, wp-admin, admin-ajax, REST API, cron
 *    - Allows the selected page itself to load normally
 */
add_action( 'template_redirect', 'jeffrey_force_non_login_page' );
function jeffrey_force_non_login_page() {

    // Only for non-logged-in visitors
    if ( is_user_logged_in() ) {
        return;
    }

    $page_id = (int) get_option( 'jeffrey_non_login_page_id', 0 );

    // If disabled (0), do nothing
    if ( $page_id < 1 ) {
        return;
    }

    // Allow wp-login.php, wp-signup.php, admin-ajax, REST, cron
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) {
        return;
    }

    // Allow the login page itself
    global $pagenow;
    if ( in_array( $pagenow, array( 'wp-login.php', 'wp-signup.php' ), true ) ) {
        return;
    }

    // If already on the allowed page, don't redirect (avoid loop)
    if ( is_page( $page_id ) ) {
        return;
    }

    // Allow excluded pages (login, register, etc.)
    $exclude_pages = get_option( 'jeffrey_non_login_exclude_pages', array() );
    if ( ! is_array( $exclude_pages ) ) {
        $exclude_pages = array();
    }
    foreach ( $exclude_pages as $excluded_id ) {
        if ( is_page( (int) $excluded_id ) ) {
            return;
        }
    }

    // --- AUTO-EXCLUDE: Auth / Password / Account pages ---
    // These pages MUST be accessible to non-logged-in users
    $current_url = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $current_path = strtolower( trim( parse_url( $current_url, PHP_URL_PATH ), '/' ) );
    $query_string = isset( $_SERVER['QUERY_STRING'] ) ? strtolower( $_SERVER['QUERY_STRING'] ) : '';

    // 1) Allow wp-login.php with any action (lostpassword, resetpass, rp, register, etc.)
    if ( strpos( $current_url, 'wp-login.php' ) !== false ) {
        return;
    }

    // 2) Allow any URL with WP auth query params (action=lostpassword, action=rp, key=, login=, etc.)
    $auth_query_params = array(
        'action=lostpassword',
        'action=resetpass',
        'action=rp',
        'action=register',
        'action=confirmaction',
        'checkemail=confirm',
        'checkemail=registered',
    );
    foreach ( $auth_query_params as $param ) {
        if ( strpos( $query_string, $param ) !== false ) {
            return;
        }
    }

    // 3) Allow password reset URLs that contain a reset key
    if ( isset( $_GET['key'] ) || isset( $_GET['reset_key'] ) || isset( $_GET['login'] ) ) {
        // Password reset confirmation links contain ?key=xxx&login=xxx
        if ( isset( $_GET['key'] ) && isset( $_GET['login'] ) ) {
            return;
        }
        if ( isset( $_GET['reset_key'] ) ) {
            return;
        }
    }

    // 4) Allow Tutor LMS default endpoints, auth pages, and password reset/forgot pages by slug
    $allowed_slugs = array(
        'student-registration',
        'student-login',
        'create-account',
        'sign-up',
        'signup',
        'register',
        'login',
        'log-in',
        'lost-password',
        'lostpassword',
        'forgot-password',
        'forgotpassword',
        'forgot_password',
        'reset-password',
        'resetpassword',
        'reset_password',
        'password-reset',
        'retrieve-password',
        'set-new-password',
        'change-password',
        'changepassword',
        'change_password',
        'account/lost-password',
        'my-account/lost-password',
    );
    foreach ( $allowed_slugs as $slug ) {
        if ( strpos( $current_path, $slug ) !== false ) {
            return;
        }
    }

    // 5) Also allow WooCommerce / Tutor "my-account" lost password endpoint
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' ) ) {
        return;
    }

    // Redirect to the selected page
    wp_safe_redirect( get_permalink( $page_id ) );
    exit;
}

/**
 * 7) Tutor LMS – Redirect after Login
 *    WordPress login_redirect for standard WP login
 */
add_filter( 'login_redirect', 'jeffrey_tutor_login_redirect', 99, 3 );
function jeffrey_tutor_login_redirect( $redirect_to, $requested_redirect_to, $user ) {

    $custom_url = trim( get_option( 'jeffrey_tutor_login_redirect_custom_url', '' ) );
    $page_id    = (int) get_option( 'jeffrey_tutor_login_redirect_page_id', 0 );

    // Determine redirect URL: custom URL takes priority over page dropdown
    $target_url = '';
    if ( ! empty( $custom_url ) ) {
        $target_url = $custom_url;
    } elseif ( $page_id > 0 ) {
        $target_url = get_permalink( $page_id );
    }

    if ( empty( $target_url ) ) {
        return $redirect_to; // no custom redirect set
    }

    // Only apply to subscribers / students (not admins)
    if ( ! is_wp_error( $user ) && is_a( $user, 'WP_User' ) ) {
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            return $redirect_to;
        }
        return $target_url;
    }

    return $redirect_to;
}

/**
 * 7b) Tutor LMS AJAX Login – Override redirect URL
 *     Tutor LMS uses its own AJAX login, not WordPress login_redirect.
 *     This filter controls the hidden redirect_to field in Tutor's login form.
 *     Priority 9999 to override Tutor Pro's dashboard redirect.
 */
add_filter( 'tutor_after_login_redirect_url', 'jeffrey_tutor_ajax_login_redirect', 9999 );
function jeffrey_tutor_ajax_login_redirect( $url ) {

    $custom_url = trim( get_option( 'jeffrey_tutor_login_redirect_custom_url', '' ) );
    $page_id    = (int) get_option( 'jeffrey_tutor_login_redirect_page_id', 0 );

    if ( ! empty( $custom_url ) ) {
        return $custom_url;
    } elseif ( $page_id > 0 ) {
        return get_permalink( $page_id );
    }

    return $url;
}

/**
 * 7c) Tutor LMS – Also intercept the AJAX login success action
 *     to force redirect via wp_send_json_success override
 */
add_action( 'tutor_after_login_success', 'jeffrey_tutor_force_login_redirect', 1 );
function jeffrey_tutor_force_login_redirect( $user_id ) {

    $custom_url = trim( get_option( 'jeffrey_tutor_login_redirect_custom_url', '' ) );
    $page_id    = (int) get_option( 'jeffrey_tutor_login_redirect_page_id', 0 );

    $target_url = '';
    if ( ! empty( $custom_url ) ) {
        $target_url = $custom_url;
    } elseif ( $page_id > 0 ) {
        $target_url = get_permalink( $page_id );
    }

    if ( empty( $target_url ) ) {
        return;
    }

    // Don't redirect admins
    $user = get_userdata( $user_id );
    if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
        return;
    }

    // Force redirect – Tutor does wp_safe_redirect after this action,
    // so we override $_POST['redirect_to'] which Tutor reads
    $_POST['redirect_to'] = $target_url;
}

/**
 * 8) Tutor LMS – Redirect after Registration
 *    Uses tutor_student_register_redirect_url filter (correct Tutor hook)
 *    This fires AFTER auto-login (wp_set_auth_cookie) so student stays logged in.
 *    DO NOT use 'user_register' hook — it fires BEFORE auto-login and breaks it.
 */
add_filter( 'tutor_student_register_redirect_url', 'jeffrey_tutor_register_redirect_url', 9999, 2 );
function jeffrey_tutor_register_redirect_url( $redirect_page, $user = null ) {

    $custom_url = trim( get_option( 'jeffrey_tutor_login_redirect_custom_url', '' ) );
    $page_id    = (int) get_option( 'jeffrey_tutor_login_redirect_page_id', 0 );

    $target_url = '';
    if ( ! empty( $custom_url ) ) {
        $target_url = $custom_url;
    } elseif ( $page_id > 0 ) {
        $target_url = get_permalink( $page_id );
    }

    if ( empty( $target_url ) ) {
        return $redirect_page;
    }

    // Don't redirect admins
    if ( is_a( $user, 'WP_User' ) && in_array( 'administrator', (array) $user->roles, true ) ) {
        return $redirect_page;
    }

    return $target_url;
}

/**
 * 9) Tutor LMS – Override Tutor's own redirect URL filter (if available)
 */
add_filter( 'tutor_student_registration_redirect_url', 'jeffrey_tutor_reg_redirect_url', 9999 );
function jeffrey_tutor_reg_redirect_url( $url ) {
    $custom_url = trim( get_option( 'jeffrey_tutor_login_redirect_custom_url', '' ) );
    if ( ! empty( $custom_url ) ) {
        return $custom_url;
    }
    $page_id = (int) get_option( 'jeffrey_tutor_login_redirect_page_id', 0 );
    if ( $page_id > 0 ) {
        return get_permalink( $page_id );
    }
    return $url;
}

/**
 * 10) Sync BMB Video Completion Settings → Tutor LMS Options
 *      When BMB settings are saved, push values into Tutor's own option keys
 *      so Tutor's built-in strict mode & video control logic works natively.
 */
add_action( 'update_option_jeffrey_completion_mode', 'jeffrey_sync_tutor_completion_settings', 10, 2 );
add_action( 'update_option_jeffrey_video_completion_control', 'jeffrey_sync_tutor_completion_settings', 10, 2 );
add_action( 'update_option_jeffrey_video_required_percentage', 'jeffrey_sync_tutor_completion_settings', 10, 2 );
function jeffrey_sync_tutor_completion_settings( $old_value = '', $new_value = '' ) {
    // Get all BMB values (use latest from DB, they may have just been saved)
    $mode       = get_option( 'jeffrey_completion_mode', 'flexible' );
    $control    = get_option( 'jeffrey_video_completion_control', '0' );
    $percentage = (int) get_option( 'jeffrey_video_required_percentage', 80 );

    // Sync to Tutor's option keys (Tutor stores all in tutor_option)
    $tutor_option = (array) get_option( 'tutor_option', array() );

    $tutor_option['course_completion_process']                    = $mode;
    $tutor_option['control_video_lesson_completion']              = ( '1' === $control ) ? 'on' : 'off';
    $tutor_option['required_percentage_to_complete_video_lesson'] = $percentage;

    update_option( 'tutor_option', $tutor_option );
}

/**
 * 10c) On BMB Settings page load, pull Tutor values INTO BMB options
 *      so the BMB form shows the current Tutor state (first-time sync).
 */
add_action( 'admin_init', 'jeffrey_pull_tutor_completion_values_once' );
function jeffrey_pull_tutor_completion_values_once() {
    // Only run on our settings page
    if ( ! isset( $_GET['page'] ) || 'jeffrey-nextgen-setting' !== $_GET['page'] ) {
        return;
    }
    // Only auto-pull if BMB values have never been explicitly set
    if ( false !== get_option( 'jeffrey_completion_mode_synced' ) ) {
        return;
    }

    $tutor_option = (array) get_option( 'tutor_option', array() );

    if ( isset( $tutor_option['course_completion_process'] ) ) {
        update_option( 'jeffrey_completion_mode', $tutor_option['course_completion_process'] );
    }
    if ( isset( $tutor_option['control_video_lesson_completion'] ) ) {
        $val = ( 'on' === $tutor_option['control_video_lesson_completion'] ) ? '1' : '0';
        update_option( 'jeffrey_video_completion_control', $val );
    }
    if ( isset( $tutor_option['required_percentage_to_complete_video_lesson'] ) ) {
        update_option( 'jeffrey_video_required_percentage', (int) $tutor_option['required_percentage_to_complete_video_lesson'] );
    }

    // Mark as synced so we don't override user choices next time
    update_option( 'jeffrey_completion_mode_synced', '1' );
}

/**
 * 10d) Also hook into Tutor's validation filter as a fallback
 *      In case Tutor Pro's Filters class doesn't load (version mismatch),
 *      this ensures video completion control still works.
 */
add_filter( 'tutor_validate_lesson_complete', 'jeffrey_validate_video_lesson_complete', 10, 3 );
function jeffrey_validate_video_lesson_complete( $validated, $user_id, $lesson_id ) {
    // Only apply if our BMB settings have video control enabled
    $mode    = get_option( 'jeffrey_completion_mode', 'flexible' );
    $control = get_option( 'jeffrey_video_completion_control', '0' );

    if ( 'strict' !== $mode || '1' !== $control ) {
        return $validated;
    }

    if ( ! function_exists( 'tutor_utils' ) ) {
        return $validated;
    }

    $video_info = tutor_utils()->get_video_info( $lesson_id );
    $source_key = is_object( $video_info ) && 'html5' !== $video_info->source ? 'source_' . $video_info->source : null;
    $has_source = ( is_object( $video_info ) && $video_info->source_video_id ) || ( isset( $source_key ) ? $video_info->$source_key : null );

    if ( ! $has_source ) {
        return $validated;
    }

    $required_percentage = (int) get_option( 'jeffrey_video_required_percentage', 80 );
    $video_duration      = floatval( $video_info->duration_sec ?? 0 );
    $best_watch_time     = tutor_utils()->get_lesson_reading_info( $lesson_id, $user_id, 'video_best_watched_time' );

    if ( $video_duration > 0 && $best_watch_time ) {
        $watched_percentage = ( $best_watch_time / $video_duration ) * 100;
        if ( $watched_percentage < $required_percentage ) {
            return false;
        }
    } elseif ( $video_duration > 0 && ! $best_watch_time ) {
        // Video exists but user hasn't watched at all
        return false;
    }

    return $validated;
}

/**
 * 12) Hide Admin Bar for non-admin users
 */
add_action( 'after_setup_theme', 'jeffrey_hide_admin_bar_for_non_admins' );
function jeffrey_hide_admin_bar_for_non_admins() {
    if ( get_option( 'jeffrey_hide_adminbar_enabled', '1' ) !== '1' ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        add_filter( 'show_admin_bar', '__return_false' );
    }
}

/**
 * 13) Block wp-admin access for non-admin users
 */
add_action( 'admin_init', 'jeffrey_block_wp_admin_for_non_admins', 1 );
function jeffrey_block_wp_admin_for_non_admins() {
    if ( get_option( 'jeffrey_hide_adminbar_enabled', '1' ) !== '1' ) {
        return;
    }
    // Allow AJAX requests
    if ( wp_doing_ajax() ) {
        return;
    }
    // Block non-admins
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_safe_redirect( home_url() );
        exit;
    }
}

// --- Custom Shortcode for Droip Builder to display Course Enrollment/Subscription Box ---
add_shortcode( 'jeffrey_course_enroll_box', function() {
    if ( ! function_exists( 'tutor_load_template' ) ) {
        return '';
    }
    ob_start();
    tutor_load_template( 'single.course.course-entry-box' );
    return ob_get_clean();
});

// --- EMERGENCY FIX: Disable Heartbeat API to reduce server RAM usage and DB crashes ---
add_action( 'init', function() {
    wp_deregister_script('heartbeat');
}, 1 );

// --- Beautiful Layout Fix for Course Entry Box ---
add_action('wp_head', function() {
    ?>
    <style>
    /* Main Box Styling */
    .tutor-course-entry-box {
        background: #ffffff !important;
        border: 1px solid #eef0f4 !important;
        border-radius: 16px !important;
        padding: 30px !important;
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08) !important;
    }

    /* Price Section Fix */
    .tutor-course-entry-box .tutor-course-price,
    .tutor-course-entry-box .price {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        flex-wrap: wrap;
        gap: 8px !important;
        font-size: 24px !important;
        font-weight: 700 !important;
        color: #111827 !important;
        margin-bottom: 24px !important;
    }
    .tutor-course-entry-box .tutor-course-price .tutor-price-start-from {
        display: inline-block !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #6b7280 !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Plan Selection Area */
    .tutor-course-entry-box .tutor-course-plan-item,
    .tutor-course-entry-box .tutor-loop-cart-btn-wrap {
        background: #f9fafb !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 12px !important;
        padding: 16px !important;
        margin-bottom: 20px !important;
    }

    /* Primary Button (Buy Now / Subscribe) */
    .tutor-course-entry-box button[type="submit"],
    .tutor-course-entry-box .tutor-btn-primary {
        background: #2533F0 !important;
        color: #ffffff !important;
        border-radius: 10px !important;
        padding: 14px 24px !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        width: 100% !important;
        border: none !important;
        transition: all 0.3s ease !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        margin-bottom: 24px !important;
    }
    .tutor-course-entry-box button[type="submit"]:hover,
    .tutor-course-entry-box .tutor-btn-primary:hover {
        background: #1d28c2 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(37, 51, 240, 0.3) !important;
    }

    /* Text below button (Billed every month, Certificate etc) */
    .tutor-course-entry-box .woocommerce-product-details__short-description,
    .tutor-course-entry-box p {
        font-size: 15px !important;
        line-height: 1.6 !important;
        color: #4b5563 !important;
    }
    
    /* Auto-format lines that start with a checkmark in the short description */
    .tutor-course-entry-box .woocommerce-product-details__short-description {
        padding-top: 10px;
        border-top: 1px solid #f3f4f6;
    }

    /* Bottom Meta Info (Level, Enrolled, Updated) */
    .tutor-course-entry-box .tutor-course-sidebar-card-meta {
        margin-top: 24px !important;
        padding-top: 24px !important;
        border-top: 1px solid #f3f4f6 !important;
    }
    .tutor-course-entry-box .tutor-course-sidebar-card-meta li {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        font-size: 14px !important;
        color: #4b5563 !important;
        margin-bottom: 16px !important;
    }
    .tutor-course-entry-box .tutor-course-sidebar-card-meta li i,
    .tutor-course-entry-box .tutor-course-sidebar-card-meta li span[class^="tutor-icon"] {
        color: #2533F0 !important;
        font-size: 18px !important;
    }

    /* ========================================================
       Tutor LMS Login Form - Modern & Premium Styling
       ======================================================== */
    .tutor-login-wrap,
    .tutor-card.tutor-login-wrap,
    .tutor-template-segment.tutor-login-wrap,
    .tutor-modal.tutor-login-modal .tutor-modal-content {
        max-width: 440px !important;
        width: 100% !important;
        margin: 0px !important;
        background: #ffffff !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.08), 0 4px 12px -2px rgba(0, 0, 0, 0.03) !important;
        border: 1px solid #e5e7eb !important;
        overflow: hidden !important;
    }

    .tutor-login-form-wrapper {
        padding: 0px !important;
        background: #ffffff !important;
    }
    .tutor-login-wrap, #tutor-registration-wrap {
        padding: 30px 20px !important;
    }

    .tutor-login-form-wrapper,
    .tutor-login-form-wrapper *,
    #tutor-login-form,
    #tutor-login-form * {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
        box-sizing: border-box !important;
    }

    .tutor-login-form-wrapper .tutor-h3,
    .tutor-login-form-wrapper .tutor-fs-4,
    .tutor-login-form-wrapper .tutor-fs-5,
    .tutor-login-form-wrapper div[class*="tutor-h3"],
    .tutor-login-form-wrapper div[class*="tutor-fs-"],
    .tutor-login-form-wrapper h3 {
        font-size: 24px !important;
        font-weight: 700 !important;
        color: #111827 !important;
        margin: 0 0 24px 0 !important;
        padding: 0 !important;
        line-height: 1.3 !important;
        letter-spacing: -0.02em !important;
    }

    #tutor-login-form {
        margin: 0 !important;
        padding: 0 !important;
        min-width:360px;
    }

    #tutor-login-form .tutor-input-field {
        margin-bottom: 18px !important;
        position: relative !important;
        display: block !important;
    }

    #tutor-login-form input[type="text"],
    #tutor-login-form input[type="password"],
    #tutor-login-form .tutor-form-control,
    #tutor-login-form .tutor-input {
        width: 100% !important;
        height: 48px !important;
        padding: 12px 16px !important;
        font-size: 15px !important;
        font-weight: 400 !important;
        color: #1f2937 !important;
        background-color: #f9fafb !important;
        border: 1px solid #d1d5db !important;
        border-radius: 10px !important;
        outline: none !important;
        box-shadow: none !important;
        transition: all 0.2s ease-in-out !important;
        margin: 0 !important;
    }

    #tutor-login-form input[type="text"]::placeholder,
    #tutor-login-form input[type="password"]::placeholder {
        color: #9ca3af !important;
        font-weight: 400 !important;
    }

    #tutor-login-form input[type="text"]:focus,
    #tutor-login-form input[type="password"]:focus,
    #tutor-login-form .tutor-form-control:focus,
    #tutor-login-form .tutor-input:focus {
        background-color: #ffffff !important;
        border-color: #1b8366 !important;
        box-shadow: 0 0 0 4px rgba(27, 131, 102, 0.15) !important;
    }

    #tutor-login-form input[name="pwd"],
    #tutor-login-form input[type="password"] {
        padding-right: 48px !important;
    }

    #tutor-login-form .tutor-input-field span[x-show] {
        position: absolute !important;
        right: 14px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        color: #6b7280 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        z-index: 2 !important;
        transition: color 0.2s ease !important;
    }

    #tutor-login-form .tutor-input-field span[x-show]:hover {
        color: #111827 !important;
    }

    #tutor-login-form .tutor-input-field span[x-show] svg {
        width: 20px !important;
        height: 20px !important;
    }

    #tutor-login-form .tutor-flex.tutor-justify-between,
    #tutor-login-form div[class*="tutor-justify-between"] {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        margin-top: 16px !important;
        margin-bottom: 24px !important;
        gap: 12px !important;
    }

    #tutor-login-form .tutor-input-wrapper {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
    }

    #tutor-login-form input[type="checkbox"].tutor-checkbox {
        width: 18px !important;
        height: 18px !important;
        margin: 0 !important;
        accent-color: #1b8366 !important;
        cursor: pointer !important;
        border-radius: 4px !important;
    }

    #tutor-login-form label.tutor-label {
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #374151 !important;
        cursor: pointer !important;
        user-select: none !important;
        line-height: 1 !important;
        margin: 0 !important;
    }

    #tutor-login-form a.tutor-btn-link-gray,
    #tutor-login-form a[href*="retrieve-password"],
    #tutor-login-form a[href*="lost-password"] {
        font-size: 14px !important;
        font-weight: 600 !important;
        color: #1b8366 !important;
        text-decoration: none !important;
        transition: color 0.2s ease !important;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
        border: none !important;
    }

    #tutor-login-form a.tutor-btn-link-gray:hover,
    #tutor-login-form a[href*="retrieve-password"]:hover,
    #tutor-login-form a[href*="lost-password"]:hover {
        color: #14614b !important;
        text-decoration: underline !important;
    }

    #tutor-login-form button[type="submit"],
    #tutor-login-form .tutor-btn-primary {
        width: 100% !important;
        height: 48px !important;
        background-color: #1b8366 !important;
        color: #ffffff !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        border: none !important;
        border-radius: 10px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 12px rgba(27, 131, 102, 0.25) !important;
        transition: all 0.2s ease-in-out !important;
        margin-top: 8px !important;
        margin-bottom: 20px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
    }

    #tutor-login-form button[type="submit"]:hover,
    #tutor-login-form .tutor-btn-primary:hover {
        background-color: #14614b !important;
        box-shadow: 0 6px 16px rgba(27, 131, 102, 0.35) !important;
        transform: translateY(-1px) !important;
        color: #ffffff !important;
    }

    #tutor-login-form button[type="submit"]:active,
    #tutor-login-form .tutor-btn-primary:active {
        transform: translateY(0) !important;
    }

    #tutor-login-form .tutor-flex.tutor-items-center.tutor-justify-center.tutor-mt-7,
    #tutor-login-form div[class*="tutor-mt-7"] {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        margin-top: 20px !important;
        padding-top: 20px !important;
        border-top: 1px solid #f3f4f6 !important;
    }

    #tutor-login-form .tutor-small {
        font-size: 14px !important;
        color: #6b7280 !important;
        font-weight: 400 !important;
    }

    #tutor-login-form a.tutor-btn-link {
        font-size: 14px !important;
        font-weight: 600 !important;
        color: #1b8366 !important;
        text-decoration: none !important;
        padding: 0 !important;
        background: transparent !important;
    }

    #tutor-login-form a.tutor-btn-link:hover {
        color: #14614b !important;
        text-decoration: underline !important;
    }

    @media (max-width: 480px) {
        .tutor-login-wrap,
        .tutor-card.tutor-login-wrap {
            margin: 20px 12px !important;
            border-radius: 12px !important;
        }
        .tutor-login-form-wrapper {
            padding: 24px 20px !important;
        }
    }
    </style>
    <?php
});

/* ================================================================
   INTERCEPT CHECKOUT REDIRECT – Force subscription plan URL
   ================================================================ */
add_filter( 'wp_redirect', 'jeffrey_intercept_course_id_checkout', 1 );
function jeffrey_intercept_course_id_checkout( $location ) {
    $parsed = wp_parse_url( $location );
    $path   = isset( $parsed['path'] ) ? trailingslashit( $parsed['path'] ) : '';
    $query  = isset( $parsed['query'] ) ? $parsed['query'] : '';

    if ( false === strpos( $path, 'checkout' ) ) {
        return $location;
    }

    if ( false === strpos( $query, 'course_id=' ) ) {
        return $location;
    }

    return home_url( '/checkout/?plan=1' );
}

add_action( 'template_redirect', 'jeffrey_catch_direct_checkout_visits', 1 );
function jeffrey_catch_direct_checkout_visits() {
    if ( is_page( 'checkout' ) && isset( $_GET['course_id'] ) ) {
        wp_redirect( home_url( '/checkout/?plan=1' ) );
        exit;
    }
}

/* ================================================================
   PREVIEW COURSE BUTTON INJECTOR & BUTTON HIDER FOR PREVIEW USERS
   ================================================================ */
add_action( 'wp_footer', 'jeffrey_inject_preview_course_button' );
function jeffrey_inject_preview_course_button() {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'kirki' ) {
        return;
    }
    if ( ! function_exists( 'tutor' ) || ! is_singular( tutor()->course_post_type ) ) {
        return;
    }

    $course_id = get_the_ID();
    $user_id   = get_current_user_id();

    if ( $user_id ) {
        global $wpdb;
        $sub_table = $wpdb->prefix . 'tutor_subscriptions';
        $has_sub = false;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$sub_table}'" ) === $sub_table ) {
            $has_sub = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$sub_table} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $user_id
            ) );
        }

        if ( ! $has_sub && function_exists( 'wcs_user_has_subscription' ) ) {
            $has_sub = wcs_user_has_subscription( $user_id, '', 'active' );
        }

        if ( $has_sub ) {
            return;
        }
    }

    global $wpdb;
    $first_preview_lesson = $wpdb->get_var( $wpdb->prepare(
        "SELECT lesson.ID
         FROM {$wpdb->posts} AS lesson
         INNER JOIN {$wpdb->postmeta} AS pm ON lesson.ID = pm.post_id
         INNER JOIN {$wpdb->posts} AS topic ON lesson.post_parent = topic.ID
         WHERE topic.post_parent = %d
           AND pm.meta_key = '_is_preview'
           AND pm.meta_value = '1'
           AND lesson.post_status = 'publish'
           AND lesson.post_type = 'lesson'
         ORDER BY topic.menu_order ASC, lesson.menu_order ASC
         LIMIT 1",
        $course_id
    ) );

    if ( ! $first_preview_lesson ) {
        return;
    }

    $preview_url = get_permalink( $first_preview_lesson );
    ?>
    <script>
    (function() {
        'use strict';

        function addPreviewButton() {
            var allElements = document.querySelectorAll('a, button, input[type="submit"]');
            for (var i = 0; i < allElements.length; i++) {
                var el = allElements[i];
                var text = (el.innerText || el.value || '').trim().toLowerCase();
                if (text === 'start learning' || text === 'continue learning' || text === 'complete course') {
                    el.style.display = 'none';
                    if (el.parentElement && el.parentElement.tagName.toLowerCase() === 'form') {
                        el.parentElement.style.display = 'none';
                    }
                }
            }

            var containers = document.querySelectorAll('[data-droip="dp6lljrn"], .post-930-dpda3lpq, [class*="dpda3lpq"]');

            if (!containers.length) {
                var subBtns = document.querySelectorAll('[data-action_type="subscribe_now_btn"]');
                if (subBtns.length) {
                    containers = [];
                    subBtns.forEach(function(btn) {
                        if (btn.parentElement && !containers.includes(btn.parentElement)) {
                            containers.push(btn.parentElement);
                        }
                    });
                }
            }

            if (!containers.length) return;

            containers.forEach(function(container) {
                if (container.querySelector('.jeffrey-preview-btn')) return;

                var btn = document.createElement('a');
                btn.href = <?php echo wp_json_encode( $preview_url ); ?>;
                btn.className = 'jeffrey-preview-btn';
                btn.innerHTML = '▶ Preview Course';
                btn.style.cssText = [
                    'display:flex',
                    'align-items:center',
                    'justify-content:center',
                    'column-gap:6px',
                    'font-family:inherit',
                    'font-size:16px',
                    'font-weight:600',
                    'line-height:1.2em',
                    'color:#183fe1',
                    'background:transparent',
                    'border:2px solid #183fe1',
                    'border-radius:8px',
                    'min-height:50px',
                    'padding:7px 26px',
                    'text-decoration:none',
                    'cursor:pointer',
                    'transition:all 300ms ease',
                    'width:100%',
                    'box-sizing:border-box',
                    'margin-bottom:0',
                    'order:-1'
                ].join(';');

                btn.addEventListener('mouseenter', function() {
                    this.style.background = '#183fe1';
                    this.style.color = '#ffffff';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.background = 'transparent';
                    this.style.color = '#183fe1';
                });

                container.insertBefore(btn, container.firstChild);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addPreviewButton);
        } else {
            addPreviewButton();
        }
        setTimeout(addPreviewButton, 800);
        setTimeout(addPreviewButton, 1800);
    })();
    </script>
    <?php
}

/* ================================================================
   AUTO-ENROLL ACTIVE SUBSCRIBERS & UPGRADE PREVIEW ENROLLMENTS
   ================================================================ */
add_action( 'template_redirect', 'jeffrey_auto_enroll_active_subscribers' );
function jeffrey_auto_enroll_active_subscribers() {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'kirki' ) {
        return;
    }
    if ( ! function_exists( 'tutor' ) || ( ! is_singular( tutor()->course_post_type ) && ! is_singular( tutor()->lesson_post_type ) ) ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    global $wpdb;
    $sub_table = $wpdb->prefix . 'tutor_subscriptions';
    $has_sub = (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$sub_table} WHERE user_id = %d AND status = 'active' LIMIT 1",
        $user_id
    ) );

    if ( ! $has_sub && function_exists( 'wcs_user_has_subscription' ) ) {
        $has_sub = wcs_user_has_subscription( $user_id, '', 'active' );
    }

    if ( ! $has_sub ) {
        return;
    }

    $course_id = get_the_ID();
    $is_lesson = is_singular( tutor()->lesson_post_type );
    if ( $is_lesson ) {
        $course_id = (int) get_post_meta( get_the_ID(), '_tutor_course_id_for_lesson', true );
        if ( ! $course_id ) {
            $course_id = 498; // Fallback to Bible Mastery Boot Camp ID
        }
    }

    $real_enrollment = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} AS p
         LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_tlp_preview_enrollment'
         WHERE p.post_type   = 'tutor_enrolled'
           AND p.post_parent = %d
           AND p.post_author = %d
           AND p.post_status = 'completed'
           AND (pm.meta_value IS NULL OR pm.meta_value != '1')
         LIMIT 1",
        $course_id,
        $user_id
    ) );

    if ( $real_enrollment ) {
        return;
    }

    $preview_enrollment_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} AS p
         INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
         WHERE p.post_type   = 'tutor_enrolled'
           AND p.post_parent = %d
           AND p.post_author = %d
           AND pm.meta_key   = '_tlp_preview_enrollment'
           AND pm.meta_value = '1'
         LIMIT 1",
        $course_id,
        $user_id
    ) );

    if ( $preview_enrollment_id ) {
        wp_update_post( array(
            'ID'          => $preview_enrollment_id,
            'post_status' => 'completed',
        ) );
        delete_post_meta( $preview_enrollment_id, '_tlp_preview_enrollment' );
        do_action( 'tutor_after_enroll', $course_id, $preview_enrollment_id );
        do_action( 'tutor_after_enrolled', $course_id, $user_id, $preview_enrollment_id );
        wp_safe_redirect( get_permalink( $course_id ) );
        exit;
    } else {
        $enrollment_id = tutor_utils()->do_enroll( $course_id, 0, $user_id );
        if ( $enrollment_id ) {
            wp_update_post( array(
                'ID'          => $enrollment_id,
                'post_status' => 'completed',
            ) );
            wp_safe_redirect( get_permalink( $course_id ) );
            exit;
        }
    }
}

/* ================================================================
   DEBUG ENDPOINT: /user-info-detailed or ?user-info-detailed=1
   ================================================================ */
add_action( 'init', 'jeffrey_user_info_detailed_endpoint' );
function jeffrey_user_info_detailed_endpoint() {
    // One-time cleanup: remove _is_preview meta from all quizzes so they are locked by default
    if ( isset( $_GET['cleanup_quizzes'] ) ) {
        global $wpdb;
        $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_type = 'tutor_quiz' AND pm.meta_key = '_is_preview'" );
        wp_die( 'All quizzes have been locked successfully! <a href="' . esc_url( remove_query_arg( 'cleanup_quizzes' ) ) . '">Return to report</a>' );
    }

    if ( isset( $_GET['user-info-detailed'] ) || strpos( $_SERVER['REQUEST_URI'], '/user-info-detailed' ) !== false ) {
        if ( ! is_user_logged_in() ) {
            wp_die( 'Please log in to view /user-info-detailed' );
        }

        $user_id = get_current_user_id();
        $user    = wp_get_current_user();
        global $wpdb;

        header( 'Content-Type: text/html; charset=utf-8' );
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>User Detailed Debug Info</title>
            <style>
                body { font-family: monospace; background: #0f172a; color: #f8fafc; padding: 30px; line-height: 1.5; }
                h1, h2, h3 { color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 8px; }
                .box { background: #1e293b; padding: 20px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #334155; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #334155; padding: 10px; text-align: left; }
                th { background: #0f172a; color: #a5f3fc; }
                .badge { padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
                .badge-yes { background: #166534; color: #bbf7d0; }
                .badge-no { background: #991b1b; color: #fecaca; }
                pre { background: #0f172a; padding: 12px; border-radius: 6px; overflow-x: auto; color: #cbd5e1; white-space: pre-wrap; }
            </style>
        </head>
        <body>
            <h1>🔍 User Detailed Diagnostic Report</h1>

            <div class="box">
                <h2>1. Current Logged-In User</h2>
                <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
                <p><strong>User Login:</strong> <?php echo esc_html($user->user_login); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($user->user_email); ?></p>
                <p><strong>Roles:</strong> <?php echo esc_html(implode(', ', $user->roles)); ?></p>
                <p><strong>Is Administrator (manage_options):</strong> <?php echo user_can($user_id, 'manage_options') ? '<span class="badge badge-yes">YES</span>' : '<span class="badge badge-no">NO</span>'; ?></p>
            </div>

            <div class="box">
                <h2>2. Tutor LMS Enrollment Posts (wp_posts post_type = 'tutor_enrolled')</h2>
                <?php
                $enrollments = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled' AND post_author = %d ORDER BY ID DESC",
                    $user_id
                ) );
                if ($enrollments) {
                    echo '<table><tr><th>Enrollment ID</th><th>Course ID</th><th>Course Title</th><th>Status</th><th>Date</th><th>Post Meta</th></tr>';
                    foreach ($enrollments as $e) {
                        $meta = get_post_meta($e->ID);
                        $course_title = get_the_title($e->post_parent);
                        echo '<tr>';
                        echo '<td>' . $e->ID . '</td>';
                        echo '<td>' . $e->post_parent . '</td>';
                        echo '<td>' . esc_html($course_title) . '</td>';
                        echo '<td>' . $e->post_status . '</td>';
                        echo '<td>' . $e->post_date . '</td>';
                        echo '<td><pre>' . esc_html(print_r($meta, true)) . '</pre></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p>No tutor_enrolled posts found for this user.</p>';
                }
                ?>
            </div>

            <div class="box">
                <h2>3. Tutor LMS Subscriptions (wp_tutor_subscriptions)</h2>
                <?php
                $sub_table = $wpdb->prefix . 'tutor_subscriptions';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$sub_table}'") === $sub_table) {
                    $subs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$sub_table} WHERE user_id = %d", $user_id));
                    if ($subs) {
                        echo '<pre>' . esc_html(print_r($subs, true)) . '</pre>';
                    } else {
                        echo '<p>No records in wp_tutor_subscriptions table for this user.</p>';
                    }
                } else {
                    echo '<p>Table wp_tutor_subscriptions does not exist on this site.</p>';
                }
                ?>
            </div>

            <div class="box">
                <h2>4. Course Curriculum & Access Filters Check</h2>
                <?php
                $courses = get_posts(array('post_type' => 'courses', 'posts_per_page' => 10));
                foreach ($courses as $c) {
                    echo '<h3>Course: ' . esc_html($c->post_title) . ' (ID: ' . $c->ID . ')</h3>';
                    $is_enrolled_tutor = function_exists('tutor_utils') ? tutor_utils()->is_enrolled($c->ID) : 'N/A';
                    $has_real_env = class_exists('TLP_Access_Control') ? TLP_Access_Control::has_real_enrollment($c->ID, $user_id) : 'N/A';
                    echo '<p><strong>tutor_utils()->is_enrolled():</strong> ' . print_r($is_enrolled_tutor, true) . '</p>';
                    echo '<p><strong>TLP_Access_Control::has_real_enrollment():</strong> ' . ($has_real_env ? 'TRUE' : 'FALSE') . '</p>';

                    $contents = $wpdb->get_results($wpdb->prepare(
                        "SELECT p.ID, p.post_title, p.post_type, p.menu_order 
                         FROM {$wpdb->posts} p 
                         INNER JOIN {$wpdb->posts} topic ON p.post_parent = topic.ID 
                         WHERE topic.post_parent = %d AND p.post_status = 'publish' 
                         ORDER BY topic.menu_order ASC, p.menu_order ASC",
                        $c->ID
                    ));

                    if ($contents) {
                        echo '<table><tr><th>Item ID</th><th>Type</th><th>Title</th><th>_is_preview Meta</th><th>show_permalink Filter</th></tr>';
                        foreach ($contents as $item) {
                            $is_prev = get_post_meta($item->ID, '_is_preview', true);
                            $show_perm = apply_filters('tutor_course/single/content/show_permalink', true, $item->ID);
                            echo '<tr>';
                            echo '<td>' . $item->ID . '</td>';
                            echo '<td>' . $item->post_type . '</td>';
                            echo '<td>' . esc_html($item->post_title) . '</td>';
                            echo '<td>' . ($is_prev ? '<span class="badge badge-yes">1 (PREVIEW)</span>' : '<span class="badge badge-no">EMPTY (LOCKED)</span>') . '</td>';
                            echo '<td>' . ($show_perm ? '<span class="badge badge-yes">TRUE (UNLOCKED)</span>' : '<span class="badge badge-no">FALSE (LOCKED 🔒)</span>') . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                }
                ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * 14) Client Feedback Changes (Items #9 and #10):
 * - Change "Exercise Files" label to "Class Notebook"
 * - Remove "Notes" / "Take Note" from single lesson player
 * - Remove "... More" section from player sidebar (Announcements, Reviews, Info, etc.)
 */

// A) Change "Exercise Files" to "Class Notebook" and remove "Notes" tab
add_filter( 'tutor_lesson_single_nav_items', function( $nav_items ) {
    if ( ! is_array( $nav_items ) ) {
        return $nav_items;
    }
    foreach ( $nav_items as $key => $item ) {
        // Change label to 'Class Notebook'
        if ( ( isset( $item['value'] ) && 'files' === $item['value'] ) || ( isset( $item['id'] ) && 'exercise_files' === $item['id'] ) ) {
            $nav_items[$key]['label'] = __( 'Class Notebook', 'tutor' );
        }
        // Remove 'Notes' tab
        if ( ( isset( $item['value'] ) && 'notes' === $item['value'] ) || ( isset( $item['id'] ) && 'notes' === $item['id'] ) ) {
            unset( $nav_items[$key] );
        }
    }
    return $nav_items;
}, 999 );

// B) Global gettext translation fallback for "Exercise Files"
add_filter( 'gettext', function( $translated_text, $text, $domain ) {
    if ( 'Exercise Files' === $text || 'Lesson- Exercise Files' === $text ) {
        return 'Class Notebook';
    }
    return $translated_text;
}, 20, 3 );

// C) Remove "Take Note" button hook from lesson view
add_action( 'wp', function() {
    if ( function_exists( 'tutor_pro' ) && isset( tutor_pro()->lesson_notes ) ) {
        remove_action( 'tutor_lesson_single_after_nav_items', array( tutor_pro()->lesson_notes, 'load_lesson_notes_nav_button' ), 10 );
    }
} );

// D) Remove "... More" popup menu items from learning area sidebar
add_filter( 'tutor_learning_area_sub_page_menu_items', function( $menu_items ) {
    return array();
}, 999 );

/**
 * 15) Fix Kirki Reset Password Link in Outgoing Emails
 * When Kirki sends password reset emails with a relative ?action=rp link,
 * prepend the site's Change/Reset Password page URL.
 */
add_filter( 'wp_mail', function( $args ) {
    if ( isset( $args['message'] ) && strpos( $args['message'], '?action=rp&key=' ) !== false ) {
        $reset_page = get_page_by_path( 'change-password' );
        if ( ! $reset_page ) {
            $reset_page = get_page_by_path( 'reset-password' );
        }
        $base_url = $reset_page ? get_permalink( $reset_page ) : home_url( '/change-password/' );

        $args['message'] = preg_replace(
            '/(href=["\']|>|\s|^)(\?action=rp&[^\s"\'<]+)/i',
            '$1' . esc_url( $base_url ) . '$2',
            $args['message']
        );
    }
    return $args;
}, 99 );

/* ================================================================
   16) POST-PAYMENT / SUBSCRIPTION REDIRECTION HANDLERS
   Directs students to Module One, Class 3 (or Course Page) instead of dummy archive
   ================================================================ */

/**
 * Helper: Retrieve default redirect URL (Module One, Class 3)
 */
function jeffrey_get_default_post_payment_url() {
    // 1) Lesson 521: Class 3: God's National Covenant with Israel
    $class_3 = get_post( 521 );
    if ( $class_3 && 'publish' === $class_3->post_status ) {
        return get_permalink( 521 );
    }

    // 2) Look up lesson by slug
    $lesson_obj = get_page_by_path( 'class-3-gods-national-covenant-with-israel', OBJECT, 'lesson' );
    if ( $lesson_obj ) {
        return get_permalink( $lesson_obj->ID );
    }

    // 3) Fallback to course page 498
    $course = get_post( 498 );
    if ( $course ) {
        return get_permalink( 498 );
    }

    return home_url( '/courses/bible-mastery-bootcamp/' );
}

/**
 * Helper: Resolve target URL based on admin settings (Custom URL > Page Dropdown > Default)
 */
function jeffrey_get_post_payment_redirect_url() {
    $custom_url = trim( get_option( 'jeffrey_payment_redirect_custom_url', '' ) );
    if ( ! empty( $custom_url ) ) {
        return $custom_url;
    }

    $page_id = (int) get_option( 'jeffrey_payment_redirect_page_id', 0 );
    if ( $page_id > 0 ) {
        $page_link = get_permalink( $page_id );
        if ( ! empty( $page_link ) ) {
            return $page_link;
        }
    }

    return jeffrey_get_default_post_payment_url();
}

/**
 * Helper: Ensure student enrollment is marked completed & preview lock removed
 */
function jeffrey_ensure_user_course_enrolled( $user_id, $course_id = 498 ) {
    if ( ! $user_id || ! $course_id ) {
        return;
    }

    global $wpdb;

    // Check if already fully enrolled
    $real_enrollment = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} AS p
         LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_tlp_preview_enrollment'
         WHERE p.post_type   = 'tutor_enrolled'
           AND p.post_parent = %d
           AND p.post_author = %d
           AND p.post_status = 'completed'
           AND (pm.meta_value IS NULL OR pm.meta_value != '1')
         LIMIT 1",
        $course_id,
        $user_id
    ) );

    if ( $real_enrollment ) {
        return;
    }

    // Upgrade preview enrollment to completed
    $preview_enrollment_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} AS p
         INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
         WHERE p.post_type   = 'tutor_enrolled'
           AND p.post_parent = %d
           AND p.post_author = %d
           AND pm.meta_key   = '_tlp_preview_enrollment'
           AND pm.meta_value = '1'
         LIMIT 1",
        $course_id,
        $user_id
    ) );

    if ( $preview_enrollment_id ) {
        wp_update_post( array(
            'ID'          => $preview_enrollment_id,
            'post_status' => 'completed',
        ) );
        delete_post_meta( $preview_enrollment_id, '_tlp_preview_enrollment' );
        do_action( 'tutor_after_enroll', $course_id, $preview_enrollment_id );
        do_action( 'tutor_after_enrolled', $course_id, $user_id, $preview_enrollment_id );
    } elseif ( function_exists( 'tutor_utils' ) ) {
        $enrollment_id = tutor_utils()->do_enroll( $course_id, 0, $user_id );
        if ( $enrollment_id ) {
            wp_update_post( array(
                'ID'          => $enrollment_id,
                'post_status' => 'completed',
            ) );
            do_action( 'tutor_after_enroll', $course_id, $enrollment_id );
            do_action( 'tutor_after_enrolled', $course_id, $user_id, $enrollment_id );
        }
    }
}

/**
 * 16a) Catch Stripe / Tutor LMS post-payment redirect (?tutor_order_placement=success)
 */
add_action( 'template_redirect', 'jeffrey_catch_payment_placement_success', 1 );
function jeffrey_catch_payment_placement_success() {
    if ( isset( $_GET['tutor_order_placement'] ) && 'success' === $_GET['tutor_order_placement'] ) {
        $user_id = get_current_user_id();
        if ( $user_id ) {
            jeffrey_ensure_user_course_enrolled( $user_id, 498 );
        }
        $target = jeffrey_get_post_payment_redirect_url();
        wp_safe_redirect( $target );
        exit;
    }
}

/**
 * 16b) Tutor LMS Redirect URL filter after checkout
 */
add_filter( 'tutor_redirect_url_after_checkout', function( $redirect_url, $status, $order_id ) {
    if ( 'success' === $status ) {
        $user_id = get_current_user_id();
        if ( $user_id ) {
            jeffrey_ensure_user_course_enrolled( $user_id, 498 );
        }
        return jeffrey_get_post_payment_redirect_url();
    }
    return $redirect_url;
}, 9999, 3 );

/**
 * 16c) WooCommerce Checkout Return URL filter (fallback)
 */
add_filter( 'woocommerce_get_return_url', function( $return_url, $order ) {
    $user_id = get_current_user_id();
    if ( $user_id ) {
        jeffrey_ensure_user_course_enrolled( $user_id, 498 );
    }
    return jeffrey_get_post_payment_redirect_url();
}, 9999, 2 );

/**
 * 16d) WooCommerce thankyou action redirect (fallback)
 */
add_action( 'woocommerce_thankyou', function( $order_id ) {
    $user_id = get_current_user_id();
    if ( $user_id ) {
        jeffrey_ensure_user_course_enrolled( $user_id, 498 );
    }
    wp_safe_redirect( jeffrey_get_post_payment_redirect_url() );
    exit;
}, 1 );

/* ================================================================
   17) COURSE BUTTON TOGGLE (FREE PREVIEW vs RESUME COURSE)
   Switches #bmb-free-btn and #bmb-resume-btn based on subscription/enrollment
   ================================================================ */

/**
 * Helper to check if user has active subscription or completed enrollment
 */
function jeffrey_is_user_paid_subscriber( $user_id = 0, $course_id = 498 ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    if ( ! $user_id ) {
        return false;
    }

    global $wpdb;

    // 1) Tutor subscriptions table check
    $sub_table = $wpdb->prefix . 'tutor_subscriptions';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$sub_table}'" ) === $sub_table ) {
        $has_sub = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$sub_table} WHERE user_id = %d AND status = 'active' LIMIT 1",
            $user_id
        ) );
        if ( $has_sub ) {
            return true;
        }
    }

    // 2) WooCommerce subscriptions check
    if ( function_exists( 'wcs_user_has_subscription' ) && wcs_user_has_subscription( $user_id, '', 'active' ) ) {
        return true;
    }

    // 3) Tutor enrolled posts check (completed and not preview)
    $real_enrollment = (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} AS p
         LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_tlp_preview_enrollment'
         WHERE p.post_type   = 'tutor_enrolled'
           AND p.post_parent = %d
           AND p.post_author = %d
           AND p.post_status = 'completed'
           AND (pm.meta_value IS NULL OR pm.meta_value != '1')
         LIMIT 1",
        $course_id,
        $user_id
    ) );

    return $real_enrollment;
}

/**
 * Instant CSS to prevent button flicker on page load
 */
add_action( 'wp_head', 'jeffrey_course_buttons_visibility_css', 999 );
function jeffrey_course_buttons_visibility_css() {
    if ( isset( $_GET['action'] ) && 'kirki' === $_GET['action'] ) {
        return; // Never hide in Kirki visual editor
    }
    if ( ! function_exists( 'tutor' ) || ! is_singular( tutor()->course_post_type ) ) {
        return;
    }

    $course_id = get_the_ID();
    $user_id   = get_current_user_id();
    $is_paid   = jeffrey_is_user_paid_subscriber( $user_id, $course_id );

    echo '<style id="bmb-button-toggle-css">';
    if ( $is_paid ) {
        // Paid user: Hide Free Classes button, Show Resume Course button
        echo '#bmb-free-btn, [data-bmb="free-btn"] { display: none !important; } ';
        echo '#bmb-resume-btn, [data-bmb="resume-btn"] { display: flex !important; }';
    } else {
        // Non-paid / Guest: Show Free Classes button, Hide Resume Course button
        echo '#bmb-free-btn, [data-bmb="free-btn"] { display: flex !important; } ';
        echo '#bmb-resume-btn, [data-bmb="resume-btn"] { display: none !important; }';
    }
    echo '</style>';
}

/**
 * JS handler to dynamically assign correct target URLs to buttons
 */
add_action( 'wp_footer', 'jeffrey_course_buttons_js_handler', 999 );
function jeffrey_course_buttons_js_handler() {
    if ( isset( $_GET['action'] ) && 'kirki' === $_GET['action'] ) {
        return;
    }
    if ( ! function_exists( 'tutor' ) || ! is_singular( tutor()->course_post_type ) ) {
        return;
    }

    $course_id = get_the_ID();
    $user_id   = get_current_user_id();
    $is_paid   = jeffrey_is_user_paid_subscriber( $user_id, $course_id );

    // Resume URL: Next lesson or Module One, Class 3
    $resume_url = '';
    if ( function_exists( 'tutor_utils' ) ) {
        $resume_url = tutor_utils()->get_resume_url( $course_id );
    }
    if ( empty( $resume_url ) || false === strpos( $resume_url, 'lesson' ) ) {
        $class_3 = get_post( 521 );
        $resume_url = ( $class_3 && 'publish' === $class_3->post_status ) ? get_permalink( 521 ) : get_permalink( $course_id );
    }

    // Free button URL: Class 1 (Lesson 501)
    $free_url = get_permalink( 501 );
    if ( empty( $free_url ) ) {
        $free_url = home_url( '/student-registration-page/' );
    }
    ?>
    <script id="bmb-button-toggle-js">
    (function() {
        'use strict';
        var isPaid    = <?php echo json_encode( (bool) $is_paid ); ?>;
        var resumeUrl = <?php echo json_encode( $resume_url ); ?>;
        var freeUrl   = <?php echo json_encode( $free_url ); ?>;

        function setupButtons() {
            var resumeBtn = document.getElementById('bmb-resume-btn');
            var freeBtn   = document.getElementById('bmb-free-btn');

            // Fallback scan by inner text if ID not matched
            if (!resumeBtn || !freeBtn) {
                var allEls = document.querySelectorAll('button, a, [role="button"], div');
                for (var i = 0; i < allEls.length; i++) {
                    var el = allEls[i];
                    var txt = (el.innerText || '').trim().toLowerCase();
                    if (!resumeBtn && (txt === 'resume course' || txt.indexOf('resume course') !== -1) && el.children.length <= 2) {
                        resumeBtn = el;
                    }
                    if (!freeBtn && (txt.indexOf('start my 2 free classes') !== -1 || txt.indexOf('2 free classes') !== -1) && el.children.length <= 2) {
                        freeBtn = el;
                    }
                }
            }

            if (isPaid) {
                // Paid user: Hide Free button, show Resume button
                if (freeBtn) freeBtn.style.setProperty('display', 'none', 'important');
                if (resumeBtn) {
                    resumeBtn.style.setProperty('display', 'flex', 'important');
                    resumeBtn.style.cursor = 'pointer';

                    // Update link URL
                    var link = resumeBtn.tagName.toLowerCase() === 'a' ? resumeBtn : resumeBtn.querySelector('a');
                    if (link) {
                        link.href = resumeUrl;
                    } else if (resumeBtn.parentElement && resumeBtn.parentElement.tagName.toLowerCase() === 'a') {
                        resumeBtn.parentElement.href = resumeUrl;
                    }

                    // Direct click redirection
                    resumeBtn.onclick = function(e) {
                        e.preventDefault();
                        window.location.href = resumeUrl;
                    };
                }
            } else {
                // Guest / Not paid: Show Free button, hide Resume button
                if (resumeBtn) resumeBtn.style.setProperty('display', 'none', 'important');
                if (freeBtn) {
                    freeBtn.style.setProperty('display', 'flex', 'important');
                    freeBtn.style.cursor = 'pointer';

                    var fLink = freeBtn.tagName.toLowerCase() === 'a' ? freeBtn : freeBtn.querySelector('a');
                    if (fLink && (!fLink.href || fLink.href.indexOf('#') !== -1)) {
                        fLink.href = freeUrl;
                    }
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupButtons);
        } else {
            setupButtons();
        }
        setTimeout(setupButtons, 300);
        setTimeout(setupButtons, 1000);
    })();
    </script>
    <?php
}

/* ================================================================
   18) REDIRECT GENERIC COURSES ARCHIVE (/courses/) TO SINGLE COURSE
   Prevents visitors/students from ever seeing the demo archive template
   ================================================================ */
add_action( 'template_redirect', 'jeffrey_redirect_courses_archive_to_single', 5 );
function jeffrey_redirect_courses_archive_to_single() {
    // Skip if inside Kirki editor
    if ( isset( $_GET['action'] ) && 'kirki' === $_GET['action'] ) {
        return;
    }
    // Skip if doing AJAX, REST, or Admin
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) {
        return;
    }

    if ( ! function_exists( 'tutor' ) ) {
        return;
    }

    $is_course_archive = is_post_type_archive( tutor()->course_post_type );

    // Also check URL path directly in case custom rewrite/template is used
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $path        = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );

    if ( $is_course_archive || 'courses' === $path ) {
        $target_url = get_permalink( 498 );
        if ( empty( $target_url ) ) {
            $target_url = home_url( '/courses/bible-mastery-bootcamp/' );
        }
        wp_safe_redirect( $target_url, 301 );
        exit;
    }
}




