<?php
/**
 * One-off cleanup: when a user has more than one task with task_status_id = 1
 * (In Progress), keep only the most recently started one. All other duplicates
 * are demoted to status 8 (Waiting) and their started_time is reset to NULL,
 * so they don't keep racking up running-hours in dashboards.
 *
 * Safe to re-run: it always recomputes the duplicate set from the live DB,
 * and only touches rows that are actually duplicates at the time of the run.
 *
 * Usage:
 *   1. Visit /cleanup_duplicate_inprogress.php  -> shows a dry-run report
 *   2. Click "Apply cleanup" (or hit /cleanup_duplicate_inprogress.php?confirm=1)
 *      to actually demote the stuck rows.
 *
 * Delete this file once the cleanup has been applied if you don't want it
 * accessible going forward.
 */

include("./includes/common.php");
include("./includes/date_functions.php");

CheckSecurity(1);

$apply = (GetParam("confirm") == "1");

// Find all users that have more than one task with task_status_id = 1.
$sql  = " SELECT responsible_user_id, COUNT(*) AS cnt ";
$sql .= " FROM tasks ";
$sql .= " WHERE task_status_id = 1 AND is_wish = 0 AND is_closed = 0 ";
$sql .= " GROUP BY responsible_user_id ";
$sql .= " HAVING cnt > 1 ";
$db->query($sql);

$affected_users = array();
while ($db->next_record()) {
    $affected_users[] = array(
        'user_id' => (int)$db->f("responsible_user_id"),
        'count'   => (int)$db->f("cnt"),
    );
}

// For each affected user, list their in-progress tasks ordered by started_time DESC
// (so [0] is the most recent and should be kept).
$rows_per_user = array();
foreach ($affected_users as $u) {
    $uid = $u['user_id'];
    $sql  = " SELECT t.task_id, t.task_title, t.started_time, t.actual_hours, ";
    $sql .= "        p.project_title, ";
    $sql .= "        CONCAT(u.first_name, ' ', u.last_name) AS user_name, ";
    $sql .= "        ((UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(t.started_time)) / 3600) AS running_hours ";
    $sql .= " FROM tasks t ";
    $sql .= " INNER JOIN projects p ON p.project_id = t.project_id ";
    $sql .= " INNER JOIN users u ON u.user_id = t.responsible_user_id ";
    $sql .= " WHERE t.task_status_id = 1 AND t.is_wish = 0 AND t.is_closed = 0 ";
    $sql .= "   AND t.responsible_user_id = " . ToSQL($uid, "integer", false);
    $sql .= " ORDER BY t.started_time DESC, t.task_id DESC ";
    $db->query($sql);
    $rows = array();
    while ($db->next_record()) {
        $rows[] = array(
            'task_id'        => (int)$db->f("task_id"),
            'task_title'     => $db->f("task_title"),
            'project_title'  => $db->f("project_title"),
            'user_name'      => $db->f("user_name"),
            'started_time'   => $db->f("started_time"),
            'actual_hours'   => (float)$db->f("actual_hours"),
            'running_hours'  => (float)$db->f("running_hours"),
        );
    }
    $rows_per_user[$uid] = $rows;
}

// Apply step: demote everything except the [0] row in each user's list.
$demoted_ids = array();
if ($apply) {
    foreach ($rows_per_user as $uid => $rows) {
        $keep_id = $rows[0]['task_id']; // most recent started_time
        for ($i = 1; $i < count($rows); $i++) {
            $stale_id = $rows[$i]['task_id'];
            $sql  = " UPDATE tasks ";
            $sql .= " SET task_status_id = 8, started_time = NULL, modified_date = NOW() ";
            $sql .= " WHERE task_id = " . ToSQL($stale_id, "integer", false);
            $sql .= "   AND task_status_id = 1 ";
            $sql .= "   AND responsible_user_id = " . ToSQL($uid, "integer", false);
            $db->query($sql);
            $demoted_ids[] = $stale_id;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cleanup duplicate in-progress tasks</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #333; background: #f5f5f5; padding: 24px; }
        .wrap { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { margin: 0 0 8px; font-size: 20px; color: #2d3748; }
        .sub { color: #718096; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { text-align: left; padding: 8px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #718096; text-transform: uppercase; }
        td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .keep { background: #ecfdf5; }
        .demote { background: #fef2f2; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .pill-keep { background: #d1fae5; color: #065f46; }
        .pill-demote { background: #fee2e2; color: #991b1b; }
        .btn { display: inline-block; padding: 10px 18px; background: #667eea; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; }
        .btn:hover { background: #5a67d8; }
        .btn-back { background: #cbd5e0; color: #2d3748; margin-left: 8px; }
        .ok { background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 16px; margin-bottom: 20px; color: #065f46; }
        .warn { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; margin-bottom: 20px; color: #78350f; }
        .empty { padding: 24px; color: #718096; text-align: center; background: #f8fafc; border-radius: 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Cleanup duplicate in-progress tasks</h1>
    <div class="sub">Finds users with more than one task at <code>task_status_id = 1</code>. Keeps the most recently started row; demotes the rest to "Waiting" (status 8) with <code>started_time = NULL</code>.</div>

<?php if ($apply): ?>
    <div class="ok">
        Cleanup applied. <strong><?php echo count($demoted_ids); ?></strong> stale row(s) demoted to Waiting and <code>started_time</code> cleared.
        <?php if (!empty($demoted_ids)): ?>
            Demoted task IDs: <?php echo htmlspecialchars(implode(", ", $demoted_ids)); ?>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (empty($rows_per_user) && !$apply): ?>
    <div class="empty">No users currently have more than one in-progress task. Nothing to clean up.</div>
<?php elseif (empty($rows_per_user) && $apply): ?>
    <div class="empty">All duplicates resolved. No remaining users with more than one in-progress task.</div>
<?php else: ?>

    <?php if (!$apply): ?>
        <div class="warn">
            Dry-run preview &mdash; nothing has been changed yet.
            Rows highlighted in green will stay In Progress; rows highlighted in red will be demoted to Waiting and have <code>started_time</code> reset to NULL.
        </div>
    <?php endif; ?>

    <?php foreach ($rows_per_user as $uid => $rows): ?>
        <h2 style="font-size: 15px; margin: 16px 0 8px; color: #2d3748;">
            <?php echo htmlspecialchars($rows[0]['user_name']); ?>
            <span style="color: #a0aec0; font-weight: normal; font-size: 13px;">
                (user_id <?php echo (int)$uid; ?> &middot; <?php echo count($rows); ?> in-progress tasks)
            </span>
        </h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 90px;">Decision</th>
                    <th style="width: 80px;">Task ID</th>
                    <th>Title</th>
                    <th>Project</th>
                    <th>Started</th>
                    <th style="text-align: right;">Running (hrs)</th>
                    <th style="text-align: right;">Actual (hrs)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $is_keep = ($i === 0); ?>
                    <tr class="<?php echo $is_keep ? 'keep' : 'demote'; ?>">
                        <td>
                            <span class="pill <?php echo $is_keep ? 'pill-keep' : 'pill-demote'; ?>">
                                <?php echo $is_keep ? 'KEEP' : 'DEMOTE'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_task.php?task_id=<?php echo (int)$r['task_id']; ?>" target="_blank">
                                #<?php echo (int)$r['task_id']; ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($r['task_title']); ?></td>
                        <td><?php echo htmlspecialchars($r['project_title']); ?></td>
                        <td><?php echo htmlspecialchars($r['started_time']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($r['running_hours'], 1); ?></td>
                        <td style="text-align: right;"><?php echo number_format($r['actual_hours'], 1); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <?php if (!$apply): ?>
        <div style="margin-top: 16px;">
            <a class="btn" href="cleanup_duplicate_inprogress.php?confirm=1"
               onclick="return confirm('Demote all DEMOTE rows to Waiting and clear their started_time? This cannot be undone automatically.');">
                Apply cleanup
            </a>
            <a class="btn btn-back" href="index.php">Cancel</a>
        </div>
    <?php else: ?>
        <div style="margin-top: 16px;">
            <a class="btn btn-back" href="index.php">Back to dashboard</a>
        </div>
    <?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
