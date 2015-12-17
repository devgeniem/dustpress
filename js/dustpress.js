//var $dp = window.DustPress.ajax;

window.DustPress = ( function( window, document, $ ) {

	var dp = {};

	dp.defaults = {
		"type": 	"post",
		"tidy": 	false,
		"render": 	false,
		"partial": 	""
	};

	dp.ajax = function( path, params, success, error ) {

		var post = $.extend( dp.defaults, params );

		$.ajax({
			url: window.location,
			method: post.type,
			data: {
				dustpress_data: {
					path: 	path,
					args: 	post.args,
					render: post.render,
					tidy: 	post.tidy
				}
			}
		})
		.done(success)
		.fail(error);

	};

	return dp;

})( window, document, jQuery );
