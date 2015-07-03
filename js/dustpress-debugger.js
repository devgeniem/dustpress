/*
 * DUSTPRESS DEBUGGER
 */
jQuery(document).ready(function($) {

	// button
	var html = '<button class="jsonview_open_debug">Show debugger</button>\n';
	// container
	html += '<div class="jsonview_data_debug jsonview_data_debug_closed"><span class="jsonview_close_debug">x</span></div>';

	$(html).appendTo('body');

	var div = "<div class=\"jsonview_debug\"></div>";

	$(div).appendTo(".jsonview_data_debug");

	var jsonData = $.parseJSON(dustpress_debugger.jsondata);
	
	// log also into console
	console.log('Debugger', jsonData);

	var jsonView = $(".jsonview_debug").JSONView(
		jsonData,
		{ 
			collapsed: true,
			recursive_collapser: false
		}
	);

	$(document).keyup(function(e) {
	     if (e.keyCode == 27) { // escape key maps to keycode `27`
	        if ( ! $(".jsonview_data_debug").hasClass('jsonview_data_debug_closed') ) {
	        	toggleDebugger();
	        }
	    }
	});

	$(".jsonview_open_debug").click(function() {
		toggleDebugger();
	});
	$(".jsonview_close_debug").click(function() {
		toggleDebugger();
	});

	var toggleDebugger = function() {
		$(".jsonview_data_debug").toggleClass('jsonview_data_debug_closed');	
		$(".jsonview_open_debug").toggleClass('jsonview_hide');	
		$("body").toggleClass("locked");
	}

});