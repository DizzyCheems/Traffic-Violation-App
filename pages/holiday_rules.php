<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Handle create holiday rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_holiday_rule'])) {
    try {
        $holiday_name = trim($_POST['holiday_name'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $fine_multiplier = trim($_POST['fine_multiplier'] ?? '1.0');
        $description = trim($_POST['description'] ?? '') ?: null;

        if (empty($holiday_name) || empty($date)) {
            $toastr_messages[] = "toastr.error('Holiday Name and Date are required.');";
        } elseif (!is_numeric($fine_multiplier) || $fine_multiplier < 0) {
            $toastr_messages[] = "toastr.error('Fine Multiplier must be a non-negative number.');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO holiday_rules (holiday_name, date, fine_multiplier, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$holiday_name, $date, $fine_multiplier, $description]);
            $toastr_messages[] = "toastr.success('Holiday rule created successfully.');";
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error creating holiday rule: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Create Holiday Rule Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle update holiday rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_holiday_rule'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $holiday_name = trim($_POST['holiday_name'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $fine_multiplier = trim($_POST['fine_multiplier'] ?? '1.0');
        $description = trim($_POST['description'] ?? '') ?: null;

        if (empty($id) || empty($holiday_name) || empty($date)) {
            $toastr_messages[] = "toastr.error('ID, Holiday Name, and Date are required.');";
        } elseif (!is_numeric($fine_multiplier) || $fine_multiplier < 0) {
            $toastr_messages[] = "toastr.error('Fine Multiplier must be a non-negative number.');";
        } else {
            $stmt = $pdo->prepare("UPDATE holiday_rules SET holiday_name = ?, date = ?, fine_multiplier = ?, description = ? WHERE id = ?");
            $stmt->execute([$holiday_name, $date, $fine_multiplier, $description, $id]);
            $toastr_messages[] = "toastr.success('Holiday rule updated successfully.');";
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating holiday rule: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Update Holiday Rule Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch holiday rules
try {
    $stmt = $pdo->query("SELECT id, holiday_name, date, fine_multiplier, description FROM holiday_rules ORDER BY date DESC");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching holiday rules: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Holiday Rules Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $rules = [];
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
                    <h1 class="h2 text-primary">Holiday Rules</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Holiday Rules</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createHolidayRuleModal">Add Holiday Rule</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Holiday Name</th>
                                        <th>Date</th>
                                        <th>Fine Multiplier</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rules)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No holiday rules found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($rules as $rule): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($rule['id']); ?></td>
                                                <td><?php echo htmlspecialchars($rule['holiday_name']); ?></td>
                                                <td><?php echo htmlspecialchars($rule['date']); ?></td>
                                                <td><?php echo htmlspecialchars(number_format($rule['fine_multiplier'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars($rule['description'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editHolidayRuleModal<?php echo $rule['id']; ?>">Edit</button>
                                                </td>
                                            </tr>
                                            <!-- Edit Holiday Rule Modal -->
                                            <div class="modal fade" id="editHolidayRuleModal<?php echo $rule['id']; ?>" tabindex="-1" aria-labelledby="editHolidayRuleModalLabel<?php echo $rule['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editHolidayRuleModalLabel<?php echo $rule['id']; ?>">Edit Holiday Rule: <?php echo htmlspecialchars($rule['holiday_name']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-holiday-rule-form">
                                                                <input type="hidden" name="id" value="<?php echo $rule['id']; ?>">
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="holiday_name" id="holiday_name_<?php echo $rule['id']; ?>" required value="<?php echo htmlspecialchars($rule['holiday_name']); ?>" />
                                                                    <label class="form-label" for="holiday_name_<?php echo $rule['id']; ?>">Holiday Name</label>
                                                                    <div class="invalid-feedback">Please enter a holiday name.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="date" class="form-control" name="date" id="date_<?php echo $rule['id']; ?>" required value="<?php echo htmlspecialchars($rule['date']); ?>" />
                                                                    <label class="form-label" for="date_<?php echo $rule['id']; ?>">Date</label>
                                                                    <div class="invalid-feedback">Please enter a valid date.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="number" step="0.01" min="0" class="form-control" name="fine_multiplier" id="fine_multiplier_<?php echo $rule['id']; ?>" required value="<?php echo htmlspecialchars($rule['fine_multiplier']); ?>" />
                                                                    <label class="form-label" for="fine_multiplier_<?php echo $rule['id']; ?>">Fine Multiplier</label>
                                                                    <div class="invalid-feedback">Please enter a valid non-negative number.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <textarea class="form-control" name="description" id="description_<?php echo $rule['id']; ?>" rows="4"><?php echo htmlspecialchars($rule['description'] ?: ''); ?></textarea>
                                                                    <label class="form-label" for="description_<?php echo $rule['id']; ?>">Description</label>
                                                                </div>
                                                                <button type="submit" name="update_holiday_rule" class="btn btn-primary">Update Rule</button>
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
                    </div>
                </div>

                <!-- Create Holiday Rule Modal -->
                <div class="modal fade" id="createHolidayRuleModal" tabindex="-1" aria-labelledby="createHolidayRuleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createHolidayRuleModalLabel">Add New Holiday Rule</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline create-holiday-rule-form" id="createHolidayRuleForm">
                                    <input type="hidden" name="create_holiday_rule" value="1">
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="holiday_name" id="holiday_name" required />
                                        <label class="form-label" for="holiday_name">Holiday Name</label>
                                        <div class="invalid-feedback">Please enter a holiday name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="date" class="form-control" name="date" id="date" required />
                                        <label class="form-label" for="date">Date</label>
                                        <div class="invalid-feedback">Please enter a valid date.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="number" step="0.01" min="0" class="form-control" name="fine_multiplier" id="fine_multiplier" value="1.0" required />
                                        <label class="form-label" for="fine_multiplier">Fine Multiplier</label>
                                        <div class="invalid-feedback">Please enter a valid non-negative number.</div>
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                                        <label class="form-label" for="description">Description</label>
                                    </div>
                                    <button type="submit" name="create_holiday_rule" class="btn btn-primary">Create Rule</button>
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

        document.getElementById('createHolidayRuleForm').addEventListener('submit', function(e) {
            console.log('Create holiday rule form submission attempted');
            const holidayName = document.getElementById('holiday_name').value.trim();
            const date = document.getElementById('date').value;
            const fineMultiplier = document.getElementById('fine_multiplier').value.trim();

            let isValid = true;

            document.getElementById('holiday_name').classList.remove('is-invalid');
            document.getElementById('date').classList.remove('is-invalid');
            document.getElementById('fine_multiplier').classList.remove('is-invalid');

            if (!holidayName) {
                document.getElementById('holiday_name').classList.add('is-invalid');
                isValid = false;
            }
            if (!date) {
                document.getElementById('date').classList.add('is-invalid');
                isValid = false;
            }
            if (!fineMultiplier || isNaN(fineMultiplier) || parseFloat(fineMultiplier) < 0) {
                document.getElementById('fine_multiplier').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Client-side validation failed for create holiday rule');
                e.preventDefault();
                return;
            }

            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to create this holiday rule?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, create it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Create holiday rule form submission confirmed');
                    this.submit();
                } else {
                    console.log('Create holiday rule form submission canceled');
                }
            });
        });

        document.querySelectorAll('.edit-holiday-rule-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit holiday rule form submission attempted');
                const holidayName = this.querySelector('input[name="holiday_name"]').value.trim();
                const date = this.querySelector('input[name="date"]').value;
                const fineMultiplier = this.querySelector('input[name="fine_multiplier"]').value.trim();

                let isValid = true;

                this.querySelector('input[name="holiday_name"]').classList.remove('is-invalid');
                this.querySelector('input[name="date"]').classList.remove('is-invalid');
                this.querySelector('input[name="fine_multiplier"]').classList.remove('is-invalid');

                if (!holidayName) {
                    this.querySelector('input[name="holiday_name"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!date) {
                    this.querySelector('input[name="date"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!fineMultiplier || isNaN(fineMultiplier) || parseFloat(fineMultiplier) < 0) {
                    this.querySelector('input[name="fine_multiplier"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    console.log('Client-side validation failed for edit holiday rule');
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this holiday rule?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit holiday rule form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit holiday rule form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>