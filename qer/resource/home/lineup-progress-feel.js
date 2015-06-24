$(function() {
	
	if (typeof Android !== 'undefined') {
		if (typeof Android.startServ === 'function') {
			Android.startServ(user, line);
		}
	}
	
	var processed = false;
	
	var updateInterval = setInterval(function() {
		ajaxRequest('line-data', {}, function(results) {
			if (processed) return;
			
			var bod = $('.page.lineup-progress > .content > .ticket-body');
			
			//Unprocessed
			bod.find('.wait-number').html(results['historical-position']);
			bod.find('.wait-time').html(results['wait-clock']);
			bod.find('.estimate-time').html(results['estimate-clock']);
			bod.find('.line-length').html(results['line-length']);
			bod.find('.line-ahead').html(results['line-ahead']);
			
			//Processed
			bod.children('.processed').html(results['process-content']);
			
			var procCont = results['process-content'];
			if (procCont !== null && procCont.length > 0) processed = true;
			
			if (!processed) {
				bod.removeClass('processed');
			} else {
				//We can safely stop updating now
				clearInterval(updateInterval);
				
				bod.addClass('processed');
				var cont = bod.find('.processed a.continue');
				cont.css('opacity', 0);
				cont.animate({opacity: 1}, 1000, 'swing', function() { cont.css('opacity', ''); });
			}
		});
	}, 2002);
});