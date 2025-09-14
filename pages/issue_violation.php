<?php
session_start();
include '../config/conn.php';

//if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
 //   header("Location: ../login.php");
  //  exit;
//}

// Initialize toastr messages
$toastr_messages = [];

// Fetch violation types
try {
    $stmt = $pdo->query("SELECT id, violation_type, fine_amount FROM types ORDER BY violation_type");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation types: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Types Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $types = [];
}

// Handle issue violation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_violation'])) {
    try {
        $violation_type_id = trim($_POST['violation_type_id'] ?? '');
        $violator_name = trim($_POST['violator_name'] ?? '');
        $plate_number = trim($_POST['plate_number'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $has_license = isset($_POST['has_license']) ? 1 : 0;
        $is_impounded = isset($_POST['is_impounded']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '') ?: null;

        file_put_contents('../debug.log', "Issue Violation Input: violation_type_id='$violation_type_id', violator_name='$violator_name', plate_number='$plate_number', reason='$reason', has_license='$has_license', is_impounded='$is_impounded'\n", FILE_APPEND);

        if (empty($violation_type_id) || empty($violator_name) || empty($reason)) {
            $toastr_messages[] = "toastr.error('Violation Type, Violator Name, and Reason are required.');";
        } elseif (strlen($violator_name) > 255 || strlen($plate_number) > 50) {
            $toastr_messages[] = "toastr.error('Violator Name or Plate Number exceeds maximum length.');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO violations (violation_type_id, violator_name, plate_number, reason, user_id, has_license, is_impounded, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
            $success = $stmt->execute([$violation_type_id, htmlspecialchars($violator_name), htmlspecialchars($plate_number), htmlspecialchars($reason), $_SESSION['user_id'], $has_license, $is_impounded, $notes]);
            if ($success) {
                $toastr_messages[] = "toastr.success('Violation issued successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to issue violation.');";
                file_put_contents('../debug.log', "Issue Violation Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error issuing violation: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Issue Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
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
                    <h1 class="h2 text-primary">Issue Traffic Violation</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Issue New Violation</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="form-outline" id="issueViolationForm">
                            <input type="hidden" name="issue_violation" value="1">
                            <div class="mb-3">
                                <select class="form-select" name="violation_type_id" id="violation_type_id" required>
                                    <option value="" disabled selected>Select Violation Type</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['id']); ?>">
                                            <?php echo htmlspecialchars($type['violation_type'] . ' ($' . number_format($type['fine_amount'], 2) . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label" for="violation_type_id">Violation Type</label>
                                <div class="invalid-feedback">Please select a violation type.</div>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control" name="violator_name" id="violator_name" required maxlength="255" />
                                <label class="form-label" for="violator_name">Violator Name (max 255 characters)</label>
                                <div class="invalid-feedback">Please enter a valid violator name.</div>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control" name="plate_number" id="plate_number" maxlength="50" />
                                <label class="form-label" for="plate_number">Plate Number (max 50 characters)</label>
                            </div>
                            <div class="mb-3">
                                <textarea class="form-control" name="reason" id="reason" required rows="4"></textarea>
                                <label class="form-label" for="reason">Reason</label>
                                <div class="invalid-feedback">Please enter a reason.</div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="has_license" id="has_license" />
                                    <label class="form-check-label" for="has_license">Has License</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded" />
                                    <label class="form-check-label" for="is_impounded">Vehicle Impounded</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <textarea class="form-control" name="notes" id="notes" rows="4"></textarea>
                                <label class="form-label" for="notes">Notes</label>
                            </div>
                            <button type="submit" name="issue_violation" class="btn btn-primary">Issue Violation</button>
                        </form>
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

        document.getElementById('issueViolationForm').addEventListener('submit', function(e) {
            console.log('Issue violation form submission attempted');
            const violationTypeId = document.getElementById('violation_type_id').value;
            const violatorName = document.getElementById('violator_name').value.trim();
            const reason = document.getElementById('reason').value.trim();

            let isValid = true;

            document.getElementById('violation_type_id').classList.remove('is-invalid');
            document.getElementById('violator_name').classList.remove('is-invalid');
            document.getElementById('reason').classList.remove('is-invalid');

            if (!violationTypeId) {
                document.getElementById('violation_type_id').classList.add('is-invalid');
                isValid = false;
            }
            if (!violatorName || violatorName.length > 255) {
                document.getElementById('violator_name').classList.add('is-invalid');
                isValid = false;
            }
            if (!reason) {
                document.getElementById('reason').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Client-side validation failed for issue violation');
                e.preventDefault();
                return;
            }

            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to issue this violation?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, issue it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Issue violation form submission confirmed');
                    this.submit();
                } else {
                    console.log('Issue violation form submission canceled');
                }
            });
        });
    </script>
</body>
</html>