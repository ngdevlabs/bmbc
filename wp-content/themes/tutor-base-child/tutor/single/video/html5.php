<?php
/**
 * Display Video HTML5
 *
 * @package Tutor\Templates
 * @subpackage Single\Video
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video_info       = tutor_utils()->get_video_info();
$video_info_array = $video_info ? (array) $video_info : array();

$poster     = tutor_utils()->avalue_dot( 'poster', $video_info_array );
$poster_url = $poster ? wp_get_attachment_url( $poster ) : '';
$video_url  = ( $video_info && $video_info->source_video_id ) ? wp_get_attachment_url( $video_info->source_video_id ) : null;

do_action( 'tutor_lesson/single/before/video/html5' );
?>

<?php if ( $video_url ) : ?>
	<?php
	$available_vtts = array();
	$user_lang = 'en';
	$lang_names = array(
		'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 
		'de' => 'German', 'it' => 'Italian', 'pt' => 'Portuguese', 
		'hi' => 'Hindi', 'ar' => 'Arabic'
	);

	if ( $video_info && $video_info->source_video_id ) {
		$options = get_option( 'bmb_vtt_gen_settings' );
		$active_languages = isset($options['bmb_vtt_gen_languages']) ? (array) $options['bmb_vtt_gen_languages'] : array('en', 'es', 'fr', 'de');
		
		// Detect user language from GTranslate cookie, URL parameter, or WP locale
		if ( ! empty( $_COOKIE['googtrans'] ) ) {
			$parts = explode( '/', trim( $_COOKIE['googtrans'], '/' ) );
			$user_lang = strtolower( end( $parts ) );
		} elseif ( ! empty( $_COOKIE['gtranslate_lang'] ) ) {
			$user_lang = strtolower( $_COOKIE['gtranslate_lang'] );
		} elseif ( isset( $_GET['lang'] ) ) {
			$user_lang = strtolower( sanitize_text_field( $_GET['lang'] ) );
		} else {
			$user_lang = strtolower( substr( get_locale(), 0, 2 ) );
		}

		foreach ($active_languages as $l) {
			$v_url = get_post_meta( $video_info->source_video_id, 'vtt_caption_' . $l, true );
			if ($v_url) {
				$available_vtts[$l] = $v_url;
			}
		}

		// If user's language VTT doesn't exist, fall back to English
		if ( !isset($available_vtts[$user_lang]) ) {
			$user_lang = 'en';
		}
	}

	// Pass available langs as JSON for JS to use
	$available_langs_json = json_encode( $available_vtts );
	?>

	<div class="tutor-video-player notranslate" translate="no">
		<input type="hidden" id="tutor_video_tracking_information" value="<?php echo esc_attr( json_encode( $jsonData ?? null ) ); ?>">
		<div class="loading-spinner" aria-hidden="true"></div>
		<video poster="<?php echo esc_url( $poster_url ); ?>" class="tutorPlayer" playsinline controls>
			<source src="<?php echo esc_url( $video_url ); ?>" type="<?php echo esc_attr( tutor_utils()->avalue_dot( 'type', $video_info_array ) ); ?>">
			<?php
			// Output all available VTT tracks — mark correct one as default
			foreach ( $available_vtts as $lang_code => $vtt_url ) {
				$label = isset($lang_names[$lang_code]) ? $lang_names[$lang_code] : strtoupper($lang_code);
				$is_default = ( $lang_code === $user_lang ) ? 'default' : '';
				echo '<track kind="captions" src="' . esc_url( $vtt_url ) . '" srclang="' . esc_attr($lang_code) . '" label="' . esc_attr($label) . '" ' . $is_default . '>';
			}
			?>
		</video>
	</div>

	<script>
	(function() {
		// Map of all available generated VTTs for this video: { "es": "url", "de": "url", ... }
		var availableVtts = <?php echo $available_langs_json; ?>;

		function getCookie(name) {
			var value = "; " + document.cookie;
			var parts = value.split("; " + name + "=");
			if (parts.length === 2) return parts.pop().split(";").shift();
			return null;
		}

		function getActiveLang() {
			var googtrans = getCookie('googtrans');
			if (googtrans) {
				var p = googtrans.split('/');
				return p[p.length - 1].toLowerCase();
			}
			var gt = getCookie('gtranslate_lang');
			if (gt) return gt.toLowerCase();
			var htmlLang = document.documentElement.lang || 'en';
			return htmlLang.substring(0, 2).toLowerCase();
		}

		function selectTrack(video, targetLang) {
			if (!video || !video.textTracks) return;
			// Determine target: prefer targetLang VTT, else fallback to 'en'
			var finalLang = availableVtts[targetLang] ? targetLang : 'en';

			for (var i = 0; i < video.textTracks.length; i++) {
				var track = video.textTracks[i];
				track.mode = (track.language === finalLang) ? 'showing' : 'hidden';
			}

			// Also update Plyr if it has initialized
			if (video.plyr) {
				try { video.plyr.language = finalLang; } catch(e) {}
			}
		}

		var lastLang = null;

		function init() {
			var currentLang = getActiveLang();
			lastLang = currentLang;

			var video = document.querySelector('video.tutorPlayer');
			if (!video) return;

			// Poll until Plyr is ready, then activate correct track
			var attempts = 0;
			var plyrReady = setInterval(function() {
				attempts++;
				var tracks = video.textTracks;
				var plyrLoaded = video.plyr || (tracks && tracks.length > 0 && tracks[0].mode !== 'disabled');

				if (plyrLoaded || attempts > 20) {
					clearInterval(plyrReady);
					selectTrack(video, currentLang);
				}
			}, 200);

			// Watch for GTranslate live language changes
			var observer = new MutationObserver(function() {
				var nowLang = getActiveLang();
				if (nowLang !== lastLang) {
					lastLang = nowLang;
					selectTrack(video, nowLang);
				}
			});
			observer.observe(document.documentElement, {
				attributes: true,
				attributeFilter: ['lang', 'class']
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
	})();
	</script>
<?php endif; ?>

<?php do_action( 'tutor_lesson/single/after/video/html5' ); ?>
