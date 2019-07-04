<?php

namespace Barn2\Lib;

if ( ! \class_exists( 'Barn2\Lib\EDD\EDD_Plugin_Data' ) ) {

	class Premium_Plugin extends Simple_Plugin implements Licensable_Plugin {

		public function __construct( $data ) {
			parent::__construct( \array_merge( array(
				'item_id' => 0,
				'license_setting_path' => '',
					), (array) $data ) );

			$this->data['license_setting_path'] = \ltrim( $this->data['license_setting_path'], '/' );
		}

		public function get_item_id() {
			return $this->data['item_id'];
		}

		public function get_license_setting_url() {
			// Default to plugin settings URL if no license URL defined.
			return ! empty( $this->data['license_setting_path'] ) ?
				\admin_url( $this->data['license_setting_path'] ) :
				parent::get_settings_page_url();
		}

	}

}