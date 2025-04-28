<?php
// ** Database Connection **
$host = 'localhost';
$db = 'ventech_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    handleError("Database connection failed: " . $e->getMessage());
}

// ** Utility Functions **
function handleError($message, $isWarning = false) {
    $style = 'padding:15px; margin-bottom: 15px; border-radius: 4px;';
    if ($isWarning) {
        $style .= 'color: #856404; background-color: #fff3cd; border-color: #ffeeba;';
        echo "<div style='{$style}'>" . htmlspecialchars($message) . "</div>";
        return;
    }
    error_log("Venue Locator Error: " . $message);
    die("<div style='{$style}'>" . htmlspecialchars($message) . "</div>");
}

function fetchData(PDO $pdo, $query, $params = []): array|false {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage() . " Query: " . $query);
        return false;
    }
}

function getUniqueAmenities(array $venues): array {
    $allAmenities = [];
    foreach ($venues as $venue) {
        if (!empty($venue['amenities'])) {
            $amenitiesArray = array_map('trim', explode(',', $venue['amenities']));
            $allAmenities = array_merge($allAmenities, $amenitiesArray);
        }
    }
    $uniqueAmenities = array_unique($allAmenities);
    sort($uniqueAmenities);
    return $uniqueAmenities;
}

// ** Fetch Data **
$venues = fetchData($pdo, "SELECT id, title, latitude, longitude, image_path, amenities, price, status FROM venue WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
$uniqueAmenities = getUniqueAmenities($venues ?: []);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJHoWIiFsp9vF5+RmJMdxG1j97yrHDNHPxmalkGcJA==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZs1Kkgc8PU1cKB4UUplusxX7j35Y==" crossorigin=""></script>
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        #map-container { width: 100%; height: 100vh; }
        #map { width: 100%; height: 500%; }
        #filter-container {
            padding: 10px;
            background-color: #f8f8f8;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        #search-container { flex-grow: 1; }
        #venue-search {
            width: 95%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
        }
        .amenity-filter { display: flex; gap: 10px; align-items: center; }
        .amenity-filter label { display: flex; align-items: center; font-size: 0.9em; }
        .amenity-filter input[type="checkbox"] { margin-right: 5px; }
        .venue-card {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: white;
        }
        .venue-card h3 { margin-top: 0; margin-bottom: 5px; font-size: 1.2em; }
        .venue-card p { margin-bottom: 3px; font-size: 0.9em; }
        .venue-card a { color: blue; text-decoration: none; font-size: 0.9em; }
        .venue-card a:hover { text-decoration: underline; }
        .leaflet-popup-content-wrapper {
            width: 400px !important; /* Adjust as needed */
            max-height: 450px !important; /* Adjust as needed */
            overflow-y: auto; /* Add scroll if content exceeds max height */
        }
        .leaflet-popup-content {
            margin: 0 !important; /* Remove default margin */
        }
        .popup-venue-card {
            /* Styles for the card within the popup */
        }
        .popup-venue-card .h-48 {
            height: 150px !important; /* Adjust image height */
        }
    </style>
</head>
<body>
    <div id="filter-container">
        <div id="search-container">
            <input type="text" id="venue-search" placeholder="Search for a venue...">
        </div>
        <div class="amenity-filter">
            <strong>Filter by Amenities:</strong>
            <?php foreach ($uniqueAmenities as $amenity): ?>
                <label>
                    <input type="checkbox" name="amenity" value="<?php echo htmlspecialchars(strtolower($amenity)); ?>">
                    <?php echo htmlspecialchars(ucfirst($amenity)); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="map-container">
        <div id="map"></div>
    </div>

    <script>
        const map = L.map('map').setView([14.4797, 120.9936], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

  let venueMarkers = [];
        const venuesData = <?php echo json_encode($venues); ?>;
        const uploadsBaseUrl = '/ventech_locator/uploads/';
        const placeholderImg = 'https://via.placeholder.com/400x250?text=No+Image';

        if (venuesData) {
            venuesData.forEach(venue => {
                let imgSrc = placeholderImg;
                if (venue.image_path) {
                    imgSrc = uploadsBaseUrl + venue.image_path.replace(/^\/+/, '');
                }

                const popupContent = `
                    <div class="popup-venue-card border rounded-lg shadow-lg overflow-hidden bg-white">
                        <img src="${imgSrc}" alt="${venue.title}" class="w-full h-48 object-cover" onerror="this.src='${placeholderImg}'" />
                        </a>
                        <div class="p-4 flex flex-col flex-grow">
                            <p class="text-xs text-gray-500 mb-1">
                                Status: <span class="font-medium ${venue.status === 'open' ? 'text-green-600' : 'text-red-600'}">
                                    ${venue.status ? venue.status.charAt(0).toUpperCase() + venue.status.slice(1) : ''}
                                </span>
                            </p>
                            <h3 class="text-lg font-semibold text-gray-800 hover:text-orange-600 mb-2">
                                <a href="venue_display.php?id=${venue.id}">${venue.title}</a>
                            </h3>

                            <p class="text-sm text-gray-600 mb-1">Starting from</p>
                            <p class="text-xl font-bold text-gray-900 mb-3">₱ ${venue.price ? parseFloat(venue.price).toFixed(2) : '0.00'} <span class="text-xs font-normal">/ Hour</span></p>

                            <div class="flex items-center text-sm text-gray-500 mb-4">
                                <div class="flex text-yellow-400">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><i class="far fa-star"></i>
                                </div>
                                <span class="ml-2">(0 Reviews)</span>
                            </div>

                            <div class="mt-auto pt-3 border-t border-gray-200 flex space-x-3">
                                <a href="venue_display.php?id=${venue.id}" class="flex-1 text-center px-3 py-2 bg-orange-500 text-white text-xs font-bold rounded hover:bg-orange-600 transition shadow-sm">
                                    <i class="fas fa-info-circle mr-1"></i> DETAILS
                                </a>
                                ${venue.status === 'open' ? `
                                <a href="/ventech_locator/venue_reservation_form.php?venue_id=${venue.id}&venue_name=${encodeURIComponent(venue.title)}" class="flex-1 text-center px-3 py-2 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700 transition shadow-sm">
                                    <i class="fas fa-calendar-check mr-1"></i> RESERVE NOW
                                </a>
                                ` : `
                                <span class="flex-1 text-center px-3 py-2 bg-gray-400 text-white text-xs font-bold rounded cursor-not-allowed" title="Venue is currently closed for reservations">
                                    <i class="fas fa-calendar-times mr-1"></i> CLOSED
                                </span>
                                `}
                            </div>
                        </div>
                    </div>
                `;
                const marker = L.marker([venue.latitude, venue.longitude]).bindPopup(popupContent).addTo(map);
                venueMarkers.push({
                    marker: marker,
                    title: venue.title.toLowerCase(),
                    amenities: venue.amenities ? venue.amenities.toLowerCase() : ''
                });
            });
        } else {
            console.log("No venue locations found in the database.");
        }

        const venueSearchInput = document.getElementById('venue-search');
        const amenityCheckboxes = document.querySelectorAll('.amenity-filter input[type="checkbox"]');

        function filterVenues() {
            const searchTerm = venueSearchInput.value.toLowerCase();
            const selectedAmenities = Array.from(amenityCheckboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => checkbox.value);

            venueMarkers.forEach(venueObj => {
                const titleMatch = venueObj.title.includes(searchTerm);
                let amenityMatch = true;

                if (selectedAmenities.length > 0) {
                    amenityMatch = selectedAmenities.every(amenity => venueObj.amenities.includes(amenity));
                }

                if (titleMatch && amenityMatch) {
                    venueObj.marker.addTo(map);
                } else {
                    map.removeLayer(venueObj.marker);
                }
            });
        }

        venueSearchInput.addEventListener('input', filterVenues);
        amenityCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', filterVenues);
        });
    </script>
</body>
</html>