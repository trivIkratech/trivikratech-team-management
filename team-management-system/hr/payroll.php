<?php
/**
 * HR — Payroll & Salary System
 * 
 * Flow:
 * Payroll -> Employee List -> Employee Status (Hours, Paid Leave, Unpaid Leave, Present, Half-Day) 
 *         -> Total Salary Calculation -> 30-Day Daily Breakdown (Date & Day, Status, Per-Day Salary Amount, Final Monthly Salary).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/middleware.php';

requireRole([ROLE_HR]);

$db = getDB();

// Handle Base Salary Update Form (if submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_action') === 'update_base_salary') {
    requireCsrf();
    $empId = (int)post('user_id');
    $newSalary = (float)post('base_salary');
    
    if ($empId > 0 && $newSalary >= 0) {
        $stmt = $db->prepare("UPDATE users SET base_salary = ? WHERE id = ?");
        $stmt->execute([$newSalary, $empId]);
        setFlash('success', 'Base salary updated successfully.');
    }
    header('Location: ' . BASE_URL . '/hr/payroll.php?month=' . get('month', date('Y-m')));
    exit;
}

// Selected Month and Year (default: current month)
$selectedMonthStr = get('month', date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthStr)) {
    $selectedMonthStr = date('Y-m');
}

$year = (int)substr($selectedMonthStr, 0, 4);
$month = (int)substr($selectedMonthStr, 5, 2);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$startDateStr = sprintf('%04d-%02d-01', $year, $month);
$endDateStr = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// Fetch all active/employee users
$employees = $db->query("
    SELECT id, employee_id, name, email, designation, base_salary, status
    FROM users
    WHERE role = 'employee'
    ORDER BY name ASC
")->fetchAll();

// Calculate payroll stats for each employee
$payrollData = [];

foreach ($employees as $emp) {
    $empId = $emp['id'];
    $baseSalary = (float)$emp['base_salary'];
    $perDayRate = $daysInMonth > 0 ? ($baseSalary / $daysInMonth) : 0;
    
    // Fetch attendance for this month
    $stmt = $db->prepare("
        SELECT date, check_in, check_out, total_working_time, status 
        FROM attendance 
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$empId, $startDateStr, $endDateStr]);
    $attendanceRows = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    
    // Fetch approved leaves for this month
    $stmt = $db->prepare("
        SELECT start_date, end_date, leave_type 
        FROM leaves 
        WHERE user_id = ? AND status = 'approved' AND NOT (end_date < ? OR start_date > ?)
    ");
    $stmt->execute([$empId, $startDateStr, $endDateStr]);
    $approvedLeaves = $stmt->fetchAll();
    
    // Process leaves into daily lookup
    $leaveDays = [];
    foreach ($approvedLeaves as $l) {
        $cur = max(strtotime($startDateStr), strtotime($l['start_date']));
        $last = min(strtotime($endDateStr), strtotime($l['end_date']));
        while ($cur <= $last) {
            $dStr = date('Y-m-d', $cur);
            $leaveDays[$dStr] = $l['leave_type'];
            $cur = strtotime('+1 day', $cur);
        }
    }
    
    // Calculate 30-day itemized list
    $dailyList = [];
    $totalWorkingSeconds = 0;
    $presentCount = 0;
    $halfDayCount = 0;
    $paidLeaveCount = 0;
    $unpaidLeaveCount = 0;
    $totalCalculatedSalary = 0;
    
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dayName = date('l', strtotime($dateStr));
        
        $att = $attendanceRows[$dateStr] ?? null;
        $leaveType = $leaveDays[$dateStr] ?? null;
        
        $statusLabel = 'Absent / Unpaid';
        $statusKey = 'unpaid';
        $dailyEarned = 0;
        $workTimeStr = '—';
        
        if ($att && !empty($att['check_in'])) {
            $workTimeStr = $att['total_working_time'] ?: '—';
            if ($att['total_working_time']) {
                $parts = explode(':', $att['total_working_time']);
                if (count($parts) >= 2) {
                    $totalWorkingSeconds += ((int)$parts[0] * 3600) + ((int)$parts[1] * 60);
                }
            }
            
            if ($att['status'] === 'half-day') {
                $statusLabel = 'Half-Day';
                $statusKey = 'half-day';
                $halfDayCount++;
                $dailyEarned = $perDayRate * 0.5;
            } else {
                $statusLabel = 'Present';
                $statusKey = 'present';
                $presentCount++;
                $dailyEarned = $perDayRate;
            }
        } elseif ($leaveType) {
            if (in_array($leaveType, ['casual', 'sick', 'paid'])) {
                $statusLabel = 'Paid Leave (' . ucfirst($leaveType) . ')';
                $statusKey = 'paid_leave';
                $paidLeaveCount++;
                $dailyEarned = $perDayRate;
            } else {
                $statusLabel = 'Unpaid Leave';
                $statusKey = 'unpaid';
                $unpaidLeaveCount++;
                $dailyEarned = 0;
            }
        } else {
            $unpaidLeaveCount++;
            $dailyEarned = 0;
        }
        
        $totalCalculatedSalary += $dailyEarned;
        
        $dailyList[] = [
            'day' => $d,
            'date' => $dateStr,
            'day_name' => $dayName,
            'status_label' => $statusLabel,
            'status_key' => $statusKey,
            'working_time' => $workTimeStr,
            'daily_amount' => $dailyEarned
        ];
    }
    
    // Format working hours
    $hours = floor($totalWorkingSeconds / 3600);
    $mins = floor(($totalWorkingSeconds % 3600) / 60);
    $formattedTotalHours = sprintf('%dh %02dm', $hours, $mins);
    
    $payrollData[$empId] = [
        'user' => $emp,
        'days_in_month' => $daysInMonth,
        'per_day_rate' => $perDayRate,
        'total_working_hours' => $formattedTotalHours,
        'present_days' => $presentCount,
        'half_days' => $halfDayCount,
        'paid_leaves' => $paidLeaveCount,
        'unpaid_leaves' => $unpaidLeaveCount,
        'net_salary' => $totalCalculatedSalary,
        'daily_breakdown' => $dailyList
    ];
}

// Selected breakdown view employee ID
$breakdownEmpId = get('breakdown_emp', null) ? (int)get('breakdown_emp') : null;
$breakdownDetail = $breakdownEmpId && isset($payrollData[$breakdownEmpId]) ? $payrollData[$breakdownEmpId] : null;

$pageTitle = 'HR — Payroll & Salary System';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 class="page-title">Payroll & Salary System</h1>
        <p class="page-subtitle">Monthly salary calculation, 30-day daily breakdown, attendance & leave status</p>
    </div>
    
    <!-- Month Selector -->
    <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); padding: 8px 16px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 10px;">
        <label class="form-label" style="margin-bottom: 0; font-weight: 600; color: var(--color-text-secondary);"><i class="fa-solid fa-calendar-days"></i> Month:</label>
        <form method="GET" action="" style="margin: 0;">
            <input type="month" name="month" class="form-input" value="<?php echo e($selectedMonthStr); ?>" onchange="this.form.submit()" style="width: auto; padding: 6px 12px;">
        </form>
    </div>
</div>

<!-- Overview 4-Metric Grid -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card accent-blue fade-in stagger-1">
        <div class="stat-icon bg-blue"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <div class="stat-content">
            <?php 
            $totalPayrollBudget = array_sum(array_column($payrollData, 'net_salary'));
            $empCount = count($employees);
            $avgSalary = $empCount > 0 ? ($totalPayrollBudget / $empCount) : 0;
            ?>
            <div class="stat-value">₹<?php echo number_format($totalPayrollBudget, 2); ?></div>
            <div class="stat-label">Total Monthly Payroll (<?php echo date('F Y', strtotime($startDateStr)); ?>)</div>
        </div>
    </div>
    <div class="stat-card accent-purple fade-in stagger-2">
        <div class="stat-icon bg-purple"><i class="fa-solid fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $empCount; ?></div>
            <div class="stat-label">Total Employees on Payroll</div>
        </div>
    </div>
    <div class="stat-card accent-orange fade-in stagger-3">
        <div class="stat-icon bg-warning"><i class="fa-solid fa-chart-pie"></i></div>
        <div class="stat-content">
            <div class="stat-value">₹<?php echo number_format($avgSalary, 2); ?></div>
            <div class="stat-label">Average Payout per Employee</div>
        </div>
    </div>
    <div class="stat-card accent-green fade-in stagger-4">
        <div class="stat-icon bg-green"><i class="fa-solid fa-calendar-days"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $daysInMonth; ?> Days</div>
            <div class="stat-label">Days in <?php echo date('F Y', strtotime($startDateStr)); ?></div>
        </div>
    </div>
</div>

<!-- Employee Payroll Table Card -->
<div class="card fade-in" style="padding: 0; overflow: hidden;">
    <!-- Top Right Corner Filters in Card Header -->
    <div class="card-header" style="padding: 16px 24px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <h3 class="card-title" style="margin: 0;">Employee Salary Summary — <?php echo date('F Y', strtotime($startDateStr)); ?></h3>
        
        <!-- Right Corner Filters -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="position: relative;">
                <input type="text" id="payrollSearchInput" onkeyup="filterPayrollTable()" placeholder="Search Employee / ID..." class="form-input" style="padding: 6px 12px 6px 30px; font-size: 13px; width: 210px; border-radius: var(--radius-md);">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); opacity: 0.6; font-size: 12px;"><i class="fa-solid fa-magnifying-glass"></i></span>
            </div>
            
            <select id="payrollStatusFilter" onchange="filterPayrollTable()" class="form-select" style="padding: 6px 12px; font-size: 13px; width: 165px; border-radius: var(--radius-md);">
                <option value="all">All Statuses</option>
                <option value="with_payout"><i class="fa-solid fa-circle-check"></i> Payable (> ₹0)</option>
                <option value="zero_payout"><i class="fa-solid fa-circle-xmark"></i> Zero Payout (₹0)</option>
            </select>
        </div>
    </div>
    
    <div class="table-container" style="border: none; border-radius: 0; overflow-x: auto; width: 100%;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="white-space: nowrap; padding: 12px 16px;">Emp ID</th>
                    <th style="white-space: nowrap; padding: 12px 16px;">Employee Name</th>
                    <th style="white-space: nowrap; padding: 12px 16px;">Base Salary</th>
                    <th style="white-space: nowrap; padding: 12px 16px;">Per Day Rate</th>
                    <th style="white-space: nowrap; padding: 12px 16px;">Work Hours</th>
                    <th style="white-space: nowrap; padding: 12px 16px;">Status</th>
                    <th style="white-space: nowrap; padding: 12px 16px;">Net Salary</th>
                    <th style="text-align: right; white-space: nowrap; padding: 12px 16px;">Action</th>
                </tr>
            </thead>
            <tbody id="payrollTableBody">
                <?php if (empty($payrollData)): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding: 24px;">No employees registered on payroll.</td></tr>
                <?php else: ?>
                    <?php foreach ($payrollData as $eId => $p): ?>
                        <tr data-emp-id="<?php echo e($p['user']['employee_id']); ?>" data-emp-name="<?php echo e($p['user']['name']); ?>" data-net-salary="<?php echo $p['net_salary']; ?>" style="vertical-align: middle;">
                            <td style="padding: 12px 16px;"><code><?php echo e($p['user']['employee_id']); ?></code></td>
                            <td style="padding: 12px 16px;">
                                <strong><?php echo e($p['user']['name']); ?></strong><br>
                                <small class="text-muted"><?php echo e($p['user']['designation'] ?: 'Employee'); ?></small>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                                    <strong>₹<?php echo number_format($p['user']['base_salary'], 2); ?></strong>
                                    <button type="button" onclick="editSalary(<?php echo $eId; ?>, <?php echo $p['user']['base_salary']; ?>)" style="background:none; border:none; color: var(--color-primary); cursor:pointer; font-size: 13px;" title="Edit Base Salary"><i class="fa-solid fa-pen"></i></button>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; white-space: nowrap;"><small class="text-muted">₹<?php echo number_format($p['per_day_rate'], 2); ?> / day</small></td>
                            <td style="padding: 12px 16px; white-space: nowrap;"><strong><?php echo e($p['total_working_hours']); ?></strong></td>
                            
                            <!-- Single Status Badge + Smooth Toggle -->
                            <td style="padding: 12px 16px; white-space: nowrap;">
                                <!-- Collapsed State (Default: 1 Primary Badge + Details Button) -->
                                <div id="status-collapsed-<?php echo $eId; ?>" style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($p['present_days'] > 0): ?>
                                        <span class="badge badge-success"><?php echo $p['present_days']; ?> Present</span>
                                    <?php elseif ($p['paid_leaves'] > 0): ?>
                                        <span class="badge badge-info"><?php echo $p['paid_leaves']; ?> Paid</span>
                                    <?php elseif ($p['half_days'] > 0): ?>
                                        <span class="badge badge-warning"><?php echo $p['half_days']; ?> Half</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><?php echo $p['unpaid_leaves']; ?> Absent</span>
                                    <?php endif; ?>

                                    <button type="button" onclick="toggleStatus(<?php echo $eId; ?>)" class="btn btn-ghost btn-sm" style="padding: 3px 8px; font-size: 11px; border: 1px solid var(--color-border); border-radius: 4px; color: var(--color-text-secondary); cursor: pointer;" title="Show full 30-day breakdown badges">
                                        ▾ Details
                                    </button>
                                </div>

                                <!-- Expanded State (All 4 Badges + Hide Button) -->
                                <div id="status-expanded-<?php echo $eId; ?>" style="display: none; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <span class="badge badge-success" style="font-size: 11px; padding: 3px 6px;"><?php echo $p['present_days']; ?> Present</span>
                                    <span class="badge badge-info" style="font-size: 11px; padding: 3px 6px;"><?php echo $p['paid_leaves']; ?> Paid</span>
                                    <span class="badge badge-warning" style="font-size: 11px; padding: 3px 6px;"><?php echo $p['half_days']; ?> Half</span>
                                    <span class="badge badge-danger" style="font-size: 11px; padding: 3px 6px;"><?php echo $p['unpaid_leaves']; ?> Absent</span>
                                    
                                    <button type="button" onclick="toggleStatus(<?php echo $eId; ?>)" class="btn btn-ghost btn-sm" style="padding: 3px 8px; font-size: 11px; border: 1px solid var(--color-border); border-radius: 4px; color: var(--color-text-secondary); cursor: pointer;">
                                        ▴ Hide
                                    </button>
                                </div>
                            </td>

                            <td style="padding: 12px 16px; white-space: nowrap;">
                                <strong style="font-size: var(--text-base); color: var(--color-success);">
                                    ₹<?php echo number_format($p['net_salary'], 2); ?>
                                </strong>
                            </td>
                            <td style="text-align: right; white-space: nowrap; padding: 12px 16px;">
                                <a href="?month=<?php echo $selectedMonthStr; ?>&breakdown_emp=<?php echo $eId; ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px;">
                                    <i class="fa-solid fa-magnifying-glass"></i> 30-Day Breakdown
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: 30-DAY DAILY BREAKDOWN -->
<?php if ($breakdownDetail): ?>
    <div class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px;">
        <div class="card fade-in" style="max-width: 850px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; padding: 0;">
            <!-- Modal Header -->
            <div class="card-header" style="padding: 20px 24px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; background: var(--color-bg-card);">
                <div>
                    <h3 class="card-title" style="margin: 0 0 4px 0;"><i class="fa-solid fa-calendar-days"></i> 30-Day Salary Breakdown: <?php echo e($breakdownDetail['user']['name']); ?></h3>
                    <p class="card-subtitle" style="margin: 0;">
                        Month: <strong><?php echo date('F Y', strtotime($startDateStr)); ?></strong> | 
                        Base Salary: <strong>₹<?php echo number_format($breakdownDetail['user']['base_salary'], 2); ?></strong> | 
                        Per Day Rate: <strong>₹<?php echo number_format($breakdownDetail['per_day_rate'], 2); ?></strong>
                    </p>
                </div>
                <a href="?month=<?php echo $selectedMonthStr; ?>" class="btn btn-ghost btn-sm" style="text-decoration: none; font-size: 20px; color: var(--color-text-secondary);">✕</a>
            </div>

            <!-- Modal Content Body -->
            <div style="padding: 20px 24px; overflow-y: auto; flex: 1;">
                <!-- Summary Chips Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px;">
                    <div style="background: var(--color-bg-secondary); padding: 10px 14px; border-radius: var(--radius-md); font-size: var(--text-sm);">
                        <i class="fa-solid fa-stopwatch"></i> Working Hours<br><strong><?php echo e($breakdownDetail['total_working_hours']); ?></strong>
                    </div>
                    <div style="background: var(--color-bg-secondary); padding: 10px 14px; border-radius: var(--radius-md); font-size: var(--text-sm);">
                        <i class="fa-solid fa-circle-check"></i> Present Days<br><strong style="color: var(--color-success);"><?php echo $breakdownDetail['present_days']; ?> days</strong>
                    </div>
                    <div style="background: var(--color-bg-secondary); padding: 10px 14px; border-radius: var(--radius-md); font-size: var(--text-sm);">
                        <i class="fa-solid fa-umbrella-beach"></i> Paid Leaves<br><strong style="color: var(--color-info);"><?php echo $breakdownDetail['paid_leaves']; ?> days</strong>
                    </div>
                    <div style="background: var(--color-bg-secondary); padding: 10px 14px; border-radius: var(--radius-md); font-size: var(--text-sm);">
                        <i class="fa-solid fa-hourglass-half"></i> Half Days<br><strong style="color: var(--color-warning);"><?php echo $breakdownDetail['half_days']; ?> days</strong>
                    </div>
                    <div style="background: var(--color-bg-secondary); padding: 10px 14px; border-radius: var(--radius-md); font-size: var(--text-sm);">
                        <i class="fa-solid fa-circle-xmark"></i> Unpaid / Absent<br><strong style="color: var(--color-danger);"><?php echo $breakdownDetail['unpaid_leaves']; ?> days</strong>
                    </div>
                </div>

                <!-- 30-Day Itemized Table -->
                <div class="table-container" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); overflow-x: auto;">
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Date & Day</th>
                                <th>Attendance / Leave Status</th>
                                <th>Working Hours</th>
                                <th style="text-align: right;">Daily Salary Earned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($breakdownDetail['daily_breakdown'] as $dayRow): ?>
                                <tr>
                                    <td><code>Day <?php echo $dayRow['day']; ?></code></td>
                                    <td>
                                        <strong><?php echo formatDate($dayRow['date']); ?></strong>
                                        <small class="text-muted">(<?php echo $dayRow['day_name']; ?>)</small>
                                    </td>
                                    <td>
                                        <?php if ($dayRow['status_key'] === 'present'): ?>
                                            <span class="badge badge-success">Present (100%)</span>
                                        <?php elseif ($dayRow['status_key'] === 'paid_leave'): ?>
                                            <span class="badge badge-info"><?php echo e($dayRow['status_label']); ?></span>
                                        <?php elseif ($dayRow['status_key'] === 'half-day'): ?>
                                            <span class="badge badge-warning">Half-Day (50%)</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Unpaid / Absent (0%)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($dayRow['working_time']); ?></td>
                                    <td style="text-align: right;">
                                        <strong>₹<?php echo number_format($dayRow['daily_amount'], 2); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Footer Total Payout Card -->
            <div style="padding: 16px 24px; background: var(--color-bg-secondary); border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 style="margin: 0; font-size: var(--text-base);">Total Calculated Monthly Salary Payout</h4>
                    <small class="text-muted">(Calculated across all 30/31 daily statuses)</small>
                </div>
                <div style="font-size: 24px; font-weight: bold; color: var(--color-success);">
                    ₹<?php echo number_format($breakdownDetail['net_salary'], 2); ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL: EDIT BASE SALARY -->
<div id="editSalaryModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 1000;">
    <div class="card fade-in" style="max-width: 400px; width: 100%; padding: 20px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 class="card-title" style="margin: 0;">Update Base Salary</h3>
            <button type="button" onclick="closeSalaryModal()" class="btn btn-ghost btn-sm" style="font-size: 18px; color: var(--color-text-secondary);">✕</button>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="update_base_salary">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">Base Monthly Salary (₹) *</label>
                <input type="number" step="500" name="base_salary" id="edit_base_salary" class="form-input" required placeholder="e.g. 35000">
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Base Salary</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterPayrollTable() {
    const searchVal = document.getElementById('payrollSearchInput').value.toLowerCase().trim();
    const filterVal = document.getElementById('payrollStatusFilter').value;
    const rows = document.querySelectorAll('#payrollTableBody tr');
    
    rows.forEach(row => {
        const empId = row.getAttribute('data-emp-id') || '';
        const empName = row.getAttribute('data-emp-name') || '';
        const netSalary = parseFloat(row.getAttribute('data-net-salary') || 0);
        
        const matchesSearch = empId.toLowerCase().includes(searchVal) || empName.toLowerCase().includes(searchVal);
        let matchesFilter = true;
        
        if (filterVal === 'with_payout') {
            matchesFilter = netSalary > 0;
        } else if (filterVal === 'zero_payout') {
            matchesFilter = netSalary === 0;
        }
        
        if (matchesSearch && matchesFilter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function toggleStatus(eId) {
    const collapsed = document.getElementById('status-collapsed-' + eId);
    const expanded = document.getElementById('status-expanded-' + eId);
    if (collapsed.style.display === 'none') {
        collapsed.style.display = 'flex';
        expanded.style.display = 'none';
    } else {
        collapsed.style.display = 'none';
        expanded.style.display = 'flex';
    }
}

function editSalary(userId, currentSalary) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_base_salary').value = currentSalary;
    document.getElementById('editSalaryModal').style.display = 'flex';
}

function closeSalaryModal() {
    document.getElementById('editSalaryModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
