<?php

namespace Barn2\Lib;

if ( ! \class_exists( 'Barn2\Lib\Link_Util' ) ) {

	class Link_Util {

		const STORE_URL				 = 'https://barn2.co.uk';
		const DOCUMENTATION_HOME_URL	 = 'https://barn2.co.uk/kb';

		public static function format_link( $url, $link_text, $new_tab = false ) {
			return \sprintf( '%1$s%2$s</a>', self::format_link_open( $url, $new_tab ), $link_text );
		}

		public static function format_link_open( $url, $new_tab = false ) {
			$target = $new_tab ? ' target="_blank"' : '';
			return \sprintf( '<a href="%1$s"%2$s>', \esc_url( $url ), $target );
		}

		public static function format_store_url( $path = '' ) {
			return self::STORE_URL . '/' . \ltrim( $path, ' /' );
		}

		public static function format_store_link( $path, $link_text, $new_tab = true ) {
			return self::format_link( self::format_store_url( $path ), $link_text, $new_tab );
		}

		public static function format_store_link_open( $path, $new_tab = true ) {
			return self::format_link_open( self::format_store_url( $path ), $new_tab );
		}

		public static function format_store_add_to_cart_url( $download_id, $price_id = 0, $discount_code = '' ) {
			$args = array(
				'edd_action' => 'add_to_cart',
				'download_id' => (int) $download_id
			);
			if ( $price_id ) {
				$args['edd_options[price_id]'] = (int) $price_id;
			}
			if ( $discount_code ) {
				$args['discount'] = $discount_code;
			}

			return self::format_store_url( '?' . \http_build_query( $args ) );
		}

	}

}