$(function() {
	var updateInterval = setInterval(function() {
		ajaxRequest('line-manager-data', {}, function(results) {
			var html = results['user-html'];
			console.log(results['lineup-length']);
			$('.page.manage-lineup .users-data > ol.users').html(html);
		});
	}, 4022);
});