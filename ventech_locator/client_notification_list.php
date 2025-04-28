<?php
// Start the session to access client user data
session_start();

// Check if the client is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the client login page
    // Adjust path if needed, assuming client_login.php is in the same directory
    header('Location: client_login.php');
    exit;
}

// Include the database connection file
// Ensure this path is correct relative to client_notification_list.php
// Assuming includes folder is one level up from the directory containing this file (e.g., client/)
require_once 'includes/db_connection.php'; // Adjust path if needed

// Check if the PDO connection is available
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Log the error and display a user-friendly message
    error_log("PDO connection not available in client_notification_list.php");
    die("Sorry, we're experiencing technical difficulties with the database. Please try again later.");
}


// Get the logged-in client's user ID from the session
$loggedInClientId = $_SESSION['user_id'];

// Initialize variables
$notifications = [];
$message = ''; // Message if there are no notifications

// Fetch all notifications for the logged-in client
try {
    // Prepare a SQL statement to select all notifications for the client
    // Joins with venue_reservations and venue tables to get related info (venue title, booker username)
    $stmt = $pdo->prepare("
        SELECT
            un.id,
            un.message,
            un.created_at,
            un.is_read,
            un.reservation_id,
            un.status_changed_to, -- Useful to show the new status in the list
            r.user_id as booker_user_id, -- User who made the reservation
            u.username as booker_username, -- Booker's username
            v.title as venue_title -- Fetch venue title for context
        FROM user_notifications un
        LEFT JOIN venue_reservations r ON un.reservation_id = r.id
        LEFT JOIN users u ON r.user_id = u.id -- Join to get booker's username
        LEFT JOIN venue v ON r.venue_id = v.id -- Join to get venue title
        WHERE un.user_id = :client_user_id -- Filter by the logged-in client's user ID
        ORDER BY un.created_at DESC -- Order by newest notifications first
    ");

    // Execute the statement with the logged-in client ID
    $stmt->execute([':client_user_id' => $loggedInClientId]);

    // Fetch all notifications as an associative array
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($notifications)) {
        $message = "You have no notifications.";
    }

} catch (PDOException $e) {
    // Catch database-related errors
    error_log("Database Error fetching client notifications list (Client ID: {$loggedInClientId}): " . $e->getMessage());
    $message = "An error occurred while loading your notifications. Please try again later.";
} catch (Exception $e) {
    // Catch other application errors
    error_log("Application Error fetching client notifications list (Client ID: {$loggedInClientId}): " . $e->getMessage());
    $message = "An unexpected error occurred. Please try again later.";
}

// Optional: Mark all notifications on this page as read when viewed
// This is a common pattern for notification list pages
if (!empty($notifications)) {
    try {
        // Get the IDs of the notifications fetched that are currently unread
        $unreadNotificationIds = array_column(array_filter($notifications, fn($n) => !$n['is_read']), 'id');

        // Only mark as read if there are unread IDs and the connection is valid
        if (!empty($unreadNotificationIds) && isset($pdo) && $pdo instanceof PDO) {
             // Create placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($unreadNotificationIds), '?'));

            // Prepare the SQL statement to update the is_read status
            $stmtMarkRead = $pdo->prepare("
                UPDATE user_notifications
                SET is_read = TRUE, updated_at = NOW()
                WHERE user_id = :client_user_id AND id IN ($placeholders) AND is_read = FALSE
            ");

            // Prepare the parameters: client ID first, then the list of notification IDs
            $params = array_merge([':client_user_id' => $loggedInClientId], $unreadNotificationIds);

            // Execute the update statement
            $stmtMarkRead->execute($params);

            // Note: The notifications displayed will still show their original is_read status
            // until the page is reloaded, but they are marked as read in the DB.

        }
    } catch (PDOException $e) {
        error_log("Database Error marking client notifications as read (Client ID: {$loggedInClientId}): " . $e->getMessage());
        // Displaying an error message for this might be intrusive, just log it.
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Notifications - Client</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css"> <style>
        body { font-family: 'Roboto', sans-serif; }
        .notification-item {
            border-left: 4px solid;
            transition: background-color 0.2s ease-in-out;
        }
        .notification-item.unread {
            border-left-color: #3b82f6; /* blue-500 */
            background-color: #eff6ff; /* blue-50 */
        }
         .notification-item.read {
            border-left-color: #d1d5db; /* gray-300 */
            background-color: #f9fafb; /* gray-50 */
        }
        .notification-item:hover {
            background-color: #e0f2fe; /* blue-100 */
        }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto">
        <header class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h1 class="text-3xl font-bold text-blue-700 mb-2">My Notifications</h1>
            <p class="text-gray-600">View updates regarding your venues and bookings.</p>
             <div class="mt-4">
                 <a href="client_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
             </div>
        </header>

        <?php if (!empty($message)): ?>
             <?php
                 $messageClass = 'bg-blue-100 border-blue-500 text-blue-700';
                 if (strpos($message, 'no notifications') !== false) {
                     $messageClass = 'bg-yellow-100 border-yellow-500 text-yellow-700';
                 } elseif (strpos($message, 'error occurred') !== false || strpos($message, 'Database connection') !== false) {
                      $messageClass = 'bg-red-100 border-red-500 text-red-700';
                 }
            ?>
            <div class="<?= $messageClass ?> border-l-4 p-4 mb-6 rounded shadow-md">
                 <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <div class="space-y-4">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                        // Determine the CSS class based on read status
                        $itemClass = $notification['is_read'] ? 'read' : 'unread';

                        // Determine link and icon based on notification type/status change
                        $link = '#'; // Default link if no specific action
                        $iconClass = 'fas fa-info-circle text-gray-500'; // Default icon
                        $notificationText = htmlspecialchars($notification['message'] ?? ''); // Default message display

                        // Check if the notification is related to a reservation status change
                        if (!empty($notification['reservation_id']) && !empty($notification['status_changed_to'])) {
                             // Link to the client's reservation management page
                             // You might link to a specific reservation detail page if you have one
                             $link = '../reservation_manage.php'; // Adjust path if needed

                             // Customize message and icon based on the status it changed to
                             switch (strtolower($notification['status_changed_to'])) {
                                 case 'cancellation_requested':
                                     $iconClass = 'fas fa-times-circle text-red-600'; // Red icon for cancellation requests
                                     $notificationText = "Cancellation requested for booking ID {$notification['reservation_id']} for venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "'.";
                                     if (!empty($notification['booker_username'])) {
                                         $notificationText .= " By user '" . htmlspecialchars($notification['booker_username']) . "'.";
                                     }
                                     break;
                                 case 'pending':
                                     $iconClass = 'fas fa-hourglass-half text-yellow-600';
                                      $notificationText = "New booking request (ID: {$notification['reservation_id']}) for your venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "'.";
                                     if (!empty($notification['booker_username'])) {
                                         $notificationText .= " By user '" . htmlspecialchars($notification['booker_username']) . "'.";
                                     }
                                     break;
                                 case 'accepted':
                                     $iconClass = 'fas fa-check-circle text-blue-600';
                                     $notificationText = "Booking ID {$notification['reservation_id']} for your venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "' was accepted.";
                                     break;
                                 case 'confirmed':
                                      $iconClass = 'fas fa-check-circle text-green-600';
                                      $notificationText = "Booking ID {$notification['reservation_id']} for your venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "' is now confirmed.";
                                      break;
                                 case 'cancelled': // If client cancelled it themselves, they might not need a notification, but this covers all cases
                                      $iconClass = 'fas fa-ban text-gray-600';
                                      $notificationText = "Booking ID {$notification['reservation_id']} for your venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "' was cancelled.";
                                      break;
                                 case 'rejected':
                                      $iconClass = 'fas fa-times text-red-600';
                                      $notificationText = "Booking ID {$notification['reservation_id']} for your venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "' was rejected.";
                                      break;
                                 case 'completed':
                                      $iconClass = 'fas fa-calendar-check text-purple-600';
                                      $notificationText = "Booking ID {$notification['reservation_id']} for your venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "' is marked as completed.";
                                      break;
                                 default:
                                     // Fallback for reservation notifications with unknown status_changed_to
                                     $iconClass = 'fas fa-calendar-alt text-blue-600';
                                     $notificationText = htmlspecialchars($notification['message'] ?? "Update for reservation ID {$notification['reservation_id']} related to venue '" . htmlspecialchars($notification['venue_title'] ?? 'N/A') . "'.");
                                     break;
                             }
                        }
                         // Add more conditions here for other types of notifications if needed
                         // Example: if ($notification['type'] === 'new_venue_review') { ... }

                    ?>
                    <div class="notification-item <?= $itemClass ?> p-4 rounded-lg shadow-sm flex items-start">
                        <div class="flex-shrink-0 mr-4">
                            <i class="<?= $iconClass ?> text-xl"></i>
                        </div>
                        <div class="flex-grow">
                            <p class="text-sm text-gray-800 mb-1">
                                <?= nl2br($notificationText) ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Received: <?= htmlspecialchars(date("M d, Y H:i", strtotime($notification['created_at'] ?? ''))) ?>
                            </p>
                        </div>
                        <div class="flex-shrink-0 ml-4 text-right">
                            <?php if ($link !== '#'): // Only show link if a specific page is relevant ?>
                                <a href="<?= $link ?>" class="text-blue-600 hover:underline text-sm">View</a>
                            <?php endif; ?>
                             <?php if (!$notification['is_read']): ?>
                                 <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="p-6 text-center text-gray-600">You have no notifications yet.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($notifications) && count(array_filter($notifications, fn($n) => !$n['is_read'])) > 0): ?>
             <?php endif; ?>

    </div>
</body>
</html>
