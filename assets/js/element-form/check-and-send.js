import { checkCustomFormatedAddressBeforeSend } from './geocode-address'

window.checkAndSend = function(submitOption)
{
	if (!$('#section_admin').is(':visible')) checkAgreeConditions();
	checkOpenHours();
	checkRequiredFields();

	// Dealing with error class
	$('.invalid, .invalid-required').each(function ()
	{
		$(this).closest('.input-field').addClass('error');
	});

	$('.valid, .validate:not(.invalid)').each(function ()
	{
		$(this).closest('.input-field').removeClass('error');
	});

	$('.input-field.error input, .input-field.error textarea').focusout(function()
	{
		if ($(this).hasClass('valid') || ($(this).hasClass('validate') && !$(this).hasClass('invalid')))
		{
			$(this).closest('.input-field').removeClass('error');
		}
		checkRequiredFields();
	});
	$('.checkbox-radio-group.invalid-required input, .select-input select').change(function()
	{
		checkRequiredFields();
	});

	checkAddressGeolocalisation();

	checkCategories()

	var errorCount = $('.error:visible:not(.flash-message), .invalid:visible').length;

	if (errorCount === 0)
	{
		checkCustomFormatedAddressBeforeSend(); // defined in geocode-address.js
		// add submit option for handling multiple submit buttons
		$('input#submit-option').val(submitOption);
		$('input[type=file]').each(function() {
			if (!$(this).val() && !$(this).attr('value')) $(this).remove();
		})
		localStorage.setItem('element-form-submitted', true);
		$('form').submit();
	}
	else  $('html,body').animate({scrollTop: $('.error:visible, .invalid:visible').first().offset().top - 80}, 'slow');

}

function checkCategories()
{
	window.apps.forEach(app => {
		if (app.$refs.taxonomyPicker) app.$refs.taxonomyPicker.checkValidity()
	})
}

function checkAgreeConditions()
{
	if (!$('#agree').is(':checked')) $('#label-agree').addClass('error');
	else $('#label-agree').removeClass('error');
}

function checkOpenHours()
{
	$('.timepicker_1, .timepicker_3').each(function()
	{
		var id = $(this).attr('id');
		var id_2e_plage = id.split("-input-")[0] + "-input-" + (parseInt(id.split("-input-")[1]) + 1);
		var value_1 = $(this).val();
		if (value_1 === "") value_1 = null;
		var value_2 = $('#'+id_2e_plage).val();
		if (value_2 === "") value_2 = null;

		if (value_1)
		{
			$(this).parents(".open-hours-container").removeClass('open-day');
			$(this).removeClass('invalid');

			if(!value_2)
			{
				$('#'+id_2e_plage).addClass('invalid');
			}
			else
			{
				$('#'+id_2e_plage).removeClass('invalid');
				$(this).parents(".open-hours-container").addClass('open-day');
			}
		}
		else if (value_2) $(this).addClass('invalid');
	});
}

function checkAddressGeolocalisation()
{
	if (!$('#input-latitude').val() || $('#input-latitude').val() == 0 ||
	  !$('#input-longitude').val() || $('#input-longitude').val() == 0) {
		$('#input-address-field').addClass("error");
	}
}

function checkRequiredFields()
{
	$('.required').each(function ()
	{
		if ($(this).hasClass('required-only-add') && editMode) return;

		var valuePresent = false;
		if ($(this).hasClass('checkbox-radio-group')) valuePresent = ($(this).find('input:checked').length > 0);
		else if ($(this).hasClass('ace-editor') && CKEDITOR) {
			valuePresent = CKEDITOR.instances[$(this).attr('id')].getData().length > 0
		} else if ($(this).hasClass('select-input') || $(this).hasClass('select-elements-input')) valuePresent = ($(this).find('option:selected:not(:disabled)').length > 0);
		else valuePresent = $(this).val();

		if (!valuePresent) {
			$(this).addClass('invalid invalid-required');
		} else {
			$(this).removeClass('invalid-required');
			if ($(this).hasClass('select-input') || $(this).hasClass('select-elements-input') || $(this).hasClass('checkbox-radio-group') || $(this).hasClass('ace-editor')) $(this).removeClass('invalid').removeClass('error');
		}
	});
}

