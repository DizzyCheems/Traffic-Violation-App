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
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM concerns WHERE user_id = ? AND status IN ('OPEN', 'PENDING')");
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

// Fetch recent violations
try {
    $stmt = $pdo->prepare("SELECT v.id, v.violation_type_id, t.violation_type, t.fine_amount, v.issued_date, v.is_paid 
                          FROM violations v 
                          JOIN types t ON v.violation_type_id = t.id 
                          WHERE v.user_id = ? 
                          ORDER BY v.issued_date DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching recent violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Recent Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $recent_violations = [];
}

// Fetch recent concerns
try {
    $stmt = $pdo->prepare("SELECT id, concern_text, status, created_at FROM concerns WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_concerns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching recent concerns: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Recent Concerns Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $recent_concerns = [];
}

// Handle report concern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_concern'])) {
    try {
        $concern_text = trim($_POST['concern_text'] ?? '');
        file_put_contents('../debug.log', "Report Concern Input: concern_text='$concern_text'\n", FILE_APPEND);

        if (empty($concern_text)) {
            $toastr_messages[] = "toastr.error('Concern description is required.');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO concerns (user_id, concern_text, status) VALUES (?, ?, 'OPEN')");
            $success = $stmt->execute([$user_id, htmlspecialchars($concern_text)]);
            if ($success) {
                $toastr_messages[] = "toastr.success('Concern reported successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to report concern.');";
                file_put_contents('../debug.log', "Report Concern Failed: No rows affected.\n", FILE_APPEND);
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
        $appeal_text = trim($_POST['appeal_text'] ?? '');
        file_put_contents('../debug.log', "Apply Appeal Input: violation_id='$violation_id', appeal_text='$appeal_text'\n", FILE_APPEND);

        if (empty($violation_id) || empty($appeal_text)) {
            $toastr_messages[] = "toastr.error('Violation ID and appeal description are required.');";
        } else {
            // Check if violation belongs to user
            $stmt = $pdo->prepare("SELECT id FROM violations WHERE id = ? AND user_id = ?");
            $stmt->execute([$violation_id, $user_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO concerns (user_id, concern_text, status) VALUES (?, ?, 'PENDING')");
                $success = $stmt->execute([$user_id, htmlspecialchars("Appeal for Violation #$violation_id: $appeal_text")]);
                if ($success) {
                    $toastr_messages[] = "toastr.success('Appeal submitted successfully.');";
                } else {
                    $toastr_messages[] = "toastr.error('Failed to submit appeal.');";
                    file_put_contents('../debug.log', "Apply Appeal Failed: No rows affected.\n", FILE_APPEND);
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
<style>
    body {
        padding-top: 56px; /* Adjust for fixed navbar height */
    }
    .sidebar {
        position: fixed;
        top: 56px; /* Start below navbar */
        bottom: 0;
        left: 0;
        z-index: 100;
        padding: 0;
        box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        overflow-y: auto;
    }
    .sidebar-sticky {
        position: relative;
        top: 0;
        height: calc(100vh - 56px); /* Full height minus navbar */
        padding-top: .5rem;
        overflow-x: hidden;
        overflow-y: auto;
    }
    @media (max-width: 767.98px) {
        .sidebar {
            position: static;
            height: auto;
            padding: 0;
        }
        .main-content {
            padding-top: 0;
        }
    }
</style>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../layout/user_menubar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <div>
                        <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                            <i class="fas fa-bars"></i>
                        </button>
                        <a href="../index.php" class="btn btn-outline-primary">Back to Home</a>
                    </div>
                </div>

                <!-- Overview and Quick Actions -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">My Overview</h5>
                                <p class="card-text">Unpaid Violations: <?php echo htmlspecialchars($unpaid_violations); ?> <a href="#recent-violations" class="text-decoration-none link-primary">[View]</a></p>
                                <p class="card-text">Pending Appeals: <?php echo htmlspecialchars($pending_appeals); ?> <a href="#recent-concerns" class="text-decoration-none link-primary">[View]</a></p>
                                <p class="card-text">Open Concerns: <?php echo htmlspecialchars($open_concerns); ?> <a href="#recent-concerns" class="text-decoration-none link-primary">[View]</a></p>
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

                <!-- Recent Violations -->
                <div class="card mb-4 shadow-sm" id="recent-violations">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Recent Violations</h3>
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

                <!-- Recent Concerns -->
                <div class="card mb-4 shadow-sm" id="recent-concerns">
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
                                    <?php if (empty($recent_concerns)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No concerns found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_concerns as $concern): ?>
                                            <tr class="table-row-hover">
                                                <td>#C-<?php echo htmlspecialchars($concern['id']); ?></td>
                                                <td><?php echo htmlspecialchars($concern['concern_text'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $concern['status'] === 'OPEN' ? 'bg-warning text-dark' : ($concern['status'] === 'PENDING' ? 'bg-info text-dark' : 'bg-success'); ?>">
                                                        <?php echo htmlspecialchars($concern['status']); ?> 
                                                        <?php echo $concern['status'] === 'RESOLVED' ? '✅' : ($concern['status'] === 'PENDING' ? '🟡' : ''); ?>
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
                                        <textarea class="form-control" name="concern_text" id="concern_text" required rows="4"></textarea>
                                        <label class="form-label" for="concern_text">Concern Description</label>
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
                                            <?php foreach ($recent_violations as $violation): ?>
                                                <option value="<?php echo htmlspecialchars($violation['id']); ?>">
                                                    #V-<?php echo htmlspecialchars($violation['id']); ?> - <?php echo htmlspecialchars($violation['violation_type']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label" for="violation_id">Violation</label>
                                        <div class="invalid-feedback">Please select a violation.</div>
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="appeal_text" id="appeal_text" required rows="4"></textarea>
                                        <label class="form-label" for="appeal_text">Appeal Reason</label>
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
            const concernText = document.getElementById('concern_text').value.trim();

            if (!concernText) {
                document.getElementById('concern_text').classList.add('is-invalid');
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
            const appealText = document.getElementById('appeal_text').value.trim();

            let isValid = true;

            document.getElementById('violation_id').classList.remove('is-invalid');
            document.getElementById('appeal_text').classList.remove('is-invalid');

            if (!violationId) {
                document.getElementById('violation_id').classList.add('is-invalid');
                isValid = false;
            }
            if (!appealText) {
                document.getElementById('appeal_text').classList.add('is-invalid');
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