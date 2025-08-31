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

// Fetch unpaid violations count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE user_id = ? AND is_paid = 0");
$stmt->execute([$user_id]);
$unpaid_violations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Fetch pending appeals count (assuming appeals are concerns with status 'OPEN' or 'IN_PROGRESS')
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM concerns WHERE user_id = ? AND status IN ('OPEN', 'IN_PROGRESS')");
$stmt->execute([$user_id]);
$pending_appeals = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Fetch open concerns count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM concerns WHERE user_id = ? AND status = 'OPEN'");
$stmt->execute([$user_id]);
$open_concerns = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Fetch recent violations
$stmt = $pdo->prepare("SELECT v.id, v.violation_type_id, t.violation_type, t.fine_amount, v.issued_date, v.is_paid 
                      FROM violations v 
                      JOIN types t ON v.violation_type_id = t.id 
                      WHERE v.user_id = ? 
                      ORDER BY v.issued_date DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent concerns
$stmt = $pdo->prepare("SELECT id, description, status FROM concerns WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_concerns = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <h1 class="h2 text-primary">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <div>
                        <a href="../index.php" class="btn btn-outline-primary">Back to Home</a>
                    </div>
                </div>

                <!-- Overview and Quick Actions -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">My Overview</h5>
                                <p class="card-text">Unpaid Violations: <?php echo htmlspecialchars($unpaid_violations); ?> <a href="#violations" class="text-decoration-none link-primary">[View]</a></p>
                                <p class="card-text">Pending Appeals: <?php echo htmlspecialchars($pending_appeals); ?></p>
                                <p class="card-text">Open Concerns: <?php echo htmlspecialchars($open_concerns); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Quick Actions</h5>
                                <ul class="list-unstyled">
                                    <li><a href="#" class="text-decoration-none link-primary">🐞 Report Road Concern</a></li>
                                    <li><a href="#" class="text-decoration-none link-primary">📝 View License Status</a></li>
                                    <li><a href="#" class="text-decoration-none link-primary">💳 Pay Violation</a></li>
                                    <li><a href="#" class="text-decoration-none link-primary">📋 Apply for Appeal</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Violations and Road Concerns -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h3 class="mb-0">Recent Violations</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="violations">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Violation ID</th>
                                                <th>Type</th>
                                                <th>Fine</th>
                                                <th>Issued</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_violations)): ?>
                                                <tr><td colspan="5" class="text-center text-muted">No violations found</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_violations as $violation): ?>
                                                    <tr class="table-row-hover">
                                                        <td>#V-<?php echo htmlspecialchars($violation['id']); ?></td>
                                                        <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                        <td>$<?php echo htmlspecialchars(number_format($violation['fine_amount'], 2)); ?></td>
                                                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($violation['issued_date']))); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $violation['is_paid'] ? 'bg-success' : 'bg-danger'; ?>">
                                                                <?php echo $violation['is_paid'] ? 'Paid ✅' : 'Unpaid'; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h3 class="mb-0">Road Concerns</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Concern ID</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_concerns)): ?>
                                                <tr><td colspan="3" class="text-center text-muted">No concerns found</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_concerns as $concern): ?>
                                                    <tr class="table-row-hover">
                                                        <td>#C-<?php echo htmlspecialchars($concern['id']); ?></td>
                                                        <td><?php echo htmlspecialchars($concern['description'] ?: 'N/A'); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $concern['status'] === 'OPEN' ? 'bg-warning' : ($concern['status'] === 'IN_PROGRESS' ? 'bg-info' : 'bg-success'); ?>">
                                                                <?php echo htmlspecialchars($concern['status']); ?> 
                                                                <?php echo $concern['status'] === 'RESOLVED' ? '✅' : ($concern['status'] === 'IN_PROGRESS' ? '🟡' : ''); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include '../layout/footer.php'; ?>
</body>
</html>