$(function() {
	var removeErrorIndicator = function() {
		$(this).closest('.field').removeAttr('data-retry');
		$(this).off('click', removeErrorIndicator);
	};
	
	$('input[data-retry], select[data-retry], textarea[data-retry]').each(function() {
		var val = $(this).attr('data-retry');
		$(this).removeAttr('data-retry');
		$(this).closest('.field').attr('data-retry', val);
		$(this).on('change', removeErrorIndicator);
		$(this).on('keyup', removeErrorIndicator);
	});
	
	$('#confirm-email').on('click', function() {
		$(this).animate({opacity: 0}, 200, 'linear', function() { $(this).remove(); });
	});
	
	setInterval(function() {
		$('.clock').each(function() {
			var me = $(this);
			
			var es = me.find('.sec');
			var em = me.find('.min');
			var eh = me.find('.hr');
			var ed = me.find('.day');
			
			var sec = 	parseInt(es.text());
			var min = 	parseInt(em.text());
			var hr = 	parseInt(eh.text());
			var day = 	parseInt(ed.text());
			
			//Here's the weirdest formatting you've ever seen; makes the clock tick
			var daysPerMonth = function(month) { 
				return 30;
			}
			//TODO: Days per month???
			if (!me.hasClass('down')) {
				if (++sec >= 60) {
					sec = 0; if (++min >= 60) {
					min = 0; if (++hr >= 60) {
					hr = 0; ++day;
				}}}
			} else {
				if (--sec < 0) {
					sec = 59; if (--min < 0) {
					min = 59; if (--hr < 0) {
					hr = 23; --day;
				}}}
			}
			
			es.text(sec);
			em.text(min);
			eh.text(hr);
			ed.text(day);
			
			var pd = ed.closest('.item');
			var ph = eh.closest('.item');
			pd.removeClass('gone');
			ph.removeClass('gone');
			if (day === 0) {
				pd.addClass('gone');
				if (hr === 0) ph.addClass('gone');
			}
		});
	}, 1000);
	
	//The header can be removed with a click, or waiting 10 seconds
	$('#header').on('click', '.notification', function() {
		$(this).animate({opacity: 0}, 300, 'swing', function() {
			$(this).remove();
		});
	});
	var notify = $('#header > .notification');
	if (notify.length > 0) {
		setTimeout(function() {
			notify.animate({opacity: 0}, 1000, 'swing', function() {
				notify.remove();
			});
		}, 10000);
	}
});