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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Hanken+Grotesk:wght@400;500;600;700;800;900&display=swap"/>
    <style>
        /* Shared design system — matches the SVN Updater (svn/index.php) */
        :root{
          --bg:#eef1f6; --bg-2:#e6ebf2; --panel:#ffffff; --card:#ffffff; --card-2:#f6f8fb;
          --raise:#f1f4f9; --raise-2:#e7ecf3; --line:rgba(15,23,42,.10); --line-strong:rgba(15,23,42,.16);
          --ink:#1f2733; --ink-soft:#3b4452; --muted:#64748b; --muted-2:#94a3b8;
          --acc-a:#5566d6; --acc-b:#9a6fa6; --acc-solid:#5d6fd6;
          --ok:#2f9e6b; --ok-bg:rgba(47,158,107,.14); --warn:#bf8420; --warn-bg:rgba(191,132,32,.16);
          --err:#cf4f6b; --err-bg:rgba(207,79,107,.14); --info:#2f86b8;
          --fill:rgba(15,23,42,.03); --fill-2:rgba(15,23,42,.05);
          --hover:rgba(15,23,42,.05); --hover-2:rgba(15,23,42,.08); --row-tint:rgba(15,23,42,.025);
          --r-lg:16px; --r-md:11px; --r-sm:8px;
          --shadow:0 18px 50px rgba(20,30,50,.16); --shadow-sm:0 2px 8px rgba(20,30,50,.08);
        }
        html.dark-mode{
          --bg:#16202e; --bg-2:#1a2636; --panel:#16202d; --card:#141d29; --card-2:#1a2433;
          --raise:#1f2a3a; --raise-2:#283649; --line:rgba(255,255,255,.07); --line-strong:rgba(255,255,255,.12);
          --ink:#eef2f7; --ink-soft:#c4ccd8; --muted:#8b97a8; --muted-2:#5f6c7e;
          --acc-a:#4f63cf; --acc-b:#9a6fa6; --acc-solid:#5d6fd6;
          --ok:#44b27c; --ok-bg:rgba(68,178,124,.14); --warn:#e0a93b; --warn-bg:rgba(224,169,59,.14);
          --err:#e2657f; --err-bg:rgba(226,101,127,.14); --info:#58a9d6;
          --fill:rgba(255,255,255,.03); --fill-2:rgba(255,255,255,.06);
          --hover:rgba(255,255,255,.06); --hover-2:rgba(255,255,255,.09); --row-tint:rgba(255,255,255,.03);
          --shadow:0 18px 50px rgba(0,0,0,.40); --shadow-sm:0 2px 8px rgba(0,0,0,.25);
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background:
               radial-gradient(1200px 540px at 78% -8%, rgba(108,92,200,.10), transparent 60%),
               radial-gradient(1000px 520px at 6% 0%, rgba(40,90,150,.10), transparent 55%),
               var(--bg);
            min-height: 100vh;
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 34px 24px 30px;
        }

        .results-container {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 24px 60px;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-family: 'Hanken Grotesk', system-ui, sans-serif;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1.2px;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 16px;
        }

        .search-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-sm);
            padding: 26px;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--muted-2);
            margin-bottom: 9px;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 11px 14px;
            background: var(--raise);
            border: 1px solid var(--line-strong);
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            color: var(--ink);
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: var(--acc-solid);
            box-shadow: 0 0 0 3px rgba(93,111,214,.18);
        }

        .form-group input[type="text"]::placeholder {
            color: var(--muted-2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .btn {
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(100deg, var(--acc-a), var(--acc-b));
            color: #fff;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            filter: brightness(1.07);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: none;
        }

        .progress-bar {
            display: none;
            margin-top: 18px;
        }

        .progress-bar.active {
            display: block;
        }

        .progress-track {
            height: 6px;
            background: var(--raise-2);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(100deg, var(--acc-a), var(--acc-b));
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
            color: var(--muted);
            font-size: 13px;
            margin-top: 10px;
        }

        /* Typeahead styling */
        .twitter-typeahead {
            width: 100%;
        }

        .tt-hint {
            color: var(--muted-2) !important;
        }

        .tt-dropdown-menu {
            width: 100%;
            margin-top: 6px;
            padding: 6px;
            background: var(--card-2);
            border: 1px solid var(--line-strong);
            border-radius: 11px;
            box-shadow: var(--shadow);
            max-height: 320px;
            overflow-y: auto;
        }

        .tt-suggestion {
            padding: 9px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            color: var(--ink-soft);
        }

        .tt-suggestion:hover,
        .tt-suggestion.tt-is-under-cursor {
            background: var(--acc-solid);
            color: #fff;
        }

        .tt-suggestion p {
            margin: 0;
        }

        /* Results table styling */
        #divTasks table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--r-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
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
            background: var(--fill);
            padding: 12px 14px;
            text-align: left;
            font-weight: 800;
            font-size: 11px;
            color: var(--muted-2);
            text-transform: uppercase;
            letter-spacing: 0.7px;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }

        #divTasks td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
            font-size: 13.5px;
            color: var(--ink-soft);
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
            background: var(--hover);
        }

        #divTasks a {
            color: var(--info);
            text-decoration: none;
            font-weight: 600;
        }

        #divTasks a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 16px 12px; }
            .results-container { padding: 0 10px 30px; }
            .search-card { padding: 20px; }
            .page-header h1 { font-size: 28px; }
            #divTasks th, #divTasks td { padding: 6px 8px; font-size: 12px; }
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
