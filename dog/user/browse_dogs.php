<?php
/**
 * Doghouse Market - Browse Dogs Page
 * 
 * This page allows users to browse, search, and filter available dogs
 */
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

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

// Initialize message variable
$message = '';

// Handle "Place Order" functionality
if (isset($_POST['place_order']) && isset($_POST['dog_id'])) {
    $dog_id = (int)$_POST['dog_id'];
    
    // Check if user already has an order for this dog
    $checkOrderQuery = "SELECT * FROM orders WHERE user_id = $user_id AND dog_id = $dog_id";
    $checkResult = $conn->query($checkOrderQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                        You already have an order for this dog.
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>';
    } else {
        // Get dog price
        $dogQuery = "SELECT price FROM dogs WHERE dog_id = $dog_id";
        $dogResult = $conn->query($dogQuery);
        
        if ($dogResult && $dogResult->num_rows > 0) {
            $dog = $dogResult->fetch_assoc();
            
            // Create order
            $insertQuery = "INSERT INTO orders (user_id, dog_id, total_amount, status) VALUES ($user_id, $dog_id, {$dog['price']}, 'Pending')";
            
            if ($conn->query($insertQuery)) {
                $order_id = $conn->insert_id;
                $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                Order placed successfully! Order ID: #' . $order_id . '
                                <button type="button" class="close" data-dismiss="alert">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>';
            } else {
                $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error placing order: ' . $conn->error . '
                                <button type="button" class="close" data-dismiss="alert">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>';
            }
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            Dog not found.
                            <button type="button" class="close" data-dismiss="alert">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>';
        }
    }
}

// Handle "Add to Cart" functionality
if (isset($_POST['add_to_cart']) && isset($_POST['dog_id'])) {
    $dog_id = (int)$_POST['dog_id'];
    
    // Create cart table if it doesn't exist
    $createCartTableSQL = "CREATE TABLE IF NOT EXISTS cart (
        cart_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        dog_id INT NOT NULL,
        quantity INT DEFAULT 1,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_cart_item (user_id, dog_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (dog_id) REFERENCES dogs(dog_id) ON DELETE CASCADE
    )";
    $conn->query($createCartTableSQL);
    
    // Check if item already in cart
    $checkCartQuery = "SELECT * FROM cart WHERE user_id = $user_id AND dog_id = $dog_id";
    $checkResult = $conn->query($checkCartQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This dog is already in your cart']);
        exit;
    }
    
    // Check if already ordered
    $checkOrderQuery = "SELECT * FROM orders WHERE user_id = $user_id AND dog_id = $dog_id";
    $checkOrderResult = $conn->query($checkOrderQuery);
    
    if ($checkOrderResult && $checkOrderResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already ordered this dog']);
        exit;
    }
    
    // Add to cart
    $insertCartQuery = "INSERT INTO cart (user_id, dog_id) VALUES ($user_id, $dog_id)";
    if ($conn->query($insertCartQuery)) {
        // Get updated cart count
        $cartCountQuery = "SELECT COUNT(*) as count FROM cart WHERE user_id = $user_id";
        $cartCountResult = $conn->query($cartCountQuery);
        $cart_count = $cartCountResult->fetch_assoc()['count'] ?? 0;
        
        echo json_encode(['success' => true, 'message' => 'Dog added to cart!', 'cart_count' => $cart_count]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding to cart']);
        exit;
    }
}

// Initialize variables for filtering and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$breed = isset($_GET['breed']) ? $_GET['breed'] : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // Number of dogs per page
$offset = ($page - 1) * $per_page;

// Build query conditions - SINGLE INSTANCE
$conditions = ["1=1"]; // Always true condition to start with
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR breed LIKE ? OR trait LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($breed)) {
    $conditions[] = "breed = ?";
    $params[] = $breed;
    $types .= "s";
}

if (!empty($min_price)) {
    $conditions[] = "price >= ?";
    $params[] = $min_price;
    $types .= "d";
}

if (!empty($max_price)) {
    $conditions[] = "price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

// Get dogs that are not already ordered by the user
$conditions[] = "dog_id NOT IN (
    SELECT dog_id FROM orders WHERE user_id = ? AND status IN ('Pending', 'Processing', 'Completed')
)";
$params[] = $user_id;
$types .= "i";

// Build the WHERE clause
$where_clause = implode(' AND ', $conditions);

// Determine sorting
$order_by = "created_at DESC"; // Default is newest first
if ($sort === 'price_low') {
    $order_by = "price ASC";
} elseif ($sort === 'price_high') {
    $order_by = "price DESC";
} elseif ($sort === 'name_asc') {
    $order_by = "name ASC";
} elseif ($sort === 'name_desc') {
    $order_by = "name DESC";
}

// Prepare count query (for pagination)
$count_sql = "SELECT COUNT(*) FROM dogs WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);

if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_dogs = $count_result->fetch_row()[0];
    $count_stmt->close();
} else {
    $total_dogs = 0;
}

// Calculate total pages for pagination
$total_pages = ceil($total_dogs / $per_page);

// Prepare the main query with pagination
$sql = "SELECT * FROM dogs WHERE $where_clause ORDER BY $order_by LIMIT ?, ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Add pagination parameters
    $params[] = $offset;
    $params[] = $per_page;
    $types .= "ii";
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dogs = [];
    while ($row = $result->fetch_assoc()) {
        $dogs[] = $row;
    }
    $stmt->close();
} else {
    $dogs = [];
}

// Get all unique breeds for filter dropdown
$breeds = [];
$breed_query = "SELECT DISTINCT breed FROM dogs ORDER BY breed";
$breed_result = $conn->query($breed_query);

if ($breed_result) {
    while ($row = $breed_result->fetch_assoc()) {
        $breeds[] = $row['breed'];
    }
}

// Get price range for filter sliders
$price_range = ['min' => 0, 'max' => 5000]; // Default values
$price_query = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM dogs";
$price_result = $conn->query($price_query);

if ($price_result && $row = $price_result->fetch_assoc()) {
    $price_range['min'] = (int)$row['min_price'];
    $price_range['max'] = (int)$row['max_price'];
}

// Ajax search handler - returns JSON response for Ajax requests
if(isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    // Reset variables for AJAX
    $ajax_search = isset($_GET['search']) ? $_GET['search'] : '';
    $ajax_breed = isset($_GET['breed']) ? $_GET['breed'] : '';
    $ajax_min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : '';
    $ajax_max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : '';
    $ajax_sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $ajax_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $ajax_offset = ($ajax_page - 1) * $per_page;
    
    // Build conditions for AJAX query
    $ajax_conditions = ["1=1"];
    $ajax_params = [];
    $ajax_types = "";
    
    if (!empty($ajax_search)) {
        $ajax_conditions[] = "(name LIKE ? OR breed LIKE ? OR trait LIKE ?)";
        $ajax_search_param = "%$ajax_search%";
        $ajax_params[] = $ajax_search_param;
        $ajax_params[] = $ajax_search_param;
        $ajax_params[] = $ajax_search_param;
        $ajax_types .= "sss";
    }
    
    if (!empty($ajax_breed)) {
        $ajax_conditions[] = "breed = ?";
        $ajax_params[] = $ajax_breed;
        $ajax_types .= "s";
    }
    
    if (!empty($ajax_min_price)) {
        $ajax_conditions[] = "price >= ?";
        $ajax_params[] = $ajax_min_price;
        $ajax_types .= "d";
    }
    
    if (!empty($ajax_max_price)) {
        $ajax_conditions[] = "price <= ?";
        $ajax_params[] = $ajax_max_price;
        $ajax_types .= "d";
    }
    
    // Exclude already ordered dogs
    $ajax_conditions[] = "dog_id NOT IN (
        SELECT dog_id FROM orders WHERE user_id = ? AND status IN ('Pending', 'Processing', 'Completed')
    )";
    $ajax_params[] = $user_id;
    $ajax_types .= "i";
    
    // Build WHERE clause for AJAX
    $ajax_where_clause = implode(' AND ', $ajax_conditions);
    
    // Determine sorting for AJAX
    $ajax_order_by = "created_at DESC";
    if ($ajax_sort === 'price_low') {
        $ajax_order_by = "price ASC";
    } elseif ($ajax_sort === 'price_high') {
        $ajax_order_by = "price DESC";
    } elseif ($ajax_sort === 'name_asc') {
        $ajax_order_by = "name ASC";
    } elseif ($ajax_sort === 'name_desc') {
        $ajax_order_by = "name DESC";
    }
    
    // Count query for AJAX
    $ajax_count_sql = "SELECT COUNT(*) FROM dogs WHERE $ajax_where_clause";
    $ajax_count_stmt = $conn->prepare($ajax_count_sql);
    
    if ($ajax_count_stmt) {
        if (!empty($ajax_params)) {
            $ajax_count_stmt->bind_param($ajax_types, ...$ajax_params);
        }
        $ajax_count_stmt->execute();
        $ajax_count_result = $ajax_count_stmt->get_result();
        $ajax_total_dogs = $ajax_count_result->fetch_row()[0];
        $ajax_count_stmt->close();
    } else {
        $ajax_total_dogs = 0;
    }
    
    // Calculate total pages for AJAX
    $ajax_total_pages = ceil($ajax_total_dogs / $per_page);
    
    // Main query for AJAX with pagination
    $ajax_sql = "SELECT * FROM dogs WHERE $ajax_where_clause ORDER BY $ajax_order_by LIMIT ?, ?";
    $ajax_stmt = $conn->prepare($ajax_sql);
    
    if ($ajax_stmt) {
        // Add pagination parameters
        $ajax_params[] = $ajax_offset;
        $ajax_params[] = $per_page;
        $ajax_types .= "ii";
        
        $ajax_stmt->bind_param($ajax_types, ...$ajax_params);
        $ajax_stmt->execute();
        $ajax_result = $ajax_stmt->get_result();
        
        $ajax_dogs = [];
        while ($row = $ajax_result->fetch_assoc()) {
            $ajax_dogs[] = $row;
        }
        $ajax_stmt->close();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'dogs' => $ajax_dogs,
            'total' => $ajax_total_dogs,
            'pages' => $ajax_total_pages
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Dogs - Doghouse Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-left: 250px; /* Make room for sidebar */
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            margin-top: 60px; /* Space for the top navigation */
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #6c757d;
        }
        
        /* Filter Panel */
        .filter-panel {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-title {
            font-weight: 600;
            font-size: 18px;
            color: #333;
            margin-bottom: 0;
        }
        
        .filter-toggle {
            background: transparent;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
        
        .filter-body {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-item {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
        }
        
        /* Dog Cards */
        .dog-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .dog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .dog-image {
            height: 200px;
            width: 100%;
            object-fit: cover;
        }
        
        .dog-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .dog-name {
            font-weight: 700;
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .dog-breed {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .dog-traits {
            margin-bottom: 15px;
            flex-grow: 1;
        }
        
        .trait-tag {
            background-color: #f0f0f0;
            color: #666;
            border-radius: 15px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        
        .dog-price {
            font-size: 22px;
            font-weight: 700;
            color: #28a745;
            margin-top: auto;
            margin-bottom: 15px;
        }
        
        .dog-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        /* Price Slider */
        .price-slider {
            height: 6px;
            margin-bottom: 20px;
        }
        
        .noUi-connect {
            background: #ff6b6b;
        }
        
        .noUi-handle {
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 0 0 3px #ff6b6b;
            border: none;
            width: 18px !important;
            height: 18px !important;
            right: -9px !important;
            cursor: pointer;
        }
        
        .noUi-handle:before, .noUi-handle:after {
            display: none;
        }
        
        .price-inputs {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .price-inputs input {
            width: 80px;
            text-align: center;
        }
        
        /* Custom Button Styles */
        .btn-primary {
            background-color: #ff6b6b;
            border-color: #ff6b6b;
        }
        
        .btn-primary:hover {
            background-color: #ff5252;
            border-color: #ff5252;
        }
        
        .btn-outline-primary {
            color: #ff6b6b;
            border-color: #ff6b6b;
        }
        
        .btn-outline-primary:hover {
            background-color: #ff6b6b;
            color: white;
        }
        
        /* No Results */
        .no-results {
            text-align: center;
            padding: 40px 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-results i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .no-results h4 {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        /* Pagination */
        .pagination {
            margin: 30px 0;
        }
        
        .pagination .page-link {
            color: #333;
            padding: 8px 16px;
            border-radius: 4px;
            margin: 0 5px;
        }
        
        .pagination .active .page-link {
            background-color: #ff6b6b;
            border-color: #ff6b6b;
        }
        
        .pagination .page-link:focus {
            box-shadow: none;
        }
        
        /* Sort By Dropdown */
        .sort-dropdown {
            width: auto;
        }
        
        .filter-clear {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        
        .filter-clear:hover {
            text-decoration: underline;
            color: #ff5252;
        }

        @media (max-width: 767.98px) {
            body {
                margin-left: 0; /* Remove sidebar margin on mobile */
            }
            
            .container {
                margin-top: 20px;
            }
            
            .filter-body {
                flex-direction: column;
            }
            
            .filter-item {
                min-width: 100%;
            }
            
            .sort-dropdown {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .dog-image {
                height: 180px;
            }
        }
        
        /* Add loading indicator styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s;
        }
        
        .loading-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 165, 0, 0.1);
            border-left-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Browse Dogs</h1>
            <p class="page-subtitle">Find your perfect canine companion</p>
        </div>
        
        <!-- Filter Panel -->
        <div class="filter-panel">
            <div class="filter-header">
                <h2 class="filter-title">Filter Dogs</h2>
                <button class="filter-toggle" id="filterToggle">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>
            
            <div class="filter-body" id="filterBody">
                <form id="searchForm" action="browse_dogs.php" method="get" class="w-100">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="filter-item">
                                <label class="filter-label" for="search">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, breed, or traits" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="filter-item">
                                <label class="filter-label" for="breed">Breed</label>
                                <select class="form-control" id="breed" name="breed">
                                    <option value="">All Breeds</option>
                                    <?php foreach ($breeds as $breed_option): ?>
                                        <option value="<?php echo htmlspecialchars($breed_option); ?>" <?php echo $breed === $breed_option ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($breed_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="filter-item">
                                <label class="filter-label" for="sort">Sort By</label>
                                <select class="form-control sort-dropdown" id="sort" name="sort">
                                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                    <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <div class="filter-item">
                                <label class="filter-label">Price Range</label>
                                <div id="priceSlider" class="price-slider"></div>
                                <div class="price-inputs">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="minPrice" name="min_price" value="<?php echo $min_price ?: $price_range['min']; ?>" min="<?php echo $price_range['min']; ?>" max="<?php echo $price_range['max']; ?>">
                                    </div>
                                    <span class="mx-2 align-self-center">to</span>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="maxPrice" name="max_price" value="<?php echo $max_price ?: $price_range['max']; ?>" min="<?php echo $price_range['min']; ?>" max="<?php echo $price_range['max']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12 d-flex justify-content-between">
                            <a href="#" id="clearFilters" class="filter-clear">Clear Filters</a>
                            <button type="submit" id="applyFilters" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Results Count & Sort -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="mb-0" id="resultsCount"><strong><?php echo $total_dogs; ?></strong> dogs found</p>
        </div>
        
        <!-- Dogs Grid -->
        <div class="row" id="dogsGrid">
            <?php if (!empty($dogs)): ?>
                <?php foreach ($dogs as $dog): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="dog-card">
                            <?php if (!empty($dog['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($dog['image_url']); ?>" class="dog-image" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                            <?php else: ?>
                                <div class="dog-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-dog fa-3x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="dog-info">
                                <h2 class="dog-name"><?php echo htmlspecialchars($dog['name']); ?></h2>
                                <p class="dog-breed"><?php echo htmlspecialchars($dog['breed']); ?> · <?php echo htmlspecialchars($dog['age']); ?></p>
                                
                                <div class="dog-traits">
                                    <?php 
                                    $traits = explode(',', $dog['trait']);
                                    $traits = array_slice($traits, 0, 3); // Show only first 3 traits
                                    foreach ($traits as $trait): 
                                        $trait = trim($trait);
                                        if (!empty($trait)):
                                    ?>
                                        <span class="trait-tag"><?php echo htmlspecialchars($trait); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                                
                                <div class="dog-price">$<?php echo number_format($dog['price'], 2); ?></div>
                                
                                <div class="dog-actions">
                                    <a href="dog_details.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                                    <button type="button" class="btn btn-primary btn-sm add-to-cart-btn" data-dog-id="<?php echo $dog['dog_id']; ?>" data-dog-name="<?php echo htmlspecialchars($dog['name']); ?>">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="no-results">
                        <i class="fas fa-dog"></i>
                        <h4>No Dogs Found</h4>
                        <p class="text-muted">Try adjusting your search filters or check back later for new listings.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation" id="pagination">
            <!-- Pagination content will be generated dynamically -->
            <?php if ($total_pages > 1): ?>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="#" data-page="<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="#" data-page="<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Toggle filter panel
            $('#filterToggle').click(function() {
                $('#filterBody').slideToggle();
                $(this).find('i').toggleClass('fa-chevron-up fa-chevron-down');
            });
            
            // Initialize price slider
            var priceSlider = document.getElementById('priceSlider');
            
            if (priceSlider) {
                var minPrice = <?php echo $min_price ?: $price_range['min']; ?>;
                var maxPrice = <?php echo $max_price ?: $price_range['max']; ?>;
                var rangeMin = <?php echo $price_range['min']; ?>;
                var rangeMax = <?php echo $price_range['max']; ?>;
                
                noUiSlider.create(priceSlider, {
                    start: [minPrice, maxPrice],
                    connect: true,
                    step: 10,
                    range: {
                        'min': rangeMin,
                        'max': rangeMax
                    }
                });
                
                priceSlider.noUiSlider.on('update', function(values, handle) {
                    var value = Math.round(values[handle]);
                    
                    if (handle === 0) {
                        document.getElementById('minPrice').value = value;
                    } else {
                        document.getElementById('maxPrice').value = value;
                    }
                });
                
                // Update slider when inputs change
                $('#minPrice, #maxPrice').on('change', function() {
                    var minVal = parseInt($('#minPrice').val());
                    var maxVal = parseInt($('#maxPrice').val());
                    
                    if (minVal > maxVal) {
                        if ($(this).attr('id') === 'minPrice') {
                            $('#minPrice').val(maxVal);
                            minVal = maxVal;
                        } else {
                            $('#maxPrice').val(minVal);
                            maxVal = minVal;
                        }
                    }
                    
                    priceSlider.noUiSlider.set([minVal, maxVal]);
                });
            }
            
            // Function to perform Ajax search
            function performSearch(page = 1) {
                // Show loading indicator
                $("#loadingOverlay").addClass("active");
                
                // Get form data
                const search = $('#search').val();
                const breed = $('#breed').val();
                const minPrice = $('#minPrice').val();
                const maxPrice = $('#maxPrice').val();
                const sort = $('#sort').val();
                
                // Build query string
                const queryParams = {
                    ajax: 'true',
                    page: page,
                    search: search,
                    breed: breed,
                    min_price: minPrice,
                    max_price: maxPrice,
                    sort: sort
                };
                
                // Make Ajax request
                $.ajax({
                    url: 'browse_dogs.php',
                    type: 'GET',
                    data: queryParams,
                    dataType: 'json',
                    success: function(response) {
                        // Update dogs grid
                        if (response.dogs && response.dogs.length > 0) {
                            let dogsHtml = '';
                            response.dogs.forEach(function(dog) {
                                dogsHtml += `
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="dog-card">
                                            <div class="dog-image">
                                                <img src="${dog.image_url || 'https://via.placeholder.com/400x300?text=No+Image'}" alt="${dog.name}">
                                                <div class="dog-overlay">
                                                    <a href="view_dog.php?id=${dog.dog_id}" class="btn btn-primary btn-sm view-details">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="dog-info">
                                                <h3 class="dog-name">${dog.name}</h3>
                                                <div class="dog-details">
                                                    <p><i class="fas fa-paw"></i> <strong>Breed:</strong> ${dog.breed}</p>
                                                    <p><i class="fas fa-birthday-cake"></i> <strong>Age:</strong> ${dog.age}</p>
                                                    <p class="dog-trait"><i class="fas fa-star"></i> ${dog.trait.substring(0, 60)}${dog.trait.length > 60 ? '...' : ''}</p>
                                                </div>
                                                <div class="dog-footer">
                                                    <div class="dog-price">$${parseFloat(dog.price).toFixed(2)}</div>
                                                    <button class="btn btn-primary add-to-cart-btn" data-id="${dog.dog_id}" data-name="${dog.name}" data-price="${dog.price}">
                                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            $('#dogsGrid').html(dogsHtml);
                            $('#noResults').hide();
                        } else {
                            $('#dogsGrid').html('');
                            $('#noResults').show();
                        }
                        
                        // Update results count
                        $('#resultsCount').html(`<strong>${response.total}</strong> dogs found`);
                        
                        // Update pagination
                        updatePagination(page, response.pages);
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax request failed:', error);
                        $('#dogsGrid').html(`
                            <div class="col-12">
                                <div class="alert alert-danger">
                                    <p>Error loading dogs. Please try again later.</p>
                                </div>
                            </div>
                        `);
                    },
                    complete: function() {
                        // Hide loading indicator
                        $("#loadingOverlay").removeClass("active");
                    }
                });
            }
            
            // Update pagination links
            function updatePagination(currentPage, totalPages) {
                currentPage = parseInt(currentPage);
                $('#pagination').empty();
                
                if (totalPages <= 1) {
                    $('#pagination').hide();
                    return;
                }
                
                let paginationHtml = '<ul class="pagination justify-content-center">';
                
                // Previous button
                paginationHtml += `
                    <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                `;
                
                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    paginationHtml += `
                        <li class="page-item ${currentPage == i ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `;
                }
                
                // Next button
                paginationHtml += `
                    <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                `;
                
                paginationHtml += '</ul>';
                $('#pagination').html(paginationHtml).show();
                
                // Attach event listeners to new pagination links
                $('#pagination').find('a.page-link').on('click', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    performSearch(page);
                    
                    // Scroll to top of results
                    $('html, body').animate({
                        scrollTop: $('#dogsGrid').offset().top - 100
                    }, 200);
                });
            }
            
            // Handle search form submission
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                performSearch(1);
            });
            
            // Handle clear filters
            $('#clearFilters').on('click', function(e) {
                e.preventDefault();
                $('#search').val('');
                $('#breed').val('');
                $('#minPrice').val(<?php echo $price_range['min']; ?>);
                $('#maxPrice').val(<?php echo $price_range['max']; ?>);
                $('#sort').val('newest');
                
                // Reset price slider
                priceSlider.noUiSlider.reset();
                
                performSearch(1);
            });
            
            // Real-time search with debouncing
            let searchTimeout;
            $('#search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    performSearch(1);
                }, 500);
            });
            
            // Update on filter changes
            $('#breed, #sort').on('change', function() {
                performSearch(1);
            });
            
            // Handle add to cart button clicks
            $(document).on('click', '.add-to-cart-btn', function() {
                const dogId = $(this).data('dog-id');
                const dogName = $(this).data('dog-name');
                const button = $(this);
                
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');
                
                $.ajax({
                    url: 'browse_dogs.php',
                    method: 'POST',
                    data: {
                        add_to_cart: true,
                        dog_id: dogId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update cart count in header/sidenav
                            const cartBadge = $('.cart-count, .cart-badge, #cartCount, .cart-counter');
                            if (cartBadge.length) {
                                cartBadge.text(response.cart_count).show();
                            }
                            
                            // Show success message with option to view cart
                            showNotification('success', response.message + ' <a href="cart.php" style="color: white; text-decoration: underline; font-weight: bold;">View Cart</a>');
                            
                            // Update button
                            button.html('<i class="fas fa-check"></i> Added').removeClass('btn-primary').addClass('btn-success');
                            
                            setTimeout(function() {
                                button.html('<i class="fas fa-cart-plus"></i> Add to Cart').removeClass('btn-success').addClass('btn-primary');
                                button.prop('disabled', false);
                            }, 2000);
                        } else {
                            showNotification('error', response.message);
                            button.html('<i class="fas fa-cart-plus"></i> Add to Cart').prop('disabled', false);
                        }
                    },
                    error: function() {
                        showNotification('error', 'Error adding to cart. Please try again.');
                        button.html('<i class="fas fa-cart-plus"></i> Add to Cart').prop('disabled', false);
                    }
                });
            });
            
            // Simple notification function
            function showNotification(type, message) {
                const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                const notification = $(`
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
                        ${message}
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `);
                
                $('body').append(notification);
                
                setTimeout(function() {
                    notification.alert('close');
                }, 3000);
            }
            
            // Initialize pagination on page load
            $(window).on('load', function() {
                if ($('#pagination').find('a.page-link').length > 0) {
                    $('#pagination').find('a.page-link').on('click', function(e) {
                        e.preventDefault();
                        const page = $(this).data('page');
                        performSearch(page);
                        
                        // Scroll to top of results
                        $('html, body').animate({
                            scrollTop: $('#dogsGrid').offset().top - 100
                        }, 300);
                    });
                }
            });
        });
    </script>
</body>
</html>
