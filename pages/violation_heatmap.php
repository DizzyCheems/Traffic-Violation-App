<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch violation data with location details, user info, and month-year
try {
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(v.issue_date, '%Y-%m') AS month_year,
               vld.city, vld.municipality, vld.province, vld.street, vld.barangay, 
               t.violation_type, v.violator_name, u.full_name AS officer_name,
               v.issue_date, v.remarks, COALESCE(vhr.violation_count, 1) AS violation_count
        FROM violation_locations vl
        JOIN violation_location_details vld ON vl.violation_id = vld.violation_id
        JOIN violations v ON vl.violation_id = v.id
        JOIN types t ON v.violation_type_id = t.id
        JOIN users u ON v.user_id = u.id
        LEFT JOIN violation_heatmap_report vhr ON vl.latitude = vhr.latitude AND vl.longitude = vhr.longitude
        ORDER BY v.issue_date DESC, vhr.violation_count DESC
    ");
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation data: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Data Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $violations = [];
}

// Fetch unique violation types for filter
try {
    $type_stmt = $pdo->query("SELECT DISTINCT violation_type FROM types");
    $violation_types = $type_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation types: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $violation_types = [];
}

// Fetch unique years for filter
try {
    $year_stmt = $pdo->query("SELECT DISTINCT YEAR(issue_date) AS year FROM violations ORDER BY year DESC");
    $years = $year_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching years: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $years = [];
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
                    <h1 class="h2 text-primary">Violation Locations Report</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violation Locations</h3>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="mb-3 d-flex gap-3">
                            <div>
                                <label for="yearFilter" class="form-label">Filter by Year:</label>
                                <select id="yearFilter" class="form-select w-auto">
                                    <option value="all">All Years</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="violationTypeFilter" class="form-label">Filter by Violation Type:</label>
                                <select id="violationTypeFilter" class="form-select w-auto">
                                    <option value="all">All Violations</option>
                                    <?php foreach ($violation_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <!-- Violations Table -->
                        <table id="violationsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Month-Year</th>
                                    <th>City</th>
                                    <th>Municipality</th>
                                    <th>Province</th>
                                    <th>Street</th>
                                    <th>Barangay</th>
                                    <th>Violation Type</th>
                                    <th>Violator Name</th>
                                    <th>Officer Name</th>
                                    <th>Issue Date</th>
                                    <th>Remarks</th>
                                    <th>Violation Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($violations as $violation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($violation['month_year']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['city']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['municipality'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($violation['province']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['street'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($violation['barangay'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['violator_name']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['officer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['issue_date']); ?></td>
                                        <td><?php echo htmlspecialchars($violation['remarks'] ?? 'No remarks'); ?></td>
                                        <td><?php echo htmlspecialchars($violation['violation_count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <!-- Empty Data Message -->
                        <div id="no-data-message" class="alert alert-info mt-3" style="display: <?php echo empty($violations) ? 'block' : 'none'; ?>;">
                            No violation locations available.
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include '../layout/footer.php'; ?>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/rowgroup/1.4.0/js/dataTables.rowGroup.min.js"></script>
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

        // Initialize DataTable
        $(document).ready(function() {
            var table = $('#violationsTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc'], [11, 'desc'], [9, 'desc']], // Sort by month-year, violation count, issue date
                columnDefs: [
                    { targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 10], type: 'string' }, // String sorting for text columns
                    { targets: [9], type: 'date' }, // Date sorting for issue_date
                    { targets: [11], type: 'num' } // Numeric sorting for violation_count
                ],
                rowGroup: {
                    dataSrc: 'month_year' // Group by month-year
                }
            });

            // Filter by year
            $('#yearFilter').on('change', function() {
                var selectedYear = this.value;
                var typeFilter = $('#violationTypeFilter').val();
                if (selectedYear === 'all') {
                    table.column(0).search('').draw();
                } else {
                    table.column(0).search('^' + selectedYear, true, false).draw();
                }
                // Re-apply violation type filter if set
                if (typeFilter !== 'all') {
                    table.column(6).search(typeFilter).draw();
                }
                updateNoDataMessage();
            });

            // Filter by violation type
            $('#violationTypeFilter').on('change', function() {
                var selectedType = this.value;
                var yearFilter = $('#yearFilter').val();
                if (selectedType === 'all') {
                    table.column(6).search('').draw();
                } else {
                    table.column(6).search(selectedType).draw();
                }
                // Re-apply year filter if set
                if (yearFilter !== 'all') {
                    table.column(0).search('^' + yearFilter, true, false).draw();
                }
                updateNoDataMessage();
            });

            // Function to toggle no-data message
            function updateNoDataMessage() {
                if (table.rows({ search: 'applied' }).count() === 0) {
                    $('#no-data-message').show();
                } else {
                    $('#no-data-message').hide();
                }
            }
        });
    </script>
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: #000000 !important;
            color: #ffffff !important;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .form-select {
            max-width: 200px;
        }
        .d-flex.gap-3 {
            display: flex;
            gap: 1rem;
        }
        .dataTables_wrapper .rowGroup {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</body>
</html>
```