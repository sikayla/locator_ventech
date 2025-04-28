<?php
// Start the session to access user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page. Adjust path if needed.
    header('Location: user_login.php');
    exit;
}

// Include the database connection file
// Ensure this path is correct relative to user_reservation_manage.php
require_once '../includes/db_connection.php'; // Adjust path if needed

// Get the logged-in user's ID from the session
$loggedInUserId = $_SESSION['user_id'];

// Initialize variables
$userReservations = [];
$message = ''; // General messages (e.g., no reservations)
$actionMessage = ''; // Messages related to cancellation actions

// --- Handle Cancellation Action Messages ---
// Check for messages from process_user_cancellation.php
if (isset($_GET['cancel_success'])) {
    if ($_GET['cancel_success'] == 'true') {
         $actionMessage = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6 shadow relative' role='alert'>
                            <p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Success!</p>
                            <p>Your reservation has been cancelled.</p>
                            <button type='button' class='absolute top-0 right-0 mt-2 mr-2 text-green-700 hover:text-green-900' onclick='this.parentElement.style.display=\"none\";' aria-label='Close'>
                                <i class='fas fa-times'></i>
                            </button>
                          </div>";
    } elseif ($_GET['cancel_success'] == 'requested') {
         // Message for successful cancellation request (intermediate status)
         $actionMessage = "<div class='bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-md mb-6 shadow relative' role='alert'>
                             <p class='font-bold'><i class='fas fa-info-circle mr-2'></i>Request Sent!</p>
                             <p>Your cancellation request has been sent to the venue owner and is awaiting their review.</p>
                             <button type='button' class='absolute top-0 right-0 mt-2 mr-2 text-blue-700 hover:text-blue-900' onclick='this.parentElement.style.display=\"none\";' aria-label='Close'>
                                 <i class='fas fa-times'></i>
                             </button>
                           </div>";
    }
} elseif (isset($_GET['cancel_error'])) {
     $errorMessageText = "An error occurred while trying to cancel your reservation.";
     switch($_GET['cancel_error']) {
         case 'invalid_id':
             $errorMessageText = "Invalid reservation ID.";
             break;
         case 'not_found':
             $errorMessageText = "Reservation not found or does not belong to you.";
             break;
         case 'status_not_cancellable':
             $errorMessageText = "This reservation status cannot be cancelled.";
             break;
         case 'db_error':
             $errorMessageText = "A database error occurred during cancellation. Please try again.";
             break;
         case 'unauthenticated':
             $errorMessageText = "You must be logged in to cancel a reservation.";
             break;
         default:
             // Keep the default error message
             break;
     }
     $actionMessage = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6 shadow relative' role='alert'>
                         <p class='font-bold'><i class='fas fa-exclamation-triangle mr-2'></i>Error!</p>
                         <p>" . htmlspecialchars($errorMessageText) . "</p>
                         <button type='button' class='absolute top-0 right-0 mt-2 mr-2 text-green-700 hover:text-green-900' onclick='this.parentElement.style.display=\'none\';' aria-label='Close'>
                                 <i class='fas fa-times'></i>
                             </button>
                       </div>";
}
// --- End Handle Cancellation Action Messages ---


// --- Handle Messages from Client Action (Approval/Rejection) ---
// Check for messages from process_client_cancellation_action.php
if (isset($_GET['action_success'])) {
     if ($_GET['action_success'] == 'cancellation_approved') {
          $actionMessage = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6 shadow relative' role='alert'>
                             <p class='font-bold'><i class='fas fa-check-circle mr-2'></i>Update!</p>
                             <p>Your cancellation request has been approved by the venue owner.</p>
                             <button type='button' class='absolute top-0 right-0 mt-2 mr-2 text-green-700 hover:text-green-900' onclick='this.parentElement.style.display=\"none\";' aria-label='Close'>
                                 <i class='fas fa-times'></i>
                             </button>
                           </div>";
     } elseif ($_GET['action_success'] == 'cancellation_rejected') {
          $actionMessage = "<div class='bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-6 shadow relative' role='alert'>
                             <p class='font-bold'><i class='fas fa-info-circle mr-2'></i>Update!</p>
                             <p>Your cancellation request has been rejected by the venue owner.</p>
                             <button type='button' class='absolute top-0 right-0 mt-2 mr-2 text-yellow-700 hover:text-yellow-900' onclick='this.parentElement.style.display=\"none\";' aria-label='Close'>
                                 <i class='fas fa-times'></i>
                             </button>
                           </div>";
     }
} elseif (isset($_GET['action_error'])) {
     // Handle errors from client action processing
     $actionMessage = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6 shadow relative' role='alert'>
                         <p class='font-bold'><i class='fas fa-exclamation-triangle mr-2'></i>Error!</p>
                         <p>An error occurred processing an update to your reservation.</p>
                         <button type='button' class='absolute top-0 right-0 mt-2 mr-2 text-red-700 hover:text-red-900' onclick='this.parentElement.style.display=\"none\";' aria-label='Close'>
                             <i class='fas fa-times'></i>
                         </button>
                       </div>";
}
// --- End Handle Messages from Client Action ---


// --- DEBUG: Check if PDO is connected ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    // This check is primarily for debugging setup issues.
    // In a production environment, the require_once should handle fatal errors.
    // echo '<div class="bg-red-200 p-3 mb-3">DEBUG: PDO connection failed!</div>';
    // Consider a more robust error handling for production if db connection fails
}
// --- END DEBUG ---


// Fetch reservations for the logged-in user
try {
    // Ensure $pdo is available before preparing statement
    if (!isset($pdo) || !$pdo instanceof PDO) {
         throw new Exception("Database connection not established.");
    }

    // Modify the query to EXCLUDE reservations with 'cancelled', 'rejected', or 'completed' status
    // if you want to hide them directly from the database fetch.
    // Alternatively, you can fetch all and filter in the loop (as shown below).
    $stmt = $pdo->prepare("
        SELECT
            r.*, -- Fetch all reservation details
            v.title AS venue_name,
            v.image_path,
            v.price_per_hour -- Fetch the venue's price per hour
        FROM venue_reservations r
        JOIN venue v ON r.venue_id = v.id
        WHERE r.user_id = :user_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':user_id' => $loggedInUserId]);
    $userReservations = $stmt->fetchAll();

    if (empty($userReservations)) {
        $message = "You have no reservations yet.";
    }

} catch (PDOException $e) {
    error_log("Database Error fetching user reservations (User ID: {$loggedInUserId}): " . $e->getMessage());
    $message = "An error occurred while loading your reservations. Please try again later.";
} catch (Exception $e) {
     error_log("Application Error fetching user reservations (User ID: {$loggedInUserId}): " . $e->getMessage());
     $message = "An unexpected error occurred. Please try again later.";
}

// --- Helper function for status badges ---
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'pending': return 'status-pending';
        case 'accepted': return 'status-accepted';
        case 'confirmed': return 'status-confirmed';
        case 'cancelled': return 'status-cancelled';
        case 'completed': return 'status-completed';
        case 'cancellation_requested': return 'status-cancelled'; // Use red for cancellation requested
        default: return 'status-unknown';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Reservations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css"> <style>
        /* Basic styling for the status badges */
        .status-badge {
            padding: 0.1rem 0.4rem;
            display: inline-block;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending { background-color: #fef3c7; color: #b45309; } /* yellow-100, yellow-800 */
        .status-accepted { background-color: #dbeafe; color: #1e40af; } /* blue-100, blue-800 */
        .status-confirmed { background-color: #d1fae5; color: #065f46; } /* green-100, green-800 */
        .status-cancelled { background-color: #fee2e2; color: #991b1b; } /* red-100, red-800 */
        .status-completed { background-color: #ede9fe; color: #5b21b6; } /* purple-100, purple-800 */
        .status-unknown { background-color: #e5e7eb; color: #374151; } /* gray-100, gray-800 */
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <header class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h1 class="text-3xl font-bold text-blue-700 mb-2">My Venue Reservations</h1>
            <p class="text-gray-600">View the status and details of your booked venues.</p>
             <div class="mt-4">
                 <a href="user_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a> </div>
        </header>

        <?php if (!empty($message) && empty($userReservations)): ?>
            <?php
                 $messageClass = 'bg-blue-100 border-blue-500 text-blue-700';
                 if (strpos($message, 'no reservations') !== false) {
                     $messageClass = 'bg-yellow-100 border-yellow-500 text-yellow-700';
                 } elseif (strpos($message, 'error occurred') !== false || strpos($message, 'Database connection') !== false) {
                      $messageClass = 'bg-red-100 border-red-500 text-red-700';
                 }
            ?>
            <div class="<?= $messageClass ?> border-l-4 p-4 mb-6 rounded shadow-md">
                 <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <?= $actionMessage ?>


        <?php if (!empty($userReservations)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($userReservations as $res): ?>
                    <?php
                        // --- Start Calculation Logic ---
                        // Calculate duration in hours
                        $start_time_str = $res['event_date'] . ' ' . $res['start_time'];
                        $end_time_str = $res['event_date'] . ' ' . $res['end_time'];

                        $start_time_ts = strtotime($start_time_str);
                        $end_time_ts = strtotime($end_time_str);

                        // Handle case where end time is on the next day (e.g., 10 PM to 2 AM)
                        if ($end_time_ts !== false && $start_time_ts !== false && $end_time_ts < $start_time_ts) {
                             $end_time_ts = strtotime($end_time_str . ' +1 day');
                        }

                        // Calculate duration only if both timestamps are valid
                        $duration_seconds = 0;
                        $duration_hours = 0;
                        $calculatedCost = 0;

                        if ($start_time_ts !== false && $end_time_ts !== false) {
                            $duration_seconds = $end_time_ts - $start_time_ts;
                            if ($duration_seconds > 0) { // Ensure duration is positive
                                $duration_hours = $duration_seconds / 3600;

                                // Calculate the total cost on the fly based on fetched data
                                $calculatedCost = $duration_hours * ($res['price_per_hour'] ?? 0); // Use 0 if price_per_hour is null/missing
                            }
                        }
                        // --- End Calculation Logic ---

                        // Format for display
                        $formatted_duration = number_format($duration_hours, 1);
                        $formatted_calculated_cost = number_format($calculatedCost, 2);

                        // Determine if cancellation is allowed based on status
                        // User can request cancellation if status is pending, accepted, or confirmed
                        $canRequestCancellation = in_array(strtolower($res['status'] ?? ''), ['pending', 'accepted', 'confirmed']);

                        // Define statuses that should NOT be displayed (hide these cards)
                        $excludedStatuses = ['cancelled', 'rejected', 'completed'];
                        $shouldDisplayCard = !in_array(strtolower($res['status'] ?? ''), $excludedStatuses);

                    ?>
                    <?php if ($shouldDisplayCard): // Only display the card if its status is not excluded ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <img src="<?= htmlspecialchars($res['image_path'] ?? '') ?>" alt="Venue Image" class="w-full h-48 object-cover">
                            <div class="p-6">
                                <h2 class="text-xl font-semibold text-blue-700 mb-2"><?= htmlspecialchars($res['venue_name'] ?? 'N/A') ?></h2>
                                <div class="mb-3">
                                    <p class="text-sm text-gray-500"><span class="font-medium">Date:</span> <?= htmlspecialchars($res['event_date'] ?? 'N/A') ?></p>
                                    <p class="text-sm text-gray-500"><span class="font-medium">Time:</span> <?= htmlspecialchars($res['start_time'] ?? 'N/A') ?> - <?= htmlspecialchars($res['end_time'] ?? 'N/A') ?></p>
                                    <p class="text-sm text-gray-500"><span class="font-medium">Price per Hour:</span> ₱<?= number_format($res['price_per_hour'] ?? 0, 2) ?></p>
                                    <p class="text-sm text-gray-500"><span class="font-medium">Duration:</span> <?= $formatted_duration ?> hours</p>
                                    <p class="text-sm text-gray-700 font-semibold"><span class="font-medium">Calculated Cost:</span> ₱<?= $formatted_calculated_cost ?></p>
                                     <?php
                                        $storedCost = $res['total_cost'] ?? 0; // Use 0 if stored cost is null/missing
                                        if (abs($calculatedCost - $storedCost) > 0.01): // Check if recalculated cost differs significantly from stored cost
                                     ?>
                                          <p class="text-sm text-red-500">(Note: Stored cost was ₱<?= number_format($storedCost, 2) ?>)</p>
                                     <?php endif; ?>
                                </div>

                                <div class="mt-4">
                                    <p class="text-sm font-medium text-gray-700 mb-2">
                                        Status:
                                        <?php
                                            $statusClass = 'status-unknown'; // Default class
                                            switch (strtolower($res['status'] ?? 'unknown')) {
                                                case 'pending': $statusClass = 'status-pending'; break;
                                                case 'accepted': $statusClass = 'status-accepted'; break;
                                                case 'confirmed': $statusClass = 'status-confirmed'; break;
                                                case 'cancelled': $statusClass = 'status-cancelled'; break;
                                                case 'completed': $statusClass = 'status-completed'; break;
                                                case 'cancellation_requested': $statusClass = 'status-cancelled'; break; // Use red for cancellation requested
                                            }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($res['status'] ?? 'N/A')) ?></span>
                                    </p>
                                    <?php if (!empty($res['notes'])): ?>
                                         <p class="text-sm text-gray-500 mt-2"><span class="font-medium">Notes:</span> <?= nl2br(htmlspecialchars($res['notes'])) ?></p>
                                    <?php endif; ?>

                                    <?php if ($canRequestCancellation): // Show button only if status allows requesting cancellation ?>
                                        <div class="mt-4">
                                            <form method="post" action="process_user_cancellation.php" onsubmit="return confirm('Are you sure you want to request cancellation for this reservation?');">
                                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id']) ?>">
                                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-medium py-2 px-4 rounded shadow transition duration-150 ease-in-out">
                                                    <i class="fas fa-times-circle mr-2"></i>Request Cancellation
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    <?php endif; // End of shouldDisplayCard check ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

