jQuery(document).ready(function()
{	
	if (window.location.search.includes('logout') )
	{
		window.location.href = window.location.origin + window.location.pathname;
	}	

	$('#btn-login').on( "login", function() {
  	window.location.reload();
	});

	$('#btn-login').on( "logout", function() {
  	window.location.href = '?logout=1';
	});
});

window.checkLoginAndSend = function()
{
	$('.required').each(function ()
	{ 
		if(!$(this).val()) $(this).addClass('invalid');		
		else $(this).closest('.input-field').removeClass('error');
	});
	
	$('.invalid').each(function ()
	{ 		
		$(this).closest('.input-field').addClass('error');
	});

	$('.valid').each(function ()
	{ 		
		$(this).closest('.input-field').removeClass('error');
	});

	if ($('.error:visible, .invalid:visible').length === 0)
	{
		$('#inputMail').removeClass('invalid');
		$('#inputMail').siblings('i').removeClass('invalid');

		$('form[name="user"]').submit();		
	}
}