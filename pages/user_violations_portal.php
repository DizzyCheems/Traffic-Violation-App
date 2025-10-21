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
    <title>Violations Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            border: none;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(90deg, #007bff 0%, #00b7eb 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.25rem;
        }
        .card-body {
            padding: 1.5rem;
        }
        .card-body p {
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            color: #333;
        }
        .card-body strong {
            color: #1a1a1a;
            font-weight: 600;
        }
        .badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .search-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 0.75rem;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            background: linear-gradient(90deg, #007bff 0%, #00b7eb 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #0056b3 0%, #0098c7 100%);
        }
        .alert {
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .card-header {
                font-size: 1.1rem;
            }
            .card-body p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="display-4 text-primary fw-bold">Violations Portal</h1>
            <p class="lead text-muted">Check your vehicle violations by entering your plate number</p>
        </div>

        <!-- Search Form -->
        <div class="search-card mb-5 p-4">
            <form method="POST" class="row g-3 justify-content-center">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="plate_number" id="plate_number" 
                           placeholder="Enter Plate Number (e.g., ABC123)" 
                           value="<?php echo htmlspecialchars($plate_number); ?>" required>
                    <label for="plate_number" class="form-label visually-hidden">Plate Number</label>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="search_violations" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>

        <!-- Violations Display -->
        <div class="row g-4">
            <?php if (!empty($violations)): ?>
                <?php foreach ($violations as $violation): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo htmlspecialchars($violation['violation_type']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Violation ID:</strong> <?php echo htmlspecialchars($violation['id']); ?></p>
                                <p><strong>Violator:</strong> <?php echo htmlspecialchars($violation['violator_name']); ?></p>
                                <p><strong>Plate Number:</strong> <?php echo htmlspecialchars($violation['plate_number']); ?></p>
                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($violation['reason']); ?></p>
                                <p><strong>Fine Amount:</strong> ₱<?php echo number_format($violation['fine_amount'], 2); ?></p>
                                <p><strong>Issued Date:</strong> <?php echo htmlspecialchars($violation['issued_date']); ?></p>
                                <p><strong>License:</strong> 
                                    <span class="badge <?php echo $violation['has_license'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $violation['has_license'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </p>
                                <p><strong>Impounded:</strong> 
                                    <span class="badge <?php echo $violation['is_impounded'] ? 'bg-warning' : 'bg-success'; ?>">
                                        <?php echo $violation['is_impounded'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </p>
                                <p><strong>Payment Status:</strong> 
                                    <span class="badge <?php echo $violation['is_paid'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $violation['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                    </span>
                                </p>
                                <p><strong>Status:</strong> 
                                    <span class="badge <?php 
                                        echo $violation['status'] === 'Pending' ? 'bg-warning' : 
                                            ($violation['status'] === 'Resolved' ? 'bg-success' : 'bg-danger'); ?>">
                                        <?php echo htmlspecialchars($violation['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($violation['notes'] ?: 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif ($plate_number): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        No violations found for the provided plate number.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
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