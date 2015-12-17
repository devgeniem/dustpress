var $dp = function( path, params ) {
	
	var dp = window.DustPressAjax,
		data = {
			dustpress_data: {
				path: path,
				args: params.args
			}
		},
		options = {};

	params.options = {};

	if (params.options.get)
		options.get = params.options.get;
	if (params.options.contentType)
		options.contentType = params.options.contentType;
	else
		options.contentType = "application/x-www-form-urlencoded; charset=UTF-8";

	if ( options.get )
		dp.ajaxGet(window.location + '/?' + encodeURIComponent(JSON.stringify(data)), params.success, params.error, options);
	else
		dp.ajaxPost(window.location, data, params.success, params.error, options);

};

window.DustPressAjax = ( function( window, document ) {

	var dp = {};

	dp.xhr = function(a) {
	    for ( // for all a
	        a = 0; // from 0
	        a < 4; // to 4,
	        a++ // incrementing
	    ) try { // try
	            return a ? new ActiveXObject( // a new ActiveXObject
	                    [ // reflecting
	                        , // (elided)
	                        "Msxml2", // the various
	                        "Msxml3", // working
	                        "Microsoft" // options
	                    ][a] + // for Microsoft implementations, and
	                    ".XMLHTTP" // the appropriate suffix,
	                ) // but make sure to
	                : new XMLHttpRequest(); // try the w3c standard first, and
	        } catch (e) {} // ignore when it fails.
	};

	dp.onreadystatechange = function(xmlhttp) {

	    if (xmlhttp.readyState != 4) return;

	    if (xmlhttp.status != 200)
	    	dp.error(xmlhttp, "error", xmlhttp.statusText);
	    else	        	
	    	dp.success(xmlhttp, "success", xmlhttp.statusText);
	        
	};


	dp.ajaxGet = function(url, success, error, options) {

	    var xmlhttp = dp.xhr();

	    dp.success = success;
	    dp.error 	= error;

	    xmlhttp.onreadystatechange = dp.onreadystatechange;

	    xmlhttp.open("GET", url, true);
	    xmlhttp.setRequestHeader("Content-Type", options.contentType);
	    xmlhttp.send(url);

	    return xmlhttp;
	};
	
	dp.ajaxPost = function(url, data, success, error, options) {

	    var xmlhttp = dp.xhr(),
	    	contentType;

	    dp.success 	= success;
	    dp.error 	= error;
	    
	    xmlhttp.onreadystatechange = dp.onreadystatechange;

	    xmlhttp.open("POST", url, true);
	    xmlhttp.setRequestHeader("Content-Type", options.contentType);
		xmlhttp.send(encodeURIComponent(JSON.stringify(data)));
	};

	return dp;

})( window, document );
