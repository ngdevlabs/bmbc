<?php
/** A labeled, persistent class notebook download. */
defined( 'ABSPATH' ) || exit;
global $tutor_current_content_id;
$id = (int) $tutor_current_content_id;
if ( ! function_exists( 'bmb_student_notebook' ) || ! bmb_student_class( $id ) ) {
    include tutor()->path . 'templates/learning-area/lesson/exercise-files.php';
    return;
}
$book = bmb_student_notebook( $id );
$downloaded = bmb_student_notebook_downloaded( $id, get_current_user_id() );
?>
<div x-show="activeTab === 'exercise_files'" x-cloak class="tutor-tab-panel" role="tabpanel">
    <div class="tutor-lesson-exercise-files tutor-p-6">
        <h5 class="tutor-h5 tutor-mb-4">Class Notebook</h5>
        <?php if ( $book ) : ?>
            <a class="bmb-notebook-download bmb-notebook-button" data-bmb-notebook="<?php echo esc_attr( $id ); ?>" data-downloaded="<?php echo $downloaded ? 'true' : 'false'; ?>" href="<?php echo esc_url( bmb_student_notebook_url( $id ) ); ?>" download><span aria-hidden="true">↓</span> Download Class Notebook (PDF)</a>
            <input type="checkbox" hidden disabled data-bmb-notebook-check="<?php echo esc_attr( $id ); ?>" <?php checked( $downloaded ); ?> aria-label="Notebook download recorded">
            <p class="bmb-notebook-status" data-bmb-notebook-status="<?php echo esc_attr( $id ); ?>"><?php echo $downloaded ? '✓ Notebook download recorded. You can download it again anytime.' : 'Required: download this class notebook before taking the quiz.'; ?></p>
        <?php else : ?><p>The class notebook is not available. Please contact us for help.</p><?php endif; ?>
    </div>
</div>
