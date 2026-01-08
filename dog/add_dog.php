<?php
/**
 * Dog House Market - Add Dog Form
 * 
 * This file provides an admin interface to add new dogs to the database
 */

// Include database connection
$conn = require_once 'dbconnect.php';

// Initialize variables
$success_message = '';
$error_message = '';
$name = '';
$breed = '';
$age = '';
$trait = '';
$price = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $trait = trim($_POST['trait'] ?? '');
    $price = trim($_POST['price'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Dog name is required";
    }
    
    if (empty($breed)) {
        $errors[] = "Breed is required";
    }
    
    if (empty($age)) {
        $errors[] = "Age is required";
    }
    
    if (empty($trait)) {
        $errors[] = "Traits are required";
    }
    
    if (empty($price)) {
        $errors[] = "Price is required";
    } elseif (!is_numeric($price)) {
        $errors[] = "Price must be a number";
    }
    
    // Handle file upload
    $image_url = '';
    if (isset($_FILES['dog_image']) && $_FILES['dog_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'images/dogs/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['dog_image']['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('dog_') . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['dog_image']['tmp_name'], $upload_path)) {
            $image_url = $upload_path;
        } else {
            $errors[] = "Failed to upload image";
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        // Check if the dogs table exists, create if not
        $tableExistsQuery = "SHOW TABLES LIKE 'dogs'";
        $tableExists = mysqli_query($conn, $tableExistsQuery);
        
        if (!$tableExists || mysqli_num_rows($tableExists) == 0) {
            // Create the dogs table
            $createTableSQL = "CREATE TABLE `dogs` (
                `dog_id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `breed` varchar(100) NOT NULL,
                `age` varchar(50) NOT NULL,
                `trait` text DEFAULT NULL,
                `image_url` text DEFAULT NULL,
                `price` decimal(10,2) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`dog_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            
            if (!mysqli_query($conn, $createTableSQL)) {
                $error_message = "Error creating table: " . mysqli_error($conn);
            }
        }
        
        // Prepare SQL statement
        $stmt = mysqli_prepare($conn, "INSERT INTO dogs (name, breed, age, trait, image_url, price) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            // Bind parameters
            mysqli_stmt_bind_param($stmt, "sssssd", $name, $breed, $age, $trait, $image_url, $price);
            
            // Execute statement
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Dog added successfully!";
                // Clear form fields after successful submission
                $name = '';
                $breed = '';
                $age = '';
                $trait = '';
                $price = '';
            } else {
                $error_message = "Error: " . mysqli_stmt_error($stmt);
            }
            
            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Please fix the following errors:<br>" . implode("<br>", $errors);
    }
}

// Fetch company information from database for the page title
$companyInfo = [];
$companyQuery = "SELECT * FROM company_info LIMIT 1";
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Dog - <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($companyInfo['primary_color'] ?? '#FFA500'); ?>;
            --secondary-color: #2c3e50;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: var(--light-color);
            padding-top: 20px;
        }
        
        .card {
            border: none;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: darken(var(--primary-color), 10%);
            border-color: darken(var(--primary-color), 10%);
        }
        
        .alert {
            border-radius: 0;
            margin-bottom: 20px;
        }

        /* Logo styles */
        .navbar-brand {
            display: flex;
            align-items: center;
        }

        .navbar-logo {
            height: 40px;
            width: auto;
            margin-right: 10px;
            object-fit: contain;
        }

        @media (max-width: 767.98px) {
            .navbar-logo {
                height: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <?php if (!empty($companyInfo['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($companyInfo['logo']); ?>" alt="Logo" class="navbar-logo me-2">
                            <?php endif; ?>
                            <h3 class="mb-0">Add New Dog</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add Dog Form -->
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="name" class="form-label">Dog Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="breed" class="form-label">Breed <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="breed" name="breed" value="<?php echo htmlspecialchars($breed); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($age); ?>" placeholder="e.g., 2 years, 6 months" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="trait" class="form-label">Traits <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="trait" name="trait" rows="3" required><?php echo htmlspecialchars($trait); ?></textarea>
                                <div class="form-text">Enter the dog's personality traits, behavior, etc.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="dog_image" class="form-label">Dog Image</label>
                                <input type="file" class="form-control" id="dog_image" name="dog_image" accept="image/*">
                                <div class="form-text">Upload an image of the dog (JPEG, PNG, GIF).</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="price" class="form-label">Price ($) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($price); ?>" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Add Dog</button>
                                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript for form validation -->
    <script>
        // Wait for DOM to load
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const name = document.getElementById('name');
                const breed = document.getElementById('breed');
                const age = document.getElementById('age');
                const trait = document.getElementById('trait');
                const price = document.getElementById('price');
                
                // Reset validation
                [name, breed, age, trait, price].forEach(field => {
                    field.classList.remove('is-invalid');
                });
                
                // Validate required fields
                if (name.value.trim() === '') {
                    name.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (breed.value.trim() === '') {
                    breed.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (age.value.trim() === '') {
                    age.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (trait.value.trim() === '') {
                    trait.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (price.value.trim() === '' || isNaN(price.value) || parseFloat(price.value) < 0) {
                    price.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
