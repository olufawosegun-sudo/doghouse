<?php
/**
 * Dog House Market - Dogs Listing Page
 * Displays all available dogs with filtering, sorting, and AJAX search
 */

// Include database connection
$conn = require_once 'dbconnect.php';

// Fetch company information from database
$companyInfo = [];
$companyQuery = "SELECT * FROM company_info LIMIT 1";
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
    // Format the business hours for display if available
    if (isset($companyInfo['hours'])) {
        $companyInfo['business_hours'] = nl2br(htmlspecialchars($companyInfo['hours']));
    }
} else {
    // Default company info if not found in database
    $companyInfo = [
        'company_name' => 'Doghouse Market',
        'primary_color' => '#FFA500',
        // ...other default values as in index.php
    ];
}

// AJAX Search Handler
if (isset($_GET['ajax_search']) && $_GET['ajax_search'] == 'true') {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $breed = isset($_GET['breed']) ? mysqli_real_escape_string($conn, $_GET['breed']) : '';
    $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
    $max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 10000;
    
    // Build the query
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    if (!empty($search)) {
        $where_clauses[] = "(name LIKE ? OR breed LIKE ? OR trait LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $param_types .= "sss";
    }
    
    if (!empty($breed)) {
        $where_clauses[] = "breed LIKE ?";
        $params[] = "%$breed%";
        $param_types .= "s";
    }
    
    $where_clauses[] = "price >= ?";
    $params[] = $min_price;
    $param_types .= "d";
    
    $where_clauses[] = "price <= ?";
    $params[] = $max_price;
    $param_types .= "d";
    
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $sort_clause = match($sort) {
        'price_asc' => "ORDER BY price ASC",
        'price_desc' => "ORDER BY price DESC",
        'name_asc' => "ORDER BY name ASC",
        'name_desc' => "ORDER BY name DESC",
        default => "ORDER BY created_at DESC", // newest first
    };
    
    $sql = "SELECT * FROM dogs $where_clause $sort_clause";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $dogs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dogs[] = $row;
    }
    
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode(['dogs' => $dogs, 'count' => count($dogs)]);
    exit;
}

// Regular page loading (non-AJAX)
// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$breed_filter = isset($_GET['breed']) ? $_GET['breed'] : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 10000; // Set a reasonable maximum default
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Pagination settings
$dogs_per_page = 12;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $dogs_per_page;

// Check if dogs table exists
$tableExistsQuery = "SHOW TABLES LIKE 'dogs'";
$tableExists = mysqli_query($conn, $tableExistsQuery);
$dogsTableExists = $tableExists && mysqli_num_rows($tableExists) > 0;

// Initialize variables
$dogs = [];
$total_dogs = 0;
$total_pages = 1;
$all_breeds = [];

if ($dogsTableExists) {
    // Build the query based on filters
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    if (!empty($search)) {
        $where_clauses[] = "(name LIKE ? OR breed LIKE ? OR trait LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $param_types .= "sss";
    }
    
    if (!empty($breed_filter)) {
        $where_clauses[] = "breed LIKE ?";
        $params[] = "%$breed_filter%";
        $param_types .= "s";
    }
    
    $where_clauses[] = "price >= ?";
    $params[] = $min_price;
    $param_types .= "d";
    
    $where_clauses[] = "price <= ?";
    $params[] = $max_price;
    $param_types .= "d";
    
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Determine sort order
    $sort_clause = match($sort) {
        'price_asc' => "ORDER BY price ASC",
        'price_desc' => "ORDER BY price DESC",
        'name_asc' => "ORDER BY name ASC",
        'name_desc' => "ORDER BY name DESC",
        default => "ORDER BY created_at DESC", // newest first
    };
    
    // Count total dogs matching filters
    $count_sql = "SELECT COUNT(*) as total FROM dogs $where_clause";
    $stmt = mysqli_prepare($conn, $count_sql);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_dogs = $count_row['total'];
    $total_pages = ceil($total_dogs / $dogs_per_page);
    
    // Fetch dogs with pagination
    $sql = "SELECT * FROM dogs $where_clause $sort_clause LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    $param_types .= "ii";
    $params[] = $dogs_per_page;
    $params[] = $offset;
    
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $dogs[] = $row;
    }
    
    // Fetch all distinct breeds for filter dropdown
    $breed_sql = "SELECT DISTINCT breed FROM dogs ORDER BY breed ASC";
    $breed_result = mysqli_query($conn, $breed_sql);
    
    if ($breed_result) {
        while ($row = mysqli_fetch_assoc($breed_result)) {
            $all_breeds[] = $row['breed'];
        }
    }
}

// Function to maintain query parameters when changing page
function getPaginationUrl($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return '?' . http_build_query($params);
}

// Function to maintain pagination when changing filters/sort
function getFilterUrl($params_to_update) {
    $params = $_GET;
    unset($params['page']); // Reset to page 1 when changing filters
    return '?' . http_build_query(array_merge($params, $params_to_update));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Dogs - <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($companyInfo['primary_color'] ?? '#FFA500'); ?>;
            --primary-color-light: <?php 
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
                $r = min(255, $r + (255 - $r) * 0.7);
                $g = min(255, $g + (255 - $g) * 0.7);
                $b = min(255, $b + (255 - $b) * 0.7);
                echo sprintf("#%02x%02x%02x", $r, $g, $b);
            ?>;
            --secondary-color: #2c3e50;
            --accent-color: #e67e22;
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

        /* Search & Filter Section */
        .filter-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .filter-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .filter-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .filter-form .form-label {
            font-weight: 600;
        }
        
        .filter-form .form-control, .filter-form .form-select {
            border-radius: var(--border-radius);
            padding: 10px 15px;
        }
        
        .filter-form .form-control:focus, .filter-form .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
        }
        
        .price-range {
            display: flex;
            gap: 15px;
        }
        
        .sort-select {
            width: 200px;
        }

        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box .form-control {
            padding-right: 40px;
            border-radius: 30px;
        }
        
        .search-box .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }
        
        .search-results-count {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .search-loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .search-loading .spinner-border {
            color: var(--primary-color);
        }

        /* Dog Cards */
        .dog-card {
            position: relative;
            border: none;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            height: 100%;
            background-color: white;
        }
        
        .dog-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .dog-card .card-img-wrapper {
            position: relative;
            overflow: hidden;
            height: 240px;
        }
        
        .dog-card img {
            transition: transform 0.5s ease;
            height: 100%;
            width: 100%;
            object-fit: cover;
        }
        
        .dog-card:hover img {
            transform: scale(1.1);
        }
        
        .dog-card .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 50%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .dog-card:hover .overlay {
            opacity: 1;
        }
        
        .dog-card .card-body {
            padding: 25px;
        }
        
        .dog-card .card-title {
            font-size: 1.4rem;
            margin-bottom: 10px;
        }
        
        .dog-card .dog-info {
            margin-bottom: 20px;
        }
        
        .dog-card .dog-info span {
            display: block;
            margin-bottom: 5px;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .dog-card .dog-info span i {
            color: var(--primary-color);
            margin-right: 8px;
            width: 18px;
            text-align: center;
        }
        
        .dog-card .price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .dog-card .btn {
            border-radius: 30px;
            padding: 10px 25px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        /* Animation for new search results */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fadeIn {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Dog card skeleton loader */
        .skeleton-card {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            height: 100%;
        }
        
        .skeleton-img {
            height: 240px;
            background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%);
            background-size: 200% 100%;
            animation: 1.5s shine linear infinite;
        }
        
        .skeleton-body {
            padding: 25px;
        }
        
        .skeleton-title {
            height: 28px;
            width: 70%;
            margin-bottom: 15px;
            background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%);
            background-size: 200% 100%;
            animation: 1.5s shine linear infinite;
            border-radius: 4px;
        }
        
        .skeleton-text {
            height: 12px;
            margin-bottom: 10px;
            background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%);
            background-size: 200% 100%;
            animation: 1.5s shine linear infinite;
            border-radius: 4px;
        }
        
        .skeleton-text.w75 {
            width: 75%;
        }
        
        .skeleton-text.w50 {
            width: 50%;
        }
        
        .skeleton-price {
            height: 30px;
            width: 40%;
            margin: 20px 0;
            background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%);
            background-size: 200% 100%;
            animation: 1.5s shine linear infinite;
            border-radius: 4px;
        }
        
        .skeleton-button {
            height: 40px;
            width: 100%;
            background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%);
            background-size: 200% 100%;
            animation: 1.5s shine linear infinite;
            border-radius: 20px;
        }
        
        @keyframes shine {
            to {
                background-position-x: -200%;
            }
        }

        /* Pagination */
        .pagination {
            margin: 40px 0;
            justify-content: center;
        }
        
        .pagination .page-item .page-link {
            color: var(--dark-color);
            padding: 12px 20px;
            border: none;
            margin: 0 5px;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }
        
        .pagination .page-item .page-link:hover {
            background-color: var(--primary-color-light);
            color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
        }
        
        .pagination .page-item.disabled .page-link {
            color: var(--text-light);
            pointer-events: none;
            background-color: transparent;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 0;
        }
        
        .no-results i {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 20px;
        }
        
        .no-results h3 {
            margin-bottom: 15px;
        }
        
        .no-results p {
            max-width: 500px;
            margin: 0 auto 30px;
            color: var(--text-light);
        }

        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 60px 0 20px;
        }
        
        /* Convert primary-color HEX to RGB for opacity support */
        :root {
            --primary-color-rgb: <?php 
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
        }

        /* Custom styles for primary color */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .page-header h1 {
                font-size: 2.5rem;
            }
            
            .page-header p {
                font-size: 1.1rem;
            }
            
            .sort-dropdown {
                margin-top: 20px;
            }
        }
        
        @media (max-width: 767.98px) {
            .page-header {
                padding: 70px 0;
                margin-bottom: 40px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .filter-section {
                padding: 20px;
            }
            
            .price-range {
                flex-direction: column;
                gap: 10px;
            }
            
            .dog-card .card-img-wrapper {
                height: 200px;
            }
            
            .pagination .page-item .page-link {
                padding: 8px 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="dogs.php">Dogs</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
                </ul>
                <div class="d-flex">
                    <a href="signin.php" class="btn btn-outline-primary me-2">Sign In</a>
                    <a href="signup.php" class="btn btn-primary">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <div class="container">
            <h1>Available Dogs</h1>
            <p>Find your perfect companion from our selection of adorable, well-cared-for dogs looking for their forever homes.</p>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Search & Filters Section -->
        <section class="filter-section">
            <h2 class="filter-title"><i class="fas fa-filter"></i> Find Your Perfect Match</h2>
            
            <!-- Live Search Box -->
            <div class="search-box mb-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name, breed, or traits..." 
                    value="<?php echo htmlspecialchars($search); ?>">
                <i class="fas fa-search search-icon"></i>
            </div>
            
            <form id="filterForm" class="filter-form" action="dogs.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="breed" class="form-label">Breed</label>
                        <select class="form-select" id="breed" name="breed">
                            <option value="">All Breeds</option>
                            <?php foreach ($all_breeds as $breed): ?>
                                <option value="<?php echo htmlspecialchars($breed); ?>" <?php echo $breed === $breed_filter ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($breed); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="price" class="form-label">Price Range</label>
                        <div class="price-range">
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="min_price" name="min_price" placeholder="Min" value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="max_price" name="max_price" placeholder="Max" value="<?php echo $max_price < 10000 ? $max_price : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                        <a href="dogs.php" class="btn btn-outline-secondary">Clear</a>
                        <!-- Hidden input for search term -->
                        <input type="hidden" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
            </form>
        </section>

        <!-- Sort & Results Count -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div id="resultsCount">
                <p class="mb-0"><strong class="search-results-count"><?php echo $total_dogs; ?></strong> dogs found</p>
            </div>
            <div class="sort-dropdown">
                <div class="d-flex align-items-center">
                    <label for="sort" class="me-2">Sort by:</label>
                    <select class="form-select sort-select" id="sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="searchLoading" class="search-loading">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Searching for dogs...</p>
        </div>

        <!-- Dogs Grid -->
        <div id="dogsContainer">
            <?php if (!$dogsTableExists || empty($dogs)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No Dogs Found</h3>
                    <p>Sorry, we couldn't find any dogs matching your criteria. Try adjusting your filters or check back later.</p>
                    <a href="index.php" class="btn btn-primary">Back to Homepage</a>
                </div>
            <?php else: ?>
                <div class="row" id="dogsGrid">
                    <?php foreach ($dogs as $dog): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="dog-card">
                                <div class="card-img-wrapper">
                                    <?php if (!empty($dog['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($dog['image_url']); ?>" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                                    <?php else: ?>
                                        <img src="https://via.placeholder.com/600x400?text=No+Image" alt="No Image">
                                    <?php endif; ?>
                                    <div class="overlay"></div>
                                </div>
                                <div class="card-body">
                                    <h3 class="card-title"><?php echo htmlspecialchars($dog['name']); ?></h3>
                                    <div class="dog-info">
                                        <span><i class="fas fa-paw"></i> Breed: <?php echo htmlspecialchars($dog['breed']); ?></span>
                                        <span><i class="fas fa-birthday-cake"></i> Age: <?php echo htmlspecialchars($dog['age']); ?></span>
                                        <span><i class="fas fa-star"></i> Traits: <?php echo htmlspecialchars(substr($dog['trait'], 0, 60)) . (strlen($dog['trait']) > 60 ? '...' : ''); ?></span>
                                    </div>
                                    <div class="price">$<?php echo number_format($dog['price'], 2); ?></div>
                                    <a href="view_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-primary w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <div id="pagination">
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Dog listing pagination">
                            <ul class="pagination">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page <= 1 ? '#' : getPaginationUrl($page - 1); ?>" aria-label="Previous">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl($i); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page >= $total_pages ? '#' : getPaginationUrl($page + 1); ?>" aria-label="Next">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="dogs.php">Dogs</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Contact Us</h5>
                    <address>
                        <?php echo htmlspecialchars($companyInfo['address'] ?? 'Oklahoma City, OK 73149'); ?><br>
                        Email: <?php echo htmlspecialchars($companyInfo['email'] ?? 'atimalothbrok@gmail.com'); ?><br>
                    </address>
                </div>
                <div class="col-lg-6 col-md-12">
                    <h5>Subscribe to Our Newsletter</h5>
                    <p>Stay updated on new arrivals and special offers</p>
                    <form class="mt-3">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your Email" aria-label="Your Email">
                            <button class="btn btn-primary" type="button">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="mt-4">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Templates for JavaScript -->
    <template id="dogCardTemplate">
        <div class="col-lg-4 col-md-6 mb-4 fadeIn">
            <div class="dog-card">
                <div class="card-img-wrapper">
                    <img src="" alt="" class="dog-image">
                    <div class="overlay"></div>
                </div>
                <div class="card-body">
                    <h3 class="card-title dog-name"></h3>
                    <div class="dog-info">
                        <span><i class="fas fa-paw"></i> Breed: <span class="dog-breed"></span></span>
                        <span><i class="fas fa-birthday-cake"></i> Age: <span class="dog-age"></span></span>
                        <span><i class="fas fa-star"></i> Traits: <span class="dog-trait"></span></span>
                    </div>
                    <div class="price dog-price"></div>
                    <a href="#" class="btn btn-primary w-100 dog-link">View Details</a>
                </div>
            </div>
        </div>
    </template>
    
    <template id="noResultsTemplate">
        <div class="no-results fadeIn">
            <i class="fas fa-search"></i>
            <h3>No Dogs Found</h3>
            <p>Sorry, we couldn't find any dogs matching your search criteria. Try adjusting your filters or check back later.</p>
            <button class="btn btn-primary" id="clearSearchBtn">Clear Search</button>
        </div>
    </template>
    
    <template id="skeletonTemplate">
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="skeleton-card">
                <div class="skeleton-img"></div>
                <div class="skeleton-body">
                    <div class="skeleton-title"></div>
                    <div class="skeleton-text w75"></div>
                    <div class="skeleton-text"></div>
                    <div class="skeleton-text w50"></div>
                    <div class="skeleton-price"></div>
                    <div class="skeleton-button"></div>
                </div>
            </div>
        </div>
    </template>

    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AJAX Search & Filter Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const searchInput = document.getElementById('searchInput');
            const breedSelect = document.getElementById('breed');
            const minPriceInput = document.getElementById('min_price');
            const maxPriceInput = document.getElementById('max_price');
            const sortSelect = document.getElementById('sort');
            const dogsGrid = document.getElementById('dogsGrid');
            const dogsContainer = document.getElementById('dogsContainer');
            const resultsCount = document.getElementById('resultsCount');
            const searchLoading = document.getElementById('searchLoading');
            const hiddenSearchInput = document.getElementById('search');
            const paginationContainer = document.getElementById('pagination');
            const filterForm = document.getElementById('filterForm');
            
            // Templates
            const dogCardTemplate = document.getElementById('dogCardTemplate');
            const noResultsTemplate = document.getElementById('noResultsTemplate');
            const skeletonTemplate = document.getElementById('skeletonTemplate');
            
            // Debounce function to avoid excessive API calls
            function debounce(func, delay) {
                let timeout;
                return function() {
                    const context = this;
                    const args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), delay);
                };
            }
            
            // Function to show loading state
            function showLoading() {
                dogsGrid.style.display = 'none';
                if (paginationContainer) paginationContainer.style.display = 'none';
                searchLoading.style.display = 'block';
                
                // Add skeleton loaders
                const tempContainer = document.createElement('div');
                tempContainer.className = 'row';
                for (let i = 0; i < 6; i++) {
                    tempContainer.appendChild(skeletonTemplate.content.cloneNode(true));
                }
                dogsContainer.appendChild(tempContainer);
                
                return tempContainer; // Return the skeleton container so we can remove it later
            }
            
            // Function to hide loading state
            function hideLoading(skeletonContainer) {
                searchLoading.style.display = 'none';
                if (skeletonContainer) {
                    dogsContainer.removeChild(skeletonContainer);
                }
                dogsGrid.style.display = 'flex';
                dogsGrid.style.flexWrap = 'wrap';
            }
            
            // Function to update results
            function updateResults(dogs, count) {
                // Update count display
                document.querySelector('.search-results-count').textContent = count;
                
                // Clear existing grid
                dogsGrid.innerHTML = '';
                
                if (dogs.length === 0) {
                    // Show no results message
                    const noResults = noResultsTemplate.content.cloneNode(true);
                    dogsGrid.appendChild(noResults);
                    
                    // Handle clear search button
                    setTimeout(() => {
                        const clearSearchBtn = document.getElementById('clearSearchBtn');
                        if (clearSearchBtn) {
                            clearSearchBtn.addEventListener('click', function() {
                                searchInput.value = '';
                                breedSelect.selectedIndex = 0;
                                minPriceInput.value = '';
                                maxPriceInput.value = '';
                                sortSelect.selectedIndex = 0;
                                fetchDogs();
                            });
                        }
                    }, 0);
                } else {
                    // Render dog cards
                    dogs.forEach((dog, index) => {
                        const dogCard = dogCardTemplate.content.cloneNode(true);
                        
                        // Set dog info
                        dogCard.querySelector('.dog-name').textContent = dog.name;
                        dogCard.querySelector('.dog-breed').textContent = dog.breed;
                        dogCard.querySelector('.dog-age').textContent = dog.age;
                        
                        // Truncate traits if too long
                        const traitText = dog.trait.length > 60 
                            ? dog.trait.substring(0, 60) + '...' 
                            : dog.trait;
                        dogCard.querySelector('.dog-trait').textContent = traitText;
                        
                        // Format price
                        dogCard.querySelector('.dog-price').textContent = '$' + parseFloat(dog.price).toFixed(2);
                        
                        // Set image
                        const imgElement = dogCard.querySelector('.dog-image');
                        if (dog.image_url) {
                            imgElement.src = dog.image_url;
                            imgElement.alt = dog.name;
                        } else {
                            imgElement.src = 'https://via.placeholder.com/600x400?text=No+Image';
                            imgElement.alt = 'No Image';
                        }
                        
                        // Set link
                        dogCard.querySelector('.dog-link').href = 'view_dog.php?id=' + dog.dog_id;
                        
                        // Add delay for staggered animation
                        const cardElement = dogCard.querySelector('.col-lg-4');
                        cardElement.style.animationDelay = (index * 0.1) + 's';
                        
                        dogsGrid.appendChild(dogCard);
                    });
                }
                
                // Hide pagination during AJAX results
                if (paginationContainer) {
                    paginationContainer.style.display = 'none';
                }
            }
            
            // Function to fetch dogs with current filters
            function fetchDogs() {
                const skeletonContainer = showLoading();
                
                // Collect all filter values
                const searchValue = searchInput.value.trim();
                const breedValue = breedSelect.value;
                const minPriceValue = minPriceInput.value;
                const maxPriceValue = maxPriceInput.value;
                const sortValue = sortSelect.value;
                
                // Update hidden search input for form submission
                if (hiddenSearchInput) {
                    hiddenSearchInput.value = searchValue;
                }
                
                // Build URL with query parameters
                const params = new URLSearchParams();
                params.append('ajax_search', 'true');
                if (searchValue) params.append('search', searchValue);
                if (breedValue) params.append('breed', breedValue);
                if (minPriceValue) params.append('min_price', minPriceValue);
                if (maxPriceValue) params.append('max_price', maxPriceValue);
                params.append('sort', sortValue);
                
                // Fetch data from server
                fetch(`dogs.php?${params.toString()}`)
                    .then(response => response.json())
                    .then(data => {
                        hideLoading(skeletonContainer);
                        updateResults(data.dogs, data.count);
                    })
                    .catch(error => {
                        console.error('Error fetching dogs:', error);
                        hideLoading(skeletonContainer);
                        // Show error message
                        dogsGrid.innerHTML = `
                            <div class="col-12 text-center">
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Error loading dogs. Please try again later.
                                </div>
                            </div>
                        `;
                    });
            }
            
            // Set up event listeners for real-time searching
            searchInput.addEventListener('input', debounce(() => fetchDogs(), 500));
            breedSelect.addEventListener('change', () => fetchDogs());
            sortSelect.addEventListener('change', () => fetchDogs());
            
            // For price fields, only search when user stops typing
            minPriceInput.addEventListener('input', debounce(() => fetchDogs(), 800));
            maxPriceInput.addEventListener('input', debounce(() => fetchDogs(), 800));
            
            // Prevent default form submission and use our AJAX search instead
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    fetchDogs();
                });
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>
</body>
</html>
