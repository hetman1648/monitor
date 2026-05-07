/**
 * Time Doctor <-> Sayu Monitor — Trello Compatibility Layer
 * =========================================================
 *
 * WHAT THIS DOES:
 * When a user clicks a task (opens edit_task.php or clicks a kanban card),
 * this script creates hidden Trello-compatible DOM elements so the
 * Time Doctor Chrome extension detects a "Trello card detail" and
 * injects its native Start/Stop Timer button.
 *
 * SETUP:
 * 1. In Time Doctor Chrome Extension -> right-click -> Options
 *    -> select "Trello" -> add your Sayu Monitor domain
 *    (e.g. monitor.sayu.co.uk or whatever your domain is)
 *
 * 2. This script is already included via modern_header.php.
 *
 * HOW IT WORKS:
 * The TD extension polls/observes for:
 *   [data-testid="card-back-title-input"]:not(.timedoctor2)
 * When found, it reads the task name from that element and the
 * board/project name from [data-testid="board-name-input"],
 * then injects a Start/Stop Timer button near
 * [data-testid="card-back-share-button"].
 *
 * This script creates those exact DOM elements populated with
 * your Sayu Monitor task data.
 *
 * INTEGRATION POINTS:
 * - edit_task.php: call TDCompat.startTask(taskName, projectName) at page load
 * - index.php: hookDashboard() automatically wraps window.confirmTaskAction
 */

(function () {
    'use strict';

    // ─── Configuration ──────────────────────────────────────────
    var CONFIG = {
        // Where the TD timer button bar will appear on your page.
        // Set to a CSS selector of an existing element, or null
        // to let the script append to body.
        timerBarParent: null,

        // Automatically show the Trello compat layer when a task
        // detail page is detected (edit_task.php)
        autoDetectTaskPage: true,

        // Also hook into the dashboard "Start" action buttons
        hookDashboardStart: true,

        // Debug logging
        debug: false
    };

    function log() {
        if (CONFIG.debug) {
            var args = Array.prototype.slice.call(arguments);
            args.unshift('[TD-Compat]');
            console.log.apply(console, args);
        }
    }

    // ─── State ──────────────────────────────────────────────────
    var currentTaskName = null;
    var currentProjectName = null;
    var compatContainer = null;

    // ─── Core: Create Trello-compatible DOM ─────────────────────
    /**
     * Creates the minimal Trello card-detail DOM that the
     * Time Doctor extension expects to find.
     *
     * @param {string} taskName    - The task/card title
     * @param {string} projectName - The project/board name
     * @param {HTMLElement} [parentEl] - Where to insert the compat bar
     */
    function createTrelloCompat(taskName, projectName, parentEl) {
        destroyTrelloCompat();

        log('Creating Trello compat layer:', taskName, '/', projectName);

        currentTaskName = taskName;
        currentProjectName = projectName;

        // ── Outer container ──
        compatContainer = document.createElement('div');
        compatContainer.id = 'td-trello-compat';
        compatContainer.setAttribute('role', 'presentation');

        // ── Board name (project) ──
        // TD reads: document.querySelector('[data-testid="board-name-input"]')?.value
        var boardNameEl = document.createElement('input');
        boardNameEl.setAttribute('data-testid', 'board-name-input');
        boardNameEl.value = projectName || 'Sayu Monitor';
        boardNameEl.style.cssText = 'display:none;';
        compatContainer.appendChild(boardNameEl);

        // ── Card back container ──
        var cardBack = document.createElement('div');
        cardBack.setAttribute('data-testid', 'card-back-name');
        compatContainer.appendChild(cardBack);

        // ── Card title ──
        // TD waits for: [data-testid="card-back-title-input"]:not(.timedoctor2)
        var cardTitle = document.createElement('div');
        cardTitle.setAttribute('data-testid', 'card-back-title-input');
        cardTitle.textContent = taskName;
        cardTitle.style.cssText = 'display:none;';
        cardBack.appendChild(cardTitle);

        // ── Actions area (where TD injects its button) ──
        // Must have a first child so TD's insertBefore(btn, container.firstChild) doesn't throw
        var actionsBtn = document.createElement('div');
        actionsBtn.setAttribute('data-testid', 'card-back-actions-button');
        var actionsPlaceholder = document.createElement('span');
        actionsPlaceholder.style.display = 'none';
        actionsBtn.appendChild(actionsPlaceholder);
        cardBack.appendChild(actionsBtn);

        // ── Insert into the page ──
        // If parentEl is supplied, append the container *inside* it
        // (so it participates in the parent's flex row, e.g. the action-buttons bar).
        // Fall back to appending to body when no anchor is given.
        if (parentEl) {
            parentEl.appendChild(compatContainer);
        } else {
            document.body.appendChild(compatContainer);
        }

        log('Trello compat layer created. TD extension should detect it shortly.');

        if (CONFIG.debug) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.type === 'attributes' && m.attributeName === 'class') {
                        if (cardTitle.classList.contains('timedoctor2')) {
                            log('Time Doctor detected the card! Extension has injected its UI.');
                            observer.disconnect();
                        }
                    }
                    if (m.type === 'childList' && m.addedNodes.length) {
                        m.addedNodes.forEach(function (node) {
                            if (node.nodeType === 1) {
                                log('TD injected element:', node.tagName, node.className || node.id || '');
                            }
                        });
                    }
                });
            });
            observer.observe(compatContainer, {
                attributes: true,
                childList: true,
                subtree: true,
                attributeFilter: ['class']
            });
        }
    }

    function destroyTrelloCompat() {
        if (compatContainer) {
            compatContainer.remove();
            compatContainer = null;
            log('Trello compat layer destroyed.');
        }
        currentTaskName = null;
        currentProjectName = null;
    }

    function updateTrelloCompat(taskName, projectName) {
        if (!compatContainer) {
            createTrelloCompat(taskName, projectName);
            return;
        }

        var titleEl = compatContainer.querySelector('[data-testid="card-back-title-input"]');
        var boardEl = compatContainer.querySelector('[data-testid="board-name-input"]');

        if (titleEl) {
            titleEl.textContent = taskName;
            titleEl.classList.remove('timedoctor2');
        }
        if (boardEl && projectName) {
            boardEl.value = projectName;
        }

        currentTaskName = taskName;
        currentProjectName = projectName || currentProjectName;
        log('Updated Trello compat:', taskName, '/', currentProjectName);
    }


    // ─── Integration: Dashboard (index.php) ─────────────────────

    /**
     * Wrap window.confirmTaskAction so that when a user clicks
     * "Start" on any task, the Trello compat layer is created and
     * TD can inject its timer button.
     */
    function hookDashboard() {
        if (!CONFIG.hookDashboardStart) return;
        if (typeof window.confirmTaskAction !== 'function') return;

        var originalConfirmTaskAction = window.confirmTaskAction;

        window.confirmTaskAction = function (action, taskId, taskTitle, currentCompletion, isPeriodic) {
            if (action === 'start') {
                var projectName = findProjectForTask(taskId);
                createTrelloCompat(taskTitle, projectName);
                log('Hooked Start action for task:', taskId, taskTitle);
            }
            return originalConfirmTaskAction.apply(this, arguments);
        };

        log('Dashboard Start action hooked.');
    }

    /**
     * Find the project name for a given task ID from existing DOM.
     * Supports table view (tr[data-task-id]) and kanban (kb-card).
     */
    function findProjectForTask(taskId) {
        // Table row: <tr data-task-id="..."> <td class="col-project"><strong>Name</strong></td>
        var row = document.querySelector('tr[data-task-id="' + taskId + '"]');
        if (row) {
            var projCell = row.querySelector('.col-project strong');
            if (projCell) return projCell.textContent.trim();
        }

        // Kanban card (by-project view): card inside .kb-column whose header has .kb-column-title
        var card = document.querySelector('.kb-card[data-task-id="' + taskId + '"]');
        if (card) {
            var col = card.closest('.kb-column');
            if (col) {
                var colTitle = col.querySelector('.kb-column-title');
                if (colTitle) return colTitle.textContent.trim();
            }
            // Status kanban: project may be in a card-level element
            var projSpan = card.querySelector('.kb-card-project');
            if (projSpan) return projSpan.textContent.trim();
        }

        return 'Sayu Monitor';
    }


    // ─── Integration: Task Detail Page (edit_task.php) ───────────

    /**
     * Auto-detect if we're on an edit_task.php page and create
     * the compat layer from the task data.
     *
     * NOTE: edit_task.php also outputs an inline script that calls
     * TDCompat.startTask() directly with PHP-provided values.
     * This function acts as a fallback only.
     */
    function detectTaskPage() {
        if (!CONFIG.autoDetectTaskPage) return;
        if (!window.location.pathname.match(/edit_task\.php/)) return;

        function init() {
            // Skip if the inline page script already set up the compat layer.
            // (DOMContentLoaded fires after the inline script runs, which would
            //  destroy a correctly-positioned container and re-append to body.)
            if (compatContainer) return;

            // h1.task-title is used in edit_task.php
            var taskTitle =
                document.querySelector('h1.task-title, .task-title, .page-title');
            // project link: <a href="view_project_tasks.php?...">ProjectName</a>
            var projectTitle =
                document.querySelector('.task-project, [href*="view_project_tasks.php"], .project-name');

            if (taskTitle) {
                var tName = taskTitle.textContent.trim();
                var pName = projectTitle ? projectTitle.textContent.trim() : 'Sayu Monitor';
                // Find the action-buttons bar to place TD inline with Start/Edit/etc.
                var anchor =
                    document.getElementById('task-action-buttons') ||
                    document.querySelector('.view-mode .task-header') ||
                    document.querySelector('.task-header') ||
                    document.querySelector('.task-title-wrapper');
                createTrelloCompat(tName, pName, anchor || null);
                log('Auto-detected task page:', tName, '/', pName);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }


    // ─── Integration: Persistent "board" element ─────────────────

    /**
     * Always keep a board-name-input on the page so TD knows
     * which "board" we're on, even before a card is opened.
     */
    function ensureBoardName() {
        if (document.querySelector('[data-testid="board-name-input"]')) return;

        var boardEl = document.createElement('div');
        boardEl.setAttribute('data-testid', 'board-name-input');
        boardEl.textContent = 'Sayu Monitor';
        boardEl.style.display = 'none';
        document.body.appendChild(boardEl);
    }


    // ─── Public API ──────────────────────────────────────────────

    window.TDCompat = {
        /**
         * Show the TD timer for a specific task.
         * Called directly from edit_task.php with PHP-provided values.
         *
         * @param {string} taskName
         * @param {string} projectName
         * @param {HTMLElement} [parentEl]
         *
         * Example:
         *   TDCompat.startTask('Fix homepage bug', 'Internal Tools');
         */
        startTask: function (taskName, projectName, parentEl) {
            createTrelloCompat(taskName, projectName || 'Sayu Monitor', parentEl);
        },

        updateTask: function (taskName, projectName) {
            updateTrelloCompat(taskName, projectName);
        },

        stop: function () {
            destroyTrelloCompat();
        },

        isDetected: function () {
            if (!compatContainer) return false;
            var el = compatContainer.querySelector('[data-testid="card-back-title-input"]');
            return el && el.classList.contains('timedoctor2');
        }
    };


    // ─── Boot ────────────────────────────────────────────────────

    function boot() {
        log('Initializing Trello compatibility layer for Time Doctor...');
        ensureBoardName();
        hookDashboard();
        detectTaskPage();
        log('Ready.');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
