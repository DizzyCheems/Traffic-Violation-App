<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch user details
$user_id = $_SESSION['user_id'];
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

// Fetch unpaid violations count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE user_id = ? AND is_paid = 0");
    $stmt->execute([$user_id]);
    $unpaid_violations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching unpaid violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Unpaid Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $unpaid_violations = 0;
}

// Fetch pending appeals count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appeals WHERE user_id = ? AND status = 'PENDING'");
    $stmt->execute([$user_id]);
    $pending_appeals = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching pending appeals: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Pending Appeals Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $pending_appeals = 0;
}

// Fetch open concerns count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM concerns WHERE user_id = ? AND status = 'OPEN'");
    $stmt->execute([$user_id]);
    $open_concerns = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching open concerns: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Open Concerns Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $open_concerns = 0;
}

// Fetch all violations for the user with offense frequency
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.violation_type_id, v.offense_freq, COALESCE(t.violation_type, 'Unknown Type') AS violation_type, 
               COALESCE(t.fine_amount, 0.00) AS fine_amount, v.issued_date, v.is_paid 
        FROM violations v 
        LEFT JOIN types t ON v.violation_type_id = t.id 
        WHERE v.user_id = ? 
        ORDER BY v.issued_date DESC
    ");
    $stmt->execute([$user_id]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $violations = [];
}

// Fetch all concerns
try {
    $stmt = $pdo->prepare("SELECT id, description, status, created_at FROM concerns WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $concerns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching concerns: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Concerns Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $concerns = [];
}

// Fetch all appeals
try {
    $stmt = $pdo->prepare("SELECT id, violation_id, appeal_reason, status, created_at FROM appeals WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching appeals: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Appeals Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $appeals = [];
}

// Handle report concern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_concern'])) {
    try {
        $description = trim($_POST['description'] ?? '');
        file_put_contents('../debug.log', "Report Concern Input: user_id='$user_id', description='$description'\n", FILE_APPEND);

        if (empty($description)) {
            $toastr_messages[] = "toastr.error('Concern description is required.');";
        } elseif (strlen($description) > 65535) { // TEXT column limit
            $toastr_messages[] = "toastr.error('Concern description is too long.');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO concerns (user_id, description, status, created_at) VALUES (?, ?, 'OPEN', NOW())");
            $success = $stmt->execute([$user_id, htmlspecialchars($description)]);
            if ($success) {
                $toastr_messages[] = "Swal.fire({ title: 'Success!', text: 'Concern reported successfully.', icon: 'success', confirmButtonText: 'OK' });";
                // Refresh concerns list
                $stmt = $pdo->prepare("SELECT id, description, status, created_at FROM concerns WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$user_id]);
                $concerns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $toastr_messages[] = "toastr.error('Failed to report concern.');";
                file_put_contents('../debug.log', "Report Concern Failed: No rows affected for user_id='$user_id'.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error reporting concern: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Report Concern Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle apply for appeal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_appeal'])) {
    try {
        $violation_id = trim($_POST['violation_id'] ?? '');
        $appeal_reason = trim($_POST['appeal_reason'] ?? '');
        file_put_contents('../debug.log', "Apply Appeal Input: user_id='$user_id', violation_id='$violation_id', appeal_reason='$appeal_reason'\n", FILE_APPEND);

        if (empty($violation_id) || empty($appeal_reason)) {
            $toastr_messages[] = "toastr.error('Violation ID and appeal reason are required.');";
        } elseif (strlen($appeal_reason) > 65535) { // TEXT column limit
            $toastr_messages[] = "toastr.error('Appeal reason is too long.');";
        } else {
            // Check if violation belongs to user
            $stmt = $pdo->prepare("SELECT id FROM violations WHERE id = ? AND user_id = ?");
            $stmt->execute([$violation_id, $user_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO appeals (violation_id, user_id, appeal_reason, status, created_at, updated_at) VALUES (?, ?, ?, 'PENDING', NOW(), NOW())");
                $success = $stmt->execute([$violation_id, $user_id, htmlspecialchars($appeal_reason)]);
                if ($success) {
                    $toastr_messages[] = "Swal.fire({ title: 'Success!', text: 'Appeal submitted successfully.', icon: 'success', confirmButtonText: 'OK' });";
                    // Refresh appeals list
                    $stmt = $pdo->prepare("SELECT id, violation_id, appeal_reason, status, created_at FROM appeals WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$user_id]);
                    $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $toastr_messages[] = "toastr.error('Failed to submit appeal.');";
                    file_put_contents('../debug.log', "Apply Appeal Failed: No rows affected for user_id='$user_id', violation_id='$violation_id'.\n", FILE_APPEND);
                }
            } else {
                $toastr_messages[] = "toastr.error('Invalid or unauthorized violation ID.');";
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error submitting appeal: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Apply Appeal Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">
        <!-- Toggle button for offcanvas sidebar (mobile only) -->
        <button class="btn btn-primary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="fas fa-bars"></i> Menu
        </button>
        <div class="row">
            <!-- Sidebar (visible on desktop, offcanvas on mobile) -->
            <nav class="col-lg-2 d-none d-lg-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/user_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/pay_violation.php">
                                <i class="fas fa-credit-card me-2"></i>
                                Pay Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#reportConcernModal">
                                <i class="fas fa-bug me-2"></i>
                                Report Concern
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#applyAppealModal">
                                <i class="fas fa-gavel me-2"></i>
                                Apply for Appeal
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/license_status.php">
                                <i class="fas fa-id-card me-2"></i>
                                License Status
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <div class="offcanvas offcanvas-start sidebar d-lg-none" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="sidebarMenuLabel">Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/user_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/pay_violation.php">
                                <i class="fas fa-credit-card me-2"></i>
                                Pay Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#reportConcernModal">
                                <i class="fas fa-bug me-2"></i>
                                Report Concern
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#applyAppealModal">
                                <i class="fas fa-gavel me-2"></i>
                                Apply for Appeal
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/license_status.php">
                                <i class="fas fa-id-card me-2"></i>
                                License Status
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- Main content -->
            <main class="col-12 col-md-9 col-lg-10 px-md-4 py-4">
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
                                <p class="card-text">Pending Appeals: <?php echo htmlspecialchars($pending_appeals); ?> <a href="#appeals" class="text-decoration-none link-primary">[View]</a></p>
                                <p class="card-text">Open Concerns: <?php echo htmlspecialchars($open_concerns); ?> <a href="#concerns" class="text-decoration-none link-primary">[View]</a></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Quick Actions</h5>
                                <ul class="list-unstyled">
                                    <li><a href="#" class="text-decoration-none link-primary" data-bs-toggle="modal" data-bs-target="#reportConcernModal"><i class="fas fa-bug me-2"></i> Report Road Concern</a></li>
                                    <li><a href="../pages/license_status.php" class="text-decoration-none link-primary"><i class="fas fa-id-card me-2"></i> View License Status</a></li>
                                    <li><a href="../pages/pay_violation.php" class="text-decoration-none link-primary"><i class="fas fa-credit-card me-2"></i> Pay Violation</a></li>
                                    <li><a href="#" class="text-decoration-none link-primary" data-bs-toggle="modal" data-bs-target="#applyAppealModal"><i class="fas fa-gavel me-2"></i> Apply for Appeal</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Violations -->
                <div class="mb-4" id="violations">
                    <h3 class="text-primary mb-3">My Violations</h3>
                    <?php if (empty($violations)): ?>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <p class="text-center text-muted">No violations found</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($violations as $violation): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($violation['violation_type']); ?></h5>
                                            <p class="card-text">
                                                <strong>Violation ID:</strong> #V-<?php echo htmlspecialchars($violation['id']); ?><br>
                                                <strong>Frequency:</strong> <?php echo htmlspecialchars($violation['offense_freq']); ?><br>
                                                <strong>Fine:</strong> ₱<?php echo htmlspecialchars(number_format($violation['fine_amount'], 2)); ?><br>
                                                <strong>Issued:</strong> <?php echo htmlspecialchars($violation['issued_date'] ? date('d M Y', strtotime($violation['issued_date'])) : 'N/A'); ?><br>
                                                <strong>Status:</strong> 
                                                <span class="badge <?php echo $violation['is_paid'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $violation['is_paid'] ? 'Paid ✅' : 'Unpaid'; ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- My Appeals -->
                <div class="mb-4" id="appeals">
                    <h3 class="text-primary mb-3">My Appeals</h3>
                    <?php if (empty($appeals)): ?>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <p class="text-center text-muted">No appeals found</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h4 class="mb-0">Appeals</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Appeal ID</th>
                                                <th>Violation ID</th>
                                                <th>Appeal Reason</th>
                                                <th>Status</th>
                                                <th>Submitted</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appeals as $appeal): ?>
                                                <tr class="table-row-hover">
                                                    <td>#A-<?php echo htmlspecialchars($appeal['id']); ?></td>
                                                    <td>#V-<?php echo htmlspecialchars($appeal['violation_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($appeal['appeal_reason'] ?: 'N/A'); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $appeal['status'] === 'PENDING' ? 'bg-warning text-dark' : ($appeal['status'] === 'APPROVED' ? 'bg-success' : 'bg-danger'); ?>">
                                                            <?php echo htmlspecialchars($appeal['status']); ?>
                                                            <?php echo $appeal['status'] === 'APPROVED' ? '✅' : ($appeal['status'] === 'PENDING' ? '🟡' : '❌'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($appeal['created_at']))); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- All Concerns -->
                <div class="card mb-4 shadow-sm" id="concerns">
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
                                        <th>Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($concerns)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No concerns found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($concerns as $concern): ?>
                                            <tr class="table-row-hover">
                                                <td>#C-<?php echo htmlspecialchars($concern['id']); ?></td>
                                                <td><?php echo htmlspecialchars($concern['description'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $concern['status'] === 'OPEN' ? 'bg-warning text-dark' : ($concern['status'] === 'IN_PROGRESS' ? 'bg-info text-dark' : 'bg-success'); ?>">
                                                        <?php echo htmlspecialchars($concern['status']); ?> 
                                                        <?php echo $concern['status'] === 'RESOLVED' ? '✅' : ($concern['status'] === 'IN_PROGRESS' ? '🟡' : ''); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($concern['created_at']))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Report Concern Modal -->
                <div class="modal fade" id="reportConcernModal" tabindex="-1" aria-labelledby="reportConcernModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="reportConcernModalLabel">Report Road Concern</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline" id="reportConcernForm">
                                    <input type="hidden" name="report_concern" value="1">
                                    <div class="mb-3">
                                        <textarea class="form-control" name="description" id="description" required rows="4"></textarea>
                                        <label class="form-label" for="description">Concern Description</label>
                                        <div class="invalid-feedback">Please enter a concern description.</div>
                                    </div>
                                    <button type="submit" name="report_concern" class="btn btn-primary">Submit Concern</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Apply Appeal Modal -->
                <div class="modal fade" id="applyAppealModal" tabindex="-1" aria-labelledby="applyAppealModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="applyAppealModalLabel">Apply for Appeal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline" id="applyAppealForm">
                                    <input type="hidden" name="apply_appeal" value="1">
                                    <div class="mb-3">
                                        <select class="form-select" name="violation_id" id="violation_id" required>
                                            <option value="" disabled selected>Select Violation</option>
                                            <?php foreach ($violations as $violation): ?>
                                                <option value="<?php echo htmlspecialchars($violation['id']); ?>">
                                                    #V-<?php echo htmlspecialchars($violation['id']); ?> - <?php echo htmlspecialchars($violation['violation_type']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label" for="violation_id">Violation</label>
                                        <div class="invalid-feedback">Please select a violation.</div>
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="appeal_reason" id="appeal_reason" required rows="4"></textarea>
                                        <label class="form-label" for="appeal_reason">Appeal Reason</label>
                                        <div class="invalid-feedback">Please enter an appeal reason.</div>
                                    </div>
                                    <button type="submit" name="apply_appeal" class="btn btn-primary">Submit Appeal</button>
                                </form>
                            </div>
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

        document.getElementById('reportConcernForm').addEventListener('submit', function(e) {
            console.log('Report concern form submission attempted');
            const description = document.getElementById('description').value.trim();

            if (!description) {
                document.getElementById('description').classList.add('is-invalid');
                console.log('Client-side validation failed for report concern');
                e.preventDefault();
                return;
            }

            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to submit this concern?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, submit it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Report concern form submission confirmed');
                    this.submit();
                } else {
                    console.log('Report concern form submission canceled');
                }
            });
        });

        document.getElementById('applyAppealForm').addEventListener('submit', function(e) {
            console.log('Apply appeal form submission attempted');
            const violationId = document.getElementById('violation_id').value;
            const appealReason = document.getElementById('appeal_reason').value.trim();

            let isValid = true;

            document.getElementById('violation_id').classList.remove('is-invalid');
            document.getElementById('appeal_reason').classList.remove('is-invalid');

            if (!violationId) {
                document.getElementById('violation_id').classList.add('is-invalid');
                isValid = false;
            }
            if (!appealReason) {
                document.getElementById('appeal_reason').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Client-side validation failed for apply appeal');
                e.preventDefault();
                return;
            }

            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to submit this appeal?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, submit it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Apply appeal form submission confirmed');
                    this.submit();
                } else {
                    console.log('Apply appeal form submission canceled');
                }
            });
        });
    </script>
</body>
</html>