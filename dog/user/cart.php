<?php
/**
 * Doghouse Market - Shopping Cart
 * Modern and professional cart interface
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

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle remove from cart
if (isset($_POST['remove_item']) && isset($_POST['cart_id'])) {
    $cart_id = (int)$_POST['cart_id'];
    $deleteQuery = "DELETE FROM cart WHERE cart_id = $cart_id AND user_id = $user_id";
    
    if ($conn->query($deleteQuery)) {
        $_SESSION['cart_message'] = ['type' => 'success', 'text' => 'Item removed from cart'];
    } else {
        $_SESSION['cart_message'] = ['type' => 'error', 'text' => 'Error removing item'];
    }
    
    header("Location: cart.php");
    exit;
}

// Handle checkout
if (isset($_POST['checkout'])) {
    $cartQuery = "SELECT c.*, d.price FROM cart c 
                  JOIN dogs d ON c.dog_id = d.dog_id 
                  WHERE c.user_id = $user_id";
    $cartResult = $conn->query($cartQuery);
    
    if ($cartResult && $cartResult->num_rows > 0) {
        $conn->begin_transaction();
        
        try {
            while ($item = $cartResult->fetch_assoc()) {
                $dog_id = $item['dog_id'];
                $price = $item['price'];
                
                $orderQuery = "INSERT INTO orders (user_id, dog_id, total_amount, status, order_date) 
                              VALUES ($user_id, $dog_id, $price, 'Pending', NOW())";
                
                if (!$conn->query($orderQuery)) {
                    throw new Exception("Error creating order");
                }
            }
            
            $clearCartQuery = "DELETE FROM cart WHERE user_id = $user_id";
            $conn->query($clearCartQuery);
            
            $conn->commit();
            $_SESSION['cart_message'] = ['type' => 'success', 'text' => 'Order placed successfully! Check your orders page.'];
            header("Location: my_orders.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['cart_message'] = ['type' => 'error', 'text' => 'Error processing checkout: ' . $e->getMessage()];
        }
    }
}

// Fetch cart items
$cartQuery = "SELECT c.*, d.name, d.breed, d.age, d.price, d.image_url, d.trait 
              FROM cart c 
              JOIN dogs d ON c.dog_id = d.dog_id 
              WHERE c.user_id = $user_id 
              ORDER BY c.added_at DESC";
$cartResult = $conn->query($cartQuery);

$cart_items = [];
$total_amount = 0;

if ($cartResult && $cartResult->num_rows > 0) {
    while ($row = $cartResult->fetch_assoc()) {
        $cart_items[] = $row;
        $total_amount += $row['price'];
    }
}

$cart_count = count($cart_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Doghouse Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --dark-color: #2c3e50;
            --light-gray: #f8f9fa;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            margin-left: 250px;
            min-height: 100vh;
        }
        
        .cart-container {
            max-width: 1200px;
            margin: 80px auto 40px;
            padding: 0 20px;
        }
        
        .cart-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .cart-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), #ff8787);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .cart-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }
        
        .cart-count-badge {
            background: var(--secondary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }
        
        .cart-items-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart-icon {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .cart-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border-radius: var(--border-radius);
            background: var(--light-gray);
            margin-bottom: 15px;
            transition: var(--transition);
            position: relative;
        }
        
        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .cart-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
        }
        
        .item-image {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
        }
        
        .item-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .item-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .item-breed {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .item-traits {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }
        
        .trait-badge {
            background: white;
            color: #666;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #e0e0e0;
        }
        
        .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .item-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .remove-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remove-btn:hover {
            background: #ee5a6f;
            transform: scale(1.05);
        }
        
        .cart-summary {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 80px;
            height: fit-content;
        }
        
        .summary-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            color: #666;
        }
        
        .summary-row.total {
            border-top: 2px solid var(--light-gray);
            margin-top: 10px;
            padding-top: 20px;
            font-size: 20px;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .summary-row.total .amount {
            color: var(--primary-color);
        }
        
        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), #ff8787);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
            text-transform: uppercase;
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.3);
        }
        
        .continue-shopping {
            display: block;
            text-align: center;
            color: #666;
            text-decoration: none;
            margin-top: 15px;
            font-weight: 600;
        }
        
        .continue-shopping:hover {
            color: var(--primary-color);
        }
        
        .security-badge {
            background: var(--light-gray);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        
        .security-badge i {
            color: #28a745;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .security-badge p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        @media (max-width: 991px) {
            body {
                margin-left: 0;
            }
            
            .cart-content {
                grid-template-columns: 1fr;
            }
            
            .cart-summary {
                position: static;
            }
        }
        
        @media (max-width: 767px) {
            .cart-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .cart-item {
                flex-direction: column;
            }
            
            .item-image {
                width: 100%;
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="cart-container">
        <div class="cart-header">
            <div class="cart-title">
                <div class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h1>Shopping Cart</h1>
                </div>
            </div>
            <span class="cart-count-badge"><?php echo $cart_count; ?> Items</span>
        </div>
        
        <?php if (isset($_SESSION['cart_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['cart_message']['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $_SESSION['cart_message']['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $_SESSION['cart_message']['text']; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['cart_message']); ?>
        <?php endif; ?>
        
        <div class="cart-content">
            <div class="cart-items-section">
                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        <div class="empty-cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3>Your Cart is Empty</h3>
                        <p>Looks like you haven't added any dogs to your cart yet.</p>
                        <a href="browse_dogs.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-paw mr-2"></i>Browse Dogs
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="item-image">
                            <?php else: ?>
                                <div class="item-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-dog fa-3x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="item-details">
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-breed">
                                        <i class="fas fa-paw mr-1"></i>
                                        <?php echo htmlspecialchars($item['breed']); ?> · 
                                        <?php echo htmlspecialchars($item['age']); ?>
                                    </div>
                                    <div class="item-traits">
                                        <?php 
                                        $traits = explode(',', $item['trait']);
                                        $traits = array_slice($traits, 0, 3);
                                        foreach ($traits as $trait): 
                                            $trait = trim($trait);
                                            if (!empty($trait)):
                                        ?>
                                            <span class="trait-badge"><?php echo htmlspecialchars($trait); ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="item-footer">
                                    <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <button type="submit" name="remove_item" class="remove-btn">
                                            <i class="fas fa-trash"></i>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($cart_items)): ?>
                <div class="cart-summary">
                    <h2 class="summary-title">Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Subtotal (<?php echo $cart_count; ?> items)</span>
                        <span>$<?php echo number_format($total_amount, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span class="text-success">FREE</span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span class="amount">$<?php echo number_format($total_amount, 2); ?></span>
                    </div>
                    
                    <form method="post">
                        <button type="submit" name="checkout" class="checkout-btn">
                            <i class="fas fa-lock mr-2"></i>
                            Proceed to Checkout
                        </button>
                    </form>
                    
                    <a href="browse_dogs.php" class="continue-shopping">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Continue Shopping
                    </a>
                    
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        <p><strong>Secure Checkout</strong><br>Your information is protected</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('form').on('submit', function(e) {
                if ($(this).find('[name="remove_item"]').length) {
                    if (!confirm('Are you sure you want to remove this item from your cart?')) {
                        e.preventDefault();
                    }
                }
            });
            
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
