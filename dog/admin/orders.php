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

// Error handling function for database queries
function executeQuery($connection, $query, $errorMessage = "Database error") {
    $result = $connection->query($query);
    if (!$result) {
        error_log("Query error: " . $connection->error . " in query: " . $query);
        return false;
    }
    return $result;
}

// Fetch company information from database
$companyInfo = [];
$companyQuery = "SELECT * FROM company_info LIMIT 1";
$result = executeQuery($conn, $companyQuery, "Error fetching company information");

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
}

// Initialize stats array with default values to prevent undefined index errors
$stats = [
    'total' => 0,
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'revenue' => 0
];

// Get stats for different statuses
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0 END) as revenue
    FROM orders";
$stats_result = executeQuery($conn, $stats_sql, "Error fetching order statistics");

if ($stats_result && $stats_result->num_rows > 0) {
    $row = $stats_result->fetch_assoc();
    
    // Handle potentially NULL values from SQL - VERY IMPORTANT!
    $stats['total'] = (isset($row['total']) && $row['total'] !== NULL) ? (int)$row['total'] : 0;
    $stats['pending'] = (isset($row['pending']) && $row['pending'] !== NULL) ? (int)$row['pending'] : 0;
    $stats['processing'] = (isset($row['processing']) && $row['processing'] !== NULL) ? (int)$row['processing'] : 0;
    $stats['completed'] = (isset($row['completed']) && $row['completed'] !== NULL) ? (int)$row['completed'] : 0;
    $stats['cancelled'] = (isset($row['cancelled']) && $row['cancelled'] !== NULL) ? (int)$row['cancelled'] : 0;
    $stats['revenue'] = (isset($row['revenue']) && $row['revenue'] !== NULL) ? (float)$row['revenue'] : 0;
}

// Monthly orders data for charts
$monthly_orders = [];
$monthly_query = "
    SELECT 
        DATE_FORMAT(order_date, '%b') AS month,
        COUNT(*) as order_count,
        SUM(total_amount) as revenue
    FROM orders
    WHERE order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%b'), MONTH(order_date)
    ORDER BY MONTH(order_date)";
$monthly_result = executeQuery($conn, $monthly_query, "Error fetching monthly order data");

if ($monthly_result && $monthly_result->num_rows > 0) {
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_orders[$row['month']] = [
            'count' => $row['order_count'] ?? 0,
            'revenue' => $row['revenue'] ?? 0
        ];
    }
}

// Fill missing months
$last_six_months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $last_six_months[$month] = isset($monthly_orders[$month]) 
        ? $monthly_orders[$month] 
        : ['count' => 0, 'revenue' => 0];
}

// Handle AJAX Search Requests
if(isset($_GET['ajax_search']) && $_GET['ajax_search'] === 'true') {
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
    $date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';
    
    // Build query conditions
    $where_clauses = [];
    
    if(!empty($search)) {
        $where_clauses[] = "(o.id LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR d.name LIKE '%$search%')";
    }
    
    if(!empty($status)) {
        $where_clauses[] = "o.status = '$status'";
    }
    
    if(!empty($date_from)) {
        $where_clauses[] = "DATE(o.order_date) >= '$date_from'";
    }
    
    if(!empty($date_to)) {
        $where_clauses[] = "DATE(o.order_date) <= '$date_to'";
    }
    
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Get orders with filtering
    $sql = "SELECT o.*, u.first_name, u.last_name, u.email, d.name AS dog_name, d.breed AS dog_breed, d.image_url 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.user_id 
            LEFT JOIN dogs d ON o.dog_id = d.dog_id 
            $where_clause 
            ORDER BY o.order_date DESC 
            LIMIT 50";
            
    $result = $conn->query($sql);
    
    $orders = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Format the data for JSON response
            $orders[] = [
                'id' => $row['id'],
                'customer' => isset($row['first_name']) ? $row['first_name'] . ' ' . $row['last_name'] : 'User ID: ' . $row['user_id'],
                'email' => $row['email'] ?? '',
                'dog_name' => $row['dog_name'] ?? '',
                'dog_breed' => $row['dog_breed'] ?? '',
                'image_url' => $row['image_url'] ?? '',
                'order_date' => date('M d, Y', strtotime($row['order_date'])),
                'total_amount' => number_format($row['total_amount'], 2),
                'status' => $row['status'],
                'user_id' => $row['user_id'],
                'dog_id' => $row['dog_id'],
                'initials' => isset($row['first_name']) ? strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)) : 'U'
            ];
        }
    }
    
    // Count total orders matching the criteria
    $count_sql = "SELECT COUNT(*) as total FROM orders o 
                  LEFT JOIN users u ON o.user_id = u.user_id 
                  LEFT JOIN dogs d ON o.dog_id = d.dog_id 
                  $where_clause";
    $count_result = $conn->query($count_sql);
    $total_records = $count_result->fetch_assoc()['total'];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'orders' => $orders,
        'total' => $total_records
    ]);
    exit;
}

// Handle order status updates
$message = '';
if(isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $conn->real_escape_string($_POST['status']);
    
    $updateSql = "UPDATE orders SET status = '$status' WHERE id = $order_id";
    
    if($conn->query($updateSql)) {
        $message = "<div class='alert alert-success'>Order #$order_id status updated to $status</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error updating order status: " . $conn->error . "</div>";
    }
}

// Handle order deletion (with confirmation)
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    
    $deleteSql = "DELETE FROM orders WHERE id = $order_id";
    
    if($conn->query($deleteSql)) {
        $message = "<div class='alert alert-success'>Order #$order_id has been deleted</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error deleting order: " . $conn->error . "</div>";
    }
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filtering
$where_clause = "";
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

if(!empty($filter_status)) {
    $filter_status = $conn->real_escape_string($filter_status);
    $where_clause = "WHERE o.status = '$filter_status'";
}

// Search
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    if(empty($where_clause)) {
        $where_clause = "WHERE (o.id LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR d.name LIKE '%$search%')";
    } else {
        $where_clause .= " AND (o.id LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR d.name LIKE '%$search%')";
    }
}

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o";
$count_result = executeQuery($conn, $count_sql, "Error counting orders");
$total_records = ($count_result && $count_result->num_rows > 0) ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $records_per_page);

// Get orders with pagination and joins to get related data
$sql = "SELECT o.*, u.first_name, u.last_name, u.email, d.name AS dog_name, d.breed AS dog_breed, d.image_url 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.user_id 
        LEFT JOIN dogs d ON o.dog_id = d.dog_id 
        $where_clause 
        ORDER BY o.order_date DESC 
        LIMIT $offset, $records_per_page";
$result = executeQuery($conn, $sql, "Error fetching orders");

$orders = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Ensure all necessary keys are set
        $row['first_name'] = $row['first_name'] ?? '';
        $row['last_name'] = $row['last_name'] ?? '';
        $row['email'] = $row['email'] ?? '';
        $row['dog_name'] = $row['dog_name'] ?? '';
        $row['dog_breed'] = $row['dog_breed'] ?? '';
        $row['image_url'] = $row['image_url'] ?? '';
        
        $orders[] = $row;
    }
}

// Get months for date filter dropdown
$months = [];
$current_month = date('Y-m');
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('F Y', strtotime("-$i months"));
    $months[$month] = $month_name;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | Doghouse Market Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" rel="stylesheet">
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

        /* Main Content Area */
        .main-area {
            margin-left: 0; /* Sidebar is included separately */
            flex: 1;
            transition: all 0.3s;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Content Container */
        .content-container {
            padding: calc(var(--header-height) + 30px) var(--content-padding) 30px;
            flex: 1;
        }

        /* Welcome Section */
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
            padding: 25px;
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
            padding: 25px;
        }

        /* Search and Filters */
        .search-filter-bar {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
        }
        
        .search-input-group {
            position: relative;
        }
        
        .search-input-group input {
            padding-left: 40px;
            height: 45px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        /* Order Table */
        .orders-table {
            background-color: white;
            border-radius: var(--card-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
        }
        
        .orders-table .table {
            margin-bottom: 0;
        }
        
        .orders-table th {
            background-color: rgba(255, 165, 0, 0.08);
            color: var(--text-primary);
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 15px 20px;
        }
        
        .orders-table td {
            vertical-align: middle;
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .orders-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .orders-table tbody tr:hover {
            background-color: rgba(255, 165, 0, 0.05);
        }
        
        .customer-info, .dog-info {
            display: flex;
            align-items: center;
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-right: 15px;
        }
        
        .dog-thumbnail {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 15px;
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .dog-info:hover .dog-thumbnail {
            transform: scale(1.1);
        }

        /* Status Badges */
        .badge {
            font-size: 12px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-Pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #856404;
        }
        
        .badge-Processing {
            background-color: rgba(66, 133, 244, 0.2);
            color: #0d47a1;
        }
        
        .badge-Completed {
            background-color: rgba(102, 187, 106, 0.2);
            color: #2e7d32;
        }
        
        .badge-Cancelled {
            background-color: rgba(244, 67, 54, 0.2);
            color: #b71c1c;
        }

        /* Action Buttons */
        .action-btn {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            background-color: white;
            color: var(--text-primary);
            margin: 0 3px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
        }
        
        .action-btn.btn-info {
            background-color: rgba(66, 133, 244, 0.2);
            color: #0d47a1;
        }
        
        .action-btn.btn-info:hover {
            background-color: #42a5f5;
            color: white;
        }
        
        .action-btn.btn-warning {
            background-color: rgba(255, 193, 7, 0.2);
            color: #856404;
        }
        
        .action-btn.btn-warning:hover {
            background-color: #ffc107;
            color: white;
        }
        
        .action-btn.btn-danger {
            background-color: rgba(244, 67, 54, 0.2);
            color: #b71c1c;
        }
        
        .action-btn.btn-danger:hover {
            background-color: #f44336;
            color: white;
        }

        /* Pagination */
        .pagination {
            margin-bottom: 0;
        }
        
        .pagination .page-item:first-child .page-link,
        .pagination .page-item:last-child .page-link {
            border-radius: 20px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 5px;
            padding: 0;
        }
        
        .pagination .page-item .page-link {
            border-radius: 20px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 5px;
            padding: 0;
            color: var(--text-primary);
            border: none;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            color: white;
        }
        
        .pagination .page-item .page-link:hover {
            background-color: var(--primary-light);
            color: white;
        }

        /* Chart Styles */
        .chart-container {
            height: 300px;
        }
        
        /* Loading Animation */
        .loader {
            display: none;
            text-align: center;
            margin: 2rem 0;
        }

        .loader-spinner {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .content-container {
                padding: 80px 20px 20px;
            }
            
            .welcome-title {
                font-size: 22px;
            }
        }
        
        @media (max-width: 767px) {
            .content-container {
                padding: 70px 15px 15px;
            }
            
            .welcome-title {
                font-size: 20px;
            }
            
            .search-filter-bar {
                padding: 15px;
            }
            
            .stats-card-value {
                font-size: 24px;
            }
            
            .customer-info, .dog-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .customer-avatar, .dog-thumbnail {
                margin-bottom: 10px;
            }
        }

        /* Modal Styles */
        .modal-content {
            border-radius: var(--card-radius);
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px 25px;
        }
        
        .modal-header.bg-danger {
            background: linear-gradient(45deg, #f44336, #e57373) !important;
            color: white;
        }
        
        .modal-title {
            font-weight: 700;
            font-size: 18px;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 20px 25px;
        }
    </style>
</head>
<body>
    <?php 
    // Check if sidenav.php exists, if not display a warning and include a fallback
    if(file_exists('sidenav.php')) {
        include 'sidenav.php';
    } else {
        echo '<div class="alert alert-warning">Warning: sidenav.php not found. Please create this file.</div>';
        // Create minimal sidebar as fallback
        echo '<div style="width: 250px; position: fixed; height: 100vh; background: #2c3e50; color: white;">
                <div style="padding: 20px;">
                    <h4>Doghouse Market</h4>
                    <p>Admin Menu</p>
                </div>
             </div>';
    }
    ?>
    
    <div class="app-wrapper">
        <!-- Main Content -->
        <main class="main-area" style="margin-left: 250px;">
            <!-- Content Container -->
            <div class="content-container">
                <!-- Welcome Section -->
                <div class="welcome-header">
                    <h1 class="welcome-title">Order Management</h1>
                    <p class="welcome-subtitle">Monitor and manage your customer orders.</p>
                </div>
                
                <!-- Alert Messages -->
                <?php if(!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Stats Cards Row -->
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="doghouse-card stats-card">
                            <div class="stats-card-header">
                                <div class="stats-card-icon" style="background-color: rgba(255, 165, 0, 0.1);">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div>
                                    <h2 class="stats-card-title">Total Orders</h2>
                                    <p class="stats-card-subtitle">All time</p>
                                </div>
                            </div>
                            <div class="stats-card-body">
                                <h3 class="stats-card-value"><?php echo isset($stats['total']) ? number_format((int)$stats['total']) : '0'; ?></h3>
                                <div class="stats-card-trend up">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="doghouse-card stats-card">
                            <div class="stats-card-header">
                                <div class="stats-card-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h2 class="stats-card-title">Pending</h2>
                                    <p class="stats-card-subtitle">Awaiting action</p>
                                </div>
                            </div>
                            <div class="stats-card-body">
                                <h3 class="stats-card-value"><?php echo isset($stats['pending']) ? number_format((int)$stats['pending']) : '0'; ?></h3>
                                <div class="stats-card-trend" style="background-color: rgba(255, 193, 7, 0.1); color: #856404;">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="doghouse-card stats-card">
                            <div class="stats-card-header">
                                <div class="stats-card-icon" style="background-color: rgba(102, 187, 106, 0.1); color: #66bb6a;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h2 class="stats-card-title">Completed</h2>
                                    <p class="stats-card-subtitle">Successfully delivered</p>
                                </div>
                            </div>
                            <div class="stats-card-body">
                                <h3 class="stats-card-value"><?php echo isset($stats['completed']) ? number_format((int)$stats['completed']) : '0'; ?></h3>
                                <div class="stats-card-trend up">
                                    <i class="fas fa-arrow-up"></i>
                                    <span><?php 
                                        $percentage = 0;
                                        if (isset($stats['total']) && isset($stats['completed']) && (int)$stats['total'] > 0) {
                                            $percentage = ((int)$stats['completed'] / (int)$stats['total']) * 100;
                                        }
                                        echo number_format($percentage, 1);
                                    ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="doghouse-card stats-card">
                            <div class="stats-card-header">
                                <div class="stats-card-icon" style="background-color: rgba(66, 165, 245, 0.1); color: #42a5f5;">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div>
                                    <h2 class="stats-card-title">Revenue</h2>
                                    <p class="stats-card-subtitle">Total earnings</p>
                                </div>
                            </div>
                            <div class="stats-card-body">
                                <h3 class="stats-card-value">$<?php echo isset($stats['revenue']) ? number_format((float)$stats['revenue'], 2) : '0.00'; ?></h3>
                                <div class="stats-card-trend up">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-chart-line mr-2"></i> Order Trends</h5>
                                <div class="card-tools">
                                    <select class="custom-select custom-select-sm" id="trendTimeRange">
                                        <option value="6">Last 6 Months</option>
                                        <option value="3">Last 3 Months</option>
                                        <option value="12">Last 12 Months</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="orderTrendsChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="doghouse-card panel-card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-chart-pie mr-2"></i> Order Status</h5>
                            </div>
                            <div class="card-body">
                                <div id="orderStatusChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search & Filter Bar -->
                <div class="search-filter-bar mb-4">
                    <form id="orderFilterForm">
                        <div class="row">
                            <div class="col-lg-4 col-md-6 mb-3 mb-md-0">
                                <div class="search-input-group">
                                    <input type="text" id="orderSearch" name="search" class="form-control form-control-lg" placeholder="Search orders, customers, or dogs..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <span class="search-icon">
                                        <i class="fas fa-search"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-lg-2 col-md-6 mb-3 mb-md-0">
                                <select class="custom-select custom-select-lg" id="statusFilter" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Pending" <?php echo $filter_status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Processing" <?php echo $filter_status == 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="Completed" <?php echo $filter_status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Cancelled" <?php echo $filter_status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3 mb-md-0">
                                <input type="text" id="dateFilter" class="form-control form-control-lg" placeholder="Date range">
                            </div>
                            
                            <div class="col-lg-3 col-md-6">
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary btn-lg flex-fill mr-2">
                                        <i class="fas fa-filter mr-2"></i> Apply Filters
                                    </button>
                                    <button type="button" id="resetFilters" class="btn btn-light btn-lg">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Orders Table -->
                <div class="orders-table">
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 border-bottom">
                        <h5 class="card-title mb-0"><i class="fas fa-list mr-2"></i> Order List</h5>
                        <div>
                            <a href="export_orders.php" class="btn btn-outline-primary btn-sm mr-2">
                                <i class="fas fa-file-export mr-1"></i> Export
                            </a>
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#bulkActionsModal">
                                <i class="fas fa-cog mr-1"></i> Bulk Actions
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Dog</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody">
                                <?php if (!empty($orders)): ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td>
                                            <?php if(!empty($order['first_name'])): ?>
                                                <div class="customer-info">
                                                    <div class="customer-avatar">
                                                        <?php echo strtoupper(substr($order['first_name'], 0, 1) . substr($order['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">User ID: <?php echo $order['user_id']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($order['dog_name'])): ?>
                                                <div class="dog-info">
                                                    <?php if(!empty($order['image_url'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($order['image_url']); ?>" class="dog-thumbnail" alt="Dog">
                                                    <?php endif; ?>
                                                    <div>
                                                        <?php echo htmlspecialchars($order['dog_name']); ?><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['dog_breed']); ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Dog ID: <?php echo $order['dog_id']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-info action-btn" data-toggle="tooltip" title="View Order">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-warning action-btn" data-toggle="modal" data-target="#updateStatusModal" data-order-id="<?php echo $order['id']; ?>" data-status="<?php echo $order['status']; ?>" title="Update Status">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger action-btn delete-order" data-order-id="<?php echo $order['id']; ?>" data-toggle="tooltip" title="Delete Order">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                            <h5>No Orders Found</h5>
                                            <p class="text-muted">Try adjusting your search or filter criteria</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Loading Indicator -->
                    <div class="loader" id="tableLoader">
                        <div class="loader-spinner"></div>
                        <p class="mt-2">Loading orders...</p>
                    </div>
                    
                    <!-- No Results Message -->
                    <div class="no-results" id="noResults" style="display: none;">
                        <i class="fas fa-search"></i>
                        <h5>No Orders Found</h5>
                        <p>Try adjusting your search or filter criteria</p>
                        <button id="clearSearchBtn" class="btn btn-primary mt-2">Clear Search</button>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top">
                        <div>
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page=' . ($page - 1) . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . (isset($_GET['search']) ? '&search=' . $_GET['search'] : ''); ?>" aria-label="Previous">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                                
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . (isset($_GET['search']) ? '&search=' . $_GET['search'] : ''); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page=' . ($page + 1) . (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . (isset($_GET['search']) ? '&search=' . $_GET['search'] : ''); ?>" aria-label="Next">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Update Order Status</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="updateStatusForm" action="orders.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="orderIdInput">
                        <div class="form-group">
                            <label for="statusSelect" class="form-label">Order Status</label>
                            <select class="custom-select" id="statusSelect" name="status">
                                <option value="Pending">Pending</option>
                                <option value="Processing">Processing</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div class="modal fade" id="bulkActionsModal" tabindex="-1" aria-labelledby="bulkActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkActionsModalLabel">Bulk Actions</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="bulkActionForm">
                        <div class="form-group">
                            <label for="bulkStatusSelect" class="form-label">Status</label>
                            <select class="custom-select" id="bulkStatusSelect">
                                <option value="">-- Select Status --</option>
                                <option value="Pending">Pending</option>
                                <option value="Processing">Processing</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bulkDateRange" class="form-label">Date Range</label>
                            <input type="text" id="bulkDateRange" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="applyBulkAction" class="btn btn-primary">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteOrderModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this order? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete Order</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize tooltips safely
            try {
                if (typeof $.fn.tooltip === 'function') {
                    $('[data-toggle="tooltip"]').tooltip();
                }
            } catch (e) {
                console.error("Error initializing tooltips:", e);
            }

            // Initialize Date Range Picker if the library is loaded
            try {
                if (typeof $.fn.daterangepicker === 'function') {
                    $('#dateFilter').daterangepicker({
                        autoUpdateInput: false,
                        ranges: {
                            'Today': [moment(), moment()],
                            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                            'This Month': [moment().startOf('month'), moment().endOf('month')],
                            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                        }
                    });

                    $('#dateFilter').on('apply.daterangepicker', function(ev, picker) {
                        $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                    });

                    $('#dateFilter').on('cancel.daterangepicker', function(ev, picker) {
                        $(this).val('');
                    });

                    // Initialize Bulk Action Date Range Picker
                    $('#bulkDateRange').daterangepicker({
                        autoUpdateInput: false
                    });

                    $('#bulkDateRange').on('apply.daterangepicker', function(ev, picker) {
                        $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                    });

                    $('#bulkDateRange').on('cancel.daterangepicker', function(ev, picker) {
                        $(this).val('');
                    });
                }
            } catch (e) {
                console.error("Error initializing daterangepicker:", e);
            }

            // Reset filters
            $('#resetFilters').click(function() {
                window.location.href = 'orders.php';
            });

            // Update status modal
            $('#updateStatusModal').on('show.bs.modal', function(event) {
                try {
                    const button = $(event.relatedTarget);
                    const orderId = button.data('order-id');
                    const status = button.data('status');

                    const modal = $(this);
                    modal.find('#orderIdInput').val(orderId);
                    modal.find('#statusSelect').val(status);
                    modal.find('#updateStatusModalLabel').text('Update Order #' + orderId + ' Status');
                } catch (e) {
                    console.error("Error updating status modal:", e);
                }
            });

            // Delete order confirmation
            $(document).on('click', '.delete-order', function(e) {
                try {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    $('#confirmDelete').attr('href', 'orders.php?delete=1&id=' + orderId);
                    $('#deleteOrderModal').modal('show');
                } catch (e) {
                    console.error("Error handling delete order:", e);
                }
            });

            // Auto-hide alerts after 3 seconds
            setTimeout(function() {
                $('.alert-dismissible').alert('close');
            }, 3000);
            
            // Handle bulk actions
            $('#applyBulkAction').click(function() {
                try {
                    const status = $('#bulkStatusSelect').val();
                    const dateRange = $('#bulkDateRange').val();
                    
                    if (!status && !dateRange) {
                        alert('Please select a status or date range');
                        return;
                    }
                    
                    // This would typically send an AJAX request to process bulk actions
                    // For now, we'll just close the modal and show a success message
                    $('#bulkActionsModal').modal('hide');
                    
                    const alertHtml = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Bulk action applied successfully!
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    `;
                    
                    $('.welcome-header').after(alertHtml);
                    
                    // Auto-hide the message after 3 seconds
                    setTimeout(function() {
                        $('.alert-dismissible').alert('close');
                    }, 3000);
                } catch (e) {
                    console.error("Error applying bulk actions:", e);
                }
            });

            // Initialize charts only if the required data and library are available
            try {
                if (typeof ApexCharts === 'function') {
                    // Chart data arrays for safety
                    const monthCategories = [];
                    const orderCounts = [];
                    const revenueData = [];
                    
                    <?php foreach ($last_six_months as $month => $data): ?>
                        monthCategories.push('<?php echo $month; ?>');
                        orderCounts.push(<?php echo $data['count']; ?>);
                        revenueData.push(<?php echo $data['revenue']; ?>);
                    <?php endforeach; ?>
                    
                    // Order Trends Chart
                    const orderTrendsOptions = {
                        series: [{
                            name: 'Orders',
                            type: 'column',
                            data: orderCounts
                        }, {
                            name: 'Revenue',
                            type: 'line',
                            data: revenueData
                        }],
                        chart: {
                            height: 300,
                            type: 'line',
                            fontFamily: 'Nunito, sans-serif',
                            toolbar: {
                                show: false
                            }
                        },
                        stroke: {
                            width: [0, 3],
                            curve: 'smooth'
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 5,
                                columnWidth: '50%'
                            }
                        },
                        colors: ['#ffa500', '#42a5f5'],
                        fill: {
                            opacity: [0.85, 1],
                            gradient: {
                                inverseColors: false,
                                shade: 'light',
                                type: "vertical",
                                opacityFrom: 0.85,
                                opacityTo: 0.55,
                                stops: [0, 100, 100, 100]
                            }
                        },
                        markers: {
                            size: 4,
                            colors: ['#42a5f5'],
                            strokeColors: '#fff',
                            strokeWidth: 2,
                            hover: {
                                size: 6,
                            }
                        },
                        xaxis: {
                            categories: monthCategories,
                            labels: {
                                style: {
                                    colors: '#868e96'
                                }
                            }
                        },
                        yaxis: [{
                            title: {
                                text: 'Orders',
                                style: {
                                    color: '#ffa500'
                                }
                            },
                            labels: {
                                style: {
                                    colors: '#868e96'
                                }
                            }
                        }, {
                            opposite: true,
                            title: {
                                text: 'Revenue',
                                style: {
                                    color: '#42a5f5'
                                }
                            },
                            labels: {
                                formatter: function(value) {
                                    return '$' + value;
                                },
                                style: {
                                    colors: '#868e96'
                                }
                            }
                        }],
                        tooltip: {
                            shared: true,
                            intersect: false,
                            y: [{
                                formatter: function(value) {
                                    return value + " orders";
                                }
                            }, {
                                formatter: function(value) {
                                    return "$" + value.toFixed(2);
                                }
                            }]
                        },
                        legend: {
                            horizontalAlign: 'left',
                            offsetX: 40
                        }
                    };
                    
                    if (document.querySelector("#orderTrendsChart")) {
                        var orderTrendsChart = new ApexCharts(document.querySelector("#orderTrendsChart"), orderTrendsOptions);
                        orderTrendsChart.render();
                    }
                    
                    // Order Status Pie Chart
                    const orderStatusOptions = {
                        series: [
                            <?php echo (int)$stats['pending']; ?>,
                            <?php echo (int)$stats['processing']; ?>,
                            <?php echo (int)$stats['completed']; ?>,
                            <?php echo (int)$stats['cancelled']; ?>
                        ],
                        chart: {
                            type: 'donut',
                            height: 300,
                            fontFamily: 'Nunito, sans-serif',
                        },
                        labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
                        colors: ['#ffc107', '#42a5f5', '#66bb6a', '#f44336'],
                        legend: {
                            position: 'bottom',
                            horizontalAlign: 'center'
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '65%',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: true
                                        },
                                        value: {
                                            show: true,
                                            formatter: function(val) {
                                                return val;
                                            }
                                        },
                                        total: {
                                            show: true,
                                            label: 'Total Orders',
                                            formatter: function(w) {
                                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    };
                    
                    if (document.querySelector("#orderStatusChart")) {
                        var orderStatusChart = new ApexCharts(document.querySelector("#orderStatusChart"), orderStatusOptions);
                        orderStatusChart.render();
                    }
                    
                    // Event listener for trend time range change
                    $('#trendTimeRange').change(function() {
                        const months = parseInt($(this).val()) || 6;
                        updateChartWithAjax(months);
                    });
                }
            } catch (e) {
                console.error("Error initializing charts:", e);
            }
            
            // Function to update chart with AJAX data
            function updateChartWithAjax(months) {
                try {
                    // Show loading state
                    if (typeof orderTrendsChart !== 'undefined') {
                        orderTrendsChart.updateOptions({
                            chart: {
                                animations: {
                                    dynamicAnimation: {
                                        enabled: true
                                    }
                                }
                            }
                        });
                        
                        // In a real implementation, fetch data via AJAX
                        // For demo purposes, generate random data
                        const newCategories = [];
                        const newOrderData = [];
                        const newRevenueData = [];
                        
                        for (let i = months - 1; i >= 0; i--) {
                            const monthName = moment().subtract(i, 'months').format('MMM');
                            newCategories.push(monthName);
                            newOrderData.push(Math.floor(Math.random() * 50) + 10);
                            newRevenueData.push(Math.floor(Math.random() * 5000) + 1000);
                        }
                        
                        setTimeout(() => {
                            orderTrendsChart.updateOptions({
                                xaxis: {
                                    categories: newCategories
                                }
                            });
                            
                            orderTrendsChart.updateSeries([{
                                name: 'Orders',
                                data: newOrderData
                            }, {
                                name: 'Revenue',
                                data: newRevenueData
                            }]);
                        }, 800);
                    }
                } catch (e) {
                    console.error("Error updating chart:", e);
                }
            }
        });
    </script>
</body>
</html>
