<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BeaverBuilder extends Singleton {

	/**
	 * @var string
	 */
	public static $minimum_version = '2.7.0';

	/**
	 * @var array
	 */
	public $initiated_widgets = [];

	/**
	 * @var array
	 */
	public $excluded_widgets = [];

	/**
	 * ElementorBuilder constructor
	 */
	public function __construct() {
		if ( ! defined( 'FL_BUILDER_VERSION' ) ) {
			add_action( 'admin_notices', [ $this, 'not_installed_notice' ] );

			return;
		}

		// Check for the minimum required Elementor version.
		if ( ! version_compare( FL_BUILDER_VERSION, self::$minimum_version, '>=' ) ) {
			add_action( 'admin_notices', [ $this, 'required_min_version_notice' ] );

			return;
		}

		add_filter( 'fl_builder_custom_fields', [ $this, 'register_custom_fields' ] );

		require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'beaver/GeneralVisibility.php';

		add_action(
			'init',
			function() {
				\FLBuilder::register_settings_form(
					'stax_visibility',
					[
						'title'    => __( 'Stax Visibility', 'visibility-logic-elementor' ),
						'sections' => [],
					]
				);
			},
			1
		);
	}

	/**
	 * Load settings
	 *
	 * @return void
	 */
	public function load_settings() {
		$widgets = Resources::get_all_widget_options();

		foreach ( $widgets as $slug => $option ) {
			if ( ! isset( $option['status'], $option['class'], $option['pro'] ) ||
				 ! $option['status'] ||
				 false !== $option['pro'] ) {
				continue;
			}

			$class_path = STAX_VISIBILITY_CORE_SETTINGS_PATH . "beaver/{$option['class']}.php";

			if ( ! file_exists( $class_path ) ) {
				continue;
			}

			require_once $class_path;
		}

		add_filter(
			'fl_builder_register_module_settings_form',
			function( $form, $slug ) use ( $widgets ) {
				$stax_form                        = \FLBuilderModel::$settings_forms['stax_visibility'];
				$stax_form['sections']['general'] = \Stax\VisibilityLogic\Beaver\GeneralVisibility::instance()->register_controls();

				foreach ( $widgets as $slug => $option ) {
					if ( ! isset( $option['status'], $option['class'], $option['pro'] ) ||
						! $option['status'] ||
						false !== $option['pro'] ) {
						continue;
					}

					$class_path = STAX_VISIBILITY_CORE_SETTINGS_PATH . "beaver/{$option['class']}.php";

					if ( ! file_exists( $class_path ) ) {
						continue;
					}

					$class_name = "\Stax\VisibilityLogic\Beaver\\{$option['class']}";

					$stax_form['sections'][ $slug ] = $class_name::instance()->register_controls();
				}

				$form['stax_visbility'] = $stax_form;

				return $form;
			},
			10,
			2
		);

		do_action( 'stax/visibility/after/beaver/load_settings' );
	}

	/**
	 * Register custom fields
	 *
	 * @param array $fields
	 * @return array
	 */
	public function register_custom_fields( $fields ) {
		$fields['select2'] = STAX_VISIBILITY_CORE_HELPERS_PATH . 'beaver/fields/views/select-field.php';

		return $fields;
	}

	/**
	 * Not installed notice
	 *
	 * @return void
	 */
	public function not_installed_notice() {
		$class = 'notice notice-warning';
		/* translators: %s: html tags */
		$message = sprintf( __( '%1$sVisibility Logic%2$s is enabled for %1$sBeaver Builder%2$s but the %1$sBeaver Builder%2$s plugin is not installed or activated.', 'visibility-logic-elementor' ), '<strong>', '</strong>' );

		$plugin = 'beaver-builder-lite-version/fl-builder.php';

		if ( Resources::instance()->is_plugin_installed( $plugin ) ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$action_url   = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
			$button_label = __( 'Activate Beaver Builder', 'visibility-logic-elementor' );

		} else {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}

			$action_url   = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );
			$button_label = __( 'Install Beaver Builder', 'visibility-logic-elementor' );
		}

		$button = '<p><a href="' . $action_url . '" class="button-primary">' . $button_label . '</a></p><p></p>';

		printf( '<div class="%1$s"><p>%2$s</p>%3$s</div>', esc_attr( $class ), $message, $button );
	}

	/**
	 * Required minimum version notice
	 *
	 * @return void
	 */
	public function required_min_version_notice() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		if ( isset( $_GET['activate'] ) ) { // WPCS: CSRF ok, input var ok.
			unset( $_GET['activate'] ); // WPCS: input var ok.
		}

		$message = sprintf(
			'<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">'
			/* translators: 1: Plugin name 2: Beaver Builder */
			. esc_html__( '%1$s is enabled for %2$s but it requires version %3$s or greater of %2$s plugin.', 'visibility-logic-elementor' )
			. '</span>',
			'<strong>' . __( 'Visibility Logic', 'visibility-logic-elementor' ) . '</strong>',
			'<strong>' . __( 'Beaver Builder', 'visibility-logic-elementor' ) . '</strong>',
			self::$minimum_version
		);

		$file_path   = 'beaver-builder-lite-version/fl-builder.php';
		$update_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );

		$message .= sprintf(
			'<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">' .
			'<a class="button-primary" href="%1$s">%2$s</a></span>',
			$update_link,
			__( 'Update Beaver Builder Now', 'visibility-logic-elementor' )
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $message );
	}
}
