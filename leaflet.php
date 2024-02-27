<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rdr2_mapped";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch pins data
$sql = "SELECT pins.id, pins.name, pins.latitude, pins.longitude, pins.category, pins_categories.name AS category_name
        FROM pins
        INNER JOIN pins_categories ON pins.category = pins_categories.id";
$result = $conn->query($sql);

// Prepare an array to hold pins grouped by category
$pinsByCategory = array();

if ($result->num_rows > 0) {
    // Group pins by category
    while ($row = $result->fetch_assoc()) {
        $pinsByCategory[$row['category_name']][] = $row;
    }
}

// Fetch category icons from the database
$sql = "SELECT name, icon_image FROM pins_categories";
$result = $conn->query($sql);

// Prepare an array to hold category icons
$categoryIcons = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Convert the image data to a format compatible with Leaflet icons
        $categoryIcons[$row['name']] = 'data:image/jpeg;base64,' . base64_encode($row['icon_image']);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
  <head>
    <title>Map with Points of Interest</title>
    <!-- Include leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.css">
    <!-- Include leaflet JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.js"></script>
    <style>
      html, body { height: 100%; margin: 0; padding: 0; }
      .container { display: flex; height: 100%; }
      #map-container { flex: 1; background-color: #D4B891; }
      #map { width: 100%; height: 100%; background-color: #D4B891;}
      #left-panel { flex: 0 0 33%; padding: 20px; background-color: #4d4031; }
      #sidebar {
        flex: 0 0 5%;
        padding: 20px;
        background-color: #645440;
        color: white;
      }
      #recenter-btn { margin-top: 5px; }
      #category-toggles { margin-top: 10px; }
      .category-toggle { display: block; margin-bottom: 5px; }
    </style>
  </head>
  <body>
    <div class="container">
      <div id="left-panel">
        <!-- Content for the left panel -->
      </div>
      <div id="sidebar">
        <button id="recenter-btn" style="background-color: #4d4031; border-radius: 5px; padding: 7px; border: none;">
          <img src="icons/recenter_icon.png" alt="Recenter Icon" style="width: 20px; height: 20px;">
        </button>
        <button id="toggle-all-btn" class="toggled-off" style="background-color: #4d4031; border-radius: 5px; padding: 7px; border: none;">
          <img src="icons/check_icon.png" alt="Check Icon" style="width: 20px; height: 20px;">
        </button>
        <div id="category-toggles">
          <!-- Add checkboxes or buttons for each category -->
          <?php foreach ($pinsByCategory as $category => $pins): ?>
              <input type="checkbox" class="category-toggle" id="<?php echo str_replace(' ', '_', $category); ?>"
                    checked="checked">
              <label for="<?php echo str_replace(' ', '_', $category); ?>"><?php echo $category; ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div id="map-container">
        <div id="map"></div>
      </div>
    </div>


    <script type="text/javascript">
      var mapExtent = [0, -5400, 7200, 0];
      var mapMinZoom = 0;
      var mapMaxZoom = 4;
      var mapMaxResolution = 1.00000000;
      var mapMinResolution = Math.pow(2, mapMaxZoom) * mapMaxResolution;
      var tileExtent = [0, -5400, 7200, 0];
      var crs = L.CRS.Simple;
      crs.transformation = new L.Transformation(1, -tileExtent[0], -1, tileExtent[3]);
      crs.scale = function(zoom) {
        return Math.pow(2, zoom) / mapMinResolution;
      };
      crs.zoom = function(scale) {
        return Math.log(scale * mapMinResolution) / Math.LN2;
      };

      // Initialize the map
      var map = L.map('map', {
          maxZoom: mapMaxZoom,
          minZoom: mapMinZoom,
          crs: crs,
          maxBounds: [[mapExtent[1], mapExtent[0]], [mapExtent[3], mapExtent[2]]]
      });

      // Add the tile layer
      var layer = L.tileLayer('{z}/{x}/{y}.png', {
          minZoom: mapMinZoom,
          maxZoom: mapMaxZoom,
          tileSize: L.point(512, 512),
          attribution: '<a href="https://www.maptiler.com/engine/">Rendered with MapTiler Engine</a>',
          noWrap: true,
          tms: false
      }).addTo(map);

      // Fit the map bounds
      map.fitBounds([
        crs.unproject(L.point(mapExtent[2], mapExtent[3])),
        crs.unproject(L.point(mapExtent[0], mapExtent[1]))
      ]);

      // Create custom icons for each category
      var icons = {};
      <?php foreach ($categoryIcons as $category => $iconData): ?>
      icons['<?php echo $category; ?>'] = L.icon({
          iconUrl: '<?php echo $iconData; ?>', // URL of the icon image
          iconSize: [24, 24], // size of the icon
          iconAnchor: [12, 12], // point of the icon which will correspond to marker's location
          popupAnchor: [0, -12] // point from which the popup should open relative to the iconAnchor
      });
      <?php endforeach; ?>

      // Add markers for points of interest
      <?php foreach ($pinsByCategory as $category => $pins): ?>
      var <?php echo str_replace(' ', '_', $category); ?> = L.layerGroup();
      <?php foreach ($pins as $pin): ?>
      L.marker([<?php echo $pin['latitude']; ?>, <?php echo $pin['longitude']; ?>], {icon: icons['<?php echo $category; ?>']})
          .bindPopup("<b><?php echo $pin['name']; ?></b><br />Category: <?php echo $category; ?>")
          .addTo(<?php echo str_replace(' ', '_', $category); ?>);
      <?php endforeach; ?>
      <?php echo str_replace(' ', '_', $category); ?>.addTo(map);
      <?php endforeach; ?>

      // Add event listener to recenter button
      document.getElementById('recenter-btn').addEventListener('click', function() {
        map.setView([0, 0], 0); // Set the map view to the initial center and zoom level
      });

      document.getElementById('toggle-all-btn').addEventListener('click', function() {
        var toggleButtons = document.querySelectorAll('.category-toggle');
        var allChecked = true;
        toggleButtons.forEach(function(button) {
          if (!button.checked) {
            allChecked = false;
          }
        });
        toggleButtons.forEach(function(button) {
          button.checked = !allChecked;
          var event = new Event('change');
          button.dispatchEvent(event);
        });
      });

      <?php foreach ($pinsByCategory as $category => $pins): ?>
      document.getElementById('<?php echo str_replace(' ', '_', $category); ?>').addEventListener('change', function () {
        if (this.checked) {
            map.addLayer(<?php echo str_replace(' ', '_', $category); ?>);
        } else {
            map.removeLayer(<?php echo str_replace(' ', '_', $category); ?>);
        }
      });
      <?php endforeach; ?>
    </script>
  </body>
</html>
