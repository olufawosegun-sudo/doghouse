<?php
/**
 * Doghouse Market - Sidebar Navigation Component
 * 
 * This file provides a responsive sidebar navigation for both admin and user areas
 * with proper logo display, active menu highlighting, and role-specific options
 */

// Check if user is logged in and start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine if user is admin or regular user
$isAdmin = isset($_SESSION['admin_id']);
$isUser = isset($_SESSION['user_id']);

// Handle the case when no one is logged in
if (!$isAdmin && !$isUser) {
    $current_path = $_SERVER['PHP_SELF'];
    if (strpos($current_path, '/admin/') !== false) {
        header("Location: index.php");
    } else {
        header("Location: login.php");
    }
    exit;
}

// Set username based on session
if ($isAdmin) {
    $username = $_SESSION['admin_username'] ?? 'Admin';
    $user_id = $_SESSION['admin_id'];
} else {
    $username = $_SESSION['username'] ?? 'User';
    $user_id = $_SESSION['user_id'];
}

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
            'color' => '#2c3e50', // Default color if not set in database
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

// Function to check if a submenu section is active
function isMenuActive($pages) {
    global $current_page;
    return in_array($current_page, $pages) ? 'show' : '';
}

// Path prefix for admin/user areas
$adminPrefix = $isAdmin ? '' : 'admin/';
$userPrefix = $isUser ? '' : '';

// Get stats based on user role
$stats = [
    'pending' => 0,
    'new_users' => 0,
    'favorites' => 0,
    'cart_items' => 0
];

if ($isAdmin) {
    // Admin stats
    $pendingOrdersQuery = "SELECT COUNT(*) as pending FROM orders WHERE status = 'Pending'";
    $pendingResult = mysqli_query($conn, $pendingOrdersQuery);
    
    if ($pendingResult && $row = mysqli_fetch_assoc($pendingResult)) {
        $stats['pending'] = $row['pending'];
    }
    
    $newUsersQuery = "SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $newUsersResult = mysqli_query($conn, $newUsersQuery);
    
    if ($newUsersResult && $row = mysqli_fetch_assoc($newUsersResult)) {
        $stats['new_users'] = $row['new_users'];
    }
} else {
    // User stats
    $cartQuery = "SELECT COUNT(*) as cart_items FROM cart WHERE user_id = $user_id";
    $cartResult = mysqli_query($conn, $cartQuery);
    
    if ($cartResult && $row = mysqli_fetch_assoc($cartResult)) {
        $stats['cart_items'] = $row['cart_items'];
    }
    
    $favoritesQuery = "SELECT COUNT(*) as favorites FROM favorites WHERE user_id = $user_id";
    $favoritesResult = mysqli_query($conn, $favoritesQuery);
    
    if ($favoritesResult && $row = mysqli_fetch_assoc($favoritesResult)) {
        $stats['favorites'] = $row['favorites'];
    }
}

function adjustBrightness($hexCode, $adjustPercent) {
    $hex = ltrim($hexCode, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, $r + ($r * ($adjustPercent / 100))));
    $g = max(0, min(255, $g + ($g * ($adjustPercent / 100))));
    $b = max(0, min(255, $b + ($b * ($adjustPercent / 100))));

    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
<style>
    :root {
        --theme-color: <?php echo $companyInfo['color'] ?? '#2c3e50'; ?>;
        --theme-color-light: <?php echo isset($companyInfo['color']) ? adjustBrightness($companyInfo['color'], 20) : '#3c5876'; ?>;
        --theme-color-dark: <?php echo isset($companyInfo['color']) ? adjustBrightness($companyInfo['color'], -20) : '#1a2632'; ?>;
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.7);
        --sidebar-text-bright: #ffffff;
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

    /* Clean hamburger button styles */
    .sidebar-toggle {
        width: 40px;
        height: 40px;
        display: none; /* Hidden by default */
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        padding: 0;
        margin-right: 15px;
        cursor: pointer;
        position: relative;
        z-index: 1041; /* Ensure it's above other elements */
    }

    .sidebar-toggle .material-icons {
        font-size: 28px;
        color: #ffffff;
        font-weight: bold;
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
        background-color: var(--primary-color);
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
        background-color: var(--primary-color);
        color: #fff;
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .sidebar-dropdown-toggle {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255,255,255,0.5);
        transition: transform 0.35s ease;
    }

    .sidebar-dropdown-toggle.rotated {
        transform: translateY(-50%) rotate(180deg);
    }

    .sidebar-dropdown {
        padding: 0;
        overflow: hidden;
        max-height: 0;
        transition: max-height 0.35s ease;
        background-color: rgba(0,0,0,0.15);
    }

    .sidebar-dropdown.show {
        max-height: 1000px;
    }

    .sidebar-dropdown .sidebar-link {
        padding-left: 3.25rem;
        font-size: 0.875rem;
    }

    .sidebar-dropdown .sidebar-badge {
        right: 15px;
    }

    .sidebar-section {
        padding: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--sidebar-text-muted);
        margin-bottom: 1rem;
    }

    .sidebar-stats {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .sidebar-stat {
        font-size: 0.875rem;
        color: rgba(255,255,255,0.7);
    }

    .sidebar-progress {
        height: 5px;
        background-color: rgba(255,255,255,0.1);
        border-radius: 10px;
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .sidebar-progress-bar {
        background-color: var(--theme-color-light);
        height: 100%;
        border-radius: 10px;
    }

    .sidebar-collapsed {
        width: 70px;
    }

    .sidebar-collapsed .sidebar-brand,
    .sidebar-collapsed .sidebar-user-details,
    .sidebar-collapsed .sidebar-link span,
    .sidebar-collapsed .sidebar-section-title,
    .sidebar-collapsed .sidebar-stats,
    .sidebar-collapsed .sidebar-dropdown-toggle {
        display: none;
    }

    .sidebar-collapsed .sidebar-link {
        justify-content: center;
        padding: 0.75rem;
    }

    .sidebar-collapsed .sidebar-link i {
        margin-right: 0;
        font-size: 1.25rem;
    }

    .sidebar-collapsed .sidebar-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        padding: 0.15rem 0.4rem;
    }

    .sidebar-collapsed .sidebar-header,
    .sidebar-collapsed .sidebar-user {
        justify-content: center;
    }

    .sidebar-collapsed .sidebar-dropdown {
        position: absolute;
        left: 70px;
        top: 0;
        width: 180px;
        background-color: var(--theme-color);
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        border-radius: 0 4px 4px 0;
        max-height: 0;
        overflow: hidden;
    }

    .sidebar-collapsed .sidebar-item:hover .sidebar-dropdown {
        max-height: 1000px;
    }

    .sidebar-collapsed .sidebar-dropdown .sidebar-link {
        padding-left: 1.5rem;
        justify-content: flex-start;
    }

    /* Simple hover effect for hamburger button */
    .sidebar-toggle:hover i {
        opacity: 0.7;
    }

    /* Improved responsive styles */
    @media (max-width: 767.98px) {
        .top-nav-search {
            display: none;
        }

        .sidebar-toggle {
            display: flex; /* Show on mobile */
            position: relative;
            z-index: 1041;
        }

        .sidebar {
            top: 60px;
            height: calc(100vh - 60px);
            width: 250px;
            transform: translateX(-250px);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 1025;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
            visibility: visible;
        }
        
        .main-area {
            margin-left: 0 !important;
            width: 100% !important;
            transition: margin-left 0.3s ease;
        }
    }

    /* Desktop layout improvements */
    @media (min-width: 768px) {
        .main-area {
            margin-left: 250px;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
        }
        
        .main-area.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
        
        .sidebar-toggle {
            display: flex;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
    }
</style>

<!-- Top Navigation -->
<nav class="top-nav">
    <div class="top-nav-content">
        <button class="sidebar-toggle" id="sidebarToggle">
            <span class="material-icons">menu</span>
        </button>
        <h1 class="top-nav-title">Doghouse Market</h1>
        <div class="top-nav-actions">
            <?php if ($isUser): ?>
            <div class="cart-icon" onclick="window.location.href='cart.php'">
                <i class="material-icons">shopping_cart</i>
                <?php if ($stats['cart_items'] > 0): ?>
                <span class="badge"><?php echo $stats['cart_items']; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="notification-icon">
                <i class="material-icons">notifications</i>
            </div>
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
            <?php
            $logoPath = $isAdmin ? 
                str_replace('images/', '../images/', $companyInfo['logo']) : 
                $companyInfo['logo'];
            ?>
            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="sidebar-logo">
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
            <p class="sidebar-user-role"><?php echo $isAdmin ? 'Administrator' : 'Customer'; ?></p>
        </div>
    </div>

    <!-- Main Navigation -->
    <ul class="sidebar-nav list-unstyled">
        <?php if($isAdmin): ?>
        <!-- Admin Menu Items -->
        <li class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link <?php echo isActive('dashboard.php'); ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="orders.php" class="sidebar-link <?php echo isActive('orders.php'); ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <?php if($stats['pending'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="customers.php" class="sidebar-link <?php echo isActive('customers.php'); ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
                <?php if($stats['new_users'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['new_users']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="#dogSubmenu" data-bs-toggle="collapse" class="sidebar-link <?php echo isMenuActive(['manage_dogs.php', 'add_dog.php', 'dog_categories.php']); ?>">
                <i class="fas fa-dog"></i>
                <span>Dog Management</span>
                <i class="fas fa-chevron-down sidebar-dropdown-toggle <?php echo isMenuActive(['manage_dogs.php', 'add_dog.php', 'dog_categories.php']) ? 'rotated' : ''; ?>"></i>
            </a>
            <ul class="collapse list-unstyled sidebar-dropdown <?php echo isMenuActive(['manage_dogs.php', 'add_dog.php', 'dog_categories.php']) ? 'show' : ''; ?>" id="dogSubmenu">
                <li class="sidebar-item">
                    <a href="manage_dogs.php" class="sidebar-link <?php echo isActive('manage_dogs.php'); ?>">
                        <span>All Dogs</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="add_dog.php" class="sidebar-link <?php echo isActive('add_dog.php'); ?>">
                        <span>Add New Dog</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="dog_categories.php" class="sidebar-link <?php echo isActive('dog_categories.php'); ?>">
                        <span>Categories</span>
                    </a>
                </li>
            </ul>
        </li>

        <li class="sidebar-item">
            <a href="#settingsSubmenu" data-bs-toggle="collapse" class="sidebar-link <?php echo isMenuActive(['site_settings.php', 'user_settings.php', 'payment_settings.php']); ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
                <i class="fas fa-chevron-down sidebar-dropdown-toggle <?php echo isMenuActive(['site_settings.php', 'user_settings.php', 'payment_settings.php']) ? 'rotated' : ''; ?>"></i>
            </a>
            <ul class="collapse list-unstyled sidebar-dropdown <?php echo isMenuActive(['site_settings.php', 'user_settings.php', 'payment_settings.php']) ? 'show' : ''; ?>" id="settingsSubmenu">
                <li class="sidebar-item">
                    <a href="site_settings.php" class="sidebar-link <?php echo isActive('site_settings.php'); ?>">
                        <span>Site Settings</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="user_settings.php" class="sidebar-link <?php echo isActive('user_settings.php'); ?>">
                        <span>My Account</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php else: ?>
        <!-- User Menu Items -->
        <li class="sidebar-item">
            <a href="account.php" class="sidebar-link <?php echo isActive('account.php'); ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Account</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="my_orders.php" class="sidebar-link <?php echo isActive('my_orders.php'); ?>">
                <i class="fas fa-box-open"></i>
                <span>My Orders</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="favorites.php" class="sidebar-link <?php echo isActive('favorites.php'); ?>">
                <i class="fas fa-heart"></i>
                <span>My Favorites</span>
                <?php if($stats['favorites'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['favorites']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="cart.php" class="sidebar-link <?php echo isActive('cart.php'); ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Shopping Cart</span>
                <?php if($stats['cart_items'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['cart_items']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="browse.php" class="sidebar-link <?php echo isActive('browse.php'); ?>">
                <i class="fas fa-search"></i>
                <span>Browse Dogs</span>
            </a>
        </li>
        
        <li class="sidebar-item">
            <a href="settings.php" class="sidebar-link <?php echo isActive('settings.php'); ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Logout - For both user types -->
        <li class="sidebar-item">
            <a href="<?php echo $isAdmin ? 'logout.php' : 'logout.php'; ?>" class="sidebar-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>

    <?php if($isAdmin): ?>
    <!-- System Status Section - Admin only -->
    <div class="sidebar-section">
        <h6 class="sidebar-section-title">SYSTEM STATUS</h6>
        
        <!-- Orders Status -->
        <div class="sidebar-stats">
            <div class="sidebar-stat">Pending Orders</div>
            <div class="sidebar-stat"><?php echo $stats['pending']; ?>/<?php echo ($stats['pending'] + 10); ?></div>
        </div>
        <div class="sidebar-progress">
            <div class="sidebar-progress-bar" style="width: <?php echo ($stats['pending'] > 0) ? (($stats['pending'] / ($stats['pending'] + 10)) * 100) : 0; ?>%"></div>
        </div>
        
        <!-- Server Load -->
        <div class="sidebar-stats">
            <div class="sidebar-stat">Server Load</div>
            <div class="sidebar-stat"><?php echo rand(20, 80); ?>%</div>
        </div>
        <div class="sidebar-progress">
            <div class="sidebar-progress-bar" style="width: <?php echo rand(20, 80); ?>%"></div>
        </div>
        
        <!-- Memory Usage -->
        <div class="sidebar-stats">
            <div class="sidebar-stat">Memory Usage</div>
            <div class="sidebar-stat"><?php echo rand(30, 70); ?>%</div>
        </div>
        <div class="sidebar-progress">
            <div class="sidebar-progress-bar" style="width: <?php echo rand(30, 70); ?>%"></div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- JavaScript for Sidebar Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainArea = document.querySelector('.main-area');
    
    function toggleSidebar() {
        if (window.innerWidth < 768) {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
            if (mainArea) {
                mainArea.classList.toggle('expanded');
            }
        }
        
        // Store sidebar state
        const sidebarState = window.innerWidth < 768 ? 
            sidebar.classList.contains('active') : 
            sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarState', sidebarState);
    }
    
    // Toggle sidebar on button click
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }
    
    // Close sidebar when clicking overlay (mobile only)
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 767.98) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            // Apply the collapsed state if previously set
            if (localStorage.getItem('sidebarState') === 'true') {
                sidebar.classList.add('collapsed');
                if (mainArea) {
                    mainArea.classList.add('expanded');
                }
            }
        } else {
            sidebar.classList.remove('collapsed');
            if (mainArea) {
                mainArea.classList.remove('expanded');
            }
            // Apply the active state if previously set
            if (localStorage.getItem('sidebarState') === 'true') {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            }
        }
    });
    
    // Initialize sidebar state from localStorage based on screen size
    const savedSidebarState = localStorage.getItem('sidebarState');
    if (savedSidebarState === 'true') {
        if (window.innerWidth < 768) {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
        } else {
            sidebar.classList.add('collapsed');
            if (mainArea) {
                mainArea.classList.add('expanded');
            }
        }
    }
    
    // Handle dropdown menus
    const dropdownToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenuId = this.getAttribute('href');
            const submenu = document.querySelector(submenuId);
            
            // Toggle dropdown
            if (submenu) {
                submenu.classList.toggle('show');
                this.querySelector('.sidebar-dropdown-toggle').classList.toggle('rotated');
            }
        });
    });
});
</script>

</body>
</html>