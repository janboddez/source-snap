<?php
/**
 * Plugin Name: Source Snap
 * Description: Automatically generate Featured Images from source code snippets.
 * Author: Jan Boddez
 * Author URI: https://janboddez.tech/
 * License: GNU General Public License v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Version: 0.1
 */

namespace Source_Snap;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Composer's autoloader.
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Highlight\Highlighter;

class Source_Snap {
	/**
	 * Handles plugin settings.
	 */
	private $options_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options_handler = new Options_Handler();

		// Register callback functions.
		add_action('publish_post', array( $this, 'create_thumbnail'), 10, 2);
	}

	/**
	 * Whenever a post is published through WP Admin, creates a new Featured
	 * Image based on the `source_snap_snippet` custom field.
	 *
	 * Existing images will not be recreated.
	 *
	 * @param int $post_id Post ID.
	 * @param WP_POST $post Corresponding post object.
	 */
	public function create_thumbnail( $post_id, $post ) {
		if ( ! class_exists( 'Imagick' ) ) {
			return;
		}

		// Get the 'current', i.e., this month's, WordPress upload dir.
		$wp_upload_dir = wp_upload_dir();

		// File path, without extension.
		$filename = trailingslashit( $wp_upload_dir['path'] ) . $post->post_name;

		if ( is_file( $filename . '-min.png' ) || is_file( $filename . '.png' ) ) {
			// File exists. Bail.
			return;
		}

		$hl = new Highlighter();
		$hl->setAutodetectLanguages( array( 'php', 'javascript', 'css', 'bash', 'html', 'apache' ) );

		// Fetch the code snippet to be used.
		$str = get_post_meta( $post_id, 'source_snap_snippet', true );

		if ( empty( $str ) ) {
			return;
		}

		// Handle possibly encoded strings.
		$str = html_entity_decode( $str, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		try {
			// Highlight that code.
			$highlighted = $hl->highlightAuto( $str );
			$str = '<pre class="hljs ' . $highlighted->language . '">' . $highlighted->value . '</pre>';
		} catch ( \DomainException $e ) {
			// Something went wrong.
			return;
		}

		// On to trying to make Dompdf comply...

		// Windows and (older) Mac to Linux newline conversion.
		$str = str_replace( "\r\n", "\n", $str );
		$str = str_replace( "\r", "\n", $str );
		// Add a space to empty lines in order to have them occupy the same
		// height (!) as non-empty lines.
		$str = str_replace( "\n\n", '<br>&nbsp;<br>', $str );
		// Force line breaks in order to prevent, well, random behavior.
		$str = str_replace( "\n", '<br>', $str );

		// Start output buffering.
		ob_start();
		// Render our 'template'.
		require dirname( __FILE__ ) . '/templates/snap.php';
		$html = ob_get_clean();

		// Convert to PDF at 72 PPI.
		$options = new Options();
		$options->setDpi( 72 );

		// This solves an issue with whitespace at the start of a line.
		$options->set( 'enable_html5_parser', true );
		$dompdf = new Dompdf( $options );

		// Set the 'viewport' really wide to prevent random line breaks.
		$dompdf->setPaper( array( 0, 0, 999, 3999 ), 'landscape' ); // Pixels, thanks to the 72 PPI above.
		$dompdf->loadHtml( $html );

		// Render HTML as PDF
		$dompdf->render();

		// Dump to intermediate PDF file.
		$output = $dompdf->output();
		file_put_contents( $filename . '.pdf', $output );

		$im = new \Imagick();

		// Render at double the resolution. Leads to way better kerning.
		$im->setResolution( 144, 144 );

		// Rasterize first page and convert to PNG.
		$im->readimage( $filename . '.pdf[0]' );
		$im->setImageFormat( 'png' );

		$width = $im->getImageWidth();
		$height = $im->getImageHeight();

		// Resize down and sharpen a bit. (Why the minus 1 pixel? A: Somewhat
		// crispier text.)
		$im->resizeImage( $width / 2 - 1, $height / 2 - 1, \Imagick::FILTER_CATROM, 0.69 );

		// Trim away the transparent border. The resulting image is purely text
		// on a transparent background.
		$im->trimImage( 0.3 );
		$im->setImagePage( 0, 0, 0, 0 );

		// Get updated height.
		$height = $im->getImageHeight();

		if ( $height > 544 ) {
			// Ensure the final composited image isn't taller than 800px.
			$height = 544;
		} elseif ( $height < 144 ) {
			// Or shorter than 400px.
			$height = 144;
		}

		// Load our mockup image and place the rendered text on top.
		$background = new \Imagick();
		$background->readImage( dirname( __FILE__ ) . '/assets/images/background.png' );

		$background->compositeImage( $im, \Imagick::COMPOSITE_DEFAULT, 82, 149 );
		$im->clear();
		$im->destroy();

		// Crop to final size.
		$background->cropImage( 1800, $height + 256, 0, 0 );
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
		$bottom->readImage( dirname( __FILE__ ) . '/assets/images/bottom.png');

		// Lay the bottom image on top of what we've got so far.
		$background->compositeImage( $bottom, \Imagick::COMPOSITE_DEFAULT, 0, $height + 256 - 100 );

		// Destroy image buffer.
		$bottom->clear();
		$bottom->destroy();

		// Fetch plugin options.
		$options = get_option( 'source_snap_settings', $this->options_handler->get_default_options() );

		// If Tinify is enabled.
		if ( ! empty( $options['tinify_enabled'] ) && ! empty( $options['tinify_api_key'] ) ) {
			// Get the image buffer.
			ob_start();
			echo $background;
			$imageBuffer = ob_get_clean();

			// Send the image data off to TinyPNG.
			\Tinify\setKey( $options['tinify_api_key'] );
			$resultData = \Tinify\fromBuffer( $imageBuffer )->toBuffer();

			// Save the compressed image to disk.
			file_put_contents( $filename . '-min.png', $resultData );
		} else {
			// Save the 'original' image to disk.
			$background->writeImage( $filename . '.png' );
		}

		// Destroy image buffer.
		$background->clear();
		$background->destroy();

		// Delete intermediary PDF file.
		unlink( $filename . '.pdf' );

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
			'guid' => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => 'image/png',
			'post_title' => basename( $filename ),
			'post_content' => '',
			'post_status' => 'inherit',
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
	}
}

new Source_Snap();
