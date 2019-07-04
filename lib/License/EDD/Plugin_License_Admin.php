<?php

namespace Barn2\Lib\License\EDD;

use Barn2\Lib\Link_Util;
use Barn2\Lib\Registerable;
use Barn2\Lib\Ajax_Listener;
use Barn2\Lib\Licensable_Plugin;
use Barn2\Lib\License\License;
use Barn2\Lib\License\License_Settings;
use Barn2\Lib\Admin\Plugin_Activation_Listener;

if ( ! \class_exists( __NAMESPACE__ . '\Plugin_License_Admin' ) ) {

	/**
	 * Admin functions for our plugin licensing system.
	 *
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 */
	class Plugin_License_Admin implements Registerable, License_Settings, Plugin_Activation_Listener, Ajax_Listener {

		const OVERRIDE_HASH	 = 'caf9da518b5d4b46c2ef1f9d7cba50ad';
		const ACTIVATED		 = 'activated';
		const EXPIRED			 = 'expired';
		const DISABLED		 = 'disabled';
		const SITE_MOVED		 = 'site_moved';

		/**
		 * @var Licensable_Plugin The plugin we're handling in the admin.
		 */
		private $plugin;

		/**
		 * @var License The plugin license.
		 */
		private $license;

		public function __construct( Licensable_Plugin $plugin, License $license ) {
			$this->plugin	 = $plugin;
			$this->license	 = $license;
		}

		public function register() {
			\add_action( 'admin_init', array( $this, 'process_license_action' ), 10 );
			\add_action( 'admin_init', array( $this, 'check_license' ), 20 );
			\add_action( 'admin_init', array( $this, 'add_notices' ), 30 );
			\add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );

			$this->register_activation_events();
			$this->register_ajax_events();
		}

		public function get_license_setting() {
			$setting = array(
				'title' => __( 'License key', 'woocommerce-product-table' ),
				'type' => 'text',
				'id' => $this->get_license_setting_name() . '[license]',
				'desc' => $this->get_license_description(),
				'class' => 'regular-text'
			);

			if ( $this->plugin->is_woocommerce() ) {
				$setting['desc_tip'] = __( 'The licence key is contained in your order confirmation email.', 'woocommerce-product-table' );
			}

			if ( $this->is_license_setting_readonly() ) {
				$setting['custom_attributes'] = array(
					'readonly' => 'readonly'
				);
			}

			return $setting;
		}

		public function get_license_override_setting() {
			$override = \filter_input( INPUT_GET, 'license_override', FILTER_SANITIZE_STRING );

			//@todo: Add hidden field to WP_Settings_API_Helper then both woocommerce and standard settings can return the same array.
			if ( $this->plugin->is_woocommerce() ) {
				return $override ? array(
					'type' => 'hidden',
					'id' => 'license_override',
					'default' => $override
					) : array();
			} else {
				return $override ? '<input type="hidden" name="license_override" value="' . esc_attr( $override ) . '" />' : '';
			}
		}

		/**
		 * Save the specified license key.
		 *
		 * If there is a valid key currently active, the current key will be deactivated first
		 * before activating the new one.
		 *
		 * @param string $license_key The license key to save.
		 * @return string The license key.
		 */
		public function save_license_setting( $license_key ) {
			// If the license key input is disabled, the submitted license key will be null on save.
			// If license is currently active it means we don't need to re-save, so bail early.
			if ( ! $license_key && $this->license->is_active() ) {
				return $license_key;
			}

			$license_key = \filter_var( $license_key, FILTER_SANITIZE_STRING );

			// Deactivate old license key first if it was valid.
			if ( $this->license->is_active() && $license_key !== $this->license->get_license_key() ) {
				$this->license->deactivate();
			}

			// If new license key is different to current key, or current key isn't active, attempt to activate.
			if ( $license_key !== $this->license->get_license_key() || ! $this->license->is_active() ) {
				$this->activate_license( $license_key );
			}

			$this->cleanup_transients();

			return $license_key;
		}

		public function get_license_setting_name() {
			return $this->license->get_setting_name();
		}

		public function register_activation_events() {
			// Register activation hook for 1st plugin activation.
			\register_activation_hook( $this->plugin->get_plugin_file(), array( $this, 'on_activate' ) );
		}

		public function on_activate() {
			\delete_transient( $this->get_notice_dismissed_transient_name( self::ACTIVATED ) );
		}

		public function on_deactivate() {
			// nothing on deactivation.
		}

		public function register_ajax_events() {
			$ajax_events = array(
				'barn2_dismiss_notice' => 'ajax_dismiss_notice'
			);

			foreach ( $ajax_events as $action => $handler ) {
				\add_action( 'wp_ajax_nopriv_' . $action, array( $this, $handler ) );
				\add_action( 'wp_ajax_' . $action, array( $this, $handler ) );
			}
		}

		public function ajax_dismiss_notice() {
			$item_id	 = \filter_input( INPUT_POST, 'id', FILTER_VALIDATE_INT );
			$notice_type = \filter_input( INPUT_POST, 'type', FILTER_SANITIZE_STRING );

			// Check data is valid.
			if ( ! $item_id || ! \in_array( $notice_type, array( 'activated', 'expired', 'disabled', 'site_moved' ) ) ) {
				\wp_die();
			}

			if ( $item_id === $this->plugin->get_item_id() ) {
				\set_transient( $this->get_notice_dismissed_transient_name( $notice_type ), true );
			}

			\wp_die();
		}

		/**
		 * Process a license action from the plugin license settings page (i.e. activate, deactivate or check license)
		 */
		public function process_license_action() {
			$cleanup = false;

			if ( $this->is_license_action( 'activate_key' ) ) {
				$license_setting = $this->get_license_setting_to_save();

				if ( isset( $license_setting['license'] ) ) {
					$activated = $this->activate_license( $license_setting['license'] );

					$this->add_settings_message(
						__( 'License key activated successfully.', 'woocommerce-product-table' ),
						__( 'There was an error activating your license key.', 'woocommerce-product-table' ),
						$activated
					);

					$cleanup = true;
				}
			} elseif ( $this->is_license_action( 'deactivate_key' ) ) {
				$deactivated = $this->license->deactivate();

				$this->add_settings_message(
					__( 'License key deactivated.', 'woocommerce-product-table' ),
					__( 'There was an error deactivating your license key, please try again.', 'woocommerce-product-table' ),
					$deactivated
				);

				$cleanup = true;
			} elseif ( $this->is_license_action( 'check_key' ) ) {
				$this->license->refresh();
				$cleanup = true;
			}

			if ( $cleanup ) {
				$this->cleanup_transients();
			}
		}

		public function check_license() {
			// Don't re-check if we've already set the 'site moved' transient.
			if ( \get_transient( $this->get_site_moved_transient_name() ) ) {
				return;
			}

			// Check if site has moved by comparing license URL to current URL. If it's moved, set transient and set license to inactive.
			if ( $this->license->is_active() && $this->license->get_active_url() !== \home_url() ) {
				$this->license->set_status( 'inactive' );

				\set_transient( $this->get_site_moved_transient_name(), true, MONTH_IN_SECONDS );
			}
		}

		public function add_notices() {
			// Don't add notices if we're about to save the settings.
			if ( $this->get_license_setting_to_save() ) {
				return;
			}

			if ( ! $this->license->exists() ) {
				// 'No license key' notice.
				$this->maybe_add_admin_notice( self::ACTIVATED, array( $this, 'first_activation_notice' ) );
			} elseif ( $this->license->is_expired() ) {
				// Expired license notice.
				$this->maybe_add_admin_notice( self::EXPIRED, array( $this, 'expired_license_notice' ) );
			} elseif ( $this->license->is_disabled() ) {
				// Disabled license notice.
				$this->maybe_add_admin_notice( self::DISABLED, array( $this, 'disabled_license_notice' ) );
			} elseif ( \get_transient( $this->get_site_moved_transient_name() ) && $this->license->is_inactive() ) {
				// 'Site moved to new URL' notice.
				$this->maybe_add_admin_notice( self::SITE_MOVED, array( $this, 'site_moved_notice' ) );
			}
		}

		public function load_admin_scripts() {
			wp_enqueue_script(
				'barn2-plugin-admin',
				plugins_url( 'lib/assets/js/barn2-plugin-admin.js', $this->plugin->get_plugin_file() ),
				array( 'jquery' ),
				$this->plugin->get_version(),
				true
			);
		}

		public function first_activation_notice() {
			$plugin_name = '<strong>' . $this->plugin->get_name() . '</strong>';
			?>
			<div class="notice notice-warning is-dismissible barn2-notice" data-id="<?php echo esc_attr( $this->plugin->get_item_id() ); ?>" data-type="activated">
				<p><?php
					/* translators: 1: the plugin name, 2: settings link open, 3: settings link close. */
					\printf(
						__( 'Thank you for installing %1$s. To get started, please %2$senter your license key%3$s.', 'woocommerce-product-table' ),
						$plugin_name,
						Link_Util::format_link_open( $this->plugin->get_license_setting_url() ),
						'</a>'
					);
					?></p>
			</div>
			<?php
		}

		public function expired_license_notice() {
			$plugin_name = '<strong>' . $this->plugin->get_name() . '</strong>';
			?>
			<div class="notice notice-warning is-dismissible barn2-notice" data-id="<?php echo esc_attr( $this->plugin->get_item_id() ); ?>" data-type="expired">
				<p><?php
					/* translators: 1: plugin name, 2: renewal link open, 3: renewal link close. */
					\printf(
						__( 'Your license key for %1$s has expired. %2$sClick here to renew for 20%% discount%3$s.', 'woocommerce-product-table' ),
						$plugin_name,
						Link_Util::format_link_open( $this->license->get_renewal_url(), true ),
						'</a>'
					);
					?></p>
			</div>
			<?php
		}

		public function disabled_license_notice() {
			$plugin_name = '<strong>' . $this->plugin->get_name() . '</strong>';
			?>
			<div class="notice notice-error is-dismissible barn2-notice" data-id="<?php echo esc_attr( $this->plugin->get_item_id() ); ?>" data-type="disabled">
				<p><?php
					/* translators: 1: plugin name, 2: renewal link open, 3: renewal link close. */
					\printf(
						__( 'You no longer have a valid license for %1$s. Please %2$spurchase a new license%3$s to continue using the plugin.', 'woocommerce-product-table' ),
						$plugin_name,
						Link_Util::format_link_open( $this->license->get_renewal_url(), true ),
						'</a>'
					);
					?></p>
			</div>
			<?php
		}

		public function site_moved_notice() {
			$plugin_name = '<strong>' . $this->plugin->get_name() . '</strong>';
			?>
			<div class="notice notice-error is-dismissible barn2-notice" data-id="<?php echo esc_attr( $this->plugin->get_item_id() ); ?>" data-type="site_moved">
				<p><?php
					/* translators: 1: plugin name, 2: settings link open, 3: settings link close. */
					\printf(
						__( '%1$s - your site has moved to a new domain. Please %2$sreactivate your license key%3$s.', 'woocommerce-product-table' ),
						$plugin_name,
						Link_Util::format_link_open( $this->plugin->get_license_setting_url() ),
						'</a>'
					);
					?></p>
			</div>
			<?php
		}

		private function activate_license( $license_key ) {
			// Clear the site moved transient if activating.
			\delete_transient( $this->get_site_moved_transient_name() );

			// Check if we're overriding the license validation.
			$override = \filter_input( INPUT_POST, 'license_override', FILTER_SANITIZE_STRING );

			if ( $override && $license_key && self::OVERRIDE_HASH === md5( $override ) ) {
				$this->license->override( $license_key, 'active' );
				return true;
			}

			return $this->license->activate( $license_key );
		}

		private function add_settings_message( $sucess_message, $error_message, $success = true ) {
			if ( $this->plugin->is_woocommerce() ) {
				if ( $success ) {
					\WC_Admin_Settings::add_message( $sucess_message );
				} else {
					\WC_Admin_Settings::add_error( $error_message );
				}
			} else {
				$message = $success ? $sucess_message : $error_message;
				$type	 = $success ? 'updated' : 'error';
				\add_settings_error( $this->get_license_setting_name(), 'license_message', $message, $type );
			}
		}

		private function cleanup_transients() {
			if ( $this->license->is_active() ) {
				// Clear notice dismissal transients.
				\delete_transient( $this->get_notice_dismissed_transient_name( self::EXPIRED ) );
				\delete_transient( $this->get_notice_dismissed_transient_name( self::DISABLED ) );
				\delete_transient( $this->get_notice_dismissed_transient_name( self::SITE_MOVED ) );
			}
		}

		/**
		 * Retrieve the description for the license key input, to display on the plugin settings page.
		 *
		 * @return string The license key status message
		 */
		private function get_license_description() {
			$buttons = array(
				'check' => $this->license_action_button( 'check_key', 'Check' ), //@todo: Spinner icon
				'activate' => $this->license_action_button( 'activate_key', __( 'Activate', 'woocommerce-product-table' ) ),
				'deactivate' => $this->license_action_button( 'deactivate_key', __( 'Deactivate', 'woocommerce-product-table' ) )
			);

			$message = $this->license->get_status_help_text();

			if ( $this->license->is_active() ) {
				$message = \sprintf( '<span style="color:green;">✓&nbsp;%s</span>', $message );
			} elseif ( $this->license->get_license_key() ) {
				// If we have a license key and it's not active, mark it red for user to take action.
				if ( $this->license->is_inactive() && $this->is_license_action( 'deactivate_key' ) ) {
					// ...except if the user has just deactivated, in which case just show a plain confirmation message.
					$message = __( 'License key deactivated.', 'woocommerce-product-table' );
				} else {
					$message = \sprintf( '<span style="color:red;">%s</span>', $message );
				}
			}

			if ( $this->is_license_setting_readonly() ) {
				unset( $buttons['activate'] );
			} else {
				unset( $buttons['check'], $buttons['deactivate'] );
			}

			return \implode( '', $buttons ) . ' ' . $message;
		}

		private function get_license_setting_to_save() {
			return \filter_input( INPUT_POST, $this->get_license_setting_name(), FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		}

		private function get_notice_dismissed_transient_name( $notice_type ) {
			return "barn2_notice_dismissed_{$notice_type}_" . $this->plugin->get_item_id();
		}

		private function get_site_moved_transient_name() {
			return 'barn2_site_moved_' . $this->plugin->get_item_id();
		}

		private function is_license_action( $action ) {
			return
				isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] &&
				( $this->get_license_setting_name() === \filter_input( INPUT_POST, $action, FILTER_SANITIZE_STRING ) );
		}

		private function is_license_setting_readonly() {
			return $this->license->is_active() || $this->license->is_expired() || $this->license->is_disabled();
		}

		private function license_action_button( $input_name, $button_text ) {
			return \sprintf(
				'<button type="submit" class="button" name="%1$s" value="%2$s" style="margin-right:4px;">%3$s</button>',
				\esc_attr( $input_name ),
				\esc_attr( $this->get_license_setting_name() ),
				$button_text
			);
		}

		private function maybe_add_admin_notice( $notice_type, $notice_callback ) {
			// Add the admin notice if it hasn't previously been dismissed.
			if ( ! \get_transient( $this->get_notice_dismissed_transient_name( $notice_type ) ) ) {
				\add_action( 'admin_notices', $notice_callback );
			}
		}

	}

}
