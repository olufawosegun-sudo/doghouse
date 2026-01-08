<?php
/**
 * Dog House Market - Sign In Page
 * User authentication system with responsive design
 */

// Start session
session_start();

// Include database connection
$conn = require_once 'dbconnect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch company information from database
$companyInfo = [];
$companyQuery = "SELECT * FROM company_info LIMIT 1";
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
} else {
    // Default company info if not found in database
    $companyInfo = [
        'company_name' => 'Doghouse Market',
        'primary_color' => '#FFA500',
        // ...other default values
    ];
}

// Initialize variables
$email = '';
$password = '';
$errors = [];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty($_POST['email'])) {
        $errors['email'] = "Email is required";
    } else {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
        }
    }
    
    // Validate password
    if (empty($_POST['password'])) {
        $errors['password'] = "Password is required";
    } else {
        $password = $_POST['password'];
    }
    
    // If no validation errors, attempt to sign in
    if (empty($errors)) {
        // Check if the users table exists
        $tableExistsQuery = "SHOW TABLES LIKE 'users'";
        $tableExists = mysqli_query($conn, $tableExistsQuery);
        
        if ($tableExists && mysqli_num_rows($tableExists) > 0) {
            // Prepare statement for security
            $sql = "SELECT * FROM users WHERE email = ?";
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $user = mysqli_fetch_assoc($result);
                    
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Password is correct, set up session
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_name'] = $user['first_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
                        $_SESSION['last_activity'] = time(); // For session timeout
                        
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        // Set remember me cookie if checked
                        if (isset($_POST['remember']) && $_POST['remember'] == '1') {
                            $token = bin2hex(random_bytes(32)); // Secure token
                            $expires = time() + (30 * 24 * 60 * 60); // 30 days
                            setcookie('remember_user', $token, $expires, '/', '', true, true);
                            
                            // Store token in database - in a real implementation,
                            // you would create a remember_tokens table
                        }
                        
                        // Redirect to dashboard instead of index.php or admin
                        if ($_SESSION['is_admin']) {
                            header("Location: admin/index.php");
                        } else {
                            header("Location: user/dashboard.php");
                        }
                        exit;
                    } else {
                        $errors['login'] = "Invalid email or password";
                    }
                } else {
                    $errors['login'] = "Invalid email or password";
                }
            } else {
                $errors['system'] = "Database error: " . mysqli_error($conn);
            }
        } else {
            // Create users table automatically if it doesn't exist
            $createTableSql = "CREATE TABLE IF NOT EXISTS users (
                user_id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                is_admin TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            if (mysqli_query($conn, $createTableSql)) {
                $errors['login'] = "User account system has been initialized. Please sign up for an account.";
            } else {
                $errors['system'] = "Failed to initialize user system: " . mysqli_error($conn);
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
    <title>Sign In - <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($companyInfo['primary_color'] ?? '#FFA500'); ?>;
            --secondary-color: #2c3e50;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-color: #212529;
            --text-light: #6c757d;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 5px 30px rgba(0,0,0,0.08);
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.7;
            color: var(--text-color);
            background-color: var(--light-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            line-height: 1.3;
        }

        /* Header & Navigation */
        .navbar {
            padding: 15px 0;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary-color) !important;
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
        
        .nav-link {
            font-weight: 500;
            margin: 0 10px;
            padding: 8px 0 !important;
            position: relative;
            color: var(--dark-color) !important;
            transition: all 0.3s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 2px;
            background-color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .nav-link:hover::after, .nav-link.active::after {
            width: 100%;
        }
        
        .auth-buttons .btn {
            margin-left: 10px;
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* Auth Form Styles */
        .auth-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }
        
        .auth-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            margin: 0 15px;
        }
        
        .auth-card .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-card .auth-header h2 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        .auth-card .auth-header p {
            color: var(--text-light);
        }
        
        .auth-form .form-control {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .auth-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.15);
        }
        
        .auth-form label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        
        .auth-form .btn-primary {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            border-radius: 30px;
            margin-top: 10px;
            transition: all 0.3s ease;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .auth-form .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(0, 0, 0, 0.1);
            background-color: var(--primary-color);
            opacity: 0.9;
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        .auth-social-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f8f9fa;
            color: var(--dark-color);
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .auth-social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }
        
        .auth-social-btn.facebook:hover {
            background-color: #3b5998;
            color: white;
        }
        
        .auth-social-btn.google:hover {
            background-color: #dd4b39;
            color: white;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .auth-links a {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .alert-dismissible {
            padding-right: 1rem;
        }
        
        .invalid-feedback {
            font-size: 0.85rem;
        }

        /* Footer */
        .footer {
            background-color: var(--dark-color);
            color: white;
            padding: 30px 0 20px;
            margin-top: auto;
        }
        
        .footer p {
            margin-bottom: 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* Password visibility toggle */
        .password-field {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
        }
        
        .password-toggle:focus {
            outline: none;
            color: var(--primary-color);
        }
        
        /* Convert primary-color HEX to RGB for opacity support */
        :root {
            --primary-color-rgb: <?php 
                $hex = ltrim($companyInfo['primary_color'] ?? '#FFA500', '#');
                if (strlen($hex) == 3) {
                    $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
                    $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
                    $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
                } else {
                    $r = hexdec(substr($hex, 0, 2));
                    $g = hexdec(substr($hex, 2, 2));
                    $b = hexdec(substr($hex, 4, 2));
                }
                echo "$r, $g, $b";
            ?>;
        }

        /* Responsive Adjustments */
        @media (max-width: 767.98px) {
            .auth-card {
                padding: 30px 20px;
                margin: 0 15px;
            }
            
            .auth-card .auth-header h2 {
                font-size: 1.8rem;
            }
            
            .navbar-brand {
                font-size: 1.5rem;
            }
            
            .auth-buttons {
                margin-top: 10px;
            }
            
            .auth-buttons .btn {
                padding: 6px 12px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 575.98px) {
            .auth-card {
                padding: 25px 15px;
            }
            
            .auth-card .auth-header h2 {
                font-size: 1.6rem;
            }
            
            .auth-form .btn-primary {
                padding: 10px;
            }
            
            .auth-social-btn {
                width: 35px;
                height: 35px;
            }
        }
        
        /* Form shake animation for errors */
        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
            20%, 40%, 60%, 80% {transform: translateX(5px);}
        }
        
        .shake {
            animation: shake 0.6s;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php if (!empty($companyInfo['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($companyInfo['logo']); ?>" alt="Logo" class="navbar-logo">
                <?php endif; ?>
                <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="dogs.php">Dogs</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
                </ul>
                <div class="auth-buttons d-flex flex-wrap">
                    <a href="signin.php" class="btn btn-primary">Sign In</a>
                    <a href="signup.php" class="btn btn-outline-primary">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Auth Section -->
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <?php if (!empty($companyInfo['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($companyInfo['logo']); ?>" alt="Logo" style="max-height: 60px; width: auto; margin-bottom: 15px;">
                <?php endif; ?>
                <h2>Sign In</h2>
                <p>Welcome back! Sign in to access your account</p>
            </div>
            
            <?php if (!empty($errors['system'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errors['system']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors['login'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errors['login']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form class="auth-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate id="signin-form">
                <div class="mb-3">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                    <?php if (!empty($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3 password-field">
                    <label for="password">Password</label>
                    <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                    <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                        <i class="far fa-eye"></i>
                    </button>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="forgot-password.php" class="text-primary">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Sign In <i class="fas fa-sign-in-alt ms-2"></i>
                </button>
            </form>
            
            <div class="auth-footer">
                <p class="mb-3">Or sign in with</p>
                <div class="social-login">
                    <a href="#" class="auth-social-btn facebook" aria-label="Sign in with Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="auth-social-btn google" aria-label="Sign in with Google">
                        <i class="fab fa-google"></i>
                    </a>
                </div>
            </div>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>. All rights reserved.</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script for Enhanced UX -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle');
            
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Change icon
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
            
            // Animation for form with errors
            const form = document.getElementById('signin-form');
            if (form.querySelector('.is-invalid')) {
                form.classList.add('shake');
                setTimeout(() => {
                    form.classList.remove('shake');
                }, 600);
            }
            
            // Hide alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                });
            }, 5000);
            
            // Mobile optimization
            if (window.innerWidth < 768) {
                const navLinks = document.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', () => {
                        const navbarCollapse = document.querySelector('.navbar-collapse');
                        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(navbarCollapse);
                        if (bsCollapse) {
                            bsCollapse.hide();
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
