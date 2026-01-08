<?php
session_start();

// If already logged in, redirect to dashboard
if(isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

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
$companyResult = mysqli_query($conn, $companyQuery);

if ($companyResult && mysqli_num_rows($companyResult) > 0) {
    $companyInfo = mysqli_fetch_assoc($companyResult);
}

// Create admin_users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    echo "Error creating table: " . $conn->error;
}

// Check if default admin exists, if not create one
$check_admin = "SELECT * FROM admin_users WHERE username='admin'";
$result = $conn->query($check_admin);

if ($result->num_rows == 0) {
    // Default password is 'admin123' (hashed)
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    
    $insert_admin = "INSERT INTO admin_users (username, password, email) 
                     VALUES ('admin', '$default_password', 'admin@example.com')";
    
    if ($conn->query($insert_admin) === TRUE) {
        $admin_created = true;
    } else {
        echo "Error: " . $insert_admin . "<br>" . $conn->error;
    }
}

// Process login form
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT id, username, password FROM admin_users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_username'] = $row['username'];
            
            // Redirect to admin dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "Invalid username";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doghouse Market - Admin Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #ffa500;
            --primary-hover: #e69400;
            --secondary-color: #ff7e33;
            --success-color: #10b981;
            --dark-color: #1e293b;
            --light-color: #f1f5f9;
            --text-dark: #334155;
            --text-light: #94a3b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(120deg, #f8f9fa, #e9ecef);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1583337130417-3346a1be7dee?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80') no-repeat center center;
            background-size: cover;
            opacity: 0.035;
            pointer-events: none;
        }
        
        .login-container {
            display: flex;
            max-width: 1200px;
            height: 100vh;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        }
        
        .login-banner {
            flex: 0 0 50%;
            background: linear-gradient(140deg, var(--primary-color), var(--secondary-color));
            color: white;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem 3rem;
            overflow: hidden;
            display: none;
            clip-path: polygon(0 0, 100% 0, 92% 100%, 0 100%);
        }
        
        .login-banner-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            line-height: 0;
            transform: rotate(180deg);
            opacity: 0.15;
            pointer-events: none;
        }
        
        .login-banner-shapes svg {
            position: relative;
            display: block;
            width: calc(150% + 1.3px);
            height: 347px;
        }
        
        .login-banner-shapes .shape-fill {
            fill: #FFFFFF;
        }
        
        .login-banner h1 {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            position: relative;
        }
        
        .login-banner h1::after {
            content: '';
            display: block;
            height: 4px;
            width: 60px;
            background: white;
            margin-top: 20px;
            border-radius: 2px;
        }
        
        .login-banner p {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin-top: 2.5rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1.25rem;
            opacity: 0.9;
        }
        
        .feature-item i {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 0.875rem;
        }
        
        .login-form-area {
            flex: 0 0 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }
        
        .form-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo .icon-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
            border-radius: 20px;
            margin-bottom: 1.25rem;
            box-shadow: 0 10px 20px rgba(255, 165, 0, 0.2);
            transform: rotate(10deg);
        }
        
        .login-logo i {
            color: white;
            font-size: 2.25rem;
            transform: rotate(-10deg);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .login-header h2 {
            font-weight: 800;
            font-size: 1.75rem;
            color: var(--dark-color);
            margin-bottom: 0.75rem;
        }
        
        .login-header p {
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.75rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
        }
        
        .form-control-wrapper {
            position: relative;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            border: 2px solid #edf2f7;
            transition: all 0.25s ease;
        }
        
        .form-control-wrapper:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(255, 165, 0, 0.1);
        }
        
        .form-control-wrapper i {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: var(--text-light);
            transition: all 0.25s ease;
        }
        
        .form-control-wrapper input {
            height: 56px;
            padding: 0 16px 0 48px;
            border: none;
            border-radius: 12px;
            background: transparent;
            color: var(--text-dark);
            width: 100%;
            font-size: 1rem;
        }
        
        .form-control-wrapper input:focus {
            outline: none;
        }
        
        .form-control-wrapper:focus-within i {
            color: var(--primary-color);
        }
        
        .btn-login {
            display: block;
            width: 100%;
            background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-size: 1rem;
            font-weight: 600;
            margin-top: 2.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(255, 165, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.6s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(255, 165, 0, 0.3);
            color: white;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login i {
            margin-right: 10px;
        }
        
        .default-credentials {
            margin-top: 2.5rem;
            padding: 1.25rem;
            border-radius: 12px;
            background-color: #f8fafc;
            border-left: 4px solid var(--primary-color);
        }
        
        .default-credentials h5 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .default-credentials h5 i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .credential-row {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .credential-label {
            flex: 0 0 100px;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-dark);
        }
        
        .credential-value {
            font-family: 'Roboto Mono', monospace;
            background: #edf2f7;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.875rem;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .alert {
            border-radius: 12px;
            margin-bottom: 1.75rem;
            padding: 1rem 1.25rem;
            border: none;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            color: #b91c1c;
        }
        
        .alert-info {
            background-color: #fff8e6;
            color: var(--primary-color);
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .floating-paws {
            position: absolute;
            opacity: 0.1;
            z-index: 0;
            pointer-events: none;
        }
        
        .paw1 {
            top: 10%;
            right: 10%;
            font-size: 3rem;
            animation: float 5s ease-in-out infinite;
            color: var(--primary-color);
        }
        
        .paw2 {
            bottom: 15%;
            left: 10%;
            font-size: 2rem;
            animation: float 7s ease-in-out infinite;
            color: var(--primary-color);
        }
        
        .paw3 {
            top: 45%;
            right: 20%;
            font-size: 1.5rem;
            animation: float 6s ease-in-out infinite;
            color: var(--primary-color);
        }
        
        @media (min-width: 992px) {
            .login-banner {
                display: flex;
            }
            
            .login-form-area {
                flex: 0 0 50%;
            }
        }
        
        @media (max-width: 991px) {
            .login-container {
                max-width: 100%;
                box-shadow: none;
            }
            
            .login-form-area {
                flex: 0 0 100%;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <!-- Banner Side -->
        <div class="login-banner">
            <div class="login-banner-shapes">
                <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" class="shape-fill"></path>
                </svg>
            </div>
            
            <h1>Doghouse Market Admin System</h1>
            <p>A powerful dashboard to manage your dog listings, track sales and analytics, and grow your business with ease.</p>
            
            <ul class="feature-list">
                <li class="feature-item">
                    <i class="fas fa-check"></i>
                    <span>Easy dog inventory management</span>
                </li>
                <li class="feature-item">
                    <i class="fas fa-check"></i>
                    <span>Comprehensive sales tracking</span>
                </li>
                <li class="feature-item">
                    <i class="fas fa-check"></i>
                    <span>Detailed reports and analytics</span>
                </li>
                <li class="feature-item">
                    <i class="fas fa-check"></i>
                    <span>Customer relationship management</span>
                </li>
            </ul>
        </div>
        
        <!-- Login Form Area -->
        <div class="login-form-area">
            <i class="fas fa-paw floating-paws paw1"></i>
            <i class="fas fa-paw floating-paws paw2"></i>
            <i class="fas fa-paw floating-paws paw3"></i>
            
            <div class="form-container">
                <div class="login-logo">
                    <?php if (!empty($companyInfo['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($companyInfo['logo']); ?>" alt="Doghouse Market Logo" class="mb-3" style="max-height: 70px; width: auto;">
                    <?php else: ?>
                        <div class="icon-container">
                            <i class="fas fa-paw"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="login-header">
                    <h2><?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></h2>
                    <p>Please enter your credentials to access the admin panel</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($admin_created) && $admin_created): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> Default admin user has been created.
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="form-control-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" placeholder="Enter your username" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="form-control-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Sign In to Dashboard
                    </button>
                </form>
                
                <div class="default-credentials">
                    <h5><i class="fas fa-key"></i> Default Login Information</h5>
                    <div class="credential-row">
                        <div class="credential-label">Username:</div>
                        <div class="credential-value">admin</div>
                    </div>             </div>
                    <div class="credential-row">         </div>
                        <div class="credential-label">Password:</div>            </div>












</html></body>    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>    </div>        </div>            </div>                </div>                    </div>                        <div class="credential-value">admin123</div>        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
