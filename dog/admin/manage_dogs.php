<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

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

// Create dogs table if not exists
$sql = "CREATE TABLE IF NOT EXISTS dogs (
    dog_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    breed VARCHAR(100) NOT NULL,
    age INT(3) NOT NULL,
    trait VARCHAR(255),
    image_url VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    echo "Error creating table: " . $conn->error;
}

// Initialize variables
$dog_id = $name = $breed = $age = $trait = $image_url = $price = '';
$error = $success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = $conn->real_escape_string($_POST['name']);
    $breed = $conn->real_escape_string($_POST['breed']);
    $age = (int)$_POST['age'];
    $trait = $conn->real_escape_string($_POST['trait']);
    $price = (float)$_POST['price'];
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_dir = '../images/';
        
        // Create upload directory if not exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $image_name;
        
        // Check file type
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // File uploaded successfully - store relative path
                $image_url = 'images/' . $image_name;
            } else {
                $error = "Error uploading file.";
            }
        } else {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    // Add new dog
    if ($_POST['form_action'] == 'add') {
        // Use the existing image_url if no new image is uploaded
        if (empty($image_url)) {
            $sql = "INSERT INTO dogs (name, breed, age, trait, price) 
                    VALUES ('$name', '$breed', $age, '$trait', $price)";
        } else {
            $sql = "INSERT INTO dogs (name, breed, age, trait, image_url, price) 
                    VALUES ('$name', '$breed', $age, '$trait', '$image_url', $price)";
        }
                
        if ($conn->query($sql) === TRUE) {
            $success = "New dog listing added successfully!";
            $name = $breed = $age = $trait = $image_url = $price = '';
        } else {
            $error = "Error: " . $sql . "<br>" . $conn->error;
        }
    }
    
    // Update existing dog
    if ($_POST['form_action'] == 'edit') {
        $dog_id = (int)$_POST['dog_id'];
        
        if (empty($image_url)) {
            $sql = "UPDATE dogs SET name='$name', breed='$breed', age=$age, trait='$trait', 
                    price=$price, updated_at=CURRENT_TIMESTAMP 
                    WHERE dog_id=$dog_id";
        } else {
            $sql = "UPDATE dogs SET name='$name', breed='$breed', age=$age, trait='$trait', 
                    image_url='$image_url', price=$price, updated_at=CURRENT_TIMESTAMP 
                    WHERE dog_id=$dog_id";
        }
                
        if ($conn->query($sql) === TRUE) {
            $success = "Dog listing updated successfully!";
        } else {
            $error = "Error: " . $sql . "<br>" . $conn->error;
        }
        
        // Reset to list view
        $action = 'list';
    }
}

// Process deletions
if ($action == 'delete' && isset($_GET['id'])) {
    $dog_id = (int)$_GET['id'];
    
    // Get image filename first to delete the file
    $sql = "SELECT image_url FROM dogs WHERE dog_id=$dog_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['image_url'])) {
            $image_path = '../images/' . $row['image_url'];
            if (file_exists($image_path)) {
                unlink($image_path); // Delete the image file
            }
        }
    }
    
    // Delete the record
    $sql = "DELETE FROM dogs WHERE dog_id=$dog_id";
    
    if ($conn->query($sql) === TRUE) {
        $success = "Dog listing deleted successfully!";
    } else {
        $error = "Error deleting record: " . $conn->error;
    }
    
    $action = 'list';
}

// Load data for editing
if ($action == 'edit' && isset($_GET['id'])) {
    $dog_id = (int)$_GET['id'];
    $sql = "SELECT * FROM dogs WHERE dog_id=$dog_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row['name'];
        $breed = $row['breed'];
        $age = $row['age'];
        $trait = $row['trait'];
        $image_url = $row['image_url'];
        $price = $row['price'];
    } else {
        $error = "Dog not found";
        $action = 'list';
    }
}

// Get all dogs for listing
$dogs = array();
$sql = "SELECT * FROM dogs ORDER BY dog_id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dogs[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Dogs | Doghouse Market Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #ffa500;
            --primary-dark: #e69500;
            --primary-light: #ffb733;
            --secondary-color: #343a40;
            --accent-color: #ff7e33;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --danger-color: #dc3545;
            --text-color: #343a40;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--text-color);
            padding-top: 56px;
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover {
            color: white !important;
        }
        
        .navbar-dark .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 20px 0;
            width: 250px;
            background-color: white;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar-sticky {
            padding-top: 1rem;
        }
        
        .sidebar .nav-link {
            color: var(--text-color);
            padding: 0.75rem 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(255, 165, 0, 0.1);
            border-left: 3px solid var(--primary-color);
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-success {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .btn-success:hover, .btn-success:focus {
            background-color: #e67730;
            border-color: #e67730;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table td, .table th {
            vertical-align: middle;
        }
        
        .dog-img-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 165, 0, 0.25);
        }
        
        .required-field::after {
            content: "*";
            color: var(--danger-color);
            margin-left: 4px;
        }
        
        .img-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid var(--border-color);
            padding: 3px;
        }
        
        /* Enhanced mobile responsiveness */
        @media (max-width: 991.98px) {
            .sidebar {
                width: 75px;
            }
            
            .sidebar .nav-link {
                padding: 0.75rem;
                text-align: center;
                justify-content: center;
            }
            
            .sidebar .nav-link i {
                margin-right: 0;
                font-size: 18px;
            }
            
            .sidebar .nav-link span {
                display: none;
            }
            
            .main-content {
                margin-left: 75px;
            }
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                top: 56px;
                left: -250px;
                width: 250px;
                height: 100%;
                z-index: 1030;
                transition: all 0.3s;
                box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .sidebar .nav-link span {
                display: inline;
            }
            
            .sidebar .nav-link i {
                margin-right: 10px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header h1 {
                margin-bottom: 10px;
            }
            
            .sidebar-toggler {
                display: block;
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background-color: var(--primary-color);
                color: white;
                border: none;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
                z-index: 1040;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1020;
            }
        }
        
        @media (max-width: 575.98px) {
            .dog-table-responsive .table {
                min-width: 700px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .row {
                margin-right: -10px;
                margin-left: -10px;
            }
            
            .col-md-6 {
                padding-right: 10px;
                padding-left: 10px;
            }
            
            .dog-mobile-card {
                display: flex;
                flex-direction: column;
                margin-bottom: 15px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                overflow: hidden;
            }
            
            .dog-mobile-header {
                display: flex;
                align-items: center;
                padding: 10px;
                background-color: #f8f9fa;
                border-bottom: 1px solid var(--border-color);
            }
            
            .dog-mobile-thumb {
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 4px;
                margin-right: 10px;
            }
            
            .dog-mobile-title {
                font-weight: 600;
                margin: 0;
            }
            
            .dog-mobile-body {
                padding: 10px;
            }
            
            .dog-mobile-info {
                margin-bottom: 10px;
            }
            
            .dog-mobile-info .label {
                font-weight: 600;
                color: var(--text-muted);
                margin-right: 5px;
            }
            
            .dog-mobile-actions {
                display: flex;
                justify-content: flex-end;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid var(--border-color);
            }
            
            .dog-mobile-actions .btn {
                margin-left: 5px;
            }
            
            .table-view-toggle {
                display: flex;
                justify-content: flex-end;
                margin-bottom: 10px;
            }
        }
        
        /* Dog Card Styles */
        .dog-card {
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .dog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .dog-card .card-header {
            border-bottom: 1px solid var(--border-color);
        }
        
        .dog-card-img {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .dog-card:hover .dog-card-img {
            transform: scale(1.05);
        }
        
        .dog-details p {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .dog-card .card-footer {
            border-top: 1px solid var(--border-color);
            padding: 0.75rem 1.25rem;
        }
        
        /* View toggle buttons */
        .table-view-toggle .btn.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top navbar-expand-lg">
        <a class="navbar-brand" href="dashboard.php">
            <?php if (!empty($companyInfo) && !empty($companyInfo['logo'])): ?>
                <?php
                // Fix the logo path by prepending "../" to access the file in parent directory
                $logoPath = str_replace('images/', '../images/', $companyInfo['logo']);
                ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" style="height: 30px; width: auto; margin-right: 10px;">
            <?php else: ?>
                <i class="fas fa-paw mr-2"></i>
            <?php endif; ?>
            Doghouse Market
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-1"></i>
                        <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user-cog mr-2"></i>Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Mobile Sidebar Toggle Button -->
    <button class="sidebar-toggler d-md-none" id="sidebarToggler">
        <i class="fas fa-bars"></i>
    </button>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="sidebar" id="sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_dogs.php">
                                <i class="fas fa-dog"></i>
                                <span>Manage Dogs</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="main-content">
                <div class="page-header">
                    <h1 class="h2">Manage Dogs</h1>
                    <?php if ($action == 'list'): ?>
                    <a href="../add_dog.php" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i> Add New Dog
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($action == 'list'): ?>
                <!-- Dog Listing -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list mr-2"></i> Dog Inventory</span>
                            <div class="table-view-toggle d-none d-sm-block">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" id="tableView">
                                        <i class="fas fa-table"></i> Table
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary active" id="cardView">
                                        <i class="fas fa-th"></i> Cards
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($dogs) > 0): ?>
                            <!-- Table View for larger screens -->
                            <div class="table-responsive dog-table-responsive d-none">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="60">Image</th>
                                            <th>Name</th>
                                            <th>Breed</th>
                                            <th>Age</th>
                                            <th>Trait</th>
                                            <th>Price</th>
                                            <th>Date Added</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dogs as $dog): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($dog['image_url'])): ?>
                                                        <img src="../<?php echo $dog['image_url']; ?>" class="dog-img-thumbnail">
                                                    <?php else: ?>
                                                        <img src="https://via.placeholder.com/60x60?text=No+Image" class="dog-img-thumbnail">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($dog['name']); ?></td>
                                                <td><?php echo htmlspecialchars($dog['breed']); ?></td>
                                                <td><?php echo $dog['age']; ?> years</td>
                                                <td><?php echo htmlspecialchars(substr($dog['trait'], 0, 50)) . (strlen($dog['trait']) > 50 ? '...' : ''); ?></td>
                                                <td>$<?php echo number_format($dog['price'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($dog['created_at'])); ?></td>
                                                <td>
                                                    <a href="?action=edit&id=<?php echo $dog['dog_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $dog['dog_id']; ?>" class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this dog?');">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Card View with 3 cards per row -->
                            <div class="dog-grid-view">
                                <div class="row">
                                    <?php foreach ($dogs as $dog): ?>
                                        <div class="col-lg-4 col-md-6 mb-4">
                                            <div class="card dog-card h-100">
                                                <div class="card-header bg-light">
                                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($dog['name']); ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="text-center mb-3">
                                                        <?php if (!empty($dog['image_url'])): ?>
                                                            <img src="../<?php echo $dog['image_url']; ?>" class="dog-card-img" alt="<?php echo htmlspecialchars($dog['name']); ?>" style="height: 150px; object-fit: cover; border-radius: 8px;">
                                                        <?php else: ?>
                                                            <img src="https://via.placeholder.com/300x150?text=No+Image" class="dog-card-img" alt="No Image" style="height: 150px; object-fit: cover; border-radius: 8px;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="dog-details">
                                                        <p><strong>Breed:</strong> <?php echo htmlspecialchars($dog['breed']); ?></p>
                                                        <p><strong>Age:</strong> <?php echo $dog['age']; ?> years</p>
                                                        <p><strong>Trait:</strong> <?php echo htmlspecialchars(substr($dog['trait'], 0, 100)) . (strlen($dog['trait']) > 100 ? '...' : ''); ?></p>
                                                        <p><strong>Price:</strong> <span class="text-primary">$<?php echo number_format($dog['price'], 2); ?></span></p>
                                                        <p><small class="text-muted">Added: <?php echo date('M d, Y', strtotime($dog['created_at'])); ?></small></p>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-white">
                                                    <div class="d-flex justify-content-between">
                                                        <a href="?action=edit&id=<?php echo $dog['dog_id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-edit mr-1"></i> Edit
                                                        </a>
                                                        <a href="?action=delete&id=<?php echo $dog['dog_id']; ?>" class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('Are you sure you want to delete this dog?');">
                                                            <i class="fas fa-trash-alt mr-1"></i> Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Card View for Mobile -->
                            <div class="dog-mobile-view d-block d-sm-none">
                                <?php foreach ($dogs as $dog): ?>
                                    <div class="dog-mobile-card">
                                        <div class="dog-mobile-header">
                                            <?php if (!empty($dog['image_url'])): ?>
                                                <img src="../<?php echo $dog['image_url']; ?>" class="dog-mobile-thumb">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/60x60?text=No+Image" class="dog-mobile-thumb">
                                            <?php endif; ?>
                                            <h5 class="dog-mobile-title"><?php echo htmlspecialchars($dog['name']); ?></h5>
                                        </div>
                                        <div class="dog-mobile-body">
                                            <div class="dog-mobile-info">
                                                <span class="label">Breed:</span>
                                                <span><?php echo htmlspecialchars($dog['breed']); ?></span>
                                            </div>
                                            <div class="dog-mobile-info">
                                                <span class="label">Age:</span>
                                                <span><?php echo $dog['age']; ?> years</span>
                                            </div>
                                            <div class="dog-mobile-info">
                                                <span class="label">Price:</span>
                                                <span class="text-primary font-weight-bold">$<?php echo number_format($dog['price'], 2); ?></span>
                                            </div>
                                            <div class="dog-mobile-actions">
                                                <a href="?action=edit&id=<?php echo $dog['dog_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="?action=delete&id=<?php echo $dog['dog_id']; ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this dog?');">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> No dogs found. Add your first dog listing!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <!-- Add/Edit Dog Form - uses responsive grid layout -->
                <div class="card">
                    <div class="card-header">
                        <?php if ($action == 'add'): ?>
                            <i class="fas fa-plus-circle mr-2"></i> Add New Dog
                        <?php else: ?>
                            <i class="fas fa-edit mr-2"></i> Edit Dog
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form action="manage_dogs.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="form_action" value="<?php echo $action; ?>">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="dog_id" value="<?php echo $dog_id; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="required-field">Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($name); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="breed" class="required-field">Breed</label>
                                        <input type="text" class="form-control" id="breed" name="breed"
                                               value="<?php echo htmlspecialchars($breed); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="age" class="required-field">Age (years)</label>
                                        <input type="number" class="form-control" id="age" name="age" min="0" max="20"
                                               value="<?php echo $age; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="price" class="required-field">Price ($)</label>
                                        <input type="number" class="form-control" id="price" name="price" min="0" step="0.01"
                                               value="<?php echo $price; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="trait">Traits</label>
                                <textarea class="form-control" id="trait" name="trait" rows="3" 
                                          placeholder="Enter dog traits (e.g., friendly, loyal, energetic)"><?php echo htmlspecialchars($trait); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Dog Image</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="image" name="image">
                                    <label class="custom-file-label" for="image">Choose file</label>
                                </div>
                                <small class="form-text text-muted">Accepted formats: JPG, JPEG, PNG, GIF. Max size: 5MB.</small>
                                
                                <?php if (!empty($image_url)): ?>
                                    <div class="mt-3">
                                        <p>Current image:</p>
                                        <img src="../images/dogs/<?php echo $image_url; ?>" class="img-preview">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-success mr-2">
                                    <?php if ($action == 'add'): ?>
                                        <i class="fas fa-plus-circle mr-1"></i> Add Dog
                                    <?php else: ?>
                                        <i class="fas fa-save mr-1"></i> Update Dog
                                    <?php endif; ?>
                                </button>
                                <a href="manage_dogs.php" class="btn btn-secondary">
                                    <i class="fas fa-times mr-1"></i> Cancel
                                </a>
                            </div>
                        </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mobile sidebar toggle
            $("#sidebarToggler").click(function() {
                $("#sidebar").toggleClass("active");
                $("#sidebarOverlay").fadeToggle();
            });
            
            $("#sidebarOverlay").click(function() {
                $("#sidebar").removeClass("active");
                $("#sidebarOverlay").fadeOut();
            });
            
            // View toggle (table/card)
            $("#tableView").click(function() {
                $(this).addClass("active");
                $("#cardView").removeClass("active");
                $(".dog-table-responsive").removeClass("d-none");
                $(".dog-grid-view").addClass("d-none");
            });
            
            $("#cardView").click(function() {
                $(this).addClass("active");
                $("#tableView").removeClass("active");
                $(".dog-table-responsive").addClass("d-none");
                $(".dog-grid-view").removeClass("d-none");
            });
            
            // Custom file input
            document.querySelector('.custom-file-input').addEventListener('change', function(e) {
                var label = e.target.nextElementSibling;
                var fileName = e.target.files[0].name;
                label.textContent = fileName;
            });
        });
    </script>
</body>
</html>
            $("#cardView").click(function() {
                $(this).addClass("active");
                $("#tableView").removeClass("active");
                $(".dog-table-responsive").addClass("d-none");
                $(".dog-grid-view").removeClass("d-none");
            });
            
            // Custom file input
            document.querySelector('.custom-file-input').addEventListener('change', function(e) {
                var label = e.target.nextElementSibling;
                var fileName = e.target.files[0].name;
                label.textContent = fileName;





</body>    </script>        });            });







</html></body>    </script>        });            });                $(".dog-grid-view").removeClass("d-none");                $(".dog-table-responsive").addClass("d-none");                $("#tableView").removeClass("active");                $(this).addClass("active");            $("#cardView").click(function() {                        });                $(".dog-grid-view").addClass("d-none");                $(".dog-table-responsive").removeClass("d-none");                $("#cardView").removeClass("active");                $(this).addClass("active");            $("#tableView").click(function() {            // View toggle (table/card)                        });                $("#sidebarOverlay").fadeOut();                $("#sidebar").removeClass("active");            $("#sidebarOverlay").click(function() {                        });                $("#sidebarOverlay").fadeToggle();                $("#sidebar").toggleClass("active");            $("#sidebarToggler").click(function() {        $(document).ready(function() {        // Mobile sidebar toggle                });            label.textContent = fileName;            var label = e.target.nextElementSibling;            var fileName = e.target.files[0].name;        document.querySelector('.custom-file-input').addEventListener('change', function(e) {        // Custom file input    <script>        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>        </div>        </div>            </main>                <?php endif; ?>                </div>                    </div>            });
        });
    </script>
</body>
