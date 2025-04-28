<?php
// **1. Start Session** (MUST be the very first thing)
session_start();

// **2. Database Connection Parameters**
$host = 'localhost';
$db   = 'ventech_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];



// **3. Initialize User Session Variables**
$isLoggedIn = false;
$username = '';
$userRole = '';
$dashboardLink = '#'; // Default link
$logoutLink = '#'; // Default link

// **4. Establish PDO Connection and Fetch Data**
$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // **5. Check Session and Fetch User Data if Logged In**
    if (isset($_SESSION['user_id'])) {
        $loggedInUserId = $_SESSION['user_id'];
        // Prepare statement to fetch username and role
        $stmt_user = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $userData = $stmt_user->fetch();

        if ($userData) {
            $isLoggedIn = true;
            $username = $userData['username'];
            $userRole = strtolower($userData['role'] ?? 'user'); // Default to 'user'

            // Determine dashboard and logout links based on role (ADJUST PATHS AS NEEDED)
            if ($userRole === 'client' || $userRole === 'owner') {
                $dashboardLink = '/ventech_locator/client/client_dashboard.php';
                $logoutLink = '/ventech_locator/client/client_logout.php';
            } elseif ($userRole === 'admin') {
                $dashboardLink = '/ventech_locator/admin/admin_dashboard.php';
                $logoutLink = '/ventech_locator/admin/admin_logout.php';
            } else { // Default user role
                $dashboardLink = '/ventech_locator/users/user_dashboard.php';
                $logoutLink = '/ventech_locator/users/user_logout.php';
            }
            // If you have a single unified logout script:
            // $logoutLink = '/ventech_locator/logout.php';

        } else {
            // User ID in session doesn't exist in DB - clear invalid session
            error_log("Invalid user ID found in session on user_venue_list.php: " . $loggedInUserId);
            session_unset();
            session_destroy();
            // No need to redirect here, just proceed as logged out
        }
    } // End session check

    // ** Database Connection **
    $host_map = 'localhost';
    $db_map = 'ventech_db';
    $user_map = 'root';
    $pass_map = '';
    $charset_map = 'utf8mb4';
    $dsn_map = "mysql:host=$host_map;dbname=$db_map;charset=$charset_map";
    $options_map = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo_map = null;
    try {
        $pdo_map = new PDO($dsn_map, $user_map, $pass_map, $options_map);
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
    $venues = fetchData($pdo_map, "SELECT id, title, latitude, longitude, image_path, amenities, price, status FROM venue WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
    $uniqueAmenities = getUniqueAmenities($venues ?: []);

} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
    die();
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // **Handle Guest Sign-in**
    if (isset($_GET['guest_signin']) && $_GET['guest_signin'] === 'true') {
        // Create a new guest user
        $stmt_guest = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'guest')");
        $guest_username = 'guest_' . time(); // Unique guest username
        $stmt_guest->execute([
            ':username' => $guest_username,
            ':email' => 'guest_' . time() . '@example.com', // Unique guest email
            ':password' => password_hash('guest_password', PASSWORD_DEFAULT), // Placeholder password
        ]);
        $guest_user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $guest_user_id;
        header("Location: /ventech_locator/user_venue_list.php"); // Redirect guest to venue list
        exit();
    }

    // **5. Check Session and Fetch User Data if Logged In**
    if ($isLoggedIn) {
        $loggedInUserId = $_SESSION['user_id'];
        // Prepare statement to fetch username and role
        $stmt_user = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $userData = $stmt_user->fetch();

        if ($userData) {
            $username = $userData['username'];
            $userRole = strtolower($userData['role'] ?? 'guest'); // Default to 'guest' if role is null/missing

            // Determine dashboard link based on role (ADJUST PATHS AS NEEDED)
            if ($userRole === 'client' || $userRole === 'admin' || $userRole === 'owner') {
                $dashboardLink = '/ventech_locator/client/client_dashboard.php';
            } else { // Default to user/guest dashboard (or profile page)
                $dashboardLink = '/ventech_locator/users/user_profile.php'; // Assuming user profile exists
            }
            $logoutLink = '/ventech_locator/logout.php';

        } else {
            // User ID in session doesn't exist in DB - clear invalid session
            error_log("Invalid user ID found in session on index.php: " . $loggedInUserId);
            session_unset();
            session_destroy();
            $isLoggedIn = false; // Update login status
            // No redirection here, let the landing page load for non-logged-in users
        }
    } // End session check

    // **6. Fetch Venues** (Fetch regardless of login status)
    $stmt_venues = $pdo->prepare("SELECT * FROM venue WHERE status IN ('open', 'closed') ORDER BY created_at DESC LIMIT 9"); // Limit displayed venues?
    $stmt_venues->execute();
    $venues = $stmt_venues->fetchAll();

} catch (PDOException $e) {
    error_log("Database error on index.php: " . $e->getMessage());
    // Display a user-friendly error message but don't reveal details
    echo "<div style='color:red; padding:10px; border:1px solid red; background-color:#ffe0e0; margin:10px;'>";
    echo "Sorry, we encountered a problem loading the page content. Please try again later.";
    echo "</div>";
    // You might want to die() here or just let the rest of the page render without DB data
    $venues = []; // Ensure $venues is an empty array if DB fails
    // die(); // Uncomment if you want to stop execution on DB error
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ventech Locator</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJHoWIiFsp9vF5+RmJMdxG1j97yrHDNHPxmalkGcJA==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZs1Kkgc8PU1cKB4UUplusxX7j35Y==" crossorigin=""></script>
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet"/>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        #map-container { width: 100%; height: 100vh; }
        #map { width: 100%; height: 80%; }
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
           /* Optional: Add minor custom styles if needed */
           .hero-overlay-content {
            max-width: 1200px; /* Limit width of content over hero */
            width: 100%;
        }
        .main.container.mx-auto.py-12.md\:py-16.px-4.md\:px-8 {
        padding-top: 0px;
}
    </style>
</head>
<body class="font-roboto">
<header class="relative">
    <img src="/ventech_locator/images/header.jpg" alt="Hotel" class="w-full h-96 object-cover" />
    <div class="absolute top-0 left-0 w-full h-full bg-black bg-opacity-50 flex flex-col items-center justify-center">
        <div class="flex justify-between items-center w-full px-8 py-4">
            <a href="index.php">
                <img src="/ventech_locator/images/logo.png" class="h-12" alt="Planyo Logo" />
            </a>
            <nav class="text-white space-x-8 flex items-center">
                <a class="hover:underline" href="index.php">HOME</a>
                <a class="hover:underline" href="user_venue_list.php">VENUE LIST</a>

                <?php if (!$isLoggedIn): ?>
                    <div class="relative">
                        <button class="hover:underline focus:outline-none" id="signInButton">SIGN IN</button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-2 hidden" id="dropdownMenu">
                            <a href="/ventech_locator/users/user_login.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-200">User</a>
                            <a href="/ventech_locator/client/client_login.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-200">Client</a>
                            <a href="/ventech_locator/admin/admin_login.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-200">Admin</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($userRole === 'admin'): ?>
                        <a class="hover:underline" href="<?= $dashboardLink ?>">Admin Dashboard</a>
                    <?php elseif ($userRole === 'client' || $userRole === 'owner'): ?>
                        <a class="hover:underline" href="<?= $dashboardLink ?>">Client Dashboard</a>
                    <?php else: ?>
                        <a class="hover:underline" href="<?= $dashboardLink ?>">User Dashboard</a>
                    <?php endif; ?>
                    <a class="hover:underline" href="<?= $logoutLink ?>">LOGOUT</a>
                <?php endif; ?>

            </nav>
        </div>
        <h1 class="text-4xl text-white mt-8">welcome to</h1>
        <h2 class="text-6xl text-yellow-500 font-bold">Ventech Locator</h2>
        <a href="#venue-map-section" class="mt-4 px-6 py-2 bg-yellow-500 text-white font-bold rounded">VIEW VENUES ON MAP</a>
    </div>
</header>



<main class="py-16 px-8" id="venue-map-section">
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
</main>

<main class="container mx-auto py-12 md:py-16 px-4 md:px-8">
    <h2 class="text-2xl md:text-3xl font-bold text-center text-gray-800 mb-8 md:mb-12">Featured Venues</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
        <?php if (!empty($venues)): ?>
            <?php foreach ($venues as $venue):
                // Construct the correct image path (same logic as before)
                $imagePathFromDB = $venue['image_path'] ?? null;
                // IMPORTANT: Adjust this base URL if your uploads folder location changes relative to the web root!
                $uploadsBaseUrl = '/ventech_locator/uploads/';
                $placeholderImg = 'https://via.placeholder.com/400x250/e2e8f0/64748b?text=No+Image'; // Placeholder color adjusted
                $imgSrc = $placeholderImg;

                if (!empty($imagePathFromDB)) {
                    // Check if it's already a full URL (less common but possible)
                    if (filter_var($imagePathFromDB, FILTER_VALIDATE_URL)) {
                        $imgSrc = htmlspecialchars($imagePathFromDB);
                    } else {
                        // Assume relative path and construct full URL
                        $imgSrc = $uploadsBaseUrl . ltrim(htmlspecialchars($imagePathFromDB), '/');
                        // Basic check if file exists (optional, uncomment if needed, adjust path)
                        // $filesystemPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uploadsBaseUrl . ltrim($imagePathFromDB, '/');
                        // if (!file_exists($filesystemPath)) { $imgSrc = $placeholderImg; }
                    }
                }
            ?>
            <div class="border rounded-lg shadow-md overflow-hidden bg-white flex flex-col transition duration-300 hover:shadow-xl">
                <a href="venue_display.php?id=<?= $venue['id'] ?>" class="block">
                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($venue['title']) ?>" class="w-full h-48 object-cover" loading="lazy" />
                </a>
                <div class="p-4 flex flex-col flex-grow">
                     <p class="text-xs text-gray-500 mb-1">
                         Status: <span class="font-medium <?= $venue['status'] === 'open' ? 'text-green-600' : 'text-red-600' ?>">
                                     <?= ucfirst(htmlspecialchars($venue['status'])) ?>
                                 </span>
                     </p>
                    <h3 class="text-lg font-semibold text-gray-800 hover:text-orange-600 mb-2">
                        <a href="venue_display.php?id=<?= $venue['id'] ?>"><?= htmlspecialchars($venue['title']) ?></a>
                    </h3>

                    <p class="text-sm text-gray-600 mb-1">Starting from</p>
                    <p class="text-xl font-bold text-gray-900 mb-3">₱ <?= number_format($venue['price'] ?? 0, 2) ?> <span class="text-xs font-normal">/ Hour</span></p>

                    <div class="flex items-center text-sm text-gray-500 mb-4">
                        <div class="flex text-yellow-400">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><i class="far fa-star"></i>
                        </div>
                        <span class="ml-2">(<?= htmlspecialchars($venue['reviews'] ?? 0) ?> Reviews)</span>
                    </div>

                    <div class="mt-auto pt-3 border-t border-gray-200 flex space-x-3">
                        <a href="venue_display.php?id=<?= $venue['id'] ?>" class="flex-1 text-center px-3 py-2 bg-orange-500 text-white text-xs font-bold rounded hover:bg-orange-600 transition shadow-sm">
                            <i class="fas fa-info-circle mr-1"></i> DETAILS
                        </a>
                        <?php if ($venue['status'] === 'open'): // Only show reservation button if venue is open ?>
                        <a href="/ventech_locator/venue_reservation_form.php?venue_id=<?= $venue['id'] ?>&venue_title=<?= urlencode(htmlspecialchars($venue['title'])) ?>" class="flex-1 text-center px-3 py-2 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700 transition shadow-sm">
                            <i class="fas fa-calendar-check mr-1"></i> RESERVE NOW
                        </a>
                        <?php else: ?>
                            <span class="flex-1 text-center px-3 py-2 bg-gray-400 text-white text-xs font-bold rounded cursor-not-allowed" title="Venue is currently closed for reservations">
                                <i class="fas fa-calendar-times mr-1"></i> CLOSED
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="col-span-full text-center text-gray-500">No venues found matching the criteria.</p>
        <?php endif; ?>
    </div>

    <div class="text-center mt-12">
        <a href="user_venue_list.php" class="inline-block px-8 py-3 bg-gray-700 hover:bg-gray-800 text-white font-semibold rounded-md shadow-md transition">
            View All Venues &rarr;
        </a>
    </div>
</main>

<footer class="bg-gray-800 text-gray-300 text-center p-6 mt-12">
    <p>&copy; <?= date('Y') ?> Ventech Locator. All Rights Reserved.</p>
    </footer>

<script>
    const map = L.map('map').setView([14.4797, 120.9936], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Define custom marker icon
    const customIcon = L.icon({
        iconUrl: '/ventech_locator/images/logo.png', // Replace with the actual path to your image
        iconSize: [38, 50], // Adjust size as needed
        iconAnchor: [19, 50], // Point of the icon which will correspond to marker's location
        popupAnchor: [0, -50] // Point from which the popup should open relative to the iconAnchor
    });

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
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><i class="far fa-star"></i>
                        <span class="ml-2">(0 Reviews)</span>
                        <div class="mt-auto pt-3 border-t border-gray-200 flex space-x-3">
                        <a href="venue_display.php?id=${venue.id}" class="flex-1 text-center px-3 py-2 bg-orange-500 text-white text-xs font-bold rounded hover:bg-orange-600 transition shadow-sm">
                        <i class="fas fa-info-circle mr-1"></i> DETAILS</a>
                        ${venue.status === 'open' ? `
                        <a href="/ventech_locator/venue_reservation_form.php?venue_id=${venue.id}&venue_name=${encodeURIComponent(venue.title)}" class="flex-1 text-center px-3 py-2 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700 transition shadow-sm">
                        <i class="fas fa-calendar-check mr-1"></i> RESERVE NOW</a>
                            ` : `
                            <span class="flex-1 text-center px-3 py-2 bg-gray-400 text-white text-xs font-bold rounded cursor-not-allowed" title="Venue is currently closed for reservations">
                                <i class="fas fa-calendar-times mr-1"></i> CLOSED
                            </span>
                            `}
                        
                
            `;
            const marker = L.marker([venue.latitude, venue.longitude], { icon: customIcon }).bindPopup(popupContent).addTo(map);
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
<script>
    document.getElementById('signInButton').addEventListener('click', function () {
        document.getElementById('dropdownMenu').classList.toggle('hidden');
    });
</script>
</body>
</html>