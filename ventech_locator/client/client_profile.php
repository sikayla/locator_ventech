<?php
// Include the database connection and config files
include_once('../includes/db_connection.php'); // Adjusted path
include_once('../includes/config.php'); // Adjusted path


// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: client_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch client details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $contact_number = trim($_POST["contact_number"]);
    $location = trim($_POST["location"]);

    // Update user profile
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, contact_number = ?, location = ? WHERE id = ?");
    $stmt->execute([$username, $email, $contact_number, $location, $user_id]);

    $success = "Profile updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Roboto', sans-serif; } </style>
</head>
<body class="bg-gray-100">

    <!-- Navbar (Optional) -->
    <div class="bg-orange-500 p-4 text-white text-center">
        <h1 class="text-xl font-bold">Client Profile</h1>
    </div>

    <!-- Sidebar Navigation -->
    <div class="flex min-h-screen">
        <div class="w-1/4 bg-white p-6 shadow-md">
            <h2 class="text-xl font-semibold mb-6">Welcome, <?= htmlspecialchars($user['username']) ?></h2>
            <ul class="space-y-4">
                <li><a href="client_dashboard.php" class="text-gray-700 hover:text-orange-500">Dashboard</a></li>
                <li><a href="add_venue.php" class="text-gray-700 hover:text-orange-500">Add New Venue</a></li>
                <li><a href="client_profile.php" class="text-gray-700 hover:text-orange-500">Profile</a></li>
                <li><a href="client_logout.php" class="text-gray-700 hover:text-orange-500">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-6">
            <h2 class="text-2xl font-bold mb-4">Edit Your Profile</h2>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 text-green-700 p-4 mb-4 rounded">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
                        <input type="text" name="contact_number" id="contact_number" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="<?= htmlspecialchars($user['contact_number']) ?>" required>
                    </div>
                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <input type="text" name="location" id="location" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="<?= htmlspecialchars($user['location']) ?>" required>
                    </div>
                </div>
                <div class="mb-6">
                    <button type="submit" class="w-full bg-orange-500 text-white py-2 rounded-md text-lg font-bold">Update Profile</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
