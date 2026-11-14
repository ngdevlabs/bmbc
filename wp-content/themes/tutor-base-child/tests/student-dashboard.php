<?php
/** Run locally with: wp eval-file wp-content/themes/tutor-base-child/tests/student-dashboard.php */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit;
}
if ( 'local' !== wp_get_environment_type() ) {
    WP_CLI::error( 'Run these fixture-based checks only in the local environment.' );
}

global $wpdb;
$student_id = wp_insert_user( array( 'user_login' => 'bmb-check-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32, true ), 'role' => 'subscriber' ) );
if ( is_wp_error( $student_id ) ) {
    throw new RuntimeException( $student_id->get_error_message() );
}
$checks = 0;
$historical_student_id = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$checks;
    WP_CLI::log( 'PASS: ' . $message );
};
$lesson_id = 501;
$class = bmb_student_class( $lesson_id );
$duration = bmb_student_video( $lesson_id )['duration'];
$set_ranges = static function ( $ranges ) use ( $student_id, $lesson_id ) {
    update_user_meta( $student_id, '_bmb_video_' . $lesson_id, array( 'fingerprint' => bmb_student_video( $lesson_id )['fingerprint'], 'ranges' => $ranges ) );
};
$set_attempt = static function ( $earned, $status = 'attempt_ended', $result = 'pass', $ended = true ) use ( $wpdb, $student_id, $class ) {
    $wpdb->delete( $wpdb->prefix . 'tutor_quiz_attempts', array( 'user_id' => $student_id ) );
    $wpdb->insert( $wpdb->prefix . 'tutor_quiz_attempts', array(
        'course_id' => bmb_student_course_id(), 'quiz_id' => $class['quizzes'][0], 'user_id' => $student_id,
        'total_questions' => 1, 'total_marks' => 100, 'earned_marks' => $earned, 'attempt_status' => $status,
        'result' => $result, 'attempt_started_at' => current_time( 'mysql' ), 'attempt_ended_at' => $ended ? current_time( 'mysql' ) : null,
    ) );
    bmb_student_passed_attempts( $student_id, true );
};

try {
    $assert( count( bmb_student_curriculum() ) === 4, 'Real curriculum contains four modules.' );
    $assert( $class['quizzes'] === array( 504 ) && bmb_student_class( 505 )['quizzes'] === array( 520 ), 'Each class uses its own following quiz.' );
    $assert( ! bmb_student_class_status( $class, $student_id )['complete'], 'A new student starts with no completed classes.' );
    $assert( bmb_student_can_access( 501, $student_id ) && ! bmb_student_can_access( 521, $student_id ), 'Existing free-preview and paid-access boundaries are preserved.' );

    \Tutor\Models\LessonModel::mark_lesson_complete( $lesson_id, $student_id );
    $assert( ! get_user_meta( $student_id, '_tutor_completed_lesson_id_' . $lesson_id, true ), 'Direct Tutor auto-completion cannot bypass the requirements.' );

    $set_ranges( array( array( 0, $duration * 0.95 ) ) );
    $set_attempt( 100 );
    $assert( ! bmb_student_class_status( $class, $student_id )['complete'], 'A passed quiz plus 95% watched is insufficient.' );
    $assert( false === update_user_meta( $student_id, '_tutor_completed_lesson_id_' . $lesson_id, time() ), 'Manual completion metadata writes are also guarded.' );

    $set_ranges( array( array( 0, $duration - 20 ), array( $duration - 10, $duration ) ) );
    $assert( ! bmb_student_class_status( $class, $student_id )['complete'], 'Reaching the end with a skipped section does not count as a full watch.' );

    $set_ranges( array( array( 0, $duration ) ) );
    $set_attempt( 0, 'attempt_ended', 'fail' );
    $assert( ! bmb_student_class_status( $class, $student_id )['complete'], 'Full video plus failed quiz is insufficient.' );
    $set_attempt( 100, 'attempt_started', 'pass', false );
    $assert( ! bmb_student_class_status( $class, $student_id )['complete'], 'An unfinished attempt with apparent passing marks cannot count.' );
    $set_attempt( 100, 'review_required', 'pending' );
    $assert( ! bmb_student_class_status( $class, $student_id )['complete'], 'An attempt awaiting grading cannot count.' );
    $set_attempt( 100 );
    $assert( bmb_student_class_status( $class, $student_id )['complete'], 'Full video and a graded passing quiz complete the class.' );
    bmb_student_sync_completion( $lesson_id, $student_id );
    $assert( (bool) get_user_meta( $student_id, '_tutor_completed_lesson_id_' . $lesson_id, true ), 'Verified completion synchronizes into Tutor LMS.' );

    // A failed retry must not erase an earlier pass.
    $wpdb->insert( $wpdb->prefix . 'tutor_quiz_attempts', array( 'course_id' => bmb_student_course_id(), 'quiz_id' => 504, 'user_id' => $student_id, 'total_marks' => 100, 'earned_marks' => 0, 'attempt_status' => 'attempt_ended', 'result' => 'fail', 'attempt_ended_at' => current_time( 'mysql' ) ) );
    bmb_student_passed_attempts( $student_id, true );
    $assert( bmb_student_class_status( $class, $student_id )['complete'], 'A failed retry does not remove an existing valid pass.' );
    $assert( ! bmb_student_class_status( array_merge( $class, array( 'quizzes' => array() ) ), $student_id )['complete'], 'A class without an associated quiz cannot silently complete.' );

    delete_user_meta( $student_id, '_bmb_video_' . $lesson_id );
    $session = bmb_student_save_video( $lesson_id, $student_id, array(), '', true );
    $assert( ! is_wp_error( $session ) && ! empty( $session['token'] ), 'An authorized playback session can begin.' );
    $forged = bmb_student_save_video( $lesson_id, $student_id, array( array( 0, $duration ) ), $session['token'] );
    $assert( is_wp_error( $forged ) && 'elapsed' === $forged->get_error_code(), 'A forged full-video report is rejected by the server clock.' );
    $assert( is_wp_error( bmb_student_save_video( $lesson_id, $student_id, array(), 'wrong-token' ) ), 'A forged playback session is rejected.' );
    $assert( is_wp_error( bmb_student_save_video( 521, $student_id, array(), '', true ) ), 'The tracking endpoint rejects paid content without access.' );
    $assert( is_wp_error( bmb_student_save_video( 527, $student_id, array(), '', true ) ), 'Ordinary pages cannot be submitted as course lessons.' );
    $assert( is_wp_error( bmb_student_save_video( $lesson_id, 0, array(), '', true ) ), 'Anonymous playback cannot write student progress.' );

    // Simulate ten elapsed seconds without sleeping or modifying the course video.
    $progress = get_user_meta( $student_id, '_bmb_video_' . $lesson_id, true );
    $progress['session']['at'] -= 10;
    update_user_meta( $student_id, '_bmb_video_' . $lesson_id, $progress );
    $saved = bmb_student_save_video( $lesson_id, $student_id, array( array( 0, 8 ) ), $session['token'] );
    $assert( ! is_wp_error( $saved ) && bmb_student_video_progress( $lesson_id, $student_id )['seconds'] === 8.0, 'Valid partial playback persists and resumes across requests.' );
    $progress = get_user_meta( $student_id, '_bmb_video_' . $lesson_id, true );
    $progress['session']['at'] -= $duration;
    update_user_meta( $student_id, '_bmb_video_' . $lesson_id, $progress );
    $finished = bmb_student_save_video( $lesson_id, $student_id, array( array( 0, $duration ) ), $session['token'] );
    $assert( ! is_wp_error( $finished ) && $finished['complete'] && strpos( $finished['next_url'], 'class=505' ) !== false, 'Completing both requirements returns the next class for Continue Learning.' );

    $original = (array) get_post_meta( $lesson_id, '_video', true );
    $tampered = $original;
    $tampered['duration_sec'] = 1;
    $tampered['runtime'] = array( 'hours' => 0, 'minutes' => 0, 'seconds' => 1 );
    $override = static function ( $value, $id, $key ) use ( $tampered, $lesson_id ) { return $id === $lesson_id && '_video' === $key ? array( $tampered ) : $value; };
    add_filter( 'get_post_metadata', $override, 10, 3 );
    $assert( bmb_student_video( $lesson_id )['duration'] === $duration, 'Client-editable playback duration cannot shorten the real media duration.' );
    remove_filter( 'get_post_metadata', $override );

    $assert( ! bmb_student_video( 623 )['url'], 'The existing unconfigured class video is detected rather than fabricated.' );
    $assert( count( bmb_student_resources( 505 ) ) === 3, 'Existing notebook and both supporting videos are discovered.' );

    $historical_student_id = wp_insert_user( array( 'user_login' => 'bmb-check-history-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password( 32, true ), 'role' => 'subscriber' ) );
    if ( is_wp_error( $historical_student_id ) ) {
        throw new RuntimeException( $historical_student_id->get_error_message() );
    }
    // Seed a pre-rollout record directly, as it existed before the new write guard.
    $wpdb->insert( $wpdb->usermeta, array( 'user_id' => $historical_student_id, 'meta_key' => '_tutor_completed_lesson_id_501', 'meta_value' => time() - DAY_IN_SECONDS ) );
    wp_cache_delete( $historical_student_id, 'user_meta' );
    $historical = bmb_student_class_status( $class, $historical_student_id );
    $assert( $historical['complete'] && $historical['grandfathered'], 'Historical class completions are honored without rewatching or retaking a quiz.' );
    $assert( ! $historical['video']['complete'] && ! $historical['quiz_passed'], 'Honoring history does not fabricate video evidence or quiz grades.' );
    $assert( false === update_user_meta( $historical_student_id, '_tutor_completed_lesson_id_505', time() - DAY_IN_SECONDS ), 'Backdating a new completion cannot bypass the new requirements.' );
    $assert( bmb_student_historical_completions( $historical_student_id ) === array( 501 ), 'The historical snapshot does not grow to include future completions.' );
    $assert( ! bmb_student_class_status( $class, $student_id )['grandfathered'], 'New verified completions stay distinct from historical completions.' );
    delete_user_meta( $historical_student_id, '_tutor_completed_lesson_id_501' );
    $assert( ! bmb_student_class_status( $class, $historical_student_id )['complete'], 'An explicitly cleared completion is not resurrected from the historical snapshot.' );

    $previous_user = get_current_user_id();
    wp_set_current_user( $student_id );
    ob_start();
    bmb_student_navigation();
    $navigation = ob_get_clean();
    wp_set_current_user( $previous_user );
    $assert( strpos( $navigation, '>Dashboard</a>' ) !== false && strpos( $navigation, 'Course Home' ) === false, 'The shared menu returns to Dashboard and removes Course Home.' );
    $assert( strpos( $navigation, 'href="https://ybhstore.company.site/" target="_blank" rel="noopener noreferrer"' ) !== false, 'Store points to the requested external storefront in a safe new tab.' );
    $assert( strpos( $navigation, 'href="' . esc_url( home_url( '/contact/' ) ) . '"' ) !== false && strpos( $navigation, '/contact-us/' ) === false, 'Contact navigation uses only the new Contact page.' );
    $assert( strpos( $navigation, '#bmb-syllabus' ) !== false, 'Syllabus navigation points to the dashboard class list.' );
    $assert( 'courses' === get_post_type( bmb_student_course_id() ) && count( bmb_student_curriculum() ) === 4, 'Retiring the overview preserves the underlying course and modules.' );
    $dashboard_url = tutor_utils()->tutor_dashboard_url();
    $assert( apply_filters( 'login_redirect', get_permalink( bmb_student_course_id() ), '', get_userdata( $student_id ) ) === $dashboard_url, 'Student WordPress login redirects to the dashboard rather than the syllabus.' );
    $assert( apply_filters( 'tutor_after_login_redirect_url', get_permalink( bmb_student_course_id() ) ) === $dashboard_url, 'Tutor login uses the same dashboard destination.' );
    $assert( apply_filters( 'login_redirect', admin_url(), '', get_userdata( 1 ) ) === admin_url(), 'Administrator WordPress login remains unchanged.' );
    WP_CLI::success( $checks . ' dashboard and completion checks passed.' );
} finally {
    $wpdb->delete( $wpdb->prefix . 'tutor_quiz_attempts', array( 'user_id' => $student_id ) );
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $student_id );
    if ( $historical_student_id && ! is_wp_error( $historical_student_id ) ) {
        wp_delete_user( $historical_student_id );
    }
}
