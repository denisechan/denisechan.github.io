$(function() {
	//$('.form.signup-form').addClass('hidden');
	$('#no-account').on('change', function() {
		$('.form').toggleClass('hidden');
		var state = !$('#no-account').is(':checked');
		ajaxRequest('show-login-form', {value: state});
	});
});