<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$cur_sort_direction = "DESC";
if (isset($_SESSION["session_sort_direction"])) $cur_sort_direction = $_SESSION["session_sort_direction"];

$user_name = GetSessionParam("UserName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Tasks - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            color: #1a202c;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 24px;
        }

        .results-container {
            max-width: 100%;
            padding: 0 20px 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #718096;
            font-size: 0.95rem;
        }

        .search-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input[type="text"]::placeholder {
            color: #a0aec0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .progress-bar {
            display: none;
            margin-top: 20px;
        }

        .progress-bar.active {
            display: block;
        }

        .progress-track {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px;
            animation: progress-animation 1.5s ease-in-out infinite;
        }

        @keyframes progress-animation {
            0% { width: 0%; margin-left: 0; }
            50% { width: 50%; margin-left: 25%; }
            100% { width: 0%; margin-left: 100%; }
        }

        .progress-text {
            text-align: center;
            color: #718096;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        /* Typeahead styling */
        .twitter-typeahead {
            width: 100%;
        }

        .tt-hint {
            color: #a0aec0 !important;
        }

        .tt-dropdown-menu {
            width: 100%;
            margin-top: 4px;
            padding: 8px 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
        }

        .tt-suggestion {
            padding: 10px 16px;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .tt-suggestion:hover,
        .tt-suggestion.tt-is-under-cursor {
            background: #667eea;
            color: white;
        }

        .tt-suggestion p {
            margin: 0;
        }

        /* Results table styling */
        #divTasks table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            table-layout: fixed;
        }

        /* Column widths — fixed cols + two flexible (project & task) */
        #divTasks col.col-id       { width: 50px; }
        #divTasks col.col-project  { width: 12%; }
        #divTasks col.col-task     { width: auto; }
        #divTasks col.col-assigned { width: 136px; }
        #divTasks col.col-status   { width: 66px; }
        #divTasks col.col-pct      { width: 38px; }
        #divTasks col.col-hrs      { width: 82px; }
        #divTasks col.col-created  { width: 78px; }
        #divTasks col.col-closed   { width: 64px; }

        #divTasks th {
            background: #f8f9fa;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 0.73rem;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        #divTasks td {
            padding: 9px 14px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.83rem;
            white-space: nowrap;
        }

        /* Only project & task name truncate with ellipsis */
        #divTasks td.td-project,
        #divTasks td.td-task {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* HRS column: prevent overflow into adjacent columns */
        #divTasks td:nth-child(7) {
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 82px;
        }

        /* CLOSED column: add left padding and center align */
        #divTasks th:nth-child(9),
        #divTasks td:nth-child(9) {
            padding-left: 16px;
            text-align: center;
        }

        #divTasks tbody tr {
            cursor: pointer;
        }

        #divTasks tbody tr:hover td {
            background: #eef2ff;
        }

        #divTasks a {
            color: #667eea;
            text-decoration: none;
        }

        #divTasks a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 16px 12px; }
            .results-container { padding: 0 10px 20px; }
            .search-card { padding: 20px; }
            #divTasks th, #divTasks td { padding: 5px 8px; font-size: 0.75rem; }
            #divTasks col.col-pct, #divTasks col.col-hrs, #divTasks col.col-closed { width: 0; }
            #divTasks th:nth-child(6), #divTasks td:nth-child(6),
            #divTasks th:nth-child(7), #divTasks td:nth-child(7),
            #divTasks th:nth-child(9), #divTasks td:nth-child(9) { display: none; }
        }
        @media (max-width: 480px) {
            #divTasks th:nth-child(1), #divTasks td:nth-child(1),
            #divTasks th:nth-child(4), #divTasks td:nth-child(4),
            #divTasks th:nth-child(8), #divTasks td:nth-child(8) { display: none; }
        }

        /* Dark mode */
        html.dark-mode body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
        }
        html.dark-mode .page-header h1 { color: #e2e8f0; }
        html.dark-mode .page-header p { color: #94a3b8; }
        html.dark-mode .search-card {
            background: #1e293b;
            border: 1px solid #334155;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        html.dark-mode .form-group label { color: #94a3b8; }
        html.dark-mode .form-group input[type="text"] {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }
        html.dark-mode .form-group input[type="text"]:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
        }
        html.dark-mode .form-group input[type="text"]::placeholder { color: #64748b; }
        html.dark-mode .progress-track { background: #334155; }
        html.dark-mode .progress-text { color: #94a3b8; }
        html.dark-mode .tt-dropdown-menu {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        }
        html.dark-mode .tt-suggestion { color: #e2e8f0; }
        html.dark-mode .tt-suggestion:hover,
        html.dark-mode .tt-suggestion.tt-is-under-cursor {
            background: #334155;
            color: #e2e8f0;
        }
        html.dark-mode .tt-hint { color: #64748b !important; }
        html.dark-mode #divTasks table {
            background: #1e293b;
            border: 1px solid #334155;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        html.dark-mode #divTasks th {
            background: #0f172a;
            border-color: #334155;
            color: #94a3b8;
        }
        html.dark-mode #divTasks td {
            border-color: #334155;
            color: #cbd5e1;
        }
        html.dark-mode #divTasks tbody tr:hover td { background: #334155; }
        html.dark-mode #divTasks a { color: #818cf8; }
        html.dark-mode #divTasks a:hover { color: #a5b4fc; }
    </style>
</head>
<body>
    <?php $root_path = "../"; include("../includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <h1>Search Tasks</h1>
            <p>Search across all projects, users, and tasks</p>
        </div>

        <div class="search-card">
            <form id="frmSearch">
                <div class="form-row">
                    <div class="form-group">
                        <label for="inputProject">Project</label>
                        <input type="text" id="inputProject" placeholder="Start typing project name...">
                    </div>
                    <div class="form-group">
                        <label for="inputUser">User</label>
                        <input type="text" id="inputUser" placeholder="Start typing user name...">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputDomain">Domain</label>
                    <input type="text" id="inputDomain" placeholder="Start typing domain...">
                </div>

                <div class="form-group">
                    <label for="inputKeyword">Keyword</label>
                    <input type="text" id="inputKeyword" placeholder="Enter a keyword or leave blank to get all tasks">
                </div>

                <button type="submit" class="btn btn-primary" id="btnSearch">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    Search Tasks
                </button>
            </form>

            <div class="progress-bar" id="progressBar">
                <div class="progress-track">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">Searching...</p>
            </div>
        </div>

    </div>

    <div class="results-container">
        <div id="divTasks"></div>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/typeahead.min.js"></script>
    <script>
    var globalSortOrder = "";
    var globalSortDirection = "<?php echo $cur_sort_direction; ?>";

    // Build hash from current search fields
    function updateHash() {
        var parts = [];
        var p = $("#inputProject").val();
        var u = $("#inputUser").val();
        var d = $("#inputDomain").val();
        var k = $("#inputKeyword").val();
        if (p) parts.push("project:" + encodeURIComponent(p));
        if (u) parts.push("user:" + encodeURIComponent(u));
        if (d) parts.push("domain:" + encodeURIComponent(d));
        if (k) parts.push("q:" + encodeURIComponent(k));
        window.location.hash = parts.length ? "search/" + parts.join("/") : "";
    }

    // Restore search fields from hash
    function restoreFromHash() {
        var hash = window.location.hash.replace(/^#\/?search\/?/, "");
        if (!hash) return false;
        var pairs = hash.split("/");
        for (var i = 0; i < pairs.length; i++) {
            var idx = pairs[i].indexOf(":");
            if (idx === -1) continue;
            var key = pairs[i].substring(0, idx);
            var val = decodeURIComponent(pairs[i].substring(idx + 1));
            if (key === "project") $("#inputProject").val(val);
            else if (key === "user") $("#inputUser").val(val);
            else if (key === "domain") $("#inputDomain").val(val);
            else if (key === "q") $("#inputKeyword").val(val);
        }
        return true;
    }

    $(document).ready(function() {
        $('#inputProject').typeahead({
            name: 'projects',
            prefetch: 'projects_list.php',
            limit: 10
        });

        $('#inputUser').typeahead({
            name: 'users',
            prefetch: 'users_list.php',
            limit: 10
        });

        $('#inputDomain').typeahead({
            name: 'domains',
            prefetch: 'domains_list.php',
            limit: 10
        });

        $("#btnSearch").click(function() {
            get_tasks();
            return false;
        });

        $("#frmSearch").submit(function() {
            get_tasks();
            return false;
        });

        // If URL has a hash, restore fields and auto-search
        if (restoreFromHash()) {
            get_tasks();
        } else {
            $("#inputProject").focus();
        }
    });

    function get_tasks() {
        var searchKeyword = $("#inputKeyword").val();
        var project = $("#inputProject").val();
        var userName = $("#inputUser").val();
        var domain = $("#inputDomain").val();

        // Update URL hash so the search is shareable
        updateHash();

        $("#divTasks").hide();
        $("#progressBar").addClass("active");
        $("#btnSearch").prop("disabled", true);

        $.post("get_tasks.php", {
            sort_direction: globalSortDirection,
            sort_order: globalSortOrder,
            operation: "search",
            user: userName,
            domain: domain,
            search_keyword: searchKeyword,
            project_id: project
        }, function(xml) {
            $("#progressBar").removeClass("active");
            $("#btnSearch").prop("disabled", false);
            $("#divTasks").show();
            $("#divTasks").html(xml);

            // Make rows clickable — open task in new tab
            $("#divTasks tbody tr").click(function(e) {
                // Don't intercept if user clicked an actual link
                if ($(e.target).closest("a").length) return;
                var taskId = $(this).data("task-id");
                if (taskId) window.open("../edit_task.php?task_id=" + taskId, "_blank");
            });
        });
    }
    </script>
</body>
</html>
