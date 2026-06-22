<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

// Getting available repositories list
$repositories = "";
$path = "https://web1.sayu.co.uk/svn/";
$command = "index.php?action=show&username=" . $svn_login . "&password=" . $svn_password;
$res = get_page($path . $command);
$monitor_svn_repository = GetParam("repository");
if (!$monitor_svn_repository) {
    if (isset($_COOKIE["monitor_svn_repository"])) {
        $monitor_svn_repository = $_COOKIE["monitor_svn_repository"];
    }
}

$repositories_typehead = "";
if (strpos($res, '+OK Repositories list') !== false) {
    $lines = explode("+OK Repositories list: ", $res);
    if (sizeof($lines) > 1) {
        $repositories_list = explode("\n", $lines[1]);
        foreach ($repositories_list as $repository) {
            if (strlen($repository)) {
                if (strlen($repositories_typehead)) $repositories_typehead .= ",";
                $repositories_typehead .= '"' . trim($repository) . '"';
            }
        }
    } else {
        die("No repositories available");
    }
} else {
    die("ERROR: Can't get a repository list: " . $res);
}

$user_name = GetSessionParam("UserName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVN Updater - Sayu Monitor</title>
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
            max-width: min(1200px, 96vw);
            margin: 0 auto;
            padding: 30px 24px;
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

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            overflow: visible;
        }

        .card-header {
            padding: 16px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #2d3748;
        }

        .card-body {
            padding: 24px;
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

        .repository-path {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 8px;
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .repository-path strong {
            color: #4a5568;
        }

        .copy-path-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            color: #718096;
            cursor: pointer;
            transition: color 0.15s, background 0.15s, border-color 0.15s;
            flex-shrink: 0;
        }
        .copy-path-btn:hover {
            background: #f1f5f9;
            color: #667eea;
            border-color: #cbd5e0;
        }
        .copy-path-btn svg {
            width: 14px;
            height: 14px;
        }
        .copy-path-btn.copied {
            color: #38a169;
            border-color: #38a169;
            background: #f0fff4;
        }

        .recent-repos {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .recent-repos-label {
            font-size: 0.75rem;
            color: #718096;
            margin-right: 4px;
        }
        .recent-repo-pill {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: 6px;
            background: #edf2f7;
            color: #4a5568;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            border: 1px solid transparent;
        }
        .recent-repo-pill:hover {
            background: #e2e8f0;
            color: #667eea;
        }
        html.dark-mode .recent-repos-label { color: #8b949e; }
        html.dark-mode .recent-repo-pill {
            background: #252d3d;
            color: #cbd5e0;
        }
        html.dark-mode .recent-repo-pill:hover {
            background: #1c2333;
            color: #93c5fd;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
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
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .btn-sm {
            padding: 8px 14px;
            font-size: 0.85rem;
        }

        #divFilesList {
            margin-top: 20px;
        }

        #divFilesList table {
            width: 100%;
            border-collapse: collapse;
        }

        #divFilesList th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        #divFilesList .svn-th-sortable {
            cursor: pointer;
            user-select: none;
            padding-right: 22px;
            position: relative;
        }
        #divFilesList .svn-th-sortable:hover {
            color: #2d3748;
            background: #edf2f7;
        }
        #divFilesList .svn-th-sortable::after {
            content: '';
            position: absolute;
            right: 8px;
            top: 50%;
            margin-top: -6px;
            font-size: 0.55rem;
            line-height: 1;
            opacity: 0.35;
            letter-spacing: -2px;
        }
        #divFilesList .svn-th-sortable:not(.svn-th-sorted)::after {
            content: '▲▼';
        }
        #divFilesList .svn-th-sorted--asc::after {
            content: '▲';
            opacity: 0.95;
            color: #667eea;
        }
        #divFilesList .svn-th-sorted--desc::after {
            content: '▼';
            opacity: 0.95;
            color: #667eea;
        }

        #divFilesList td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
        }

        #divFilesList tr:hover td {
            background: #f7fafc;
        }

        #divFilesList .svn-diff-cell {
            white-space: nowrap;
            width: 1%;
            vertical-align: middle;
        }

        #divFilesList .svn-diff-link {
            color: #667eea;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            background: none;
            border: none;
            padding: 0;
            margin: 0;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
        }

        #divFilesList .svn-diff-link:hover {
            text-decoration: underline;
        }

        html.dark-mode #divFilesList .svn-diff-link {
            color: #93c5fd;
        }

        #modalFileDiff {
            z-index: 1120;
        }

        #modalFileDiff .modal-box {
            max-width: min(920px, 96vw);
            width: min(920px, 96vw);
        }

        #modalFileDiff .svn-diff-modal-body {
            padding: 0;
            background: #0d1117;
        }

        #modalFileDiff .svn-diff-scroll {
            max-height: 70vh;
            overflow: auto;
            background: #0d1117;
        }

        #modalFileDiff .svn-diff-inner--plain {
            margin: 0;
            padding: 16px 18px;
            font-size: 0.82rem;
            line-height: 1.55;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            color: #e6edf3;
            white-space: pre-wrap;
            word-break: break-word;
        }

        #modalFileDiff .svn-diff-inner--formatted {
            padding-bottom: 12px;
        }

        #modalFileDiff .svn-diff-legend {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            flex-wrap: wrap;
            gap: 14px 20px;
            padding: 10px 16px;
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.02em;
            background: rgba(22, 27, 34, 0.97);
            border-bottom: 1px solid #30363d;
            color: #8b949e;
        }

        #modalFileDiff .svn-diff-legend span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        #modalFileDiff .svn-diff-legend-add::before {
            content: "";
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            background: rgba(63, 185, 80, 0.5);
            border: 1px solid #3fb950;
        }

        #modalFileDiff .svn-diff-legend-del::before {
            content: "";
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            background: rgba(248, 81, 73, 0.35);
            border: 1px solid #f85149;
        }

        #modalFileDiff .svn-diff-legend-ctx::before {
            content: "";
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            background: #21262d;
            border: 1px solid #484f58;
        }

        #modalFileDiff .svn-diff-lines {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 0.8rem;
            line-height: 1.55;
            tab-size: 4;
        }

        #modalFileDiff .svn-diff-line {
            padding: 3px 16px 3px 14px;
            border-left: 3px solid transparent;
            white-space: pre-wrap;
            word-break: break-word;
            color: #e6edf3;
        }

        #modalFileDiff .svn-diff-index {
            color: #d2a8ff;
            font-weight: 600;
            background: rgba(130, 80, 223, 0.08);
            border-left-color: #a371f7;
            padding-top: 10px;
        }

        #modalFileDiff .svn-diff-separator {
            color: #6e7681;
            letter-spacing: 0.12em;
            font-size: 0.7rem;
            padding: 4px 16px;
        }

        #modalFileDiff .svn-diff-file-old {
            color: #ff7b72;
            background: rgba(248, 81, 73, 0.06);
            border-left-color: #f85149;
            font-weight: 500;
        }

        #modalFileDiff .svn-diff-file-new {
            color: #7ee787;
            background: rgba(63, 185, 80, 0.06);
            border-left-color: #3fb950;
            font-weight: 500;
        }

        #modalFileDiff .svn-diff-hunk {
            color: #79c0ff;
            background: rgba(56, 139, 253, 0.1);
            border-left-color: #58a6ff;
            font-weight: 600;
            font-size: 0.76rem;
        }

        #modalFileDiff .svn-diff-add {
            background: rgba(46, 160, 67, 0.2);
            border-left-color: #3fb950;
            color: #aff5b4;
        }

        #modalFileDiff .svn-diff-del {
            background: rgba(248, 81, 73, 0.14);
            border-left-color: #f85149;
            color: #ffd8d5;
        }

        #modalFileDiff .svn-diff-ctx {
            color: #c9d1d9;
            border-left-color: #484f58;
            background: rgba(110, 118, 129, 0.06);
        }

        #modalFileDiff .svn-diff-meta,
        #modalFileDiff .svn-diff-binary {
            color: #8b949e;
            font-size: 0.76rem;
            font-style: italic;
        }

        html.dark-mode #modalFileDiff .svn-diff-modal-body {
            background: #010409;
        }

        html.dark-mode #modalFileDiff .svn-diff-scroll {
            background: #010409;
        }

        html.dark-mode #modalFileDiff .svn-diff-legend {
            background: rgba(1, 4, 9, 0.97);
            border-bottom-color: #21262d;
        }

        #divFilesList .svn-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1.3;
            white-space: nowrap;
        }

        #divFilesList .svn-status--conflict {
            background: #fed7d7;
            color: #9b2c2c;
        }
        #divFilesList .svn-status--changed {
            background: #bee3f8;
            color: #2c5282;
        }
        #divFilesList .svn-status--not-on-server {
            background: #e9d8fd;
            color: #553c9a;
        }
        #divFilesList .svn-status--modified {
            background: #feebc8;
            color: #9c4221;
        }
        #divFilesList .svn-status--to-add {
            background: #c6f6d5;
            color: #276749;
        }
        #divFilesList .svn-status--to-delete {
            background: #e2e8f0;
            color: #4a5568;
        }
        #divFilesList .svn-status--not-in-svn {
            background: #edf2f7;
            color: #718096;
        }
        #divFilesList .svn-status--missing {
            background: #fbd38d;
            color: #7b341e;
        }
        #divFilesList .svn-status--locked {
            background: #e9d8fd;
            color: #553c9a;
        }
        #divFilesList .svn-status--replaced {
            background: #b2f5ea;
            color: #234e52;
        }
        #divFilesList .svn-status--type-change {
            background: #e2e8f0;
            color: #2d3748;
        }
        #divFilesList .svn-status--default {
            background: #edf2f7;
            color: #4a5568;
        }

        html.dark-mode #divFilesList .svn-status--conflict {
            background: #742a2a;
            color: #fed7d7;
        }
        html.dark-mode #divFilesList .svn-status--changed {
            background: #2c5282;
            color: #bee3f8;
        }
        html.dark-mode #divFilesList .svn-status--not-on-server {
            background: #44337a;
            color: #e9d8fd;
        }
        html.dark-mode #divFilesList .svn-status--modified {
            background: #744210;
            color: #feebc8;
        }
        html.dark-mode #divFilesList .svn-status--to-add {
            background: #22543d;
            color: #c6f6d5;
        }
        html.dark-mode #divFilesList .svn-status--to-delete {
            background: #2d3748;
            color: #cbd5e0;
        }
        html.dark-mode #divFilesList .svn-status--not-in-svn {
            background: #2d3748;
            color: #a0aec0;
        }
        html.dark-mode #divFilesList .svn-status--missing {
            background: #744210;
            color: #faf089;
        }
        html.dark-mode #divFilesList .svn-status--locked {
            background: #44337a;
            color: #e9d8fd;
        }
        html.dark-mode #divFilesList .svn-status--replaced {
            background: #234e52;
            color: #b2f5ea;
        }
        html.dark-mode #divFilesList .svn-status--type-change {
            background: #1a202c;
            color: #cbd5e0;
        }
        html.dark-mode #divFilesList .svn-status--default {
            background: #2d3748;
            color: #e2e8f0;
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
            max-width: 700px;
            width: 100%;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        #modalHistory .modal-box {
            max-width: min(1040px, 98vw);
            width: min(1040px, 98vw);
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

        .modal-body pre {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .svn-modal-message {
            padding: 14px 16px;
            border-radius: 8px;
            background: #ebf8ff;
            color: #2c5282;
            font-size: 0.9rem;
            line-height: 1.45;
        }
        .svn-modal-message--warn {
            background: #fffaf0;
            color: #9c4221;
        }

        .svn-modal-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0;
            padding: 0 8px;
            box-sizing: border-box;
        }

        .modal-body .svn-modal-table {
            width: 100%;
            min-width: 320px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.875rem;
        }

        .modal-body .svn-modal-table.svn-history-table {
            table-layout: fixed;
            min-width: 560px;
        }

        .modal-body .svn-modal-table.svn-history-table th:first-child,
        .modal-body .svn-modal-table.svn-history-table td:first-child:not([colspan]) {
            width: 5.5rem;
            min-width: 4.5rem;
            max-width: 7rem;
            padding-right: 10px;
        }

        .modal-body .svn-modal-table.svn-history-table th:nth-child(2),
        .modal-body .svn-modal-table.svn-history-table td:nth-child(2):not([colspan]) {
            width: 11.5rem;
            min-width: 9.5rem;
            max-width: 14rem;
            padding-right: 12px;
        }

        .modal-body .svn-modal-table.svn-history-table th:nth-child(3),
        .modal-body .svn-modal-table.svn-history-table td:nth-child(3) {
            width: auto;
            min-width: 0;
        }

        .modal-body .svn-modal-table th {
            text-align: left;
            padding: 11px 16px;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #4a5568;
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
        }

        .modal-body .svn-modal-table td {
            padding: 11px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            color: #2d3748;
            word-wrap: break-word;
        }

        .modal-body .svn-modal-table th:first-child,
        .modal-body .svn-modal-table td:first-child:not([colspan]) {
            width: 42%;
            padding-right: 20px;
        }

        .modal-body .svn-modal-table th:last-child,
        .modal-body .svn-modal-table td:last-child:not([colspan]) {
            width: 58%;
        }

        .modal-body .svn-modal-table.svn-history-table thead th {
            text-align: left;
        }

        .modal-body .svn-modal-table .svn-history-date {
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            color: #4a5568;
            font-size: 0.82rem;
        }

        .modal-body .svn-modal-table.svn-history-table .svn-history-rev {
            font-weight: 600;
            font-size: 0.82rem;
            color: #334155;
            white-space: nowrap;
            vertical-align: top;
        }

        .modal-body .svn-modal-table.svn-history-table .svn-history-when {
            vertical-align: top;
        }

        .modal-body .svn-modal-table.svn-history-table .svn-history-comment {
            font-size: 0.82rem;
            line-height: 1.4;
            color: #475569;
            word-wrap: break-word;
            vertical-align: top;
        }

        .modal-body .svn-modal-table.svn-history-table tbody tr:nth-child(even) td {
            background: transparent;
        }

        .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-group td {
            padding: 0;
            vertical-align: middle;
            border-bottom: none;
            background: transparent;
        }

        .modal-body .svn-modal-table.svn-history-table .svn-history-group-cell {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            box-sizing: border-box;
            width: 100%;
            background: #e8edf3;
            border-bottom: 1px solid #cbd5e1;
            padding: 12px 16px;
            color: #1e293b;
        }

        .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-group:first-child .svn-history-group-cell {
            padding-top: 11px;
        }

        .modal-body .svn-modal-table.svn-history-table .svn-history-group-name {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 10rem;
            flex: 1 1 auto;
            text-align: left;
            word-wrap: normal;
            overflow-wrap: normal;
        }

        .modal-body .svn-modal-table.svn-history-table .svn-history-group-meta {
            font-weight: 500;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-item td {
            padding: 9px 12px;
            border-bottom: 1px solid #e2e8f0;
            background: transparent;
            vertical-align: top;
        }

        .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-item td:first-child {
            border-left: 3px solid #cbd5e1;
            padding-left: 13px;
        }

        .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-item:hover td {
            background: #f1f5f9;
        }

        .modal-body .svn-modal-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .modal-body .svn-modal-table tbody tr:hover td {
            background: #edf2f7;
        }

        .modal-footer {
            padding: 16px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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

        .files-list-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 24px;
            color: #718096;
            font-size: 0.9rem;
        }
        .files-list-loading .loading-spinner {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }
        html.dark-mode .files-list-loading { color: #8b949e; }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 16px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #c6f6d5;
            color: #276749;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
        }

        .progress-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 16px;
            display: none;
        }

        .progress-bar.active {
            display: block;
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

        /* Typeahead dropdown */
        .form-group-wrapper {
            position: relative;
        }
        .repo-dropdown {
            position: absolute;
            left: 0;
            right: 0;
            width: 100%;
            min-width: min(100%, 320px);
            margin-top: 4px;
            padding: 8px 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            display: none;
            box-sizing: border-box;
        }

        .repo-dropdown.show {
            display: block;
        }

        .repo-suggestion {
            padding: 10px 16px;
            cursor: pointer;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .repo-suggestion:hover {
            background: #667eea;
            color: white;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .checkbox-group label {
            font-size: 0.9rem;
            color: #4a5568;
        }

        /* Dark mode */
        html.dark-mode .page-header h1 { color: #e2e8f0; }
        html.dark-mode .page-header p { color: #a0aec0; }
        html.dark-mode .card {
            background: #161b22;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .card-header {
            background: #1c2333;
            color: #e2e8f0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .card-body { color: #e2e8f0; }
        html.dark-mode .form-group label { color: #cbd5e0; }
        html.dark-mode .form-group input[type="text"] {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .form-group input[type="text"]:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        html.dark-mode .repository-path { color: #8b949e; }
        html.dark-mode .repository-path strong { color: #cbd5e0; }
        html.dark-mode .copy-path-btn {
            background: #1c2333;
            border-color: #2d333b;
            color: #8b949e;
        }
        html.dark-mode .copy-path-btn:hover {
            background: #252d3d;
            color: #93c5fd;
            border-color: #4a5568;
        }
        html.dark-mode .copy-path-btn.copied {
            color: #68d391;
            border-color: #276749;
            background: #1a2f23;
        }
        html.dark-mode .btn-secondary {
            background: #1c2333;
            color: #e2e8f0;
            border-color: #2d333b;
        }
        html.dark-mode .btn-secondary:hover { background: #2d333b; }
        html.dark-mode #divFilesList th {
            background: #1c2333;
            color: #a0aec0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode #divFilesList .svn-th-sortable:hover {
            color: #e2e8f0;
            background: #252d3d;
        }
        html.dark-mode #divFilesList .svn-th-sorted--asc::after,
        html.dark-mode #divFilesList .svn-th-sorted--desc::after {
            color: #93c5fd;
        }
        html.dark-mode #divFilesList td {
            color: #e2e8f0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode #divFilesList tr:hover td { background: #1c2333; }
        html.dark-mode .modal-box {
            background: #161b22;
            border: 1px solid #2d333b;
        }
        html.dark-mode .modal-body { color: #e2e8f0; }
        html.dark-mode .modal-body pre {
            background: #1c2333;
            color: #e2e8f0;
        }
        html.dark-mode .modal-footer {
            background: #1c2333;
            border-top-color: #2d333b;
        }
        html.dark-mode .svn-modal-message {
            background: #1c3a5e;
            color: #bee3f8;
        }
        html.dark-mode .svn-modal-message--warn {
            background: #744210;
            color: #feebc8;
        }
        html.dark-mode .modal-body .svn-modal-table th {
            background: #1c2333;
            color: #a0aec0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .modal-body .svn-modal-table td {
            color: #e2e8f0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .modal-body .svn-modal-table .svn-history-date {
            color: #a0aec0;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table .svn-history-rev {
            color: #e2e8f0;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table .svn-history-comment {
            color: #94a3b8;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table tbody tr:nth-child(even) td {
            background: transparent;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table .svn-history-group-cell {
            background: #252d3d;
            border-bottom-color: #2d333b;
            color: #e2e8f0;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table .svn-history-group-meta {
            color: #8b949e;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-item td {
            border-bottom-color: #2d333b;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-item td:first-child {
            border-left-color: #4a5568;
        }
        html.dark-mode .modal-body .svn-modal-table.svn-history-table tbody tr.svn-history-item:hover td {
            background: #1c2333;
        }
        html.dark-mode .modal-body .svn-modal-table tbody tr:nth-child(even) td {
            background: #131820;
        }
        html.dark-mode .modal-body .svn-modal-table tbody tr:hover td {
            background: #1c2333;
        }
        html.dark-mode .repo-dropdown {
            background: #161b22;
            border-color: #2d333b;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        html.dark-mode .repo-suggestion { color: #e2e8f0; }
        html.dark-mode .repo-suggestion:hover {
            background: #667eea;
            color: #fff;
        }
        html.dark-mode .alert-success {
            background: rgba(34, 84, 61, 0.5);
            color: #9ae6b4;
        }
        html.dark-mode .alert-error {
            background: rgba(130, 39, 39, 0.5);
            color: #feb2b2;
        }
        html.dark-mode .progress-bar { background: #2d333b; }
        html.dark-mode .loading-spinner {
            border-color: #2d333b;
            border-top-color: #667eea;
        }
        html.dark-mode .checkbox-group label { color: #cbd5e0; }

        .modal-confirm-overlay {
            z-index: 1100;
        }

        .modal-confirm-box {
            max-width: 440px;
            width: 100%;
        }

        .modal-confirm-message {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.55;
            color: #2d3748;
        }

        .modal-confirm-message strong {
            color: #1a202c;
            font-weight: 600;
        }

        .modal-confirm-footer {
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }

        html.dark-mode .modal-confirm-message {
            color: #e2e8f0;
        }

        html.dark-mode .modal-confirm-message strong {
            color: #fff;
        }

    </style>
</head>
<body>
    <?php $root_path = "../"; include("../includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <h1>SVN Updater</h1>
            <p>Update sites from SVN repository</p>
        </div>

        <div class="card">
            <div class="card-header">Repository</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="lstRepositories">Find a Repository</label>
                    <div class="form-group-wrapper">
                        <input type="text" id="lstRepositories" autocomplete="off" value="<?php echo htmlspecialchars($monitor_svn_repository); ?>" placeholder="Start typing repository name...">
                        <div class="repo-dropdown" id="repoDropdown"></div>
                    </div>
                    <div class="recent-repos" id="recentReposWrap" style="display:none;">
                        <span class="recent-repos-label">Recent:</span>
                        <div id="recentRepos"></div>
                    </div>
                    <div class="repository-path" id="divRepositoryPath"></div>
                </div>

                <div id="divFilesList"></div>

                <button class="btn btn-primary btn-block" id="btnUpdate" type="button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 2v6h-6"></path>
                        <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                        <path d="M3 22v-6h6"></path>
                        <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
                    </svg>
                    Update Site Now
                </button>

                <div class="btn-group">
                    <button class="btn btn-secondary btn-sm" id="btnHistory">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        History
                    </button>
                    <button class="btn btn-secondary btn-sm" id="btnLog">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        Error Log
                    </button>
                    <button class="btn btn-secondary btn-sm" id="btnCriticalLog">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        Critical Errors
                    </button>
                    <button class="btn btn-secondary btn-sm" id="btnCron">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        Cron Jobs
                    </button>
                    <button class="btn btn-secondary btn-sm" id="btnDevelopers">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                        </svg>
                        Dev Tools
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SVN update confirmation -->
    <div class="modal-overlay modal-confirm-overlay" id="modalConfirmUpdate" role="dialog" aria-modal="true" aria-labelledby="confirmUpdateTitle">
        <div class="modal-box modal-confirm-box">
            <div class="modal-header">
                <h3 id="confirmUpdateTitle">Update from SVN</h3>
                <button type="button" class="modal-close" id="modalConfirmUpdateClose" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-confirm-message" id="modalConfirmUpdateMessage"></p>
            </div>
            <div class="modal-footer modal-confirm-footer">
                <button type="button" class="btn btn-secondary" id="modalConfirmUpdateCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="modalConfirmUpdateConfirm">Update site</button>
            </div>
        </div>
    </div>

    <!-- Per-file SVN diff -->
    <div class="modal-overlay" id="modalFileDiff" role="dialog" aria-modal="true" aria-labelledby="fileDiffTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="fileDiffTitle">File diff</h3>
                <button type="button" class="modal-close" id="modalFileDiffClose" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body svn-diff-modal-body">
                <div class="svn-diff-scroll">
                    <div id="fileDiffInner" class="svn-diff-inner svn-diff-inner--plain" role="region" aria-label="Diff content"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modalFileDiffDone">Close</button>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal-overlay" id="modalHistory">
        <div class="modal-box">
            <div class="modal-header">
                <h3>History - Last 50 SVN Commits</h3>
                <button class="modal-close" onclick="closeModal('modalHistory')">&times;</button>
            </div>
            <div class="modal-body" id="infoBox">
                <div class="loading-spinner"></div> Loading...
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalHistory')">Close</button>
            </div>
        </div>
    </div>

    <!-- Error Log Modal -->
    <div class="modal-overlay" id="modalLogs">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Last 50 Errors from error.log</h3>
                <button class="modal-close" onclick="closeModal('modalLogs')">&times;</button>
            </div>
            <div class="modal-body" id="infoLogs">
                <div class="loading-spinner"></div> Loading...
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalLogs')">Close</button>
            </div>
        </div>
    </div>

    <!-- Critical Log Modal -->
    <div class="modal-overlay" id="modalCriticalLogs">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Critical Errors in error.log</h3>
                <button class="modal-close" onclick="closeModal('modalCriticalLogs')">&times;</button>
            </div>
            <div class="modal-body" id="infoCriticalLogs">
                <div class="loading-spinner"></div> Loading...
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalCriticalLogs')">Close</button>
            </div>
        </div>
    </div>

    <!-- Cron Jobs Modal -->
    <div class="modal-overlay" id="modalCron">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Cron Jobs for this Repository</h3>
                <button class="modal-close" onclick="closeModal('modalCron')">&times;</button>
            </div>
            <div class="modal-body" id="infoCron">
                <div class="loading-spinner"></div> Loading...
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalCron')">Close</button>
            </div>
        </div>
    </div>

    <!-- Developers Modal -->
    <div class="modal-overlay" id="modalDevelopers">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Developer Tools</h3>
                <button class="modal-close" onclick="closeModal('modalDevelopers')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 16px; color: #4a5568;">
                    Automatically download recent database and images from Sayu Hosting servers to Sayu Development server.
                </p>
                <p style="margin-bottom: 20px; font-size: 0.85rem; color: #718096;">
                    Note: Before download, you must make SVN checkout to Development server.
                </p>

                <form id="frmDownload">
                    <div class="checkbox-group">
                        <input type="checkbox" id="chkDownloadDB" checked>
                        <label for="chkDownloadDB">Download Project Database (<span id="spanDBSize">calculating...</span>)</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="chkDownloadImages" checked>
                        <label for="chkDownloadImages">Download Images Folder (<span id="spanImagesSize">calculating...</span>)</label>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnStartDownload" style="margin-top: 16px;">
                        Start Download
                    </button>
                </form>

                <div class="progress-bar" id="divProgress">
                    <div class="progress-fill"></div>
                </div>

                <div class="alert" id="divAlert">
                    <div id="divDownloadInfo"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalDevelopers')">Close</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function svnDiffEscapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function svnDiffLooksLikeUnified(text) {
        return /(^|\n)--- |\n\+\+\+ |(^|\n)@@ /.test(String(text));
    }

    function svnFormatDiffLines(text) {
        var lines = String(text).split(/\r?\n/);
        var parts = [];
        parts.push('<div class="svn-diff-legend">');
        parts.push('<span class="svn-diff-legend-add">Lines added</span>');
        parts.push('<span class="svn-diff-legend-del">Lines removed</span>');
        parts.push('<span class="svn-diff-legend-ctx">Unchanged context</span>');
        parts.push('</div><div class="svn-diff-lines">');
        var i, line, c;
        for (i = 0; i < lines.length; i++) {
            line = lines[i];
            c = 'svn-diff-line';
            if (/^Index: /.test(line)) {
                c += ' svn-diff-index';
            } else if (/^={7,}$/.test(line)) {
                c += ' svn-diff-separator';
            } else if (/^--- /.test(line)) {
                c += ' svn-diff-file-old';
            } else if (/^\+\+\+ /.test(line)) {
                c += ' svn-diff-file-new';
            } else if (/^@@/.test(line)) {
                c += ' svn-diff-hunk';
            } else if (/^\\ No newline/.test(line) || /^\\/.test(line)) {
                c += ' svn-diff-meta';
            } else if (/^Binary files /.test(line)) {
                c += ' svn-diff-binary';
            } else if (line.length && line.charAt(0) === '+') {
                c += ' svn-diff-add';
            } else if (line.length && line.charAt(0) === '-') {
                c += ' svn-diff-del';
            } else if (line.length && line.charAt(0) === ' ') {
                c += ' svn-diff-ctx';
            } else {
                c += ' svn-diff-meta';
            }
            parts.push('<div class="' + c + '">' + svnDiffEscapeHtml(line) + '</div>');
        }
        parts.push('</div>');
        return parts.join('');
    }

    function setFileDiffInner(kind, payload) {
        var $el = $('#fileDiffInner');
        if (kind === 'diff' && svnDiffLooksLikeUnified(payload)) {
            $el.removeClass('svn-diff-inner--plain').addClass('svn-diff-inner--formatted');
            $el.html(svnFormatDiffLines(payload));
            return;
        }
        $el.removeClass('svn-diff-inner--formatted').addClass('svn-diff-inner--plain');
        $el.text(payload);
    }

    var defaultRepository = "<?php echo $monitor_svn_repository; ?>";
    var repositories = [<?php echo $repositories_typehead; ?>];
    var RECENT_REPOS_KEY = "svn_recent_repos";
    var MAX_RECENT = 10;
    var copyPathBtnIconCopy = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    var copyPathBtnIconCopied = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';

    function setCopyPathButtonState($btn, copied) {
        if (copied) {
            $btn.html(copyPathBtnIconCopied).attr({ title: "Copied!", "aria-label": "Copied" }).addClass("copied");
        } else {
            $btn.html(copyPathBtnIconCopy).attr({ title: "Copy to clipboard", "aria-label": "Copy to clipboard" }).removeClass("copied");
        }
    }

    function flashCopyPathButton($btn) {
        clearTimeout($btn.data("copyPathResetT"));
        setCopyPathButtonState($btn, true);
        $btn.data("copyPathResetT", setTimeout(function() {
            setCopyPathButtonState($btn, false);
        }, 1500));
    }

    function getRecentRepos() {
        try {
            var raw = localStorage.getItem(RECENT_REPOS_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveRecentRepo(repo) {
        repo = (repo || "").trim();
        if (!repo) return;
        var list = getRecentRepos();
        list = list.filter(function(r) { return r !== repo; });
        list.unshift(repo);
        list = list.slice(0, MAX_RECENT);
        try {
            localStorage.setItem(RECENT_REPOS_KEY, JSON.stringify(list));
        } catch (e) {}
        renderRecentRepos();
    }

    function renderRecentRepos() {
        var list = getRecentRepos();
        var wrap = $("#recentReposWrap");
        var container = $("#recentRepos");
        if (list.length === 0) {
            wrap.hide();
            return;
        }
        container.empty();
        list.forEach(function(repo) {
            $('<button type="button" class="recent-repo-pill"></button>').text(repo).data("repo", repo).appendTo(container);
        });
        wrap.show();
    }

    $(document).ready(function() {
        renderRecentRepos();

        $(document).on('click', '.recent-repo-pill', function() {
            var repo = $(this).data("repo");
            if (repo) {
                $('#lstRepositories').val(repo);
                $('#repoDropdown').removeClass('show');
                get_recent_files(repo);
            }
        });
        // Typeahead for repositories
        $('#lstRepositories').on('input', function() {
            var val = $(this).val().toLowerCase();
            var dropdown = $('#repoDropdown');
            
            if (val.length > 0) {
                var matches = repositories.filter(function(r) {
                    return r.toLowerCase().indexOf(val) !== -1;
                }).slice(0, 10);
                
                if (matches.length > 0) {
                    dropdown.html('');
                    matches.forEach(function(match) {
                        dropdown.append('<div class="repo-suggestion">' + match + '</div>');
                    });
                    dropdown.addClass('show');
                } else {
                    dropdown.removeClass('show');
                }
            } else {
                dropdown.removeClass('show');
            }
        });

        $(document).on('click', '.repo-suggestion', function() {
            var selected = $(this).text();
            $('#lstRepositories').val(selected);
            $('#repoDropdown').removeClass('show');
            get_recent_files(selected);
        });

        $(document).on('click', '.copy-path-btn', function() {
            var $btn = $(this);
            var path = "svn://web1.sayu.co.uk/mnt/drive2/webclients/" + $("#lstRepositories").val();
            var done = function() { flashCopyPathButton($btn); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(path).then(done).catch(function() {});
            } else {
                var ta = document.createElement("textarea");
                ta.value = path;
                document.body.appendChild(ta);
                ta.select();
                try {
                    if (document.execCommand("copy")) {
                        done();
                    }
                } finally {
                    document.body.removeChild(ta);
                }
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#lstRepositories, #repoDropdown').length) {
                $('#repoDropdown').removeClass('show');
            }
        });

        $('#lstRepositories').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#repoDropdown').removeClass('show');
                get_recent_files($(this).val());
            }
        });

        $("#lstRepositories").focus(function() {
            var repository = $(this).val();
            if (repository == defaultRepository) {
                $(this).select();
            }
        });

        // Button handlers
        $("#btnHistory").click(function() {
            openModal('modalHistory');
            var repository = $("#lstRepositories").val();
            $("#infoBox").html('<div class="loading-spinner"></div> Loading...');
            $.post("history.php", {repository: repository}, function(xml) {
                $("#infoBox").html(xml);
            });
        });

        $("#btnLog").click(function() {
            openModal('modalLogs');
            var repository = $("#lstRepositories").val();
            $("#infoLogs").html('<div class="loading-spinner"></div> Loading...');
            $.post("get_logs.php", {repository: repository, last_50_errors: 1}, function(xml) {
                $("#infoLogs").html('<pre>' + xml + '</pre>');
            });
        });

        $("#btnCriticalLog").click(function() {
            openModal('modalCriticalLogs');
            var repository = $("#lstRepositories").val();
            $("#infoCriticalLogs").html('<div class="loading-spinner"></div> Loading...');
            $.post("get_logs.php", {repository: repository}, function(xml) {
                $("#infoCriticalLogs").html('<pre>' + xml + '</pre>');
            });
        });

        $("#btnCron").click(function() {
            openModal('modalCron');
            var repository = $("#lstRepositories").val();
            $("#infoCron").html('<div class="loading-spinner"></div> Loading...');
            $.post("get_cron.php", {repository: repository}, function(xml) {
                $("#infoCron").html('<pre>' + xml + '</pre>');
            });
        });

        $("#btnDevelopers").click(function() {
            openModal('modalDevelopers');
            $("#divAlert").removeClass('show');
            var repository = $("#lstRepositories").val();
            $.post("hosting_get_sizes.php", {project: repository}, function(xml) {
                try {
                    var res = JSON.parse(xml);
                    $("#spanDBSize").html(res.db_size || 'N/A');
                    $("#spanImagesSize").html(res.images_size || 'N/A');
                } catch(e) {
                    $("#spanDBSize").html('N/A');
                    $("#spanImagesSize").html('N/A');
                }
            });
        });

        $("#frmDownload").submit(function() {
            var url = "hosting_download.php";
            var repository = $("#lstRepositories").val();
            var is_db = $('#chkDownloadDB').is(':checked');
            var is_images = $('#chkDownloadImages').is(':checked');
            
            $("#divAlert").removeClass('show alert-success alert-error');
            $("#btnStartDownload").prop('disabled', true);
            $("#divProgress").addClass('active');
            
            $.get(url, {project: repository, is_db: is_db, is_images: is_images}, function(xml) {
                if (xml.substring(0, 4) == "-ERR") {
                    $("#divAlert").addClass("alert-error");
                } else {
                    $("#divAlert").addClass("alert-success");
                }
                
                $("#divDownloadInfo").html(xml);
                $("#divProgress").removeClass('active');
                $("#divAlert").addClass('show');
                $("#btnStartDownload").prop('disabled', false);
            });
            return false;
        });

        $("#btnUpdate").click(function() {
            openConfirmUpdateModal();
        });

        $("#modalConfirmUpdateConfirm").click(function() {
            var repository = $("#lstRepositories").val();
            if (!repository || !repository.trim()) {
                closeConfirmUpdateModal();
                return;
            }
            repository = repository.trim();
            closeConfirmUpdateModal();
            runSvnUpdate(repository);
        });

        $("#modalConfirmUpdateCancel, #modalConfirmUpdateClose").click(function() {
            closeConfirmUpdateModal();
        });

        $(document).on('click', '.svn-diff-link', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var repo = ($btn.attr('data-repo') || '').trim() || ($('#lstRepositories').val() || '').trim();
            var file = ($btn.attr('data-file') || '').trim();
            if (!file) {
                var p = ($btn.attr('data-path') || '').trim();
                var n = ($btn.attr('data-name') || '').trim();
                if (n) {
                    file = p ? (p.replace(/\/+$/, '') + '/' + n) : n;
                }
            }
            var title = file.length > 72 ? file.slice(0, 69) + '…' : (file || 'File diff');
            $('#fileDiffTitle').text('Diff — ' + title);
            setFileDiffInner('plain', 'Loading…');
            openModal('modalFileDiff');
            if (!repo) {
                setFileDiffInner('plain', 'No repository selected. Choose a repository above, then try again.');
                return;
            }
            if (!file) {
                setFileDiffInner('plain', 'Could not determine file path for this row.');
                return;
            }
            $.post('get_file_diff.php', { repository: repo, file: String(file) }, function(data) {
                if (data && data.ok) {
                    var body = data.diff || '(No diff output.)';
                    setFileDiffInner(svnDiffLooksLikeUnified(body) ? 'diff' : 'plain', body);
                } else {
                    setFileDiffInner('plain', (data && data.error) ? data.error : 'Could not load diff.');
                }
            }, 'json').fail(function(xhr) {
                var msg = 'Request failed.';
                if (xhr.responseText) {
                    try {
                        var j = JSON.parse(xhr.responseText);
                        if (j && j.error) {
                            msg = j.error;
                        }
                    } catch (err) {
                        msg = xhr.responseText.slice(0, 500);
                    }
                }
                setFileDiffInner('plain', msg);
            });
        });

        $('#modalFileDiffClose, #modalFileDiffDone').click(function() {
            closeModal('modalFileDiff');
        });

        // Initial load
        get_recent_files();
        
        // Auto-refresh every 20 seconds
        setInterval(function() {
            get_recent_files();
        }, 20000);
    });

    function openModal(id) {
        $('#' + id).addClass('active');
        $('body').css('overflow', 'hidden');
    }

    function closeModal(id) {
        $('#' + id).removeClass('active');
        $('body').css('overflow', '');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function runSvnUpdate(repository) {
        $("#btnUpdate").prop('disabled', true).html('<div class="loading-spinner"></div> Updating...');
        $.post("update_repository.php", {repository: repository}, function() {
            $("#btnUpdate").prop('disabled', false).html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> Update Site Now');
            get_recent_files();
        });
    }

    function openConfirmUpdateModal() {
        var repository = $("#lstRepositories").val();
        if (!repository || !repository.trim()) {
            return;
        }
        repository = repository.trim();
        $("#modalConfirmUpdateMessage").html(
            'Are you sure you want to update <strong>' + escapeHtml(repository) + '</strong> with a working copy from SVN repository?'
        );
        openModal('modalConfirmUpdate');
        setTimeout(function() {
            $("#modalConfirmUpdateConfirm").trigger('focus');
        }, 0);
    }

    function closeConfirmUpdateModal() {
        closeModal('modalConfirmUpdate');
        $("#btnUpdate").trigger('focus');
    }

    // Close modal on overlay click
    $(document).on('click', '.modal-overlay', function(e) {
        if (e.target === this) {
            if (this.id === 'modalConfirmUpdate') {
                closeConfirmUpdateModal();
            } else {
                closeModal(this.id);
            }
        }
    });

    // Close modal on Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if ($('#modalConfirmUpdate').hasClass('active')) {
                closeConfirmUpdateModal();
                return;
            }
            $('.modal-overlay.active').each(function() {
                closeModal(this.id);
            });
            return;
        }
        if ((e.key === 'Enter' || e.keyCode === 13) && $('#modalConfirmUpdate').hasClass('active')) {
            var $t = $(e.target);
            if ($t.is('textarea')) {
                return;
            }
            if ($t.closest('#modalConfirmUpdate').length && $t.is('button')) {
                return;
            }
            e.preventDefault();
            $('#modalConfirmUpdateConfirm').trigger('click');
        }
    });

    /** Tier for default "sort by status" (lower = earlier in ascending list). */
    var SVN_STATUS_SORT_TIER = {
        conflict: 10,
        missing: 20,
        modified: 30,
        'not-on-server': 40,
        'to-add': 50,
        'to-delete': 60,
        'not-in-svn': 70,
        locked: 80,
        replaced: 90,
        'type-change': 100,
        changed: 110,
        default: 900
    };

    function svnStatusTier(slug) {
        if (!slug) return SVN_STATUS_SORT_TIER.default;
        return SVN_STATUS_SORT_TIER.hasOwnProperty(slug) ? SVN_STATUS_SORT_TIER[slug] : SVN_STATUS_SORT_TIER.default;
    }

    function sortSvnFilesTableRows($table, col, dir) {
        var mult = dir === 'asc' ? 1 : -1;
        var $tbody = $table.find('tbody');
        if (!$tbody.length) return;
        var $rows = $tbody.find('tr').get();

        function tiePath(a, b) {
            var pa = ($(a).data('sortPath') || '').toString();
            var pb = ($(b).data('sortPath') || '').toString();
            var c = pa.localeCompare(pb, undefined, { sensitivity: 'base' });
            if (c !== 0) return c;
            return ($(a).data('sortName') || '').toString().localeCompare(($(b).data('sortName') || '').toString(), undefined, { sensitivity: 'base' });
        }

        $rows.sort(function (a, b) {
            var cmp = 0;
            if (col === 'path') {
                cmp = tiePath(a, b);
            } else if (col === 'name') {
                cmp = ($(a).data('sortName') || '').toString().localeCompare(($(b).data('sortName') || '').toString(), undefined, { sensitivity: 'base' });
                if (cmp === 0) cmp = tiePath(a, b);
            } else if (col === 'status') {
                cmp = svnStatusTier($(a).data('sortStatus')) - svnStatusTier($(b).data('sortStatus'));
                if (cmp === 0) cmp = tiePath(a, b);
            } else if (col === 'revision') {
                cmp = (parseInt($(a).data('sortRev'), 10) || 0) - (parseInt($(b).data('sortRev'), 10) || 0);
                if (cmp === 0) cmp = tiePath(a, b);
            }
            return mult * cmp;
        });

        $.each($rows, function (_, row) {
            $tbody.append(row);
        });
    }

    function updateSvnSortHeaderClasses($table) {
        var col = $table.data('svnSortCol');
        var dir = $table.data('svnSortDir');
        $table.find('thead th[data-sort]').removeClass('svn-th-sorted svn-th-sorted--asc svn-th-sorted--desc');
        if (col && dir) {
            $table.find('thead th[data-sort="' + col + '"]').addClass('svn-th-sorted svn-th-sorted--' + dir);
        }
    }

    function applyDefaultSvnFilesTableSort() {
        var $table = $('#divFilesList .svn-files-table');
        if (!$table.length || !$table.find('tbody tr').length) return;
        $table.data('svnSortCol', 'status').data('svnSortDir', 'asc');
        sortSvnFilesTableRows($table, 'status', 'asc');
        updateSvnSortHeaderClasses($table);
    }

    $(document).on('click', '#divFilesList .svn-files-table thead th[data-sort]', function () {
        var $th = $(this);
        var $table = $th.closest('table');
        var col = $th.data('sort');
        if (!col) return;
        var curCol = $table.data('svnSortCol');
        var dir = 'asc';
        if (curCol === col) {
            dir = $table.data('svnSortDir') === 'asc' ? 'desc' : 'asc';
        }
        $table.data('svnSortCol', col).data('svnSortDir', dir);
        sortSvnFilesTableRows($table, col, dir);
        updateSvnSortHeaderClasses($table);
    });

    function get_recent_files(rep) {
        var repository = rep || $("#lstRepositories").val();
        
        var fullPath = "svn://web1.sayu.co.uk/mnt/drive2/webclients/" + repository;
        var copyBtn = '<button type="button" class="copy-path-btn" title="Copy to clipboard" aria-label="Copy to clipboard">' + copyPathBtnIconCopy + '</button>';
        $("#divRepositoryPath").html("<strong>Repository path:</strong> " + fullPath + " " + copyBtn);

        $("#divFilesList").html('<div class="files-list-loading"><div class="loading-spinner"></div> Loading repository...</div>');
        $("#btnUpdate").prop('disabled', true).html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> Loading...');

        $.post("get_recent_files.php", {repository: repository}, function(xml) {
            $("#divFilesList").html(xml);
            applyDefaultSvnFilesTableSort();
            var filesNumber = $("#hdnFilesNumber").val();
            
            if (filesNumber) {
                var text = filesNumber == 1 ? "Update Site (1 file)" : "Update Site (" + filesNumber + " files)";
                $("#btnUpdate").html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> ' + text);
                $("#btnUpdate").prop('disabled', false);
            } else {
                $("#btnUpdate").html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> Nothing to Update');
                $("#btnUpdate").prop('disabled', true);
            }
            
            defaultRepository = repository;
            saveRecentRepo(repository);
        }).fail(function() {
            $("#divFilesList").html('<div class="files-list-loading" style="color:#e53e3e;">Failed to load repository. Please try again.</div>');
            $("#btnUpdate").prop('disabled', true).html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> Nothing to Update');
        });
    }
    </script>
</body>
</html>
