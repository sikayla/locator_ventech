<?php
// Include the database connection and config files from the 'includes' folder
include_once('../includes/db_connection.php'); // Adjusted path
include_once('../includes/config.php'); // Adjusted path

// Start session for login
session_start();

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize email and password input
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Validate email and password
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // If no errors, proceed to authenticate
    if (empty($errors)) {
        // Prepare query to find user by email and check role
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'client'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Check if user exists and password matches
        if ($user) {
            // Now verify the password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email']; // Optionally store email in session
                $_SESSION['user_role'] = 'client'; // ADD THIS LINE

                // Redirect to user dashboard
                header("Location: /ventech_locator/client_dashboard.php");
                exit;
            } else {
                $errors[] = "Incorrect password.";
            }
        } else {
            $errors[] = "No user found with this email.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Roboto', sans-serif; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-lg w-full">
        <h1 class="text-2xl font-bold text-center mb-2">Client Login</h1>
        <h2 class="text-2xl font-bold text-center text-orange-500 mb-4">VenTech - Locator</h2>
        <p class="text-center mb-6">Log in to manage your venues and reservations.</p>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 text-red-700 p-4 mb-4 rounded">
                <?php foreach ($errors as $err): ?>
                    <p>• <?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
            </div>
            <button type="submit" class="w-full bg-orange-500 text-white py-2 rounded-md text-lg font-bold">Login</button>
        </form>

        <div class="mt-4 text-center">
            <a href="client_signup.php" class="text-orange-500 hover:underline">Register</a>
            <a href="forgot_password.php" class="text-orange-500 hover:underline">Forgot your password?</a>
        </div>
    </div>
</body>
</html>