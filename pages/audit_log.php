<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch audit logs
try {
    $stmt = $pdo->query("SELECT a.id, a.action, a.details, a.created_at, u.username 
                         FROM audit_log a 
                         JOIN users u ON a.user_id = u.id 
                         ORDER BY a.created_at DESC");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching audit logs: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Audit Logs Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $logs = [];
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
                    <h1 class="h2 text-primary">Audit Log</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">System Audit Logs</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No audit logs found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($log['id']); ?></td>
                                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                <td><?php echo htmlspecialchars($log['details'] ?: 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
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