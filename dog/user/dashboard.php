<?php
/**
 * Dog House Market - User Dashboard
 * A comprehensive dashboard for users to manage dog listings and transactions
 */

// Start session
session_start();

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
$conn = require_once '../dbconnect.php';

// Fetch user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Fetch company information
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
    ];
}

// Create necessary tables if they don't exist
// Dogs table
$createDogsTableQuery = "CREATE TABLE IF NOT EXISTS dogs (
    dog_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    breed VARCHAR(100) NOT NULL,
    age VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    size ENUM('Small', 'Medium', 'Large', 'Extra Large') NOT NULL,
    color VARCHAR(50) NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    trait TEXT,
    vaccination_status ENUM('Up to date', 'Needs updating', 'None') NOT NULL,
    image_url VARCHAR(255),
    status ENUM('Available', 'Sold', 'Pending', 'Reserved') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(user_id)
)";
mysqli_query($conn, $createDogsTableQuery);

// Transactions table
$createTransactionsTableQuery = "CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    dog_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dog_id) REFERENCES dogs(dog_id),
    FOREIGN KEY (seller_id) REFERENCES users(user_id),
    FOREIGN KEY (buyer_id) REFERENCES users(user_id)
)";
mysqli_query($conn, $createTransactionsTableQuery);

// Initialize variables for dashboard statistics
$myDogs = [];
$totalMyDogs = 0;
$availableDogs = 0;
$totalSales = 0;
$totalRevenue = 0;
$totalPurchases = 0;
$transactions = [];
$recentMarketplaceDogs = [];

// Fetch user's dogs with error handling
try {
    $myDogsQuery = "SELECT * FROM dogs WHERE seller_id = ? ORDER BY created_at DESC LIMIT 4";
    $stmt = mysqli_prepare($conn, $myDogsQuery);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $myDogs[] = $row;
        }
        
        // Get total count of user's dogs
        $totalDogsQuery = "SELECT COUNT(*) as total FROM dogs WHERE seller_id = ?";
        $stmt = mysqli_prepare($conn, $totalDogsQuery);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $totalMyDogs = mysqli_fetch_assoc($result)['total'];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Fetch marketplace statistics with error handling
try {
    // Count available dogs (excluding user's own)
    $availableDogsQuery = "SELECT COUNT(*) as total FROM dogs WHERE status = 'Available' AND seller_id != ?";
    $stmt = mysqli_prepare($conn, $availableDogsQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $availableDogs = mysqli_fetch_assoc($result)['total'];
    }
    
    // Get recent marketplace listings
    $marketplaceQuery = "SELECT d.*, u.first_name, u.last_name 
                        FROM dogs d 
                        JOIN users u ON d.seller_id = u.user_id 
                        WHERE d.status = 'Available' AND d.seller_id != ? 
                        ORDER BY d.created_at DESC LIMIT 4";
    $stmt = mysqli_prepare($conn, $marketplaceQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $recentMarketplaceDogs[] = $row;
        }
    }
} catch (Exception $e) {
    // Handle error silently
}

// Fetch transaction statistics with error handling
try {
    // Get total sales count and revenue
    $salesQuery = "SELECT COUNT(*) as total, IFNULL(SUM(price), 0) as revenue 
                  FROM transactions WHERE seller_id = ? AND status = 'Completed'";
    $stmt = mysqli_prepare($conn, $salesQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $salesStats = mysqli_fetch_assoc($result);
        $totalSales = $salesStats['total'] ?? 0;
        $totalRevenue = $salesStats['revenue'] ?? 0;
    }
    
    // Get total purchases count
    $purchasesQuery = "SELECT COUNT(*) as total FROM transactions 
                      WHERE buyer_id = ? AND status = 'Completed'";
    $stmt = mysqli_prepare($conn, $purchasesQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $totalPurchases = mysqli_fetch_assoc($result)['total'];
    }
    
    // Get recent transactions
    $transactionsQuery = "SELECT t.*, d.name, d.breed, d.image_url, d.status as dog_status,
                        u1.first_name as seller_fname, u1.last_name as seller_lname,
                        u2.first_name as buyer_fname, u2.last_name as buyer_lname
                        FROM transactions t 
                        JOIN dogs d ON t.dog_id = d.dog_id 
                        JOIN users u1 ON t.seller_id = u1.user_id
                        JOIN users u2 ON t.buyer_id = u2.user_id
                        WHERE t.seller_id = ? OR t.buyer_id = ?
                        ORDER BY t.transaction_date DESC LIMIT 5";
    $stmt = mysqli_prepare($conn, $transactionsQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $transactions[] = $row;
        }
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get current active section from URL parameter or default to dashboard
$activeSection = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo htmlspecialchars($companyInfo['company_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($companyInfo['primary_color'] ?? '#FFA500'); ?>;
            --primary-dark: <?php 
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
                // Darken by 15%
                $r = max(0, $r * 0.85);
                $g = max(0, $g * 0.85);
                $b = max(0, $b * 0.85);
                echo sprintf('#%02x%02x%02x', $r, $g, $b);
            ?>;
            --primary-light: <?php 
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
                // Lighten by 40%
                $r = min(255, $r + (255 - $r) * 0.4);
                $g = min(255, $g + (255 - $g) * 0.4);
                $b = min(255, $b + (255 - $b) * 0.4);
                echo sprintf('#%02x%02x%02x', $r, $g, $b);
            ?>;
            --primary-rgb: <?php 
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
            --secondary-color: #334155;
            --bg-light: #f8fafc;
            --bg-dark: #1e293b;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            --card-border-radius: 12px;
            --btn-border-radius: 6px;
        }
        
        /* Base Styles */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        
        /* Layout */
        .dashboard-container {
            display: flex;
            width: 100%;
        }

     
        
        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 5px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .user-profile:hover {
            background-color: var(--bg-light);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1rem;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-email {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-dark);
            cursor: pointer;
        }
        
        .page-title {
            font-size: 1.75rem;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Dashboard Cards */
        .section-title {
            font-size: 1.25rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 5rem;
            color: rgba(var(--primary-rgb), 0.1);
            line-height: 1;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0 5px;
        }
        
        .stat-change {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            margin-top: auto;
        }
        
        .stat-change.positive {
            color: var(--success);
        }
        
        .stat-change.negative {
            color: var(--danger);
        }
        
        .stat-change i {
            margin-right: 5px;
            font-size: 0.8rem;
        }
        
        /* Card Components */
        .card {
            background-color: #fff;
            border: none;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.1rem;
            margin: 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Dog Cards */
        .dog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .dog-card {
            background-color: #fff;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .dog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .dog-img-container {
            height: 180px;
            overflow: hidden;
            position: relative;
        }
        
        .dog-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .dog-card:hover .dog-img {
            transform: scale(1.1);
        }
        
        .dog-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .status-available {
            background-color: var(--success);
            color: #fff;
        }
        
        .status-sold {
            background-color: var(--danger);
            color: #fff;
        }
        
        .status-pending {
            background-color: var(--warning);
            color: #fff;
        }
        
        .status-reserved {
            background-color: var(--info);
            color: #fff;
        }
        
        .dog-content {
            padding: 15px;
        }
        
        .dog-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .dog-breed {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .dog-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .dog-details {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        
        .dog-detail-badge {
            background-color: var(--bg-light);
            padding: 3px 10px;
            border-radius: 15px;
        }
        
        .dog-detail-badge i {
            color: var(--primary-color);
            margin-right: 4px;
        }
        
        .dog-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: var(--btn-border-radius);
            transition: all 0.2s ease;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover, .btn-outline-primary:focus {
            background-color: var(--primary-color);
            color: #fff;
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }
        
        .dashboard-table {
            width: 100%;
            margin-bottom: 0;
        }
        
        .dashboard-table th {
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-top: none;
            padding: 15px 10px;
        }
        
        .dashboard-table td {
            padding: 15px 10px;
            vertical-align: middle;
        }
        
        .dashboard-table tr {
            border-bottom: 1px solid var(--border-color);
        }
        
        .dashboard-table tr:last-child {
            border-bottom: none;
        }
        
        .table-dog-info {
            display: flex;
            align-items: center;
        }
        
        .table-dog-img {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            margin-right: 10px;
        }
        
        .table-dog-name {
            font-weight: 600;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .table-dog-breed {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin: 0;
        }
        
        .table-price {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .transaction-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }
        
        /* Empty states */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--text-muted);
            opacity: 0.6;
            margin-bottom: 15px;
        }
        
        .empty-state h4 {
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--text-muted);
            max-width: 500px;
            margin: 0 auto 20px;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .backdrop {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.3);
                z-index: 999;
            }
            
            .backdrop.show {
                display: block;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 767.98px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .dog-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-dog-breed,
            .table-transaction-date {
                display: none;
            }
        }
        
        @media (max-width: 575.98px) {
            .main-content {
                padding: 15px;
            }
            
            .card-header, .card-body {
                padding: 15px;
            }
            
            .btn {
                padding: 6px 12px;
            }
            
            .dog-actions {
                flex-direction: column;
            }
            
            .table-actions {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .table-actions .btn {
                width: 100%;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }
        
        ::-webkit-scrollbar-thumb {
            background-color: var(--text-muted);
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    <!-- Mobile Backdrop -->
    <div class="backdrop" id="backdrop"></div>
    
    <div class="dashboard-container">
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Dashboard Section -->
            <?php if ($activeSection === 'dashboard'): ?>
                <div class="content-header">
                    <div class="d-flex align-items-center">
                        <button class="mobile-toggle me-3" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="page-title">Dashboard</h1>
                    </div>
                    <div class="header-actions">
                        <span class="badge bg-light text-dark">
                            <i class="far fa-calendar me-1"></i> <?php echo date('F j, Y'); ?>
                        </span>
                        <a href="../add_dog.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-1"></i> Add Dog
                        </a>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <section class="stats-grid fade-in">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dog"></i>
                        </div>
                        <p class="stat-title">My Dogs</p>
                        <h3 class="stat-value"><?php echo $totalMyDogs; ?></h3>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Active Listings
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <p class="stat-title">Total Sales</p>
                        <h3 class="stat-value"><?php echo $totalSales; ?></h3>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> $<?php echo number_format($totalRevenue, 2); ?> Revenue
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <p class="stat-title">Purchases</p>
                        <h3 class="stat-value"><?php echo $totalPurchases; ?></h3>
                        <div class="stat-change">
                            <i class="fas fa-check-circle"></i> Completed Transactions
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <p class="stat-title">Marketplace</p>
                        <h3 class="stat-value"><?php echo $availableDogs; ?></h3>
                        <div class="stat-change">
                            <i class="fas fa-info-circle"></i> Dogs Available
                        </div>
                    </div>
                </section>
                
                <!-- My Dogs Section -->
                <section class="card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-dog me-2 text-primary"></i> My Dogs
                        </h3>
                        <a href="?section=my-dogs" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($myDogs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-dog"></i>
                                <h4>No Dogs Listed Yet</h4>
                                <p>You haven't listed any dogs for sale. Start by adding your first dog listing.</p>
                                <a href="../add_dog.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Add Your First Dog
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="dog-grid">
                                <?php foreach ($myDogs as $dog): ?>
                                    <div class="dog-card">
                                        <div class="dog-img-container">
                                            <span class="dog-status-badge status-<?php echo strtolower($dog['status']); ?>">
                                                <?php echo $dog['status']; ?>
                                            </span>
                                            <?php if (!empty($dog['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($dog['image_url']); ?>" class="dog-img" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/300x200?text=No+Image" class="dog-img" alt="No Image">
                                            <?php endif; ?>
                                        </div>
                                        <div class="dog-content">
                                            <h4 class="dog-name"><?php echo htmlspecialchars($dog['name']); ?></h4>
                                            <p class="dog-breed"><?php echo htmlspecialchars($dog['breed']); ?></p>
                                            <div class="dog-price">$<?php echo number_format($dog['price'], 2); ?></div>
                                            <div class="dog-details">
                                                <span class="dog-detail-badge">
                                                    <i class="fas fa-venus-mars"></i> <?php echo $dog['gender']; ?>
                                                </span>
                                                <span class="dog-detail-badge">
                                                    <i class="fas fa-ruler"></i> <?php echo $dog['size']; ?>
                                                </span>
                                            </div>
                                            <div class="dog-actions">
                                                <a href="../edit_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-primary btn-sm w-100">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                                <a href="../view_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Marketplace Section -->
                <section class="card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-store me-2 text-primary"></i> Marketplace
                        </h3>
                        <a href="marketplace.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentMarketplaceDogs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-store"></i>
                                <h4>No Dogs Available</h4>
                                <p>There are currently no dogs available in the marketplace.</p>
                                <a href="../add_dog.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Add Your Dog for Sale
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="dog-grid">
                                <?php foreach ($recentMarketplaceDogs as $dog): ?>
                                    <div class="dog-card">
                                        <div class="dog-img-container">
                                            <span class="dog-status-badge status-available">Available</span>
                                            <?php if (!empty($dog['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($dog['image_url']); ?>" class="dog-img" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/300x200?text=No+Image" class="dog-img" alt="No Image">
                                            <?php endif; ?>
                                        </div>
                                        <div class="dog-content">
                                            <h4 class="dog-name"><?php echo htmlspecialchars($dog['name']); ?></h4>
                                            <p class="dog-breed"><?php echo htmlspecialchars($dog['breed']); ?></p>
                                            <div class="dog-price">$<?php echo number_format($dog['price'], 2); ?></div>
                                            <div class="dog-details">
                                                <span class="dog-detail-badge">
                                                    <i class="fas fa-venus-mars"></i> <?php echo $dog['gender']; ?>
                                                </span>
                                                <span class="dog-detail-badge">
                                                    <i class="fas fa-ruler"></i> <?php echo $dog['size']; ?>
                                                </span>
                                            </div>
                                            <div class="dog-actions">
                                                <a href="../view_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-primary btn-sm w-100">
                                                    <i class="fas fa-eye me-1"></i> View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Recent Transactions -->
                <section class="card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exchange-alt me-2 text-primary"></i> Recent Transactions
                        </h3>
                        <a href="?section=transactions" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-exchange-alt"></i>
                                <h4>No Transactions Yet</h4>
                                <p>You don't have any transactions yet. Browse the marketplace to find your perfect companion!</p>
                                <a href="?section=marketplace" class="btn btn-primary">
                                    <i class="fas fa-store me-1"></i> Browse Marketplace
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>Dog</th>
                                            <th>Type</th>
                                            <th class="table-transaction-date">Date</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <div class="table-dog-info">
                                                        <?php if (!empty($transaction['image_url'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($transaction['image_url']); ?>" class="table-dog-img" alt="<?php echo htmlspecialchars($transaction['name']); ?>">
                                                        <?php else: ?>
                                                            <img src="https://via.placeholder.com/40x40?text=No+Image" class="table-dog-img" alt="No Image">
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="table-dog-name"><?php echo htmlspecialchars($transaction['name']); ?></h6>
                                                            <p class="table-dog-breed"><?php echo htmlspecialchars($transaction['breed']); ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['seller_id'] == $user_id): ?>
                                                        <span class="badge bg-success">Sale</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Purchase</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="table-transaction-date">
                                                    <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                                </td>
                                                <td>
                                                    <span class="table-price">$<?php echo number_format($transaction['price'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <span class="transaction-status status-<?php echo strtolower($transaction['status']); ?>">
                                                        <?php echo $transaction['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="?section=transactions&id=<?php echo $transaction['transaction_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
            <!-- Other sections -->
            <?php else: ?>
                <div class="content-header">
                    <div class="d-flex align-items-center">
                        <button class="mobile-toggle me-3" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="page-title">
                            <?php 
                            switch($activeSection) {
                                case 'my-dogs': echo 'My Dogs'; break;
                                case 'marketplace': echo 'Marketplace'; break;
                                case 'transactions': echo 'Transactions'; break;
                                case 'favorites': echo 'Favorites'; break;
                                case 'messages': echo 'Messages'; break;
                                case 'profile': echo 'My Profile'; break;
                                case 'settings': echo 'Settings'; break;
                                default: echo 'Dashboard'; break;
                            }
                            ?>
                        </h1>
                    </div>
                    <div class="header-actions">
                        <span class="badge bg-light text-dark">
                            <i class="far fa-calendar me-1"></i> <?php echo date('F j, Y'); ?>
                        </span>
                        <a href="../add_dog.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-1"></i> Add Dog
                        </a>
                    </div>
                </div>
                
                <div class="card fade-in">
                    <div class="card-body text-center">
                        <i class="fas fa-cog fa-spin fa-3x mb-3" style="color: var(--primary-color);"></i>
                        <h3 class="mt-3">Coming Soon</h3>
                        <p class="text-muted mb-4">This section is currently under development. Please check back later!</p>
                        <a href="?section=dashboard" class="btn btn-primary">Return to Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('backdrop');
            const mainContent = document.getElementById('mainContent');
            
            // Toggle sidebar on mobile
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                backdrop.classList.toggle('show');
            });
            
            // Close sidebar when clicking backdrop
            backdrop.addEventListener('click', function() {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
            });
            
            // Handle responsive behavior
            function handleResize() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                    backdrop.classList.remove('show');
                }
            }
            
            // Listen for window resize
            window.addEventListener('resize', handleResize);
            
            // Initialize on page load
            handleResize();
            
            // Section navigation
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('show');
                        backdrop.classList.remove('show');
                    }
                });
            });
        });
    </script>
</body>
</html>
