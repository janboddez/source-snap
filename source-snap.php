<?php
/**
 * Plugin Name: Source Snap
 * Description: Automatically generate Featured Images from source code snippets.
 * Author:      Jan Boddez
 * Author URI:  https://janboddez.tech/
 * License: GNU General Public License v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Version:     0.3
 *
 * @package Source_Snap
 */

namespace Source_Snap;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;
use Highlight\Highlighter;

/**
 * Main plugin class.
 */
class Source_Snap {
	/**
	 * Handles plugin settings.
	 *
	 * @var Options_Handler $options_handler
	 */
	private $options_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options_handler = new Options_Handler();
	}

	/**
	 * Registers callback functions.
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'update_meta' ), 11, 2 );
		add_action( 'publish_post', array( $this, 'create_thumbnail' ), 999, 2 );
	}

	/**
	 * Registers a new meta box.
	 */
	public function add_meta_box() {
		add_meta_box(
			'source-snap',
			__( 'Source Snap', 'source-snap' ),
			array( $this, 'render_meta_box' ),
			array( 'post' ),
			'advanced',
			'default'
		);
	}

	/**
	 * Renders custom fields meta boxes on the custom post type edit page.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public function render_meta_box( $post ) {
		?>
			<?php wp_nonce_field( basename( __FILE__ ), 'source_snap_nonce' ); ?>
			<table style="width: 100%;">
				<tr valign="top">
					<th style="width: 10%; text-align: right; font-weight: normal;"><label for="source_snap_lang" style="margin-right: 1em;"><?php esc_html_e( 'Language', 'source-snap' ); ?></label></th>
					<td><input type="text" name="source_snap_lang" id="source_snap_lang" value="<?php echo esc_attr( get_post_meta( $post->ID, '_source_snap_lang', true ) ); ?>" class="widefat" /></td>
				</tr>
				<tr valign="top">
					<th style="width: 10%; text-align: right; font-weight: normal;"><label for="source_snap_code" style="margin-right: 1em;"><?php esc_html_e( 'Code Snippet', 'source-snap' ); ?></label></th>
					<td><textarea name="source_snap_code" id="source_snap_code" rows="8" class="widefat" style="font: 13px/1.5 Consolas, Monaco, monospace;"><?php echo esc_html( get_post_meta( $post->ID, '_source_snap_code', true ) ); ?></textarea></td>
				</tr>
			</table>
		<?php
	}

	/**
	 * Handles metadata.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Corresponding post object.
	 */
	public function update_meta( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['source_snap_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['source_snap_nonce'] ), basename( __FILE__ ) ) ) {
			// Nonce missing or invalid.
			return;
		}

		if ( ! empty( $_POST['source_snap_lang'] ) ) {
			update_post_meta( $post->ID, '_source_snap_lang', sanitize_text_field( wp_unslash( $_POST['source_snap_lang'] ) ) );
		}

		if ( ! empty( $_POST['source_snap_code'] ) ) {
			update_post_meta( $post->ID, '_source_snap_code', sanitize_textarea_field( wp_unslash( $_POST['source_snap_code'] ) ) );
		}
	}

	/**
	 * Whenever a post is published through WP Admin, creates a new Featured
	 * Image based on whatever text (code, presumably) is in the
	 * `source_snap_snippet` custom field.
	 *
	 * Existing images will not be recreated.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_POST $post    Corresponding post object.
	 */
	public function create_thumbnail( $post_id, $post ) {
		// We currently have a hard requirement for Imagick.
		if ( ! class_exists( 'Imagick' ) ) {
			// Imagick not supported. Bail.
			return;
		}

		if ( has_post_thumbnail( $post_id ) ) {
			// Post already has a Featured Image. Bail.
			return;
		}

		// Get the 'current', i.e., this month's, WordPress upload dir.
		$wp_upload_dir = wp_upload_dir();

		// File path, without extension. The actual filename is equal to the
		// post slug and thus unique.
		$filename = trailingslashit( $wp_upload_dir['path'] ) . $post->post_name;

		if ( is_file( $filename . '-min.png' ) || is_file( $filename . '.png' ) ) {
			// File already exists. Leave it to the post author to set it as the
			// post's Featured Image.
			return;
		}

		// Fetch the code snippet and language, if present, to be used.
		$code = get_post_meta( $post_id, '_source_snap_code', true );
		$lang = get_post_meta( $post_id, '_source_snap_lang', true );

		if ( empty( $code ) ) {
			// Nothing to do.
			return;
		}

		// Handle possibly pre-encoded strings.
		$code = html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		try {
			$hl = new Highlighter();

			// Highlight that code. This'll add `span` tags that can be styled
			// with CSS, and encode HTML entities.
			if ( ! empty( $lang ) ) {
				// Try the specified language.
				$highlighted = $hl->highlight( $lang, $code );
			} else {
				// Give autodetect a try.
				$hl->setAutodetectLanguages( array( 'php', 'javascript', 'css', 'bash', 'html', 'apache', 'yaml', 'nginx' ) );
				$highlighted = $hl->highlightAuto( $code );
			}

			// Wrap the highlighted code in `pre` tags.
			$code = '<pre class="hljs ' . $highlighted->language . '">' . $highlighted->value . '</pre>';
		} catch ( \Exception $e ) {
			// Something went wrong. Bail.
			error_log( $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return;
		}

		// Windows and (older) Mac to Linux newline conversion.
		$code = str_replace( "\r\n", "\n", $code );
		$code = str_replace( "\r", "\n", $code );

		// Adding a non-breaking space to empty lines forces Dompdf to have them
		// occupy the same height (!) as non-empty lines.
		$code = str_replace( "\n\n", '<br>&nbsp;<br>', $code );

		// Force line breaks in order to prevent, well, more random Dompdf
		// behavior.
		$code = str_replace( "\n", '<br>', $code );

		// Start output buffering.
		ob_start();

		// Render our 'template' (a very simple HTML page with some very simple
		// inline CSS).
		require dirname( __FILE__ ) . '/templates/snap.php';

		// Fetch the outcome.
		$html = ob_get_clean();

		// Convert to PDF at 72 PPI.
		$options = new Options();
		$options->setDpi( 72 );

		// This works around an issue with whitespace at the start of a line.
		$options->set( 'enable_html5_parser', true );
		$dompdf = new Dompdf( $options );

		// Set the 'viewport' really, really wide to prevent random line breaks.
		$dompdf->setPaper( array( 0, 0, 999, 3999 ), 'landscape' ); // Pixels, thanks to the 72 PPI above.
		$dompdf->loadHtml( $html );

		// Render HTML as PDF.
		$dompdf->render();

		global $wp_filesystem;

		if ( null === $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';

			WP_Filesystem();
		}

		// Dump to intermediate PDF file.
		$output = $dompdf->output();
		$wp_filesystem->put_contents( $filename . '.pdf', $output );

		$im = new \Imagick();

		// Render at double the resolution. Leads to way better kerning.
		$im->setResolution( 144, 144 );

		// Rasterize only the first page, and convert to PNG.
		$im->readimage( $filename . '.pdf[0]' );
		$im->setImageFormat( 'png' );

		$width  = $im->getImageWidth();
		$height = $im->getImageHeight();

		// Resize down and sharpen a bit.
		$im->resizeImage( $width / 2 - 1, $height / 2 - 1, \Imagick::FILTER_CATROM, 0.69 ); // The 'minus 1 pixel' results in somewhat crispier text.

		// Trim away the transparent border. The resulting image is purely text
		// on a transparent background.
		$im->trimImage( 0.3 );
		$im->setImagePage( 0, 0, 0, 0 );

		// Get updated height, and add some bottom padding.
		$height = $im->getImageHeight() + 5;

		if ( $height > 560 ) {
			// Ensure the final composited image isn't taller than 800px.
			$height = 560;
		} elseif ( $height < 160 ) {
			// Or shorter than 400px.
			$height = 160;
		}

		// Round up to a multiple of 80. As `$width` is _always_ equal to 1600,
		// this limits the number of possible aspect ratios, which sometimes
		// comes in handy when lazy loading is applied.
		$height = $this->round_up_to_any( $height, 80 );

		// Load our mockup image.
		$background = new \Imagick();
		$background->readImage( dirname( __FILE__ ) . '/assets/images/background.png' );

		// Place the rendered text on top.
		$background->compositeImage( $im, \Imagick::COMPOSITE_DEFAULT, 82, 159 );
		$im->clear();
		$im->destroy();

		// Crop to final size.
		$background->cropImage( 1800, $height + 240, 0, 0 );
		$background->setImagePage( 0, 0, 0, 0 );

		// 'Fade out' any overflow on the right and bottom sides by compositing
		// pre-rendered PNGs on top of the main image.
		$right = new \Imagick();
		$right->readImage( dirname( __FILE__ ) . '/assets/images/right.png' );

		// Lay the right image on top of what we've got so far.
		$background->compositeImage( $right, \Imagick::COMPOSITE_DEFAULT, 1500, 0 );

		// Destroy image buffer.
		$right->clear();
		$right->destroy();

		$bottom = new \Imagick();
		$bottom->readImage( dirname( __FILE__ ) . '/assets/images/bottom.png' );

		// Lay the bottom image on top of what we've got so far.
		$background->compositeImage( $bottom, \Imagick::COMPOSITE_DEFAULT, 0, $height + 140 ); // 240, or 3 x 80, minus 100.

		// Destroy image buffer.
		$bottom->clear();
		$bottom->destroy();

		// Fetch plugin options.
		$options = get_option( 'source_snap_settings', $this->options_handler->get_default_options() );

		// If Tinify is enabled.
		if ( ! empty( $options['tinify_enabled'] ) && ! empty( $options['tinify_api_key'] ) ) {
			// Get the image buffer.
			ob_start();
			echo $background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$image_buffer = ob_get_clean();

			try {
				// Send the image data off to TinyPNG.
				\Tinify\setKey( $options['tinify_api_key'] );
				$result_data = \Tinify\fromBuffer( $image_buffer )->toBuffer();
				// Save the compressed image to disk.
				$wp_filesystem->put_contents( $filename . '-min.png', $result_data );
			} catch ( \Exception $e ) {
				// Something went wrong.
				error_log( $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

				// Save the 'original' image to disk instead.
				$background->writeImage( $filename . '.png' );
			}
		} else {
			// Save the 'original' image to disk.
			$background->writeImage( $filename . '.png' );
		}

		// Destroy the image buffer.
		$background->clear();
		$background->destroy();

		// Delete the intermediary PDF file.
		unlink( $filename . '.pdf' );

		// Tack the file extension onto the filename (file path, really).
		if ( is_file( $filename . '-min.png' ) ) {
			// Tinified image.
			$filename = $filename . '-min.png';
		} elseif ( is_file( $filename . '.png' ) ) {
			// 'Original' image.
			$filename = $filename . '.png';
		} else {
			// Something went wrong.
			return;
		}

		// Now import the image into WordPress' media library.
		$attachment = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => 'image/png',
			'post_title'     => $post->post_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $filename, $post_id );

		if ( ! function_exists( 'wp_crop_image' ) ) {
			// Load image functions.
			require ABSPATH . 'wp-admin/includes/image.php';
		}

		// Generate metadata. Generates thumbnails, too.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $filename );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Set as Featured Iamge.
		set_post_thumbnail( $post_id, $attachment_id );

		// Done!
	}

	/**
	 * Round up to the nearest multiple of a certain number.
	 *
	 * @link https://stackoverflow.com/questions/4133859/round-up-to-nearest-multiple-of-five-in-php
	 *
	 * @param int $n Number to be rounded up.
	 * @param int $x Round up to multiples of this number only.
	 *
	 * @return int The outcome.
	 */
	private function round_up_to_any( $n, $x = 5 ) {
		return ( 0 === ceil( $n ) % $x ? ceil( $n ) : round( ( $n + $x / 2 ) / $x ) * $x );
	}
}

$source_snap = new Source_Snap();
$source_snap->register();
