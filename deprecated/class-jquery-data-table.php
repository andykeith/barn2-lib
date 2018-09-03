<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-html-data-table.php';

if ( ! interface_exists( 'JQuery_Data_Table_Config' ) ) {

	/**
	 * Interface for the DataTables config builder. An instance of this is passed to
	 * <code>JQuery_Data_Table</code> to create the table config object.
	 *
	 * @package   Util
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 * @version   1.1
	 */
	interface JQuery_Data_Table_Config {

		/**
		 * Gets the table config as an array.
		 *
		 * @return array The table config object
		 */
		public function get_config();

	}
}

if ( ! class_exists( 'JQuery_Data_Table' ) ) {

	/**
	 * Represents a JQuery DataTables table, including the DataTables config object to initialize the table.
	 *
	 * @package   Util
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 * @version   1.1
	 */
	class JQuery_Data_Table extends Html_Data_Table {

		private $id;
		private $config;
		private $show_footer;
		private $above	 = array();
		private $below	 = array();

		public function __construct( $id, JQuery_Data_Table_Config $config, $show_footer = false ) {
			parent::__construct();
			$this->id			 = $id;
			$this->config		 = $config;
			$this->show_footer	 = $show_footer;
		}

		public function add_above( $above ) {
			if ( $above ) {
				$this->above[] = $above;
			}
		}

		public function add_below( $below ) {
			if ( $below ) {
				$this->below[] = $below;
			}
		}

		public function add_header( $data, $atts = false, $key = false, $is_th = true ) {
			parent::add_header( $data, $atts, $key, $is_th );

			if ( $this->show_footer ) {
				parent::add_footer( $data, $atts, $key, $is_th );
			}
		}

		public function reset() {
			parent::reset();
			$this->above = array();
			$this->below = array();
		}

		public function get_config() {
			return $this->config ? $this->config->get_config() : false;
		}

		public function get_config_json() {
			if ( $config = $this->get_config() ) {
				return self::json_encode( $config );
			}
			return '';
		}

		/**
		 * Get the table config as a string in the form 'var config_[table id] = [script]', to be inserted as an inline
		 * script in the current page. The script data itself is JSON encoded.
		 *
		 * The result can be wrapped in script tags if required, which is the default, or returned without.
		 *
		 * @param type $include_script_tag Whether to wrap the returned config in script tags. Default: true
		 * @return string The config script, to embed in page or enqueue.
		 */
		public function get_config_script( $include_script_tag = true ) {
			if ( ! ( $config_json = $this->get_config_json() ) ) {
				return '';
			}

			$object_name	 = 'config_' . $this->id;
			$config_script	 = sprintf( 'var %1$s = %2$s;', $object_name, $config_json );

			if ( $include_script_tag ) {
				$config_script = "\n<script type='text/javascript'>\n{$config_script}\n</script>\n";
			}

			return $config_script;
		}

		public function to_html() {
			$html	 = parent::to_html();
			$html	 .= $this->get_config_script();

			if ( ! empty( $this->above ) ) {
				$html = implode( "\n", $this->above ) . $html;
			}

			if ( ! empty( $this->below ) ) {
				$html .= implode( "\n", $this->below );
			}

			return $html;
		}

		public function to_array() {
			$array			 = parent::to_array();
			$array['config'] = $this->get_config();

			if ( ! empty( $this->above ) ) {
				$array['above'] = $this->above;
			}

			if ( ! empty( $this->below ) ) {
				$array['below'] = $this->below;
			}

			return $array;
		}

		protected static function json_encode( $data ) {
			$json = wp_json_encode( $data );

			// Ensure JS functions are defined as a function, not a string, in the encoded json
			return preg_replace( '#"(jQuery\.fn.*)"#U', '$1', $json );
		}

	}
}