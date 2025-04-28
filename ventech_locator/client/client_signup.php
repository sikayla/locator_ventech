<?php
// Include the database connection and config files from the 'includes' folder
include_once('../includes/db_connection.php'); // Adjusted path
include_once('../includes/config.php'); // Adjusted path

// Initialize errors and success variables
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); // Hash password
    $contact_number = trim($_POST["contact_number"]);
    $location = trim($_POST["location"]);

    // Input validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Email is already registered.";
    }

    // Insert new client into the database if no errors
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, contact_number, location, role) VALUES (?, ?, ?, ?, ?, 'client')");
        if ($stmt->execute([$username, $email, $password, $contact_number, $location])) {
            $success = "Registration successful! You can now log in.";
            header("Location: client_login.php"); // Redirect to login page
            exit;
        } else {
            $errors[] = "Something went wrong, please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Roboto', sans-serif; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-lg w-full">
        <h1 class="text-2xl font-bold text-center mb-2">Create Your Client Account</h1>
        <h2 class="text-2xl font-bold text-center text-orange-500 mb-4">Courts of the World</h2>
        <p class="text-center mb-6">Join our community to post venues and manage your reservations.</p>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 text-red-700 p-4 mb-4 rounded">
                <?php foreach ($errors as $err): ?>
                    <p>• <?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-4 mb-4 rounded">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="username" name="username" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
            </div>
            <div class="mb-4">
                <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
            </div>
            <div class="mb-4">
                <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                <input type="text" id="location" name="location" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
            </div>
            <button type="submit" class="w-full bg-orange-500 text-white py-2 rounded-md text-lg font-bold">Register</button>
        </form>

        <div class="mt-4 text-center">
            <a href="client_login.php" class="text-orange-500 hover:underline">Already have an account? Log in</a>
        </div>
    </div>
</body>
</html>



