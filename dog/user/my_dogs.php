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

// Handle dog deletion
if(isset($_GET['delete']) && isset($_GET['dog_id'])) {
    $dog_id = (int)$_GET['dog_id'];
    
    // Make sure the dog belongs to this user
    $checkOwnerQuery = "SELECT * FROM user_dogs WHERE dog_id = $dog_id AND user_id = $user_id";
    $checkResult = $conn->query($checkOwnerQuery);
    
    if($checkResult && $checkResult->num_rows > 0) {
        $deleteQuery = "DELETE FROM user_dogs WHERE dog_id = $dog_id AND user_id = $user_id";
        
        if($conn->query($deleteQuery)) {
            $message = '<div class="alert alert-success">Dog removed successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error removing dog: ' . $conn->error . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">You do not have permission to remove this dog.</div>';
    }
}

// Handle adoption status update
if(isset($_POST['update_status']) && isset($_POST['dog_id']) && isset($_POST['adoption_status'])) {
    $dog_id = (int)$_POST['dog_id'];
    $status = $conn->real_escape_string($_POST['adoption_status']);
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    
    // Make sure the dog belongs to this user
    $checkOwnerQuery = "SELECT * FROM user_dogs WHERE dog_id = $dog_id AND user_id = $user_id";
    $checkResult = $conn->query($checkOwnerQuery);
    
    if($checkResult && $checkResult->num_rows > 0) {
        // Set adoption_date if status is changed to Adopted
        $adoptionDateSql = ($status == 'Adopted') ? ", adoption_date = NOW()" : "";
        
        $updateQuery = "UPDATE user_dogs SET adoption_status = '$status', notes = '$notes' $adoptionDateSql WHERE dog_id = $dog_id AND user_id = $user_id";
        
        if($conn->query($updateQuery)) {
            $message = '<div class="alert alert-success">Dog status updated successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error updating dog status: ' . $conn->error . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">You do not have permission to update this dog.</div>';
    }
}

// Get filter from query parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build WHERE clause based on filter
$filterClause = "WHERE ud.user_id = $user_id";
if($filter === 'available') {
    $filterClause .= " AND ud.adoption_status = 'Available'";
} elseif($filter === 'adopted') {
    $filterClause .= " AND ud.adoption_status = 'Adopted'";
} elseif($filter === 'pending') {
    $filterClause .= " AND ud.adoption_status = 'Pending'";
}

// Get user's dogs with proper joins and filters
$dogsQuery = "SELECT ud.*, d.name, d.breed, d.age, d.trait, d.image_url, d.price 
              FROM user_dogs ud 
              JOIN dogs d ON ud.dog_id = d.dog_id 
              $filterClause
              ORDER BY ud.created_at DESC";
$dogsResult = $conn->query($dogsQuery);

$dogs = [];
if($dogsResult && $dogsResult->num_rows > 0) {
    while($row = $dogsResult->fetch_assoc()) {
        $dogs[] = $row;
    }
}

// Add a new dog to the user's collection
if(isset($_POST['add_dog']) && !empty($_POST['dog_id'])) {
    $dog_id = (int)$_POST['dog_id'];
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $adoption_status = $conn->real_escape_string($_POST['adoption_status'] ?? 'Available');
    
    // Check if the dog exists
    $checkDogQuery = "SELECT * FROM dogs WHERE dog_id = $dog_id";
    $checkResult = $conn->query($checkDogQuery);
    
    if($checkResult && $checkResult->num_rows > 0) {
        // Check if the user already has this dog
        $checkUserDogQuery = "SELECT * FROM user_dogs WHERE user_id = $user_id AND dog_id = $dog_id";
        $userDogResult = $conn->query($checkUserDogQuery);
        
        if($userDogResult && $userDogResult->num_rows > 0) {
            $message = '<div class="alert alert-warning">You already have this dog in your collection.</div>';
        } else {
            // Add the dog to the user's collection
            $insertQuery = "INSERT INTO user_dogs (user_id, dog_id, adoption_status, notes) 
                           VALUES ($user_id, $dog_id, '$adoption_status', '$notes')";
            
            if($conn->query($insertQuery)) {
                $message = '<div class="alert alert-success">Dog added to your collection!</div>';
                
                // Refresh the page to show the new dog
                header("Location: my_dogs.php");
                exit;
            } else {
                $message = '<div class="alert alert-danger">Error adding dog: ' . $conn->error . '</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Dog not found.</div>';
    }
}

// Get available dogs for adding to collection
$availableDogsQuery = "SELECT * FROM dogs WHERE dog_id NOT IN (
                        SELECT dog_id FROM user_dogs WHERE user_id = $user_id
                       ) ORDER BY name";
$availableDogsResult = $conn->query($availableDogsQuery);

$available_dogs = [];
if($availableDogsResult && $availableDogsResult->num_rows > 0) {
    while($row = $availableDogsResult->fetch_assoc()) {
        $available_dogs[] = $row;
    }
}

// Get adoption status counts for filter badges
$statusCounts = [
    'all' => 0,
    'available' => 0,
    'adopted' => 0,
    'pending' => 0
];

$countQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN adoption_status = 'Available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN adoption_status = 'Adopted' THEN 1 ELSE 0 END) as adopted,
                SUM(CASE WHEN adoption_status = 'Pending' THEN 1 ELSE 0 END) as pending
               FROM user_dogs 
               WHERE user_id = $user_id";

$countResult = $conn->query($countQuery);
if($countResult && $countResult->num_rows > 0) {
    $counts = $countResult->fetch_assoc();
    $statusCounts['all'] = $counts['total'];
    $statusCounts['available'] = $counts['available'];
    $statusCounts['adopted'] = $counts['adopted'];
    $statusCounts['pending'] = $counts['pending'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dogs - Doghouse Market</title>
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
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-title {
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .card-text {
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .text-primary {
            color: #ff6b6b !important;
        }
        
        .badge {
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .badge-primary {
            background-color: #ff6b6b;
        }
        
        .badge-available {
            background-color: #28a745;
            color: white;
        }
        
        .badge-adopted {
            background-color: #007bff;
            color: white;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
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
        
        .no-dogs-message {
            text-align: center;
            padding: 50px 0;
        }
        
        .no-dogs-message i {
            font-size: 60px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .no-dogs-message h4 {
            margin-bottom: 10px;
            color: #6c757d;
        }
        
        .dog-traits {
            margin-bottom: 15px;
        }
        
        .add-dog-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #ff6b6b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 100;
            border: none;
            transition: all 0.3s ease;
        }
        
        .add-dog-btn:hover {
            transform: scale(1.1);
            background-color: #ff5252;
        }
        
        .dog-card-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
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
            font-size: 18px;
            font-weight: 700;
            color: #28a745;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-btn.active {
            background-color: #ff6b6b;
            color: white;
        }
        
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            z-index: 10;
        }
        
        .adoption-date {
            font-size: 13px;
            color: #6c757d;
            font-style: italic;
        }
        
        .notes-container {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
        }

        @media (max-width: 767.98px) {
            body {
                margin-left: 0; /* Remove sidebar margin on mobile */
            }
            .container {
                margin-top: 20px;
            }
            .filter-bar {
                justify-content: center;
            }
            .dog-card-actions {
                flex-direction: column;
                gap: 10px;
            }
            .dog-card-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidenav.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">My Dogs</h1>
                    <p class="page-subtitle">Manage your canine companions</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary d-none d-md-block">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Display message if any -->
        <?php echo $message; ?>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <a href="my_dogs.php" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?> filter-btn">
                All <span class="badge badge-light"><?php echo $statusCounts['all']; ?></span>
            </a>
            <a href="my_dogs.php?filter=available" class="btn <?php echo $filter === 'available' ? 'btn-primary' : 'btn-outline-secondary'; ?> filter-btn">
                Available <span class="badge badge-light"><?php echo $statusCounts['available']; ?></span>
            </a>
            <a href="my_dogs.php?filter=adopted" class="btn <?php echo $filter === 'adopted' ? 'btn-primary' : 'btn-outline-secondary'; ?> filter-btn">
                Adopted <span class="badge badge-light"><?php echo $statusCounts['adopted']; ?></span>
            </a>
            <a href="my_dogs.php?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-outline-secondary'; ?> filter-btn">
                Pending <span class="badge badge-light"><?php echo $statusCounts['pending']; ?></span>
            </a>
        </div>
        
        <!-- Dogs Grid -->
        <?php if (!empty($dogs)): ?>
            <div class="row">
                <?php foreach ($dogs as $dog): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card h-100 position-relative">
                            <!-- Status Badge -->
                            <span class="status-badge badge-<?php echo strtolower($dog['adoption_status']); ?>">
                                <?php echo $dog['adoption_status']; ?>
                            </span>
                            
                            <?php if (!empty($dog['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($dog['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-dog fa-3x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($dog['name']); ?></h5>
                                <p class="card-text mb-1"><strong>Breed:</strong> <?php echo htmlspecialchars($dog['breed']); ?></p>
                                <p class="card-text"><strong>Age:</strong> <?php echo htmlspecialchars($dog['age']); ?></p>
                                
                                <!-- Show adoption date if adopted -->
                                <?php if($dog['adoption_status'] == 'Adopted' && !empty($dog['adoption_date'])): ?>
                                <p class="adoption-date">
                                    <i class="fas fa-calendar-check mr-1"></i> Adopted on <?php echo date('M d, Y', strtotime($dog['adoption_date'])); ?>
                                </p>
                                <?php endif; ?>
                                
                                <div class="dog-traits">
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
                                
                                <div class="dog-price">$<?php echo number_format($dog['price'], 2); ?></div>
                                
                                <!-- Show notes if any -->
                                <?php if (!empty($dog['notes'])): ?>
                                    <div class="notes-container">
                                        <strong><i class="fas fa-sticky-note mr-1"></i> Notes:</strong>
                                        <p class="mb-0"><?php echo htmlspecialchars($dog['notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="dog-card-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-toggle="modal" 
                                            data-target="#updateStatusModal" 
                                            data-dog-id="<?php echo $dog['dog_id']; ?>"
                                            data-dog-name="<?php echo htmlspecialchars($dog['name']); ?>"
                                            data-status="<?php echo $dog['adoption_status']; ?>"
                                            data-notes="<?php echo htmlspecialchars($dog['notes'] ?? ''); ?>">
                                        <i class="fas fa-pencil-alt mr-1"></i> Update Status
                                    </button>
                                    
                                    <a href="my_dogs.php?delete=1&dog_id=<?php echo $dog['dog_id']; ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to remove this dog from your collection?')">
                                        <i class="fas fa-trash mr-1"></i> Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-dogs-message">
                <i class="fas fa-dog"></i>
                <h4>You don't have any dogs in this category</h4>
                <p class="text-muted">Add a dog to your collection by clicking the button below.</p>
                <button class="btn btn-primary" data-toggle="modal" data-target="#addDogModal">
                    <i class="fas fa-plus mr-2"></i> Add Dog
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Floating Add Button (visible only if user has dogs) -->
        <?php if (!empty($dogs)): ?>
            <button class="add-dog-btn" data-toggle="modal" data-target="#addDogModal" title="Add Dog">
                <i class="fas fa-plus"></i>
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Add Dog Modal -->
    <div class="modal fade" id="addDogModal" tabindex="-1" aria-labelledby="addDogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDogModalLabel">Add Dog to Collection</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($available_dogs)): ?>
                        <form action="my_dogs.php" method="post">
                            <div class="form-group">
                                <label for="dog_id">Select a Dog</label>
                                <select class="form-control" id="dog_id" name="dog_id" required>
                                    <option value="">-- Select Dog --</option>
                                    <?php foreach ($available_dogs as $dog): ?>
                                        <option value="<?php echo $dog['dog_id']; ?>"><?php echo htmlspecialchars($dog['name']); ?> (<?php echo htmlspecialchars($dog['breed']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="adoption_status">Adoption Status</label>
                                <select class="form-control" id="adoption_status" name="adoption_status">
                                    <option value="Available">Available</option>
                                    <option value="Adopted">Adopted</option>
                                    <option value="Pending">Pending</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Notes (optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any special notes about this dog"></textarea>
                            </div>
                            
                            <button type="submit" name="add_dog" class="btn btn-primary">Add to My Collection</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
                            <h5>No dogs available</h5>
                            <p>There are no more dogs available to add to your collection at this time.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Update Dog Status</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="my_dogs.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="dog_id" id="update_dog_id">
                        
                        <div class="form-group">
                            <label for="update_adoption_status">Adoption Status</label>
                            <select class="form-control" id="update_adoption_status" name="adoption_status">
                                <option value="Available">Available</option>
                                <option value="Adopted">Adopted</option>
                                <option value="Pending">Pending</option>
                            </select>
                            <small class="form-text text-muted">
                                Note: Setting to "Adopted" will record today's date as the adoption date.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="update_notes">Notes</label>
                            <textarea class="form-control" id="update_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle update status modal
        $('#updateStatusModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const dogId = button.data('dog-id');
            const dogName = button.data('dog-name');
            const status = button.data('status');
            const notes = button.data('notes');
            
            const modal = $(this);
            modal.find('.modal-title').text('Update Status: ' + dogName);
            modal.find('#update_dog_id').val(dogId);
            modal.find('#update_adoption_status').val(status);
            modal.find('#update_notes').val(notes);
        });
    </script>
</body>
</html>
