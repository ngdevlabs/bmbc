<?php
/**
 * BMBC student dashboard and verified class completion.
 */
defined( 'ABSPATH' ) || exit;

function bmb_student_course_id() {
    static $id;
    if ( null === $id ) {
        $course = get_page_by_path( 'bible-mastery-bootcamp', OBJECT, 'courses' );
        $id = $course ? (int) $course->ID : 0;
    }
    return (int) apply_filters( 'bmb_student_course_id', $id );
}

/** Pair each lesson with the quiz(s) following it in the same topic. */
function bmb_student_curriculum() {
    static $curricula = array();
    $course_id = bmb_student_course_id();
    if ( ! $course_id ) {
        return array();
    }
    if ( isset( $curricula[ $course_id ] ) ) {
        return $curricula[ $course_id ];
    }
    $modules = array();
    $topics = get_posts( array( 'post_type' => 'topics', 'post_parent' => $course_id, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order ID', 'order' => 'ASC' ) );
    foreach ( $topics as $topic ) {
        $classes = array();
        $lesson_id = 0;
        $contents = get_posts( array( 'post_type' => array( 'lesson', 'tutor_quiz' ), 'post_parent' => $topic->ID, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order ID', 'order' => 'ASC' ) );
        foreach ( $contents as $post ) {
            if ( 'lesson' === $post->post_type ) {
                $lesson_id = $post->ID;
                $classes[ $lesson_id ] = array( 'lesson' => $post, 'quizzes' => array(), 'topic' => $topic );
            } elseif ( $lesson_id ) {
                $classes[ $lesson_id ]['quizzes'][] = $post->ID;
            }
        }
        $modules[] = array( 'topic' => $topic, 'classes' => $classes );
    }
    $curricula[ $course_id ] = $modules;
    return $modules;
}

function bmb_student_class( $lesson_id ) {
    foreach ( bmb_student_curriculum() as $module ) {
        if ( isset( $module['classes'][ $lesson_id ] ) ) {
            return $module['classes'][ $lesson_id ];
        }
    }
    return null;
}

/** Use the site's existing preview/subscription rules without granting new access. */
function bmb_student_can_access( $post_id, $user_id ) {
    if ( ! $user_id || 'publish' !== get_post_status( $post_id ) ) {
        return false;
    }
    if ( class_exists( 'TLP_Access_Control' ) ) {
        if ( TLP_Access_Control::is_preview_content( $post_id ) ) {
            return true;
        }
        static $paid_access = array();
        $key = bmb_student_course_id() . ':' . $user_id;
        if ( ! array_key_exists( $key, $paid_access ) ) {
            $paid_access[ $key ] = TLP_Access_Control::has_real_enrollment( bmb_student_course_id(), $user_id );
        }
        return $paid_access[ $key ];
    }
    $type = 'tutor_quiz' === get_post_type( $post_id ) ? 'quiz' : 'lesson';
    return tutor_utils()->has_enrolled_content_access( $type, $post_id, $user_id );
}

function bmb_student_video( $lesson_id ) {
    $video = (array) get_post_meta( $lesson_id, '_video', true );
    $attachment_id = absint( $video['source_video_id'] ?? 0 );
    $metadata = $attachment_id ? (array) wp_get_attachment_metadata( $attachment_id ) : array();
    $runtime = $video['runtime'] ?? array();
    // Tutor's playback endpoint accepts client duration; use the server's media metadata first.
    $duration = (float) ( $metadata['length'] ?? 0 );
    if ( $duration <= 0 ) {
        $duration = (float) ( $runtime['hours'] ?? 0 ) * 3600 + (float) ( $runtime['minutes'] ?? 0 ) * 60 + (float) ( $runtime['seconds'] ?? 0 );
    }
    if ( $duration <= 0 ) {
        $duration = (float) ( $video['duration_sec'] ?? 0 );
    }
    $source = $video['source'] ?? '';
    $url = 'html5' === $source ? wp_get_attachment_url( $attachment_id ) : '';
    if ( 'html5' === $source && ! $url ) {
        $url = $video['source_html5'] ?? '';
    }
    return array(
        'url' => $url,
        'duration' => $duration,
        'poster' => wp_get_attachment_url( absint( $video['poster'] ?? 0 ) ),
        'captions' => $attachment_id ? get_post_meta( $attachment_id, 'vtt_caption_en', true ) : '',
        'fingerprint' => hash( 'sha256', wp_json_encode( array( $source, $attachment_id, $url, $duration, $metadata['filesize'] ?? 0 ) ) ),
    );
}

/** Merge actual playback ranges. Seeking ahead must not fill unwatched gaps. */
function bmb_student_merge_ranges( $ranges, $duration ) {
    $valid = array();
    foreach ( $ranges as $range ) {
        if ( ! is_array( $range ) || count( $range ) !== 2 || ! is_numeric( $range[0] ) || ! is_numeric( $range[1] ) ) {
            continue;
        }
        $start = max( 0, min( $duration, (float) $range[0] ) );
        $end = max( 0, min( $duration, (float) $range[1] ) );
        if ( $end > $start ) {
            $valid[] = array( $start, $end );
        }
    }
    usort( $valid, static fn( $a, $b ) => $a[0] <=> $b[0] );
    $merged = array();
    foreach ( $valid as $range ) {
        $last = count( $merged ) - 1;
        if ( $last >= 0 && $range[0] <= $merged[ $last ][1] + 0.1 ) {
            $merged[ $last ][1] = max( $merged[ $last ][1], $range[1] );
        } else {
            $merged[] = $range;
        }
    }
    return $merged;
}

function bmb_student_range_seconds( $ranges ) {
    return array_sum( array_map( static fn( $r ) => $r[1] - $r[0], $ranges ) );
}

function bmb_student_video_progress( $lesson_id, $user_id ) {
    $video = bmb_student_video( $lesson_id );
    $progress = (array) get_user_meta( $user_id, '_bmb_video_' . $lesson_id, true );
    if ( ( $progress['fingerprint'] ?? '' ) !== $video['fingerprint'] ) {
        $progress = array();
    }
    $progress['ranges'] = bmb_student_merge_ranges( $progress['ranges'] ?? array(), $video['duration'] );
    $progress['seconds'] = bmb_student_range_seconds( $progress['ranges'] );
    // Half a second accommodates the final video frame; skipped sections still count as gaps.
    $progress['complete'] = $video['url'] && $video['duration'] > 0 && $progress['seconds'] >= $video['duration'] - 0.5;
    $progress['percent'] = $progress['complete'] ? 100 : ( $video['duration'] > 0 ? min( 99, (int) floor( 100 * $progress['seconds'] / $video['duration'] ) ) : 0 );
    return $progress;
}

/** Only graded, finished attempts qualify; an open or pending attempt never passes. */
function bmb_student_quiz_passed( $quiz_id, $user_id ) {
    global $wpdb;
    $attempts = bmb_student_passed_attempts( $user_id );
    $passing = (float) tutor_utils()->get_quiz_option( $quiz_id, 'passing_grade', 80 );
    foreach ( $attempts as $attempt ) {
        if ( (int) $attempt->quiz_id !== (int) $quiz_id ) {
            continue;
        }
        if ( (float) $attempt->total_marks > 0 && (float) $attempt->earned_marks / (float) $attempt->total_marks * 100 >= $passing ) {
            return true;
        }
    }
    return false;
}

function bmb_student_passed_attempts( $user_id, $refresh = false ) {
    global $wpdb;
    static $cache = array();
    if ( $refresh || ! isset( $cache[ $user_id ] ) ) {
        $cache[ $user_id ] = $wpdb->get_results( $wpdb->prepare(
            "SELECT quiz_id, total_marks, earned_marks FROM {$wpdb->prefix}tutor_quiz_attempts WHERE course_id = %d AND user_id = %d AND attempt_ended_at IS NOT NULL AND result = 'pass' AND (attempt_status IN ('attempt_ended', 'attempt_timeout') OR is_manually_reviewed = 1)",
            bmb_student_course_id(), $user_id
        ) );
    }
    return $cache[ $user_id ];
}

/** Snapshot old Tutor completions once, before any new completion write is allowed. */
function bmb_student_historical_completions( $user_id ) {
    if ( ! $user_id ) {
        return array();
    }
    $key = '_bmb_historical_class_completions';
    if ( ! metadata_exists( 'user', $user_id, $key ) ) {
        $historical = array();
        foreach ( bmb_student_curriculum() as $module ) {
            foreach ( $module['classes'] as $lesson_id => $class ) {
                if ( get_user_meta( $user_id, '_tutor_completed_lesson_id_' . $lesson_id, true ) ) {
                    $historical[] = (int) $lesson_id;
                }
            }
        }
        add_user_meta( $user_id, $key, $historical, true );
    }
    return (array) get_user_meta( $user_id, $key, true );
}

function bmb_student_class_status( $class, $user_id ) {
    $lesson_id = $class['lesson']->ID;
    $grandfathered = in_array( (int) $lesson_id, bmb_student_historical_completions( $user_id ), true )
        && (bool) get_user_meta( $user_id, '_tutor_completed_lesson_id_' . $lesson_id, true );
    $video = bmb_student_video_progress( $lesson_id, $user_id );
    $quizzes_passed = ! empty( $class['quizzes'] );
    foreach ( $class['quizzes'] as $quiz_id ) {
        $quizzes_passed = bmb_student_quiz_passed( $quiz_id, $user_id ) && $quizzes_passed;
    }
    return array(
        'video' => $video,
        'quiz_passed' => $quizzes_passed,
        'complete' => $grandfathered || ( $video['complete'] && $quizzes_passed ),
        'grandfathered' => $grandfathered,
        'accessible' => bmb_student_can_access( $lesson_id, $user_id ),
    );
}

function bmb_student_sync_completion( $lesson_id, $user_id ) {
    $class = bmb_student_class( $lesson_id );
    if ( ! $class || ! bmb_student_class_status( $class, $user_id )['complete'] || ! bmb_student_can_access( $lesson_id, $user_id ) ) {
        return;
    }
    if ( ! get_user_meta( $user_id, '_tutor_completed_lesson_id_' . $lesson_id, true ) ) {
        \Tutor\Models\LessonModel::mark_lesson_complete( $lesson_id, $user_id );
    }
}

/** Protect every Tutor completion path, including automatic player/REST completion. */
function bmb_student_guard_completion_meta( $check, $user_id, $meta_key ) {
    if ( preg_match( '/^_tutor_completed_lesson_id_(\d+)$/', $meta_key, $match ) ) {
        $class = bmb_student_class( (int) $match[1] );
        if ( $class && ! bmb_student_class_status( $class, $user_id )['complete'] ) {
            return false;
        }
    }
    return $check;
}
add_filter( 'add_user_metadata', 'bmb_student_guard_completion_meta', 1000, 3 );
add_filter( 'update_user_metadata', 'bmb_student_guard_completion_meta', 1000, 3 );
add_filter( 'tutor_validate_lesson_complete', static function ( $valid, $user_id, $lesson_id ) {
    $class = bmb_student_class( $lesson_id );
    return $class ? $valid && bmb_student_class_status( $class, $user_id )['complete'] : $valid;
}, 1000, 3 );
// The native player also sends completion emails on "ended", before its quiz may be passed.
add_action( 'wp_ajax_sync_video_playback', static function () {
    $class = bmb_student_class( absint( $_POST['post_id'] ?? 0 ) );
    if ( $class && ! bmb_student_class_status( $class, get_current_user_id() )['complete'] ) {
        $_POST['is_ended'] = '0';
    }
}, 0 );

function bmb_student_sync_quiz( $attempt_id ) {
    global $wpdb;
    $attempt = $wpdb->get_row( $wpdb->prepare( "SELECT quiz_id, user_id FROM {$wpdb->prefix}tutor_quiz_attempts WHERE attempt_id = %d", $attempt_id ) );
    if ( ! $attempt ) {
        return;
    }
    bmb_student_passed_attempts( (int) $attempt->user_id, true );
    foreach ( bmb_student_curriculum() as $module ) {
        foreach ( $module['classes'] as $class ) {
            if ( in_array( (int) $attempt->quiz_id, $class['quizzes'], true ) ) {
                bmb_student_sync_completion( $class['lesson']->ID, (int) $attempt->user_id );
            }
        }
    }
}
add_action( 'tutor_quiz/attempt_ended', 'bmb_student_sync_quiz' );
add_action( 'tutor_quiz_finished', 'bmb_student_sync_quiz' );
add_action( 'tutor_quiz_review_answer_after', static function ( $answer_id, $attempt_id ) { bmb_student_sync_quiz( $attempt_id ); }, 20, 2 );

/** Record bounded playback evidence using a nonce, access check, and server clock. */
function bmb_student_save_video( $lesson_id, $user_id, $ranges, $token = '', $start = false ) {
    $class = bmb_student_class( $lesson_id );
    if ( ! $class || ! bmb_student_can_access( $lesson_id, $user_id ) ) {
        return new WP_Error( 'access', 'You do not have access to this class.' );
    }
    $video = bmb_student_video( $lesson_id );
    if ( ! $video['url'] || $video['duration'] <= 0 ) {
        return new WP_Error( 'video', 'This class video is not configured for progress tracking.' );
    }
    $progress = bmb_student_video_progress( $lesson_id, $user_id );
    $now = microtime( true );
    if ( $start ) {
        $progress['session'] = array( 'token' => wp_generate_password( 32, false ), 'at' => $now );
    } else {
        $session = $progress['session'] ?? array();
        if ( ! $token || ! hash_equals( $session['token'] ?? '', $token ) ) {
            return new WP_Error( 'session', 'Reopen this class to resume saving progress.' );
        }
        if ( ! is_array( $ranges ) || count( $ranges ) > 500 ) {
            return new WP_Error( 'ranges', 'Invalid playback information.' );
        }
        $merged = bmb_student_merge_ranges( array_merge( $progress['ranges'], $ranges ), $video['duration'] );
        $growth = bmb_student_range_seconds( $merged ) - $progress['seconds'];
        // The native player permits up to 2x speed. Reject time that could not have been watched.
        if ( $growth > max( 0, $now - (float) $session['at'] ) * 2 + 1 ) {
            return new WP_Error( 'elapsed', 'Progress could not be saved. Continue watching and try again.' );
        }
        $progress['ranges'] = $merged;
        $progress['session']['at'] = $now;
    }
    $progress['fingerprint'] = $video['fingerprint'];
    unset( $progress['complete'], $progress['percent'], $progress['seconds'] );
    update_user_meta( $user_id, '_bmb_video_' . $lesson_id, $progress );
    bmb_student_sync_completion( $lesson_id, $user_id );
    $status = bmb_student_class_status( $class, $user_id );
    $result = array( 'token' => $progress['session']['token'], 'percent' => $status['video']['percent'], 'video_complete' => $status['video']['complete'], 'complete' => $status['complete'], 'grandfathered' => $status['grandfathered'] );
    if ( $status['complete'] ) {
        $result['next_url'] = '';
        foreach ( bmb_student_curriculum() as $module ) {
            foreach ( $module['classes'] as $next_class ) {
                if ( ! bmb_student_class_status( $next_class, $user_id )['complete'] ) {
                    $result['next_url'] = add_query_arg( 'class', $next_class['lesson']->ID, tutor_utils()->tutor_dashboard_url() ) . '#bmb-class-title';
                    break 2;
                }
            }
        }
    }
    return $result;
}

add_action( 'wp_ajax_bmb_student_video', static function () {
    check_ajax_referer( 'bmb_student_video', 'nonce' );
    if ( ! is_string( $_POST['ranges'] ?? '' ) ) {
        wp_send_json_error( array( 'message' => 'Invalid playback information.' ), 400 );
    }
    $result = bmb_student_save_video(
        absint( $_POST['lesson_id'] ?? 0 ), get_current_user_id(),
        json_decode( wp_unslash( $_POST['ranges'] ?? '[]' ), true ),
        sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ),
        'start' === ( $_POST['mode'] ?? '' )
    );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
    }
    wp_send_json_success( $result );
} );

function bmb_is_student_dashboard() {
    return function_exists( 'tutor_utils' ) && is_user_logged_in() && tutor_utils()->is_tutor_frontend_dashboard() && ! get_query_var( 'tutor_dashboard_page' );
}
add_filter( 'template_include', static function ( $template ) {
    return bmb_is_student_dashboard() ? get_stylesheet_directory() . '/student-dashboard.php' : $template;
}, 110 );
add_action( 'template_redirect', static function () {
    if ( bmb_is_student_dashboard() ) {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        nocache_headers();
    }
}, 1 );
add_filter( 'kirki_show_custom_section_footer', static function ( $show ) { return bmb_is_student_dashboard() ? false : $show; } );

add_action( 'wp_enqueue_scripts', static function () {
    $dashboard = bmb_is_student_dashboard();
    $lesson = is_singular( 'lesson' ) && bmb_student_class( get_queried_object_id() );
    if ( ! $dashboard && ! $lesson ) {
        return;
    }
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    if ( $dashboard ) {
        wp_enqueue_style( 'bmb-student-dashboard', $uri . '/assets/css/student-dashboard.css', array( 'child-style' ), filemtime( $dir . '/assets/css/student-dashboard.css' ) );
    }
    wp_enqueue_script( 'bmb-student-dashboard', $uri . '/assets/js/student-dashboard.js', array(), filemtime( $dir . '/assets/js/student-dashboard.js' ), true );
    wp_localize_script( 'bmb-student-dashboard', 'bmbStudent', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'bmb_student_video' ), 'lessonId' => $lesson ? get_queried_object_id() : 0 ) );
} );

/** Available notebooks and supplementary videos, without inventing missing resources. */
function bmb_student_resources( $lesson_id ) {
    $resources = array();
    foreach ( (array) get_post_meta( $lesson_id, '_tutor_attachments', true ) as $id ) {
        $url = wp_get_attachment_url( $id );
        if ( $url ) {
            $resources[ $url ] = array( 'url' => $url, 'title' => get_the_title( $id ), 'type' => 'Notebook / resource' );
        }
    }
    $content = get_post_field( 'post_content', $lesson_id );
    $tags = new WP_HTML_Tag_Processor( $content );
    $clips = array();
    while ( $tags->next_tag( 'A' ) ) {
        $href = $tags->get_attribute( 'href' );
        if ( $href && preg_match( '/\.(mp4|webm)(?:\?|$)/i', $href ) ) {
            $clips[] = $href;
        }
    }
    if ( preg_match_all( '/\[video\b([^\]]*)\]|\[embed\](.*?)\[\/embed\]/is', $content, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $attributes = shortcode_parse_atts( $match[1] );
            $url = $attributes['mp4'] ?? trim( $match[2] ?? '' );
            if ( $url ) {
                $clips[] = $url;
            }
        }
    }
    foreach ( array_unique( $clips ) as $url ) {
        $resources[ $url ] = array( 'url' => $url, 'title' => str_replace( array( '-', '_' ), ' ', pathinfo( urldecode( wp_parse_url( $url, PHP_URL_PATH ) ), PATHINFO_FILENAME ) ), 'type' => 'Before / after class video' );
    }
    return array_values( $resources );
}
