<?php
// Database connection parameters
include('db_connection.php');

// Get venue ID from the URL
$venue_id = $_GET['id'] ?? null;

if (!$venue_id) {
    die("No venue ID provided.");
}

// Fetch venue details from the database
try {
    $stmt = $pdo->prepare("SELECT * FROM venue WHERE id = ?");
    $stmt->execute([$venue_id]);
    $venue = $stmt->fetch();

    if (!$venue) {
        die("Venue not found.");
    }
} catch (PDOException $e) {
    error_log("Error fetching venue data: " . $e->getMessage());
    die("Error fetching venue data.");
}

// Handle form submission for updating venue details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $image_path = $_POST['image_path']; // Add logic for image upload if needed
    $status = $_POST['status'];  // Get the selected status

    try {
        $stmt = $pdo->prepare("UPDATE venue SET title = ?, description = ?, price = ?, image_path = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $description, $price, $image_path, $status, $venue_id]);

        header("Location: client_dashboard.php?message=Venue updated successfully.");
        exit();
    } catch (PDOException $e) {
        error_log("Error updating venue: " . $e->getMessage());
        die("Error updating venue.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

    <!-- Navbar (Optional) -->
    <div class="bg-orange-500 p-4 text-white text-center">
        <h1 class="text-xl font-bold">Edit Venue</h1>
    </div>

    <!-- Main Content -->
    <div class="max-w-3xl mx-auto p-6 bg-white shadow-md rounded-md mt-8">
        <h2 class="text-2xl font-bold mb-6">Edit Your Venue</h2>
        
        <form action="edit_venue.php?id=<?= $venue['id'] ?>" method="POST">
            <div class="mb-4">
                <label for="title" class="block text-gray-700">Venue Title</label>
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($venue['title']) ?>" class="w-full p-3 border rounded-md" required>
            </div>

            <div class="mb-4">
                <label for="description" class="block text-gray-700">Description</label>
                <textarea name="description" id="description" class="w-full p-3 border rounded-md" rows="4" required><?= htmlspecialchars($venue['description']) ?></textarea>
            </div>

            <div class="mb-4">
                <label for="price" class="block text-gray-700">Price per Hour</label>
                <input type="number" name="price" id="price" value="<?= $venue['price'] ?>" class="w-full p-3 border rounded-md" required>
            </div>

            <!-- Image URL (optional) -->
            <div class="mb-4">
                <label for="image_path" class="block text-gray-700">Image URL (optional)</label>
                <input type="text" name="image_path" id="image_path" value="<?= htmlspecialchars($venue['image_path']) ?>" class="w-full p-3 border rounded-md">
            </div>

            <!-- Status dropdown -->
            <div class="mb-4">
                <label for="status" class="block text-gray-700">Status</label>
                <select name="status" id="status" class="w-full p-3 border rounded-md" required>
                    <option value="open" <?= $venue['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="closed" <?= $venue['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>

            <button type="submit" class="bg-blue-500 text-white p-3 rounded-md hover:bg-blue-600">Update Venue</button>
        </form>
    </div>

</body>
</html>
