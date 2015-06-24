function ajaxRequest(clientValueKey, dat, callback) {
	dat.ajax = clientValueKey;
	var ajaxParams = {
		url: '.',
		type: 'POST',
		data: dat
	};
	if (typeof callback !== 'undefined') ajaxParams['success'] = callback;
	$.ajax(ajaxParams);
}