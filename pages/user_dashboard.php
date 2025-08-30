<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's violations
$stmt = $pdo->prepare("SELECT v.id, v.issued_date, v.status, v.notes, t.violation_type, t.fine_amount 
                      FROM violations v 
                      JOIN types t ON v.violation_type_id = t.id 
                      WHERE v.user_id = ? 
                      ORDER BY v.issued_date DESC");
$stmt->execute([$user_id]);
$violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../layout/menubar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                </div>

                <h3 class="my-3">Your Violations</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>ID</th>
                                <th>Violation Type</th>
                                <th>Fine Amount</th>
                                <th>Issued Date</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($violations)): ?>
                                <tr><td colspan="6" class="text-center">No violations found</td></tr>
                            <?php else: ?>
                                <?php foreach ($violations as $violation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['fine_amount']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['issued_date']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['status']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['notes'] ?: 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <a href="../index.php" class="btn btn-primary mt-3">Back to Home</a>
            </main>
        </div>
    </div>
    <?php include '../layout/footer.php'; ?>
</body>
</html>