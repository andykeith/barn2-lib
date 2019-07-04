<?php

namespace Barn2\Lib\License\EDD;

use Barn2\Lib\Registerable;
use Barn2\Lib\Link_Util;
use Barn2\Lib\License\License;

if ( ! \class_exists( __NAMESPACE__ . '\Plugin_License' ) ) {

	/**
	 *
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 */
	class Plugin_License implements Registerable, License {

		const RENEWAL_DISCOUNT_CODE = 'RENEWAL20';

		private $item_id;
		private $license_option;
		private $legacy_license_prefix;
		private $license_data = null;

		/**
		 * Creates a new plugin license instance.
		 *
		 * @param int $item_id The item ID for this plugin.
		 * @param string $legacy_license_prefix The legacy prefix for the license key DB options.
		 */
		public function __construct( $item_id, $legacy_license_prefix = '' ) {
			$this->item_id				 = (int) $item_id;
			$this->legacy_license_prefix = $legacy_license_prefix;
			$this->license_option		 = 'barn2_plugin_license_' . $this->item_id;
		}

		public function register() {
			add_action( 'plugins_loaded', array( $this, 'migrate_legacy_license' ), 0 ); // early, before any is_valid() checks.
		}

		public function exists() {
			return $this->get_license_key() ? true : false;
		}

		public function is_valid() {
			return $this->get_license_key() && \in_array( $this->get_status(), array( 'active', 'inactive', 'expired' ) );
		}

		public function is_active() {
			return $this->get_license_key() && 'active' === $this->get_status();
		}

		public function is_expired() {
			return $this->get_license_key() && 'expired' === $this->get_status();
		}

		public function is_inactive() {
			return $this->get_license_key() && 'inactive' === $this->get_status();
		}

		public function is_disabled() {
			return $this->get_license_key() && 'disabled' === $this->get_status();
		}

		public function get_license_key() {
			$data = $this->get_license_data();
			return isset( $data['license'] ) ? $data['license'] : '';
		}

		public function get_status() {
			$data = $this->get_license_data();
			return isset( $data['status'] ) ? $data['status'] : '';
		}

		public function get_status_help_text() {
			$message = $this->get_error_message();

			switch ( $this->get_status() ) {
				case 'active':
					$message = __( 'Your license key is active.', 'woocommerce-product-table' );
					break;
				case 'inactive':
					if ( ! $message ) {
						$message = __( 'Your license key is not active. Please reactivate or save the settings.', 'woocommerce-product-table' );
					}
					break;
				case 'expired':
					if ( ! $message ) {
						$message = __( 'Your license key has expired.', 'woocommerce-product-table' );
					}
					break;
				case 'disabled':
					if ( ! $message ) {
						$message = __( 'Your license key has been disabled. Please purchase a new license to continue using the plugin.', 'woocommerce-product-table' );
					}
					break;
				case 'invalid':
				default:
					if ( ! $this->get_license_key() ) {
						$message = __( 'Please enter your license key.', 'woocommerce-product-table' );
					} elseif ( ! $message ) {
						$message = __( 'Your license key is invalid.', 'woocommerce-product-table' );
					}
					break;
			}

			return $message;
		}

		public function set_license_key( $license_key ) {
			$this->update_license_data( array( 'license' => $license_key ) );
		}

		public function set_status( $status ) {
			// Status is sanitized during update_license_data().
			$this->update_license_data( array( 'status' => $status ) );
		}

		/**
		 * Attempt to activate the specified license key.
		 *
		 * @param string $license_key The license key to activate.
		 * @return boolea true if successfully activated, false otherwise.
		 */
		public function activate( $license_key ) {
			// Check a license was supplied.
			if ( ! $license_key ) {
				$this->set_missing_license();
				return false;
			}

			$result			 = false;
			$url_to_activate = \home_url();

			$license_data = array(
				'license' => $license_key,
				'url' => $url_to_activate
			);

			$api_result = Licensing_API::activate_license( $license_key, $this->item_id, $url_to_activate );

			if ( $api_result->success ) {
				// Successful response - now check whether license is valid.
				$response = $api_result->response;

				// $response->license will be 'valid' or 'invalid'.
				if ( 'valid' === $response->license ) {
					$license_data['status']	 = 'active';
					$result					 = true;
				} else {
					// Invalid license.
					$license_data['error_code']	 = isset( $response->error ) ? $response->error : 'error';
					$license_data['status']		 = $this->to_license_status( $license_data['error_code'] );
				}

				// Store the returned license info.
				$license_data['license_info'] = $this->format_license_info( $response );
			} else {
				// API error - set license to invalid as we can't activate.
				$license_data['status']			 = 'invalid';
				$license_data['error_code']		 = 'error';
				$license_data['error_message']	 = $api_result->response;
			}

			$this->set_license_data( $license_data );

			return $result;
		}

		/**
		 * Attempt to deactivate the current license key.
		 *
		 * @return boolean true if successfully deactivated, false otherwise.
		 */
		public function deactivate() {
			// We can't deactivate a license if it doesn't exist.
			if ( ! $this->exists() ) {
				return false;
			}

			// Bail early if already inactive.
			if ( $this->is_inactive() ) {
				return true;
			}

			// If license is overridden, or expired or disabled, bypass API and set status manually.
			if ( $this->is_license_overridden() ) {
				$this->set_status( 'inactive' );
				return true;
			}

			$result			 = false;
			$license_data	 = array();
			$api_result		 = Licensing_API::deactivate_license( $this->get_license_key(), $this->item_id );

			if ( $api_result->success ) {
				// Successful response - now check whether license is valid.
				$response = $api_result->response;

				// $response->license will be 'deactivated' or 'failed'.
				if ( 'deactivated' === $response->license ) {
					$result = true;

					// License deactivated, so update status.
					$license_data['status'] = 'inactive';

					// Store returned license info.
					$license_data['license_info'] = $this->format_license_info( $response );
					$this->update_license_data( $license_data );
				} else {
					// Deactivation failed - reasons: already deactivated via Account page, license has expired, bad data, etc.
					// In this case we refresh license data to ensure we have correct state stored in database.
					$this->refresh();
					$result = true;
				}
			} else {
				// API error
				$license_data['error_code']		 = 'error';
				$license_data['error_message']	 = $api_result->response;
				$this->update_license_data( $license_data );
			}

			return $result;
		}

		/**
		 * Refresh the current license key information from the EDD Licensing server. Ensures the correct
		 * license state for this plugin is stored in the database.
		 *
		 * @return void
		 */
		public function refresh() {
			// No point refreshing if license doesn't exist.
			if ( ! $this->exists() ) {
				return;
			}

			// If license is overridden, we shouldn't refresh as it will lose override state.
			if ( $this->is_license_overridden() ) {
				return;
			}

			$license_data	 = array( 'license' => $this->get_license_key() );
			$api_result		 = Licensing_API::check_license( $this->get_license_key(), $this->item_id );

			if ( $api_result->success ) {
				// Successful response returned.
				$response = $api_result->response;

				if ( 'valid' === $response->license ) {
					// Valid (and active) license.
					$license_data['status'] = 'active';
				} else {
					// Invalid license - $response->license will contain the reason for the invalid license - e.g. expired, inactive, site_inactive, etc.
					$license_data['error_code']	 = $response->license;
					$license_data['status']		 = $this->to_license_status( $response->license );
				}

				// Store returned license info.
				$license_data['license_info'] = $this->format_license_info( $response );
			} else {
				// API error - store the error but don't change license status (e.g. temporary communication error).
				$license_data['error_code']		 = 'error';
				$license_data['error_message']	 = $api_result->response;
			}

			$this->update_license_data( $license_data );
		}

		public function override( $license_key, $status ) {
			if ( ! $license_key || ! $this->is_valid_status( $status ) ) {
				return;
			}

			$this->set_license_data( array(
				'license' => $license_key,
				'status' => $status,
				'override' => true
			) );
		}

		public function get_setting_name() {
			return $this->license_option;
		}

		public function get_error_code() {
			$license_data = $this->get_license_data();
			return isset( $license_data['error_code'] ) ? $license_data['error_code'] : '';
		}

		public function get_error_message() {
			if ( ! $this->get_error_code() ) {
				return '';
			}

			$message		 = '';
			$license_info	 = $this->get_license_details();

			switch ( $this->get_error_code() ) {
				case 'missing' :
					/* translators: 1: account page link open, 2: account page link close */
					$message = \sprintf(
						__( 'Invalid license key - please check your order confirmation email or %1$sAccount%2$s.', 'woocommerce-product-table' ),
						Link_Util::format_store_link_open( 'account' ),
						'</a>'
					);
					break;
				case 'missing_url' :
					$message = __( 'No URL was supplied for activation, please contact support.', 'woocommerce-product-table' );
					break;
				case 'key_mismatch':
					$message = __( 'License key mismatch, please contact support.', 'woocommerce-product-table' );
					break;
				case 'license_not_activable' :
					$message = __( 'This license is for a bundled product and cannot be activated.', 'woocommerce-product-table' );
					break;
				case 'item_name_mismatch' :
				case 'invalid_item_id':
					$message = __( 'Your license key is not valid for this plugin.', 'woocommerce-product-table' );
					break;
				case 'no_activations_left':
					$limit	 = '';

					if ( isset( $license_info['max_sites'] ) ) {
						$limit = \sprintf( _n( '%s site active', '%s sites active', absint( $license_info['max_sites'] ), 'woocommerce-product-table' ), $license_info['max_sites'] );
					}

					$message		 = \sprintf( __( 'Your license key has reached its activation limit (%s).', 'woocommerce-product-table' ), $limit );
					$read_more_link	 = Link_Util::format_store_link( 'kb/license-key-problems', __( 'Read more', 'woocommerce-product-table' ) );

					/* translators: support for RTL. 1: license limit error message, 2: a read more link */
					$message = \sprintf( __( '%1$s %2$s', 'woocommerce-product-table' ), $message, $read_more_link );
					break;
				case 'inactive':
				case 'site_inactive':
					$message = __( 'Your license key is not active. Please reactivate or save the settings.', 'woocommerce-product-table' );
					break;
				case 'expired' :
					$message = __( 'Your license key expired has expired.', 'woocommerce-product-table' );

					// See if we have a valid expiry date by checking first 4 chars are numbers (the expiry year).
					// This is only a rough check - createFromFormat() will validate fully and return a DateTime object if valid.
					if ( ! empty( $license_info['expires'] ) && \is_numeric( \substr( $license_info['expires'], 0, 4 ) ) ) {
						if ( $expiry_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $license_info['expires'] ) ) {
							/* translators: %s: The license expiry date */
							$message = \sprintf( __( 'Your license key expired on %s.', 'woocommerce-product-table' ), $expiry_datetime->format( \get_option( 'date_format' ) ) );
						}
					}

					$renewal_link = Link_Util::format_link(
							$this->get_renewal_url(),
							__( 'Renew now for 20% discount.', 'woocommerce-product-table' ),
							true
					);

					/* translators: support for RTL. 1: expired license error message, 2: a renewal link */
					$message		 = \sprintf( __( '%1$s %2$s', 'woocommerce-product-table' ), $message, $renewal_link );
					break;
				case 'disabled':
					$message		 = __( 'Your license key has been disabled. Please purchase a new license to continue using the plugin.', 'woocommerce-product-table' );
					break;
				default :
					$license_data	 = $this->get_license_data();

					if ( ! empty( $license_data['error_message'] ) ) {
						$message = $license_data['error_message'];
					} else {
						$message = __( 'Your license key is invalid.', 'woocommerce-product-table' );
					}
					break;
			}

			return $message;
		}

		public function get_active_url() {
			$data = $this->get_license_data();
			return $data['url'];
		}

		public function get_renewal_url( $apply_discount = true ) {
			$discount_code	 = $apply_discount ? self::RENEWAL_DISCOUNT_CODE : '';
			$license_info	 = $this->get_license_details();

			if ( ! empty( $license_info['item_id'] ) ) {
				$price_id = ! empty( $license_info['price_id'] ) ? $license_info['price_id'] : 0;
				return Link_Util::format_store_add_to_cart_url( $license_info['item_id'], $price_id, $discount_code );
			} else {
				$default_store_path = 'our-wordpress-plugins';

				if ( $discount_code ) {
					$default_store_path .= '/?discount=' . $discount_code;
				}
				return Link_Util::format_store_url( $default_store_path );
			}
		}

		public function migrate_legacy_license() {
			if ( empty( $this->legacy_license_prefix ) ) {
				return;
			}

			$license_key = \get_option( $this->legacy_license_prefix . '_license_key' );

			if ( $license_key && \is_string( $license_key ) ) {
				// Migrate from legacy license data.
				$data			 = $this->get_default_data();
				$data['license'] = $license_key;

				$status = \get_option( $this->legacy_license_prefix . '_license_status' );

				if ( 'valid' === $status ) {
					$data['status'] = 'active';
				} elseif ( 'deactivated' === $status ) {
					$data['status'] = 'inactive';
				} else {
					$data['status'] = 'invalid';
				}

				// Remove legacy license data.
				\delete_option( $this->legacy_license_prefix . '_license_key' );
				\delete_option( $this->legacy_license_prefix . '_license_status' );
				\delete_option( $this->legacy_license_prefix . '_license_error' );
				\delete_option( $this->legacy_license_prefix . '_license_debug' );

				$this->set_license_data( $data );
			}
		}

		private function get_default_data() {
			return array(
				'license' => '',
				'status' => 'invalid',
				'url' => '',
				'error_code' => '',
				'error_message' => '',
				'license_info' => array()
			);
		}

		private function get_license_data() {
			if ( null === $this->license_data ) {
				$this->license_data = \get_option( $this->license_option, $this->get_default_data() );
			}
			return $this->license_data;
		}

		private function set_license_data( $data = array() ) {
			$this->license_data = $this->sanitize_license_data( (array) $data );
			\update_option( $this->license_option, $this->license_data, false );
		}

		private function update_license_data( $data = array() ) {
			$license_data = $this->get_license_data();

			// Clear any previous error before updating.
			$license_data['error_code']		 = '';
			$license_data['error_message']	 = '';

			// Merge current data with new $data before setting.
			$this->set_license_data( \array_merge( $license_data, $data ) );
		}

		private function sanitize_license_data( $data ) {
			$default_data	 = $this->get_default_data();
			$data			 = \array_merge( $default_data, $data );

			if ( ! $this->is_valid_status( $data['status'] ) ) {
				$data['status'] = $default_data['status'];
			}

			// License is invalid if there's no license key.
			if ( empty( $data['license'] ) ) {
				$data['status'] = 'invalid';
			}

			// Set URL according to status.
			if ( 'active' === $data['status'] ) {
				$data['url'] = \home_url();
			} elseif ( 'inactive' === $data['status'] ) {
				$data['url'] = '';
			}

			return $data;
		}

		private function get_license_details() {
			$data = $this->get_license_data();
			return isset( $data['license_info'] ) ? $data['license_info'] : array();
		}

		private function set_missing_license() {
			$this->set_license_data( array(
				'license' => '',
				'status' => 'invalid',
				'error_code' => 'missing'
			) );
		}

		private function format_license_info( $api_response ) {
			$info = array();

			// License info should always return the expiry date, so it's considered valid if this is present.
			if ( isset( $api_response->expires ) ) {
				// Cast response to array.
				$info = (array) $api_response;

				// Remove the stuff we don't need.
				unset( $info['success'], $info['license'], $info['checksum'], $info['error'] );
			}

			return $info;
		}

		private function is_license_overridden() {
			$license_data = $this->get_license_data();
			return ! empty( $license_data['override'] );
		}

		private function is_valid_status( $status ) {
			return $status && \in_array( $status, array( 'active', 'inactive', 'expired', 'disabled', 'invalid' ) );
		}

		private function to_license_status( $api_license_status ) {
			if ( 'valid' === $api_license_status ) {
				return 'active';
			} elseif ( \in_array( $api_license_status, array( 'inactive', 'site_inactive' ) ) ) {
				return 'inactive';
			} elseif ( \in_array( $api_license_status, array( 'expired', 'disabled' ) ) ) {
				return $api_license_status;
			}
			return 'invalid';
		}

	}

}