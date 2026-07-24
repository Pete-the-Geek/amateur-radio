<?php
$output .= "<script>
    (function() {
        // Initialize map targeting our specific div ID
        var map = L.map('osm-hub-map');

        // --- NEW: Fullscreen Button Control ---
        var FullScreenControl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd: function (map) {
                // Create a button that matches standard Leaflet UI styling
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.innerHTML = '<a href=\"#\" title=\"Toggle Fullscreen\" style=\"font-size: 20px; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; color: #333; background-color: #fff; line-height: 1;\">⛶</a>';
                
                container.onclick = function(e){
                    e.preventDefault();
                    var mapElement = document.getElementById('osm-hub-map');
                    
                    // Toggle HTML5 Fullscreen API with cross-browser fallbacks
                    if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                        if (mapElement.requestFullscreen) mapElement.requestFullscreen();
                        else if (mapElement.webkitRequestFullscreen) mapElement.webkitRequestFullscreen();
                        else if (mapElement.msRequestFullscreen) mapElement.msRequestFullscreen();
                    } else {
                        if (document.exitFullscreen) document.exitFullscreen();
                        else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
                        else if (document.msExitFullscreen) document.msExitFullscreen();
                    }
                }
                return container;
            }
        });
        map.addControl(new FullScreenControl());

        // Ensure Leaflet redraws tiles properly when entering/exiting fullscreen
        document.addEventListener('fullscreenchange', function() {
            setTimeout(function() { map.invalidateSize(); }, 200);
        });
        document.addEventListener('webkitfullscreenchange', function() {
            setTimeout(function() { map.invalidateSize(); }, 200);
        });
        // --------------------------------------

        // 2. Add the OpenTopoMap (OSM Terrain) tile layer
        L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data: &copy; OpenStreetMap contributors, SRTM | Map style: &copy; OpenTopoMap'
        }).addTo(map);

        /*
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        */

        // Custom Red Pin
        var redIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

		// Hub Definition
		var hub = L.latLng(".$points[0]['latitude'].", ".$points[0]['longitude']."); // Base
		L.marker(hub, {icon: redIcon}).addTo(map).bindPopup(\"<b>Base: ".$points[0]['name']."</b>\").openPopup();

		// Destinations (Added a few more so the 3 columns look populated)
		var destinations = [";
			for ($x = 1; $x < (count($points)); $x++){
					$output .= "{ 
						name: \"";
							if ($points[$x]['form_id']) $output .= "<a href='javascript:document.getElementById(\\\"".$points[$x]['form_id']."\\\").submit();'>";
							$output .= $points[$x]['name'];
							if ($points[$x]['form_id']) $output .= "</a>";
							$output .= "\", 
						coords: L.latLng(".$points[$x]['latitude'].", ".$points[$x]['longitude'].")
					},";
				};
		$output .= "];

        // 1. Calculate distances
        destinations.forEach(function(dest) {
            dest.distanceMeters = hub.distanceTo(dest.coords);
            dest.distanceKm = (dest.distanceMeters / 1000).toFixed(1);
        });

        // 2. Sort by distance (closest to furthest)
        destinations.sort(function(a, b) {
            return a.distanceMeters - b.distanceMeters;
        });

        // 3. Draw sorted map elements and build the list HTML
        var allBounds = [hub]; 
        var listHTML = \"\";

        destinations.forEach(function(dest) {
            allBounds.push(dest.coords);

            L.marker(dest.coords).addTo(map).bindPopup(dest.name);
/*
            marker.on('click', function() {
                window.open(dest.url, '_blank');
            });
*/	
            L.polyline([hub, dest.coords], {color: 'blue', weight: 3, opacity: 0.7}).addTo(map);
            
            // Build the individual list items, preventing them from breaking across columns
            listHTML += \"<div style='break-inside: avoid; margin-bottom: 8px;'>• \" + dest.name + \": \" + dest.distanceKm + \" km (\" + (dest.distanceKm * 0.621371).toFixed(1)  + \" miles)</div>\";
        });

		// 4. Construct the final HTML with the 3-column layout
		var finalHTML = 
			\"<div style='font-weight: bold; margin-bottom: 15px;'>Distances from ".$points[0]['name']." (Closest to Furthest):</div>\" +
			\"<div style='column-count: ".(count($points) > 20 ? "5" : "3")."; column-gap: 20px;'>\" + listHTML + \"</div>\";

        // Update the specific info div
        document.getElementById('osm-hub-info').innerHTML = finalHTML;
        map.fitBounds(L.latLngBounds(allBounds), { padding: [50, 50] });

    })();
</script>
";
?>