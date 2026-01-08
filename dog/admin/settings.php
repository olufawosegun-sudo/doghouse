<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['admin_username'];

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'doghousemarket';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch company information from database
$companyInfo = [];
$companyQuery = "SELECT * FROM company_info LIMIT 1";
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
}

// Initialize message variables
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_company'])) {
        // Company settings update
        $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
        $color = mysqli_real_escape_string($conn, $_POST['color']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $about = mysqli_real_escape_string($conn, $_POST['about']);
        
        // Handle logo upload
        $logo_path = $companyInfo['logo'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo_path = 'images/' . $new_filename;
            } else {
                $error_message = "Failed to upload logo.";
            }
        }
        
        // Check if company_info table exists, create if not
        $tableExistsQuery = "SHOW TABLES LIKE 'company_info'";
        $tableExists = mysqli_query($conn, $tableExistsQuery);
        
        if (!$tableExists || mysqli_num_rows($tableExists) == 0) {
            $createTableSQL = "CREATE TABLE `company_info` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `company_name` varchar(255) NOT NULL,
                `logo` varchar(255) DEFAULT NULL,
                `color` varchar(20) DEFAULT '#ffa500',
                `email` varchar(100) DEFAULT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `address` text DEFAULT NULL,
                `about` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if (!mysqli_query($conn, $createTableSQL)) {
                $error_message = "Error creating company_info table: " . mysqli_error($conn);
            }
        }
        
        // Update or insert company information
        if (empty($error_message)) {
            if (!empty($companyInfo)) {
                // Update existing record
                $updateSQL = "UPDATE company_info SET 
                    company_name = '$company_name', 
                    color = '$color', 
                    email = '$email', 
                    phone = '$phone', 
                    address = '$address', 
                    about = '$about'";
                
                // Only update logo if a new one was uploaded
                if (!empty($logo_path) && $logo_path !== $companyInfo['logo']) {
                    $updateSQL .= ", logo = '$logo_path'";
                }
                
                $updateSQL .= " WHERE id = " . $companyInfo['id'];
                
                if (mysqli_query($conn, $updateSQL)) {
                    $success_message = "Company information updated successfully!";
                    // Refresh company info after update
                    $result = mysqli_query($conn, $companyQuery);
                    if ($result && mysqli_num_rows($result) > 0) {
                        $companyInfo = mysqli_fetch_assoc($result);
                    }
                } else {
                    $error_message = "Error updating company information: " . mysqli_error($conn);
                }
            } else {
                // Insert new record
                $insertSQL = "INSERT INTO company_info (company_name, logo, color, email, phone, address, about) 
                              VALUES ('$company_name', '$logo_path', '$color', '$email', '$phone', '$address', '$about')";
                
                if (mysqli_query($conn, $insertSQL)) {
                    $success_message = "Company information saved successfully!";
                    // Refresh company info after insert
                    $result = mysqli_query($conn, $companyQuery);
                    if ($result && mysqli_num_rows($result) > 0) {
                        $companyInfo = mysqli_fetch_assoc($result);
                    }
                } else {
                    $error_message = "Error saving company information: " . mysqli_error($conn);
                }
            }
        }
    } elseif (isset($_POST['update_password'])) {
        // Password update
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirmation do not match.";
        } else {
            // Verify current password
            $admin_id = $_SESSION['admin_id'];
            $checkPasswordSQL = "SELECT password FROM admins WHERE id = $admin_id";
            $passwordResult = mysqli_query($conn, $checkPasswordSQL);
            
            if ($passwordResult && mysqli_num_rows($passwordResult) > 0) {
                $adminData = mysqli_fetch_assoc($passwordResult);
                if (password_verify($current_password, $adminData['password'])) {
                    // Current password is correct, update to new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $updatePasswordSQL = "UPDATE admins SET password = '$hashed_password' WHERE id = $admin_id";
                    
                    if (mysqli_query($conn, $updatePasswordSQL)) {
                        $success_message = "Password updated successfully!";
                    } else {
                        $error_message = "Error updating password: " . mysqli_error($conn);
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } else {
                $error_message = "Could not verify current password.";
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
    <title>Settings | Doghouse Market Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Doghouse Market Theme Colors with Orange (#ffa500) as primary */
            --primary: <?php echo $companyInfo['color'] ?? '#ffa500'; ?>;
            --primary-light: <?php 
                $hex = ltrim($companyInfo['color'] ?? '#ffa500', '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $r = max(0, min(255, $r + ($r * 0.2)));
                $g = max(0, min(255, $g + ($g * 0.2)));
                $b = max(0, min(255, $b + ($b * 0.2)));
                echo sprintf("#%02x%02x%02x", $r, $g, $b);
            ?>;
            --primary-dark: <?php 
                $hex = ltrim($companyInfo['color'] ?? '#ffa500', '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $r = max(0, min(255, $r - ($r * 0.2)));
                $g = max(0, min(255, $g - ($g * 0.2)));
                $b = max(0, min(255, $b - ($b * 0.2)));
                echo sprintf("#%02x%02x%02x", $r, $g, $b);
            ?>;
            --secondary: #ff7e33;
            --tertiary: #ffcf40;
            --success: #66bb6a;
            --info: #42a5f5;
            --warning: #ffc107;
            --danger: #f44336;
            --light: #f8f9fa;
            --dark: #343a40;
            --text-primary: #495057;
            --text-secondary: #868e96;
            --text-muted: #adb5bd;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
            
            /* Layout dimensions */
            --sidebar-width: 260px;
            --header-height: 70px;
            --content-padding: 30px;
            --border-radius: 0.75rem;
            --card-radius: 1rem;
            --box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main Content Area */
        .main-area {
            margin-left: 0; /* Sidebar is included separately */
            flex: 1;
            transition: all 0.3s;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Content Container */
        .content-container {
            padding: calc(var(--header-height) + 30px) var(--content-padding) 30px;
            flex: 1;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-weight: 800;
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 15px;
            margin: 0;
        }

        /* Settings Cards */
        .settings-card {
            background-color: white;
            border-radius: var(--card-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            border: none;
        }
        
        .settings-card .card-header {
            padding: 20px 25px;
            background-color: white;
            border-bottom: 1px solid var(--border-color);
        }
        
        .settings-card .card-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
            margin: 0;
        }
        
        .settings-card .card-body {
            padding: 25px;
        }

        /* Form Controls */
        .form-group label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .form-control, .custom-select {
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            height: auto;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .custom-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(255, 165, 0, 0.25);
        }

        /* Custom File Input */
        .custom-file-label {
            padding: 10px 15px;
            height: auto;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
        }
        
        .custom-file-label::after {
            height: auto;
            padding: 10px 15px;
            background-color: var(--primary);
            color: white;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        /* Logo Preview */
        .logo-preview {
            max-width: 200px;
            max-height: 100px;
            margin-top: 10px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        /* Color Picker */
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            vertical-align: middle;
            border: 2px solid var(--border-color);
        }

        @media (max-width: 767.98px) {
            .content-container {
                padding: 80px 15px 15px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        /* Nav Tabs */
        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-item {
            margin-bottom: -1px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            padding: 12px 20px;
            color: var(--text-secondary);
            font-weight: 600;
            border-radius: 0;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background-color: transparent;
        }
        
        .nav-tabs .nav-link:hover:not(.active) {
            color: var(--primary-light);
            border-bottom: 3px solid var(--primary-light);
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="app-wrapper">
        <!-- Main Content -->
        <main class="main-area" style="margin-left: 250px;">
            <!-- Content Container -->
            <div class="content-container">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Settings</h1>
                    <p class="page-subtitle">Configure your Doghouse Market settings</p>
                </div>
                
                <!-- Alert Messages -->
                <?php if(!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Settings Navigation Tabs -->
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="company-tab" data-toggle="tab" href="#company" role="tab" aria-controls="company" aria-selected="true">
                            <i class="fas fa-building mr-2"></i>Company Settings
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="account-tab" data-toggle="tab" href="#account" role="tab" aria-controls="account" aria-selected="false">
                            <i class="fas fa-user-shield mr-2"></i>Account Security
                        </a>
                    </li>
                </ul>
                
                <!-- Settings Tab Content -->
                <div class="tab-content" id="settingsTabContent">
                    <!-- Company Settings Tab -->
                    <div class="tab-pane fade show active" id="company" role="tabpanel" aria-labelledby="company-tab">
                        <div class="settings-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2 class="card-title">Company Information</h2>
                            </div>
                            <div class="card-body">
                                <form action="settings.php" method="post" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="company_name">Company Name</label>
                                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="color">Primary Color</label>
                                                <div class="d-flex align-items-center">
                                                    <span class="color-preview" id="colorPreview" style="background-color: <?php echo $companyInfo['color'] ?? '#ffa500'; ?>"></span>
                                                    <input type="color" class="form-control" id="color" name="color" value="<?php echo $companyInfo['color'] ?? '#ffa500'; ?>" style="width: 60px;">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="email">Contact Email</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($companyInfo['email'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="phone">Contact Phone</label>
                                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($companyInfo['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                <label for="address">Address</label>
                                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($companyInfo['address'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                <label for="about">About Your Company</label>
                                                <textarea class="form-control" id="about" name="about" rows="4"><?php echo htmlspecialchars($companyInfo['about'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                <label for="logo">Company Logo</label>
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input" id="logo" name="logo" accept="image/*">
                                                    <label class="custom-file-label" for="logo">Choose file...</label>
                                                </div>
                                                <?php if (!empty($companyInfo['logo'])): ?>
                                                <div class="mt-2">
                                                    <p class="text-muted">Current Logo:</p>
                                                    <img src="../<?php echo htmlspecialchars($companyInfo['logo']); ?>" class="logo-preview" alt="Company Logo">
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right mt-4">
                                        <button type="reset" class="btn btn-secondary mr-2">Reset</button>
                                        <button type="submit" name="update_company" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Settings Tab -->
                    <div class="tab-pane fade" id="account" role="tabpanel" aria-labelledby="account-tab">
                        <div class="settings-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2 class="card-title">Change Password</h2>
                            </div>
                            <div class="card-body">
                                <form action="settings.php" method="post">
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                <label for="current_password">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="new_password">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="confirm_password">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right mt-4">
                                        <button type="reset" class="btn btn-secondary mr-2">Cancel</button>
                                        <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Update color preview when color picker changes
            $('#color').on('input', function() {
                $('#colorPreview').css('background-color', $(this).val());
            });
            
            // Show selected filename in custom file input
            $('.custom-file-input').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert-dismissible').alert('close');
            }, 5000);
            
            // Password validation
            $('#confirm_password').on('keyup', function() {
                if ($('#new_password').val() == $('#confirm_password').val()) {
                    $('#confirm_password').removeClass('is-invalid').addClass('is-valid');
                } else {
                    $('#confirm_password').removeClass('is-valid').addClass('is-invalid');
                }
            });
            
            // Form validation before submit
            $('form').on('submit', function(e) {
                var form = $(this);
                if (form.find('button[name="update_password"]').length) {
                    // Password form validation
                    if ($('#new_password').val() !== $('#confirm_password').val()) {
                        e.preventDefault();
                        alert('The new password and confirmation do not match.');
                    }
                }
            });
        });
    </script>
</body>
</html>
