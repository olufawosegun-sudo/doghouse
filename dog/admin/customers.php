<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once __DIR__ . '/../includes/config.php';

// Get search parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Prepare base query matching the actual table structure
$query = "SELECT c.*, 
          COUNT(DISTINCT o.id) as total_orders,
          COALESCE(SUM(o.total_amount), 0) as total_spent,
          MAX(o.order_date) as last_order_date
          FROM customers c 
          LEFT JOIN orders o ON c.id = o.user_id 
          WHERE 1=1";

// Add search condition
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND c.user_id IN (SELECT id FROM users WHERE username LIKE '%$search%' OR email LIKE '%$search%')";
}

// Add status condition
if ($status !== 'all') {
    $status = mysqli_real_escape_string($conn, $status);
    $query .= " AND c.status = '$status'";
}

$query .= " GROUP BY c.id ORDER BY c.id DESC";

// Execute query with error handling
$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$customers = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Get user details for each customer
    $userQuery = "SELECT username, email FROM users WHERE id = " . $row['user_id'];
    $userResult = mysqli_query($conn, $userQuery);
    $userInfo = mysqli_fetch_assoc($userResult);
    if ($userInfo) {
        $row = array_merge($row, $userInfo);
    } else {
        $row['username'] = 'Unknown User';
        $row['email'] = 'No Email';
    }
    $customers[] = $row;
}

// Include the shared sidebar
require_once __DIR__ . '/../includes/sidenav.php';

// Page title
$pageTitle = "Customer Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Doghouse Market Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #6f42c1;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --background-color: #f8f9fc;
            --card-border-color: #e3e6f0;
            --text-color: #444;
            --text-muted: #858796;
        }
        
        body {
            background-color: var(--background-color);
            color: var(--text-color);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .main-content {
            padding: 1.5rem 2rem;
            margin-left: 250px; /* Match sidebar width */
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        .page-header {
            margin-bottom: 1.5rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .stats-row {
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 8px;
            border-left: 4px solid;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card.primary {
            border-left-color: var(--primary-color);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-card.info {
            border-left-color: var(--info-color);
        }
        
        .stat-card .stat-title {
            text-transform: uppercase;
            margin-bottom: 0.25rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        
        .stat-card .stat-value {
            color: var(--dark-color);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 0;
        }
        
        .stat-card .stat-icon {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            opacity: 0.25;
        }
        
        .content-card {
            background: #fff;
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .content-card .card-header {
            padding: 1rem 1.25rem;
            background-color: white;
            border-bottom: 1px solid var(--card-border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .content-card .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }
        
        .content-card .card-body {
            padding: 1.25rem;
        }
        
        .search-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        
        .search-controls .form-control,
        .search-controls .form-select {
            border-radius: 4px;
            border-color: var(--card-border-color);
        }
        
        .search-controls .form-control:focus,
        .search-controls .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
            border-color: #bac8f3;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #4262c5;
            border-color: #3d5cb9;
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        table.dataTable thead th {
            border-bottom: 1px solid var(--card-border-color);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            vertical-align: middle;
            padding: 0.75rem;
            color: var(--dark-color);
        }
        
        table.dataTable tbody td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            border-radius: 4px;
        }
        
        .badge-active {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-inactive {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
            margin: 0 2px;
        }
        
        .btn-group .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            margin: 0 3px;
        }
        
        .add-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }
        
        @media (max-width: 767.98px) {
            .stats-row .col-md-4 {
                margin-bottom: 1rem;
            }
            
            .search-controls {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .search-controls .form-control,
            .search-controls .form-select {
                width: 100%;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }
            
            .d-flex.justify-content-between .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Customer Management</h1>
            <p class="page-subtitle">Manage and monitor your customer accounts</p>
        </div>
        
        <!-- Stats Row -->
        <div class="row stats-row">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="stat-title">Total Customers</div>
                    <div class="stat-value"><?php echo count($customers); ?></div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <div class="stat-title">Active Customers</div>
                    <div class="stat-value"><?php echo count(array_filter($customers, function($c) { return $c['status'] === 'Active'; })); ?></div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card info">
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-value">₱<?php echo number_format(array_sum(array_column($customers, 'total_spent')), 2); ?></div>
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Card -->
        <div class="card content-card">
            <div class="card-header">
                <h5 class="card-title"><i class="fas fa-users me-2"></i>Customer List</h5>
                <button class="btn btn-primary add-button" onclick="location.href='add_customer.php'">
                    <i class="fas fa-plus"></i> Add Customer
                </button>
            </div>
            <div class="card-body">
                <!-- Search Controls -->
                <div class="search-controls">
                    <input type="text" class="form-control" id="searchCustomer" placeholder="Search customer by name or email...">
                    <select class="form-select" id="statusFilter">
                        <option value="all">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                
                <!-- Customer Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="customersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Last Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['id']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar rounded-circle bg-light text-dark me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: 600;">
                                            <?php echo strtoupper(substr($customer['username'], 0, 1)); ?>
                                        </div>
                                        <div><?php echo htmlspecialchars($customer['username']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $customer['status'] == 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $customer['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $customer['total_orders']; ?></td>
                                <td>₱<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td><?php echo $customer['last_order_date'] ? date('M d, Y', strtotime($customer['last_order_date'])) : 'Never'; ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-action" onclick="viewCustomer(<?php echo $customer['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-action" onclick="editCustomer(<?php echo $customer['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-<?php echo $customer['status'] == 'Active' ? 'danger' : 'success'; ?> btn-action" 
                                                onclick="toggleStatus(<?php echo $customer['id']; ?>, '<?php echo $customer['status']; ?>')" 
                                                title="<?php echo $customer['status'] == 'Active' ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-<?php echo $customer['status'] == 'Active' ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Required JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable with better configuration
        const table = $('#customersTable').DataTable({
            pageLength: 10,
            responsive: true,
            dom: '<"top"fl>rt<"bottom"ip><"clear">',
            language: {
                search: '',
                searchPlaceholder: 'Search...',
                lengthMenu: '_MENU_ records per page',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    previous: '<i class="fas fa-angle-left"></i>',
                    next: '<i class="fas fa-angle-right"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>'
                }
            },
            initComplete: function() {
                $('.dataTables_filter input').addClass('form-control');
                $('.dataTables_length select').addClass('form-select');
            }
        });

        // Custom search box functionality
        $('#searchCustomer').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Custom status filter
        $('#statusFilter').on('change', function() {
            const status = this.value;
            if (status === 'all') {
                table.column(3).search('').draw();
            } else {
                table.column(3).search(status).draw();
            }
        });
    });

    function viewCustomer(id) {
        window.location.href = 'customer_details.php?id=' + id;
    }

    function editCustomer(id) {
        window.location.href = 'edit_customer.php?id=' + id;
    }

    function toggleStatus(id, currentStatus) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to ' + (currentStatus == 'Active' ? 'deactivate' : 'activate') + ' this customer?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: currentStatus == 'Active' ? '#e74a3b' : '#1cc88a',
            cancelButtonColor: '#6c757d',
            confirmButtonText: currentStatus == 'Active' ? 'Yes, deactivate' : 'Yes, activate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'toggle_customer_status.php?id=' + id;
            }
        });
    }
    </script>

    <!-- SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
