<?php
/**
 * Doghouse Market - Admin Sidebar Navigation Component
 * 
 * This file provides a comprehensive sidebar navigation for the admin area
 * with proper logo display, active menu highlighting, and responsive design
 */

// Check if user is logged in and start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['admin_username'] ?? 'Admin';

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

// Initialize stats array to prevent undefined array errors
$stats = [
    'pending' => 0,
    'new_users' => 0,
    'new_comments' => 0,
];

// Get count of pending orders
$pendingOrdersQuery = "SELECT COUNT(*) as pending FROM orders WHERE status = 'Pending'";
$pendingResult = mysqli_query($conn, $pendingOrdersQuery);

if ($pendingResult && $row = mysqli_fetch_assoc($pendingResult)) {
    $stats['pending'] = $row['pending'];
}

// Get count of new users in the last 7 days
try {
    // Check if users table exists first
    $tableCheckQuery = "SHOW TABLES LIKE 'users'";
    $tableExists = mysqli_query($conn, $tableCheckQuery);
    
    if ($tableExists && mysqli_num_rows($tableExists) > 0) {
        $newUsersQuery = "SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $newUsersResult = mysqli_query($conn, $newUsersQuery);
        
        if ($newUsersResult && $row = mysqli_fetch_assoc($newUsersResult)) {
            $stats['new_users'] = $row['new_users'];
        }
    }
} catch (Exception $e) {
    // Silently handle the error - the users table might not exist or have the expected structure
    $stats['new_users'] = 0;
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
        --theme-color: <?php echo $companyInfo['color'] ?? '#2c3e50'; ?>;
        --theme-color-light: <?php echo isset($companyInfo['color']) ? adjustBrightness($companyInfo['color'], 20) : '#3c5876'; ?>;
        --theme-color-dark: <?php echo isset($companyInfo['color']) ? adjustBrightness($companyInfo['color'], -20) : '#1a2632'; ?>;
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.7);
        --sidebar-text-bright: #ffffff;
    }

    <?php
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

    @media (max-width: 767.98px) {
        .top-nav-search {
            display: none;
        }

        .sidebar-toggle {
            display: flex; /* Show on mobile */
            position: relative;
            z-index: 1041;
        }

        .sidebar-toggle i {
            color: #1a1a1a; /* Ensure color stays dark on mobile */
        }

        .sidebar {
            top: 60px;
            height: calc(100vh - 60px);
        }
    }

    /* Ensure proper spacing for main content */
    .main-area {
        margin-left: 250px; /* Match sidebar width */
        margin-top: 60px;
        width: calc(100% - 250px); /* Ensure main content respects sidebar width */
        transition: all 0.3s ease;
        overflow-x: hidden; /* Prevent horizontal overflow */
    }

    /* When sidebar is collapsed, expand main area */
    .main-area.expanded {
        margin-left: 0;
        width: 100%;
    }

    /* Hide sidebar on mobile and adjust main area */
    @media (max-width: 767.98px) {
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

    /* Content container adjustments */
    .content-container {
        padding: 20px;
        width: 100%;
        box-sizing: border-box;
    }
</style>

<!-- Top Navigation - Update the HTML structure -->
<nav class="top-nav">
    <div class="top-nav-content">
        <button class="sidebar-toggle" id="sidebarToggle">
            <span class="material-icons">menu</span>
        </button>
        <h1 class="top-nav-title">Doghouse Market</h1>
        <div class="top-nav-actions">
            <div class="notification-icon">
                <i class="material-icons">notifications</i>
                <?php if($stats['pending'] > 0): ?>
                <span class="notification-badge"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
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
            // Fix the logo path by prepending "../" to access the file in parent directory
            $logoPath = str_replace('images/', '../images/', $companyInfo['logo']);
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
            <p class="sidebar-user-role">Administrator</p>
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

        <!-- Orders Section -->
        <li class="sidebar-item">
            <a href="orders.php" class="sidebar-link <?php echo isActive('orders.php'); ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <?php if($stats['pending'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Customers Section -->
        <li class="sidebar-item">
            <a href="customers.php" class="sidebar-link <?php echo isActive('customers.php'); ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
                <?php if($stats['new_users'] > 0): ?>
                    <span class="sidebar-badge"><?php echo $stats['new_users']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Dog Management Section (as a single menu item without dropdown) -->
        <li class="sidebar-item">
            <a href="manage_dogs.php" class="sidebar-link <?php echo isActive('manage_dogs.php'); ?>">
                <i class="fas fa-dog"></i>
                <span>Dog Management</span>
            </a>
        </li>

        <!-- Settings - Changed from dropdown to single link -->
        <li class="sidebar-item">
            <a href="settings.php" class="sidebar-link <?php echo isActive('settings.php'); ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>

        <!-- Logout -->
        <li class="sidebar-item">
            <a href="logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>

    <!-- System Status Section -->
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
        sidebar.classList.toggle('active');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active');
        }
        
        // Update main area class to match sidebar state
        if (mainArea) {
            if (window.innerWidth <= 767.98) {
                // On mobile, we only expand when sidebar is hidden
                mainArea.classList.toggle('expanded', !sidebar.classList.contains('active'));
            } else {
                // On desktop, we only expand when sidebar is explicitly collapsed
                mainArea.classList.toggle('expanded', sidebar.classList.contains('collapsed'));
            }
        }
        
        // Store sidebar state
        const isSidebarActive = sidebar.classList.contains('active');
        try {
            localStorage.setItem('sidebarActive', isSidebarActive);
        } catch (e) {
            console.warn("LocalStorage not available:", e);
        }
    }
    
    // Toggle sidebar on button click
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
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
    
    // Initialize sidebar state from localStorage
    try {
        const savedSidebarState = localStorage.getItem('sidebarActive');
        if (savedSidebarState === 'true') {
            sidebar.classList.add('active');
            if (mainArea) {
                mainArea.classList.add('expanded');
            }
        }
    } catch (e) {
        console.warn("Could not retrieve sidebar state from localStorage:", e);
    }
});
</script>

</body>
</html>
