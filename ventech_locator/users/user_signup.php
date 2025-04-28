<?php
$host = 'localhost';
$db   = 'ventech_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$errors = [];
$success = false; // Initialize success flag

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $repeat_password = $_POST["repeat_password"];
    $contact_number = trim($_POST["contact_number"]);
    $location = trim($_POST["location"]);

    // Basic validations
    if (empty($username) || empty($email) || empty($password) || empty($repeat_password) || empty($contact_number)) {
        $errors[] = "All required fields must be filled out.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if ($password !== $repeat_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username or email already in use.";
    }

    // If no errors, insert the user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare("INSERT INTO users (username, email, password, contact_number, location) VALUES (?, ?, ?, ?, ?)");
        if ($insert->execute([$username, $email, $hashed_password, $contact_number, $location])) {
            header("Location: user_login.php?registered=1");
            exit;
        } else {
            $errors[] = "Something went wrong during registration.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Roboto', sans-serif; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-lg w-full">
        <h1 class="text-2xl font-bold text-center mb-2">Create Your Account on</h1>
        <h2 class="text-2xl font-bold text-center text-orange-500 mb-4">Courts of the World</h2>
        <p class="text-center mb-6">Enjoy the benefits of becoming a registered user:<br> Create your profile, add your homecourt, comment on courts and post your photos and videos!</p>

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
                    <label for="username" class="block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input type="text" id="username" name="username" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                    <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long.</p>
                </div>
                <div>
                    <label for="repeat-password" class="block text-sm font-medium text-gray-700">Repeat password <span class="text-red-500">*</span></label>
                    <input type="password" id="repeat-password" name="repeat_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                </div>
                <div>
                    <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
                    <input type="text" name="contact_number" id="contact_number" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                    <input type="text" name="location" id="location" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
            </div>
            <div class="mb-4">
                <input type="checkbox" id="terms" class="mr-2" required>
                <label for="terms" class="text-sm text-gray-700">
                    By clicking "Create Your Account", you accept our <a href="#" class="text-orange-500">Terms of Use</a>,
                    <a href="#" class="text-orange-500">Privacy Policy</a> and <a href="#" class="text-orange-500">Cookie Policy</a>. <span class="text-red-500">*</span>
                </label>
            </div>
            <div class="mb-6">
                <input type="checkbox" id="newsletter" class="mr-2">
                <label for="newsletter" class="text-sm text-gray-700">You agree to receive updates via the Courts of the World newsletter.</label>
            </div>
            <p class="text-sm text-gray-700 mb-4"><span class="text-red-500">*</span> Mandatory fields.</p>
            <button type="submit" class="w-full bg-orange-500 text-white py-2 rounded-md text-lg font-bold">Create Your Account</button>
        </form>
    </div>
</body>
</html>
