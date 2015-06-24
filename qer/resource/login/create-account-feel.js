$(function() {
	var field = $('#username-field');
	var input = field.children('input[type="text"]');
	var changeFunc = function() {
		var val = $(this).val();
		var loader = field.children('.loader');
		if (loader.length === 0) {
			loader = $('<div class="loader"></div>');
			field.prepend(loader);
		}
		
		loader.attr('class', 'loader loading');
		ajaxRequest('unique-user', {value: val}, function(result) {
			var res = result.result;
			loader.attr('class', res ? 'loader success' : 'loader failure');
		});
	};
	input.on('change', changeFunc);
	input.on('keyup', changeFunc);
});

// skyladder.net/demo/qer/api
// POST
// {mode: 'get-user-position', 'user-id': 04590, 'line-id': 90845}