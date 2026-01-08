<?php
/**
 * Dog House Market - Sign Up Page
 * User registration system with responsive design
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
$first_name = '';
$last_name = '';
$email = '';
$password = '';
$confirm_password = '';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate first name
    if (empty($_POST['first_name'])) {
        $errors['first_name'] = "First name is required";
    } else {
        $first_name = trim($_POST['first_name']);
        if (!preg_match("/^[a-zA-Z-' ]*$/", $first_name)) {
            $errors['first_name'] = "Only letters, hyphens, apostrophes, and spaces are allowed";
        }
    }
    
    // Validate last name
    if (empty($_POST['last_name'])) {
        $errors['last_name'] = "Last name is required";
    } else {
        $last_name = trim($_POST['last_name']);
        if (!preg_match("/^[a-zA-Z-' ]*$/", $last_name)) {
            $errors['last_name'] = "Only letters, hyphens, apostrophes, and spaces are allowed";
        }
    }
    
    // Validate email
    if (empty($_POST['email'])) {
        $errors['email'] = "Email is required";
    } else {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
        } else {
            // First, ensure the users table exists
            $createTableSql = "CREATE TABLE IF NOT EXISTS users (
                user_id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                is_admin TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            if (!mysqli_query($conn, $createTableSql)) {
                $errors['system'] = "Failed to initialize user system: " . mysqli_error($conn);
            } else {
                // Check if email already exists
                $sql = "SELECT * FROM users WHERE email = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if ($result && mysqli_num_rows($result) > 0) {
                        $errors['email'] = "This email address is already registered";
                    }
                }
            }
        }
    }
    
    // Validate password
    if (empty($_POST['password'])) {
        $errors['password'] = "Password is required";
    } else {
        $password = $_POST['password'];
        if (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters";
        } elseif (!preg_match("/[A-Z]/", $password)) {
            $errors['password'] = "Password must include at least one uppercase letter";
        } elseif (!preg_match("/[a-z]/", $password)) {
            $errors['password'] = "Password must include at least one lowercase letter";
        } elseif (!preg_match("/[0-9]/", $password)) {
            $errors['password'] = "Password must include at least one number";
        }
    }
    
    // Validate confirm password
    if (empty($_POST['confirm_password'])) {
        $errors['confirm_password'] = "Please confirm your password";
    } else {
        $confirm_password = $_POST['confirm_password'];
        if ($password != $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match";
        }
    }
    
    // Validate terms agreement
    if (!isset($_POST['terms']) || $_POST['terms'] != '1') {
        $errors['terms'] = "You must agree to the Terms of Service and Privacy Policy";
    }
    
    // If no validation errors, create the user account
    if (empty($errors)) {
        // Hash the password with strong algorithm
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert the user into the database
        $sql = "INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $first_name, $last_name, $email, $hashed_password);
            
            if (mysqli_stmt_execute($stmt)) {
                // Registration successful
                $success = true;
                $user_id = mysqli_insert_id($conn);
                
                // Check if this is the first user and make them an admin
                $countQuery = "SELECT COUNT(*) as total FROM users";
                $countResult = mysqli_query($conn, $countQuery);
                if ($countResult) {
                    $row = mysqli_fetch_assoc($countResult);
                    if ($row['total'] == 1) {
                        // This is the first user, make them an admin
                        $adminSql = "UPDATE users SET is_admin = 1 WHERE user_id = ?";
                        $adminStmt = mysqli_prepare($conn, $adminSql);
                        if ($adminStmt) {
                            mysqli_stmt_bind_param($adminStmt, "i", $user_id);
                            mysqli_stmt_execute($adminStmt);
                        }
                    }
                }
                
                // Clear form fields
                $first_name = $last_name = $email = $password = $confirm_password = '';
            } else {
                // Check for duplicate email error (MySQL error 1062)
                if (mysqli_errno($conn) == 1062) {
                    $errors['email'] = "This email address is already registered";
                } else {
                    $errors['system'] = "Error creating account: " . mysqli_stmt_error($stmt);
                }
            }
        } else {
            $errors['system'] = "Database error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></title>
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
            max-width: 550px;
            padding: 40px;
            margin: 20px 15px;
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
        
        /* Password Strength Meter */
        .password-strength {
            height: 5px;
            margin-top: 10px;
            margin-bottom: 20px;
            background-color: #e9ecef;
            border-radius: 3px;
            position: relative;
        }
        
        .password-strength-meter {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .strength-text {
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .weak {
            width: 25%;
            background-color: #dc3545;
        }
        
        .medium {
            width: 50%;
            background-color: #ffc107;
        }
        
        .strong {
            width: 75%;
            background-color: #28a745;
        }
        
        .very-strong {
            width: 100%;
            background-color: #20c997;
        }
        
        /* Terms and conditions checkbox */
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
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
                margin: 15px;
            }
            
            .auth-card .auth-header h2 {
                font-size: 1.8rem;
            }
            
            .row {
                margin-right: 0;
                margin-left: 0;
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
            
            .form-check-label {
                font-size: 0.9rem;
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
                    <a href="signin.php" class="btn btn-outline-primary">Sign In</a>
                    <a href="signup.php" class="btn btn-primary">Sign Up</a>
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
                <h2>Create Account</h2>
                <p>Join us today and find your perfect pet companion</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Account created successfully! You can now <a href="signin.php" class="alert-link">sign in</a>.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors['system'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errors['system']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form class="auth-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate id="signup-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name">First Name</label>
                            <input type="text" class="form-control <?php echo !empty($errors['first_name']) ? 'is-invalid' : ''; ?>" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required autofocus>
                            <?php if (!empty($errors['first_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name">Last Name</label>
                            <input type="text" class="form-control <?php echo !empty($errors['last_name']) ? 'is-invalid' : ''; ?>" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                            <?php if (!empty($errors['last_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (!empty($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-0 password-field">
                    <label for="password">Password</label>
                    <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                    <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                        <i class="far fa-eye"></i>
                    </button>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                    
                    <div class="password-strength">
                        <div class="password-strength-meter"></div>
                    </div>
                    <div class="strength-text text-muted"></div>
                </div>
                
                <div class="mb-3 password-field">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control <?php echo !empty($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                        <i class="far fa-eye"></i>
                    </button>
                    <?php if (!empty($errors['confirm_password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input <?php echo !empty($errors['terms']) ? 'is-invalid' : ''; ?>" id="terms" name="terms" value="1" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                    <?php if (!empty($errors['terms'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['terms']; ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Create Account <i class="fas fa-user-plus ms-2"></i>
                </button>
            </form>
            
            <div class="auth-footer">
                <p class="mb-3">Or sign up with</p>
                <div class="social-login">
                    <a href="#" class="auth-social-btn facebook" aria-label="Sign up with Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="auth-social-btn google" aria-label="Sign up with Google">
                        <i class="fab fa-google"></i>
                    </a>
                </div>
            </div>
            
            <div class="auth-links">
                <p>Already have an account? <a href="signin.php">Sign In</a></p>
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
            // Password strength meter
            const passwordInput = document.getElementById('password');
            const passwordStrengthMeter = document.querySelector('.password-strength-meter');
            const strengthText = document.querySelector('.strength-text');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            passwordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const strength = calculatePasswordStrength(password);
                
                // Update password strength meter
                passwordStrengthMeter.className = 'password-strength-meter';
                
                if (password === '') {
                    passwordStrengthMeter.style.width = '0';
                    strengthText.textContent = '';
                    return;
                }
                
                if (strength < 25) {
                    passwordStrengthMeter.classList.add('weak');
                    strengthText.textContent = 'Weak: Add uppercase letters, numbers, and special characters';
                    strengthText.style.color = '#dc3545';
                } else if (strength < 50) {
                    passwordStrengthMeter.classList.add('medium');
                    strengthText.textContent = 'Medium: Add more character types for a stronger password';
                    strengthText.style.color = '#ffc107';
                } else if (strength < 75) {
                    passwordStrengthMeter.classList.add('strong');
                    strengthText.textContent = 'Strong: Good password!';
                    strengthText.style.color = '#28a745';
                } else {
                    passwordStrengthMeter.classList.add('very-strong');
                    strengthText.textContent = 'Very Strong: Excellent password!';
                    strengthText.style.color = '#20c997';
                }
                
                passwordStrengthMeter.style.width = strength + '%';
            });
            
            // Check password match
            confirmPasswordInput.addEventListener('input', function() {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity("Passwords don't match");
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            });
            
            function calculatePasswordStrength(password) {
                let strength = 0;
                
                if (password.length === 0) {
                    return strength;
                }
                
                // Length contribution (up to 25%)
                strength += Math.min(25, (password.length * 2));
                
                // Complexity contribution
                const patterns = [
                    /[a-z]/, // lowercase
                    /[A-Z]/, // uppercase
                    /[0-9]/, // numbers
                    /[^a-zA-Z0-9]/ // special characters
                ];
                
                let complexity = 0;
                patterns.forEach(pattern => {
                    if (pattern.test(password)) {
                        complexity += 25;
                    }
                });
                
                strength += Math.min(50, complexity);
                
                // Bonus for mixed character types
                if (complexity > 25 && password.length >= 8) {
                    strength += 25;
                }
                
                return Math.min(100, strength);
            }
            
            // Password visibility toggle
            const toggleButtons = document.querySelectorAll('.password-toggle');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
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
            });
            
            // Animation for form with errors
            const form = document.getElementById('signup-form');
            if (form.querySelector('.is-invalid')) {
                form.classList.add('shake');
                setTimeout(() => {
                    form.classList.remove('shake');
                }, 600);
            }
            
            // Hide success alert after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-success');
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
