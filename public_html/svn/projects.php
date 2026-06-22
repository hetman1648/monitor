<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$user_name = GetSessionParam("UserName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Sayu Monitor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            color: #2d3748;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2d3748;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        /* Alert notification */
        .alert-toast {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            display: none;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-width: 400px;
        }

        .alert-success {
            background: #c6f6d5;
            color: #276749;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
        }

        /* Projects container */
        #divProjects {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        #divProjects table {
            width: 100%;
            border-collapse: collapse;
        }

        #divProjects th {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        #divProjects td {
            padding: 10px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        #divProjects tr:hover td {
            background: #f7fafc;
        }

        #divProjects a {
            color: #667eea;
            text-decoration: none;
        }

        #divProjects a:hover {
            text-decoration: underline;
        }

        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 100%;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-box.modal-lg {
            max-width: 800px;
        }

        .modal-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            opacity: 0.8;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 16px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 6px;
            font-size: 0.85rem;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group small {
            display: block;
            color: #718096;
            font-size: 0.8rem;
            margin-top: 4px;
        }

        .form-hint {
            color: #718096;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        .form-hint svg {
            vertical-align: middle;
            margin-right: 6px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .radio-group input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .notes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .notes-table th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        .notes-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
            vertical-align: top;
        }

        .notes-table textarea {
            width: 100%;
            min-height: 60px;
            padding: 8px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            resize: vertical;
        }

        .notes-table textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .notes-alert {
            padding: 10px 14px;
            background: #c6f6d5;
            color: #276749;
            border-radius: 6px;
            margin-bottom: 12px;
            display: none;
            font-size: 0.85rem;
        }

        /* User tasks sortable */
        #sortable {
            list-style: none;
            padding: 0;
        }

        #sortable li {
            padding: 10px 14px;
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: move;
            font-size: 0.9rem;
        }

        #sortable li:hover {
            background: #edf2f7;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <link href="css/projects.css" rel="stylesheet" media="screen">
</head>
<body>
    <?php $root_path = "../"; include("../includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <h1>Projects</h1>
            <button class="btn btn-secondary" id="lnkSettings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                Settings
            </button>
        </div>

        <div class="alert-toast" id="infoLine"></div>

        <div id="divProjects">
            <div style="padding: 40px; text-align: center; color: #718096;">
                <div class="loading-spinner"></div>
                <p style="margin-top: 12px;">Loading projects...</p>
            </div>
        </div>
    </div>

    <!-- History/Info Modal -->
    <div class="modal-overlay" id="myModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="popupTitle">History</h3>
                <button class="modal-close" onclick="closeModal('myModal')">&times;</button>
            </div>
            <div class="modal-body" id="infoBox"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('myModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Estimates Modal -->
    <div class="modal-overlay" id="estimatesModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Task Duration</h3>
                <button class="modal-close" onclick="closeModal('estimatesModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    Enter or change "<span id="estimatesModalTitle"></span>" duration
                </div>
                <p style="font-size: 0.8rem; color: #718096; margin-bottom: 16px;">
                    Supported formats: 2d, 2days, 8hrs, 8h, 30mins, 1week
                </p>
                <form id="frmEstimate">
                    <input type="hidden" id="hdnTaskID">
                    <div class="form-group">
                        <label>Estimated Duration</label>
                        <input type="text" id="inputDuration" placeholder="e.g. 1 day">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('estimatesModal')">Cancel</button>
                <button class="btn btn-primary" id="bntSaveEstimates">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal-overlay" id="settingsModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Settings</h3>
                <button class="modal-close" onclick="closeModal('settingsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                        <line x1="4" y1="22" x2="4" y2="15"></line>
                    </svg>
                    Change report display settings
                </div>
                <div id="settingsModalBody">
                    <div class="loading-spinner"></div> Loading...
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('settingsModal')">Cancel</button>
                <button class="btn btn-primary" id="bntSaveSettings">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Status Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Task Status and Completion</h3>
                <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                        <line x1="4" y1="22" x2="4" y2="15"></line>
                    </svg>
                    Change "<span id="statusModalTitle"></span>" status and completion
                </div>
                <input type="hidden" id="statusHiddenID">
                <form id="frmCompletion">
                    <div class="form-group">
                        <label>Completion (%)</label>
                        <input type="number" min="0" max="100" id="inputCompletion" placeholder="0">
                    </div>
                </form>
                <form id="frmStatus">
                    <div class="form-group">
                        <label>Task Status</label>
                        <div class="radio-group" id="statusRadios"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button class="btn btn-primary" id="bntSaveStatus">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Dependent/Linking Modal -->
    <div class="modal-overlay" id="dependentModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Linking Tasks</h3>
                <button class="modal-close" onclick="closeModal('dependentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 16 16 12 12 8"></polyline>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    Successor Task: "<span id="dependentModalTitle"></span>"
                </div>
                <p style="font-size: 0.8rem; color: #718096; margin-bottom: 16px;">
                    The dependent task can be completed anytime after the predecessor task is completed.
                </p>
                <form id="frmDependent">
                    <input type="hidden" id="dependentHiddenID">
                    <div class="form-group">
                        <label>Predecessor Task</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="inputDependent" placeholder="#, Task ID or Task Title" style="flex: 1;">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnClearDependent">Clear</button>
                        </div>
                    </div>
                    <span id="dependentInfo" style="font-size: 0.8rem; color: #718096;"></span>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('dependentModal')">Cancel</button>
                <button class="btn btn-primary" id="bntSaveDependent">Link Tasks</button>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h3 id="modalUsersTitle">User Tasks</h3>
                <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
                <input type="hidden" id="userHiddenID">
            </div>
            <div class="modal-body" id="userModalBody">
                <div class="loading-spinner"></div> Loading...
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('userModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Project Modal -->
    <div class="modal-overlay" id="projectModal">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h3>Project Settings</h3>
                <button class="modal-close" onclick="closeModal('projectModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="frmProject">
                    <input type="hidden" id="projectHiddenID">
                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" id="inputProjectName">
                    </div>
                    <div class="form-group">
                        <label>Responsible User</label>
                        <select id="selectProjectUsers"></select>
                    </div>
                    <div class="form-group">
                        <label>Project Status</label>
                        <select id="selectProjectStatuses"></select>
                    </div>
                </form>

                <div style="margin-top: 24px;">
                    <label style="font-weight: 600; color: #4a5568; margin-bottom: 12px; display: block;">Project Notes</label>
                    <div class="notes-alert" id="notesMsg"></div>
                    <table class="notes-table" id="tableNotes">
                        <thead>
                            <tr>
                                <th style="width: 120px;">Date</th>
                                <th style="width: 100px;">User</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo date('D, jS M Y'); ?></td>
                                <td><?php echo $current_user_first_name; ?></td>
                                <td>
                                    <textarea id="addNote" placeholder="Add a note..."></textarea>
                                    <button class="btn btn-primary btn-sm" id="btnSaveNote" style="margin-top: 8px;">Save Note</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="btnEditProject">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit Project
                </button>
                <button class="btn btn-danger" id="btnCloseProject">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    Close Project
                </button>
                <button class="btn btn-secondary" onclick="closeModal('projectModal')">Cancel</button>
                <button class="btn btn-primary" id="bntSaveProject">Update Project</button>
            </div>
        </div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/jquery.autosize.min.js"></script>
    <script src="js/jquery-ui.js"></script>

    <script>
    // Modal functions
    function openModal(id) {
        $('#' + id).addClass('active');
        $('body').css('overflow', 'hidden');
    }

    function closeModal(id) {
        $('#' + id).removeClass('active');
        $('body').css('overflow', '');
    }

    // Close modal on overlay click
    $(document).on('click', '.modal-overlay', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });

    // Close modal on Escape
    $(document).keydown(function(e) {
        if (e.key === 'Escape') {
            $('.modal-overlay.active').each(function() {
                closeModal(this.id);
            });
        }
    });

    // Popup alert
    function popupAlert(msg) {
        var isError = (msg.toLowerCase().indexOf("error") >= 0);
        if (isError) {
            $("#infoLine").removeClass("alert-success").addClass("alert-error");
        } else {
            $("#infoLine").removeClass("alert-error").addClass("alert-success");
        }
        $("#infoLine").html(msg).show();
        setTimeout(function() {
            $("#infoLine").fadeOut();
        }, 5000);
    }

    function submitEstimatesForm() {
        var task_id = $("#hdnTaskID").val();
        var duration = $("#inputDuration").val();
        if (duration == "") duration = "1 day";
        $("#" + task_id + "Label").html(duration);

        closeModal('estimatesModal');

        $.post("update_task.php", {task_id: task_id, operation: "save_estimate", estimated_hours: duration}, function(xml) {
            popupAlert(xml);
            get_projects();
        });
    }

    function submitStatusForm() {
        var task_id = $("#statusHiddenID").text();
        var completion = $("#inputCompletion").val();
        if (completion == "") completion = "0";

        var task_status_id = $("input[type='radio'][name='radioStatuses']:checked").val();

        closeModal('statusModal');

        $.post("update_task.php", {task_id: task_id, operation: "save_status", completion: completion, task_status_id: task_status_id}, function(xml) {
            popupAlert(xml);
            get_projects();
        });
    }

    function submitDependentForm() {
        var task_id = $("#dependentHiddenID").val();
        var dependent_task = $("#inputDependent").val();

        closeModal('dependentModal');

        $.post("update_task.php", {task_id: task_id, operation: "link_tasks", dependent_task: dependent_task}, function(xml) {
            popupAlert(xml);
            get_projects();
        });
    }

    function saveSettings() {
        var weeks_to_display = $("#inputWeeksToDisplay").val();
        var show_done_tasks = $("#chkShowDoneTasks").is(":checked");
        $.post("update_settings.php", {operation: "save_settings", weeks_to_display: weeks_to_display, show_done_tasks: show_done_tasks}, function(xml) {
            popupAlert(xml);
            get_projects();
            closeModal('settingsModal');
        });
    }

    function submitProjectForm() {
        var project_id = $("#projectHiddenID").val();
        var project_title = $("#inputProjectName").val();
        var project_status_id = $("#selectProjectStatuses").val();
        var responsible_user_id = $("#selectProjectUsers").val();

        closeModal('projectModal');

        $.post("update_project.php", {
            project_id: project_id,
            operation: "save_project",
            responsible_user_id: responsible_user_id,
            project_status_id: project_status_id,
            project_title: project_title
        }, function(xml) {
            popupAlert(xml);
            get_projects();
        });
    }

    var globalHideDoneTasks = <?php echo ($hide_done_tasks ? 1 : 0); ?>;

    $(document).ready(function() {
        $("#lnkSettings").click(function() {
            openModal('settingsModal');
            $.get("get_settings.php", {}, function(xml) {
                $("#settingsModalBody").html(xml);
                $("#inputWeeksToDisplay").click(function() {
                    $("#inputWeeksToDisplay").val("");
                });
                $("#frmSettings").submit(function() {
                    saveSettings();
                    return false;
                });
                $("#inputWeeksToDisplay").focus();
            });
            return false;
        });

        $("#bntSaveSettings").click(function() {
            saveSettings();
        });

        // Estimates modal
        $("#frmEstimate").submit(function() {
            submitEstimatesForm();
            return false;
        });
        $("#bntSaveEstimates").click(function() {
            submitEstimatesForm();
        });

        // Status modal
        $("#frmStatus, #frmCompletion").submit(function() {
            submitStatusForm();
            return false;
        });
        $("#bntSaveStatus").click(function() {
            submitStatusForm();
        });

        // Dependent modal
        $("#btnClearDependent").click(function() {
            $("#inputDependent").val("");
            $("#inputDependent").focus();
            return false;
        });
        $("#bntSaveDependent").click(function() {
            submitDependentForm();
            return false;
        });
        $("#frmDependent").submit(function() {
            submitDependentForm();
            return false;
        });

        // Project modal
        $("#bntSaveProject").click(function() {
            submitProjectForm();
            return false;
        });
        $("#frmProject").submit(function() {
            submitProjectForm();
            return false;
        });
        $("#btnEditProject").click(function() {
            var project_id = $("#projectHiddenID").val();
            project_id = project_id.replace(/\D/g, '');
            window.open('../edit_project.php?project_id=' + project_id, 'wndProject', 'type=fullWindow,fullscreen,scrollbars=yes');
            return false;
        });
        $("#btnCloseProject").click(function() {
            var project_id = $("#projectHiddenID").val();
            project_id = project_id.replace(/\D/g, '');

            closeModal('projectModal');

            $.post("update_project.php", {operation: "close_project", project_id: project_id}, function(xml) {
                popupAlert(xml);
                get_projects();
            });
        });

        $("#addNote").autosize();

        $("#btnSaveNote").click(function() {
            var project_id = $("#projectHiddenID").val();
            var notes = $("#addNote").val();

            $.post("update_project.php", {operation: "add_note", project_id: project_id, note: notes}, function(xml) {
                $("#notesMsg").html(xml).show();
                $("#addNote").val("").autosize();
                setTimeout(function() {
                    $("#notesMsg").fadeOut();
                }, 5000);
                getProjectInfo($("#projectHiddenID").val());
            });
        });

        get_projects();
    });

    function getProjectInfo(project_id) {
        $.post("get_project_info.php", {project_id: project_id}, function(xml) {
            var projectTitle = $("project_title", xml).text();
            var projectStatusID = $("project_status_id", xml).text();
            var resposibleID = $("responsible_user_id", xml).text();
            var statuses = $("all_statuses", xml).text();
            var users = $("users", xml).text();
            var notes = $("notes", xml).text();

            var statusesObj = jQuery.parseJSON(statuses);
            var usersObj = jQuery.parseJSON(users);

            $("#tableNotes").find("tr:gt(1)").remove();
            $('#tableNotes tr:last').after(notes);

            $("#selectProjectStatuses").html(getOptionsFromJSON(statusesObj, projectStatusID));
            $("#selectProjectUsers").html(getOptionsFromJSON(usersObj, resposibleID));
            $("#inputProjectName").val(projectTitle);
        });
    }

    function getUserTasks(user_id) {
        $.post("get_user_tasks.php", {user_id: user_id}, function(xml) {
            $("#userModalBody").html(xml);
            $("#modalUsersTitle").html($("#hdnTasksUserName").val() + " Tasks");
            $("#sortable").sortable({
                deactivate: function(event, ui) {
                    var sortedIDs = $("#sortable").sortable("serialize", {key: "sort"});
                    var user_id = $("#userHiddenID").val();
                    $.post("save_priorities.php", {user_id: user_id, sorted_ids: sortedIDs}, function(xml) {});
                }
            });
            $("#sortable").disableSelection();
        });
    }

    function getOptionsFromJSON(obj, selectedItem) {
        var txt = "";
        $.each(obj, function(key, val) {
            var isChecked = "";
            if (selectedItem == key) isChecked = "selected";
            txt += '<option value="' + key + '" ' + isChecked + '>' + val + '</option>';
        });
        return txt;
    }

    function get_projects() {
        $.post("get_projects.php", {repository: 1}, function(xml) {
            $("#divProjects").html(xml);

            var tdSizes = [];
            var count = 0;
            $("#tableBody").find('tr:first').each(function() {
                $(this).find('td').each(function() {
                    tdSizes[count] = $(this).width();
                    count++;
                });
            });
            count = 0;
            $("#tableHeader").width($("#tableBody").width());
            $("#tableHeader").find('thead th').each(function() {
                var width = parseInt(tdSizes[count]);
                if (width < 25) width = 25;
                $(this).width(width);
                count++;
            });

            // Task row hover
            $(".taskRow").hover(function() {
                var id = $(this).attr("id");
                $("#" + id + "Close").show();
            }, function() {
                var id = $(this).attr("id");
                $("#" + id + "Close").hide();
            });

            // Dependency icon
            $(".dependancyCell").hover(function() {
                $(this).css("cursor", "pointer");
                var id = $(this).attr("id");
                $("#" + id + "Icon").show();
            }, function() {
                $(this).css("cursor", "auto");
                var id = $(this).attr("id");
                $("#" + id + "Icon").hide();
            });

            $("#inputCompletion").focus(function() {
                $(this).val("");
            });

            // Task estimate click
            $(".taskEstimate").click(function() {
                var id = $(this).attr("id");
                $("#estimatesModalTitle").html(id);
                openModal('estimatesModal');

                $.post("get_task_info.php", {task_id: id}, function(xml) {
                    var taskTitle = $("task_title", xml).text();
                    var estimatedHours = $("estimated_hours", xml).text();
                    $("#inputDuration").focus();
                    if (estimatedHours == "1 day?") $("#inputDuration").val("");
                    else $("#inputDuration").val(estimatedHours);
                    $("#estimatesModalTitle").html(taskTitle);
                    $("#hdnTaskID").val(id);
                });
            });

            // Status click
            $(".taskStatus").click(function() {
                var id = $(this).attr("id");
                $("#statusHiddenID").html(id);
                openModal('statusModal');

                $.post("get_task_info.php", {task_id: id}, function(xml) {
                    var taskTitle = $("task_title", xml).text();
                    var completion = $("completion", xml).text();
                    var taskStatusID = $("task_status_id", xml).text();
                    var allStatuses = $("all_statuses", xml).text();
                    var allStatusesObj = jQuery.parseJSON(allStatuses);

                    var txt = "";
                    $.each(allStatusesObj, function(key, val) {
                        var isChecked = "";
                        if (taskStatusID == key) isChecked = "checked";
                        txt += '<label><input type="radio" name="radioStatuses" value="' + key + '" ' + isChecked + '>' + val + '</label>';
                    });
                    $("#statusRadios").html(txt);
                    $("#statusModalTitle").html(taskTitle);
                    $("#inputCompletion").val(completion);
                });
            });

            // Dependency modal
            $(".dependancyCell").click(function() {
                var id = $(this).attr("id");
                $("#dependentHiddenID").val(id);
                openModal('dependentModal');

                $.post("get_task_info.php", {task_id: id, all_tasks: 1}, function(xml) {
                    var taskTitle = $("task_title", xml).text();
                    var allTasks = $("all_tasks", xml).text();
                    var dependent_id = $("dependent_id", xml).text();
                    var allTasksObj = jQuery.parseJSON(allTasks);

                    var dependentTitle = "";
                    $.each(allTasksObj, function(key, val) {
                        if (key == dependent_id) dependentTitle = val;
                    });

                    $("#dependentModalTitle").html(taskTitle);
                    $("#inputDependent").val(dependentTitle);
                    $("#inputDependent").focus();
                });
            });

            // Project modal
            $(".projectCell").click(function() {
                var id = $(this).attr("id");
                $("#projectHiddenID").val(id);
                openModal('projectModal');
                getProjectInfo(id);
            });

            // User modal
            $(".userCell").click(function() {
                var id = $(this).attr("id");
                $("#userHiddenID").val(id);
                openModal('userModal');
                getUserTasks(id);
            });

            // Close task
            $(".lnkClose").click(function() {
                var id = $(this).attr("id");
                $("#" + id + "Row").hide("slow");
                $.post("close_task.php", {task_id: id}, function(xml) {
                    popupAlert(xml);
                    get_projects();
                });
                return false;
            });
        });
    }
    </script>
</body>
</html>
