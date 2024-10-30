jQuery(document).ready(function() { // wait until the page is completely loaded
	var bwsData = {
		action: 'bws-save',
		security: bws.nonce,
		browser_width: jQuery(window).width(),
		browser_height: jQuery(window).height(),
		screen_width: screen.width,
		screen_height: screen.height
		};
		
	if( !( window.top != window.self || jQuery("body").hasClass("dashboard_page_bws") ) ) { // don't submit when page loaded in iframe, or when viewing own stats page
		jQuery.post( bws.ajaxurl, bwsData );
	}
});