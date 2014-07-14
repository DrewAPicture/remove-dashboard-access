<?php
/**
 * Remove Dashboard Access Options Class
 *
 * @since 1.0
 */
if ( ! class_exists( 'RDA_Options' ) ) {
class RDA_Options {

	/**
	 * Static instance to make removing actions and filters modular.
	 *
	 * @since 1.1
	 * @access public
	 * @static
	 */
	public static $instance;

	/**
	 * @var $settings rda-settings options array
	 *
	 * @since 1.0
	 * @access public
	 */
	public $settings = array();

	/**
	 * Init
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		self::$instance = $this;

		$this->setup();
	}

	/**
	 * Set up various actions, filters, and other items.
	 *
	 * @since 1.1
	 * @access public
	 */
	public function setup() {
		load_plugin_textdomain( 'remove_dashboard_access', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Set up options array on activation.
//		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		$this->settings = array(
			'access_switch'  => get_option( 'rda_access_switch', 'manage_options' ),
			'access_cap'     => get_option( 'rda_access_cap',     'manage_options' ),
			'enable_profile' => get_option( 'rda_enable_profile', 1 ),
			'redirect_url'   => get_option( 'rda_redirect_url', home_url() ),
			'login_message'  => get_option( 'rda_login_message', __( 'This site is in maintenance mode.', 'remove_dashboard_access' ) ),
		);

		// Settings.
		add_action( 'admin_init',                     array( $this, 'settings'         ) );
		add_action( 'admin_head-options-reading.php', array( $this, 'access_switch_js' ) );

		// Settings link in plugins list.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ) );

		// Login message.
		add_filter( 'login_message', array( $this, 'output_login_message' ) );
	}

	/**
	 * Login Message option callback.
	 *
	 * @since 1.1
	 * @access public
	 */
	public function output_login_message( $message ) {
		if ( ! empty( $this->settings['login_message'] ) ) {
			$message .= '<p class="message">' . esc_html( $this->settings['login_message'] ) . '</p>';
		}
		return $message;
	}

	/**
	 * Activation Hook.
	 *
	 * Setup default options on activation.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @see $this->setup()
	 */
	public function activate() {
		$settings = array(
			'rda_access_switch'  => 'manage_options',
			'rda_access_cap'     => 'manage_options',
			'rda_redirect_url'   => home_url(),
			'rda_enable_profile' => 1,
			'rda_login_message'  => ''
		);

		foreach ( $settings as $key => $value ) {
			update_option( $key, $value );
		}
	}

	/**
	 * Register settings and settings sections.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @see $this->setup()
	 */
	public function settings() {
		add_settings_section( 'rda_options', __( 'Dashboard Access Controls', 'remove_dashbord_access' ), array( $this, 'settings_section' ), 'reading' );

		// Settings.
		$sets = array(
			'rda_access_switch'  => array(
				'label'    => __( 'Dashboard User Access:', 'remove_dashboard_access' ),
				'callback' => 'access_switch_cb',
			),
			'rda_access_cap'     => array(
				'label'    => '',
				'callback' => 'access_cap_dropdown',
			),
			'rda_redirect_url'   => array(
				'label'    => __( 'Redirect URL:', 'remove_dashboard_access' ),
				'callback' => 'url_redirect_cb',
			),
			'rda_enable_profile' => array(
				'label'    => __( 'User Profile Access:', 'remove_dashboard_access' ),
				'callback' => 'profile_enable_cb',
			),
			'rda_login_message'  => array(
				'label'    => __( 'Login Message', 'remove_dashboard_access' ),
				'callback' => 'login_message_cb',
			),
		);

		foreach ( $sets as $id => $settings ) {
			add_settings_field( $id, $settings['label'], array( $this, $settings['callback'] ), 'reading', 'rda_options' );

			// Pretty lame that we need separate sanitize callbacks for everything.
			$sanitize_callback = str_replace( 'rda', 'sanitize', $id );
			register_setting( 'reading', $id, array( $this, $sanitize_callback ) );
		};

		// Debug info.
		if ( ! empty( $_GET['rda_debug'] ) ) {
			add_settings_field( 'rda_debug_mode', __( 'Debug Info', 'remove_dashboard_access' ), array( $this, '_debug_mode' ), 'reading', 'rda_options' );
		}

	}

	/**
	 * Dashboard Access Controls display callback.
	 *
	 * @since 1.1
	 * @access public
	 */
	public function settings_section() {
		_e( 'Dashboard access can be restricted to users of certain roles only or users with a specific capability.', 'remove_dashboard_access' );
	}

	/**
	 * Access Controls 2 of 2.
	 *
	 * Output the capability drop-down.
	 *
	 * @since 1.1
	 * @access public
	 */
	public function access_cap_dropdown() {
		$switch = $this->settings['access_switch'];
		?>
		<p><label>
			<input name="rda_access_switch" type="radio" value="capability" class="tag" <?php checked( 'capability', esc_attr( $switch ) ); ?> />
			<?php _e( '<strong>Advanced</strong>: Limit by capability:', 'remove_dashboard_access' ); ?>
		</label><?php $this->_output_caps_dropdown(); ?></p>
		<p>
			<?php printf( __( 'You can find out more about specific %s in the Codex.', 'remove_dashboard_access' ),
				sprintf( '<a href="%1$s" target="_new">%2$s</a>',
					esc_url( 'http://codex.wordpress.org/Roles_and_Capabilities' ),
					esc_html( __( 'Roles &amp; Capabilities', 'remove_dashboard_access' ) )
				)
			); ?>
		</p>
	<?php
	}

	/**
	 * Capability-type radio switch jQuery script.
	 *
	 * When the 'Limit by capability' radio option is selected the script.
	 * enables the capabilities drop-down. Default state is disabled.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @see $this->setup()
	 */
	public function access_switch_js() {
		wp_enqueue_script( 'rda-settings', plugin_dir_url( __FILE__ ) . 'js/settings.js', array( 'jquery' ), '1.0' );
	}

	/**
	 * Enable/Disable radio toggle display callback
	 *
	 * @since 1.1
	 * @access public
	 *
	 * @see $this->options_setup()
	 */
	public function plugin_toggle_cb() {
		printf( '<input name="rda_toggle_plugin_off" type="checkbox" value="1" class="code" %1$s/>%2$s',
			checked( esc_attr( $this->settings['toggle_plugin_off'] ), true, false ),
			__( ' Disable access controls and redirection', 'remove_dashboard_access' )
		);
	}

	/**
	 * Capability-type radio switch display callback.
	 *
	 * Displays the radio button switch for choosing which
	 * capability users need to access the Dashboard. Mimics
	 * 'Page on front' UI in options-reading.php for a more
	 * integrated feel.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @see $this->options_setup()
	 *
	 * @uses checked() Activates the checked attribute on the selected option.
	 * @uses $this->caps_dropdown() Displays the capabilities dropdown paired with the second access switch radio option.
	 */
	public function access_switch_cb() {
		echo '<a name="dashboard-access"></a>';

		$switch = $this->settings['access_switch'];

		$defaults = apply_filters( 'rda_default_caps_for_role', array(
			'admin'  => 'manage_options',
			'editor' => 'edit_others_posts',
			'author' => 'publish_posts'
		) );
		?>
		<p><label>
			<input name="rda_access_switch" type="radio" value="<?php echo esc_attr( $defaults['admin'] ); ?>" class="tag" <?php checked( $defaults['admin'], esc_attr( $switch ) ); ?> />
			<?php _e( 'Administrators only', 'remove_dashboard_access' ); ?>
		</label></p>
		<p><label>
			<input name="rda_access_switch" type="radio" value="<?php echo esc_attr( $defaults['editor'] ); ?>" class="tag" <?php checked( $defaults['editor'], esc_attr( $switch ) ); ?> />
			<?php _e( 'Editors and Administrators', 'remove_dashboard_access' ); ?>
		</label></p>
		<p><label>
			<input name="rda_access_switch" type="radio" value="<?php echo esc_attr( $defaults['author'] ); ?>" class="tag" <?php checked( $defaults['author'], esc_attr( $switch ) ); ?> />
			<?php _e( 'Authors, Editors, and Administrators', 'remove_dashboard_access' ); ?>
		</label></p>

	<?php
	}


	/**
	 * Capability-type switch drop-down.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @see $this->access_switch_cb()
	 *
	 * @uses global $wp_roles to derive an array of capabilities.
	 */
	private function _output_caps_dropdown() {
		/** @global WP_Roles $wp_roles */
		global $wp_roles;

		$capabilities = array();
		foreach ( $wp_roles->role_objects as $key => $role ) {
			if ( is_array( $role->capabilities ) ) {
				foreach ( $role->capabilities as $cap => $grant )
					$capabilities[$cap] = $cap;
			}
		}

		// Gather legacy user levels.
		$levels = array(
			'level_0','level_1', 'level_2', 'level_3',
			'level_4', 'level_5', 'level_6', 'level_7',
			'level_8', 'level_9', 'level_10',
		);

		// Remove levels from caps array (Thank you Justin Tadlock).
		$capabilities = array_diff( $capabilities, $levels );

		// Remove # capabilities (maybe from some plugin, perhaps?).
		for ( $i = 0; $i < 12; $i++ ) {
			unset( $capabilities[$i] );
		}

		// Alphabetize for nicer display.
		ksort( $capabilities );

		if ( ! empty( $capabilities ) ) {
			// Start <select> element.
			print( '<select name="rda_access_cap">' );

			// Default first option.
			printf( '<option selected="selected" value="manage_options">%s</option>', __( '--- Select a Capability ---', 'removed_dashboard_access' ) );

			// Build capabilities dropdown.
			foreach ( $capabilities as $capability => $value ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $this->settings['access_cap'], $value ), esc_html( $capability ) );
			}
			print( '</select>' );
		}
	}

	/**
	 * Enable profile access checkbox display callback.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @see $this->options_setup()
	 *
	 * @uses checked() Outputs the checked attribute when the option is enabled.
	 */
	public function profile_enable_cb() {
		printf( '<input name="rda_enable_profile" type="checkbox" value="1" class="code" %1$s/>%2$s',
			checked( esc_attr( $this->settings['enable_profile'] ), true, false ),
			/* Translators: The leading space is intentional to space the text away from the checkbox */
			__( ' Allow all users to edit their profiles in the dashboard.', 'remove_dashboard_access' )
		);
	}

	/**
	 * Redirect URL display callback.
	 *
	 * Default value is home_url(). $this->sanitize_option() handles validation and escaping.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @see $this->options_setup()
	 */
	public function url_redirect_cb() {
		?>
		<p><label>
			<?php _e( 'Redirect disallowed users to:', 'remove_dashboard_access' ); ?>
			<input name="rda_redirect_url" class="regular-text" type="text" value="<?php echo esc_attr( $this->settings['redirect_url'] ); ?>" placeholder="<?php printf( esc_attr__( 'Default: %s', 'remove_dashboard_access' ), home_url() ); ?>" />
		</label></p>
		<?php
	}

	/**
	 * Login Message display callback.
	 *
	 * @since 1.1
	 * @access public
	 */
	public function login_message_cb() {
		?>
		<p><input name="rda_login_message" class="regular-text" type="text" value="<?php echo esc_attr( $this->settings['login_message'] ); ?>" placeholder="<?php esc_attr_e( '(Disabled when empty)', 'remove_dashboard_access' ); ?>" /></p>
		<?php
	}

	/**
	 * Access Switch sanitize callback.
	 *
	 * @since 1.1
	 * @access public
	 *
	 * @param string $option Access switch capability.
 	 * @return string Sanitized capability.
	 */
	public function sanitize_access_switch( $option ) {
		return $option;
	}

	/**
	 * Access capability sanitize callback.
	 *
	 * @since 1.1
	 * @access public
	 *
	 * @param string $option Access capability.
	 * @return string Sanitized capability. If the option is empty, default to the value of
	 *                'rda_access_switch'.
	 */
	public function sanitize_access_cap( $option ) {
		return empty( $option ) ? get_option( 'rda_access_switch' ) : $option;
	}

	/**
	 * Redirect URL sanitize callback.
	 *
	 * @since 1.1
	 * @access public
	 *
	 * @param string $option Redirect URL.
	 * @return string If empty, defaults to home_url(). Otherwise sanitized URL.
	 */
	public function sanitize_redirect_url( $option ) {
		return empty( $option ) ? home_url() : esc_url_raw( $option );
	}

	/**
	 * Enable Profile sanitize callback.
	 *
	 * @since 1.1
	 * @access public
	 *
	 * @param bool $option Whether to enable all users to edit their profiles.
	 * @return bool Whether all users will be able to edit their profiles.
	 */
	public function sanitize_enable_profile( $option ) {
		return (bool) empty( $option ) ? false : true;
	}

	/**
	 * Login Message sanitize callback.
	 *
	 * @since 1.1
	 * @access public
	 *
	 * @param string $option Login message.
	 * @return string Sanitized login message.
	 */
	public function sanitize_login_message( $option ) {
		return sanitize_text_field( $option );
	}

	/**
	 * Required capability for Dashboard access.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return string $this->settings['access_cap'] if set, otherwise, 'manage_options' (filterable).
	 */
	public function capability() {
		return $this->settings['access_cap'];
	}

	/**
	 * Plugins list 'Settings' row link.
	 *
	 * @since 1.0
	 *
	 * @see $this->setup()
	 *
	 * @param array $links Row links array to filter.
	 * @return array $links Filtered links array.
	 */
	public function settings_link( $links ) {
		return array_merge(
			array( 'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-reading.php#dashboard-access' ) ),
				esc_attr( __( 'Settings', 'remove_dashboard_access' ) )
			) ), $links
		);
	}

	/**
	 * RDA Debug Mode output
	 *
	 * Displays a var_dump of the RDA options array on the options page.
	 *
	 * Use the 'rda_debug_mode' filter to enable it:
	 * add_filter( 'rda_debug_mode', '__return_true' );
	 *
	 * @since 1.3
	 * @access private
	 */
	function _debug_mode() {
		?>
		<style type="text/css">
			table.rda_debug {
				width: 400px;
				border: 1px solid #222;
			}
			.rda_debug th {
				text-align: center;
			}
			.rda_debug th,
			.rda_debug td {
				width: 50%;
				padding: 15px 10px;
				border: 1px solid #222;
			}
		</style>
		<table class="rda_debug">
			<tbody>
				<tr>
					<th><?php _e( 'Setting', 'remove_dashboard_access' ); ?></th>
					<th><?php _e( 'Value', 'remove_dashboard_access' ); ?></th>
				</tr>
				<?php foreach ( $this->settings as $key => $value ) :
					$value = empty( $value ) ? __( 'empty', 'remove_dashboard_access' ) : $value;
					?>
					<tr>
						<td><?php echo esc_html( $key ); ?></td>
						<td><?php echo esc_html( $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

} // RDA_Options

} // class exists
