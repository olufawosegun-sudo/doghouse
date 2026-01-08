<?php
/**
 * Mobile Bottom Navigation Component
 * Only visible on mobile devices (< 768px)
 * Includes all desktop navigation features
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get stats for badges (reuse from sidenav if available)
if (!isset($stats)) {
    $stats = [
        'pending_orders' => 0,
        'my_dogs' => 0
    ];
    
    if (isset($conn) && $conn instanceof mysqli) {
        // Get pending orders count
        $pendingQuery = "SELECT COUNT(*) as pending FROM orders WHERE user_id = {$_SESSION['user_id']} AND status = 'Pending'";
        $pendingResult = mysqli_query($conn, $pendingQuery);
        if ($pendingResult && $row = mysqli_fetch_assoc($pendingResult)) {
            $stats['pending_orders'] = $row['pending'];
        }
        
        // Get my dogs count
        $dogsQuery = "SELECT COUNT(*) as count FROM user_dogs WHERE user_id = {$_SESSION['user_id']}";
        $dogsResult = mysqli_query($conn, $dogsQuery);
        if ($dogsResult && $row = mysqli_fetch_assoc($dogsResult)) {
            $stats['my_dogs'] = $row['count'];
        }
    }
}
?>

<style>
/* Mobile Bottom Navigation - Only visible on mobile */
.mobile-bottom-nav {
    display: none; /* Hidden by default on desktop */
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    z-index: 1050;
    height: 60px;
    border-top: 1px solid #dee2e6;
}

.bottom-nav-container {
    display: flex;
    justify-content: space-around;
    align-items: center;
    height: 100%;
    max-width: 100%;
    margin: 0 auto;
    padding: 0;
}

.bottom-nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #6c757d;
    padding: 8px 5px;
    position: relative;
    transition: all 0.3s ease;
    min-height: 60px;
}

.bottom-nav-item:hover {
    text-decoration: none;
    color: var(--theme-color, #ffa500);
    background-color: rgba(255, 165, 0, 0.05);
}

.bottom-nav-item.active {
    color: var(--theme-color, #ffa500);
    font-weight: 600;
}

.bottom-nav-item.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 3px;
    background-color: var(--theme-color, #ffa500);
    border-radius: 0 0 3px 3px;
}

.bottom-nav-icon {
    font-size: 20px;
    margin-bottom: 4px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bottom-nav-label {
    font-size: 11px;
    font-weight: 500;
    text-align: center;
    line-height: 1.2;
    margin: 0;
}

.bottom-nav-badge {
    position: absolute;
    top: -5px;
    right: -8px;
    background-color: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 5px;
    font-size: 9px;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Hamburger Menu Button */
.bottom-nav-menu-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: inherit;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* Show bottom nav only on mobile devices */
@media (max-width: 767.98px) {
    .mobile-bottom-nav {
        display: block;
    }
    
    /* Add padding to body to prevent content from being hidden behind bottom nav */
    body {
        padding-bottom: 70px;
    }
    
    /* Adjust container padding on mobile */
    .container,
    .content-container {
        padding-bottom: 80px !important;
    }
}

/* Extra animations for better UX */
@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

.bottom-nav-item:active {
    animation: bounce 0.3s ease;
}

/* Ripple effect on tap */
.bottom-nav-item::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background-color: rgba(255, 165, 0, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.4s ease, height 0.4s ease;
}

.bottom-nav-item:active::after {
    width: 80%;
    height: 80%;
}

/* Bottom Menu Overlay */
.bottom-menu-overlay {
    display: none; /* Hidden by default */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1040;
}

/* Bottom Menu Panel */
.bottom-menu-panel {
    display: none; /* Hidden by default */
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.2);
    z-index: 1050;
    border-top: 1px solid #dee2e6;
    max-height: 70%;
    overflow-y: auto;
    padding: 10px 0;
}

.menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 15px;
    margin-bottom: 10px;
}

.menu-header h2 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.menu-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #6c757d;
    font-size: 18px;
}

/* Menu Items */
.menu-items {
    list-style: none;
    padding: 0;
    margin: 0;
}

.menu-item {
    width: 100%;
}

.menu-link {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    text-decoration: none;
    color: #333;
    transition: background 0.3s ease;
}

.menu-link:hover {
    background: rgba(255, 165, 0, 0.1);
}

.menu-link.active {
    color: var(--theme-color, #ffa500);
    font-weight: 600;
}

.menu-link-badge {
    background-color: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 700;
    margin-left: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Divider */
.menu-divider {
    height: 1px;
    background: #dee2e6;
    margin: 10px 0;
}
</style>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
    <div class="bottom-nav-container">
        <!-- Dashboard/Home -->
        <a href="dashboard.php" class="bottom-nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="bottom-nav-icon">
                <i class="fas fa-home"></i>
            </div>
            <span class="bottom-nav-label">Home</span>
        </a>
        
        <!-- Browse Dogs -->
        <a href="browse_dogs.php" class="bottom-nav-item <?php echo ($current_page == 'browse_dogs.php') ? 'active' : ''; ?>">
            <div class="bottom-nav-icon">
                <i class="fas fa-search"></i>
            </div>
            <span class="bottom-nav-label">Browse</span>
        </a>
        
        <!-- My Dogs -->
        <a href="my_dogs.php" class="bottom-nav-item <?php echo ($current_page == 'my_dogs.php') ? 'active' : ''; ?>">
            <div class="bottom-nav-icon">
                <i class="fas fa-dog"></i>
                <?php if ($stats['my_dogs'] > 0): ?>
                    <span class="bottom-nav-badge"><?php echo $stats['my_dogs']; ?></span>
                <?php endif; ?>
            </div>
            <span class="bottom-nav-label">My Dogs</span>
        </a>
        
        <!-- My Orders -->
        <a href="my_orders.php" class="bottom-nav-item <?php echo ($current_page == 'my_orders.php') ? 'active' : ''; ?>">
            <div class="bottom-nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($stats['pending_orders'] > 0): ?>
                    <span class="bottom-nav-badge"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </div>
            <span class="bottom-nav-label">Orders</span>
        </a>
        
        <!-- Hamburger Menu - Now controls sidebar -->
        <div class="bottom-nav-item" id="bottomSidebarToggle">
            <button class="bottom-nav-menu-btn">
                <div class="bottom-nav-icon">
                    <i class="fas fa-bars"></i>
                </div>
                <span class="bottom-nav-label">Menu</span>
            </button>
        </div>
    </div>
</nav>

<!-- Hamburger Menu Overlay -->
<div class="bottom-menu-overlay" id="bottomMenuOverlay"></div>

<!-- Hamburger Menu Panel -->
<div class="bottom-menu-panel" id="bottomMenuPanel">
    <div class="menu-header">
        <h2>Menu</h2>
        <button class="menu-close-btn" id="bottomMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <ul class="menu-items">
        <!-- Main Navigation Items -->
        <li class="menu-item">
            <a href="dashboard.php" class="menu-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="menu-link-text">Dashboard</span>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="browse_dogs.php" class="menu-link <?php echo ($current_page == 'browse_dogs.php') ? 'active' : ''; ?>">
                <i class="fas fa-search"></i>
                <span class="menu-link-text">Browse Dogs</span>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="my_dogs.php" class="menu-link <?php echo ($current_page == 'my_dogs.php') ? 'active' : ''; ?>">
                <i class="fas fa-dog"></i>
                <span class="menu-link-text">My Dogs</span>
                <?php if ($stats['my_dogs'] > 0): ?>
                    <span class="menu-link-badge"><?php echo $stats['my_dogs']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="my_orders.php" class="menu-link <?php echo ($current_page == 'my_orders.php') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span class="menu-link-text">My Orders</span>
                <?php if ($stats['pending_orders'] > 0): ?>
                    <span class="menu-link-badge"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="menu-divider"></li>
        
        <!-- User Profile -->
        <li class="menu-item">
            <a href="profile.php" class="menu-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span class="menu-link-text">My Profile</span>
            </a>
        </li>
        
        <li class="menu-divider"></li>
        
        <!-- Logout -->
        <li class="menu-item">
            <a href="../logout.php" class="menu-link logout-link" onclick="return confirm('Are you sure you want to logout?');">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-link-text">Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
// Add touch feedback for better mobile experience
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.bottom-nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.95)';
        });
        
        item.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Bottom navigation hamburger controls the main sidebar
    const bottomSidebarToggle = document.getElementById('bottomSidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const bottomMenuOverlay = document.getElementById('bottomMenuOverlay');
    const bottomMenuPanel = document.getElementById('bottomMenuPanel');
    const bottomMenuClose = document.getElementById('bottomMenuClose');
    
    if (bottomSidebarToggle && sidebar) {
        bottomSidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Toggle sidebar active class
            sidebar.classList.toggle('active');
            
            // Toggle overlay if it exists
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
            
            // Store sidebar state
            const isSidebarActive = sidebar.classList.contains('active');
            try {
                localStorage.setItem('userSidebarActive', isSidebarActive);
            } catch (e) {
                console.warn("LocalStorage not available:", e);
            }
        });
    }
    
    // Open/close bottom menu panel
    if (bottomMenuOverlay && bottomMenuPanel && bottomMenuClose) {
        bottomSidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Show overlay and menu panel
            bottomMenuOverlay.style.display = 'block';
            bottomMenuPanel.style.display = 'block';
            
            // Close sidebar if open
            if (sidebar) {
                sidebar.classList.remove('active');
            }
        });
        
        bottomMenuClose.addEventListener('click', function() {
            // Hide overlay and menu panel
            bottomMenuOverlay.style.display = 'none';
            bottomMenuPanel.style.display = 'none';
        });
        
        // Close menu panel when clicking outside of it
        bottomMenuOverlay.addEventListener('click', function() {
            this.style.display = 'none';
            bottomMenuPanel.style.display = 'none';
        });
    }
});
</script>
