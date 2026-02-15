<?php

include ("./includes/common.php");
include ("./includes/date_functions.php");

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}

$type = strtolower(getparam("type"));
if (!$type) {
	$type = "ukrainian";
}

$current_year = date('Y');
$next_year = $current_year + 1;
$selected_year = getparam("year") ? intval(getparam("year")) : $current_year;

if ($type == "english") {
	$holidays_table = "english_holidays";
	$country = "English";
} else {
	$holidays_table = "national_holidays";
	$country = "Ukrainian";
}

// Get available years for dropdown
$years = array();
$sql = "SELECT DISTINCT YEAR(holiday_date) as year FROM " . $holidays_table . " ORDER BY year DESC";
$db->query($sql);
while ($db->next_record()) {
	$years[] = $db->Record["year"];
}
// Make sure current and next year are in list
if (!in_array($current_year, $years)) $years[] = $current_year;
if (!in_array($next_year, $years)) $years[] = $next_year;
rsort($years);

// Get holidays for selected year
$holidays = array();
$sql = "SELECT * FROM " . $holidays_table . " WHERE YEAR(holiday_date) = " . intval($selected_year) . " ORDER BY holiday_date";
$db->query($sql);
while ($db->next_record()) {
	$holidays[] = array(
		'holiday_id' => $db->Record["holiday_id"],
		'holiday_title' => $db->Record["holiday_title"],
		'holiday_date' => $db->Record["holiday_date"]
	);
}

// Get previous year holidays for magic generation
$prev_year_holidays = array();
$sql = "SELECT DISTINCT holiday_title, DAY(holiday_date) as day, MONTH(holiday_date) as month FROM " . $holidays_table . " WHERE YEAR(holiday_date) = " . ($selected_year - 1) . " ORDER BY holiday_date";
$db->query($sql);
while ($db->next_record()) {
	$prev_year_holidays[] = array(
		'title' => $db->Record["holiday_title"],
		'day' => $db->Record["day"],
		'month' => $db->Record["month"]
	);
}

// If no previous year, try template
if (empty($prev_year_holidays)) {
	$sql = "SELECT holiday_title, DAY(holiday_date) as day, MONTH(holiday_date) as month FROM holiday_template ORDER BY holiday_date";
	$db->query($sql);
	while ($db->next_record()) {
		$day = $db->Record["day"];
		$month = $db->Record["month"];
		if ($day && $month) {
			$prev_year_holidays[] = array(
				'title' => $db->Record["holiday_title"],
				'day' => $day,
				'month' => $month
			);
		}
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $country; ?> National Holidays - Control</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="site.css" type="text/css"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body.PageBODY {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            color: #2d3748;
        }

        .holidays-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 4px;
        }

        .page-subtitle {
            color: #718096;
            font-size: 0.9rem;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a5f 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(44, 90, 160, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-magic {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
        }

        .btn-magic:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-danger {
            background: #fc8181;
            color: #742a2a;
        }

        .btn-danger:hover {
            background: #f56565;
        }

        .btn-secondary {
            background: white;
            border: 2px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            text-decoration: none;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        /* Filters bar */
        .filters-bar {
            background: white;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .year-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .year-filter label {
            font-weight: 500;
            color: #4a5568;
            font-size: 0.85rem;
        }

        .filter-select {
            padding: 10px 32px 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L2 4h8z'/%3E%3C/svg%3E") no-repeat right 12px center;
            cursor: pointer;
            appearance: none;
        }

        .filter-select:focus {
            outline: none;
            border-color: #2c5aa0;
        }

        .type-tabs {
            display: flex;
            gap: 4px;
        }

        .type-tab {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
            color: #4a5568;
            text-decoration: none;
        }

        .type-tab:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            text-decoration: none;
            color: #4a5568;
        }

        .type-tab.active {
            background: #2c5aa0;
            color: white;
            border-color: #2c5aa0;
        }

        .magic-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        /* Table container */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .holidays-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .holidays-table thead {
            background: #f7fafc;
        }

        .holidays-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        .holidays-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }

        .holidays-table tbody tr {
            transition: background 0.1s;
        }

        .holidays-table tbody tr:hover {
            background: #f7fafc;
        }

        .holidays-table tbody tr:last-child td {
            border-bottom: none;
        }

        .holidays-table .date-col {
            width: 200px;
        }

        .holidays-table .actions-col {
            width: 180px;
            text-align: right;
        }

        .holiday-date {
            color: #4a5568;
        }

        .holiday-date .day {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c5aa0;
        }

        .holiday-date .weekday {
            font-size: 0.8rem;
            color: #a0aec0;
            margin-left: 8px;
        }

        .holiday-date .weekday.weekend {
            color: #e53e3e;
        }

        .holiday-title {
            font-weight: 500;
            color: #1a202c;
        }

        .editable-input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
        }

        .editable-input:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
        }

        /* Add form */
        .add-holiday-form {
            display: none;
            background: #ebf4ff;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .add-holiday-form.active {
            display: block;
        }

        .add-holiday-form .form-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .add-holiday-form .form-group {
            flex: 1;
        }

        .add-holiday-form label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: #4a5568;
            font-size: 0.8rem;
        }

        .add-holiday-form input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
        }

        .add-holiday-form input:focus {
            outline: none;
            border-color: #2c5aa0;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            color: #718096;
        }

        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.1rem;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .empty-state p {
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 10px;
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            opacity: 0.8;
            line-height: 1;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 24px;
            max-height: 55vh;
            overflow-y: auto;
        }

        .modal-body > p {
            margin-bottom: 16px;
            color: #718096;
            font-size: 0.9rem;
        }

        .modal-footer {
            padding: 16px 24px;
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .magic-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .magic-table th,
        .magic-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
        }

        .magic-table th {
            background: #f7fafc;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #4a5568;
        }

        .magic-table tr.duplicate {
            background: #fef3c7;
        }

        .magic-table tr.duplicate td {
            color: #92400e;
        }

        .magic-table input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .magic-table input[type="text"],
        .magic-table input[type="date"] {
            padding: 6px 10px;
            border: 2px solid #e2e8f0;
            border-radius: 4px;
            font-size: 0.8rem;
            font-family: inherit;
        }

        .magic-table input[type="text"] {
            width: 100%;
        }

        .weekend-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 6px;
        }

        .weekend-badge.adjusted {
            background: #c6f6d5;
            color: #22543d;
        }

        .weekend-badge.calculated {
            background: #e9d8fd;
            color: #553c9a;
        }

        .status-text {
            font-size: 0.85rem;
            color: #718096;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 0.85rem;
            font-weight: 500;
            z-index: 2000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            background: #48bb78;
        }

        .toast.error {
            background: #f56565;
        }

        @media (max-width: 768px) {
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .magic-buttons {
                margin-left: 0;
                width: 100%;
                justify-content: flex-start;
            }
            
            .type-tabs {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="PageBODY">

<?php include("./templates/header.html"); ?>

<div class="holidays-container">
    <div class="page-header">
        <div>
            <h1 class="page-title"><?php echo $country; ?> National Holidays</h1>
            <p class="page-subtitle">Manage national holidays for <?php echo $selected_year; ?></p>
        </div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="toggleAddForm()">+ Add Holiday</button>
        </div>
    </div>
    
    <div class="filters-bar">
        <div class="year-filter">
            <label for="yearSelect">Year:</label>
            <select id="yearSelect" class="filter-select" onchange="changeYear(this.value)">
                <?php foreach ($years as $year): ?>
                <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="type-tabs">
            <a href="holidays.php?type=ukrainian&year=<?php echo $selected_year; ?>" class="type-tab <?php echo $type == 'ukrainian' ? 'active' : ''; ?>">Ukrainian</a>
            <a href="holidays.php?type=english&year=<?php echo $selected_year; ?>" class="type-tab <?php echo $type == 'english' ? 'active' : ''; ?>">English</a>
        </div>
        
        <div class="magic-buttons">
            <button class="btn btn-magic" onclick="openMagicModal(<?php echo $current_year; ?>)">
                &#10024; Magic <?php echo $current_year; ?>
            </button>
            <button class="btn btn-magic" onclick="openMagicModal(<?php echo $next_year; ?>)">
                &#10024; Magic <?php echo $next_year; ?>
            </button>
        </div>
    </div>
    
    <div class="table-container">
        <div class="add-holiday-form" id="addHolidayForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="newHolidayTitle">Holiday Name</label>
                    <input type="text" id="newHolidayTitle" placeholder="Enter holiday name">
                </div>
                <div class="form-group" style="max-width: 180px;">
                    <label for="newHolidayDate">Date</label>
                    <input type="date" id="newHolidayDate">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-success" onclick="addHoliday()">Add</button>
                    <button class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
                </div>
            </div>
        </div>
        
        <table class="holidays-table" id="holidaysTable">
            <thead>
                <tr>
                    <th class="date-col">Date</th>
                    <th>Holiday Name</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody id="holidaysBody">
                <?php if (empty($holidays)): ?>
                <tr id="emptyRow">
                    <td colspan="3">
                        <div class="empty-state">
                            <div class="icon">&#128197;</div>
                            <h3>No holidays for <?php echo $selected_year; ?></h3>
                            <p>Click "Magic <?php echo $selected_year; ?>" to generate holidays automatically</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($holidays as $h): ?>
                <?php 
                    $date = strtotime($h['holiday_date']);
                    $dayOfWeek = date('w', $date);
                    $weekdayName = date('l', $date);
                    $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
                ?>
                <tr data-id="<?php echo $h['holiday_id']; ?>" data-date="<?php echo $h['holiday_date']; ?>">
                    <td class="date-col">
                        <div class="holiday-date display-date">
                            <span class="day"><?php echo date('j', $date); ?></span>
                            <?php echo date('M Y', $date); ?>
                            <span class="weekday <?php echo $isWeekend ? 'weekend' : ''; ?>"><?php echo $weekdayName; ?></span>
                        </div>
                        <input type="date" class="editable-input edit-date" value="<?php echo $h['holiday_date']; ?>" style="display:none;">
                    </td>
                    <td>
                        <span class="holiday-title"><?php echo htmlspecialchars($h['holiday_title']); ?></span>
                        <input type="text" class="editable-input edit-title" value="<?php echo htmlspecialchars($h['holiday_title']); ?>" style="display:none;">
                    </td>
                    <td class="actions-col">
                        <div class="action-buttons">
                            <button class="btn btn-secondary btn-sm edit-btn" onclick="editRow(this)">Edit</button>
                            <button class="btn btn-success btn-sm save-btn" onclick="saveRow(this)" style="display:none;">Save</button>
                            <button class="btn btn-secondary btn-sm cancel-btn" onclick="cancelEdit(this)" style="display:none;">Cancel</button>
                            <button class="btn btn-danger btn-sm delete-btn" onclick="deleteHoliday(<?php echo $h['holiday_id']; ?>)">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Magic Modal -->
<div class="modal-overlay" id="magicModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="magicModalTitle">&#10024; Magic Holidays</h2>
            <button class="modal-close" onclick="closeMagicModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Suggested holidays based on previous year. Weekend dates are adjusted to Friday or Monday. Duplicates are highlighted.</p>
            <table class="magic-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Holiday Name</th>
                        <th style="width:150px;">Date</th>
                        <th style="width:100px;">Day</th>
                    </tr>
                </thead>
                <tbody id="magicTableBody">
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <div class="status-text" id="magicStatus">Select holidays to add</div>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="closeMagicModal()">Cancel</button>
                <button class="btn btn-success" onclick="addMagicHolidays()">Add Selected</button>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const holidaysTable = '<?php echo $holidays_table; ?>';
const holidayType = '<?php echo $type; ?>';
const currentYear = <?php echo $current_year; ?>;
const prevYearHolidays = <?php echo json_encode($prev_year_holidays); ?>;

function changeYear(year) {
    window.location.href = 'holidays.php?type=' + holidayType + '&year=' + year;
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function toggleAddForm() {
    const form = document.getElementById('addHolidayForm');
    form.classList.toggle('active');
    if (form.classList.contains('active')) {
        document.getElementById('newHolidayTitle').focus();
    }
}

function addHoliday() {
    const title = document.getElementById('newHolidayTitle').value.trim();
    const date = document.getElementById('newHolidayDate').value;
    
    if (!title || !date) {
        showToast('Please fill in all fields', 'error');
        return;
    }
    
    fetch('ajax_responder.php?action=add_holiday', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({table: holidaysTable, title: title, date: date})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Holiday added successfully');
            location.reload();
        } else {
            showToast(data.error || 'Failed to add holiday', 'error');
        }
    })
    .catch(() => showToast('Failed to add holiday', 'error'));
}

function editRow(btn) {
    const row = btn.closest('tr');
    row.querySelector('.holiday-title').style.display = 'none';
    row.querySelector('.display-date').style.display = 'none';
    row.querySelector('.edit-title').style.display = 'block';
    row.querySelector('.edit-date').style.display = 'block';
    row.querySelector('.edit-btn').style.display = 'none';
    row.querySelector('.delete-btn').style.display = 'none';
    row.querySelector('.save-btn').style.display = 'inline-flex';
    row.querySelector('.cancel-btn').style.display = 'inline-flex';
    row.querySelector('.edit-title').focus();
}

function cancelEdit(btn) {
    const row = btn.closest('tr');
    const titleSpan = row.querySelector('.holiday-title');
    const titleInput = row.querySelector('.edit-title');
    const dateInput = row.querySelector('.edit-date');
    titleInput.value = titleSpan.textContent;
    dateInput.value = row.dataset.date;
    titleSpan.style.display = 'inline';
    titleInput.style.display = 'none';
    row.querySelector('.display-date').style.display = 'block';
    dateInput.style.display = 'none';
    row.querySelector('.edit-btn').style.display = 'inline-flex';
    row.querySelector('.delete-btn').style.display = 'inline-flex';
    row.querySelector('.save-btn').style.display = 'none';
    row.querySelector('.cancel-btn').style.display = 'none';
}

function saveRow(btn) {
    const row = btn.closest('tr');
    const id = row.dataset.id;
    const title = row.querySelector('.edit-title').value.trim();
    const date = row.querySelector('.edit-date').value;
    
    if (!title) {
        showToast('Holiday name cannot be empty', 'error');
        return;
    }
    
    if (!date) {
        showToast('Date cannot be empty', 'error');
        return;
    }
    
    fetch('ajax_responder.php?action=update_holiday', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({table: holidaysTable, id: id, title: title, date: date})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showToast(data.error || 'Failed to update', 'error');
        }
    })
    .catch(() => showToast('Failed to update', 'error'));
}

function deleteHoliday(id) {
    if (!confirm('Are you sure you want to delete this holiday?')) return;
    
    fetch('ajax_responder.php?action=delete_holiday', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({table: holidaysTable, id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            row.remove();
            showToast('Holiday deleted');
            if (document.querySelectorAll('#holidaysBody tr[data-id]').length === 0) {
                location.reload();
            }
        } else {
            showToast(data.error || 'Failed to delete', 'error');
        }
    })
    .catch(() => showToast('Failed to delete', 'error'));
}

function adjustForWeekend(year, month, day, usedDates = []) {
    let date = new Date(year, month - 1, day);
    const dayOfWeek = date.getDay();
    let adjusted = false;
    let adjustedTo = '';
    
    if (dayOfWeek === 0) {
        date.setDate(date.getDate() + 1);
        adjusted = true;
        adjustedTo = 'Mon';
    } else if (dayOfWeek === 6) {
        let fridayDate = new Date(date);
        fridayDate.setDate(fridayDate.getDate() - 1);
        const fridayStr = formatDate(fridayDate);
        
        if (usedDates.includes(fridayStr)) {
            date.setDate(date.getDate() + 2);
            adjusted = true;
            adjustedTo = 'Mon';
        } else {
            date.setDate(date.getDate() - 1);
            adjusted = true;
            adjustedTo = 'Fri';
        }
    }
    
    return { date, adjusted, adjustedTo };
}

function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function getOrthodoxEaster(year) {
    const a = year % 4;
    const b = year % 7;
    const c = year % 19;
    const d = (19 * c + 15) % 30;
    const e = (2 * a + 4 * b - d + 34) % 7;
    const month = Math.floor((d + e + 114) / 31);
    const day = ((d + e + 114) % 31) + 1;
    const julianDate = new Date(year, month - 1, day);
    julianDate.setDate(julianDate.getDate() + 13);
    return julianDate;
}

function getOrthodoxTrinity(year) {
    const easter = getOrthodoxEaster(year);
    const trinity = new Date(easter);
    trinity.setDate(trinity.getDate() + 49);
    return trinity;
}

function getDayName(date) {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return days[date.getDay()];
}

function isMovableHoliday(title) {
    const lower = title.toLowerCase();
    const easterKeywords = ['easter', 'пасха', 'великдень', 'воскресіння'];
    const trinityKeywords = ['trinity', 'трійц', 'п\'ятдесятниц', 'pentecost', 'зелені свята'];
    
    for (const kw of easterKeywords) if (lower.includes(kw)) return 'easter';
    for (const kw of trinityKeywords) if (lower.includes(kw)) return 'trinity';
    return null;
}

function openMagicModal(targetYear) {
    const modal = document.getElementById('magicModal');
    const title = document.getElementById('magicModalTitle');
    const tbody = document.getElementById('magicTableBody');
    
    title.innerHTML = `&#10024; Magic Holidays ${targetYear}`;
    tbody.innerHTML = '';
    
    const orthodoxEaster = getOrthodoxEaster(targetYear);
    const orthodoxTrinity = getOrthodoxTrinity(targetYear);
    
    fetch('ajax_responder.php?action=get_holidays&table=' + holidaysTable + '&year=' + targetYear)
    .then(r => r.json())
    .then(existingData => {
        const existingDates = existingData.holidays.map(h => h.holiday_date);
        const existingTitles = existingData.holidays.map(h => h.holiday_title.toLowerCase());
        const usedDates = [...existingDates];
        
        prevYearHolidays.forEach((h, i) => {
            let dateObj;
            let isCalculated = false;
            let calculatedNote = '';
            
            const movableType = isMovableHoliday(h.title);
            
            if (movableType === 'easter') {
                dateObj = new Date(orthodoxEaster);
                isCalculated = true;
                calculatedNote = 'Orthodox Easter';
            } else if (movableType === 'trinity') {
                dateObj = new Date(orthodoxTrinity);
                isCalculated = true;
                calculatedNote = 'Orthodox Trinity';
            } else {
                const result = adjustForWeekend(targetYear, h.month, h.day, usedDates);
                dateObj = result.date;
                if (result.adjusted) calculatedNote = `→ ${result.adjustedTo}`;
            }
            
            const dateStr = formatDate(dateObj);
            const dayName = getDayName(dateObj);
            
            const isDateDuplicate = existingDates.includes(dateStr);
            const isTitleDuplicate = existingTitles.includes(h.title.toLowerCase());
            const isDuplicate = isDateDuplicate || isTitleDuplicate;
            
            if (!isDuplicate) usedDates.push(dateStr);
            
            const tr = document.createElement('tr');
            if (isDuplicate) tr.className = 'duplicate';
            
            let badgeHtml = '';
            if (calculatedNote) {
                const badgeClass = isCalculated ? 'calculated' : 'adjusted';
                badgeHtml = `<span class="weekend-badge ${badgeClass}">${calculatedNote}</span>`;
            }
            
            tr.innerHTML = `
                <td><input type="checkbox" class="magic-check" data-index="${i}" ${isDuplicate ? 'disabled' : 'checked'}></td>
                <td><input type="text" class="magic-title" value="${h.title}" ${isDuplicate ? 'disabled' : ''}></td>
                <td><input type="date" class="magic-date" value="${dateStr}" ${isDuplicate ? 'disabled' : ''}> ${badgeHtml}</td>
                <td>${dayName}${isDuplicate ? ' <em>(exists)</em>' : ''}</td>
            `;
            tbody.appendChild(tr);
        });
        
        if (prevYearHolidays.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px;color:#718096;">No template holidays found. Add holidays to previous year first.</td></tr>';
        }
        
        updateMagicStatus();
        modal.classList.add('active');
    });
}

function closeMagicModal() {
    document.getElementById('magicModal').classList.remove('active');
}

function updateMagicStatus() {
    const checked = document.querySelectorAll('.magic-check:checked:not(:disabled)').length;
    document.getElementById('magicStatus').textContent = checked + ' holiday(s) selected';
}

document.addEventListener('change', e => {
    if (e.target.classList.contains('magic-check')) updateMagicStatus();
});

function addMagicHolidays() {
    const rows = document.querySelectorAll('#magicTableBody tr');
    const holidays = [];
    
    rows.forEach(row => {
        const checkbox = row.querySelector('.magic-check');
        if (checkbox && checkbox.checked && !checkbox.disabled) {
            const title = row.querySelector('.magic-title').value.trim();
            const date = row.querySelector('.magic-date').value;
            if (title && date) holidays.push({title, date});
        }
    });
    
    if (holidays.length === 0) {
        showToast('No holidays selected', 'error');
        return;
    }
    
    fetch('ajax_responder.php?action=add_holidays_bulk', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({table: holidaysTable, holidays: holidays})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.added + ' holiday(s) added successfully');
            closeMagicModal();
            const firstDate = holidays[0].date;
            const year = firstDate.split('-')[0];
            window.location.href = 'holidays.php?type=' + holidayType + '&year=' + year;
        } else {
            showToast(data.error || 'Failed to add holidays', 'error');
        }
    })
    .catch(() => showToast('Failed to add holidays', 'error'));
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeMagicModal();
});
document.getElementById('magicModal').addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) closeMagicModal();
});
</script>

</body>
</html>
