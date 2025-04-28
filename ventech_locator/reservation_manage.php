<?php 
require_once 'includes/db_connection.php'; // Ensure this path is correct

// Initialize message variable
$message = '';

// Reservation form submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['venue_id'], $_POST['start_time'], $_POST['end_time']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    try {
        // Get venue price per hour
        $stmt = $pdo->prepare("SELECT price_per_hour FROM venue WHERE id = :venue_id");
        $stmt->execute([':venue_id' => $_POST['venue_id']]);
        $venue = $stmt->fetch();

        if ($venue) {
            // Calculate total cost based on hours booked
            $start_time = strtotime($_POST['start_time']);
            $end_time = strtotime($_POST['end_time']);
            $duration_in_hours = ($end_time - $start_time) / 3600;
            $total_cost = $venue['price_per_hour'] * $duration_in_hours;

            // Insert reservation with calculated total cost
            $reservationStmt = $pdo->prepare("
                INSERT INTO venue_reservations
                (venue_id, user_id, event_date, start_time, end_time, first_name, last_name, email, mobile_country_code, mobile_number, country, notes, total_cost, status)
                VALUES
                (:venue_id, :user_id, :event_date, :start_time, :end_time, :first_name, :last_name, :email, :mobile_country_code, :mobile_number, :country, :notes, :total_cost, 'pending')
            ");
            $reservationStmt->execute([
                ':venue_id' => $_POST['venue_id'],
                ':user_id' => $_POST['user_id'] ?? null,
                ':event_date' => $_POST['event_date'],
                ':start_time' => $_POST['start_time'],
                ':end_time' => $_POST['end_time'],
                ':first_name' => $_POST['first_name'],
                ':last_name' => $_POST['last_name'],
                ':email' => $_POST['email'],
                ':mobile_country_code' => $_POST['mobile_country_code'] ?? '',
                ':mobile_number' => $_POST['mobile_number'] ?? '',
                ':country' => $_POST['country'] ?? '',
                ':notes' => $_POST['notes'] ?? '',
                ':total_cost' => $total_cost
            ]);

            echo 'success';
            exit;
        } else {
            echo 'Venue not found.';
            exit;
        }
    } catch (PDOException $e) {
        echo 'Error: ' . $e->getMessage();
        exit;
    }
}

// Handle reservation status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['status'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE venue_reservations
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $_POST['status'],
            ':id' => $_POST['reservation_id']
        ]);

        $message = "Reservation status updated successfully.";
    } catch (PDOException $e) {
        error_log("Failed to update reservation status: " . $e->getMessage());
        $message = "An error occurred while updating the reservation status.";
    }
}

// Fetch reservations
try {
    $stmt = $pdo->query("
        SELECT
            r.*,
            v.title AS venue_name,
            v.image_path,
            v.price_per_hour
        FROM venue_reservations r
        JOIN venue v ON r.venue_id = v.id
        ORDER BY r.created_at DESC
    ");
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch reservations: " . $e->getMessage());
    $reservations = [];
    $message = "Failed to load reservations.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reservations - Client</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <header class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h1 class="text-3xl font-bold text-blue-700 mb-2">Client Panel: Manage Reservations</h1>
            <p class="text-gray-600">View and manage your venue reservations.</p>
        </header>

        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-md">
                <p class="font-bold">Success</p>
                <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($reservations as $res): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                <img src="<?= htmlspecialchars($res['image_path']) ?>" alt="Venue Image" class="w-full h-48 object-cover">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-blue-700 mb-2"><?= htmlspecialchars($res['venue_name']) ?></h2>
                        <p class="text-gray-600 mb-2">
                            <span class="font-medium">Booked by:</span> <?= htmlspecialchars($res['first_name'] . ' ' . $res['last_name']) ?>
                        </p>
                        <div class="mb-3">
                            <p class="text-sm text-gray-500"><span class="font-medium">Date:</span> <?= htmlspecialchars($res['event_date']) ?></p>
                            <p class="text-sm text-gray-500"><span class="font-medium">Time:</span> <?= htmlspecialchars($res['start_time']) ?> - <?= htmlspecialchars($res['end_time']) ?></p>
                            <p class="text-sm text-gray-700 font-semibold"><span class="font-medium">Total Cost:</span> ₱<?= number_format($res['total_cost'], 2) ?></p>
                        </div>
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                            <div class="flex items-center space-x-2">
                                <label for="status_<?= $res['id'] ?>" class="text-sm font-medium text-gray-700">Status:</label>
                                <select name="status" id="status_<?= $res['id'] ?>" class="block w-full border-gray-300 rounded shadow">
                                    <?php
                                    $statuses = ['pending', 'accepted', 'confirmed', 'cancelled', 'completed'];
                                    foreach ($statuses as $status) {
                                        $selected = ($res['status'] === $status) ? 'selected' : '';
                                        echo "<option value='$status' $selected>" . ucfirst($status) . "</option>";
                                    }
                                    ?>
                                </select>
                                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white py-2 px-4 rounded">
                                    Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>



