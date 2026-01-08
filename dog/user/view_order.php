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

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header("Location: my_orders.php");
    exit;
}

// Fetch order details
$orderQuery = "SELECT o.*, d.name as dog_name, d.breed, d.age, d.trait, d.image_url, d.price as dog_price
               FROM orders o 
               JOIN dogs d ON o.dog_id = d.dog_id 
               WHERE o.id = $order_id AND o.user_id = $user_id";

$orderResult = $conn->query($orderQuery);

if (!$orderResult || $orderResult->num_rows === 0) {
    header("Location: my_orders.php");
    exit;
}

$order = $orderResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order['id']; ?> - Doghouse Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-left: 250px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 15px;
            margin-top: 60px;
        }
        
        .order-header {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .order-id {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .order-date {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-Pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #856404;
            border: 2px solid #ffc107;
        }
        
        .status-Processing {
            background-color: rgba(66, 133, 244, 0.2);
            color: #0d47a1;
            border: 2px solid #4285f4;
        }
        
        .status-Completed {
            background-color: rgba(102, 187, 106, 0.2);
            color: #2e7d32;
            border: 2px solid #66bb6a;
        }
        
        .status-Cancelled {
            background-color: rgba(244, 67, 54, 0.2);
            color: #b71c1c;
            border: 2px solid #f44336;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .dog-image {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            object-fit: cover;
            margin-right: 20px;
        }
        
        .dog-details h3 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .trait-tag {
            background-color: #f0f0f0;
            color: #666;
            border-radius: 15px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        
        .price-display {
            font-size: 24px;
            font-weight: 700;
            color: #28a745;
        }
        
        @media (max-width: 767.98px) {
            body {
                margin-left: 0;
            }
            
            .container {
                margin-top: 20px;
                padding: 15px;
            }
            
            .dog-image {
                width: 100px;
                height: 100px;
                margin-bottom: 15px;
            }
            
            .order-header {
                padding: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="container">
        <!-- Order Header -->
        <div class="order-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="order-id">Order #<?php echo $order['id']; ?></div>
                    <div class="order-date">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        Placed on <?php echo date('F d, Y \a\t g:i A', strtotime($order['order_date'])); ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo $order['status']; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Dog Details Card -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-dog mr-2"></i>Dog Details</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <?php if (!empty($order['image_url'])): ?>
                            <img src="../<?php echo htmlspecialchars($order['image_url']); ?>" class="dog-image" alt="<?php echo htmlspecialchars($order['dog_name']); ?>">
                        <?php else: ?>
                            <div class="dog-image bg-light d-flex align-items-center justify-content-center">
                                <i class="fas fa-dog fa-3x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <div class="dog-details">
                            <h3><?php echo htmlspecialchars($order['dog_name']); ?></h3>
                            <p class="mb-2"><strong>Breed:</strong> <?php echo htmlspecialchars($order['breed']); ?></p>
                            <p class="mb-2"><strong>Age:</strong> <?php echo htmlspecialchars($order['age']); ?></p>
                            
                            <?php if (!empty($order['trait'])): ?>
                            <div class="mb-3">
                                <strong>Traits:</strong><br>
                                <?php 
                                $traits = explode(',', $order['trait']);
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
                            <?php endif; ?>
                            
                            <div class="price-display">$<?php echo number_format($order['dog_price'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Summary Card -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-receipt mr-2"></i>Order Summary</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Order ID:</strong></td>
                                <td>#<?php echo $order['id']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Order Date:</strong></td>
                                <td><?php echo date('M d, Y g:i A', strtotime($order['order_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Subtotal:</strong></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tax:</strong></td>
                                <td>$0.00</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>Total:</strong></td>
                                <td><strong class="text-success">$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="text-center">
            <a href="my_orders.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Orders
            </a>
            
            <?php if ($order['status'] === 'Pending'): ?>
            <button class="btn btn-danger ml-2" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                <i class="fas fa-times mr-2"></i>Cancel Order
            </button>
            <?php endif; ?>
            
            <button class="btn btn-info ml-2" onclick="window.print()">
                <i class="fas fa-print mr-2"></i>Print Order
            </button>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                // Redirect to cancel order endpoint
                window.location.href = 'cancel_order.php?id=' + orderId;
            }
        }
    </script>
</body>
</html>
