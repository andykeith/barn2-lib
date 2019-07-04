<?php

namespace Barn2\Lib\Admin;

use Barn2\Lib\Registerable;
use Barn2\Lib\Plugin;

if ( ! \class_exists( 'Barn2\Lib\Admin\Plugin_Admin' ) ) {

	/**
	 *
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 */
	class Plugin_Admin implements Registerable {

		/**
		 * @var Plugin The core plugin data (ID, version, etc).
		 */
		private $plugin;

		public function __construct( Plugin $plugin ) {
			$this->plugin = $plugin;
		}

		public function register() {
			// Add settings link from Plugins page.
			\add_filter( 'plugin_action_links_' . $this->plugin->get_basename(), array( $this, 'add_settings_link' ) );

			// Add documentation link to meta info on Plugins page.
			\add_filter( 'plugin_row_meta', array( $this, 'add_documentation_link' ), 10, 2 );
		}

		public function add_settings_link( $links ) {
			if ( ! ( $settings_url = $this->plugin->get_settings_page_url() ) ) {
				return $links;
			}

			// Don't add link if it's a WooCommerce plugin but WooCommerce is not activated.
			if ( $this->plugin->is_woocommerce() && ! \class_exists( '\WooCommerce' ) ) {
				return $links;
			}

			\array_unshift( $links, \sprintf( '<a href="%1$s">%2$s</a>', \esc_url( $settings_url ), __( 'Settings', 'woocommerce-product-table' ) ) );
			return $links;
		}

		public function add_documentation_link( $links, $file ) {
			if ( $file !== $this->plugin->get_basename() ) {
				return $links;
			}

			if ( ! ( $documentation_url = $this->plugin->get_documentation_url() ) ) {
				return $links;
			}

			$row_meta = array(
				'docs' => \sprintf(
					'<a href="%1$s" aria-label="%2$s" target="_blank">%3$s</a>',
					\esc_url( $documentation_url ),
					\esc_attr( sprintf( __( 'View %s documentation', 'woocommerce-product-table' ), $this->plugin->get_name() ) ),
					\esc_html__( 'Docs', 'woocommerce-product-table' )
				)
			);

			return \array_merge( $links, $row_meta );
		}

	}

}