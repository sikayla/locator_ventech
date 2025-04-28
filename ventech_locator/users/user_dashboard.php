<?php
// **1. Start Session**
session_start();

// **2. Include Database Connection**
// Ensure this path is correct relative to user_dashboard.php
// Assuming includes folder is one level up from the directory containing user_dashboard.php (e.g., users/)
require_once '../includes/db_connection.php';

// **3. Check User Authentication**
// Assuming user ID is stored in 'user_id' session variable after login
if (!isset($_SESSION['user_id'])) {
    // Redirect to user login page. Adjust path if needed.
    header("Location: user_login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// **4. Check if PDO connection is available**
// This check is good practice after including the connection file
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Log the error and display a user-friendly message
    error_log("PDO connection not available in user_dashboard.php");
    die("Sorry, we're experiencing technical difficulties with the database. Please try again later.");
}

// **5. Fetch Logged-in User Details**
try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // Invalid user ID in session, log out
        error_log("Invalid user_id {$user_id} in session (user_dashboard).");
        session_unset();
        session_destroy();
        // Redirect to login with an error message. Adjust path if needed.
        header("Location: user_login.php?error=invalid_session");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching user details for user ID {$user_id} in user_dashboard: " . $e->getMessage());
    die("Error loading your user information. Please try again later.");
}

// **6. Fetch Dashboard Counts Using Efficient Queries**
$total_reservations_count = 0;
$upcoming_reservations_count = 0;
$pending_reservations_count = 0;
// CURDATE() is a MySQL function that returns the current date
$today_date_sql = date('Y-m-d'); // Also keep PHP date for comparison if needed, though CURDATE() is better in SQL

try {
    // Query 1: Total Reservations Count for the user
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM venue_reservations WHERE user_id = ?");
    $stmtTotal->execute([$user_id]);
    $total_reservations_count = $stmtTotal->fetchColumn(); // Fetch only the count

    // Query 2: Pending Reservations Count for the user
    $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM venue_reservations WHERE user_id = ? AND status = 'pending'");
    $stmtPending->execute([$user_id]);
    $pending_reservations_count = $stmtPending->fetchColumn(); // Fetch only the count

    // Query 3: Upcoming Reservations Count for the user (Confirmed and date is today or later)
    // Using CURDATE() in the SQL query is generally more reliable than passing a PHP date string
    $stmtUpcoming = $pdo->prepare("SELECT COUNT(*) FROM venue_reservations WHERE user_id = ? AND status = 'confirmed' AND event_date >= CURDATE()");
    $stmtUpcoming->execute([$user_id]);
    $upcoming_reservations_count = $stmtUpcoming->fetchColumn(); // Fetch only the count

} catch (PDOException $e) {
    error_log("Error fetching dashboard counts for user $user_id: " . $e->getMessage());
    // Counts will remain 0, dashboard cards will show 0, which is acceptable in case of error
}

// **7. Fetch User's Recent Reservations for the Table**
$recent_reservations = []; // Use a new variable name for clarity
try {
     $stmtRecent = $pdo->prepare(
        "SELECT r.id, r.event_date, r.status, r.created_at,
                 v.id as venue_id, v.title as venue_title
           FROM venue_reservations r -- Corrected table name
           JOIN venue v ON r.venue_id = v.id
           WHERE r.user_id = ?
           ORDER BY r.event_date DESC, r.created_at DESC
           LIMIT 5" // Limit to fetch only the most recent 5 for the dashboard table
     );
     $stmtRecent->execute([$user_id]);
     $recent_reservations = $stmtRecent->fetchAll();

} catch (PDOException $e) {
     error_log("Error fetching recent reservations for user $user_id: " . $e->getMessage());
     // $recent_reservations remains empty, user will see a "no reservations" message in the table
}


// --- Helper function for status badges (reused from client_dashboard) ---
// This function is fine as is, no changes needed based on the request
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'confirmed': return 'bg-green-100 text-green-800';
        case 'cancelled': case 'rejected': return 'bg-red-100 text-red-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'completed': return 'bg-purple-100 text-purple-800'; // Added completed status
        default: return 'bg-gray-100 text-gray-800'; // For other statuses
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        .sidebar-link { transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out; }
        /* Style for sticky elements */
        nav { position: sticky; top: 0; z-index: 10; }
        aside { position: sticky; top: 64px; /* Height of nav */ height: calc(100vh - 64px); }
        main { min-height: calc(100vh - 64px); }

        /* Custom styles for notification badge */
        .notification-icon-container {
            position: relative;
            display: inline-block; /* Allows positioning the badge relative to this */
            margin-right: 1rem; /* Space between notification icon and logout */
        }

        .notification-badge {
            position: absolute;
            top: -5px; /* Adjust vertical position */
            right: -5px; /* Adjust horizontal position */
            background-color: #ef4444; /* Red color */
            color: white;
            border-radius: 9999px; /* Full rounded */
            padding: 0.1rem 0.4rem; /* Adjust padding */
            font-size: 0.75rem; /* Smaller font size */
            font-weight: bold;
            min-width: 1.25rem; /* Minimum width to ensure circle shape */
            text-align: center;
            line-height: 1; /* Adjust line height for vertical centering */
            display: none; /* Hidden by default, shown when count > 0 */
        }
    </style>
</head>
<body class="bg-gray-100">

    <nav class="bg-indigo-600 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
             <a href="../index.php" class="text-xl font-bold hover:text-indigo-200">Ventech Locator</a>
            <div class="flex items-center">
                <div class="notification-icon-container">
                    <a href="user_notification_list.php" class="text-white hover:text-indigo-200" title="View Notifications">
                         <i class="fas fa-bell text-xl"></i>
                    </a>
                    <span id="notification-count-badge" class="notification-badge">0</span>
                </div>

                <span class="mr-4 hidden sm:inline">Welcome, <?= htmlspecialchars($user['username'] ?? 'User') ?>!</span>
                 <a href="user_logout.php" class="bg-white text-indigo-600 hover:bg-gray-200 py-1 px-3 rounded text-sm font-medium transition duration-150 ease-in-out shadow">Logout</a>
            </div>
        </div>
    </nav>

    <div class="flex">
        <aside class="w-64 bg-white p-5 shadow-lg flex flex-col flex-shrink-0">
            <h2 class="text-lg font-semibold mb-5 border-b pb-3 text-gray-700">Menu</h2>
            <ul class="space-y-2 flex-grow">
                 <li><a href="user_dashboard.php" class="sidebar-link flex items-center text-gray-700 font-semibold bg-indigo-50 rounded p-2"><i class="fas fa-home fa-fw mr-3 w-5 text-center text-indigo-600"></i>Dashboard</a></li>
                 <li><a href="user_reservation_manage.php" class="sidebar-link flex items-center text-gray-700 hover:text-indigo-600 hover:bg-indigo-50 rounded p-2"><i class="fas fa-calendar-check fa-fw mr-3 w-5 text-center"></i>My Reservations</a></li>
                 <li><a href="../user_venue_list.php" class="sidebar-link flex items-center text-gray-700 hover:text-indigo-600 hover:bg-indigo-50 rounded p-2"><i class="fas fa-search-location fa-fw mr-3 w-5 text-center"></i>Find Venues</a></li>
                 <li><a href="user_profile.php" class="sidebar-link flex items-center text-gray-700 hover:text-indigo-600 hover:bg-indigo-50 rounded p-2"><i class="fas fa-user-circle fa-fw mr-3 w-5 text-center"></i>Profile</a></li>
            </ul>
            <div class="mt-auto pt-4 border-t">
                 <a href="user_logout.php" class="sidebar-link flex items-center text-gray-700 hover:text-red-600 hover:bg-red-50 rounded p-2"><i class="fas fa-sign-out-alt fa-fw mr-3 w-5 text-center"></i>Logout</a>
            </div>
        </aside>

        <main class="flex-1 p-6 md:p-8 lg:p-10 overflow-y-auto bg-gray-50">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">User Dashboard</h1>

            <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition-shadow">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2 flex items-center"><i class="fas fa-calendar-alt mr-2 text-blue-500"></i>Total Reservations</h3>
                    <p class="text-3xl font-bold text-blue-600"><?= htmlspecialchars($total_reservations_count) ?></p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition-shadow">
                     <h3 class="text-lg font-semibold text-gray-600 mb-2 flex items-center"><i class="fas fa-calendar-day mr-2 text-green-500"></i>Upcoming Events</h3>
                     <p class="text-3xl font-bold text-green-600"><?= htmlspecialchars($upcoming_reservations_count) ?></p>
                     <p class="text-xs text-gray-500 mt-1">Confirmed reservations for today or later.</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition-shadow">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2 flex items-center"><i class="fas fa-hourglass-half mr-2 text-yellow-500"></i>Pending Requests</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?= htmlspecialchars($pending_reservations_count) ?></p>
                     <p class="text-xs text-gray-500 mt-1">Awaiting confirmation from venue owner.</p>
                </div>
            </section>

            <section class="mb-8">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6 rounded-lg shadow-lg flex flex-col md:flex-row justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold mb-2">Ready to book a new venue?</h2>
                        <p class="mb-4 md:mb-0">Find the perfect space for your next event.</p>
                    </div>
                    <a href="../user_venue_list.php" class="bg-white text-indigo-600 hover:bg-gray-100 font-bold py-2 px-5 rounded-full shadow transition duration-300 ease-in-out transform hover:scale-105 flex items-center">
                        <i class="fas fa-search mr-2"></i> Find Venues
                    </a>
                </div>
            </section>

            <section>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b pb-2">Your Recent Reservations</h2>
                <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                    <?php if (count($recent_reservations) > 0): ?>
                        <table class="w-full table-auto text-sm text-left">
                            <thead class="bg-gray-100 text-xs text-gray-600 uppercase">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Venue</th>
                                    <th scope="col" class="px-6 py-3">Event Date</th>
                                    <th scope="col" class="px-6 py-3">Status</th>
                                    <th scope="col" class="px-6 py-3">Reserved On</th>
                                    <th scope="col" class="px-6 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reservations as $reservation): // Loop through the recent 5 ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                        <?php // Link to venue display page if available (adjust path) ?>
                                        <a href="../venue_display.php?id=<?= htmlspecialchars($reservation['venue_id'] ?? '') ?>" class="hover:text-indigo-600" title="View Venue Details">
                                            <?= htmlspecialchars($reservation['venue_title'] ?? 'N/A') ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= htmlspecialchars(date("D, M d, Y", strtotime($reservation['event_date'] ?? ''))) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 inline-block rounded-full text-xs font-semibold <?= getStatusBadgeClass($reservation['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($reservation['status'] ?? 'N/A')) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                                        <?= htmlspecialchars(date("M d, Y H:i", strtotime($reservation['created_at'] ?? ''))) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                         <a href="../reservation_details.php?id=<?= htmlspecialchars($reservation['id'] ?? '') ?>" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View Details</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if($total_reservations_count > count($recent_reservations)): // Check if there are more than 5 total reservations ?>
                        <div class="p-4 text-center border-t">
                            <a href="user_reservation_manage.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">View All Reservations &rarr;</a>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="p-6 text-center text-gray-600">You haven't made any reservations yet.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Function to fetch unread notifications
        function fetchNotifications() {
            // Adjust the path to your user_notification.php file if needed
            const notificationEndpoint = 'user_notification.php?action=fetch';

            fetch(notificationEndpoint)
                .then(response => {
                    // Check if the response is OK (status code 200-299)
                    if (!response.ok) {
                        console.error('Error fetching notifications:', response.statusText);
                        // Optionally hide the badge or show an error state
                        document.getElementById('notification-count-badge').style.display = 'none';
                        return Promise.reject('Network response was not ok.');
                    }
                    return response.json(); // Parse the JSON response
                })
                .then(data => {
                    // Check if the JSON response indicates success
                    if (data.success) {
                        const notificationCount = data.notifications.length;
                        const badge = document.getElementById('notification-count-badge');

                        if (notificationCount > 0) {
                            badge.textContent = notificationCount; // Update the badge text
                            badge.style.display = 'inline-block'; // Show the badge
                        } else {
                            badge.style.display = 'none'; // Hide the badge if no unread notifications
                        }
                        // You would typically also store the notifications here to display them when the icon is clicked
                        // console.log('Fetched notifications:', data.notifications); // Log notifications for debugging
                    } else {
                        console.error('Error from notification endpoint:', data.message);
                         document.getElementById('notification-count-badge').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('There was a problem with the fetch operation:', error);
                    // Hide the badge on fetch errors
                    document.getElementById('notification-count-badge').style.display = 'none';
                });
        }

        // Fetch notifications when the page loads
        document.addEventListener('DOMContentLoaded', fetchNotifications);

        // Periodically fetch notifications (e.g., every 30 seconds)
        // Adjust the interval time (in milliseconds) as needed
        const notificationCheckInterval = 30000; // 30 seconds
        setInterval(fetchNotifications, notificationCheckInterval);

        // Note: This script only fetches and displays the count.
        // You will need additional JavaScript to:
        // 1. Show a dropdown/modal with the notification list when the bell icon is clicked.
        // 2. Implement the 'mark_read' functionality when notifications are viewed or dismissed.
        // 3. Create the user_notification_list.php page to display all notifications.

    </script>

</body>
</html>
