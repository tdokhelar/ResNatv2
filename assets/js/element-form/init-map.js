var marker, markerPosition, mapZoom, firstGeocodeDone;

document.addEventListener('DOMContentLoaded', function() {
	if ($('#address-preview-map').length > 0) initMap()
})

function initMap()
{
	var mapCenter;
	if ($('#input-latitude').val() && $('#input-latitude').val() != 0 &&
	    $('#input-longitude').val() && $('#input-longitude').val() != 0)
	{
		markerPosition = new L.LatLng($('#input-latitude').val(), $('#input-longitude').val());
		mapCenter = markerPosition;
		mapZoom = 18;
		firstGeocodeDone = true;
	}
	else
	{
		markerPosition = null;
		mapCenter = new L.LatLng(46.897045, 2.425235);
		mapZoom = 5;
	}

	window.map = L.map('address-preview-map', {
	    center: mapCenter,
	    zoom: mapZoom,
	    zoomControl: true,
	    scrollWheelZoom : false
	});

	// defaultBounds is set in address.html.twig
	if (!firstGeocodeDone && defaultBounds) window.map.fitBounds(defaultBounds);

	L.tileLayer(defaultTileLayer.url, {
		attribution: defaultTileLayer.attribution,
		maxZoom: defaultTileLayer.maxZoom || 20
	}).addTo(window.map);

	if (markerPosition) createMarker(markerPosition);
}

export function createMarker(position)
{
	if (marker) marker.remove();

	marker = new L.Marker(position, { draggable: true } ).addTo(window.map);
	marker.on('dragend', function()
	{
	  $('#input-latitude').attr('value',marker.getLatLng().lat);
		$('#input-longitude').attr('value',marker.getLatLng().lng);
  });

  marker.bindPopup(`<center>${t('js.element_form.geocoded_marker_text')}</center>`).openPopup();

	return marker
}
