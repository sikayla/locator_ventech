<?php
session_start();

// Redirect logged-in users to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: /ventech_locator/users/user_login.php"); // Redirect to dashboard
    exit;
}

$host = 'localhost';
$db = 'ventech_db'; // Removed extra whitespace
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login_as_guest'])) {
        // Logic for guest login
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'guest' LIMIT 1");
        $stmt->execute();
        $guest_user = $stmt->fetch();

        if ($guest_user) {
            $_SESSION['user_id'] = $guest_user['id'];
            $_SESSION['username'] = 'Guest'; // Or $guest_user['username'] if you have a specific guest username
            $_SESSION['role'] = 'guest';
            header("Location: /ventech_locator/users/user_dashboard.php"); // Redirect to dashboard
            exit;
        } else {
            // Create a new guest user if none exists
            $stmt_guest = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'guest')");
            $guest_username = 'guest_' . time(); // Unique guest username
            $stmt_guest->execute([
                ':username' => $guest_username,
                ':email' => 'guest_' . time() . '@example.com', // Unique guest email
                ':password' => password_hash('guest_password', PASSWORD_DEFAULT), // Placeholder password
            ]);
            $guest_user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $guest_user_id;
            $_SESSION['username'] = $guest_username;
            $_SESSION['role'] = 'guest';
            header("Location: /ventech_locator/users/user_dashboard.php"); // Redirect to dashboard
            exit;
        }
    } else {
        // Regular login logic
        $login = trim($_POST['email_or_username']);
        $password = $_POST['password'];

        if (empty($login) || empty($password)) {
            $error = "Please enter both username/email and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: /ventech_locator/users/user_dashboard.php"); // Redirect to dashboard
                exit;
            } else {
                $error = "Invalid credentials. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventech Locator - User Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="text-center">
        <h1 class="text-3xl font-bold mb-2">Log in to Ventech Locator</h1>
        <p class="text-gray-600 mb-8">
            Welcome back! Please enter your credentials to log in.
        </p>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 w-full max-w-md mx-auto"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-center items-center space-y-6 md:space-y-0 md:space-x-12">
            <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
                <h2 class="text-lg font-semibold mb-4">Log in with</h2>
                <button class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg flex items-center justify-center mb-4">
                    <i class="fab fa-facebook-f mr-2"></i> Facebook
                </button>
                <button class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg flex items-center justify-center">
                    <i class="fab fa-google mr-2"></i> Google
                </button>
            </div>
            <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
                <h2 class="text-lg font-semibold mb-4">Or, use your Ventech Locator account</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="email_or_username" class="block text-left text-gray-700">Email or Username <span class="text-red-500">*</span></label>
                        <input type="text" id="email_or_username" name="email_or_username" class="w-full border border-gray-300 p-2 rounded-lg" required>
                    </div>
                    <div class="mb-6">
                        <label for="password" class="block text-left text-gray-700">Password <span class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password" class="w-full border border-gray-300 p-2 rounded-lg" required>
                    </div>
                    <button type="submit" class="w-full bg-red-500 text-white py-2 px-4 rounded-lg">Log in</button>
                </form>
                <form method="POST" action="" class="mt-4">
                    <button type="submit" name="login_as_guest" class="w-full bg-yellow-500 text-white py-2 px-4 rounded-lg">Log in as Guest</button>
                </form>
            </div>
        </div>

        <div class="mt-6 flex justify-between w-full max-w-md mx-auto text-sm text-gray-600">
            <a href="user_signup.php" class="text-red-500">Register here</a>
            <a href="#" class="text-red-500">Forgot password?</a>
        </div>
    </div>
</body>
</html>