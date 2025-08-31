<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch violation report data
try {
    $stmt = $pdo->query("SELECT v.id, v.violator_name, v.plate_number, v.reason, t.violation_type, t.fine_amount, v.issued_date, v.status, u.full_name as user_name 
                         FROM violations v 
                         JOIN types t ON v.violation_type_id = t.id 
                         JOIN users u ON v.user_id = u.id 
                         ORDER BY v.issued_date DESC");
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $violations = [];
}

// Fetch analytics for current month
$current_month = date('Y-m');
try {
    $stmt = $pdo->prepare("SELECT t.violation_type, va.percentage 
                           FROM violation_analytics va 
                           JOIN types t ON va.violation_type_id = t.id 
                           WHERE va.month_year = ? 
                           ORDER BY va.percentage DESC");
    $stmt->execute([$current_month]);
    $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation analytics: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Analytics Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $analytics = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../layout/menubar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Violation Report</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violation Analytics (<?php echo htmlspecialchars($current_month); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Violation Type</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($analytics)): ?>
                                        <tr><td colspan="2" class="text-center text-muted">No analytics data found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($analytics as $data): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($data['violation_type']); ?></td>
                                                <td><?php echo htmlspecialchars(number_format($data['percentage'], 2)) . '%'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">All Violations</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Violator Name</th>
                                        <th>Plate Number</th>
                                        <th>Reason</th>
                                        <th>Violation Type</th>
                                        <th>Fine Amount</th>
                                        <th>Issued By</th>
                                        <th>Issued Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($violations)): ?>
                                        <tr><td colspan="9" class="text-center text-muted">No violations found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($violations as $violation): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violator_name']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['plate_number']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['reason']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                <td><?php echo '$' . number_format($violation['fine_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($violation['user_name']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['issued_date']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['status']); ?></td>
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
    </script>
</body>
</html>