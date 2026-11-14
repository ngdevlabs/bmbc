<?php
/** Student learning home. All progress belongs to the authenticated current user. */
defined( 'ABSPATH' ) || exit;

$user = wp_get_current_user();
$modules = bmb_student_curriculum();
$classes = array();
$states = array();
$completed = 0;
$next_id = 0;
foreach ( $modules as $module ) {
    foreach ( $module['classes'] as $id => $class ) {
        $classes[ $id ] = $class;
        $states[ $id ] = bmb_student_class_status( $class, $user->ID );
        if ( $states[ $id ]['complete'] ) {
            ++$completed;
            bmb_student_sync_completion( $id, $user->ID );
        } elseif ( ! $next_id ) {
            $next_id = $id;
        }
    }
}
$dashboard_url = tutor_utils()->tutor_dashboard_url();
$requested_id = absint( $_GET['class'] ?? 0 );
$selected_id = isset( $classes[ $requested_id ] ) ? $requested_id : ( $next_id ?: (int) array_key_first( $classes ) );
$selected = $classes[ $selected_id ] ?? null;
$state = $states[ $selected_id ] ?? null;
$video = $selected ? bmb_student_video( $selected_id ) : null;
$percent = $classes ? (int) floor( $completed / count( $classes ) * 100 ) : 0;
$name = $user->first_name ?: $user->display_name;
$course_url = get_permalink( bmb_student_course_id() );
$checkout = class_exists( 'TLP_Access_Control' ) ? TLP_Access_Control::get_course_checkout_url( bmb_student_course_id() ) : $course_url;
get_header();
?>
<main id="bmb-student-dashboard" class="bmb-student-dashboard">
    <div class="bmb-dashboard-container">
        <?php bmb_student_navigation(); ?>

        <div class="bmb-dashboard-heading">
            <div><p class="bmb-eyebrow">BIBLE MASTERY BOOT CAMP</p><h1><?php echo esc_html( $name ); ?>’s learning dashboard</h1><p>Pick up where you left off. Your next step is right here.</p></div>
            <div class="bmb-overall-progress"><strong data-bmb-total-percent><?php echo esc_html( $percent ); ?>%</strong><span>of your course completed</span></div>
        </div>

        <section class="bmb-progress-card" aria-label="Overall course progress">
            <div><strong><span data-bmb-total-completed><?php echo esc_html( $completed ); ?></span> of <?php echo esc_html( count( $classes ) ); ?> classes complete</strong><span>Watch the full class video and pass its quiz to earn a checkmark.</span></div>
            <progress data-bmb-total-progress max="<?php echo esc_attr( max( 1, count( $classes ) ) ); ?>" value="<?php echo esc_attr( $completed ); ?>" aria-label="Completed classes"><?php echo esc_html( $percent ); ?>%</progress>
            <?php if ( $next_id ) : ?><a class="bmb-text-link bmb-continue" data-bmb-continue href="<?php echo esc_url( add_query_arg( 'class', $next_id, $dashboard_url ) . '#bmb-class-title' ); ?>">Continue Learning →</a><?php endif; ?>
        </section>

        <?php if ( $selected ) : ?>
        <div class="bmb-class-layout">
            <section class="bmb-current-class" aria-labelledby="bmb-class-title" data-bmb-selected="<?php echo esc_attr( $selected_id ); ?>">
                <div class="bmb-class-heading">
                    <p class="bmb-eyebrow"><?php echo esc_html( $selected['topic']->post_title ); ?></p>
                    <h2 id="bmb-class-title"><?php echo esc_html( $selected['lesson']->post_title ); ?></h2>
                    <p class="bmb-class-label" data-bmb-completion-label><?php echo $state['complete'] ? 'Completed — review this class anytime.' : ( $selected_id === $next_id ? 'Your next class' : 'Class review' ); ?></p>
                </div>

                <?php if ( ! $state['accessible'] ) : ?>
                    <div class="bmb-video-notice"><span class="bmb-notice-icon" aria-hidden="true">↗</span><h3>This class requires course access</h3><p>Your current access includes the free preview classes. Continue through the existing enrollment process to open this class.</p><a class="bmb-button" href="<?php echo esc_url( $checkout ); ?>">View course access</a></div>
                <?php elseif ( $video['url'] ) : ?>
                    <video class="bmb-class-video" data-bmb-video="<?php echo esc_attr( $selected_id ); ?>" controls playsinline preload="metadata" poster="<?php echo esc_url( $video['poster'] ); ?>" aria-label="<?php echo esc_attr( $selected['lesson']->post_title ); ?>">
                        <source src="<?php echo esc_url( $video['url'] ); ?>" type="video/mp4">
                        <?php if ( $video['captions'] ) : ?><track kind="captions" src="<?php echo esc_url( $video['captions'] ); ?>" srclang="en" label="English"><?php endif; ?>
                        Your browser does not support this video. <a href="<?php echo esc_url( get_permalink( $selected_id ) ); ?>">Open the class</a>.
                    </video>
                <?php else : ?>
                    <div class="bmb-video-notice"><h3>This class video is not available yet</h3><p>You can review the class materials. Completion will become available when its video is configured.</p><a class="bmb-button bmb-button-secondary" href="<?php echo esc_url( get_permalink( $selected_id ) ); ?>">Open class materials</a></div>
                <?php endif; ?>

                <div class="bmb-class-requirements">
                    <?php if ( $state['grandfathered'] ) : ?>
                    <div><span class="bmb-step-number">✓</span><div><strong>Previously completed</strong><span>Your saved completion is preserved</span></div></div>
                    <div><span class="bmb-step-number">↗</span><div><strong>Review anytime</strong><span>No rewatch or quiz retake required</span></div></div>
                    <?php else : ?>
                    <div><span class="bmb-step-number" data-bmb-video-check><?php echo $state['video']['complete'] ? '✓' : '1'; ?></span><div><strong>Watch the full video</strong><span data-bmb-watch-label><?php echo esc_html( $state['video']['percent'] ); ?>% watched</span></div></div>
                    <div><span class="bmb-step-number"><?php echo $state['quiz_passed'] ? '✓' : '2'; ?></span><div><strong>Pass the class quiz</strong><span><?php echo $state['quiz_passed'] ? 'Passed' : 'Use the quiz’s required passing score'; ?></span></div></div>
                    <?php endif; ?>
                </div>
                <p class="bmb-save-status" data-bmb-save-status role="status" aria-live="polite"><?php echo $state['grandfathered'] ? 'Your previous class completion is honored. Reviewing this class is optional.' : 'Progress saves automatically while you watch. Seeking ahead does not complete skipped sections.'; ?></p>
                <div class="bmb-class-actions">
                    <?php if ( $state['accessible'] && $selected['quizzes'] ) : ?>
                        <?php foreach ( $selected['quizzes'] as $quiz_id ) : ?>
                            <?php if ( bmb_student_can_access( $quiz_id, $user->ID ) ) : ?><a class="bmb-button" href="<?php echo esc_url( get_permalink( $quiz_id ) ); ?>"><?php echo $state['quiz_passed'] || $state['grandfathered'] ? 'Review quiz' : 'Take class quiz'; ?> <span aria-hidden="true">→</span></a><?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ( $state['accessible'] ) : ?><span class="bmb-muted">The quiz for this class is not available yet.</span><?php endif; ?>
                    <?php if ( $next_id && $selected_id !== $next_id ) : ?><a class="bmb-text-link" href="<?php echo esc_url( add_query_arg( 'class', $next_id, $dashboard_url ) ); ?>">Continue Learning →</a><?php endif; ?>
                </div>
            </section>

            <aside class="bmb-resources" aria-labelledby="bmb-resources-title">
                <p class="bmb-eyebrow">FOR THIS CLASS</p><h2 id="bmb-resources-title">Your learning materials</h2>
                <?php if ( $state['accessible'] ) : ?>
                    <?php $resources = bmb_student_resources( $selected_id ); ?>
                    <?php if ( $resources ) : ?><ul><?php foreach ( $resources as $resource ) : ?><li><span class="bmb-resource-type"><?php echo esc_html( $resource['type'] ); ?></span><a href="<?php echo esc_url( $resource['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $resource['title'] ); ?> <span aria-hidden="true">↗</span></a></li><?php endforeach; ?></ul>
                    <?php else : ?><p class="bmb-muted">No additional resources are attached to this class.</p><?php endif; ?>
                    <a class="bmb-text-link" href="<?php echo esc_url( get_permalink( $selected_id ) ); ?>">Read class instructions →</a>
                <?php else : ?><p>Class materials become available with course access.</p><?php endif; ?>
                <div class="bmb-help-note"><strong>Learn at your own pace.</strong><p>Completed classes stay in your syllabus so you can return to them whenever your course access allows.</p></div>
            </aside>
        </div>
        <?php else : ?><section class="bmb-video-notice"><h2>Your classes are being prepared</h2><p>Your course syllabus will appear here when the classes are available.</p></section><?php endif; ?>

        <section id="bmb-syllabus" class="bmb-syllabus" aria-labelledby="bmb-syllabus-title">
            <div class="bmb-syllabus-heading"><div><p class="bmb-eyebrow">YOUR LEARNING PATH</p><h2 id="bmb-syllabus-title">Course syllabus</h2></div><div class="bmb-class-filters" role="group" aria-label="Filter classes"><button type="button" data-bmb-filter="all" aria-pressed="true">All classes</button><button type="button" data-bmb-filter="completed" aria-pressed="false">Completed</button></div></div>
            <p data-bmb-no-completed class="bmb-filter-empty" hidden>No completed classes yet. Watch a full class video and pass its quiz to get started.</p>
            <?php foreach ( $modules as $index => $module ) : ?>
                <?php $module_completed = count( array_filter( $module['classes'], static fn( $class ) => $states[ $class['lesson']->ID ]['complete'] ) ); ?>
                <details class="bmb-module" <?php echo isset( $module['classes'][ $selected_id ] ) ? 'open' : ''; ?> data-bmb-module>
                    <summary><span class="bmb-module-number"><?php echo esc_html( str_pad( $index + 1, 2, '0', STR_PAD_LEFT ) ); ?></span><span class="bmb-module-name"><?php echo esc_html( $module['topic']->post_title ); ?><small><span data-bmb-module-count><?php echo esc_html( $module_completed ); ?></span> of <?php echo esc_html( count( $module['classes'] ) ); ?> classes complete</small></span><span class="bmb-module-chevron" aria-hidden="true">⌄</span></summary>
                    <ol class="bmb-class-list">
                        <?php foreach ( $module['classes'] as $id => $class ) : ?>
                            <?php $s = $states[ $id ]; $label = $s['complete'] ? 'Completed' : ( ! $s['accessible'] ? 'Access required' : ( $s['video']['percent'] > 0 || $s['quiz_passed'] ? 'In progress' : 'Not started' ) ); ?>
                            <li data-bmb-class="<?php echo esc_attr( $id ); ?>" data-complete="<?php echo $s['complete'] ? 'true' : 'false'; ?>" class="<?php echo $selected_id === $id ? 'bmb-class-current' : ''; ?>">
                                <span class="bmb-class-indicator <?php echo $s['complete'] ? 'is-complete' : ''; ?>" aria-hidden="true"><?php echo $s['complete'] ? '✓' : '○'; ?></span>
                                <a href="<?php echo esc_url( add_query_arg( 'class', $id, $dashboard_url ) ); ?>" <?php echo $selected_id === $id ? 'aria-current="true"' : ''; ?>><strong><?php echo esc_html( $class['lesson']->post_title ); ?></strong><span data-bmb-class-detail><?php if ( $s['grandfathered'] ) : ?>Previous completion preserved<?php else : ?><?php echo esc_html( $s['video']['percent'] ); ?>% watched · <?php echo $s['quiz_passed'] ? 'Quiz passed' : 'Quiz to pass'; ?><?php endif; ?></span></a>
                                <span class="bmb-class-status" data-bmb-class-status><?php echo esc_html( $label ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </details>
            <?php endforeach; ?>
        </section>
    </div>
</main>
<?php get_footer(); ?>
