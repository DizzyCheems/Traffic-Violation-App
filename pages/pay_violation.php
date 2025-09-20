<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['user', 'officer', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch user details
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
try {
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $toastr_messages[] = "toastr.error('User not found.');";
        file_put_contents('../debug.log', "User not found: ID=$user_id\n", FILE_APPEND);
        header("Location: ../logout.php");
        exit;
    }
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching user details: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch User Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $user = ['username' => 'Unknown', 'full_name' => 'Unknown'];
}

// Fetch unpaid violations based on role
try {
    $query = "SELECT v.id, t.violation_type, t.fine_amount, v.issued_date 
              FROM violations v 
              JOIN types t ON v.violation_type_id = t.id 
              WHERE v.is_paid = 0";
    $params = [];

    if ($role === 'user') {
        $query .= " AND v.user_id = ?";
        $params[] = $user_id;
    } elseif ($role === 'officer') {
        $query .= " AND v.officer_id = ?";
        $params[] = $user_id;
    } // Admin sees all, no additional WHERE clause

    $query .= " ORDER BY v.issued_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $unpaid_violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching unpaid violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Unpaid Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $unpaid_violations = [];
}

// Handle payment (placeholder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_violation'])) {
    try {
        $violation_id = trim($_POST['violation_id'] ?? '');
        file_put_contents('../debug.log', "Pay Violation Input: violation_id='$violation_id', user_id='$user_id', role='$role'\n", FILE_APPEND);

        if (empty($violation_id)) {
            $toastr_messages[] = "toastr.error('Violation ID is required.');";
        } else {
            // Verify violation based on role
            $query = "SELECT id FROM violations WHERE id = ? AND is_paid = 0";
            $params = [$violation_id];
            if ($role === 'user') {
                $query .= " AND user_id = ?";
                $params[] = $user_id;
            } elseif ($role === 'officer') {
                $query .= " AND officer_id = ?";
                $params[] = $user_id;
            } // Admin can pay any violation

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                // Placeholder: Update is_paid (in production, integrate with payment gateway)
                $stmt = $pdo->prepare("UPDATE violations SET is_paid = 1, or_number = ? WHERE id = ?");
                $or_number = 'OR' . time(); // Simulated OR number
                $success = $stmt->execute([$or_number, $violation_id]);
                if ($success) {
                    $toastr_messages[] = "Swal.fire({ title: 'Success!', text: 'Violation paid successfully (OR: $or_number).', icon: 'success', confirmButtonText: 'OK' });";
                    // Refresh violations list
                    $query = "SELECT v.id, t.violation_type, t.fine_amount, v.issued_date 
                              FROM violations v 
                              JOIN types t ON v.violation_type_id = t.id 
                              WHERE v.is_paid = 0";
                    $params = [];
                    if ($role === 'user') {
                        $query .= " AND v.user_id = ?";
                        $params[] = $user_id;
                    } elseif ($role === 'officer') {
                        $query .= " AND v.officer_id = ?";
                        $params[] = $user_id;
                    }
                    $query .= " ORDER BY v.issued_date DESC";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $unpaid_violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $toastr_messages[] = "toastr.error('Failed to process payment.');";
                    file_put_contents('../debug.log', "Pay Violation Failed: No rows affected for violation_id='$violation_id'.\n", FILE_APPEND);
                }
            } else {
                $toastr_messages[] = "toastr.error('Invalid or already paid violation.');";
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error processing payment: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Pay Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<body>
    <?php include '../layout/user_menubar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Pay Violation</h1>
                    <div>
                        <a href="../pages/user_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Unpaid Violations</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Violation ID</th>
                                        <th>Type</th>
                                        <th>Fine</th>
                                        <th>Issued</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($unpaid_violations)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No unpaid violations found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($unpaid_violations as $violation): ?>
                                            <tr class="table-row-hover">
                                                <td>#V-<?php echo htmlspecialchars($violation['id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                <td>₱<?php echo htmlspecialchars(number_format($violation['fine_amount'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($violation['issued_date']))); ?></td>
                                                <td>
                                                    <form method="POST" class="form-outline pay-violation-form" style="display: inline;">
                                                        <input type="hidden" name="violation_id" value="<?php echo htmlspecialchars($violation['id']); ?>">
                                                        <input type="hidden" name="pay_violation" value="1">
                                                        <button type="submit" class="btn btn-sm btn-success">Pay Now</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include '../layout/footer.php'; ?>
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

        document.querySelectorAll('.pay-violation-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Pay violation form submission attempted');
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to pay this violation?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, pay it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Pay violation form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Pay violation form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>