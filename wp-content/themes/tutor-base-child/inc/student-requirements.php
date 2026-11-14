<?php
/** Persist notebook clicks and enforce BMBC progression for the authenticated student. */
defined( 'ABSPATH' ) || exit;

function bmb_student_notebook( $lesson_id ) {
    static $cache = array();
    if ( ! array_key_exists( $lesson_id, $cache ) ) {
        $cache[ $lesson_id ] = null;
        foreach ( (array) get_post_meta( $lesson_id, '_tutor_attachments', true ) as $id ) {
            if ( 'application/pdf' === get_post_mime_type( $id ) ) {
                $cache[ $lesson_id ] = array( 'id' => (int) $id, 'url' => wp_get_attachment_url( $id ), 'path' => get_attached_file( $id ) );
                break;
            }
        }
    }
    return $cache[ $lesson_id ];
}

/** Preserve earned completions without inventing historical download evidence. */
function bmb_student_prepare_notebook_rules( $user_id ) {
    if ( ! $user_id || get_user_meta( $user_id, '_bmb_notebook_rules_v1', true ) ) {
        return;
    }
    $history = (array) get_user_meta( $user_id, '_bmb_historical_class_completions', true );
    foreach ( bmb_student_curriculum() as $module ) {
        foreach ( $module['classes'] as $id => $class ) {
            if ( get_user_meta( $user_id, '_tutor_completed_lesson_id_' . $id, true ) ) {
                $history[] = (int) $id;
            }
        }
    }
    update_user_meta( $user_id, '_bmb_historical_class_completions', array_values( array_unique( array_filter( array_map( 'absint', $history ) ) ) ) );
    update_user_meta( $user_id, '_bmb_notebook_rules_v1', 1 );
}

function bmb_student_notebook_downloaded( $lesson_id, $user_id ) {
    $book = bmb_student_notebook( $lesson_id );
    $record = (array) get_user_meta( $user_id, '_bmb_notebook_downloaded_' . $lesson_id, true );
    return $book && (int) ( $record['attachment_id'] ?? 0 ) === $book['id'] && ! empty( $record['clicked_at'] );
}

function bmb_student_notebook_url( $lesson_id ) {
    return add_query_arg( array( 'bmb_notebook_download' => $lesson_id, '_wpnonce' => wp_create_nonce( 'bmb_notebook_' . $lesson_id ) ), home_url( '/' ) );
}

function bmb_student_record_notebook( $lesson_id, $user_id ) {
    $book = bmb_student_notebook( $lesson_id );
    if ( ! bmb_student_class( $lesson_id ) || ! bmb_student_can_access( $lesson_id, $user_id ) ) {
        return new WP_Error( 'access', 'Your course access does not include this class.' );
    }
    if ( ! $book || ! $book['path'] || ! is_readable( $book['path'] ) ) {
        return new WP_Error( 'notebook', 'The class notebook is not available. Please contact us for help.' );
    }
    // Snapshot existing completions before this student's first new requirement is recorded.
    bmb_student_historical_completions( $user_id );
    if ( ! bmb_student_notebook_downloaded( $lesson_id, $user_id ) ) {
        $saved = update_user_meta( $user_id, '_bmb_notebook_downloaded_' . $lesson_id, array( 'attachment_id' => $book['id'], 'clicked_at' => time() ) );
        if ( ! $saved ) {
            return new WP_Error( 'save', 'The download could not be recorded. Please try again.' );
        }
    }
    bmb_student_sync_completion( $lesson_id, $user_id );
    return $book;
}

add_action( 'template_redirect', static function () {
    if ( empty( $_GET['bmb_notebook_download'] ) ) { return; }
    $lesson_id = absint( $_GET['bmb_notebook_download'] );
    check_admin_referer( 'bmb_notebook_' . $lesson_id );
    $book = bmb_student_record_notebook( $lesson_id, get_current_user_id() );
    if ( is_wp_error( $book ) ) {
        wp_die( esc_html( $book->get_error_message() ), 'Notebook download', array( 'response' => 403 ) );
    }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $book['path'] ) ) . '"' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Length: ' . filesize( $book['path'] ) );
    readfile( $book['path'] );
    exit;
}, 0 );

function bmb_student_requirement_state( $lesson_id, $user_id ) {
    $class = bmb_student_class( $lesson_id );
    if ( ! $class ) {
        return null;
    }
    $state = bmb_student_class_status( $class, $user_id );
    $missing = array();
    if ( ! $state['complete'] ) {
        if ( ! $state['notebook_downloaded'] ) { $missing[] = 'Download the Class Notebook (PDF).'; }
        if ( ! $state['video']['complete'] ) { $missing[] = 'Watch the full class video (' . $state['video']['percent'] . '% watched).'; }
        if ( ! $state['quiz_passed'] ) { $missing[] = 'Pass the class quiz.'; }
    }
    return array( 'lesson_id' => (int) $lesson_id, 'title' => $class['lesson']->post_title, 'notebook_downloaded' => $state['notebook_downloaded'], 'video_complete' => (bool) $state['video']['complete'], 'percent' => $state['video']['percent'], 'quiz_passed' => $state['quiz_passed'], 'complete' => (bool) $state['complete'], 'grandfathered' => $state['grandfathered'], 'missing' => $missing );
}

/** Resolve the first unmet requirement, keeping access and completion independent. */
function bmb_student_navigation_requirement( $target_id, $user_id ) {
    $prior = array();
    foreach ( bmb_student_curriculum() as $module ) {
        foreach ( $module['classes'] as $id => $class ) {
            $quiz = in_array( (int) $target_id, $class['quizzes'], true );
            if ( (int) $id !== (int) $target_id && ! $quiz ) {
                $prior[] = (int) $id;
                continue;
            }
            $state = bmb_student_requirement_state( $id, $user_id );
            if ( ! bmb_student_can_access( $target_id, $user_id ) ) {
                $state['missing'] = array( 'Obtain course access for this class.' );
                return array( 'allowed' => false, 'state' => $state );
            }
            if ( $state['complete'] ) { return array( 'allowed' => true, 'state' => $state ); }
            foreach ( $prior as $prior_id ) {
                $previous = bmb_student_requirement_state( $prior_id, $user_id );
                if ( ! $previous['complete'] ) { return array( 'allowed' => false, 'state' => $previous ); }
            }
            $allowed = ! $quiz || ( $state['notebook_downloaded'] && $state['video_complete'] );
            return array( 'allowed' => $allowed, 'state' => $state );
        }
    }
    return null;
}

add_action( 'wp_ajax_bmb_student_requirements', static function () {
    check_ajax_referer( 'bmb_student_requirements', 'nonce' );
    $user_id = get_current_user_id();
    $mode = sanitize_key( $_POST['mode'] ?? 'state' );
    $id = absint( $_POST['post_id'] ?? 0 );
    if ( 'navigate' === $mode ) {
        $result = bmb_student_navigation_requirement( $id, $user_id );
    } else {
        if ( ! bmb_student_class( $id ) || ! bmb_student_can_access( $id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'You do not have access to this class.' ), 403 );
        }
        if ( 'download' === $mode ) {
            $book = bmb_student_record_notebook( $id, $user_id );
            if ( is_wp_error( $book ) ) { wp_send_json_error( array( 'message' => $book->get_error_message() ), 400 ); }
        }
        $result = array( 'state' => bmb_student_requirement_state( $id, $user_id ), 'download_url' => bmb_student_notebook_url( $id ) );
    }
    if ( ! $result ) { wp_send_json_error( array( 'message' => 'This class is not available.' ), 400 ); }
    wp_send_json_success( $result );
} );

// A pasted URL or submitted quiz form must obey the same rules as the visible links.
add_action( 'template_redirect', static function () {
    if ( ! is_user_logged_in() ) { return; }
    $target = is_singular( array( 'lesson', 'tutor_quiz' ) ) ? get_queried_object_id() : 0;
    if ( bmb_is_student_dashboard() && ! empty( $_GET['class'] ) ) { $target = absint( $_GET['class'] ); }
    if ( 'tutor_start_quiz' === ( $_POST['tutor_action'] ?? '' ) ) { $target = absint( $_POST['quiz_id'] ?? 0 ); }
    $result = $target ? bmb_student_navigation_requirement( $target, get_current_user_id() ) : null;
    if ( $result && ! $result['allowed'] ) {
        $args = array( 'bmb_notice' => $target );
        if ( bmb_student_can_access( $result['state']['lesson_id'], get_current_user_id() ) ) {
            $args['class'] = $result['state']['lesson_id'];
        }
        wp_safe_redirect( add_query_arg( $args, tutor_utils()->tutor_dashboard_url() ) );
        exit;
    }
}, 3 );
add_action( 'tutor_quiz/start/before', static function ( $quiz_id, $user_id ) {
    $result = bmb_student_navigation_requirement( $quiz_id, $user_id );
    if ( $result && ! $result['allowed'] ) {
        wp_die( esc_html( implode( ' ', $result['state']['missing'] ) ), 'Class requirements', array( 'response' => 403 ) );
    }
}, 1, 2 );

add_filter( 'tutor_lesson_single_nav_items', static function ( $items ) {
    if ( ! bmb_student_class( get_the_ID() ) ) { return $items; }
    foreach ( $items as &$item ) {
        if ( 'exercise_files' === ( $item['id'] ?? '' ) || 'files' === ( $item['value'] ?? '' ) ) {
            $item['label'] = 'Download Class Notebook';
        }
    }
    return $items;
}, 1000 );

// Cover authored PDF links, the native attachment panel, and dashboard resources.
add_filter( 'the_content', static function ( $html ) {
    $id = get_the_ID();
    if ( ! bmb_student_class( $id ) || ! is_user_logged_in() ) { return $html; }
    $book = bmb_student_notebook( $id );
    if ( ! $book ) { return $html; }
    $tags = new WP_HTML_Tag_Processor( $html );
    while ( $tags->next_tag( 'A' ) ) {
        if ( wp_parse_url( $tags->get_attribute( 'href' ) ?? '', PHP_URL_PATH ) === wp_parse_url( $book['url'], PHP_URL_PATH ) ) {
            $tags->set_attribute( 'href', bmb_student_notebook_url( $id ) );
            $tags->set_attribute( 'data-bmb-notebook', (string) $id );
            $tags->set_attribute( 'download', '' );
            $tags->add_class( 'bmb-notebook-download' );
        }
    }
    return $tags->get_updated_html();
}, 30 );

add_action( 'wp_enqueue_scripts', static function () {
    if ( ! bmb_is_student_portal_page() || ! is_user_logged_in() ) { return; }
    $dir = get_stylesheet_directory(); $uri = get_stylesheet_directory_uri();
    if ( ! file_exists( $dir . '/assets/js/student-requirements.js' ) ) { return; }
    wp_enqueue_style( 'bmb-student-requirements', $uri . '/assets/css/student-requirements.css', array(), filemtime( $dir . '/assets/css/student-requirements.css' ) );
    wp_enqueue_script( 'bmb-student-requirements', $uri . '/assets/js/student-requirements.js', array(), filemtime( $dir . '/assets/js/student-requirements.js' ), true );
    $classes = array();
    foreach ( bmb_student_curriculum() as $module ) {
        foreach ( $module['classes'] as $id => $class ) {
            $book = bmb_student_notebook( $id );
            $classes[] = array( 'id' => $id, 'url' => get_permalink( $id ), 'quizzes' => array_map( static fn( $q ) => array( 'id' => $q, 'url' => get_permalink( $q ) ), $class['quizzes'] ), 'notebook_url' => $book['url'] ?? '', 'state' => bmb_student_requirement_state( $id, get_current_user_id() ) );
        }
    }
    $initial = ! empty( $_GET['bmb_notice'] ) ? bmb_student_navigation_requirement( absint( $_GET['bmb_notice'] ), get_current_user_id() ) : null;
    wp_localize_script( 'bmb-student-requirements', 'bmbRequirements', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'bmb_student_requirements' ), 'dashboardUrl' => tutor_utils()->tutor_dashboard_url(), 'classes' => $classes, 'initialNotice' => $initial && ! $initial['allowed'] ? $initial['state'] : null ) );
}, 30 );
