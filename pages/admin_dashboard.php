<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ds') {
    header("Location: login.php");
    exit;
}

// Fetch users
$stmt = $pdo->query("SELECT id, username, full_name, role, created_at FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch violation types
$stmt = $pdo->query("SELECT id, violation_type, fine_amount, description FROM types");
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
                    <h2>Admin Dashboard</h2>
                </div>

                <h3 class="my-3">Users</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="my-3">Violation Types</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>ID</th>
                                <th>Violation Type</th>
                                <th>Fine Amount</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types as $type): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type['id']); ?></td>
                                    <td><?php echo htmlspecialchars($type['violation_type']); ?></td>
                                    <td><?php echo htmlspecialchars($type['fine_amount']); ?></td>
                                    <td><?php echo htmlspecialchars($type['description'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
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