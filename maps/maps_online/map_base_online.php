<?php
// Récupérer le fichier de tuiles via GET
$tileScript = isset($_GET['tile']) ? $_GET['tile'] : '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Maps Online</title>
<link rel="icon" type="image/png" href="../../favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../../vendor/leaflet/dist/leaflet.css" />
<?php
echo '<link rel="stylesheet" href="../../css/maps.css?t='.time().'">'."\n";
?>
</head>
<body>
  <div id="map"></div>
  <script src="../../vendor/leaflet/dist/leaflet.js"></script>
  
  <script>
    // Détecter mode standalone
    if(window.matchMedia('(display-mode: standalone)').matches){
      document.body.classList.add('standalone');
    }
    
    var defaultLat = 48.854659;
    var defaultLon = 2.347872;
    var defaultZoom = 5;
    var map = L.map('map', { zoomControl: false }).setView([defaultLat, defaultLon], defaultZoom);

    // Fonction pour se centrer sur la géolocalisation
    function centerOnLocation(){
      if(navigator.geolocation){
        navigator.geolocation.getCurrentPosition(
          function(pos){
            var latlng = [pos.coords.latitude, pos.coords.longitude];
            //map.setView(latlng, Math.min(map.getZoom(), 16));
            map.flyTo([pos.coords.latitude, pos.coords.longitude], 16); // Contrairement à la ligne au-dessus, là on force le recentrage à zoomer
            if(!userMarker){
              userMarker = L.marker(latlng).addTo(map);
            } else {
              userMarker.setLatLng(latlng);
            }
          },
          function(err){ console.warn("Erreur géolocalisation:", err.message); },
          { enableHighAccuracy:true, timeout:5000, maximumAge:0 }
        );
      } else {
        alert("La géolocalisation n'est pas supportée par ce navigateur.");
      }
    }
    
    // Ajout du bouton de géolocalisation (en bas à gauche)
    L.Control.Locate = L.Control.extend({
      onAdd: function(map){
        var btn = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-locate');
        btn.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>`;
        btn.title = "Recentrer sur ma position";
        
        L.DomEvent.on(btn, 'click', function(e){
          L.DomEvent.stopPropagation(e);
          L.DomEvent.preventDefault(e);
          centerOnLocation();
        });
        
        return btn;
      }
    });
    L.control.locate = function(opts){ return new L.Control.Locate(opts); }
    L.control.locate({ position:'bottomleft' }).addTo(map);
    
    // Positionnement pour les boutons permettant de contrôler le zoom
    L.control.zoom({ position:'bottomright' }).addTo(map);
    
    // Géolocalisation
    if(navigator.geolocation){
      navigator.geolocation.getCurrentPosition(function(pos){
        map.flyTo([pos.coords.latitude, pos.coords.longitude], defaultZoom);
        L.marker([pos.coords.latitude, pos.coords.longitude])
          .addTo(map)
          //.bindPopup("Vous êtes ici")
          .openPopup();
      });
    }
    
    // Envoyer le zoom actuel au parent
    function sendZoom(){
      if(window.parent) window.parent.postMessage({type:"zoom", value:map.getZoom()}, "*");
    }
    sendZoom();
    map.on("zoomend", sendZoom);
    
    // Réception des commandes depuis le parent
    window.addEventListener('message', function(event){
      // N'accepter que la même origine
      if (event.origin !== window.location.origin) return;
      const data = event.data || {};

      if (data.type === 'zoomIn') {
        if (map && typeof map.zoomIn === 'function') {
          map.zoomIn();
          sendZoom();
        }
      } else if (data.type === 'zoomOut') {
        if (map && typeof map.zoomOut === 'function') {
          map.zoomOut();
          sendZoom();
        }
      } else if (data.type === 'locate') {
        if (typeof centerOnLocation === 'function') centerOnLocation();
      }
    });
  </script>
  
  <?php
  // Inclure le fichier de tuiles si spécifié
  if($tileScript && file_exists($tileScript)){
      echo '<script src="'.htmlspecialchars($tileScript).'"></script>';
  }
  ?>
  
  <script>
    // Se positionner suivant la géolocalisation
    document.addEventListener("DOMContentLoaded", function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                map.flyTo([pos.coords.latitude, pos.coords.longitude], 16);
            });
        }
    });
    
    function initMapFlyTo() {
      if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(function(pos) {
              map.flyTo([pos.coords.latitude, pos.coords.longitude], 16);
          });
      }
    }
  </script>
</body>
</html>