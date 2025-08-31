<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch license status
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT license_number, status, expiry_date FROM license_status WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching license status: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch License Status Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $license = null;
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
                    <h1 class="h2 text-primary">License Status</h1>
                    <div>
                        <a href="../pages/user_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Your License Status</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($license): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>License Number:</strong> <?php echo htmlspecialchars($license['license_number'] ?: 'N/A'); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge <?php echo $license['status'] === 'VALID' ? 'bg-success' : ($license['status'] === 'SUSPENDED' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                            <?php echo htmlspecialchars($license['status']); ?>
                                        </span>
                                    </p>
                                    <p><strong>Expiry Date:</strong> <?php echo htmlspecialchars($license['expiry_date'] ? date('d M Y', strtotime($license['expiry_date'])) : 'N/A'); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No license information found.</p>
                        <?php endif; ?>
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