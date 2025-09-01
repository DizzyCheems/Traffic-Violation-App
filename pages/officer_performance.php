<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch officer performance data with violations count and locations
try {
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(v.issue_date, '%Y-%m') AS month_year,
            u.id, u.username, u.full_name,
            COUNT(v.id) AS violations_issued,
            COALESCE(SUM(oe.total_fines), 0) AS total_fines,
            GROUP_CONCAT(DISTINCT vld.city ORDER BY vld.city) AS violation_cities,
            (
                (COUNT(v.id) / GREATEST((SELECT MAX(violation_count) FROM (
                    SELECT COUNT(id) AS violation_count 
                    FROM violations 
                    GROUP BY user_id, DATE_FORMAT(issue_date, '%Y-%m')
                ) AS max_violations), 1)) * 50 +
                (COALESCE(SUM(oe.total_fines), 0) / GREATEST((SELECT MAX(total_fines) FROM officer_earnings), 1)) * 50
            ) AS performance_score
        FROM users u
        LEFT JOIN violations v ON u.id = v.user_id
        LEFT JOIN officer_earnings oe ON u.id = oe.user_id AND DATE_FORMAT(v.issue_date, '%Y-%m') = oe.month_year
        LEFT JOIN violation_location_details vld ON v.violation_id = vld.violation_id
        WHERE u.role = 'officer'
        GROUP BY u.id, DATE_FORMAT(v.issue_date, '%Y-%m')
        HAVING violations_issued > 0 OR total_fines > 0
        ORDER BY performance_score DESC, month_year DESC
    ");
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching officer performance: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Officer Performance Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $officers = [];
}

// Fetch unique years for filter
try {
    $year_stmt = $pdo->query("SELECT DISTINCT YEAR(issue_date) AS year FROM violations UNION SELECT DISTINCT LEFT(month_year, 4) AS year FROM officer_earnings ORDER BY year DESC");
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
                    <h1 class="h2 text-primary">Officer Performance</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Officer Performance</h3>
                    </div>
                    <div class="card-body">
                        <!-- Year Filter -->
                        <div class="mb-3">
                            <label for="yearFilter" class="form-label">Filter by Year:</label>
                            <select id="yearFilter" class="form-select w-auto">
                                <option value="all">All Years</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Officer Performance Table -->
                        <div class="table-responsive">
                            <table id="officerTable" class="table table-striped table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month-Year</th>
                                        <th>Officer ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Violations Issued</th>
                                        <th>Total Fines</th>
                                        <th>Performance Score</th>
                                        <th>Violation Cities</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($officers)): ?>
                                        <tr><td colspan="8" class="text-center text-muted">No officer performance data found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($officers as $officer): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($officer['month_year'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($officer['id']); ?></td>
                                                <td><?php echo htmlspecialchars($officer['username']); ?></td>
                                                <td><?php echo htmlspecialchars($officer['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($officer['violations_issued']); ?></td>
                                                <td><?php echo '$' . number_format($officer['total_fines'], 2); ?></td>
                                                <td><?php echo htmlspecialchars(number_format($officer['performance_score'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars($officer['violation_cities'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
            var table = $('#officerTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc'], [6, 'desc']], // Sort by month-year, then performance_score
                columnDefs: [
                    { targets: [0, 2, 3, 7], type: 'string' }, // String sorting for text columns
                    { targets: [1, 4, 6], type: 'num' }, // Numeric sorting for ID, violations, score
                    { targets: [5], type: 'num-fmt' } // Formatted number for total_fines
                ],
                rowGroup: {
                    dataSrc: 'month_year' // Group by month-year
                }
            });

            // Filter by year
            $('#yearFilter').on('change', function() {
                var selectedYear = this.value;
                if (selectedYear === 'all') {
                    table.column(0).search('').draw();
                } else {
                    table.column(0).search('^' + selectedYear, true, false).draw();
                }
                updateNoDataMessage();
            });

            // Function to toggle no-data message
            function updateNoDataMessage() {
                if (table.rows({ search: 'applied' }).count() === 0) {
                    $('#officerTable').next('.text-center.text-muted').show();
                } else {
                    $('#officerTable').next('.text-center.text-muted').hide();
                }
            }
        });
    </script>
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .table-row-hover:hover {
            background-color: #000000 !important;
            color: #ffffff !important;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .form-select {
            max-width: 200px;
        }
        .dataTables_wrapper .rowGroup {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</body>
</html>
```