<?php
/**
 * Modern Header Navigation
 * 
 * Usage:
 *   For root pages:     include("./includes/modern_header.php");
 *   For subdirectories: $root_path = "../"; include("../includes/modern_header.php");
 * 
 * Make sure $user_name is set before including this file.
 * If not set, it will try to get it from session.
 */

// Set default root path if not defined
if (!isset($root_path)) {
    $root_path = "";
}

// Get user name if not already set
if (!isset($user_name) || empty($user_name)) {
    $user_name = GetSessionParam("UserName");
}
?>
<script>
// Apply dark mode immediately to prevent flash
(function(){try{if(localStorage.getItem('sayuDarkMode')==='1')document.documentElement.classList.add('dark-mode');}catch(e){}})();
</script>
<style>
    .site-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #e2e8f0;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 100;
        flex-wrap: nowrap;
    }

    .site-header h1 {
        color: #2d3748;
        font-size: 1.25rem;
        font-weight: 700;
        white-space: nowrap;
        flex-shrink: 0;
        margin: 0;
    }

    .site-header h1 a {
        color: inherit;
        text-decoration: none;
    }

    .site-header h1 a:hover {
        color: #667eea;
    }

    .header-nav {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
    }

    .header-nav a {
        color: #4a5568;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        padding: 6px 10px;
        border-radius: 6px;
        transition: all 0.2s;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
    }

    .header-nav a:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
    }

    .header-nav a svg {
        flex-shrink: 0;
    }

    .nav-dropdown {
        position: relative;
    }

    .nav-dropdown-toggle {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 2px;
        color: #4a5568;
        font-size: 0.8rem;
        font-weight: 500;
        padding: 6px 10px;
        border-radius: 6px;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .nav-dropdown-toggle:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
    }

    .nav-dropdown-toggle::after {
        content: '▾';
        font-size: 0.6rem;
        margin-left: 2px;
    }

    .nav-dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        min-width: 160px;
        padding: 8px 0;
        z-index: 1000;
        margin-top: 4px;
    }

    .nav-dropdown.dropdown-open > .nav-dropdown-menu {
        display: block;
    }

    .nav-dropdown-menu a {
        display: block;
        padding: 8px 16px;
        color: #4a5568;
        text-decoration: none;
        font-size: 0.85rem;
        border-radius: 0;
        cursor: pointer;
    }

    .nav-dropdown-menu a:hover {
        background: #f7fafc;
        color: #2d3748;
    }

    .nav-dropdown-menu .menu-separator {
        height: 1px;
        background: #e2e8f0;
        margin: 4px 0;
    }

    .nav-submenu {
        position: relative;
    }

    .nav-submenu > .nav-submenu-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 16px;
        color: #4a5568;
        font-size: 0.85rem;
        cursor: pointer;
        transition: background 0.1s, color 0.1s;
    }

    .nav-submenu > .nav-submenu-toggle:hover,
    .nav-submenu.submenu-open > .nav-submenu-toggle {
        background: #f7fafc;
        color: #2d3748;
    }

    .nav-submenu > .nav-submenu-toggle::after {
        content: '›';
        font-size: 1.1em;
        color: #a0aec0;
    }

    .nav-submenu-items {
        display: none;
        position: absolute;
        left: calc(100% - 4px);
        top: -4px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        min-width: 170px;
        padding: 8px 0;
        z-index: 1001;
    }

    /* Invisible bridge to prevent gap between parent and submenu */
    .nav-submenu-items::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 0;
        width: 14px;
        height: 100%;
    }

    .nav-submenu.submenu-open > .nav-submenu-items {
        display: block;
    }

    .nav-submenu-items a {
        display: block;
        padding: 8px 16px;
        color: #4a5568;
        text-decoration: none;
        font-size: 0.85rem;
        white-space: nowrap;
        cursor: pointer;
    }

    .nav-submenu-items a:hover {
        background: #f7fafc;
        color: #2d3748;
    }

    .header-user-info {
        color: #718096;
        font-size: 0.8rem;
        white-space: nowrap;
        padding: 6px 8px;
    }

    @media (max-width: 1400px) {
        .header-nav a span.nav-text,
        .nav-dropdown-toggle span.nav-text {
            display: none;
        }
        .header-nav a svg,
        .nav-dropdown-toggle svg {
            margin-right: 0 !important;
        }
    }

    @media (max-width: 1200px) {
        .site-header {
            padding: 10px 16px;
        }
        .site-header h1 {
            font-size: 1.1rem;
        }
        .header-nav {
            gap: 2px;
        }
        .header-nav a,
        .nav-dropdown-toggle {
            padding: 6px 8px;
        }
        .header-user-info {
            display: none;
        }
    }

    /* Dark mode toggle button */
    .dark-mode-toggle {
        display: flex; align-items: center; justify-content: center;
        width: 30px; height: 30px; border: none; background: transparent;
        border-radius: 6px; cursor: pointer; color: #718096;
        transition: all 0.2s; padding: 0; flex-shrink: 0;
    }
    .dark-mode-toggle:hover { background: rgba(102,126,234,0.1); color: #667eea; }
    .dark-mode-toggle .icon-moon { display: none; }
    .dark-mode-toggle .icon-sun { display: block; }
    html.dark-mode .dark-mode-toggle .icon-moon { display: block; }
    html.dark-mode .dark-mode-toggle .icon-sun { display: none; }

    /* ==================== DARK MODE: Header ==================== */
    html.dark-mode .site-header {
        background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
        border-bottom-color: #4a5568;
    }
    html.dark-mode .site-header h1 { color: #e2e8f0; }
    html.dark-mode .site-header h1 a { color: #e2e8f0; }
    html.dark-mode .site-header h1 a:hover { color: #90cdf4; }
    html.dark-mode .header-nav a { color: #cbd5e0; }
    html.dark-mode .header-nav a:hover { background: rgba(144,205,244,0.1); color: #90cdf4; }
    html.dark-mode .nav-dropdown-toggle { color: #cbd5e0; }
    html.dark-mode .nav-dropdown-toggle:hover { background: rgba(144,205,244,0.1); color: #90cdf4; }
    html.dark-mode .nav-dropdown-menu { background: #161b22; box-shadow: 0 4px 20px rgba(0,0,0,0.6); border: 1px solid #2d333b; }
    html.dark-mode .nav-dropdown-menu a { color: #cbd5e0; }
    html.dark-mode .nav-dropdown-menu a:hover { background: #1c2333; color: #fff; }
    html.dark-mode .nav-dropdown-menu .menu-separator { background: #2d333b; }
    html.dark-mode .nav-submenu > .nav-submenu-toggle { color: #cbd5e0; }
    html.dark-mode .nav-submenu > .nav-submenu-toggle:hover,
    html.dark-mode .nav-submenu.submenu-open > .nav-submenu-toggle { background: #1c2333; color: #fff; }
    html.dark-mode .nav-submenu-items { background: #161b22; box-shadow: 0 4px 20px rgba(0,0,0,0.6); border: 1px solid #2d333b; }
    html.dark-mode .nav-submenu-items a { color: #cbd5e0; }
    html.dark-mode .nav-submenu-items a:hover { background: #1c2333; color: #fff; }
    html.dark-mode .header-user-info { color: #8b949e; }
    html.dark-mode .dark-mode-toggle { color: #ecc94b; }
    html.dark-mode .dark-mode-toggle:hover { background: rgba(236,201,75,0.15); color: #f6e05e; }

    /* ==================== DARK MODE: Global page elements ==================== */
    html.dark-mode body { background: #1b2838 !important; color: #e2e8f0 !important; }

    /* Container / cards */
    html.dark-mode .card,
    html.dark-mode .info-card { background: #161b22; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
    html.dark-mode .card-header { background: linear-gradient(135deg, #1c2333 0%, #161b22 100%); }
    html.dark-mode .card-header.light { background: #161b22; border-bottom: 1px solid #2d333b; }
    html.dark-mode .card-body { color: #e2e8f0; }

    /* Tables */
    html.dark-mode .data-table th,
    html.dark-mode .g-table th { background: #161b22; color: #a0aec0; border-bottom-color: #2d333b; }
    html.dark-mode .data-table td,
    html.dark-mode .g-table td { border-bottom-color: #2d333b; color: #e2e8f0; }
    html.dark-mode .data-table tr:hover td,
    html.dark-mode .g-table tr:hover td { background: #1c2333; }
    html.dark-mode .data-table a,
    html.dark-mode .g-table a { color: #90cdf4; }
    html.dark-mode .scroll-table { background: #161b22; }

    /* Forms */
    html.dark-mode input[type="text"],
    html.dark-mode input[type="number"],
    html.dark-mode input[type="email"],
    html.dark-mode input[type="password"],
    html.dark-mode input[type="date"],
    html.dark-mode input[type="search"],
    html.dark-mode input[type="tel"],
    html.dark-mode textarea,
    html.dark-mode select { background: #1c2333 !important; color: #e2e8f0 !important; border-color: #2d333b !important; }
    html.dark-mode input::placeholder,
    html.dark-mode textarea::placeholder { color: #a0aec0 !important; }
    html.dark-mode input:focus,
    html.dark-mode textarea:focus,
    html.dark-mode select:focus { border-color: #667eea !important; }

    /* Buttons */
    html.dark-mode .btn-secondary { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
    html.dark-mode .btn-secondary:hover { background: #2d333b; }

    /* Kanban board */
    html.dark-mode .kanban-board { background: transparent; }
    html.dark-mode .kanban-column,
    html.dark-mode .kanban-col { background: #161b22; }
    html.dark-mode .kanban-card,
    html.dark-mode .k-card { background: #1c2333; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
    html.dark-mode .kanban-card:hover,
    html.dark-mode .k-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.6); }
    html.dark-mode .kanban-card-title,
    html.dark-mode .kanban-card-title a { color: #e2e8f0; }
    html.dark-mode .kanban-card-title a:hover { color: #90cdf4; }
    html.dark-mode .kanban-card-meta { color: #a0aec0; }
    html.dark-mode .kanban-card-footer { border-top-color: #718096; }
    html.dark-mode .kanban-card-project { color: #a0aec0; }
    html.dark-mode .kanban-column-count { background: rgba(0,0,0,0.3); }
    html.dark-mode .kanban-empty { color: #718096; }

    /* Dark mode kanban column header overrides */
    html.dark-mode .kanban-col-new .kanban-column-header { background: #1c2333; }
    html.dark-mode .kanban-col-new .kanban-column-title { color: #e2e8f0; }
    html.dark-mode .kanban-col-progress .kanban-column-header { background: #1a3a2a; }
    html.dark-mode .kanban-col-progress .kanban-column-title { color: #c6f6d5; }
    html.dark-mode .kanban-col-hold .kanban-column-header { background: #3a2a0a; }
    html.dark-mode .kanban-col-hold .kanban-column-title { color: #feebc8; }
    html.dark-mode .kanban-col-review .kanban-column-header { background: #2d1f5e; }
    html.dark-mode .kanban-col-review .kanban-column-title { color: #e9d8fd; }
    html.dark-mode .kanban-col-done .kanban-column-header { background: #172a45; }
    html.dark-mode .kanban-col-done .kanban-column-title { color: #bee3f8; }

    /* Links */
    html.dark-mode a { color: #90cdf4; }
    html.dark-mode a:hover { color: #63b3ed; }

    /* Misc elements */
    html.dark-mode .page-title,
    html.dark-mode h1, html.dark-mode h2, html.dark-mode h3 { color: #e2e8f0; }
    html.dark-mode .page-stats { color: #a0aec0; }
    html.dark-mode .back-btn { color: rgba(255,255,255,0.7); }
    html.dark-mode .back-btn:hover { color: #fff; background: rgba(255,255,255,0.1); }
    html.dark-mode strong { color: #e2e8f0; }

    /* Context menu */
    html.dark-mode .context-menu { background: #161b22; border-color: #2d333b; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }
    html.dark-mode .context-menu-item { color: #cbd5e0; }
    html.dark-mode .context-menu-item:hover { background: #1c2333; color: #fff; }
    html.dark-mode .context-menu-separator { background: #2d333b; }
    html.dark-mode .context-menu-submenu-items { background: #161b22; border-color: #2d333b; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }

    /* Confirm / modal overlays */
    html.dark-mode .confirm-box,
    html.dark-mode .modal-content { background: #161b22; color: #e2e8f0; }
    html.dark-mode .confirm-title { color: #e2e8f0; }
    html.dark-mode .confirm-msg { color: #a0aec0; }
    html.dark-mode .confirm-cancel { background: #1c2333; color: #e2e8f0; }
    html.dark-mode .confirm-cancel:hover { background: #2d333b; }

    /* Flash messages */
    html.dark-mode .flash-message { box-shadow: 0 4px 20px rgba(0,0,0,0.3); }

    /* Tabs */
    html.dark-mode .view-tabs,
    html.dark-mode .dash-tabs { background: #161b22; border-bottom-color: #2d333b; }
    html.dark-mode .view-tab { color: #a0aec0; }
    html.dark-mode .view-tab:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }
    html.dark-mode .view-tab.active { background: #1c2333; color: #90cdf4; box-shadow: none; }
    html.dark-mode .dash-tab { color: #a0aec0; }
    html.dark-mode .dash-tab:hover { color: #e2e8f0; }
    html.dark-mode .dash-tab.active { color: #90cdf4; border-bottom-color: #90cdf4; }
    html.dark-mode .dash-tab .tab-count { background: #1c2333; color: #a0aec0; }
    html.dark-mode .dash-tab.active .tab-count { background: #172a45; color: #90cdf4; }

    /* Status badges - keep original colors but slight adjustments */
    html.dark-mode .status-badge { opacity: 0.9; }

    /* Meta items / info */
    html.dark-mode .meta-item { background: #1c2333; }
    html.dark-mode .meta-label { color: #a0aec0; }
    html.dark-mode .meta-value { color: #e2e8f0; }

    /* Toolbar / search */
    html.dark-mode .toolbar-btn { background: #1c2333; color: #cbd5e0; border-color: #2d333b; }
    html.dark-mode .toolbar-btn:hover { background: #2d333b; color: #fff; }
    html.dark-mode .search-box { background: #1c2333; border-color: #2d333b; }
    html.dark-mode .search-box input { background: transparent !important; color: #e2e8f0 !important; }

    /* Completion bar */
    html.dark-mode .comp-bar { background: #1c2333; }

    /* Priority dot - keep */

    /* Bulk bar - keep gradient */

    /* Pie chart / time view */
    html.dark-mode .time-view-container,
    html.dark-mode .time-dash-container { color: #e2e8f0; }
    html.dark-mode .time-section-title { color: #e2e8f0; }
    html.dark-mode .time-proj-name,
    html.dark-mode .time-proj-name a { color: #e2e8f0; }
    html.dark-mode .time-proj-hours { color: #cbd5e0; }
    html.dark-mode .time-proj-row:hover { background: #1c2333; }
    html.dark-mode .time-proj-bar-wrap { background: #1c2333; }
    html.dark-mode .tp-btn { background: #1c2333; color: #cbd5e0; border-color: #2d333b; }
    html.dark-mode .tp-btn:hover { background: #2d333b; color: #fff; }
    html.dark-mode .tp-btn.active { background: #667eea; color: #fff; border-color: #667eea; }
    html.dark-mode .pie-center-total { color: #e2e8f0; }
    html.dark-mode .pie-center-label { color: #a0aec0; }
    html.dark-mode .pie-legend-item { color: #cbd5e0; }
    html.dark-mode .pie-legend-value { color: #e2e8f0; }

    /* Gantt / timeline */
    html.dark-mode .gantt-container { background: #161b22; }
    html.dark-mode .gantt-header { border-bottom-color: #2d333b; }
    html.dark-mode .gantt-btn { background: #1c2333; color: #cbd5e0; border-color: #2d333b; }
    html.dark-mode .gantt-btn:hover { background: #2d333b; }
    html.dark-mode .gantt-btn.active { background: #667eea; color: #fff; border-color: #667eea; }
    html.dark-mode .gantt-row { border-bottom-color: #2d333b; }
    html.dark-mode .gantt-row:hover { background: #1c2333; }
    html.dark-mode .gantt-label { color: #e2e8f0; border-right-color: #2d333b; }
    html.dark-mode .gantt-label:hover { color: #90cdf4; }
    html.dark-mode .gantt-label small { color: #a0aec0; }
    html.dark-mode .gantt-dates { background: #161b22; border-bottom-color: #2d333b; }
    html.dark-mode .gantt-dates-label { color: #a0aec0; border-right-color: #2d333b; }
    html.dark-mode .gantt-date-marker { color: #4a5568; border-right-color: #2d333b; }
    html.dark-mode .gantt-project-header { background: #1c2333; color: #90cdf4; border-bottom-color: #2d333b; }

    /* Messages */
    html.dark-mode .message-item { border-bottom-color: #2d333b; }
    html.dark-mode .message-author-name { color: #e2e8f0; }
    html.dark-mode .message-date { color: #a0aec0; }
    html.dark-mode .message-content { color: #cbd5e0; }
    html.dark-mode .message-textarea { background: #1c2333 !important; color: #e2e8f0 !important; border-color: #2d333b !important; }
    html.dark-mode .message-composer { background: #161b22; border-color: #2d333b; }
    html.dark-mode .message-dropzone { background: #1c2333; border-color: #2d333b; color: #a0aec0; }

    /* Layout toggle */
    html.dark-mode .layout-toggle { background: rgba(0,0,0,0.3); }
    html.dark-mode .layout-toggle-btn { color: rgba(255,255,255,0.4); }
    html.dark-mode .layout-toggle-btn:hover { color: #fff; background: rgba(255,255,255,0.1); }
    html.dark-mode .layout-toggle-btn.active { background: rgba(255,255,255,0.2); color: #fff; }

    /* Scrollbars for dark mode */
    html.dark-mode ::-webkit-scrollbar { width: 8px; height: 8px; }
    html.dark-mode ::-webkit-scrollbar-track { background: #0d1117; }
    html.dark-mode ::-webkit-scrollbar-thumb { background: #2d333b; border-radius: 4px; }
    html.dark-mode ::-webkit-scrollbar-thumb:hover { background: #444c56; }

    /* Custom select dropdowns */
    html.dark-mode .custom-select-trigger { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
    html.dark-mode .custom-select-dropdown { background: #161b22; border-color: #2d333b; box-shadow: 0 4px 20px rgba(0,0,0,0.6); }
    html.dark-mode .custom-select-option { color: #cbd5e0; }
    html.dark-mode .custom-select-option:hover,
    html.dark-mode .custom-select-option.selected { background: #1c2333; color: #fff; }

    /* Bulk dropdown */
    html.dark-mode .bulk-dropdown { background: #161b22; border-color: #2d333b; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }
    html.dark-mode .bulk-dropdown .bd-item { color: #cbd5e0; }
    html.dark-mode .bulk-dropdown .bd-item:hover,
    html.dark-mode .bulk-dropdown .bd-item.highlighted { background: #1c2333; color: #fff; }

    /* Checkbox styling */
    html.dark-mode input[type="checkbox"] { accent-color: #667eea; }
</style>

<div class="site-header">
    <h1><a href="<?php echo $root_path; ?>index.php">Sayu Monitor</a></h1>
    <div class="header-nav">
        <a href="<?php echo $root_path; ?>search/"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path></svg><span class="nav-text">Search</span></a>
        <a href="<?php echo $root_path; ?>view_vacations.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg><span class="nav-text">Time Off</span></a>
        <a href="<?php echo $root_path; ?>view_clients.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="nav-text">Clients</span></a>
        <a href="<?php echo $root_path; ?>tasks_dashboard.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg><span class="nav-text">Tasks</span></a>
        <a href="<?php echo $root_path; ?>svn/"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg><span class="nav-text">SVN</span></a>
        <a href="<?php echo $root_path; ?>my_stats.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><span class="nav-text">My Stats</span></a>
        <div class="nav-dropdown">
            <span class="nav-dropdown-toggle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg><span class="nav-text">Reports</span></span>
            <div class="nav-dropdown-menu">
                <a href="<?php echo $root_path; ?>summary_report.php">Summary</a>
                <a href="<?php echo $root_path; ?>projects_report.php">Projects</a>
                <a href="<?php echo $root_path; ?>time_report.php">Time</a>
                <a href="<?php echo $root_path; ?>tasks_report.php">Tasks</a>
                <a href="<?php echo $root_path; ?>projects_summary.php">Projects Summary</a>
                <div class="menu-separator"></div>
                <div class="nav-submenu">
                    <span class="nav-submenu-toggle">Historical</span>
                    <div class="nav-submenu-items">
                        <a href="<?php echo $root_path; ?>svn/projects.php">Projects Gantt</a>
                        <a href="<?php echo $root_path; ?>projects_report_small.php">Projects (small)</a>
                        <a href="<?php echo $root_path; ?>quarterly_report.php">Quarterly</a>
                        <a href="<?php echo $root_path; ?>clients_report.php">Clients</a>
                        <a href="<?php echo $root_path; ?>estimates_report.php">Estimates</a>
                        <a href="<?php echo $root_path; ?>bugs_tracking.php">Bugs</a>
                        <a href="<?php echo $root_path; ?>inventory_report.php">Inventory</a>
                        <a href="<?php echo $root_path; ?>responses_report.php">Responses</a>
                        <a href="<?php echo $root_path; ?>productivity_report.php">Productivity</a>
                        <a href="<?php echo $root_path; ?>quotations_report.php">Quotations</a>
                        <a href="<?php echo $root_path; ?>monthly_report.php">Monthly</a>
                        <a href="<?php echo $root_path; ?>cvs_warnings.php">CVS report</a>
                    </div>
                </div>
            </div>
        </div>
        <a href="<?php echo $root_path; ?>users.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg><span class="nav-text">Maintenance</span></a>
        <a href="<?php echo $root_path; ?>user_profile.php?action=self"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><span class="nav-text">Profile</span></a>
        <button type="button" class="dark-mode-toggle" id="darkModeToggle" onclick="toggleDarkMode()" title="Toggle dark mode">
            <svg class="icon-sun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <span class="header-user-info"><?php echo htmlspecialchars($user_name); ?></span>
        <a href="<?php echo $root_path; ?>login.php?action=logout"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 3px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg><span class="nav-text">Logout</span></a>
    </div>
</div>
<script>
(function() {
    // Dropdown with delay - prevents menu from disappearing too quickly
    var CLOSE_DELAY = 300; // ms delay before closing

    document.querySelectorAll('.nav-dropdown').forEach(function(dropdown) {
        var closeTimer = null;

        function openDropdown() {
            clearTimeout(closeTimer);
            dropdown.classList.add('dropdown-open');
        }
        function closeDropdown() {
            closeTimer = setTimeout(function() {
                dropdown.classList.remove('dropdown-open');
                // Also close any open submenus
                dropdown.querySelectorAll('.nav-submenu.submenu-open').forEach(function(s) {
                    s.classList.remove('submenu-open');
                });
            }, CLOSE_DELAY);
        }

        dropdown.addEventListener('mouseenter', openDropdown);
        dropdown.addEventListener('mouseleave', closeDropdown);

        // Keep open when inside the dropdown menu
        var menu = dropdown.querySelector('.nav-dropdown-menu');
        if (menu) {
            menu.addEventListener('mouseenter', openDropdown);
            menu.addEventListener('mouseleave', closeDropdown);
        }
    });

    // Submenu with delay
    document.querySelectorAll('.nav-submenu').forEach(function(submenu) {
        var closeTimer = null;

        function openSubmenu() {
            clearTimeout(closeTimer);
            submenu.classList.add('submenu-open');
        }
        function closeSubmenu() {
            closeTimer = setTimeout(function() {
                submenu.classList.remove('submenu-open');
            }, CLOSE_DELAY);
        }

        submenu.addEventListener('mouseenter', openSubmenu);
        submenu.addEventListener('mouseleave', closeSubmenu);

        var items = submenu.querySelector('.nav-submenu-items');
        if (items) {
            items.addEventListener('mouseenter', openSubmenu);
            items.addEventListener('mouseleave', closeSubmenu);
        }
    });
})();

// Dark mode toggle
function toggleDarkMode() {
    var isDark = document.documentElement.classList.toggle('dark-mode');
    try { localStorage.setItem('sayuDarkMode', isDark ? '1' : '0'); } catch(e) {}
}
</script>
