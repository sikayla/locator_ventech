<?php
// Start the session to access user data (client's session)
session_start();

// Set the content type to JSON for the response
header('Content-Type: application/json');

// Include the database connection file
// Ensure this path is correct relative to client_notification.php
// Assuming includes folder is one level up from the directory containing client_notification.php (e.g., client/)
require_once 'includes/db_connection.php'; // Adjust path if needed

// Check if the client is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, return an error response in JSON format
    echo json_encode(['success' => false, 'message' => 'Client not authenticated.']);
    exit; // Stop script execution
}

// Get the logged-in client's user ID from the session
$loggedInClientId = $_SESSION['user_id'];

// Determine the action requested by the client's browser
// Default action is 'count_unread' for the dashboard badge
$action = $_GET['action'] ?? $_POST['action'] ?? 'count_unread';

try {
    // Check if the PDO database connection object is available
    if (!isset($pdo) || !$pdo instanceof PDO) {
        // If PDO is not available, throw an exception
        throw new Exception("Database connection not available.");
    }

    // Use a switch statement to handle different actions
    switch ($action) {
        case 'count_unread':
            // Action to fetch the count of unread notifications for the logged-in client

            // Prepare a SQL statement to count unread notifications for this user ID
            // These are notifications inserted by process_user_cancellation.php
            // or potentially other processes that notify the client.
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS unread_count
                FROM user_notifications
                WHERE user_id = :user_id AND is_read = FALSE
            ");

            // Execute the statement with the logged-in client's user ID
            $stmt->execute([':user_id' => $loggedInClientId]);

            // Fetch the count
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $unreadCount = $result['unread_count'] ?? 0; // Get the count, default to 0 if something goes wrong

            // Return a success response with the unread count
            echo json_encode(['success' => true, 'unread_count' => (int)$unreadCount]); // Cast to int to be sure
            break;

        // You could add other actions here in the future, e.g., 'fetch_all', 'mark_read'
        // case 'fetch_all':
        //     // Logic to fetch all notifications for the client
        //     break;
        // case 'mark_read':
        //     // Logic to mark notifications as read for the client
        //     break;

        default:
            // If an invalid action is specified, return an error response
            echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
            break;
    }

} catch (PDOException $e) {
    // Catch database-related errors
    error_log("Database Error in client_notification.php (Client ID: {$loggedInClientId}): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
} catch (Exception $e) {
    // Catch other application errors
    error_log("Application Error in client_notification.php (Client ID: {$loggedInClientId}): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

exit; // Ensure no further output is sent after the JSON response
?>
