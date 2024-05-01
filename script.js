document.addEventListener('DOMContentLoaded', function () {
  var map = L.map('map').setView([48.8566, 2.3522], 13); // Paris as center
  var gogocarto = new GogoCarto({
    map: map,
    gogocartoUrl: 'https://gogocarto.fr',
    layers: ['services', 'contributors']
  });
  gogocarto.loadLayers();
});
