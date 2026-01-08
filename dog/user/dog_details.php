<?php
/**
 * Doghouse Market - Dog Details Page
 * 
 * This page shows detailed information about a specific dog
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

// Check if dog ID is provided
if(!isset($_GET['id'])) {
    header("Location: browse_dogs.php");
    exit;
}

$dog_id = (int)$_GET['id'];

// Get dog details
$dogQuery = "SELECT * FROM dogs WHERE dog_id = ?";
$stmt = $conn->prepare($dogQuery);
$stmt->bind_param("i", $dog_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    // Dog not found
    header("Location: browse_dogs.php");
    exit;
}

$dog = $result->fetch_assoc();
$stmt->close();

// Check if this dog is already in user's collection
$inCollection = false;
$collectionQuery = "SELECT * FROM user_dogs WHERE user_id = ? AND dog_id = ?";
$stmt = $conn->prepare($collectionQuery);
$stmt->bind_param("ii", $user_id, $dog_id);
$stmt->execute();
$result = $stmt->get_result();
$inCollection = ($result->num_rows > 0);
$stmt->close();

// Check if this dog is already in pending/completed orders
$inOrders = false;
$ordersQuery = "SELECT * FROM orders WHERE user_id = ? AND dog_id = ? AND status IN ('Pending', 'Processing', 'Completed')";
$stmt = $conn->prepare($ordersQuery);
$stmt->bind_param("ii", $user_id, $dog_id);
$stmt->execute();
$result = $stmt->get_result();
$inOrders = ($result->num_rows > 0);
$stmt->close();

// Get similar dogs based on breed
$similarDogs = [];
$similarQuery = "SELECT * FROM dogs WHERE breed = ? AND dog_id != ? LIMIT 4";
$stmt = $conn->prepare($similarQuery);
$stmt->bind_param("si", $dog['breed'], $dog_id);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $similarDogs[] = $row;
}
$stmt->close();

// Initialize message variable for success/error messages
$message = '';

// Handle adding dog to collection
if(isset($_POST['add_to_collection'])) {
    if($inCollection) {
        $message = '<div class="alert alert-warning">This dog is already in your collection.</div>';
    } else {
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        $adoption_status = 'Available';
        
        $insertQuery = "INSERT INTO user_dogs (user_id, dog_id, adoption_status, notes) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iiss", $user_id, $dog_id, $adoption_status, $notes);
        
        if($stmt->execute()) {
            $message = '<div class="alert alert-success">Dog added to your collection successfully!</div>';
            $inCollection = true;
        } else {
            $message = '<div class="alert alert-danger">Error adding dog to collection: ' . $conn->error . '</div>';
        }
        $stmt->close();
    }
}

// Handle placing order
if(isset($_POST['place_order'])) {
    if($inOrders) {
        $message = '<div class="alert alert-warning">You already have an order for this dog.</div>';
    } else {
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
                           VALUES (?, ?, ?, 'Pending')";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iid", $user_id, $dog_id, $total_amount);
            
            if($stmt->execute()) {
                $order_id = $conn->insert_id;
                $message = '<div class="alert alert-success">Order placed successfully! Your order ID is #' . $order_id . '</div>';
                $inOrders = true;
            } else {
                $message = '<div class="alert alert-danger">Error placing order: ' . $conn->error . '</div>';
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dog['name']); ?> - Doghouse Market</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
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
        
        .breadcrumb {
            background: none;
            padding: 0;
        }
        
        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: #ff6b6b;
        }
        
        .breadcrumb-item.active {
            color: #ff6b6b;
        }
        
        /* Dog Details Card */
        .dog-details-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .dog-gallery {
            position: relative;
            height: 400px;
            overflow: hidden;
        }
        
        .dog-gallery img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .dog-gallery-placeholder {
            width: 100%;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        
        .dog-gallery-placeholder i {
            font-size: 80px;
            color: #dee2e6;
        }
        
        .dog-info-container {
            padding: 30px;
        }
        
        .dog-name {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 5px;
            color: #333;
        }
        
        .dog-breed {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .dog-price {
            font-size: 28px;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .dog-actions {
            margin-bottom: 20px;
        }
        
        .dog-actions .btn {
            margin-right: 10px;
            margin-bottom: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .dog-description {
            margin-bottom: 30px;
        }
        
        .dog-description h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }
        
        .dog-description p {
            color: #6c757d;
            line-height: 1.8;
        }
        
        .dog-details-table {
            margin-bottom: 30px;
        }
        
        .dog-details-table h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }
        
        .table th {
            font-weight: 600;
            background-color: rgba(255, 107, 107, 0.1);
        }
        
        /* Similar Dogs */
        .similar-dogs {
            margin-top: 30px;
        }
        
        .similar-dogs h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }
        
        .similar-dog-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .similar-dog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .similar-dog-image {
            height: 180px;
            width: 100%;
            object-fit: cover;
        }
        
        .similar-dog-info {
            padding: 15px;
        }
        
        .similar-dog-name {
            font-weight: 700;
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .similar-dog-breed {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .similar-dog-price {
            font-size: 18px;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .tag-container {
            margin-bottom: 20px;
        }
        
        .trait-tag {
            background-color: #f0f0f0;
            color: #666;
            border-radius: 15px;
            padding: 5px 12px;
            font-size: 13px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
        }
        
        @media (max-width: 767.98px) {
            body {
                margin-left: 0; /* Remove sidebar margin on mobile */
            }
            
            .container {
                margin-top: 20px;
            }
            
            .dog-gallery {
                height: 250px;
            }
            
            .dog-name {
                font-size: 24px;
            }
            
            .dog-price {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="container">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mt-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item"><a href="browse_dogs.php">Browse Dogs</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($dog['name']); ?></li>
            </ol>
        </nav>
        
        <!-- Display message if any -->
        <?php echo $message; ?>
        
        <!-- Dog Details Section -->
        <div class="dog-details-card">
            <div class="row no-gutters">
                <div class="col-md-6">
                    <!-- Dog Image Gallery -->
                    <div class="dog-gallery">
                        <?php if (!empty($dog['image_url'])): ?>
                            <img src="../<?php echo htmlspecialchars($dog['image_url']); ?>" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                        <?php else: ?>
                            <div class="dog-gallery-placeholder">
                                <i class="fas fa-dog"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <!-- Dog Information -->
                    <div class="dog-info-container">
                        <h1 class="dog-name"><?php echo htmlspecialchars($dog['name']); ?></h1>
                        <p class="dog-breed"><?php echo htmlspecialchars($dog['breed']); ?></p>
                        <div class="dog-price">$<?php echo number_format($dog['price'], 2); ?></div>
                        
                        <!-- Dog Actions -->
                        <div class="dog-actions">
                            <?php if(!$inOrders): ?>
                                <form action="dog_details.php?id=<?php echo $dog_id; ?>" method="post" class="d-inline">
                                    <button type="submit" name="place_order" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart mr-2"></i> Order Now
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check mr-2"></i> Already Ordered
                                </button>
                            <?php endif; ?>
                            
                            <?php if(!$inCollection): ?>
                                <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#addToCollectionModal">
                                    <i class="fas fa-plus mr-2"></i> Add to Collection
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline-success" disabled>
                                    <i class="fas fa-check mr-2"></i> In Collection
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Dog Traits -->
                        <div class="tag-container">
                            <?php
                            $traits = explode(',', $dog['trait']);
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
                        
                        <!-- Details Table -->
                        <div class="dog-details-table">
                            <h3>Details</h3>
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th>Age</th>
                                        <td><?php echo htmlspecialchars($dog['age']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Breed</th>
                                        <td><?php echo htmlspecialchars($dog['breed']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Added</th>
                                        <td><?php echo date('F j, Y', strtotime($dog['created_at'])); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dog Description Section -->
            <div class="dog-description p-4">
                <h3>About <?php echo htmlspecialchars($dog['name']); ?></h3>
                <?php
                // Generate a description based on the dog's traits if no dedicated description field
                $traitsArray = explode(',', $dog['trait']);
                $traitsFormatted = array_map('trim', $traitsArray);
                $traitsText = implode(', ', $traitsFormatted);
                ?>
                <p><?php echo htmlspecialchars($dog['name']); ?> is a wonderful <?php echo htmlspecialchars($dog['breed']); ?> who is <?php echo htmlspecialchars($dog['age']); ?>. 
                <?php echo htmlspecialchars($dog['name']); ?> has a unique personality and is known to be <?php echo htmlspecialchars($traitsText); ?>.</p>
                
                <p>This <?php echo htmlspecialchars($dog['breed']); ?> would make a perfect addition to your family. 
                Don't miss the opportunity to welcome <?php echo htmlspecialchars($dog['name']); ?> into your home!</p>
            </div>
        </div>
        
        <!-- Similar Dogs Section -->
        <?php if (!empty($similarDogs)): ?>
            <div class="similar-dogs">
                <h3>Similar Dogs</h3>
                <div class="row">
                    <?php foreach ($similarDogs as $similarDog): ?>
                        <div class="col-md-3 col-sm-6">
                            <a href="dog_details.php?id=<?php echo $similarDog['dog_id']; ?>" class="text-decoration-none">
                                <div class="similar-dog-card">
                                    <?php if (!empty($similarDog['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($similarDog['image_url']); ?>" class="similar-dog-image" alt="<?php echo htmlspecialchars($similarDog['name']); ?>">
                                    <?php else: ?>
                                        <div class="similar-dog-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-dog fa-2x text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="similar-dog-info">
                                        <h4 class="similar-dog-name"><?php echo htmlspecialchars($similarDog['name']); ?></h4>
                                        <p class="similar-dog-breed"><?php echo htmlspecialchars($similarDog['age']); ?></p>
                                        <div class="similar-dog-price">$<?php echo number_format($similarDog['price'], 2); ?></div>
                                        <button class="btn btn-sm btn-outline-primary">View Details</button>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add to Collection Modal -->
    <div class="modal fade" id="addToCollectionModal" tabindex="-1" aria-labelledby="addToCollectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addToCollectionModalLabel">Add <?php echo htmlspecialchars($dog['name']); ?> to Your Collection</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="dog_details.php?id=<?php echo $dog_id; ?>" method="post">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any special notes about this dog"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_to_collection" class="btn btn-primary">Add to Collection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
