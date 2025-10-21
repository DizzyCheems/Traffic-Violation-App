<?php
session_start();
include '../config/conn.php';

// Initialize toastr messages
$toastr_messages = [];
$violations = [];

// Handle plate number search (from form or URL query parameter)
$plate_number = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_violations'])) {
    $plate_number = trim($_POST['plate_number'] ?? '');
} elseif (isset($_GET['plate_number'])) {
    $plate_number = trim($_GET['plate_number'] ?? '');
}

if ($plate_number) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.id, v.violator_name, v.plate_number, v.reason, t.violation_type, t.fine_amount, 
                   v.issued_date, v.has_license, v.is_impounded, v.is_paid, v.status, v.notes
            FROM violations v 
            JOIN types t ON v.violation_type_id = t.id 
            WHERE v.plate_number = ?
            ORDER BY v.issued_date DESC
        ");
        $stmt->execute([$plate_number]);
        $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($violations)) {
            $toastr_messages[] = "toastr.info('No violations found for plate number: " . htmlspecialchars($plate_number) . "');";
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error fetching violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Fetch Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Portal - Violation Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/dashboards.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* Navigation link styles for sidebar and modules */
        .nav-link {
            transition: all 0.3s ease;
            border-radius: 5px;
            padding: 10px 15px;
            margin: 5px 0;
        }

        /* Hover effect: float, black background, white bold text */
        .nav-link:hover {
            background-color: #000000;
            color: #ffffff !important;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Active link style to maintain consistency */
        .nav-link.active {
            background-color: #007bff;
            color: #ffffff !important;
            font-weight: bold;
        }

        /* Ensure modal trigger links also get the style */
        a[data-bs-toggle="modal"] {
            transition: all 0.3s ease;
            border-radius: 5px;
            padding: 8px 12px;
            display: inline-block;
        }

        a[data-bs-toggle="modal"]:hover {
            background-color: #000000;
            color: #ffffff !important;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Portal specific styles */
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
        }

        .card {
            border: none;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: #4dabf7;
            color: white;
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 1.25rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .card-body p {
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: #495057;
        }

        .card-body strong {
            color: #212529;
            font-weight: 600;
        }

        .badge {
            padding: 0.4em 0.8em;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .search-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .form-control {
            border-radius: 5px;
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Button styling to match dashboard */
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            border-radius: 5px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #000000;
            border-color: #000000;
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .alert {
            border-radius: 5px;
            border: none;
        }

        .display-4 {
            font-weight: 700;
        }

        .lead {
            font-weight: 400;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-header {
                font-size: 1rem;
                padding: 1rem;
            }
            .card-body {
                padding: 1rem;
            }
            .card-body p {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="text-center mb-4 mb-md-5">
            <h1 class="display-4 text-primary">Violations Portal</h1>
            <p class="lead text-muted">Check your vehicle violations by entering your plate number</p>
        </div>

        <!-- Search Form -->
        <div class="search-card p-4">
            <form method="POST" class="row g-3 justify-content-center">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="plate_number" id="plate_number" 
                           placeholder="Enter Plate Number (e.g., ABC123)" 
                           value="<?php echo htmlspecialchars($plate_number); ?>" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="search_violations" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Violations Display -->
        <?php if (!empty($violations)): ?>
            <div class="row g-3 g-md-4">
                <?php foreach ($violations as $violation): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($violation['violation_type']); ?>
                            </div>
                            <div class="card-body">
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($violation['id']); ?></p>
                                <p><strong>Violator:</strong> <?php echo htmlspecialchars($violation['violator_name']); ?></p>
                                <p><strong>Plate:</strong> <?php echo htmlspecialchars($violation['plate_number']); ?></p>
                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($violation['reason']); ?></p>
                                <p><strong>Fine:</strong> ₱<?php echo number_format($violation['fine_amount'], 2); ?></p>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($violation['issued_date']); ?></p>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <p><strong>License:</strong><br>
                                            <span class="badge <?php echo $violation['has_license'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $violation['has_license'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>Impounded:</strong><br>
                                            <span class="badge <?php echo $violation['is_impounded'] ? 'bg-warning' : 'bg-success'; ?>">
                                                <?php echo $violation['is_impounded'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <p><strong>Paid:</strong><br>
                                            <span class="badge <?php echo $violation['is_paid'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $violation['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>Status:</strong><br>
                                            <span class="badge <?php 
                                                echo $violation['status'] === 'Pending' ? 'bg-warning' : 
                                                    ($violation['status'] === 'Resolved' ? 'bg-success' : 'bg-danger'); ?>">
                                                <?php echo htmlspecialchars($violation['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($violation['notes'] ?: 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($plate_number): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                No violations found for plate number: <strong><?php echo htmlspecialchars($plate_number); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 5000
        };
        <?php foreach ($toastr_messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>
    </script>
</body>
</html>