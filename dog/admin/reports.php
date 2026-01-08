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

// Set default date range for reports (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Update date range if provided in GET parameters
if(isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Format dates for SQL queries
$start_date_sql = $start_date . ' 00:00:00';
$end_date_sql = $end_date . ' 23:59:59';

// Get sales summary for the selected period
$salesSummaryQuery = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0 END) as total_revenue
FROM orders
WHERE order_date BETWEEN '$start_date_sql' AND '$end_date_sql'";

$salesSummary = [];
$summaryResult = mysqli_query($conn, $salesSummaryQuery);

if ($summaryResult && mysqli_num_rows($summaryResult) > 0) {
    $salesSummary = mysqli_fetch_assoc($summaryResult);
}

// Get daily sales data for chart
$dailySalesQuery = "SELECT 
    DATE(order_date) as sale_date,
    COUNT(*) as order_count,
    SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0 END) as daily_revenue
FROM orders
WHERE order_date BETWEEN '$start_date_sql' AND '$end_date_sql'
GROUP BY DATE(order_date)
ORDER BY sale_date ASC";

$dailySales = [];
$dailyResult = mysqli_query($conn, $dailySalesQuery);

if ($dailyResult && mysqli_num_rows($dailyResult) > 0) {
    while($row = mysqli_fetch_assoc($dailyResult)) {
        $dailySales[] = $row;
    }
}

// Get top selling breeds
$topBreedsQuery = "SELECT 
    d.breed,
    COUNT(*) as sold_count,
    SUM(o.total_amount) as revenue
FROM orders o
JOIN dogs d ON o.dog_id = d.dog_id
WHERE o.status = 'Completed' AND o.order_date BETWEEN '$start_date_sql' AND '$end_date_sql'
GROUP BY d.breed
ORDER BY sold_count DESC
LIMIT 5";

$topBreeds = [];
$breedsResult = mysqli_query($conn, $topBreedsQuery);

if ($breedsResult && mysqli_num_rows($breedsResult) > 0) {
    while($row = mysqli_fetch_assoc($breedsResult)) {
        $topBreeds[] = $row;
    }
}

// Get top customers
$topCustomersQuery = "SELECT 
    u.user_id,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
    COUNT(o.id) as order_count,
    SUM(CASE WHEN o.status = 'Completed' THEN o.total_amount ELSE 0 END) as total_spent
FROM orders o
JOIN users u ON o.user_id = u.user_id
WHERE o.order_date BETWEEN '$start_date_sql' AND '$end_date_sql'
GROUP BY o.user_id
ORDER BY total_spent DESC
LIMIT 5";

$topCustomers = [];
$customersResult = mysqli_query($conn, $topCustomersQuery);

if ($customersResult && mysqli_num_rows($customersResult) > 0) {
    while($row = mysqli_fetch_assoc($customersResult)) {
        $topCustomers[] = $row;
    }
}

// Get price range distribution
$priceRangeQuery = "SELECT 
    CASE 
        WHEN d.price < 500 THEN 'Under $500'
        WHEN d.price BETWEEN 500 AND 1000 THEN '$500 - $1,000'
        WHEN d.price BETWEEN 1001 AND 2000 THEN '$1,001 - $2,000'
        ELSE 'Over $2,000'
    END as price_range,
    COUNT(*) as count,
    SUM(o.total_amount) as revenue
FROM orders o
JOIN dogs d ON o.dog_id = d.dog_id
WHERE o.status = 'Completed' AND o.order_date BETWEEN '$start_date_sql' AND '$end_date_sql'
GROUP BY price_range
ORDER BY MIN(d.price)";

$priceRanges = [];
$rangeResult = mysqli_query($conn, $priceRangeQuery);

if ($rangeResult && mysqli_num_rows($rangeResult) > 0) {
    while($row = mysqli_fetch_assoc($rangeResult)) {
        $priceRanges[] = $row;
    }
}

// Get recent sales for table
$recentSalesQuery = "SELECT 
    o.id as order_id,
    o.order_date,
    o.total_amount,
    o.status,
    d.name as dog_name,
    d.breed,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name
FROM orders o
JOIN dogs d ON o.dog_id = d.dog_id
JOIN users u ON o.user_id = u.user_id
WHERE o.order_date BETWEEN '$start_date_sql' AND '$end_date_sql'
ORDER BY o.order_date DESC
LIMIT 10";

$recentSales = [];
$recentResult = mysqli_query($conn, $recentSalesQuery);

if ($recentResult && mysqli_num_rows($recentResult) > 0) {
    while($row = mysqli_fetch_assoc($recentResult)) {
        $recentSales[] = $row;
    }
}

// Get monthly comparison data
$monthlyComparisonQuery = "SELECT 
    DATE_FORMAT(order_date, '%Y-%m') as month,
    COUNT(*) as order_count,
    SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0 END) as monthly_revenue
FROM orders
WHERE order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(order_date, '%Y-%m')
ORDER BY month ASC";

$monthlyData = [];
$monthlyResult = mysqli_query($conn, $monthlyComparisonQuery);

if ($monthlyResult && mysqli_num_rows($monthlyResult) > 0) {
    while($row = mysqli_fetch_assoc($monthlyResult)) {
        $monthName = date('M Y', strtotime($row['month'] . '-01'));
        $monthlyData[$monthName] = [
            'order_count' => $row['order_count'],
            'revenue' => $row['monthly_revenue']
        ];
    }
}

// Calculate growth metrics
$currentMonthRevenue = end($monthlyData)['revenue'] ?? 0;
$previousMonthRevenue = prev($monthlyData)['revenue'] ?? 0;

$revenueGrowth = 0;
if ($previousMonthRevenue > 0) {
    $revenueGrowth = (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100;
}

// Export to CSV functionality
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $filename = 'sales_report_' . date('Y-m-d') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create file pointer connected to PHP output
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Column headers
    fputcsv($output, ['Order ID', 'Date', 'Customer', 'Dog Name', 'Breed', 'Amount', 'Status']);
    
    // Get all orders for the selected period
    $exportQuery = "SELECT 
        o.id as order_id,
        o.order_date,
        o.total_amount,
        o.status,
        d.name as dog_name,
        d.breed,
        CONCAT(u.first_name, ' ', u.last_name) as customer_name
    FROM orders o
    JOIN dogs d ON o.dog_id = d.dog_id
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_date BETWEEN '$start_date_sql' AND '$end_date_sql'
    ORDER BY o.order_date DESC";
    
    $exportResult = mysqli_query($conn, $exportQuery);
    
    if ($exportResult) {
        while ($row = mysqli_fetch_assoc($exportResult)) {
            fputcsv($output, [
                $row['order_id'],
                date('Y-m-d H:i', strtotime($row['order_date'])),
                $row['customer_name'],
                $row['dog_name'],
                $row['breed'],
                $row['total_amount'],
                $row['status']
            ]);
        }
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports | Doghouse Market Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        :root {
            /* Doghouse Market Theme Colors with Orange (#ffa500) as primary */
            --primary: <?php echo $companyInfo['color'] ?? '#ffa500'; ?>;
            --primary-light: <?php 
                $hex = ltrim($companyInfo['color'] ?? '#ffa500', '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $r = max(0, min(255, $r + ($r * 0.2)));
                $g = max(0, min(255, $g + ($g * 0.2)));
                $b = max(0, min(255, $b + ($b * 0.2)));
                echo sprintf("#%02x%02x%02x", $r, $g, $b);
            ?>;
            --primary-dark: <?php 
                $hex = ltrim($companyInfo['color'] ?? '#ffa500', '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $r = max(0, min(255, $r - ($r * 0.2)));
                $g = max(0, min(255, $g - ($g * 0.2)));
                $b = max(0, min(255, $b - ($b * 0.2)));
                echo sprintf("#%02x%02x%02x", $r, $g, $b);
            ?>;
            --secondary: #ff7e33;
            --tertiary: #ffcf40;
            --success: #66bb6a;
            --info: #42a5f5;
            --warning: #ffc107;
            --danger: #f44336;
            --light: #f8f9fa;
            --dark: #343a40;
            --text-primary: #495057;
            --text-secondary: #868e96;
            --text-muted: #adb5bd;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
            
            /* Layout dimensions */
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
        }

        /* Main Content Area */
        .main-area {
            margin-left: 250px;
            margin-top: 70px;
            flex: 1;
            transition: all 0.3s;
            min-height: calc(100vh - 70px);
            display: flex;
            flex-direction: column;
        }

        /* Content Container */
        .content-container {
            padding: 30px var(--content-padding);
            flex: 1;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            font-weight: 800;
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 15px;
            margin: 0;
        }

        /* Filter Card */
        .filter-card {
            background-color: white;
            border-radius: var(--card-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            padding: 20px;
            border: none;
        }
        
        .filter-title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        /* Stats Card */
        .stats-card {
            background-color: white;
            border-radius: var(--card-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            padding: 0;
            border: none;
            height: 100%;
        }
        
        .stats-card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-card-title {
            font-weight: 700;
            font-size: 16px;
            margin: 0;
            color: var(--text-primary);
        }
        
        .stats-card-body {
            padding: 20px;
        }
        
        .stats-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .stats-trend {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }
        
        .stats-trend.up {
            background-color: rgba(102, 187, 106, 0.1);
            color: var(--success);
        }
        
        .stats-trend.down {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }
        
        .stats-trend i {
            margin-right: 5px;
            font-size: 10px;
        }

        /* Chart Card */
        .chart-card {
            background-color: white;
            border-radius: var(--card-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            border: none;
            height: 100%;
        }
        
        .chart-card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chart-card-title {
            font-weight: 700;
            font-size: 16px;
            margin: 0;
            color: var(--text-primary);
        }
        
        .chart-card-body {
            padding: 20px;
        }
        
        .chart-container {
            width: 100%;
            height: 100%;
            min-height: 300px;
        }

        /* Table Card */
        .table-card {
            background-color: white;
            border-radius: var(--card-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            border: none;
        }
        
        .table-card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-card-title {
            font-weight: 700;
            font-size: 16px;
            margin: 0;
            color: var(--text-primary);
        }
        
        .table-card-body {
            padding: 0;
        }
        
        .table-responsive {
            margin-bottom: 0;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            border-top: none;
            background-color: rgba(0, 0, 0, 0.02);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .table .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        
        .status-Completed {
            background-color: rgba(102, 187, 106, 0.1);
            color: var(--success);
        }
        
        .status-Pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .status-Cancelled {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }
        
        .status-Processing {
            background-color: rgba(66, 165, 245, 0.1);
            color: var(--info);
        }

        /* Empty State */
        .empty-state {
            padding: 50px 20px;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 48px;
            color: var(--text-muted);
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        .empty-state p {
            color: var(--text-secondary);
            max-width: 300px;
            margin: 0 auto;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Daterangepicker Custom Styling */
        .daterangepicker .ranges li.active {
            background-color: var(--primary);
        }
        
        .daterangepicker td.active, 
        .daterangepicker td.active:hover {
            background-color: var(--primary);
        }
        
        .daterangepicker td.in-range {
            background-color: rgba(255, 165, 0, 0.1);
        }
        
        .daterangepicker .drp-buttons .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .main-area {
                margin-left: 0;
                margin-top: 60px;
                width: 100% !important;
            }
            
            .content-container {
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .chart-container {
                min-height: 250px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="app-wrapper">
        <!-- Main Content -->
        <main class="main-area">
            <!-- Content Container -->
            <div class="content-container">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Sales Report</h1>
                        <p class="page-subtitle">View detailed sales analytics and performance metrics</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="reports.php?export=csv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-primary mr-2">
                            <i class="fas fa-file-download mr-2"></i> Export to CSV
                        </a>
                        <button type="button" class="btn btn-primary" id="printReport">
                            <i class="fas fa-print mr-2"></i> Print Report
                        </button>
                    </div>
                </div>
                
                <!-- Filter Card -->
                <div class="filter-card">
                    <form action="reports.php" method="get" class="row">
                        <div class="col-md-8">
                            <div class="form-group mb-0">
                                <label for="daterange" class="filter-title">Date Range</label>
                                <input type="text" id="daterange" name="daterange" class="form-control" value="<?php echo $start_date . ' - ' . $end_date; ?>">
                                <input type="hidden" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                <input type="hidden" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                        </div>
                    </form>
                </div>
                
                <!-- Sales Summary Stats -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-card-header">
                                <h2 class="stats-card-title">Total Revenue</h2>
                            </div>
                            <div class="stats-card-body">
                                <div class="stats-value">$<?php echo number_format($salesSummary['total_revenue'] ?? 0, 2); ?></div>
                                <p class="stats-label">For selected period</p>
                                <?php if ($revenueGrowth != 0): ?>
                                    <span class="stats-trend <?php echo $revenueGrowth >= 0 ? 'up' : 'down'; ?>">
                                        <i class="fas fa-<?php echo $revenueGrowth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                        <?php echo abs(round($revenueGrowth, 1)); ?>% from previous period
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-card-header">
                                <h2 class="stats-card-title">Total Orders</h2>
                            </div>
                            <div class="stats-card-body">
                                <div class="stats-value"><?php echo $salesSummary['total_orders'] ?? 0; ?></div>
                                <p class="stats-label">Orders placed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-card-header">
                                <h2 class="stats-card-title">Completed Orders</h2>
                            </div>
                            <div class="stats-card-body">
                                <div class="stats-value"><?php echo $salesSummary['completed_orders'] ?? 0; ?></div>
                                <p class="stats-label">Successfully delivered</p>
                                <?php 
                                $completionRate = 0;
                                if (($salesSummary['total_orders'] ?? 0) > 0) {
                                    $completionRate = (($salesSummary['completed_orders'] ?? 0) / $salesSummary['total_orders']) * 100;
                                }
                                ?>
                                <span class="stats-trend up">
                                    <i class="fas fa-check"></i>
                                    <?php echo number_format($completionRate, 1); ?>% completion rate
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-card-header">
                                <h2 class="stats-card-title">Average Order Value</h2>
                            </div>
                            <div class="stats-card-body">
                                <?php 
                                $avg_order = 0;
                                if (($salesSummary['completed_orders'] ?? 0) > 0) {
                                    $avg_order = ($salesSummary['total_revenue'] ?? 0) / $salesSummary['completed_orders'];
                                }
                                ?>
                                <div class="stats-value">$<?php echo number_format($avg_order, 2); ?></div>
                                <p class="stats-label">Per completed order</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sales Chart & Top Breeds -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h2 class="chart-card-title">Daily Sales Revenue</h2>
                            </div>
                            <div class="chart-card-body">
                                <div id="dailySalesChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h2 class="chart-card-title">Top Selling Breeds</h2>
                            </div>
                            <div class="chart-card-body">
                                <?php if (!empty($topBreeds)): ?>
                                    <div id="topBreedsChart" class="chart-container"></div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-chart-pie"></i>
                                        <h4>No breed data available</h4>
                                        <p>There are no completed orders in the selected date range.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Comparison & Price Distribution -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h2 class="chart-card-title">Monthly Comparison</h2>
                            </div>
                            <div class="chart-card-body">
                                <div id="monthlyComparisonChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h2 class="chart-card-title">Price Range Distribution</h2>
                            </div>
                            <div class="chart-card-body">
                                <?php if (!empty($priceRanges)): ?>
                                    <div id="priceDistributionChart" class="chart-container"></div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-dollar-sign"></i>
                                        <h4>No price data available</h4>
                                        <p>There are no completed orders in the selected date range.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Customers -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="table-card">
                            <div class="table-card-header">
                                <h2 class="table-card-title">Top Customers</h2>
                            </div>
                            <div class="table-card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Orders</th>
                                                <th>Total Spent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($topCustomers)): ?>
                                                <?php foreach ($topCustomers as $customer): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                    <td><?php echo $customer['order_count']; ?></td>
                                                    <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-4">No customer data available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h2 class="chart-card-title">Order Status Distribution</h2>
                            </div>
                            <div class="chart-card-body">
                                <div id="statusChart" class="chart-container"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Sales Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="table-card">
                            <div class="table-card-header">
                                <h2 class="table-card-title">Recent Sales</h2>
                            </div>
                            <div class="table-card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Customer</th>
                                                <th>Dog</th>
                                                <th>Breed</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recentSales)): ?>
                                                <?php foreach ($recentSales as $sale): ?>
                                                <tr>
                                                    <td>#<?php echo $sale['order_id']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($sale['order_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($sale['dog_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($sale['breed']); ?></td>
                                                    <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                    <td><span class="status-badge status-<?php echo $sale['status']; ?>"><?php echo $sale['status']; ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">No sales data available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize date range picker
            $('#daterange').daterangepicker({
                startDate: '<?php echo $start_date; ?>',
                endDate: '<?php echo $end_date; ?>',
                opens: 'left',
                ranges: {
                   'Today': [moment(), moment()],
                   'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                   'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                   'This Month': [moment().startOf('month'), moment().endOf('month')],
                   'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, function(start, end, label) {
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });
            
            // Print report functionality
            $('#printReport').click(function() {
                window.print();
            });
            
            // Daily Sales Chart
            var dailySalesOptions = {
                chart: {
                    type: 'area',
                    height: 350,
                    toolbar: {
                        show: false
                    },
                    zoom: {
                        enabled: false
                    }
                },
                series: [{
                    name: 'Revenue',
                    data: [
                        <?php 
                        foreach ($dailySales as $day) {
                            echo $day['daily_revenue'] . ', ';
                        }
                        ?>
                    ]
                }],
                xaxis: {
                    categories: [
                        <?php 
                        foreach ($dailySales as $day) {
                            echo "'" . date('M d', strtotime($day['sale_date'])) . "', ";
                        }
                        ?>
                    ],
                    labels: {
                        style: {
                            colors: '#868e96'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function (value) {
                            return '$' + value;
                        },
                        style: {
                            colors: '#868e96'
                        }
                    }
                },
                colors: ['<?php echo $companyInfo['color'] ?? '#ffa500'; ?>'],
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.7,
                        opacityTo: 0.2,
                        stops: [0, 90, 100]
                    }
                },
                markers: {
                    size: 4,
                    colors: ['#fff'],
                    strokeColors: '<?php echo $companyInfo['color'] ?? '#ffa500'; ?>',
                    strokeWidth: 2
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                },
                grid: {
                    borderColor: '#e9ecef',
                    strokeDashArray: 4
                }
            };

            var dailySalesChart = new ApexCharts(document.querySelector("#dailySalesChart"), dailySalesOptions);
            dailySalesChart.render();
            
            // Top Breeds Chart
            <?php if (!empty($topBreeds)): ?>
            var topBreedsOptions = {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Sales Count',
                    data: [
                        <?php 
                        foreach ($topBreeds as $breed) {
                            echo $breed['sold_count'] . ', ';
                        }
                        ?>
                    ]
                }],
                xaxis: {
                    categories: [
                        <?php 
                        foreach ($topBreeds as $breed) {
                            echo "'" . $breed['breed'] . "', ";
                        }
                        ?>
                    ],
                    labels: {
                        style: {
                            colors: '#868e96'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#868e96'
                        }
                    }
                },
                colors: ['<?php echo $companyInfo['color'] ?? '#ffa500'; ?>'],
                plotOptions: {
                    bar: {
                        borderRadius: 8,
                        horizontal: true,
                        barHeight: '60%',
                        dataLabels: {
                            position: 'top'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return val;
                    },
                    style: {
                        fontSize: '12px',
                        colors: ['#333']
                    },
                    offsetX: 30
                },
                tooltip: {
                    y: {
                        title: {
                            formatter: function (seriesName) {
                                return 'Sales';
                            }
                        }
                    }
                },
                grid: {
                    borderColor: '#e9ecef',
                    strokeDashArray: 4,
                    yaxis: {
                        lines: {
                            show: false
                        }
                    }
                }
            };

            var topBreedsChart = new ApexCharts(document.querySelector("#topBreedsChart"), topBreedsOptions);
            topBreedsChart.render();
            <?php endif; ?>
            
            // Monthly Comparison Chart
            var monthlyOptions = {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: {
                        show: false
                    },
                    stacked: false
                },
                series: [{
                    name: 'Revenue',
                    type: 'column',
                    data: [
                        <?php 
                        foreach ($monthlyData as $month) {
                            echo $month['revenue'] . ', ';
                        }
                        ?>
                    ]
                }, {
                    name: 'Orders',
                    type: 'line',
                    data: [
                        <?php 
                        foreach ($monthlyData as $month) {
                            echo $month['order_count'] . ', ';
                        }
                        ?>
                    ]
                }],
                xaxis: {
                    categories: [
                        <?php 
                        foreach ($monthlyData as $month => $data) {
                            echo "'" . $month . "', ";
                        }
                        ?>
                    ],
                    labels: {
                        style: {
                            colors: '#868e96'
                        }
                    }
                },
                yaxis: [{
                    title: {
                        text: 'Revenue ($)',
                        style: {
                            color: '<?php echo $companyInfo['color'] ?? '#ffa500'; ?>'
                        }
                    },
                    labels: {
                        formatter: function (value) {
                            return '$' + value;
                        },
                        style: {
                            colors: '#868e96'
                        }
                    }
                }, {
                    opposite: true,
                    title: {
                        text: 'Orders',
                        style: {
                            color: '#ff7e33'
                        }
                    },
                    labels: {
                        style: {
                            colors: '#868e96'
                        }
                    }
                }],
                colors: ['<?php echo $companyInfo['color'] ?? '#ffa500'; ?>', '#ff7e33'],
                plotOptions: {
                    bar: {
                        borderRadius: 8,
                        columnWidth: '50%'
                    }
                },
                stroke: {
                    width: [0, 3]
                },
                markers: {
                    size: 4,
                    colors: ['#fff'],
                    strokeColors: '#ff7e33',
                    strokeWidth: 2
                },
                tooltip: {
                    y: [{
                        formatter: function (value) {
                            return '$' + value.toFixed(2);
                        }
                    }, {
                        formatter: function (value) {
                            return value + ' orders';
                        }
                    }]
                },
                grid: {
                    borderColor: '#e9ecef',
                    strokeDashArray: 4
                }
            };

            var monthlyComparisonChart = new ApexCharts(document.querySelector("#monthlyComparisonChart"), monthlyOptions);
            monthlyComparisonChart.render();
            
            // Price Distribution Chart
            <?php if (!empty($priceRanges)): ?>
            var priceDistributionOptions = {
                chart: {
                    type: 'donut',
                    height: 350,
                    toolbar: {
                        show: false
                    }
                },
                series: [
                    <?php 
                    foreach ($priceRanges as $range) {
                        echo $range['count'] . ', ';
                    }
                    ?>
                ],
                labels: [
                    <?php 
                    foreach ($priceRanges as $range) {
                        echo "'" . $range['price_range'] . "', ";
                    }
                    ?>
                ],
                colors: ['#ffa500', '#ff7e33', '#ffcf40', '#66bb6a'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '55%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '14px',
                                    fontWeight: 600
                                },
                                value: {
                                    show: true,
                                    fontSize: '18px',
                                    fontWeight: 700,
                                    formatter: function (val) {
                                        return val + ' orders';
                                    }
                                },
                                total: {
                                    show: true,
                                    label: 'Total Orders',
                                    formatter: function (w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                legend: {
                    position: 'bottom',
                    horizontalAlign: 'center',
                    fontSize: '14px',
                    markers: {
                        width: 12,
                        height: 12,
                        radius: 6
                    },
                    itemMargin: {
                        horizontal: 5,
                        vertical: 5
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + ' orders';
                        }
                    }
                }
            };

            var priceDistributionChart = new ApexCharts(document.querySelector("#priceDistributionChart"), priceDistributionOptions);
            priceDistributionChart.render();
            <?php endif; ?>
            
            // Status Distribution Chart
            var statusOptions = {
                chart: {
                    type: 'donut',
                    height: 350,
                    toolbar: {
                        show: false
                    }
                },
                series: [
                    <?php echo ($salesSummary['completed_orders'] ?? 0) . ', '; ?>
                    <?php echo ($salesSummary['pending_orders'] ?? 0) . ', '; ?>
                    <?php echo ($salesSummary['cancelled_orders'] ?? 0); ?>
                ],
                labels: ['Completed', 'Pending', 'Cancelled'],
                colors: ['#66bb6a', '#ffc107', '#f44336'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '55%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '14px',
                                    fontWeight: 600
                                },
                                value: {
                                    show: true,
                                    fontSize: '18px',
                                    fontWeight: 700,
                                    formatter: function (val) {
                                        return val + ' orders';
                                    }
                                },
                                total: {
                                    show: true,
                                    label: 'Total Orders',
                                    formatter: function (w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                legend: {
                    position: 'bottom',
                    horizontalAlign: 'center',
                    fontSize: '14px',
                    markers: {
                        width: 12,
                        height: 12,
                        radius: 6
                    },
                    itemMargin: {
                        horizontal: 5,
                        vertical: 5
                    }
                }
            };

            var statusChart = new ApexCharts(document.querySelector("#statusChart"), statusOptions);
            statusChart.render();
        });
    </script>
</body>
</html>
