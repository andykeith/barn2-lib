<?php

namespace Barn2\Lib\License\EDD;

if ( ! \class_exists( __NAMESPACE__ . '\Licensing_API' ) ) {

	/**
	 * Utility class for interfacing with the EDD Software Licensing API.
	 *
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 */
	final class Licensing_API {

		/**
		 * @var string The URL of the EDD Software Licensing API.
		 */
		const EDD_LICENSING_ENDPOINT = 'http://barn2.co.uk/edd-sl';

		/**
		 * @var int API timeout in seconds.
		 */
		const API_TIMEOUT = 15;

		/**
		 * Activate the specified license key.
		 *
		 * Returns a <code>stdClass</code> object containing two properties:
		 * - success: true or false. Whether the request returned successfully.
		 * - response: If success is true, it will contain the JSON-decoded response (an object) from
		 * the server containing the result. If success if false, it will contain an error message (string)
		 * indicating why the request failed.
		 *
		 * @param string $license_key The license key to activate.
		 * @param int $item_id The download ID for the item to check.
		 * @param string (URL) $url The URL to activate. Defaults to home_url().
		 * @return stdClass The result object (see above).
		 */
		public static function activate_license( $license_key, $item_id, $url = '' ) {
			$api_params = array(
				'edd_action' => 'activate_license',
				'license' => $license_key,
				'item_id' => $item_id,
				'url' => $url ? $url : \home_url()
			);

			return self::api_request( $api_params );
		}

		/**
		 * Deactivate the specified license key.
		 *
		 * Returns a <code>stdClass</code> object containing two properties:
		 * - success: true or false. Whether the request returned successfully.
		 * - response: If success is true, it will contain the JSON-decoded response (an object) from
		 * the server containing the result. If success if false, it will contain an error message (string)
		 * indicating why the request failed.
		 *
		 * @param string $license_key The license key to deactivate.
		 * @param int $item_id The download ID for the item to check.
		 * @param string (URL) $url The URL to deactivate. Defaults to home_url().
		 * @return stdClass The result object (see above).
		 */
		public static function deactivate_license( $license_key, $item_id, $url = '' ) {
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license' => $license_key,
				'item_id' => $item_id,
				'url' => $url ? $url : \home_url()
			);

			return self::api_request( $api_params );
		}

		/**
		 * Checks the specified license key.
		 *
		 * Returns a <code>stdClass</code> object containing two properties:
		 * - success: true or false. Whether the request returned successfully.
		 * - response: If success is true, it will contain the JSON-decoded response (an object) from
		 * the server containing the license information. If success if false, it will contain an error
		 * message (string) indicating why the request failed.
		 *
		 * @param string $license_key The license key to check.
		 * @param int $item_id The download ID for the item to check.
		 * @param string (URL) $url The URL to check. Defaults to home_url().
		 * @return stdClass The result object (see above).
		 */
		public static function check_license( $license_key, $item_id, $url = '' ) {
			$api_params = array(
				'edd_action' => 'check_license',
				'license' => $license_key,
				'item_id' => $item_id,
				'url' => $url ? $url : \home_url()
			);

			return self::api_request( $api_params );
		}

		/**
		 * Gets the latest version information for the specified plugin.
		 *
		 * Returns a <code>stdClass</code> object containing two properties:
		 * - success: true or false. Whether the request returned successfully.
		 * - response: If success is true, it will contain the JSON-decoded response (an object) from
		 * the server containing the latest version information. If success if false, it will contain
		 * an error message (string) indicating why the request failed.
		 *
		 * @param string $license_key The license key.
		 * @param int $item_id The download ID for the item to check.
		 * @param string $slug The plugin slug.
		 * @param boolean $beta_testing Whether to check for beta versions.
		 * @param string (URL) $url The URL of the site we're checking updates for.
		 * @return stdClass The result object (see above).
		 */
		public static function get_latest_version( $license_key, $item_id, $slug, $beta_testing = false, $url = '' ) {
			$api_params = array(
				'edd_action' => 'get_version',
				'license' => $license_key,
				'item_id' => $item_id,
				'url' => $url ? $url : \home_url(),
				'slug' => $slug,
				'beta' => $beta_testing,
			);

			$result = self::api_request( $api_params );

			if ( $result->success && \is_object( $result->response ) ) {
				foreach ( $result->response as $prop => $data ) {
					$result->response->$prop = \maybe_unserialize( $data );
				}
			}

			return $result;
		}

		private static function api_request( $params ) {
			// Call the Software Licensing API.
			$response = \wp_remote_post( self::EDD_LICENSING_ENDPOINT, array(
				'timeout' => self::API_TIMEOUT,
				'sslverify' => false,
				'body' => $params
				) );

			// Build the result.
			$result = new \stdClass;

			if ( self::is_api_error( $response ) ) {
				$result->success	 = false;
				$result->response	 = self::get_api_error_message( $response );
			} else {
				$result->success	 = true;
				$result->response	 = \json_decode( \wp_remote_retrieve_body( $response ) );
			}

			return $result;
		}

		private static function is_api_error( $response ) {
			return \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response );
		}

		private static function get_api_error_message( $response ) {
			if ( \is_wp_error( $response ) ) {
				return $response->get_error_message();
			} elseif ( \wp_remote_retrieve_response_message( $response ) ) {
				return \wp_remote_retrieve_response_message( $response );
			} else {
				return __( 'An error has occurred, please try again.', 'woocommerce-product-table' );
			}
		}

	}

}