<?php
/**
 * Dog House Market - View Order Details
 * Admin page to view and manage individual dog adoption orders
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
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

// Initialize variables
$order_id = 0;
$order = null;
$error_message = '';

// Check if order ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    
    // Fetch order details
    $sql = "SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.city, u.postal_code, d.name as dog_name, d.breed, d.age, d.trait, d.image_url
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            JOIN dogs d ON o.dog_id = d.dog_id
            WHERE o.order_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
    } else {
        $error_message = "Order not found";
    }
    
    $stmt->close();
} else {
    $error_message = "Invalid order ID";
}

// Fetch company information for the logo
$companyInfo = [];
$companyQuery = "SELECT * FROM company_info LIMIT 1";
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Page Header */
        .page-header {
            background: linear-gradient(to right, rgba(0,0,0,0.7), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1548199973-03cce0bbc87b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center;
            background-size: cover;
            padding: 100px 0;
            margin-bottom: 60px;
            color: white;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .page-header p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
        }

        /* Order Details */
        .order-details {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .order-details h2 {
            font-size: 1.8rem;
            margin-bottom: 20px;
        }
        
        .order-details p {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .order-details .label {
            font-weight: 600;
            color: var(--text-muted);
        }
        
        .order-details .value {
            margin-left: 10px;
        }
        
        .order-details .dog-image {
            max-width: 150px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        /* Buttons */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: darken(var(--primary-color), 10%);
            border-color: darken(var(--primary-color), 10%);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-secondary:hover {
            background-color: darken(var(--secondary-color), 10%);
            border-color: darken(var(--secondary-color), 10%);
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
    
    <!-- Page Header -->
    <header class="page-header">
        <div class="container">
            <h1>Order Details</h1>
            <p>View and manage the details of the adoption order</p>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Order Details Section -->
        <section class="order-details">
            <?php if ($order): ?>
                <h2>Order #<?php echo $order['order_id']; ?></h2>
                
                <div class="mb-4">
                    <img src="<?php echo !empty($order['image_url']) ? '../' . htmlspecialchars($order['image_url']) : 'https://via.placeholder.com/150?text=No+Image'; ?>" alt="Dog Image" class="dog-image">
                </div>
                
                <p><span class="label">Dog Name:</span><span class="value"><?php echo htmlspecialchars($order['dog_name']); ?></span></p>
                <p><span class="label">Breed:</span><span class="value"><?php echo htmlspecialchars($order['breed']); ?></span></p>
                <p><span class="label">Age:</span><span class="value"><?php echo htmlspecialchars($order['age']); ?> years</span></p>
                <p><span class="label">Traits:</span><span class="value"><?php echo htmlspecialchars($order['trait']); ?></span></p>
                <p><span class="label">Price:</span><span class="value">$<?php echo number_format($order['price'], 2); ?></span></p>
                <p><span class="label">Order Date:</span><span class="value"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span></p>
                
                <h3>Customer Information</h3>
                <p><span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span></p>
                <p><span class="label">Email:</span><span class="value"><?php echo htmlspecialchars($order['email']); ?></span></p>
                <p><span class="label">Phone:</span><span class="value"><?php echo htmlspecialchars($order['phone']); ?></span></p>
                <p><span class="label">Address:</span><span class="value"><?php echo htmlspecialchars($order['address'] . ', ' . $order['city'] . ', ' . $order['postal_code']); ?></span></p>
                
                <div class="mt-4">
                    <a href="javascript:history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>. All rights reserved.</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>