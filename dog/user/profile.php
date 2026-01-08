<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../signin.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'doghousemarket';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data
$userQuery = "SELECT * FROM users WHERE user_id = $user_id";
$userResult = $conn->query($userQuery);

if (!$userResult || $userResult->num_rows === 0) {
    header("Location: ../signin.php");
    exit;
}

$user = $userResult->fetch_assoc();

// Initialize message
$message = '';
$messageType = '';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email is already taken by another user
    $emailCheckQuery = "SELECT user_id FROM users WHERE email = '$email' AND user_id != $user_id";
    $emailCheckResult = $conn->query($emailCheckQuery);
    
    if ($emailCheckResult && $emailCheckResult->num_rows > 0) {
        $message = 'Email address is already in use by another account.';
        $messageType = 'danger';
    } else {
        $updateQuery = "UPDATE users SET first_name = '$first_name', last_name = '$last_name', email = '$email' WHERE user_id = $user_id";
        
        if ($conn->query($updateQuery)) {
            $message = 'Profile updated successfully!';
            $messageType = 'success';
            
            // Refresh user data
            $_SESSION['user_name'] = $first_name;
            $userResult = $conn->query($userQuery);
            $user = $userResult->fetch_assoc();
        } else {
            $message = 'Error updating profile: ' . $conn->error;
            $messageType = 'danger';
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $messageType = 'danger';
    } elseif (strlen($new_password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'danger';
    } elseif (password_verify($current_password, $user['password'])) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $updatePasswordQuery = "UPDATE users SET password = '$hashed_password' WHERE user_id = $user_id";
        
        if ($conn->query($updatePasswordQuery)) {
            $message = 'Password changed successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error changing password: ' . $conn->error;
            $messageType = 'danger';
        }
    } else {
        $message = 'Current password is incorrect.';
        $messageType = 'danger';
    }
}

// Get user statistics - Initialize with default values first
$stats = [
    'my_dogs' => 0,
    'total_orders' => 0,
    'completed_orders' => 0
];

// Check if user_dogs table exists
$userDogsTableCheck = $conn->query("SHOW TABLES LIKE 'user_dogs'");
$userDogsTableExists = ($userDogsTableCheck && $userDogsTableCheck->num_rows > 0);

// Check if orders table exists
$ordersTableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
$ordersTableExists = ($ordersTableCheck && $ordersTableCheck->num_rows > 0);

// Build statistics query based on existing tables
if ($userDogsTableExists && $ordersTableExists) {
    $statsQuery = "SELECT 
                    (SELECT COUNT(*) FROM user_dogs WHERE user_id = $user_id) as my_dogs,
                    (SELECT COUNT(*) FROM orders WHERE user_id = $user_id) as total_orders,
                    (SELECT COUNT(*) FROM orders WHERE user_id = $user_id AND status = 'Completed') as completed_orders
                   FROM dual";
} elseif ($userDogsTableExists) {
    $statsQuery = "SELECT 
                    (SELECT COUNT(*) FROM user_dogs WHERE user_id = $user_id) as my_dogs,
                    0 as total_orders,
                    0 as completed_orders
                   FROM dual";
} elseif ($ordersTableExists) {
    $statsQuery = "SELECT 
                    0 as my_dogs,
                    (SELECT COUNT(*) FROM orders WHERE user_id = $user_id) as total_orders,
                    (SELECT COUNT(*) FROM orders WHERE user_id = $user_id AND status = 'Completed') as completed_orders
                   FROM dual";
} else {
    $statsQuery = "SELECT 0 as my_dogs, 0 as total_orders, 0 as completed_orders FROM dual";
}

$statsResult = $conn->query($statsQuery);
if ($statsResult && $statsResult->num_rows > 0) {
    $fetchedStats = $statsResult->fetch_assoc();
    // Merge fetched stats with defaults to ensure all keys exist
    $stats = array_merge($stats, $fetchedStats);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Doghouse Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --theme-color: #ffa500;
            --theme-color-light: #ffb733;
            --theme-color-dark: #e69400;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 30px;
            min-height: calc(100vh - 60px);
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--theme-color), var(--theme-color-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            color: white;
            margin-right: 25px;
            box-shadow: 0 5px 15px rgba(255, 165, 0, 0.3);
        }
        
        .profile-info h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }
        
        .profile-info p {
            color: #666;
            margin-bottom: 5px;
        }
        
        .member-badge {
            display: inline-block;
            background-color: var(--theme-color);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--theme-color), var(--theme-color-light));
            padding: 25px;
            border-radius: 10px;
            color: white;
            text-align: center;
            box-shadow: 0 5px 15px rgba(255, 165, 0, 0.3);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #28a745, #5cb85c);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #17a2b8, #5bc0de);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--theme-color);
        }
        
        .form-group label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--theme-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 165, 0, 0.25);
        }
        
        .btn-update {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary {
            background-color: var(--theme-color);
            border-color: var(--theme-color);
        }
        
        .btn-primary:hover {
            background-color: var(--theme-color-dark);
            border-color: var(--theme-color-dark);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 38px;
            border: none;
            background: none;
            color: #666;
            cursor: pointer;
        }
        
        .password-toggle:hover {
            color: var(--theme-color);
        }
        
        @media (max-width: 767.98px) {
            .main-content {
                margin-left: 0;
                margin-top: 60px;
                padding: 15px;
                padding-bottom: 80px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Manage your account settings and preferences</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p><i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-calendar-alt mr-2"></i>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    <span class="member-badge"><i class="fas fa-star mr-1"></i>Active Member</span>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo isset($stats['my_dogs']) ? $stats['my_dogs'] : 0; ?></div>
                    <div class="stat-label"><i class="fas fa-dog mr-1"></i>My Dogs</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo isset($stats['total_orders']) ? $stats['total_orders'] : 0; ?></div>
                    <div class="stat-label"><i class="fas fa-shopping-cart mr-1"></i>Total Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo isset($stats['completed_orders']) ? $stats['completed_orders'] : 0; ?></div>
                    <div class="stat-label"><i class="fas fa-check-circle mr-1"></i>Completed Orders</div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><i class="fas fa-user-edit"></i>Personal Information</h3>
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary btn-update">
                        <i class="fas fa-save mr-2"></i>Update Profile
                    </button>
                </form>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><i class="fas fa-lock"></i>Change Password</h3>
                <form method="post">
                    <div class="form-group position-relative">
                        <label for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="new_password">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning btn-update">
                        <i class="fas fa-key mr-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Password match validation
        document.querySelector('form[method="post"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('New passwords do not match!');
            }
        });
    </script>
</body>
</html>
