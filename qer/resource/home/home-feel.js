$(function() {
	if (typeof user !== 'undefined' && typeof line !== 'undefined') {
		if (typeof Android !== 'undefined') {
			if (typeof Android.startServ === 'function') {
				Android.startServ(user, line);
			}
		}
	}
	
	$('.split > .pane').on('click', function() {
		var c = $(this).hasClass('left') 
		? 'left' : ($(this).hasClass('right') ? 'right' : '');
		var split = $(this).closest('.split');
		split.removeClass('left');
		split.removeClass('right');
		split.addClass(c);
		
		var timeout = split.data('timeout');
		if (timeout !== null) clearTimeout(timeout);
		
		split.data('timeout', setInterval(function() {
			split.removeClass(c);
		}, 10000));
	});
});