<?php
// Récupérer le paramètre tile
$tileScript = isset($_GET['tile']) ? basename($_GET['tile']) : '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Maps Local</title>
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
    
    // Initialisation de la carte
    var map = L.map('map', { zoomControl:false }).setView([48.854659,2.347872], 5);
    
    // Contrôle de zoom en bas à droite
    L.control.zoom({ position:'bottomright' }).addTo(map);
    
    // Charger les tuiles
    var tileFile = <?php echo json_encode("$tileScript"); ?>;
    
    L.tileLayer(tileFile + "?z={z}&x={x}&y={y}", {
      minZoom: 3,
      maxZoom: 11,
      tms: false,
      //crossOrigin: true
    }).addTo(map);
    
    // Fonction pour se centrer sur la géolocalisation
    function centerOnLocation(){
      if(navigator.geolocation){
        navigator.geolocation.getCurrentPosition(
          function(pos){
            var latlng = [pos.coords.latitude, pos.coords.longitude];
            //map.setView(latlng, Math.min(map.getZoom(), 11));
            map.flyTo([pos.coords.latitude, pos.coords.longitude], 11); // Contrairement à la ligne au-dessus, là on force le recentrage à zoomer
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
    
    // Géolocalisation
    if(navigator.geolocation){
      navigator.geolocation.getCurrentPosition(
        function(pos){
          map.flyTo([pos.coords.latitude,pos.coords.longitude], 11);
          L.marker([pos.coords.latitude,pos.coords.longitude])
            .addTo(map)
            //.bindPopup("Vous êtes ici")
            .openPopup();
        },
        function(err){ console.warn("Erreur géolocalisation:", err.message); },
        { enableHighAccuracy:true, timeout:5000, maximumAge:0 }
      );
    } else { console.warn("Géolocalisation non supportée"); }
    
    // Envoyer le zoom actuel au parent
    function sendZoom(){ if(window.parent) window.parent.postMessage({type:"zoom", value:map.getZoom()},"*"); }
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
    
    // Se positionner suivant la géolocalisation
    document.addEventListener("DOMContentLoaded", function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                map.flyTo([pos.coords.latitude, pos.coords.longitude], 11);
            });
        }
    });
    
    function initMapFlyTo() {
      if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(function(pos) {
              map.flyTo([pos.coords.latitude, pos.coords.longitude], 11);
          });
      }
    }
  </script>
</body>
</html>