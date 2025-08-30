<?php
session_start();
include '../config/conn.php';

// Debug: Log session data
file_put_contents('debug.log', "Session Data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'officer') {
    // Debug: Log why redirection is happening
    $reason = "Redirecting to login.php. ";
    if (!isset($_SESSION['user_id'])) {
        $reason .= "user_id not set. ";
    }
    if (!isset($_SESSION['role'])) {
        $reason .= "role not set. ";
    }
    if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) !== 'officer') {
        $reason .= "role is '" . $_SESSION['role'] . "' instead of 'officer'.";
    }
    file_put_contents('debug.log', $reason . "\n", FILE_APPEND);
    header("Location: ../login.php");
    exit;
}

// Initialize success/error messages
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_violation'])) {
    try {
        $user_id = $_POST['user_id'];
        $violation_type_id = $_POST['violation_type_id'];
        $notes = trim($_POST['notes'] ?? '');
        $status = 'Pending';
        $issued_date = date('Y-m-d H:i:s');

        // Validate inputs
        if (empty($user_id) || empty($violation_type_id)) {
            $message = '<div class="alert alert-danger">User ID or Violation Type is missing.</div>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO violations (user_id, violation_type_id, issued_date, status, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $violation_type_id, $issued_date, $status, $notes]);
            $message = '<div class="alert alert-success">Violation issued successfully.</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error issuing violation: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch users with violations
$stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, COUNT(v.id) as violation_count 
                      FROM users u 
                      LEFT JOIN violations v ON u.id = v.user_id 
                      WHERE u.role = 'user' 
                      GROUP BY u.id 
                      ORDER BY violation_count DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch violation types
$stmt = $pdo->query("SELECT id, violation_type FROM types");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <h2>Officer Dashboard</h2>
                </div>

                <?php if ($message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <h3 class="my-3">Users with Violations</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Violation Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="5" class="text-center">No users found</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['violation_count']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#issueViolationModal<?php echo $user['id']; ?>">Issue Violation</button>
                                        </td>
                                    </tr>
                                    <!-- Modal for Issuing Violation -->
                                    <div class="modal fade" id="issueViolationModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="issueViolationModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="issueViolationModalLabel<?php echo $user['id']; ?>">Issue Violation for <?php echo htmlspecialchars($user['full_name']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form method="POST">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="violation_type_id_<?php echo $user['id']; ?>" class="form-label">Violation Type</label>
                                                            <select class="form-select" name="violation_type_id" id="violation_type_id_<?php echo $user['id']; ?>" required>
                                                                <?php if (empty($types)): ?>
                                                                    <option value="">No violation types available</option>
                                                                <?php else: ?>
                                                                    <?php foreach ($types as $type): ?>
                                                                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['violation_type']); ?></option>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="notes_<?php echo $user['id']; ?>" class="form-label">Notes</label>
                                                            <textarea class="form-control" name="notes" id="notes_<?php echo $user['id']; ?>" rows="4"></textarea>
                                                        </div>
                                                        <button type="submit" name="issue_violation" class="btn btn-primary">Issue Violation</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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