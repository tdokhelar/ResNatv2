var geocoderJS;
var geocodingProcessing = false;
var firstGeocodeDone = false;
var geocodedFormatedAddress = '';
var geocodeResult = null;
var marker = null

import { createMarker } from './init-map'
import 'universal-geocoder/dist/universal-geocoder'

function getInputAddress() { return $('#input-address').val(); }

jQuery(document).ready(function()
{
	geocoderJS = UniversalGeocoder.createGeocoder({provider: "nominatim", useSsl: true, userAgent: "GoGoCarto"});

	// Geocoding address
	$('#input-address').change(function ()
  {
    if (!firstGeocodeDone) handleInputAdressChange();
  });

	$('#input-address').keyup(function(e)
	{
		if(e.keyCode == 13) // touche entrée
		{
			handleInputAdressChange();
		}
	});

	$('.btn-geolocalize').click(function () { handleInputAdressChange(); });
});

function handleInputAdressChange()
{
	geocodeAddress(getInputAddress());
}

async function getPostalCode(lat, lon) {

	const urlSearchParams = new URLSearchParams({
		lat: lat,
		lon: lon,
		fields: 'nom,code,codesPostaux',
		format: 'json'
	});
	const url = 'https://geo.api.gouv.fr/communes?' + urlSearchParams;
	
	const response = await fetch(url, { 
		method: 'GET',
		headers: new Headers(),
		mode: 'cors',
		cache: 'default'
	});
	const json = await response.json();
	
	if ( !json[0].codesPostaux[0] ) { 
		console.log('Code postal non trouvé : ', {lat, lon, json});
		return undefined;
	} else {
		return json[0].codesPostaux.sort()[0];	
	}
}

export function geocodeAddress(address, forcedCountryCode = null) {

	console.log("geocodeAddress", {"address": address, "countryCode": forcedCountryCode});
	
	if (forcedCountryCode && forcedCountryCode.length !== 2) {
		forcedCountryCode = null;
	}
	
	let countryCodes = [];
	let bounds = [];
	let bounded = false;

	if (forcedCountryCode) {
		countryCodes = [forcedCountryCode];
	} else {
		switch (geocodingBoundsType) {
			case 'countryCodes':
				countryCodes = geocodingBoundsByCountryCodes.split(',');
				break;
			case 'defaultView':
				bounded = true;
				bounds = defaultBounds;
				break;
			case 'viewPicker':
				bounded = true;
				bounds = geocodingBounds;
				break;
		}
	}

	if (geocodingProcessing || !address) { console.log("Already processing or no address provided", address); return null; }

	$('#geocode-spinner-loader').show();

	geocodingProcessing = true;
	
	geocoderJS.geocode({
			text: address, 
			countryCodes: countryCodes,
			bounded: bounded,
			viewBox: bounded ? [bounds[0][1], bounds[0][0], bounds[1][1], bounds[1][0]] : null, /* (longitude first) */
			limit: 1
		}, async function(results)
	{
		if (results !== null && results.length > 0)
		{
			firstGeocodeDone = true;
			geocodeResult = results[0];
			window.map.setView(results[0].getCoordinates(), 18);
			marker = createMarker(results[0].getCoordinates());
			
			if ( !geocodeResult.postalCode && geocodeResult.countryCode === "fr" && geocodeResult.latitude && geocodeResult.longitude ) {
				const postalCodeByAPI = await getPostalCode(geocodeResult.latitude, geocodeResult.longitude);
				if (postalCodeByAPI) { results[0].postalCode = postalCodeByAPI }
			} 

			console.log("Geocode result :", results[0]);
			$(window).trigger('geocoded', results[0]);

			// Detect street address when geocoder fails to retrieve it (OSM case)
			var patt = new RegExp(/^\d+/g);
			var potentialStreetNumber = patt.exec(address);
			var streetNumber = results[0].streetNumber;
			if (!streetNumber && potentialStreetNumber && potentialStreetNumber != results[0].postalCode && results[0].streetName) {
				// console.log("street number detected", potentialStreetNumber[0]);
				streetNumber = potentialStreetNumber[0];
			}

			geocodedFormatedAddress = "";
			if (streetNumber) geocodedFormatedAddress += streetNumber + ' ';
			if (results[0].streetName) geocodedFormatedAddress += results[0].streetName + ', ';
			if (results[0].postalCode) geocodedFormatedAddress += results[0].postalCode + ' ';
			if (results[0].locality) geocodedFormatedAddress += results[0].locality;

			$('#input-latitude').val(results[0].latitude);
			$('#input-longitude').val(results[0].longitude);
			$('#input-postalCode').val(results[0].postalCode);
			$('#input-addressCountry').val(results[0].countryCode);
			$('#input-addressLocality').val(results[0].locality);
			$('#input-streetAddress').val(results[0].streetName);
			$('#input-streetNumber').val(streetNumber);

			$('#input-address').val(geocodedFormatedAddress);

			$('#input-address').closest('.input-field').removeClass("error");
			$('#input-address').removeClass('invalid');
			$('#geocode-error').hide();
		}
		else if ($('#input-address').length > 0)
		{
			$('#input-address').addClass("invalid");
			$('#input-address').closest('.input-field').addClass("error");

			if (marker) marker.remove();

			$('.geolocalize-help-text').show();

			$('#input-latitude').val('');
			$('#input-longitude').val('');
			$('#input-postalCode').val('');
			$('#input-addressLocality').val('');
			$('#input-addressCountry').val('');
			$('#input-streetAddress').val('');
			$('#input-streetNumber').val('');

			console.log("geocoding error", status);
		} else {
			$('#geocode-error').text(t('js.element_form.geocode_error', {address: address})).show()
		}

		$('#geocode-spinner-loader').hide();
		geocodingProcessing = false;
	});
}

export function checkCustomFormatedAddressBeforeSend()
{
	if (getInputAddress() != geocodedFormatedAddress)
	{
		$('#input-customFormatedAddress').val(getInputAddress());
	}
	else
	{
		$('#input-customFormatedAddress').val(null);
	}
}