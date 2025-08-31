<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch officer performance for current month
$current_month = date('Y-m');
try {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, oe.violations_issued, oe.total_fines, oe.performance_score 
                           FROM officer_earnings oe 
                           JOIN users u ON oe.user_id = u.id 
                           WHERE oe.month_year = ? AND u.role = 'officer' 
                           ORDER BY oe.performance_score DESC");
    $stmt->execute([$current_month]);
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching officer performance: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Officer Performance Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $officers = [];
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
                    <h1 class="h2 text-primary">Officer Performance</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Officer Performance (<?php echo htmlspecialchars($current_month); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Violations Issued</th>
                                        <th>Total Fines</th>
                                        <th>Performance Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($officers)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No officer performance data found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($officers as $officer): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($officer['id']); ?></td>
                                                <td><?php echo htmlspecialchars($officer['username']); ?></td>
                                                <td><?php echo htmlspecialchars($officer['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($officer['violations_issued']); ?></td>
                                                <td><?php echo '$' . number_format($officer['total_fines'], 2); ?></td>
                                                <td><?php echo htmlspecialchars(number_format($officer['performance_score'], 2)); ?></td>
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