<?php
// **1. Start Session**
session_start();

// **2. Database Connection Parameters**
$host = 'localhost';
$db   = 'ventech_db';
$user_db = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// **3. Establish PDO Connection**
try {
    $pdo = new PDO($dsn, $user_db, $pass, $options);
} catch (PDOException $e) {
    handle_error("Could not connect to the database: " . $e->getMessage());
}

// Function to handle errors (basic example) - Ensure this is defined before use
function handle_error($message, $is_user_facing = false) {
    error_log($message); // Always log the detailed error
    // Avoid outputting raw errors in production unless explicitly user-facing
    $display_message = $is_user_facing ? htmlspecialchars($message) : "An internal error occurred. Please try again later or contact support.";
    echo "<div style='color:red;padding:10px;border:1px solid red;background-color:#ffe0e0;margin:10px;'>"
         . $display_message
         . "</div>";
    die();
}

// **4. Check User Authentication & Get Role**
$loggedInUserId = $_SESSION['user_id'] ?? null;
$loggedInUserRole = null;
$loggedInUsername = null; // Store username if needed
if ($loggedInUserId) {
    try {
        $stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
        $stmt->execute([$loggedInUserId]);
        $loggedInUserData = $stmt->fetch();
        if ($loggedInUserData) {
            $loggedInUserRole = $loggedInUserData['role'];
            $loggedInUsername = $loggedInUserData['username']; // Get username
        } else {
            // User ID in session but not DB - clear session
            unset($_SESSION['user_id']);
            unset($_SESSION['username']); // Also clear username if set
            $loggedInUserId = null;
            error_log("User ID $loggedInUserId found in session but not in database.");
        }
    } catch (PDOException $e) {
        error_log("Error fetching logged-in user role/username: " . $e->getMessage());
        // Don't kill the page, just proceed as logged out
        $loggedInUserId = null;
        $loggedInUserRole = null;
        $loggedInUsername = null;
    }
}

// **5. Get and validate the venue ID**
$venue_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($venue_id === false || $venue_id <= 0) {
    handle_error("Invalid or missing Venue ID.", true); // User facing error
}

// **6. Function to fetch data (Modified for single row fetch)**
function fetch_row($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(); // Use fetch for single row
    } catch (PDOException $e) {
        error_log("Database query error (fetch_row): " . $e->getMessage() . " Query: " . $query);
        return null; // Return null on error
    }
}
function fetch_all($pdo, $query, $params = []) {
     try {
         $stmt = $pdo->prepare($query);
         $stmt->execute($params);
         return $stmt->fetchAll(); // Use fetchAll for multiple rows
     } catch (PDOException $e) {
         error_log("Database query error (fetch_all): " . $e->getMessage() . " Query: " . $query);
         return []; // Return empty array on error
     }
 }


// **7. Fetch Venue Data**
// Fetch venue details along with the owner's username
$venue = fetch_row($pdo, "SELECT v.*, u.username as owner_username FROM venue v LEFT JOIN users u ON v.user_id = u.id WHERE v.id = ?", [$venue_id]);
if (!$venue) {
    handle_error("Venue with ID " . htmlspecialchars($venue_id) . " not found.", true); // User facing error
}
// Check if venue is 'open' - might want to restrict booking if not
$isVenueOpen = (isset($venue['status']) && strtolower($venue['status']) === 'open');

// **8. Fetch Media & Determine Header Image/Video**
// Prioritize virtual tour > video > image for the header background
$media = fetch_all($pdo, "SELECT media_type, media_url FROM venue_media WHERE venue_id = ? ORDER BY FIELD(media_type, 'video', 'image'), created_at ASC", [$venue_id]);
$header_content_url = 'https://via.placeholder.com/1200x500/cccccc/999999?text=Venue+Image+Not+Available'; // Default fallback
$header_content_type = 'image'; // Can be 'image', 'video', or 'virtual_tour'

// Check for virtual tour first
if (!empty($venue['virtual_tour_url'])) {
    $header_content_url = htmlspecialchars($venue['virtual_tour_url']);
    $header_content_type = 'virtual_tour';
} else if (!empty($media)) {
    // Find first video or image
    foreach ($media as $item) {
        if ($item['media_type'] === 'video') {
            $header_content_url = htmlspecialchars($item['media_url']);
            $header_content_type = 'video';
            break; // Prioritize video over image for header if no virtual tour
        } elseif ($item['media_type'] === 'image') {
             // Use the first image found if no video is encountered first
            $header_content_url = htmlspecialchars($item['media_url']);
            $header_content_type = 'image';
            break;
        }
    }
}
// Ensure media URLs are web-accessible (Consider prepending base URL if needed)
// Example: $baseMediaUrl = '/ventech_locator/uploads/venue_media/';
// if ($header_content_type !== 'virtual_tour') { $header_content_url = $baseMediaUrl . basename($header_content_url); }
// Do similar for gallery items below if needed.

// **9. Fetch Other Data**
$unavailableDatesResult = fetch_all($pdo, "SELECT unavailable_date FROM unavailable_dates WHERE venue_id = ?", [$venue_id]);
$unavailableDates = array_column($unavailableDatesResult, 'unavailable_date'); // Extract just the dates

// Fetch client contact info associated with the venue (if available)
$venue_contact_info = fetch_row($pdo, "SELECT client_name, client_email, client_phone, client_address FROM client_info WHERE venue_id = ?", [$venue_id]);


// **10. Calendar Setup**
$currentMonth = $_GET['month'] ?? date('n');
$currentYear = $_GET['year'] ?? date('Y');
// Validate month and year
$currentMonth = filter_var($currentMonth, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$currentYear = filter_var($currentYear, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1970, 'max_range' => 2100]]); // Adjust range as needed
if (!$currentMonth) $currentMonth = date('n');
if (!$currentYear) $currentYear = date('Y');
$today = date('Y-m-d'); // Get today's date for comparison

// **11. PHP Calendar Function (Updated)**
function generateCalendarPHP($year, $month, $unavailableDates, $today) {
    $calendar = '<div class="calendar" data-year="' . $year . '" data-month="' . $month . '">';
    $calendar .= '<div class="month-header">';
    $calendar .= '<button type="button" class="month-change prev-month" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>';

    $dateObj = DateTime::createFromFormat('!Y-n', "$year-$month");
    $monthYearString = $dateObj ? $dateObj->format('F Y') : 'Invalid Date';
    $calendar .= '<span class="month-year-text">' . $monthYearString . '</span>'; // Use span for text

    $calendar .= '<button type="button" class="month-change next-month" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>';
    $calendar .= '</div>';
    $calendar .= '<div class="weekdays" aria-hidden="true">'; // Hide from screen readers
    $calendar .= '<div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>';
    $calendar .= '</div>';

    if ($dateObj) {
        $daysInMonth = (int)$dateObj->format('t');
        $firstDayOfMonth = (int)$dateObj->format('w'); // 0 (for Sunday) through 6 (for Saturday)

        $calendar .= '<div class="days" role="grid" aria-labelledby="calendar-heading">'; // ARIA roles

        // Add empty cells for days before the first of the month
        for ($i = 0; $i < $firstDayOfMonth; $i++) {
            $calendar .= '<div class="empty" role="gridcell"></div>';
        }

        // Add day cells
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $isUnavailable = in_array($dateString, $unavailableDates);
            $isPast = $dateString < $today;
            $class = 'day-cell '; // Base class
            $ariaLabel = $dateObj->format('F') . " $day, $year"; // Full date for screen reader
            $ariaDisabled = 'false'; // Default

            if ($isPast) {
                $class .= 'past ';
                $class .= $isUnavailable ? 'unavailable' : 'available'; // Still mark past unavailable days
                $ariaDisabled = 'true';
                 $ariaLabel .= $isUnavailable ? ' (Unavailable, Past)' : ' (Past)';
            } elseif ($isUnavailable) {
                $class .= 'unavailable';
                $ariaDisabled = 'true';
                $ariaLabel .= ' (Unavailable)';
            } else {
                $class .= 'available future'; // Mark future available dates
                 $ariaLabel .= ' (Available)';
            }

            $calendar .= '<div class="' . trim($class) . '" data-date="' . $dateString . '" role="gridcell" tabindex="-1" aria-label="' . $ariaLabel . '" aria-disabled="' . $ariaDisabled . '">'; // ARIA attributes
            $calendar .= $day;
            $calendar .= '</div>';
        }

        // Add empty cells for days after the last of the month
        $totalCells = $firstDayOfMonth + $daysInMonth;
        $remainingCells = (7 - ($totalCells % 7)) % 7;
        for ($i = 0; $i < $remainingCells; $i++) {
             $calendar .= '<div class="empty" role="gridcell"></div>';
        }

        $calendar .= '</div>'; // Close days
    } else {
        $calendar .= '<div class="p-4 text-red-500">Error generating calendar.</div>';
    }
    $calendar .= '</div>'; // Close calendar
    return $calendar;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($venue['title'] ?? 'Venue Details'); ?> - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" /> <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" /> <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script> <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" /> <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script> <style>
        body { font-family: 'Montserrat', sans-serif; }
        #map { height: 350px; width: 100%; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 0; /* Ensure map is below sticky elements */}
        .swiper-slide img, .swiper-slide video { display: block; width: 100%; height: auto; max-height: 60vh; object-fit: contain; /* Use contain for gallery */ margin: auto; /* Center if smaller */}
        .swiper-slide { background-color: #f9fafb; } /* Light bg for slides */

        /* --- Enhanced Calendar Styles --- */
        .calendar { width: 100%; max-width: 450px; /* Adjusted max-width for sidebar */ background-color: #fff; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 3px 6px rgba(0, 0, 0, 0.07); margin: 0 auto 20px auto; /* Centered, with bottom margin */ font-size: 15px; }
        .calendar .month-header { padding: 12px 15px; font-weight: 600; font-size: 1.15em; border-top-left-radius: 8px; border-top-right-radius: 8px; display: flex; justify-content: space-between; align-items: center; background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
        .calendar .month-header .month-year-text { flex-grow: 1; text-align: center; }
        .calendar .month-header button { background: none; border: none; font-size: 1em; cursor: pointer; padding: 8px; color: #334155; transition: color 0.2s; line-height: 1; }
        .calendar .month-header button:hover { color: #f59e0b; }
        .calendar .weekdays { display: grid; grid-template-columns: repeat(7, 1fr); padding: 8px 5px; font-weight: 600; color: #475569; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .calendar .weekdays div { text-align: center; font-size: 0.85em; }
        .calendar .days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background-color: #e2e8f0; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; overflow: hidden; }
        .calendar .days .day-cell { text-align: center; padding: 12px 5px; background-color: #fff; font-size: 0.95em; min-height: 55px; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s ease, transform 0.1s ease; position: relative; }
        .calendar .days .available.future { cursor: pointer; color: #1e40af; font-weight: 500; }
        .calendar .days .available.future:hover,
        .calendar .days .available.future:focus { background-color: #eff6ff; transform: scale(1.05); z-index: 5; box-shadow: 0 0 5px rgba(0,0,0,0.1); outline: 2px solid #60a5fa; outline-offset: -2px; border-radius: 3px; }
        .calendar .days .unavailable { background-color: #fecaca; color: #991b1b; font-weight: 500; cursor: not-allowed; }
        .calendar .days .past { color: #9ca3af; cursor: not-allowed; background-color: #f9fafb; }
        .calendar .days .past.unavailable { background-color: #fecaca; opacity: 0.7; }
        .calendar .days .empty { background-color: #f8fafc; }
        .calendar .days .selected { background-color: #3b82f6 !important; color: white !important; font-weight: 700; border-radius: 4px; transform: scale(1.05); z-index: 10; box-shadow: 0 0 8px rgba(59, 130, 246, 0.5); }

        /* Header Styles */
        .venue-header { position: relative; background-color: #333; color: white; overflow: hidden; border-radius: 0px; /* Full width */ margin-bottom: 1.5rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .venue-header-bg { display: block; width: 100%; height: 55vh; object-fit: cover; opacity: 0.5; /* Dim slightly more */ }
        .venue-header-bg-iframe { width: 100%; height: 55vh; border: none; display: block; }
        .venue-header-overlay { position: absolute; bottom: 0; left: 0; right: 0; padding: 1.5rem 2rem; background: linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0)); }
        .venue-header-overlay h1 { font-size: 2rem; md:font-size: 2.5rem; font-weight: 700; text-shadow: 1px 1px 3px rgba(0,0,0,0.6); margin-bottom: 0.5rem; }
        .venue-header-overlay p { font-size: 0.9rem; md:font-size: 1rem; font-weight: 400; text-shadow: 1px 1px 2px rgba(0,0,0,0.6); opacity: 0.9; display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem; } /* Flex wrap for smaller screens */

        #selected-date-display { margin-top: 5px; text-align: center; font-weight: 500; color: #1d4ed8; min-height: 1.5em; /* Prevent layout shift */ }

        /* Sticky Sidebar Offset */
        .sticky-sidebar { position: sticky; top: 2rem; /* Adjust based on nav height */ align-self: start; /* Important for sticky */ }

        /* Prose adjustments */
        .prose { max-width: none; } /* Allow prose to fill container */
        .prose p { margin-bottom: 1em; }
        .prose ul { list-style: disc; margin-left: 1.5em; margin-bottom: 1em; }
        .prose li { margin-bottom: 0.5em; }

        /* Adjusted margin for main content block */
        .main-content-block { margin-top: -4rem; /* Pull up content over header */ }

        /* Navigation Active State (Example) */
        nav a.active { color: #f59e0b; font-weight: 600; }

        @media (min-width: 768px) {
    .md\:px-6 {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
        margin-top: 20px;
    }
}

    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <nav class="bg-white shadow-sm p-4 sticky top-0 z-20"> <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="/ventech_locator/index.php" class="text-2xl font-bold text-yellow-600 hover:text-yellow-700">Ventech Locator</a>
            <div>
                <?php if ($loggedInUserId): ?>
                    <a href="/ventech_locator/client_dashboard.php" class="text-gray-700 hover:text-yellow-600 mr-4 font-medium">Dashboard</a>
                    <span class="text-gray-500 mr-4">|</span>
                    <span class="text-gray-700 mr-2">Hi, <?= htmlspecialchars($loggedInUsername ?? 'User'); ?></span>
                    <a href="/ventech_locator/client_logout.php" class="text-gray-700 hover:text-yellow-600 font-medium">Logout</a>
                <?php else: ?>
                    <a href="/ventech_locator/users/user_login.php" class="text-gray-700 hover:text-yellow-600 mr-4 font-medium">Login</a>
                    <a href="/ventech_locator/users/user_signup.php" class="text-gray-700 hover:text-yellow-600 font-medium">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="venue-header">
        <?php if ($header_content_type === 'virtual_tour'): ?>
            <iframe src="<?php echo $header_content_url; ?>" class="venue-header-bg-iframe" allowfullscreen allow="xr-spatial-tracking; gyroscope; accelerometer" title="<?php echo htmlspecialchars($venue['title'] ?? ''); ?> Virtual Tour"></iframe>
        <?php elseif ($header_content_type === 'video'): ?>
            <video autoplay loop muted playsinline class="venue-header-bg" poster=""> <source src="<?php echo $header_content_url; ?>" type="video/mp4">
                Your browser does not support the video tag.
                </video>
        <?php else: // Default to image ?>
            <img src="<?php echo $header_content_url; ?>" alt="<?php echo htmlspecialchars($venue['title'] ?? ''); ?> Header" class="venue-header-bg">
        <?php endif; ?>
        <div class="venue-header-overlay">
            <h1><?php echo htmlspecialchars($venue['title'] ?? 'Venue Title'); ?></h1>
            <p>
                <span><i class="fas fa-users fa-fw mr-1 opacity-80"></i> Up to <?= htmlspecialchars($venue['num_persons'] ?? 'N/A'); ?> guests</span>
                <span><i class="fas fa-money-bill-wave fa-fw mr-1 opacity-80"></i> From ₱<?= number_format($venue['price'] ?? 0, 2) ?>/Hour</span>
                <?php if($venue['owner_username']): ?>
                <span><i class="fas fa-user-tie fa-fw mr-1 opacity-80"></i> By <?= htmlspecialchars($venue['owner_username']); ?></span>
                <?php endif; ?>
                 <?php if(!$isVenueOpen): ?>
                    <span class="text-red-400 font-semibold"><i class="fas fa-exclamation-circle fa-fw mr-1"></i> Currently Closed</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 relative z-10 main-content-block">
        <div class="bg-white shadow-xl rounded-lg p-6 md:p-8 mb-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-2 space-y-8">

                    <?php if (!empty($venue['description'])) : ?>
                        <section aria-labelledby="description-heading">
                            <h2 id="description-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Description</h2>
                            <div class="text-gray-700 prose prose-sm">
                                <?php echo nl2br(htmlspecialchars($venue['description'])); ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gray-50 p-4 rounded border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Venue Details</h3>
                            <ul class="space-y-2 text-sm">
                                <li><i class="fas fa-users fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Capacity:</strong> <?php echo htmlspecialchars($venue['num_persons'] ?? 'N/A'); ?> persons</li>
                                <li><i class="fas fa-list fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Amenities:</strong> <?php echo htmlspecialchars($venue['amenities'] ?? 'N/A'); ?></li>
                                <li><i class="fas fa-star fa-fw mr-2 text-yellow-500 w-5 text-center"></i><strong>Reviews:</strong> <?php echo htmlspecialchars($venue['reviews'] ?? 'N/A'); ?> (Feature coming soon)</li>
                                <li><i class="fas fa-wifi fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Wifi:</strong> <?php echo ($venue['wifi'] ?? 'no') === 'yes' ? 'Available' : 'Not Available'; ?></li>
                                <li><i class="fas fa-parking fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Parking:</strong> <?php echo ($venue['parking'] ?? 'no') === 'yes' ? 'Available' : 'Not Available'; ?></li>
                            </ul>
                        </div>
                        <div class="bg-gray-50 p-4 rounded border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Additional Information</h3>
                            <div class="text-sm text-gray-600 prose prose-sm">
                                <?php echo !empty($venue['additional_info']) ? nl2br(htmlspecialchars($venue['additional_info'])) : '<p>No additional information provided.</p>'; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($venue_contact_info)) : ?>
                        <section aria-labelledby="contact-heading">
                            <h2 id="contact-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Venue Contact</h2>
                            <div class="bg-blue-50 p-4 rounded border border-blue-200">
                                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <li><i class="fas fa-user fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Name:</strong> <?php echo htmlspecialchars($venue_contact_info['client_name'] ?? 'N/A'); ?></li>
                                    <li><i class="fas fa-envelope fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Email:</strong> <?php echo htmlspecialchars($venue_contact_info['client_email'] ?? 'N/A'); ?></li>
                                    <li><i class="fas fa-phone fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Phone:</strong> <?php echo htmlspecialchars($venue_contact_info['client_phone'] ?? 'N/A'); ?></li>
                                    <li><i class="fas fa-map-marker-alt fa-fw mr-2 text-blue-600 w-5 text-center"></i><strong>Address:</strong> <?php echo htmlspecialchars($venue_contact_info['client_address'] ?? 'N/A'); ?></li>
                                </ul>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section id="venue-location" aria-labelledby="location-heading">
                        <h2 id="location-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Location</h2>
                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($venue['address'] ?? 'Address not provided.'); ?></p>
                        <div id="map"><p class="text-center text-gray-500 p-4">Loading map...</p></div>
                    </section>

                    <?php if (!empty($media)): ?>
                    <section id="media-section" aria-labelledby="gallery-heading">
                        <h2 id="gallery-heading" class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2">Media Gallery</h2>
                        <div class="swiper bg-gray-100 rounded-lg overflow-hidden p-2 border" id="media-slider">
                            <div class="swiper-wrapper">
                                <?php foreach ($media as $item) :
                                    // Potentially prepend base URL here if needed
                                    // $mediaUrl = $baseMediaUrl . basename(htmlspecialchars($item['media_url']));
                                    $mediaUrl = htmlspecialchars($item['media_url']);
                                ?>
                                    <div class="swiper-slide">
                                        <?php if ($item['media_type'] === 'image') : ?>
                                            <img src="<?php echo $mediaUrl; ?>" alt="Venue Image" loading="lazy" class="max-h-[400px]"/>
                                        <?php elseif ($item['media_type'] === 'video') : ?>
                                            <video controls class="w-full max-h-[400px]" preload="metadata">
                                                <source src="<?php echo $mediaUrl; ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                             <div class="swiper-pagination !bottom-2"></div>
                            <div class="swiper-button-prev !text-gray-600 hover:!text-black after:!text-xl"></div>
                            <div class="swiper-button-next !text-gray-600 hover:!text-black after:!text-xl"></div>
                        </div>
                    </section>
                    <?php endif; ?>

                </div><div class="lg:col-span-1 space-y-6 sticky-sidebar">

                    <section id="availability-section" aria-labelledby="calendar-heading" class="bg-white p-4 rounded-lg border shadow-sm">
                        <h2 id="calendar-heading" class="text-xl font-semibold text-gray-800 mb-4 text-center">Check Availability</h2>
                        <div id="calendar-container">
                            <?php echo generateCalendarPHP($currentYear, $currentMonth, $unavailableDates, $today); ?>
                        </div>
                        <div id="selected-date-display">Select an available date</div>
                         <p class="text-xs text-center text-gray-500 mt-2"><span class="inline-block w-3 h-3 rounded-full bg-red-100 border border-red-300 mr-1 align-middle"></span> Unavailable/Past</p>
                    </section>

                    <section class="bg-white p-4 rounded-lg border shadow-sm">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4 text-center">Book This Venue</h3>

                        <?php if ($isVenueOpen): ?>
                            <?php if ($loggedInUserId): ?>
                                <?php // Simple form redirecting to a booking page ?>
                                <form id="booking-redirect-form" action="/ventech_locator/venue_reservation_form.php" method="GET">
                                    <input type="hidden" name="venue_id" value="<?php echo $venue_id; ?>">
                                    <input type="hidden" name="venue_name" value="<?php echo htmlspecialchars($venue['title'] ?? ''); ?>">
                                    <input type="hidden" name="price_per_hour" value="<?php echo htmlspecialchars($venue['price'] ?? '0'); ?>">
                                    <input type="hidden" id="selected_date_input" name="event_date" value="">

                                    <?php // Optional: Display selected date clearly for user (read-only) ?>
                                    <div class="mb-3">
                                        <label for="booking_date_display" class="block text-sm font-medium text-gray-600 mb-1">Selected Date:</label>
                                        <input type="text" id="booking_date_display" readonly
                                               class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-center text-gray-700"
                                               value="Please select a date from the calendar">
                                    </div>

                                    <button type="submit" id="book-now-btn" disabled
                                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2.5 px-4 rounded transition duration-300 ease-in-out shadow disabled:opacity-50 disabled:cursor-not-allowed">
                                        <i class="fas fa-calendar-check mr-2"></i> Proceed to Booking
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-center text-red-600 bg-red-50 p-3 rounded border border-red-200 text-sm">
                                    Please <a href="/ventech_locator/users/user_login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="font-bold underline hover:text-red-800">Login</a> or
                                    <a href="/ventech_locator/users/user_signup.php" class="font-bold underline hover:text-red-800">Register</a> to make a reservation.
                                </p>
                            <?php endif; ?>
                         <?php else: // Venue is closed ?>
                             <p class="text-center text-orange-700 bg-orange-50 p-3 rounded border border-orange-200 text-sm">
                                This venue is currently marked as closed and cannot be booked at this time.
                            </p>
                         <?php endif; ?>
                    </section>

                    </div></div><?php if ($loggedInUserId && isset($loggedInUserRole) && (strtolower($loggedInUserRole) === 'admin' || $loggedInUserId === $venue['user_id']) ): ?>
                <div class="text-center mt-10 border-t pt-6">
                    <a href="venue_details.php?id=<?php echo $venue_id; ?>" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded transition duration-300 ease-in-out shadow hover:shadow-md">
                        <i class="fas fa-edit mr-2"></i>Edit Venue Details
                    </a>
                </div>
            <?php endif; ?>

        </div> </div> <script>
    document.addEventListener("DOMContentLoaded", function () {
        // --- Initialize Leaflet Map ---
        const venueLat = <?php echo json_encode($venue['latitude'] ?? null); ?>;
        const venueLon = <?php echo json_encode($venue['longitude'] ?? null); ?>;
        const venueTitle = <?php echo json_encode($venue['title'] ?? 'Venue Location'); ?>;
        const mapDiv = document.getElementById('map');
        let map = null;

        function initMap() {
            if (!mapDiv) return;
             // Check for valid, non-zero coordinates
             if (!venueLat || !venueLon || isNaN(parseFloat(venueLat)) || isNaN(parseFloat(venueLon)) || parseFloat(venueLat) === 0 || parseFloat(venueLon) === 0) {
                 mapDiv.innerHTML = '<p class="text-center text-gray-500 p-4">Map location not available.</p>';
                 return;
             }
            const latNum = parseFloat(venueLat);
            const lonNum = parseFloat(venueLon);
            try {
                map = L.map(mapDiv).setView([latNum, lonNum], 15); // Zoom level 15
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors'
                }).addTo(map);
                L.marker([latNum, lonNum]).addTo(map)
                    .bindPopup(`<b>${venueTitle}</b><br>${<?php echo json_encode($venue['address'] ?? ''); ?>}`) // Add address to popup
                    .openPopup();
            } catch (e) {
                console.error("Leaflet map initialization failed:", e);
                mapDiv.innerHTML = '<p class="text-center text-red-500 p-4">Error loading map.</p>';
            }
        }
        initMap(); // Initialize the map

        // --- Initialize Swiper ---
        const mediaSliderElement = document.getElementById('media-slider');
        if (mediaSliderElement) {
            const slides = mediaSliderElement.querySelectorAll('.swiper-slide');
            if (slides.length > 0) {
                new Swiper(mediaSliderElement, {
                    loop: slides.length > 1, // Loop only if more than one slide
                    slidesPerView: 1,
                    spaceBetween: 15, // Space between slides if multiple are shown
                    pagination: {
                        el: ".swiper-pagination",
                        clickable: true,
                        dynamicBullets: true, // Nicer pagination effect
                    },
                    navigation: {
                        nextEl: ".swiper-button-next",
                        prevEl: ".swiper-button-prev",
                    },
                    keyboard: {
                        enabled: true, // Allow keyboard navigation
                    },
                     autoplay: { // Autoplay configuration
                         delay: 5000, // 5 seconds delay
                         disableOnInteraction: true, // Stop autoplay when user interacts
                         pauseOnMouseEnter: true, // Pause when mouse is over slider
                    },
                    watchOverflow: true, // Disable nav/pagination if not enough slides
                });
            } else {
                // Optionally hide slider container or show message if no media
                 mediaSliderElement.style.display = 'none';
                // document.getElementById('media-section')?.querySelector('h2').insertAdjacentHTML('afterend', '<p class="text-gray-500 text-sm">No media available for this venue.</p>');
            }
        }


        // --- Calendar AJAX & Click Logic ---
        const calendarContainer = document.getElementById('calendar-container');
        const venueId = <?php echo json_encode($venue_id); ?>;
        const selectedDateDisplay = document.getElementById('selected-date-display');
        const selectedDateInput = document.getElementById('selected_date_input'); // Hidden input for form
        const bookingDateDisplay = document.getElementById('booking_date_display'); // Visible input for user
        const bookNowBtn = document.getElementById('book-now-btn');
        let currentSelectedDateElement = null; // Keep track of the selected DOM element

        if (calendarContainer) { // Only add listener if calendar exists
             calendarContainer.addEventListener('click', function (e) {
                // --- Handle Month Changes (AJAX) ---
                const monthButton = e.target.closest('.month-change');
                if (monthButton) {
                    e.preventDefault(); // Prevent potential button submit behaviour
                    const calendarEl = monthButton.closest('.calendar');
                    if (!calendarEl) return;

                    let year = parseInt(calendarEl.getAttribute('data-year'));
                    let month = parseInt(calendarEl.getAttribute('data-month'));

                    if (monthButton.classList.contains('prev-month')) { month--; if (month < 1) { month = 12; year--; } }
                    else if (monthButton.classList.contains('next-month')) { month++; if (month > 12) { month = 1; year++; } }
                    else { return; } // Should not happen with closest()

                    calendarContainer.innerHTML = '<div class="p-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</div>'; // Loading state

                    fetchUnavailableDates(year, month, venueId)
                        .then(unavailableDates => {
                             // Update the calendar container with new HTML from JS function
                             calendarContainer.innerHTML = generateCalendarJS(year, month, unavailableDates, '<?php echo $today; ?>');
                             resetSelection(); // Clear selection when month changes
                         })
                         .catch(error => {
                             console.error('Error fetching/generating calendar:', error);
                             calendarContainer.innerHTML = '<div class="p-4 text-center text-red-500">Failed to load calendar. Please try again.</div>';
                         });
                    return; // Stop further processing for month change click
                 }

                // --- Handle Date Selection Clicks ---
                const dayCell = e.target.closest('.day-cell.available.future'); // Target only future available cells
                if (dayCell) {
                    const dateValue = dayCell.getAttribute('data-date'); // YYYY-MM-DD

                    // Remove selected class from previously selected date
                    if (currentSelectedDateElement && currentSelectedDateElement !== dayCell) {
                        currentSelectedDateElement.classList.remove('selected');
                        currentSelectedDateElement.setAttribute('aria-selected', 'false');
                        currentSelectedDateElement.setAttribute('tabindex', '-1');
                    }

                    // Add selected class to the new date and store the element
                    dayCell.classList.add('selected');
                    dayCell.setAttribute('aria-selected', 'true');
                    dayCell.setAttribute('tabindex', '0'); // Make selectable via keyboard
                    currentSelectedDateElement = dayCell;
                    // dayCell.focus(); // Optionally focus the selected cell

                    // Format date for display (e.g., "April 13, 2025")
                    const dateObj = new Date(dateValue + 'T00:00:00'); // Add time part to avoid timezone issues
                    const displayFormat = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                    if (selectedDateDisplay) {
                        selectedDateDisplay.textContent = `Selected: ${displayFormat}`;
                    }
                    // Update form inputs
                    if (selectedDateInput) {
                        selectedDateInput.value = dateValue; // Store YYYY-MM-DD
                    }
                    if (bookingDateDisplay) {
                         bookingDateDisplay.value = displayFormat; // Show formatted date in booking form
                    }
                    // Enable booking button if it exists
                    if (bookNowBtn) {
                        bookNowBtn.disabled = false;
                    }
                }
             });
        }

        // Function to clear date selection UI and form inputs
        function resetSelection() {
            if (currentSelectedDateElement) {
                currentSelectedDateElement.classList.remove('selected');
                currentSelectedDateElement.setAttribute('aria-selected', 'false');
                currentSelectedDateElement.setAttribute('tabindex', '-1');
                currentSelectedDateElement = null;
            }
            if (selectedDateDisplay) selectedDateDisplay.textContent = 'Select an available date';
            if (selectedDateInput) selectedDateInput.value = '';
            if (bookingDateDisplay) bookingDateDisplay.value = 'Please select a date from the calendar';
            if (bookNowBtn) bookNowBtn.disabled = true;
        }


        // Function to fetch unavailable dates using AJAX
        function fetchUnavailableDates(year, month, venueId) {
            // Use URLSearchParams for cleaner parameter handling
            const params = new URLSearchParams({
                venue_id: venueId,
                year: year,
                month: month
            });
            const url = `get_unavailable_dates.php?${params.toString()}`; // Assuming script is in same directory

            return fetch(url)
                .then(response => {
                    if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('API Error fetching unavailable dates:', data.error);
                        return []; // Return empty on API error
                    }
                     // Ensure it returns an array
                    return Array.isArray(data.unavailableDates) ? data.unavailableDates : [];
                })
                .catch(error => {
                     console.error('Fetch Error fetching unavailable dates:', error);
                     throw error; // Re-throw to be caught by the caller
                 });
         }

        // Function to generate the calendar HTML using JavaScript (Completed & Enhanced)
        function generateCalendarJS(year, month, unavailableDates, today) {
            // --- Calendar structure setup ---
            let calendar = `<div class="calendar" data-year="${year}" data-month="${month}">`;
            calendar += '<div class="month-header">';
            calendar += '<button type="button" class="month-change prev-month" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>';

            const dateObjHeader = new Date(year, month - 1, 1); // Month is 0-indexed in JS Date
            const monthYearString = dateObjHeader.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
            calendar += `<span class="month-year-text" id="calendar-heading-${year}-${month}">${monthYearString}</span>`; // Unique ID for heading

            calendar += '<button type="button" class="month-change next-month" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>';
            calendar += '</div>';
            calendar += '<div class="weekdays" aria-hidden="true"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>';

            // --- Days calculation ---
            const daysInMonth = new Date(year, month, 0).getDate(); // Day 0 of next month gives last day of current
            const firstDayOfMonth = new Date(year, month - 1, 1).getDay(); // 0=Sun, 6=Sat

            calendar += `<div class="days" role="grid" aria-labelledby="calendar-heading-${year}-${month}">`; // Reference heading

            // Add empty cells before the first day
            for (let i = 0; i < firstDayOfMonth; i++) {
                calendar += '<div class="empty" role="gridcell"></div>';
            }

            // Add day cells
            for (let day = 1; day <= daysInMonth; day++) {
                 // Ensure two digits for month and day for correct YYYY-MM-DD format
                const monthPadded = String(month).padStart(2, '0');
                const dayPadded = String(day).padStart(2, '0');
                const dateString = `${year}-${monthPadded}-${dayPadded}`;

                const isUnavailable = unavailableDates.includes(dateString);
                const isPast = dateString < today;
                let classes = 'day-cell '; // Base class
                let ariaLabel = `${monthYearString} ${day}`;
                let ariaDisabled = 'false';

                if (isPast) {
                    classes += 'past ';
                    classes += isUnavailable ? 'unavailable' : 'available';
                    ariaDisabled = 'true';
                    ariaLabel += isUnavailable ? ' (Unavailable, Past)' : ' (Past)';
                } else if (isUnavailable) {
                    classes += 'unavailable';
                    ariaDisabled = 'true';
                    ariaLabel += ' (Unavailable)';
                } else {
                    classes += 'available future';
                    ariaLabel += ' (Available)';
                }

                calendar += `<div class="${classes.trim()}" data-date="${dateString}" role="gridcell" tabindex="-1" aria-label="${ariaLabel}" aria-disabled="${ariaDisabled}" aria-selected="false">`;
                calendar += day;
                calendar += '</div>';
            }

            // Add empty cells after the last day
            const totalCells = firstDayOfMonth + daysInMonth;
            const remainingCells = (7 - (totalCells % 7)) % 7;
            for (let i = 0; i < remainingCells; i++) {
                calendar += '<div class="empty" role="gridcell"></div>';
            }

            calendar += '</div>'; // Close days
            calendar += '</div>'; // Close calendar
            return calendar;
        }

        // Add event listener for the booking form if it exists
        const bookingForm = document.getElementById('booking-redirect-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(event) {
                const selectedDate = document.getElementById('selected_date_input').value;
                if (!selectedDate) {
                    event.preventDefault(); // Stop form submission
                    alert('Please select an available date from the calendar before booking.');
                    // Optional: visually indicate the calendar or date display
                }
                // If date is selected, the form will submit normally to booking_page.php
            });
        }

    });
    </script>

</body>
</html>



