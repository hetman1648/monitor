<?php
include("./includes/date_functions.php");
include("./includes/common.php");

$type = GetParam('show_table') != '' ? intval(GetParam('show_table')) : 2;
if ($type == 1) $type = 2;
$tabNames = array(2 => 'Sayu Active Clients', 3 => 'Sayu All Clients');
$currentTab = isset($tabNames[$type]) ? $tabNames[$type] : $tabNames[2];
$user_name = GetSessionParam("UserName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - Control</title>
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

        .clients-container {
            max-width: 1200px;
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

        .header-actions {
            display: flex;
            gap: 10px;
        }

        /* Search and filters bar */
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

        .search-wrapper {
            flex: 1;
            max-width: 450px;
            min-width: 250px;
        }

        .search-input-container {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 1rem;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 10px 40px 10px 38px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .search-input:focus {
            outline: none;
            border-color: #2c5aa0;
            background: white;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }

        .search-input::placeholder {
            color: #a0aec0;
        }

        .search-loading {
            position: absolute;
            right: 36px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            pointer-events: none;
        }

        .search-loading.active {
            display: block;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #e2e8f0;
            border-top-color: #2c5aa0;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
            line-height: 1;
            display: none;
        }

        .search-clear:hover {
            color: #4a5568;
        }

        .search-clear.active {
            display: block;
        }

        .search-hint {
            font-size: 0.75rem;
            color: #a0aec0;
            margin-top: 6px;
        }

        .recent-searches {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .recent-searches-label {
            font-size: 0.75rem;
            color: #718096;
            font-weight: 500;
        }

        .recent-search-item {
            background: #edf2f7;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            border: none;
        }

        .recent-search-item:hover {
            background: #2c5aa0;
            color: white;
            text-decoration: none;
        }

        /* Tabs */
        .tabs-wrapper {
            display: flex;
            gap: 4px;
            margin-left: auto;
        }

        .tab-btn {
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

        .tab-btn:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            text-decoration: none;
            color: #4a5568;
        }

        .tab-btn.active {
            background: #2c5aa0;
            color: white;
            border-color: #2c5aa0;
        }

        .results-count {
            color: #718096;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .results-count strong {
            color: #2d3748;
            font-weight: 600;
        }

        /* Table container */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .clients-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .clients-table thead {
            background: #f7fafc;
        }

        .clients-table th {
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

        .clients-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }

        .clients-table tbody tr {
            transition: background 0.1s;
            cursor: pointer;
        }

        .clients-table tbody tr:hover {
            background: #f7fafc;
        }

        .clients-table tbody tr:last-child td {
            border-bottom: none;
        }

        .clients-table tbody tr:active {
            background: #edf2f7;
        }

        /* Client info cell */
        .client-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .client-name {
            font-weight: 600;
            color: #1a202c;
        }

        .client-company {
            font-size: 0.8rem;
            color: #718096;
        }

        .client-email {
            color: #4a5568;
        }

        .client-sites {
            font-size: 0.8rem;
            max-width: 250px;
        }

        .client-sites a {
            color: #2c5aa0;
            text-decoration: none;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .client-sites a:hover {
            text-decoration: underline;
        }

        .client-tags {
            margin-top: 4px;
        }

        .tag {
            display: inline-block;
            background: #ebf4ff;
            color: #2c5aa0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-right: 4px;
        }

        .client-id {
            font-family: 'SF Mono', Monaco, monospace;
            color: #718096;
            font-size: 0.8rem;
        }

        .client-date {
            font-size: 0.8rem;
            color: #718096;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-inactive {
            background: #fed7d7;
            color: #822727;
        }

        .highlight {
            background: #fef3c7;
            border-radius: 2px;
            padding: 0 2px;
        }

        /* Results info bar */
        .results-info {
            padding: 12px 20px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
            color: #718096;
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
            background: #1a202c;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Dark mode */
        html.dark-mode .clients-container { color: #e2e8f0; }
        html.dark-mode .page-title { color: #e2e8f0; }
        html.dark-mode .page-subtitle { color: #a0aec0; }
        html.dark-mode .filters-bar {
            background: #161b22;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .search-input {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .search-input:focus {
            border-color: #667eea !important;
            background: #1c2333 !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        html.dark-mode .search-input::placeholder { color: #718096 !important; }
        html.dark-mode .search-icon { color: #8b949e; }
        html.dark-mode .search-clear { color: #8b949e; }
        html.dark-mode .search-clear:hover { color: #e2e8f0; }
        html.dark-mode .search-hint { color: #8b949e; }
        html.dark-mode .recent-searches-label { color: #8b949e; }
        html.dark-mode .recent-search-item {
            background: #1c2333;
            color: #cbd5e0;
        }
        html.dark-mode .recent-search-item:hover {
            background: #667eea;
            color: #fff;
        }
        html.dark-mode .tab-btn {
            background: #1c2333;
            border-color: #2d333b;
            color: #cbd5e0;
        }
        html.dark-mode .tab-btn:hover {
            background: #2d333b;
            border-color: #4a5568;
            color: #e2e8f0;
        }
        html.dark-mode .tab-btn.active {
            background: #2c5aa0;
            border-color: #2c5aa0;
            color: #fff;
        }
        html.dark-mode .results-count { color: #8b949e; }
        html.dark-mode .results-count strong { color: #e2e8f0; }
        html.dark-mode .table-container {
            background: #161b22;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .clients-table thead { background: #1c2333; }
        html.dark-mode .clients-table th {
            color: #a0aec0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .clients-table td {
            border-bottom-color: #2d333b;
            color: #e2e8f0;
        }
        html.dark-mode .clients-table tbody tr:hover { background: #1c2333; }
        html.dark-mode .clients-table tbody tr:active { background: #252d3a; }
        html.dark-mode .client-name { color: #e2e8f0; }
        html.dark-mode .client-company { color: #a0aec0; }
        html.dark-mode .client-email { color: #cbd5e0; }
        html.dark-mode .client-sites a { color: #90cdf4; }
        html.dark-mode .client-id { color: #8b949e; }
        html.dark-mode .client-date { color: #8b949e; }
        html.dark-mode .tag {
            background: #1c2333;
            color: #90cdf4;
        }
        html.dark-mode .status-active {
            background: rgba(34, 84, 61, 0.5);
            color: #9ae6b4;
        }
        html.dark-mode .status-inactive {
            background: rgba(130, 39, 39, 0.5);
            color: #feb2b2;
        }
        html.dark-mode .highlight {
            background: rgba(245, 158, 11, 0.3);
        }
        html.dark-mode .results-info {
            background: #1c2333;
            border-bottom-color: #2d333b;
            color: #8b949e;
        }
        html.dark-mode .empty-state { color: #8b949e; }
        html.dark-mode .empty-state .icon { opacity: 0.6; }
        html.dark-mode .empty-state h3 { color: #a0aec0; }
        html.dark-mode .empty-state p { color: #8b949e; }
        html.dark-mode .btn-primary { background: linear-gradient(135deg, #2c5aa0 0%, #1e3a5f 100%); color: #fff; }
        html.dark-mode .btn-secondary {
            background: #1c2333;
            color: #e2e8f0;
            border-color: #2d333b;
        }
        html.dark-mode .btn-secondary:hover {
            background: #2d333b;
            color: #fff;
        }
        html.dark-mode .spinner {
            border-color: #2d333b;
            border-top-color: #667eea;
        }

        @media (max-width: 768px) {
            .clients-table {
                display: block;
                overflow-x: auto;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-wrapper {
                max-width: 100%;
            }
            
            .tabs-wrapper {
                margin-left: 0;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="PageBODY">
    <?php include("./includes/modern_header.php"); ?>

<div class="clients-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Clients</h1>
            <p class="page-subtitle">Search and manage client accounts</p>
        </div>
        <div class="header-actions">
            <a href="view_sites_tags.php" class="btn btn-secondary">Tags Cloud</a>
            <a href="create_client.php" class="btn btn-primary">+ Add Client</a>
        </div>
    </div>
    
    <div class="filters-bar">
        <div class="search-wrapper">
            <div class="search-input-container">
                <span class="search-icon">&#128269;</span>
                <input type="text" class="search-input" id="searchInput" placeholder="Search by name, email, company, site, tag, or ID..." autofocus autocomplete="off">
                <div class="search-loading" id="searchLoading">
                    <div class="spinner"></div>
                </div>
                <button type="button" class="search-clear" id="searchClear" title="Clear search">&times;</button>
            </div>
            <div class="search-hint">Type at least 3 characters to search</div>
            <div class="recent-searches" id="recentSearches" style="display: none;">
                <span class="recent-searches-label">Recent:</span>
            </div>
        </div>
        
        <div class="tabs-wrapper">
            <a href="?show_table=2" class="tab-btn <?php echo $type == 2 ? 'active' : ''; ?>" data-type="2">Sayu Active</a>
            <a href="?show_table=3" class="tab-btn <?php echo $type == 3 ? 'active' : ''; ?>" data-type="3">Sayu All</a>
        </div>

        <span class="results-count" id="resultsInfo">Type to search</span>
    </div>
    
    <div class="table-container">
        <div id="resultsContainer">
            <div class="empty-state">
                <div class="icon">&#128269;</div>
                <h3>Search for clients</h3>
                <p>Enter a name, email, company, website, or tag to find clients</p>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast">Copied to clipboard!</div>

<script>
const currentType = <?php echo $type; ?>;
let searchTimeout = null;
let lastQuery = '';

const searchInput = document.getElementById('searchInput');
const searchLoading = document.getElementById('searchLoading');
const searchClear = document.getElementById('searchClear');
const resultsInfo = document.getElementById('resultsInfo');
const resultsContainer = document.getElementById('resultsContainer');
const recentSearchesContainer = document.getElementById('recentSearches');

// Recent searches functionality
const RECENT_SEARCHES_KEY = 'clientRecentSearches';
const MAX_RECENT_SEARCHES = 5;

function getRecentSearches() {
    try {
        const stored = localStorage.getItem(RECENT_SEARCHES_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        return [];
    }
}

function saveRecentSearch(query) {
    if (!query || query.length < 3) return;
    
    let searches = getRecentSearches();
    searches = searches.filter(s => s.toLowerCase() !== query.toLowerCase());
    searches.unshift(query);
    searches = searches.slice(0, MAX_RECENT_SEARCHES);
    
    try {
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(searches));
    } catch (e) {}
    
    renderRecentSearches();
}

function renderRecentSearches() {
    const searches = getRecentSearches();
    
    if (searches.length === 0) {
        recentSearchesContainer.style.display = 'none';
        return;
    }
    
    recentSearchesContainer.style.display = 'flex';
    recentSearchesContainer.innerHTML = '<span class="recent-searches-label">Recent:</span>' +
        searches.map(s => `<a href="#" class="recent-search-item" data-query="${s.replace(/"/g, '&quot;')}">${escapeHtml(s)}</a>`).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

recentSearchesContainer.addEventListener('click', function(e) {
    if (e.target.classList.contains('recent-search-item')) {
        e.preventDefault();
        const query = e.target.dataset.query;
        searchInput.value = query;
        searchClear.classList.add('active');
        const activeType = document.querySelector('.tab-btn.active').dataset.type;
        searchLoading.classList.add('active');
        resultsInfo.textContent = 'Searching...';
        performSearch(query, parseInt(activeType));
    }
});

function goToClient(clientId) {
    if (lastQuery && lastQuery.length >= 3) {
        saveRecentSearch(lastQuery);
    }
    window.location = 'create_client.php?client_id=' + clientId;
}

renderRecentSearches();

searchClear.addEventListener('click', function() {
    searchInput.value = '';
    searchClear.classList.remove('active');
    lastQuery = '';
    resultsInfo.textContent = 'Type to search';
    resultsContainer.innerHTML = `
        <div class="empty-state">
            <div class="icon">&#128269;</div>
            <h3>Search for clients</h3>
            <p>Enter a name, email, company, website, or tag to find clients</p>
        </div>
    `;
    searchInput.focus();
});

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
}

document.querySelectorAll('.tab-btn').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const type = this.dataset.type;
        
        const url = new URL(window.location);
        url.searchParams.set('show_table', type);
        history.pushState({}, '', url);
        
        document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        if (lastQuery.length >= 3) {
            performSearch(lastQuery, parseInt(type));
        }
    });
});

searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    
    if (this.value.length > 0) {
        searchClear.classList.add('active');
    } else {
        searchClear.classList.remove('active');
    }
    
    clearTimeout(searchTimeout);
    
    if (query.length < 3) {
        resultsInfo.textContent = 'Type at least 3 characters';
        resultsContainer.innerHTML = `
            <div class="empty-state">
                <div class="icon">&#128269;</div>
                <h3>Search for clients</h3>
                <p>Enter a name, email, company, website, or tag to find clients</p>
            </div>
        `;
        return;
    }
    
    searchLoading.classList.add('active');
    resultsInfo.textContent = 'Searching...';
    
    searchTimeout = setTimeout(() => {
        const activeType = document.querySelector('.tab-btn.active').dataset.type;
        performSearch(query, parseInt(activeType));
    }, 300);
});

function performSearch(query, type) {
    lastQuery = query;
    
    fetch(`ajax_responder.php?action=search_clients&q=${encodeURIComponent(query)}&type=${type}&limit=50`)
        .then(r => r.json())
        .then(data => {
            searchLoading.classList.remove('active');
            
            if (data.success) {
                renderResults(data.clients, query, type);
            } else {
                resultsInfo.textContent = data.error || 'Search failed';
            }
        })
        .catch(err => {
            searchLoading.classList.remove('active');
            resultsInfo.textContent = 'Search failed. Please try again.';
        });
}

function highlightText(text, query) {
    if (!text) return '';
    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
    return text.replace(regex, '<span class="highlight">$1</span>');
}

function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function renderResults(clients, query, type) {
    if (clients.length === 0) {
        resultsInfo.innerHTML = 'No clients found';
        resultsContainer.innerHTML = `
            <div class="empty-state">
                <div class="icon">&#128683;</div>
                <h3>No results found</h3>
                <p>No clients found matching "${escapeHtml(query)}"</p>
            </div>
        `;
        return;
    }
    
    resultsInfo.innerHTML = `Showing <strong>${clients.length}</strong> client${clients.length !== 1 ? 's' : ''}`;
    
    const showActive = (type === 3);
    
    let html = `
        <table class="clients-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Sites</th>
                    ${showActive ? '<th>Status</th>' : ''}
                    <th>Added</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    clients.forEach(client => {
        const sites = client.sites ? client.sites.split(', ') : [];
        const sitesHtml = sites.slice(0, 3).map(site => {
            const cleanSite = site.replace(/^https?:\/\//, '');
            return `<a href="http://${cleanSite}" target="_blank" title="${cleanSite}" onclick="event.stopPropagation()">${highlightText(cleanSite, query)}</a>`;
        }).join('');
        const moreSites = sites.length > 3 ? `<span style="color: #a0aec0;">+${sites.length - 3} more</span>` : '';
        
        const tags = client.tags ? client.tags.split(', ').map(t => `<span class="tag">${highlightText(t, query)}</span>`).join('') : '';
        
        const statusBadge = showActive 
            ? `<td><span class="status-badge ${client.is_active == 1 ? 'status-active' : 'status-inactive'}">${client.is_active == 1 ? 'Active' : 'Inactive'}</span></td>`
            : '';
        
        html += `
            <tr onclick="goToClient(${client.client_id})">
                <td class="client-id">${highlightText(String(client.sayu_user_id || client.client_id), query)}</td>
                <td>
                    <div class="client-info">
                        <span class="client-name">${highlightText(client.client_name, query)}</span>
                        ${client.client_company ? `<span class="client-company">${highlightText(client.client_company, query)}</span>` : ''}
                    </div>
                    ${tags ? `<div class="client-tags">${tags}</div>` : ''}
                </td>
                <td class="client-email">${highlightText(client.client_email || '', query)}</td>
                <td class="client-sites">${sitesHtml}${moreSites}</td>
                ${statusBadge}
                <td class="client-date">${formatDate(client.date_added)}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    resultsContainer.innerHTML = html;
}

// Auto-search if ?q= parameter is present
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const initialQuery = urlParams.get('q');
    if (initialQuery && initialQuery.trim().length >= 1) {
        searchInput.value = initialQuery.trim();
        searchClear.classList.add('active');
        searchLoading.classList.add('active');
        resultsInfo.textContent = 'Searching...';
        performSearch(initialQuery.trim(), currentType);
    }
})();

searchInput.focus();

window.addEventListener('popstate', function() {
    const url = new URL(window.location);
    let type = url.searchParams.get('show_table') || '2';
    if (type === '1') type = '2';
    document.querySelectorAll('.tab-btn').forEach(t => {
        t.classList.toggle('active', t.dataset.type === type);
    });
    if (lastQuery.length >= 3) {
        performSearch(lastQuery, parseInt(type));
    }
});
</script>

</body>
</html>
