
(function( $, window, document, undefined ) {
	"use strict";

	$( document ).ready( function() {
		$( '.barn2-notice' ).on( 'click', '.notice-dismiss', function() {
			var $notice = $( this ).parent(),
			  data = $notice.data();

			if ( ! data.id || ! data.type ) {
				return;
			}

			data.action = 'barn2_dismiss_notice';

			$.ajax( {
				url: ajaxurl, // always defined when running in WP Admin
				type: 'POST',
				data: data,
				xhrFields: {
					withCredentials: true
				}
			} );
		} );
	} );

})( jQuery, window, document );