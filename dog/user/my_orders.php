<?php
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

// Handle new order submission
if(isset($_POST['place_order']) && isset($_POST['dog_id'])) {
    $dog_id = (int)$_POST['dog_id'];
    
    // Fetch dog price
    $dogQuery = "SELECT price, name FROM dogs WHERE dog_id = $dog_id";
    $dogResult = $conn->query($dogQuery);
    
    if($dogResult && $dogResult->num_rows > 0) {
        $dog = $dogResult->fetch_assoc();
        $total_amount = $dog['price'];
        
        // Create the orders table if it doesn't exist
        $createTableQuery = "CREATE TABLE IF NOT EXISTS orders (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            dog_id INT(11) NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Pending',
            order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if(!$conn->query($createTableQuery)) {
            $message = '<div class="alert alert-danger">Error creating orders table: ' . $conn->error . '</div>';
        } else {
            // Insert the order
            $insertQuery = "INSERT INTO orders (user_id, dog_id, total_amount, status) 
                           VALUES ($user_id, $dog_id, $total_amount, 'Pending')";
            
            if($conn->query($insertQuery)) {
                $order_id = $conn->insert_id;
                $message = '<div class="alert alert-success">Order placed successfully! Your order ID is #' . $order_id . '</div>';
            } else {
                $message = '<div class="alert alert-danger">Error placing order: ' . $conn->error . '</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Dog not found or no longer available.</div>';
    }
}

// Get user's orders
$ordersQuery = "SELECT o.*, d.name as dog_name, d.breed, d.image_url 
                FROM orders o 
                JOIN dogs d ON o.dog_id = d.dog_id 
                WHERE o.user_id = $user_id 
                ORDER BY o.order_date DESC";
$ordersResult = $conn->query($ordersQuery);

$orders = [];
if($ordersResult && $ordersResult->num_rows > 0) {
    while($row = $ordersResult->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Get available dogs for new orders
$dogsQuery = "SELECT dog_id, name, breed, age, price, image_url FROM dogs 
              WHERE dog_id NOT IN (SELECT dog_id FROM orders WHERE status != 'Cancelled')
              ORDER BY name";
$dogsResult = $conn->query($dogsQuery);

$available_dogs = [];
if($dogsResult && $dogsResult->num_rows > 0) {
    while($row = $dogsResult->fetch_assoc()) {
        $available_dogs[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Doghouse Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0; /* Remove top padding - navigation bar accounts for this */
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            margin-left: 250px; /* Add margin-left to account for sidebar width */
            padding: 30px 15px;
            width: calc(100% - 250px); /* Adjust width to account for sidebar */
        }
        
        .page-header {
            margin-top: 70px; /* Add margin-top to account for the fixed header/navbar */
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .order-item {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid #ffa500;
        }
        
        .order-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-id {
            font-weight: 700;
            color: #333;
            font-size: 18px;
        }
        
        .order-date {
            color: #6c757d;
            font-size: 14px;
        }
        
        .order-status {
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .status-Pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #856404;
        }
        
        .status-Processing {
            background-color: rgba(66, 133, 244, 0.2);
            color: #0d47a1;
        }
        
        .status-Completed {
            background-color: rgba(102, 187, 106, 0.2);
            color: #2e7d32;
        }
        
        .status-Cancelled {
            background-color: rgba(244, 67, 54, 0.2);
            color: #b71c1c;
        }
        
        .dog-details {
            display: flex;
            align-items: center;
        }
        
        .dog-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .dog-name {
            font-weight: 700;
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .dog-breed {
            color: #6c757d;
            font-size: 14px;
        }
        
        .order-price {
            font-weight: 700;
            color: #333;
            font-size: 18px;
        }
        
        .btn-new-order {
            background-color: #ffa500;
            border-color: #ffa500;
        }
        
        .btn-new-order:hover {
            background-color: #e69500;
            border-color: #e69500;
        }
        
        .dog-selection-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dog-selection-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .dog-card-img {
            height: 180px;
            object-fit: cover;
        }
        
        .no-orders-message {
            text-align: center;
            padding: 50px 0;
        }
        
        .no-orders-message i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .no-orders-message h4 {
            margin-bottom: 10px;
            color: #6c757d;
        }

        @media (max-width: 767.98px) {
            .container {
                padding: 15px;
                margin-top: 60px; /* Adjust margin for mobile */
                margin-left: 0; /* Remove left margin on mobile when sidebar collapses */
                width: 100%; /* Full width on mobile */
            }
            
            .page-header {
                margin-top: 20px; /* Less margin needed on mobile */
            }
            
            .order-item {
                padding: 15px;
            }
            
            .dog-image {
                width: 60px;
                height: 60px;
                margin-right: 15px;
            }
            
            .dog-name {
                font-size: 14px;
            }
            
            .dog-breed {
                font-size: 12px;
            }
            
            .order-price {
                font-size: 16px;
            }
            
            .btn-new-order {
                width: 100%;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Track and manage your Doghouse Market orders</p>
        </div>
        
        <!-- Display message if any -->
        <?php echo $message; ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Order History</h5>
                        <button class="btn btn-primary btn-new-order" data-toggle="modal" data-target="#newOrderModal">
                            <i class="fas fa-plus mr-2"></i> Place New Order
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                            <div class="order-item">
                                <div class="order-header">
                                    <div class="order-id">Order #<?php echo $order['id']; ?></div>
                                    <div class="order-date"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></div>
                                    <span class="order-status status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span>
                                </div>
                                
                                <div class="dog-details">
                                    <?php if (!empty($order['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($order['image_url']); ?>" class="dog-image" alt="<?php echo htmlspecialchars($order['dog_name']); ?>">
                                    <?php else: ?>
                                        <div class="dog-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-dog fa-2x text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex-grow-1">
                                        <div class="dog-name"><?php echo htmlspecialchars($order['dog_name']); ?></div>
                                        <div class="dog-breed"><?php echo htmlspecialchars($order['breed']); ?></div>
                                        <div class="order-price">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                    </div>
                                    
                                    <div class="order-actions">
                                        <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                                        <?php if ($order['status'] === 'Pending'): ?>
                                            <button class="btn btn-outline-danger btn-sm ml-2" onclick="cancelOrder(<?php echo $order['id']; ?>)">Cancel</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-orders-message">
                                <i class="fas fa-shopping-cart"></i>
                                <h4>No orders found</h4>
                                <p class="text-muted">You haven't placed any orders yet. Browse our dogs to get started!</p>
                                <a href="browse_dogs.php" class="btn btn-primary">Browse Dogs</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Order Modal -->
    <div class="modal fade" id="newOrderModal" tabindex="-1" aria-labelledby="newOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newOrderModalLabel">Place New Order</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3">Select a dog to order:</h6>
                    <div class="row">
                        <?php if (!empty($available_dogs)): ?>
                            <?php foreach ($available_dogs as $dog): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card dog-selection-card h-100" onclick="selectDog(<?php echo $dog['dog_id']; ?>, '<?php echo htmlspecialchars($dog['name']); ?>', <?php echo $dog['price']; ?>)">
                                    <?php if (!empty($dog['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($dog['image_url']); ?>" class="card-img-top dog-card-img" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                                    <?php else: ?>
                                        <div class="card-img-top dog-card-img bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-dog fa-3x text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($dog['name']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($dog['breed']); ?> • <?php echo htmlspecialchars($dog['age']); ?></p>
                                        <p class="card-text"><strong>$<?php echo number_format($dog['price'], 2); ?></strong></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
                                <h5>No dogs available</h5>
                                <p>All dogs have been ordered or are no longer available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function selectDog(dogId, dogName, price) {
        if (confirm(`Place order for ${dogName} at $${price.toFixed(2)}?`)) {
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `<input type="hidden" name="dog_id" value="${dogId}"><input type="hidden" name="place_order" value="1">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function cancelOrder(orderId) {
        if (confirm('Are you sure you want to cancel this order?')) {
            window.location.href = `cancel_order.php?id=${orderId}`;
        }
    }
    </script>
</body>
</html>
