<?php
/**
 * Doghouse Market - User Sidebar Navigation Component
 * 
 * This file provides a comprehensive sidebar navigation for the user area
 * with proper logo display, active menu highlighting, and responsive design
 */

// Check if user is logged in and start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0; // Ensure user_id is available

// Database connection if not already established
if (!isset($conn)) {
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'doghousemarket';
    
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Fetch company information from database if not already available
if (!isset($companyInfo) || empty($companyInfo)) {
    $companyQuery = "SELECT * FROM company_info LIMIT 1";
    $result = mysqli_query($conn, $companyQuery);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $companyInfo = mysqli_fetch_assoc($result);
    } else {
        $companyInfo = [
            'company_name' => 'Doghouse Market',
            'color' => '#ffa500', // Default color if not set in database
            'logo' => ''
        ];
    }
}

// Get current page for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Function to check if a menu item is active
function isActive($page_name) {
    global $current_page;
    return ($current_page == $page_name) ? 'active' : '';
}

// Initialize stats array to prevent undefined array errors
$stats = [
    'pending_orders' => 0,
    'my_dogs' => 0,
    'favorites' => 0,
];

// Get count of user's pending orders
$pendingOrdersQuery = "SELECT COUNT(*) as pending FROM orders WHERE user_id = {$_SESSION['user_id']} AND status = 'Pending'";
$pendingResult = mysqli_query($conn, $pendingOrdersQuery);

if ($pendingResult && $row = mysqli_fetch_assoc($pendingResult)) {
    $stats['pending_orders'] = $row['pending'];
}

// Get count of user's dogs
$myDogsQuery = "SELECT COUNT(*) as count FROM user_dogs WHERE user_id = {$_SESSION['user_id']}";
$myDogsResult = mysqli_query($conn, $myDogsQuery);

if ($myDogsResult && $row = mysqli_fetch_assoc($myDogsResult)) {
    $stats['my_dogs'] = $row['count'];
}

// Get cart count for the user
$cart_count = 0;
if (isset($user_id)) {
    $cartCountQuery = "SELECT COUNT(*) as count FROM cart WHERE user_id = $user_id";
    $cartCountResult = mysqli_query($conn, $cartCountQuery);
    if ($cartCountResult) {
        $cart_count = mysqli_fetch_assoc($cartCountResult)['count'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
</head>
<body>
<style>
    :root {
        --theme-color: <?php echo $companyInfo['color'] ?? '#ffa500'; ?>;
        --theme-color-light: <?php 
            $hex = ltrim($companyInfo['color'] ?? '#ffa500', '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $r = max(0, min(255, $r + ($r * 0.2)));
            $g = max(0, min(255, $g + ($g * 0.2)));
            $b = max(0, min(255, $b + ($b * 0.2)));
            echo sprintf("#%02x%02x%02x", $r, $g, $b);
        ?>;
        --theme-color-dark: <?php 
            $hex = ltrim($companyInfo['color'] ?? '#ffa500', '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $r = max(0, min(255, $r - ($r * 0.2)));
            $g = max(0, min(255, $g - ($g * 0.2)));
            $b = max(0, min(255, $b - ($b * 0.2)));
            echo sprintf("#%02x%02x%02x", $r, $g, $b);
        ?>;
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.7);
        --sidebar-text-bright: #ffffff;
    }

    /* Prevent horizontal overflow globally */
    html, body {
        overflow-x: hidden;
        max-width: 100%;
    }

    /* Reset and clean up top navigation styles */
    .top-nav {
        position: fixed;
        top: 0;
        right: 0;
        left: 0;
        height: 60px;
        background: var(--theme-color);
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 1040;
        display: flex;
        align-items: center;
    }

    .top-nav-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 0 15px;
    }

    .top-nav-title {
        font-size: clamp(1.2rem, 2vw, 1.5rem);
        margin: 0;
        font-weight: 500;
        color: #ffffff;
        letter-spacing: 0.5px;
    }

    .top-nav-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .notification-icon,
    .user-profile {
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .notification-icon:hover,
    .user-profile:hover {
        background: var(--theme-color-light);
    }

    .notification-icon i,
    .user-profile i {
        font-size: 1.2rem;
        color: #ffffff;
    }

    /* Cart Icon in Top Nav */
    .cart-icon {
        position: relative;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-left: 15px;
    }
    
    .cart-icon:hover {
        transform: scale(1.1);
    }
    
    .cart-icon i {
        font-size: 22px;
        color: #ffffff;
    }
    
    .cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }

    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        top: 60px;
        left: 0;
        bottom: 0;
        width: 250px;
        background-color: var(--theme-color);
        color: #fff;
        z-index: 1030;
        transition: all 0.3s ease;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
        overflow-y: auto;
        overflow-x: hidden; /* Prevent horizontal scroll in sidebar */
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none;  /* IE and Edge */
    }

    /* Hide scrollbar for Chrome, Safari and Opera */
    .sidebar::-webkit-scrollbar {
        display: none;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: var(--theme-color);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background-color: #4a5568;
        border-radius: 3px;
    }

    .sidebar-header {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-logo {
        max-height: 35px;
        max-width: 35px;
        margin-right: 0.75rem;
    }

    .sidebar-brand {
        font-weight: 700;
        font-size: 1.25rem;
        color: var(--sidebar-text-bright);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-user {
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-user-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 0.75rem;
        background-color: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #fff;
    }

    .sidebar-user-details {
        overflow: hidden;
    }

    .sidebar-user-name {
        font-weight: 600;
        margin-bottom: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--sidebar-text-bright);
    }

    .sidebar-user-role {
        font-size: 0.75rem;
        color: var(--sidebar-text-muted);
    }

    .sidebar-nav {
        padding: 1rem 0;
    }

    .sidebar-item {
        position: relative;
    }

    .sidebar-link {
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        color: var(--sidebar-text);
        text-decoration: none;
        transition: all 0.2s ease;
        position: relative;
        font-size: 0.925rem;
    }

    .sidebar-link:hover {
        color: var(--sidebar-text-bright);
        background-color: var(--theme-color-light);
    }

    .sidebar-link.active {
        color: var(--sidebar-text-bright);
        background-color: var(--theme-color-light);
        font-weight: 500;
    }

    .sidebar-link i {
        width: 20px;
        text-align: center;
        margin-right: 0.75rem;
        font-size: 1rem;
    }

    .sidebar-badge {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background-color: #ff6b6b;
        color: #fff;
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Ensure proper spacing for main content - adjusted to respect sidebar width */
    .main-area {
        margin-top: 60px;
        margin-left: 250px;
        width: calc(100% - 250px); /* Ensure main content respects sidebar width */
        transition: all 0.3s ease;
        overflow-x: hidden; /* Prevent horizontal overflow */
    }

    .sidebar.collapsed {
        transform: translateX(-250px);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 1029;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .sidebar-overlay.active {
        display: block;
        opacity: 1;
        visibility: visible;
    }

    .main-area.expanded {
        margin-left: 0;
        width: 100%;
    }

    /* Responsive adjustments */
    @media (max-width: 767.98px) {
        .top-nav-search {
            display: none;
        }

        /* Sidebar hidden by default on mobile, controlled by bottom nav */
        .sidebar {
            transform: translateX(-250px);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }

        .main-area {
            margin-left: 0;
            width: 100%;
        }
    }

    /* Content container adjustments to ensure full width utilization */
    .content-container {
        padding: 20px;
        width: 100%;
        box-sizing: border-box;
        overflow-x: hidden;
    }
</style>

<!-- Top Navigation -->
<nav class="top-nav">
    <div class="top-nav-content">
        <!-- Removed sidebar-toggle button completely -->
        <h1 class="top-nav-title"><?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></h1>
        <div class="top-nav-actions">
            <div class="notification-icon">
                <i class="material-icons">notifications</i>
                <?php if($stats['pending_orders'] > 0): ?>
                <span class="notification-badge"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Cart Icon -->
            <a href="cart.php" class="cart-icon" title="Shopping Cart">
                <i class="fas fa-shopping-cart"></i>
                <?php if($cart_count > 0): ?>
                <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            
            <div class="user-profile">
                <i class="material-icons">account_circle</i>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <!-- Sidebar Header with Logo -->
    <div class="sidebar-header">
        <?php if (!empty($companyInfo['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($companyInfo['logo']); ?>" alt="Logo" class="sidebar-logo">
        <?php else: ?>
            <i class="fas fa-paw me-2"></i>
        <?php endif; ?>
        <h5 class="sidebar-brand"><?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></h5>
    </div>

    <!-- User Profile Section -->
    <div class="sidebar-user">
        <div class="sidebar-user-img">
            <?php echo strtoupper(substr($username, 0, 1)); ?>
        </div>
        <div class="sidebar-user-details">
            <p class="sidebar-user-name"><?php echo htmlspecialchars($username); ?></p>
            <p class="sidebar-user-role">User</p>
        </div>
    </div>

    <!-- Main Navigation -->
    <ul class="sidebar-nav list-unstyled">
        <!-- Dashboard -->
        <li class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link <?php echo isActive('dashboard.php'); ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Browse Dogs -->
        <li class="sidebar-item">
            <a href="browse_dogs.php" class="sidebar-link <?php echo isActive('browse_dogs.php'); ?>">
                <i class="fas fa-search"></i>
                <span>Browse Dogs</span>
            </a>
        </li>

        <!-- My Dogs - Updated to ensure proper linking -->
        <li class="sidebar-item">
            <a href="my_dogs.php" class="sidebar-link <?php echo isActive('my_dogs.php'); ?>">
                <i class="fas fa-dog"></i>
                <span>My Dogs</span>
                <?php if($stats['my_dogs'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['my_dogs']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Cart -->
        <li class="sidebar-item">
            <a href="cart.php" class="sidebar-link <?php echo isActive('cart.php'); ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Shopping Cart</span>
                <?php if($cart_count > 0): ?>
                    <span class="sidebar-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- My Orders Section -->
        <li class="sidebar-item">
            <a href="my_orders.php" class="sidebar-link <?php echo isActive('my_orders.php'); ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>My Orders</span>
                <?php if($stats['pending_orders'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Profile Settings -->
        <li class="sidebar-item">
            <a href="profile.php" class="sidebar-link <?php echo isActive('profile.php'); ?>">
                <i class="fas fa-user-cog"></i>
                <span>My Profile</span>
            </a>
        </li>

        <!-- Divider -->
        <li class="sidebar-section">
            <hr style="border-color: rgba(255,255,255,0.2); margin: 15px 0;">
        </li>

        <!-- Logout - Updated to link to ../logout.php -->
        <li class="sidebar-item">
            <a href="../logout.php" class="sidebar-link" onclick="return confirm('Are you sure you want to logout?');">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</nav>

<!-- JavaScript for Sidebar Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    let sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainArea = document.querySelector('.main-area');
    
    // Removed sidebar toggle button functionality since it's controlled by bottom nav now
    
    // Create overlay element if it doesn't exist
    if (!sidebarOverlay) {
        sidebarOverlay = document.createElement('div');
        sidebarOverlay.id = 'sidebarOverlay';
        sidebarOverlay.className = 'sidebar-overlay';
        document.body.appendChild(sidebarOverlay);
        
        // Add click event to close sidebar when overlay is clicked
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 767.98) {
            sidebar.classList.remove('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
            if (mainArea) {
                mainArea.classList.remove('expanded');
            }
        }
    });
});
</script>

<!-- Include Mobile Bottom Navigation -->
<?php include 'bottom_nav.php'; ?>

</body>
</html>
