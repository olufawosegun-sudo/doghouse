<?php
session_start();

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'doghousemarket';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get dog ID from URL
$dog_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$dog_id) {
    header("Location: dogs.php");
    exit;
}

// Fetch dog details
$dogQuery = "SELECT * FROM dogs WHERE dog_id = $dog_id";
$dogResult = $conn->query($dogQuery);

if (!$dogResult || $dogResult->num_rows === 0) {
    header("Location: dogs.php");
    exit;
}

$dog = $dogResult->fetch_assoc();

// Fetch company information
$companyInfo = [];
$companyQuery = "SELECT * FROM company_info LIMIT 1";
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
}

// Get similar dogs (same breed, excluding current dog)
$similarDogs = [];
$similarQuery = "SELECT * FROM dogs WHERE breed = '{$dog['breed']}' AND dog_id != $dog_id LIMIT 3";
$similarResult = $conn->query($similarQuery);

if ($similarResult && $similarResult->num_rows > 0) {
    while ($row = $similarResult->fetch_assoc()) {
        $similarDogs[] = $row;
    }
}

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dog['name']); ?> - <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --theme-color: <?php echo htmlspecialchars($companyInfo['color'] ?? '#ffa500'); ?>;
            --theme-color-light: #ffb733;
            --theme-color-dark: #e69400;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 70px;
        }
        
        /* Navbar */
        .navbar {
            padding: 15px 0;
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--theme-color) !important;
            display: flex;
            align-items: center;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
            margin-right: 10px;
            object-fit: contain;
        }
        
        /* Dog Details */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 15px;
        }
        
        .dog-details-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .dog-image-container {
            position: relative;
            height: 500px;
            overflow: hidden;
            background-color: #f8f9fa;
        }
        
        .dog-main-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
            cursor: pointer;
            z-index: 10;
        }
        
        .back-btn:hover {
            transform: scale(1.1);
            background-color: var(--theme-color);
            color: white;
        }
        
        .dog-content {
            padding: 40px;
        }
        
        .dog-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .dog-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .dog-id {
            color: #6c757d;
            font-size: 14px;
        }
        
        .dog-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--theme-color);
        }
        
        .dog-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--theme-color);
        }
        
        .info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
        }
        
        .info-label i {
            margin-right: 8px;
            color: var(--theme-color);
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .dog-description {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--theme-color);
        }
        
        .traits-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .trait-badge {
            background: linear-gradient(135deg, var(--theme-color), var(--theme-color-light));
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-order {
            flex: 1;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-order:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .similar-dogs-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .similar-dog-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s;
            height: 100%;
        }
        
        .similar-dog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .similar-dog-image {
            height: 200px;
            overflow: hidden;
        }
        
        .similar-dog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .similar-dog-card:hover .similar-dog-image img {
            transform: scale(1.1);
        }
        
        .similar-dog-content {
            padding: 15px;
        }
        
        .similar-dog-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .similar-dog-info {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .similar-dog-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--theme-color);
            margin-bottom: 10px;
        }
        
        @media (max-width: 767.98px) {
            .dog-image-container {
                height: 300px;
            }
            
            .dog-content {
                padding: 20px;
            }
            
            .dog-title {
                font-size: 1.8rem;
            }
            
            .dog-price {
                font-size: 1.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .dog-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .dog-price {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php if (!empty($companyInfo['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($companyInfo['logo']); ?>" alt="Logo" class="navbar-logo">
                <?php endif; ?>
                <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>
            </a>
        </div>
    </nav>
    
    <div class="container">
        <div class="dog-details-card">
            <div class="dog-image-container">
                <button class="back-btn" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <?php if (!empty($dog['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($dog['image_url']); ?>" class="dog-main-image" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                <?php else: ?>
                    <img src="https://via.placeholder.com/800x500?text=No+Image" class="dog-main-image" alt="No Image">
                <?php endif; ?>
            </div>
            
            <div class="dog-content">
                <div class="dog-header">
                    <div>
                        <h1 class="dog-title"><?php echo htmlspecialchars($dog['name']); ?></h1>
                        <p class="dog-id">Dog ID: #<?php echo $dog['dog_id']; ?></p>
                    </div>
                    <div class="dog-price">$<?php echo number_format($dog['price'], 2); ?></div>
                </div>
                
                <div class="dog-info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-paw"></i> Breed</div>
                        <div class="info-value"><?php echo htmlspecialchars($dog['breed']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-birthday-cake"></i> Age</div>
                        <div class="info-value"><?php echo htmlspecialchars($dog['age']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar-alt"></i> Listed On</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime($dog['created_at'])); ?></div>
                    </div>
                </div>
                
                <div class="dog-description">
                    <h3 class="section-title"><i class="fas fa-star"></i> Traits & Characteristics</h3>
                    <div class="traits-container">
                        <?php 
                        $traits = explode(',', $dog['trait']);
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
                
                <div class="action-buttons">
                    <?php if ($loggedIn): ?>
                        <a href="user/view_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-success btn-order">
                            <i class="fas fa-shopping-cart mr-2"></i> Place Order
                        </a>
                    <?php else: ?>
                        <a href="signin.php?redirect=view_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-success btn-order">
                            <i class="fas fa-sign-in-alt mr-2"></i> Sign In to Order
                        </a>
                    <?php endif; ?>
                    <a href="dogs.php" class="btn btn-outline-secondary btn-order">
                        <i class="fas fa-search mr-2"></i> Browse More Dogs
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (!empty($similarDogs)): ?>
        <div class="similar-dogs-section">
            <h3 class="section-title"><i class="fas fa-dog"></i> Similar Dogs</h3>
            <div class="row">
                <?php foreach ($similarDogs as $similar): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="similar-dog-card">
                        <div class="similar-dog-image">
                            <?php if (!empty($similar['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($similar['image_url']); ?>" alt="<?php echo htmlspecialchars($similar['name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/400x300?text=No+Image" alt="No Image">
                            <?php endif; ?>
                        </div>
                        <div class="similar-dog-content">
                            <h4 class="similar-dog-name"><?php echo htmlspecialchars($similar['name']); ?></h4>
                            <p class="similar-dog-info">
                                <i class="fas fa-paw"></i> <?php echo htmlspecialchars($similar['breed']); ?><br>
                                <i class="fas fa-birthday-cake"></i> <?php echo htmlspecialchars($similar['age']); ?>
                            </p>
                            <div class="similar-dog-price">$<?php echo number_format($similar['price'], 2); ?></div>
                            <a href="view_dog.php?id=<?php echo $similar['dog_id']; ?>" class="btn btn-primary btn-sm btn-block">
                                <i class="fas fa-eye mr-2"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
