<?php
// --- PHP Code ---

// **1. Start Session & Auth Check**
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: client_login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// **2. Include Database Connection**
// Use require_once for critical includes like DB connection
// Ensure the path is correct relative to add_venue.php
require_once('../includes/db_connection.php'); // Make sure $pdo is created in this file

// **3. Initialize Variables**
$errors = [];
$success = "";
// Variables to retain form input values on error
$title_val = '';
$description_val = '';
$price_val = '';
$status_val = 'open'; // Default status

// **4. Handle Form Submission**
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retain input values
    $title_val = trim($_POST['title'] ?? '');
    $description_val = trim($_POST['description'] ?? '');
    $price_val = trim($_POST['price'] ?? '');
    $status_val = $_POST['status'] ?? 'open'; // Retain status

    // Sanitize and validate input data
    $title = htmlspecialchars($title_val, ENT_QUOTES, 'UTF-8');
    $price = filter_var($price_val, FILTER_VALIDATE_FLOAT); // Use float filter
    $description = htmlspecialchars($description_val, ENT_QUOTES, 'UTF-8');
    $status = in_array($status_val, ['open', 'closed']) ? $status_val : 'closed'; // Validate status

    // Basic Presence Checks
    if (empty($title)) $errors[] = "Venue title is required.";
    if (empty($description)) $errors[] = "Venue description is required.";
    if ($price === false) $errors[] = "Price must be a valid number.";
    elseif ($price <= 0) $errors[] = "Price must be greater than zero.";
    // Removed regex as FILTER_VALIDATE_FLOAT handles numeric check better, added positive check

    // --- Image Upload Handling ---
    $image_path = ""; // Will store the relative path for the DB
    $upload_dir = __DIR__ . "/../uploads/"; // Use absolute path for PHP operations (__DIR__ refers to current file's dir)
    $relative_upload_dir = "../uploads/"; // Relative path for DB/HTML src (adjust if needed)

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
        $image_tmp_name = $_FILES["image"]["tmp_name"];
        $image_size = $_FILES["image"]["size"];
        $image_error = $_FILES["image"]["error"]; // Check again just in case

        // Validate file size (e.g., max 5MB)
        $max_file_size = 5 * 1024 * 1024;
        if ($image_size > $max_file_size) {
            $errors[] = "Image file is too large (Max: 5MB).";
        }

        // Validate MIME type and extension
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $image_tmp_name);
        finfo_close($finfo);
        $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));

        if (!in_array($mime_type, $allowed_mimes) || !in_array($file_ext, $allowed_exts)) {
            $errors[] = "Invalid file type. Only JPG, PNG, GIF allowed.";
        }

        if (empty($errors)) { // Proceed only if validation passes so far
            // Create unique filename
            $image_name = "venue_" . uniqid('', true) . "." . $file_ext;
            $full_destination_path = $upload_dir . $image_name;
            $relative_path_for_db = $relative_upload_dir . $image_name; // Store this path

            // Create upload directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) { // Use 0775 for security
                    $errors[] = "Failed to create upload directory. Check server permissions.";
                    error_log("Failed to create directory: " . $upload_dir);
                }
            } elseif (!is_writable($upload_dir)) {
                 $errors[] = "Upload directory is not writable. Check server permissions.";
                 error_log("Upload directory not writable: " . $upload_dir);
            }

            // Move the uploaded file if directory is okay
            if (empty($errors)) {
                if (move_uploaded_file($image_tmp_name, $full_destination_path)) {
                    $image_path = $relative_path_for_db; // Set the path for DB insert
                } else {
                    $errors[] = "Sorry, there was an error uploading your image. Check permissions or logs.";
                    error_log("move_uploaded_file failed for: " . $image_tmp_name . " to " . $full_destination_path);
                }
            }
        }
    } elseif (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
        // Handle other specific upload errors
        $errors[] = "File upload error code: " . $_FILES["image"]["error"];
    } else {
        // Image is required
        $errors[] = "Venue image is required.";
    }

    // --- Database Insertion ---
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO venue (user_id, title, price, image_path, description, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())" // Add created_at
                );
            $stmt->execute([$user_id, $title, $price, $image_path, $description, $status]);

            $new_venue_id = $pdo->lastInsertId();

            // Redirect with success flag and ID
            header("Location: /ventech_locator/client_dashboard.php?new_venue=true&id=" . $new_venue_id);
            exit();

        } catch (PDOException $e) {
            error_log("Database Insert Error: " . $e->getMessage());
            $errors[] = "Database error occurred while adding the venue. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Venue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; /* Light gray bg */ }
        /* Custom Form Styles */
        label { font-weight: 500; color: #374151; /* Gray 700 */ margin-bottom: 0.5rem; display: block; }
        input[type="text"], input[type="number"], textarea, select {
             border-color: #d1d5db; /* Gray 300 */ border-radius: 0.375rem; /* rounded-md */
             padding: 0.6rem 0.8rem; width: 100%; font-size: 0.95rem;
             transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
             box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        input:focus, textarea:focus, select:focus {
            border-color: #f59e0b; /* Amber 500 */
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3); /* Amber focus ring */
            outline: none;
        }
        textarea { min-height: 100px; }
        /* Style file input label as button */
        .file-input-button {
            cursor: pointer; background-color: #4f46e5; /* Indigo 600 */ color: white;
            padding: 0.6rem 1rem; border-radius: 0.375rem; transition: background-color 0.2s;
            display: inline-flex; align-items: center; font-weight: 500;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .file-input-button:hover { background-color: #4338ca; /* Indigo 700 */ }
        .file-input-button i { margin-right: 0.5rem; }
        /* Visually hide the actual file input */
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0; }

        #imagePreview { max-height: 200px; margin-top: 1rem; border-radius: 0.375rem; border: 1px solid #d1d5db; }

    </style>
</head>
<body class="bg-gray-100">

    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-800">Add New Venue</h1>
             <a href="client_dashboard.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                 <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>
    </header>

    <div class="container mx-auto py-8 max-w-3xl px-4"> <?php if ($success && $_SERVER['REQUEST_METHOD'] !== 'POST'): // Only show success if not resubmitting ?>
             <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6 shadow" role="alert">
                <p class="font-bold">Success!</p>
                <p><?= htmlspecialchars($success); ?></p>
             </div>
         <?php endif; ?>

         <?php if (!empty($errors)): ?>
             <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 shadow" role="alert">
                 <p class="font-bold mb-2">Please fix the following errors:</p>
                 <ul class="list-disc list-inside text-sm">
                     <?php foreach ($errors as $error): ?>
                         <li><?= htmlspecialchars($error); ?></li>
                     <?php endforeach; ?>
                 </ul>
             </div>
         <?php endif; ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow-md">
            <form method="POST" action="add_venue.php" enctype="multipart/form-data">
                <div class="mb-5">
                    <label for="title">Venue Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($title_val); ?>" placeholder="e.g., The Grand Ballroom" required>
                </div>

                <div class="mb-5">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the venue, its features, and suitability for events..." required><?= htmlspecialchars($description_val); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                     <div>
                        <label for="price">Price per Hour</label>
                         <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 pointer-events-none">₱</span>
                            <input type="number" id="price" name="price" value="<?= htmlspecialchars($price_val); ?>" placeholder="e.g., 5000.00" min="0.01" step="0.01" class="pl-7" required>
                        </div>
                    </div>
                    <div>
                        <label for="status">Initial Status</label>
                        <select id="status" name="status" required>
                            <option value="open" <?= ($status_val == 'open') ? 'selected' : ''; ?>>Open (Available for Booking)</option>
                            <option value="closed" <?= ($status_val == 'closed') ? 'selected' : ''; ?>>Closed (Not Available)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="image">Venue Image</label>
                     <label class="file-input-button" for="image">
                        <i class="fas fa-upload"></i> Choose Image...
                    </label>
                    <input type="file" id="image" name="image" class="sr-only" accept="image/jpeg,image/png,image/gif" required>
                    <div class="mt-3">
                        <img id="imagePreview" src="#" alt="Image Preview" class="hidden w-full max-w-sm h-auto object-contain rounded border bg-gray-50 p-1"/>
                    </div>
                     <p class="text-xs text-gray-500 mt-1">Required. Max 5MB. JPG, PNG, or GIF.</p>
                </div>

                <div class="mt-8 pt-5 border-t border-gray-200">
                    <button type="submit" class="w-full flex justify-center items-center bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-4 rounded-md shadow focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition duration-150 ease-in-out">
                         <i class="fas fa-plus-circle mr-2"></i> Add Venue
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');

            if (imageInput && imagePreview) {
                imageInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.classList.remove('hidden'); // Show preview
                        }
                        reader.readAsDataURL(file);
                    } else {
                        // No file selected or not an image
                        imagePreview.src = '#';
                        imagePreview.classList.add('hidden'); // Hide preview
                    }
                });
            }
        });
    </script>

</body>
</html>

