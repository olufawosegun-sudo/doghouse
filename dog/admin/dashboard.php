<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['admin_username'];

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
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
}

// Get dashboard statistics
$totalDogs = 0;
$recentListings = [];

// Count total dogs
$result = $conn->query("SELECT COUNT(*) as count FROM dogs");
if ($result) {
    $totalDogs = $result->fetch_assoc()['count'];
}

// Get recent dog listings with all relevant columns
$recentResult = $conn->query("SELECT dog_id, name, breed, age, trait, image_url, price, created_at, updated_at FROM dogs ORDER BY created_at DESC LIMIT 5");
if ($recentResult && $recentResult->num_rows > 0) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentListings[] = $row;
    }
}

// Calculate revenue (from sold dogs if you have a status field, otherwise total value)
$revenue = 0;
$revenueQuery = $conn->query("SELECT SUM(price) as total FROM dogs");
if ($revenueQuery) {
    $revenue = $revenueQuery->fetch_assoc()['total'] ?? 0;
}

// Calculate average price
$avgPrice = 0;
$avgPriceQuery = $conn->query("SELECT AVG(price) as avg FROM dogs");
if ($avgPriceQuery) {
    $avgPrice = $avgPriceQuery->fetch_assoc()['avg'] ?? 0;
}

// Get breed statistics
$breedStats = [];
$breedQuery = $conn->query("SELECT breed, COUNT(*) as count FROM dogs GROUP BY breed ORDER BY count DESC LIMIT 5");
if ($breedQuery && $breedQuery->num_rows > 0) {
    while ($row = $breedQuery->fetch_assoc()) {
        $breedStats[] = $row;
    }
}

// Get age statistics
$ageStats = [];
$ageQuery = $conn->query("SELECT age, COUNT(*) as count FROM dogs GROUP BY age ORDER BY count DESC LIMIT 5");
if ($ageQuery && $ageQuery->num_rows > 0) {
    while ($row = $ageQuery->fetch_assoc()) {
        $ageStats[] = $row;
    }
}

// Get price range statistics
$priceRanges = [
    '0-500' => 0,
    '501-1000' => 0,
    '1001-2000' => 0,
    '2001+' => 0
];

$priceQuery = $conn->query("SELECT 
    SUM(CASE WHEN price BETWEEN 0 AND 500 THEN 1 ELSE 0 END) as range1,
    SUM(CASE WHEN price BETWEEN 501 AND 1000 THEN 1 ELSE 0 END) as range2,
    SUM(CASE WHEN price BETWEEN 1001 AND 2000 THEN 1 ELSE 0 END) as range3,
    SUM(CASE WHEN price > 2000 THEN 1 ELSE 0 END) as range4
    FROM dogs");

if ($priceQuery && $priceResult = $priceQuery->fetch_assoc()) {
    $priceRanges['0-500'] = $priceResult['range1'];
    $priceRanges['501-1000'] = $priceResult['range2'];
    $priceRanges['1001-2000'] = $priceResult['range3'];
    $priceRanges['2001+'] = $priceResult['range4'];
}

// Additional statistics for enhanced visualizations
$monthlyData = [];
$monthlyQuery = $conn->query("SELECT 
                                MONTH(created_at) as month, 
                                COUNT(*) as dog_count,
                                SUM(price) as revenue 
                              FROM dogs 
                              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                              GROUP BY MONTH(created_at) 
                              ORDER BY month");

if ($monthlyQuery && $monthlyQuery->num_rows > 0) {
    while ($row = $monthlyQuery->fetch_assoc()) {
        $monthName = date('M', mktime(0, 0, 0, $row['month'], 10));
        $monthlyData[$monthName] = [
            'count' => $row['dog_count'],
            'revenue' => $row['revenue']
        ];
    }
}

// Fill in missing months with zeros
$lastSixMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $lastSixMonths[$month] = isset($monthlyData[$month]) ? $monthlyData[$month] : ['count' => 0, 'revenue' => 0];
}

// USER STATISTICS
// Check if users table exists and create if it doesn't
$checkUserTableQuery = "SHOW TABLES LIKE 'users'";
$userTableExists = $conn->query($checkUserTableQuery)->num_rows > 0;

if (!$userTableExists) {
    $createUserTableQuery = "CREATE TABLE IF NOT EXISTS users (
        user_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createUserTableQuery)) {
        echo "Error creating users table: " . $conn->error;
    }
}

// Get user statistics
$totalUsers = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $totalUsers = $result->fetch_assoc()['count'];
}

// Get new users in the last 30 days
$newUsers = 0;
$newUsersQuery = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($newUsersQuery) {
    $newUsers = $newUsersQuery->fetch_assoc()['count'];
}

// Calculate growth rate (if possible)
$userGrowthRate = 0;
if ($totalUsers > 0 && $newUsers > 0) {
    $userGrowthRate = ($newUsers / $totalUsers) * 100;
}

// Get the latest registered user
$latestUser = "";
$latestUserQuery = $conn->query("SELECT first_name, last_name, created_at FROM users ORDER BY created_at DESC LIMIT 1");
if ($latestUserQuery && $latestUserQuery->num_rows > 0) {
    $latestUserData = $latestUserQuery->fetch_assoc();
    $latestUser = $latestUserData['first_name'] . ' ' . $latestUserData['last_name'];
    $latestUserDate = date('M d', strtotime($latestUserData['created_at']));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doghouse Market - Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        :root {
            /* Doghouse Market Theme Colors with Orange (#ffa500) as primary */
            --primary: #ffa500;          /* Orange */
            --primary-light: #ffb733;    /* Lighter Orange */
            --primary-dark: #e69400;     /* Darker Orange */
            --secondary: #ff7e33;        /* Complementary Orange-Red */
            --tertiary: #ffcf40;         /* Gold Yellow */
            --success: #66bb6a;          /* Green */
            --info: #42a5f5;             /* Blue */
            --warning: #ffc107;          /* Amber */
            --danger: #f44336;           /* Red */
            --light: #f8f9fa;
            --dark: #343a40;
            --text-primary: #495057;
            --text-secondary: #868e96;
            --text-muted: #adb5bd;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
            
            /* Pawprint accent colors */
            --paw-accent: #fff3d6;       /* Very Light Orange */
            --paw-shadow: rgba(255,165,0,0.2);
            
            /* Layout dimensions remain the same */
            --sidebar-width: 260px;
            --header-height: 70px;
            --content-padding: 30px;
            --border-radius: 0.75rem;
            --card-radius: 1rem;
            --box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M50 50 C 55 45, 55 35, 50 30 S 35 25, 30 30 S 25 45, 30 50 S 45 55, 50 50' fill='%23fff3d6' fill-opacity='0.1' /%3E%3C/svg%3E");
            background-size: 100px 100px;
        }

        /* Doghouse Market Layout */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar with Doghouse Theme */
        .doghouse-sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
            border-right: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--box-shadow);
        }
        
        .doghouse-sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .doghouse-sidebar::-webkit-scrollbar-thumb {
            background-color: var(--primary-light);
            border-radius: 10px;
        }
        
        .sidebar-collapsed {
            transform: translateX(-100%);
        }

        /* Doghouse Brand Header */
        .doghouse-brand {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            height: var(--header-height);
        }
        
        .doghouse-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            margin-right: auto;
        }
        
        .doghouse-logo-icon {
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        
        .logo-house {
            position: absolute;
            width: 32px;
            height: 32px;
            background-color: var(--primary);
            border-radius: 5px;
            transform: rotate(45deg);
        }
        
        .logo-roof {
            position: absolute;
            top: -8px;
            width: 40px;
            height: 20px;
            background-color: var(--secondary);
            border-radius: 3px;
            clip-path: polygon(0 100%, 50% 0, 100% 100%);
        }
        
        .logo-door {
            position: absolute;
            bottom: 2px;
            width: 16px;
            height: 18px;
            background-color: white;
            border-radius: 8px 8px 0 0;
        }
        
        .doghouse-logo-text {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
        }
        
        .doghouse-logo-text span {
            color: var(--secondary);
        }

        /* Navigation Menu with Pawprints */
        .menu-section {
            padding: 20px 0;
        }
        
        .menu-heading {
            padding: 0 25px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--text-muted);
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        
        .menu-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .menu-item {
            margin: 5px 15px;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .menu-link:before {
            content: '';
            position: absolute;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--paw-accent);
            left: -2px;
            top: 50%;
            transform: translateY(-50%) scale(0);
            transition: transform 0.3s;
        }
        
        .menu-link:hover {
            background-color: var(--bg-light);
            color: var(--primary);
            text-decoration: none;
        }
        
        .menu-link:hover:before {
            transform: translateY(-50%) scale(1);
        }
        
        .menu-link.active {
            background-color: var(--primary);
            color: white;
        }
        
        .menu-link.active:hover {
            color: white;
        }
        
        .menu-link.active:before {
            background-color: white;
            transform: translateY(-50%) scale(1);
        }
        
        .menu-link i {
            width: 24px;
            font-size: 18px;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .menu-link.active i {
            color: white;
        }
        
        .menu-link .badge {
            margin-left: auto;
            background-color: var(--danger);
            font-size: 10px;
            padding: 3px 7px;
            border-radius: 20px;
        }

        /* Main Content Area */
        .main-area {
            margin-left: 0; /* Remove the margin-left */
            margin-top: 70px; /* Increase margin-top to match header height */
            flex: 1;
            transition: all 0.3s;
            min-height: calc(100vh - 70px); /* Adjust height to account for header */
            display: flex;
            flex-direction: column;
        }

        /* Top Header */
        .main-header {
            height: var(--header-height);
            background-color: white;
            display: flex;
            align-items: center;
            padding: 0 30px;
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            z-index: 990;
            transition: all 0.3s;
        }
        
        .sidebar-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            padding: 0;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 50%;
        }

        .sidebar-toggle:hover {
            background-color: rgba(255, 165, 0, 0.1);
        }

        .sidebar-toggle i {
            font-size: 20px;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover i {
            color: var(--primary);
        }

        /* Animation for sidebar toggle */
        .sidebar-toggle i {
            transform-origin: center;
            transition: transform 0.3s ease;
        }

        .sidebar-active .sidebar-toggle i {
            transform: rotate(180deg);
        }

        @media (max-width: 991.98px) {
            .sidebar-toggle {
                display: flex;
            }

            .doghouse-sidebar {
                transform: translateX(-100%);
                z-index: 1030;
            }

            .doghouse-sidebar.active {
                transform: translateX(0);
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }

            .main-area {
                margin-top: 60px; /* Reduce top margin on mobile to match smaller header */
                margin-left: 0 !important;
            }

            .main-header {
                left: 0;
            }
        }
        
        .header-title {
            font-weight: 700;
            font-size: 20px;
            color: var(--text-primary);
            margin: 0;
        }
        
        .header-tools {
            margin-left: auto;
            display: flex;
            align-items: center;
        }
        
        .header-search {
            position: relative;
            width: 240px;
            margin-right: 20px;
        }
        
        .header-search input {
            width: 100%;
            height: 40px;
            background-color: var(--bg-light);
            border: none;
            border-radius: 20px;
            padding: 0 20px 0 40px;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .header-search input:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--primary-light);
        }
        
        .header-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .header-user {
            display: flex;
            align-items: center;
        }
        
        .header-user-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        
        .header-user-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-primary);
            margin: 0;
        }
        
        .header-user-role {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Content Container */
        .content-container {
            padding: 30px var(--content-padding); /* Remove top padding calculation since we have margin-top */
            flex: 1;
            position: relative; /* Add position relative for proper stacking */
            z-index: 1; /* Ensure content is above other elements */
        }

        /* Dashboard Welcome */
        .welcome-header {
            margin-bottom: 30px;
        }
        
        .welcome-title {
            font-weight: 800;
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .welcome-subtitle {
            color: var(--text-secondary);
            font-size: 15px;
            margin: 0;
        }

        /* Doghouse Cards */
        .doghouse-card {
            background-color: white;
            border-radius: var(--card-radius);
            box-shadow: var(--box-shadow);
            transition: all 0.3s;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: none;
        }
        
        .doghouse-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .doghouse-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background-color: var(--primary);
            transform: scaleY(0);
            transition: transform 0.3s;
            transform-origin: top;
        }
        
        .doghouse-card:hover:before {
            transform: scaleY(1);
        }
        
        /* Stats Card */
        .stats-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .stats-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }
        
        .stats-card-link:hover {
            text-decoration: none;
            color: inherit;
        }
        
        .stats-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stats-card-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            margin-right: 15px;
            background-color: var(--bg-light);
            color: var(--primary);
            font-size: 24px;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card-icon:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 50%, rgba(255,255,255,0.3) 50%);
            transform: translateY(-100%) rotate(45deg);
            transition: transform 0.3s;
        }
        
        .doghouse-card:hover .stats-card-icon:after {
            transform: translateY(0) rotate(45deg);
        }
        
        .stats-card-red .stats-card-icon {
            background-color: rgba(255, 107, 107, 0.1);
            color: var(--primary);
        }
        
        .stats-card-orange .stats-card-icon {
            background-color: rgba(255, 165, 0, 0.1);
            color: var(--secondary);
        }
        
        .stats-card-yellow .stats-card-icon {
            background-color: rgba(255, 209, 102, 0.1);
            color: var(--tertiary);
        }
        
        .stats-card-green .stats-card-icon {
            background-color: rgba(116, 198, 157, 0.1);
            color: var(--success);
        }
        
        .stats-card-blue .stats-card-icon {
            background-color: rgba(66, 133, 244, 0.1);
            color: #4285f4;
        }
        
        .stats-card-title {
            font-weight: 800;
            font-size: 20px;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.2;
        }
        
        .stats-card-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }
        
        .stats-card-body {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        
        .stats-card-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
        }
        
        .stats-card-trend {
            display: inline-flex;
            align-items: center;
            font-size: 13px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .stats-card-trend.up {
            background-color: rgba(116, 198, 157, 0.1);
            color: var(--success);
        }
        
        .stats-card-trend.down {
            background-color: rgba(239, 71, 111, 0.1);
            color: var(--danger);
        }
        
        .stats-card-trend i {
            margin-right: 5px;
            font-size: 10px;
        }

        /* Action Cards */
        .action-card {
            padding: 25px;
            text-align: center;
            cursor: pointer;
        }
        
        .action-icon {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border-radius: 20px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            font-size: 28px;
            position: relative;
            overflow: hidden;
        }
        
        .action-icon:before {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            top: -10px;
            right: -10px;
            background-color: white;
            opacity: 0.2;
            border-radius: 50%;
        }
        
        .action-icon:after {
            content: '';
            position: absolute;
            width: 15px;
            height: 15px;
            bottom: 10px;
            left: 5px;
            background-color: white;
            opacity: 0.2;
            border-radius: 50%;
        }
        
        .action-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .action-desc {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .action-btn {
            background-color: var(--bg-light);
            color: var(--text-primary);
            border: none;
            border-radius: 20px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .doghouse-card:hover .action-btn {
            background-color: var(--primary);
            color: white;
        }

        /* Panel Card with Header */
        .panel-card .card-header {
            padding: 20px 25px;
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .panel-card .card-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
            margin: 0;
        }
        
        .panel-card .card-tools {
            display: flex;
        }
        
        .panel-card .card-body {
            padding: 0;
        }

        /* Doghouse Market Table */
        .doghouse-table {
            width: 100%;
        }
        
        .doghouse-table thead th {
            background-color: rgba(255,165,0,0.08);
            font-weight: 800;
            text-transform: uppercase;
            font-size: 12px;
            color: var(--text-primary);
            padding: 18px 20px;
            border: none;
            letter-spacing: 0.5px;
        }
        
        .doghouse-table tbody td {
            padding: 20px;
            vertical-align: middle;
            border-top: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .doghouse-table tbody tr:hover {
            background-color: rgba(255,165,0,0.05);
        }
        
        .dog-item {
            display: flex;
            align-items: center;
            padding: 8px;
            transition: all 0.3s ease;
            border-radius: 15px;
        }
        
        .dog-item:hover {
            background-color: rgba(255, 165, 0, 0.08);
        }
        
        .dog-thumb {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            object-fit: cover;
            margin-right: 20px;
            box-shadow: 0 6px 20px rgba(255, 165, 0, 0.25);
            border: 4px solid white;
            transition: all 0.4s ease;
            position: relative;
            z-index: 1;
        }
        
        .dog-thumb:hover {
            transform: scale(1.2) rotate(3deg);
            box-shadow: 0 10px 25px rgba(255, 165, 0, 0.4);
            border-color: var(--primary);
            z-index: 20;
        }
        
        .dog-image-container {
            position: relative;
            margin-right: 15px;
        }
        
        .dog-image-container:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 10px;
            width: 60%;
            height: 10px;
            background: rgba(0,0,0,0.1);
            filter: blur(4px);
            border-radius: 50%;
            z-index: 0;
            transition: all 0.4s ease;
        }
        
        .dog-image-container:hover:after {
            width: 70%;
            opacity: 0.7;
        }

        /* Enhanced Recent Dog Listings Container */
        .dog-listings-container {
            border-radius: var(--card-radius);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-top: 5px solid var(--primary);
            overflow: hidden;
        }

        .dog-listings-container .card-header {
            background-color: white;
            padding: 25px 30px;
            border-bottom: 2px solid var(--border-color);
        }

        .dog-listings-container .card-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
        }

        .dog-listings-container .card-title:before {
            content: '\f1b0';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            margin-right: 10px;
            font-size: 18px;
            color: var(--primary);
        }

        /* Empty State Enhancement */
        .empty-state {
            padding: 60px 30px;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 70px;
            color: var(--primary-light);
            margin-bottom: 20px;
            opacity: 0.7;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 25px;
            font-size: 16px;
        }

        /* Grid-based Dog Card Layout */
        .dog-grid-container {
            padding: 25px;
        }
        
        .dog-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-gap: 25px;
        }
        
        @media (max-width: 1200px) {
            .dog-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 767px) {
            .dog-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .dog-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid var(--border-color);
        }
        
        .dog-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(255, 165, 0, 0.2);
            border-color: var(--primary-light);
        }
        
        .dog-card-image {
            position: relative;
            width: 100%;
            padding-top: 75%; /* Aspect ratio 4:3 */
            overflow: hidden;
        }
        
        .dog-card-thumb {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.5s ease;
            border-bottom: 4px solid var(--primary);
        }
        
        .dog-card:hover .dog-card-thumb {
            transform: scale(1.08);
        }
        
        .dog-card-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .dog-card-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        
        .dog-card-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            text-transform: capitalize;
        }
        
        .dog-card-price {
            background-color: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            margin-left: 5px;
        }
        
        .dog-card-info {
            margin-bottom: 15px;
            flex-grow: 1;
        }
        
        .dog-card-info-item {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .dog-card-info-label {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-secondary);
            margin-right: 5px;
            width: 50px;
        }
        
        .dog-card-info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .dog-card-traits {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
            gap: 5px;
        }
        
        .dog-card-trait {
            background-color: var(--paw-accent);
            color: var(--primary-dark);
            border-radius: 10px;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .dog-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
        }
        
        .dog-card-date {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }
        
        .dog-card-date i {
            margin-right: 5px;
        }
        
        .dog-card-actions {
            display: flex;
            gap: 8px;
        }
        
        .dog-card-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-light);
            color: var(--text-primary);
            border: none;
            transition: all 0.3s;
        }
        
        .dog-card-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Badge */
        .dog-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--primary);
            color: white;
            border-radius: 15px;
            padding: 5px 10px;
            font-weight: 700;
            font-size: 12px;
            z-index: 5;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        /* Update mobile header and layout styles */
        @media (max-width: 767.98px) {
            .main-header {
                left: 0 !important;
                padding: 0 15px !important;
                height: 60px;
            }

            .main-area {
                margin-top: 60px; /* Reduce top margin on mobile to match smaller header */
                margin-left: 0 !important;
                width: 100% !important;
            }

            .content-container {
                padding: 15px !important; /* Simplify padding on mobile */
                width: 100% !important;
            }

            .header-title {
                font-size: 18px;
            }

            .header-tools {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php';?>
    <div class="app-wrapper">
  
        
        <!-- Main Content -->
        <main class="main-area" style="margin-left: 250px;">
            <!-- Header Bar -->

            
            <!-- Content Container -->
            <div class="content-container">
                <!-- Welcome Section -->
                <div class="welcome-header">
                    <h1 class="welcome-title">Welcome to Doghouse Market, <?php echo htmlspecialchars($username); ?>!</h1>
                    <p class="welcome-subtitle">Here's what's happening with your dog listings today.</p>
                </div>
                
                <!-- Stats Cards Row -->
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-4 fade-in-up delay-1">
                        <a href="manage_dogs.php" class="stats-card-link">
                            <div class="doghouse-card stats-card stats-card-red">
                                <div class="stats-card-header">
                                    <div class="stats-card-icon">
                                        <i class="fas fa-dog"></i>
                                    </div>
                                    <div>
                                        <h2 class="stats-card-title">Total Dogs</h2>
                                        <p class="stats-card-subtitle">All listings</p>
                                    </div>
                                </div>
                                <div class="stats-card-body">
                                    <h3 class="stats-card-value"><?php echo $totalDogs; ?></h3>
                                    <div class="stats-card-trend up">
                                        <i class="fas fa-arrow-up"></i>
                                        <span>12.5%</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- NEW CARD: User Statistics -->
                    <div class="col-lg-3 col-md-6 mb-4 fade-in-up delay-2">
                        <a href="manage_users.php" class="stats-card-link">
                            <div class="doghouse-card stats-card stats-card-blue">
                                <div class="stats-card-header">
                                    <div class="stats-card-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <h2 class="stats-card-title">Users</h2>
                                        <p class="stats-card-subtitle">Registered accounts</p>
                                    </div>
                                </div>
                                <div class="stats-card-body">
                                    <h3 class="stats-card-value"><?php echo $totalUsers; ?></h3>
                                    <?php if ($userGrowthRate > 0): ?>
                                    <div class="stats-card-trend up">
                                        <i class="fas fa-arrow-up"></i>
                                        <span><?php echo number_format($userGrowthRate, 1); ?>%</span>
                                    </div>
                                    <?php else: ?>
                                    <div class="stats-card-trend">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo $newUsers; ?> new</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4 fade-in-up delay-3">
                        <a href="reports.php" class="stats-card-link">
                            <div class="doghouse-card stats-card stats-card-orange">
                                <div class="stats-card-header">
                                    <div class="stats-card-icon">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <div>
                                        <h2 class="stats-card-title">Revenue</h2>
                                        <p class="stats-card-subtitle">This month</p>
                                    </div>
                                </div>
                                <div class="stats-card-body">
                                    <h3 class="stats-card-value">$<?php echo number_format($revenue, 0); ?></h3>
                                    <div class="stats-card-trend up">
                                        <i class="fas fa-arrow-up"></i>
                                        <span>8.2%</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4 fade-in-up delay-4">
                        <a href="manage_users.php?view=latest" class="stats-card-link">
                            <div class="doghouse-card stats-card stats-card-green">
                                <div class="stats-card-header">
                                    <div class="stats-card-icon">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div>
                                        <h2 class="stats-card-title">Latest User</h2>
                                        <p class="stats-card-subtitle">New registration</p>
                                    </div>
                                </div>
                                <div class="stats-card-body">
                                    <?php if (!empty($latestUser)): ?>
                                    <h3 class="stats-card-value" style="font-size: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($latestUser); ?></h3>
                                    <div class="stats-card-trend up">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo $latestUserDate; ?></span>
                                    </div>
                                    <?php else: ?>
                                    <h3 class="stats-card-value">N/A</h3>
                                    <div class="stats-card-trend">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span>No users</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h2 class="card-title">Quick Actions</h2>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-3 col-md-6">
                                        <div class="action-card">
                                            <div class="action-icon animate-bounce">
                                                <i class="fas fa-plus"></i>
                                            </div>
                                            <h3 class="action-title">Add New Dog</h3>
                                            <p class="action-desc">List a new dog in the marketplace</p>
                                            <a href="../add_dog.php" class="action-btn">Add Dog</a>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-3 col-md-6">
                                        <div class="action-card">
                                            <div class="action-icon">
                                                <i class="fas fa-search"></i>
                                            </div>
                                            <h3 class="action-title">Browse Dogs</h3>
                                            <p class="action-desc">View and manage your listings</p>
                                            <a href="manage_dogs.php" class="action-btn">View All</a>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-3 col-md-6">
                                        <div class="action-card">
                                            <div class="action-icon">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <h3 class="action-title">Sales Report</h3>
                                            <p class="action-desc">Check your sales performance</p>
                                            <a href="reports.php" class="action-btn">View Report</a>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-3 col-md-6">
                                        <div class="action-card">
                                            <div class="action-icon">
                                                <i class="fas fa-cog"></i>
                                            </div>
                                            <h3 class="action-title">Settings</h3>
                                            <p class="action-desc">Configure marketplace options</p>
                                            <a href="settings.php" class="action-btn">Settings</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Panels -->
                <div class="row">
                    <!-- Chart Panel -->
                    <div class="col-lg-8 mb-4">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h2 class="card-title">Monthly Statistics</h2>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div id="monthlyChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Breed Distribution -->
                    <div class="col-lg-4 mb-4">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h2 class="card-title">Top Breeds</h2>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($breedStats)): ?>
                                    <?php foreach ($breedStats as $breed): ?>
                                        <?php 
                                        $percentage = ($breed['count'] / $totalDogs) * 100;
                                        ?>
                                        <div class="breed-stat">
                                            <div class="breed-name">
                                                <div class="breed-icon">
                                                    <i class="fas fa-paw"></i>
                                                </div>
                                                <span class="breed-title"><?php echo htmlspecialchars($breed['breed']); ?></span>
                                            </div>
                                            <div class="breed-data">
                                                <span class="breed-count"><?php echo $breed['count']; ?></span>
                                                <span class="breed-percent"><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-paw"></i>
                                        <p>No breed statistics available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Price Distribution -->
                <div class="row">
                    <div class="col-lg-5 mb-4">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h2 class="card-title">Price Distribution</h2>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div id="priceChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-7 mb-4">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h2 class="card-title">Price Range Analysis</h2>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div id="priceBarChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add User Registration Chart -->
                <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h2 class="card-title">User Registrations</h2>
                            </div>
                            <div class="card-body">
                                <div id="userRegChart" style="height: 300px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar
        $(document).ready(function() {
            $('#menuToggler').click(function() {
                $('#sidebar').toggleClass('active');
                $('#overlay').toggleClass('active');
            });
            
            $('#overlay').click(function() {
                $('#sidebar').removeClass('active');
                $('#overlay').removeClass('active');
            });
            
            // Monthly Chart
            var monthlyOptions = {
                chart: {
                    type: 'area',
                    height: 350,
                    fontFamily: 'Nunito, sans-serif',
                    toolbar: {
                        show: false
                    },
                    zoom: {
                        enabled: false
                    }
                },
                colors: ['#ffa500', '#ff7e33'],
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                series: [{
                    name: 'Dogs Added',
                    data: [
                        <?php 
                        foreach ($lastSixMonths as $month => $data) {
                            echo $data['count'] . ', ';
                        }
                        ?>
                    ]
                }, {
                    name: 'Revenue ($)',
                    data: [
                        <?php 
                        foreach ($lastSixMonths as $month => $data) {
                            // Display in hundreds to keep the scale reasonable
                            echo round($data['revenue'] / 100) . ', ';
                        }
                        ?>
                    ]
                }],
                xaxis: {
                    categories: [
                        <?php 
                        foreach ($lastSixMonths as $month => $data) {
                            echo "'" . $month . "', ";
                        }
                        ?>
                    ],
                    labels: {
                        style: {
                            colors: '#868e96'
                        }
                    },
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#868e96'
                        }
                    }
                },
                tooltip: {
                    y: [{
                        formatter: function(val) {
                            return val + " dogs"
                        }
                    }, {
                        formatter: function(val) {
                            return "$" + (val * 100)
                        }
                    }]
                },
                grid: {
                    borderColor: '#e9ecef',
                    strokeDashArray: 4,
                    xaxis: {
                        lines: {
                            show: true
                        }
                    },
                    yaxis: {
                        lines: {
                            show: false
                        }
                    },
                    padding: {
                        top: 0,
                        right: 0,
                        bottom: 0,
                        left: 0
                    },
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'light',
                        type: 'vertical',
                        shadeIntensity: 0.5,
                        opacityFrom: 0.5,
                        opacityTo: 0.1,
                        stops: [0, 100]
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    offsetY: -30,
                    markers: {
                        width: 12,
                        height: 12,
                        radius: 6
                    },
                    itemMargin: {
                        horizontal: 10
                    }
                },
                markers: {
                    size: 5,
                    strokeWidth: 0,
                    hover: {
                        size: 7
                    }
                }
            };
            
            var monthlyChart = new ApexCharts(document.querySelector("#monthlyChart"), monthlyOptions);
            monthlyChart.render();
            
            // Price Distribution Chart
            var priceOptions = {
                chart: {
                    type: 'donut',
                    height: 350,
                    fontFamily: 'Nunito, sans-serif',
                    toolbar: {
                        show: false
                    }
                },
                colors: ['#ffa500', '#ff7e33', '#ffcf40', '#66bb6a'],
                series: [
                    <?php 
                    foreach ($priceRanges as $count) {
                        echo $count . ', ';
                    }
                    ?>
                ],
                labels: [
                    <?php 
                    foreach ($priceRanges as $range => $count) {
                        echo "'$" . $range . "', ";
                    }
                    ?>
                ],
                legend: {
                    position: 'bottom',
                    markers: {
                        width: 12,
                        height: 12,
                        radius: 6
                    }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '55%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '14px',
                                    fontWeight: 600,
                                    color: '#495057'
                                },
                                value: {
                                    show: true,
                                    fontSize: '22px',
                                    fontWeight: 700,
                                    color: '#495057',
                                    formatter: function(val) {
                                        return val + " dogs"
                                    }
                                },
                                total: {
                                    show: true,
                                    label: 'Total Dogs',
                                    fontSize: '14px',
                                    fontWeight: 600,
                                    color: '#495057',
                                    formatter: function(w) {
                                        return '<?php echo $totalDogs; ?>'
                                    }
                                }
                            }
                        }
                    }
                }
            };
            
            var priceChart = new ApexCharts(document.querySelector("#priceChart"), priceOptions);
            priceChart.render();
            
            // Price Bar Chart
            var priceBarOptions = {
                chart: {
                    type: 'bar',
                    height: 350,
                    fontFamily: 'Nunito, sans-serif',
                    toolbar: {
                        show: false
                    }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 10,
                        columnWidth: '50%',
                        dataLabels: {
                            position: 'top',
                        },
                    }
                },
                colors: ['#ffa500', '#ff7e33', '#ffcf40', '#66bb6a'],
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return val + " dogs";
                    },
                    offsetY: -20,
                    style: {
                        fontSize: '12px',
                        fontWeight: 600,
                        colors: ["#495057"]
                    }
                },
                series: [{
                    name: 'Dogs',
                    data: [
                        <?php 
                        foreach ($priceRanges as $count) {
                            echo $count . ', ';
                        }
                        ?>
                    ]
                }],
                xaxis: {
                    categories: [
                        <?php 
                        foreach ($priceRanges as $range => $count) {
                            echo "'$" . $range . "', ";
                        }
                        ?>
                    ],
                    labels: {
                        style: {
                            colors: '#868e96',
                            fontSize: '12px',
                            fontWeight: 500
                        }
                    },
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    }
                },
                yaxis: {
                    labels: {
                        show: false
                    },
                    axisBorder: {
                        show: false
                    }
                },
                grid: {
                    show: false
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return val + " dogs"
                        }
                    }
                }
            };
            
            var priceBarChart = new ApexCharts(document.querySelector("#priceBarChart"), priceBarOptions);
            priceBarChart.render();
            
            // User Registration Chart
            <?php
            // Get monthly user registration data
            $months = [];
            $userData = [];
            $userMonthlyQuery = $conn->query("
                SELECT 
                    DATE_FORMAT(created_at, '%b') AS month,
                    COUNT(*) as count
                FROM users
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%b'), MONTH(created_at)
                ORDER BY MONTH(created_at)
            ");
            
            if ($userMonthlyQuery && $userMonthlyQuery->num_rows > 0) {
                while ($row = $userMonthlyQuery->fetch_assoc()) {
                    $months[] = $row['month'];
                    $userData[] = $row['count'];
                }
            }
            ?>
            
            var userOptions = {
                chart: {
                    type: 'bar',
                    height: 300,
                    fontFamily: 'Nunito, sans-serif',
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Registrations',
                    data: [<?php echo !empty($userData) ? implode(',', $userData) : '0'; ?>]
                }],
                xaxis: {
                    categories: [<?php echo !empty($months) ? "'" . implode("','", $months) . "'" : "'No Data'"; ?>]
                },
                colors: ['#4285f4'],
                plotOptions: {
                    bar: {
                        borderRadius: 5,
                        columnWidth: '50%',
                    }
                },
                dataLabels: {
                    enabled: false
                },
                grid: {
                    borderColor: '#e9ecef',
                    strokeDashArray: 4,
                    yaxis: {
                        lines: {
                            show: true
                        }
                    }
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (val) {
                            return val + " users";
                        }
                    }
                }
            };

            var userRegChart = new ApexCharts(document.querySelector("#userRegChart"), userOptions);
            userRegChart.render();
        });
    </script>
    
    <!-- Enhanced Image preview functionality -->
    <div class="dog-image-preview" id="imagePreview">
        <div class="close-preview" id="closePreview">
            <i class="fas fa-times"></i>
        </div>
        <img id="previewImage" src="" alt="Dog Preview">
        <div class="preview-dog-name" id="previewDogName"></div>
    </div>
    
    <script>
        $(document).ready(function() {
            // Enhanced Image preview functionality
            $('.dog-thumb').click(function() {
                var imgSrc = $(this).attr('src');
                var dogName = $(this).data('name');
                var dogBreed = $(this).data('breed');
                
                $('#previewImage').attr('src', imgSrc);
                $('#previewDogName').text(dogName + ' - ' + dogBreed);
                $('#imagePreview').addClass('active');
            });
            
            $('#closePreview').click(function() {
                $('#imagePreview').removeClass('active');
            });
            
            $(document).keyup(function(e) {
                if (e.key === "Escape") {
                    $('#imagePreview').removeClass('active');
                }
            });
            
            // Close preview when clicking outside the image
            $('#imagePreview').click(function(e) {
                if (e.target.id === 'imagePreview') {
                    $('#imagePreview').removeClass('active');
                }
            });
        });
    </script>
    
    <script>
        // Sidebar Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.doghouse-sidebar');
            const body = document.body;

            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                body.classList.toggle('sidebar-active');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && 
                    !sidebarToggle.contains(e.target) && 
                    window.innerWidth < 992) {
                    sidebar.classList.remove('active');
                    body.classList.remove('sidebar-active');
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('active');
                    body.classList.remove('sidebar-active');
                }
            });
        });
    </script>
</body>
</html>
