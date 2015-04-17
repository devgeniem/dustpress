/*
 * DUSTPRESS DEBUGGER
 */
jQuery(document).ready(function($) {

	var button = '<div class="jsonview_data_debug"><button class="jsonview_open_debug">Show/Hide data debug</button></div>';

	$(button).appendTo('body');

	var div = "<div class=\"jsonview_debug\"></div>";

	$(div).appendTo(".jsonview_data_debug");

	$(".jsonview_debug").jsonView($.parseJSON(dustpress_debugger.jsondata));
	$(".property-value").css('display', 'none');
	$(".property-toggle-button").html('[+]');

	$(".jsonview_open_debug").click(function() {
		$(".jsonview_debug").slideToggle();		
	});

});