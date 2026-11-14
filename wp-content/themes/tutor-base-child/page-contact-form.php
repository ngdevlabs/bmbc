<?php
/**
 * Template Name: Contact Form
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main class="bmb-contact-main">
    <div class="bmb-portal-container"><?php if ( function_exists( 'bmb_student_navigation' ) ) { bmb_student_navigation(); } ?></div>
    <section class="bmb-contact-card" aria-labelledby="contact-title">
        <h1 id="contact-title">Contact Us</h1>
        <p>Have a question? Leave your details and message below.</p>
        <!-- Add the form provider's endpoint to action and enable the submit button when ready. -->
        <form class="bmb-contact-form" action="" method="post" aria-describedby="contact-status">
            <div>
                <label for="contact-name">Name</label>
                <input id="contact-name" name="name" type="text" autocomplete="name" required>
            </div>
            <div>
                <label for="contact-email">Email</label>
                <input id="contact-email" name="email" type="email" autocomplete="email" required>
            </div>
            <div>
                <label for="contact-subject">Subject</label>
                <input id="contact-subject" name="subject" type="text" required>
            </div>
            <div>
                <label for="contact-message">Message</label>
                <textarea id="contact-message" name="message" rows="6" required></textarea>
            </div>
            <p id="contact-status">This form is not accepting messages yet.</p>
            <button type="submit" disabled>Send Message</button>
        </form>
    </section>
</main>
<?php get_footer(); ?>
