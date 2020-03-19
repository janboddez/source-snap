<?php
/**
 * Deals with all things options.
 *
 * @package Source_Snap
 */

namespace Source_Snap;

/**
 * Deals with all things options.
 */
class Options_Handler {
	/**
	 * Default options array.
	 *
	 * @var array $options Default options array.
	 */
	const DEFAULT_OPTIONS = array(
		'tinify_enabled' => false,
		'tinify_api_key' => '',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
	}

	/**
	 * Registers the plugin settings page.
	 */
	public function create_menu() {
		add_options_page(
			__( 'Source Snap', 'source-snap' ),
			__( 'Source Snap', 'source-snap' ),
			'manage_options',
			'source-snap-settings-page',
			array( $this, 'settings_page' )
		);
		add_action( 'admin_init', array( $this, 'add_settings' ) );
	}

	/**
	 * Registers the actual options.
	 */
	public function add_settings() {
		register_setting(
			'source-snap-settings-group',
			'source_snap_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	/**
	 * Sanitizes submitted options.
	 *
	 * @param array $settings Settings as submitted through WP Admin.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $settings ) {
		$options = get_option( 'source_snap_settings', array() );

		foreach ( self::DEFAULT_OPTIONS as $key => $value ) {
			if ( 'tinify_enabled' === $key ) {
				if ( isset( $settings[ $key ] ) && '1' === $settings[ $key ] ) {
					$options[ $key ] = true;
				} else {
					$options[ $key ] = false;
				}
			} elseif ( isset( $settings[ $key ] ) ) {
				// Only update if set.
				$options[ $key ] = sanitize_text_field( $settings[ $key ] );
			}
		}

		return $options;
	}

	/**
	 * Echoes the plugin options form.
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Source Snap', 'source-snap' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				$options = get_option( 'source_snap_settings', $this->default_options );

				// Print nonces and such.
				settings_fields( 'source-snap-settings-group' );
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Tinify?', 'source-snap' ); ?></th>
						<td>
							<label><input type="checkbox" name="source_snap_settings[tinify_enabled]" value="1" <?php checked( $options['tinify_enabled'] ); ?> /> <?php esc_html_e( 'Crush code snaps using TinyPNG&rsquo;s API.', 'source-snap' ); ?></label>
							<?php /* translators: %s: URL of the TinyPNG developers site */ ?>
							<p class="description"><?php printf( esc_html__( 'May slow down publishing, but usually results in much smaller file sizes. Requires you sign up for a free <a href="%s" target="_blank" rel="noopener">API account</a>.', 'source-snap' ), 'https://tinypng.com/developers' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="source_snap_settings[tinify_api_key]"><?php esc_html_e( 'TinyPNG API Key', 'source-snap' ); ?></label></th>
						<td><input type="text" id="source_snap_settings[tinify_api_key]" name="source_snap_settings[tinify_api_key]" style="min-width: 50%;" value="<?php echo esc_attr( $options['tinify_api_key'] ); ?>" /></td>
					</tr>
				</table>

				<p class="submit"><?php submit_button( __( 'Save Changes', 'source-snap' ), 'primary', 'submit', false ); ?></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns default options.
	 *
	 * @return array Default options.
	 */
	public function get_default_options() {
		return $this->default_options;
	}
}
