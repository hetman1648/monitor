<?php

include("./includes/common.php");
include("./includes/date_functions.php");
$user_id = GetSessionParam("UserID");
$can_approve = ($user_id == 3);

$approve = GetParam("approve");
$decline = GetParam("decline");
$period_id = GetParam("period_id");

if ($can_approve && ($approve == 1) && $period_id) {
    $period_id = (int) $period_id;

    // Load vacation and employee details
    $sql = "SELECT d.*, u.first_name, u.last_name, u.email AS user_email
            FROM days_off d
            INNER JOIN users u ON u.user_id = d.user_id
            WHERE d.period_id = " . $period_id;
    $db->query($sql);
    if (!$db->next_record()) {
        header("Location: index.php");
        exit;
    }

    $employee_name = trim($db->f("first_name") . ' ' . $db->f("last_name"));
    $employee_email = trim($db->f("user_email"));
    $title = $db->f("period_title") ?: 'Time off';
    $start_date = $db->f("start_date");
    $end_date = $db->f("end_date");
    $total_days = (int) $db->f("total_days");
    $is_paid = (int) $db->f("is_paid");

    // Manager (you) email for CC
    $db->query("SELECT email FROM users WHERE user_id = 3");
    $manager_email = $db->next_record() ? trim($db->f("email")) : '';

    // Update to approved
    $db->query("UPDATE days_off SET is_approved = '1' WHERE period_id = " . $period_id);

    // Send cheerful HTML email to employee, CC to manager
    if ($employee_email) {
        $start_fmt = date('l, j F Y', strtotime($start_date));
        $end_fmt = date('l, j F Y', strtotime($end_date));
        $days_text = $total_days == 1 ? '1 day' : $total_days . ' days';
        $type_label = $is_paid ? 'Overtime / paid leave' : 'Vacation';

        $subject = "Your time off has been approved – enjoy!";

        $message = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Time off approved</title>
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background:#f5f7fa; color:#1a202c;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fa;">
    <tr>
      <td style="padding: 32px 20px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 520px; margin:0 auto; background:#fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow:hidden;">
          <tr>
            <td style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); padding: 28px 32px; text-align:center;">
              <span style="font-size: 48px;">&#127881;</span>
              <h1 style="margin: 12px 0 0; font-size: 1.5rem; font-weight: 700; color:#fff; letter-spacing: -0.02em;">You\'re all set!</h1>
              <p style="margin: 6px 0 0; font-size: 1rem; color: rgba(255,255,255,0.95);">Your time off has been approved</p>
            </td>
          </tr>
          <tr>
            <td style="padding: 28px 32px;">
              <p style="margin: 0 0 16px; font-size: 1.05rem; line-height: 1.5; color:#2d3748;">Hi ' . htmlspecialchars($employee_name) . ',</p>
              <p style="margin: 0 0 20px; font-size: 1.05rem; line-height: 1.6; color:#4a5568;">Great news – your <strong>' . htmlspecialchars($type_label) . '</strong> request has been approved. Time to put the out-of-office on and enjoy yourself!</p>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                <tr>
                  <td style="padding: 20px 24px;">
                    <p style="margin: 0 0 8px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color:#718096; font-weight: 600;">Request</p>
                    <p style="margin: 0 0 12px; font-size: 1.1rem; font-weight: 600; color:#2d3748;">' . htmlspecialchars($title) . '</p>
                    <p style="margin: 0 0 4px; font-size: 0.95rem; color:#4a5568;">' . $start_fmt . ' &rarr; ' . $end_fmt . '</p>
                    <p style="margin: 8px 0 0; font-size: 0.95rem; color:#4a5568;"><strong>' . $days_text . '</strong></p>
                  </td>
                </tr>
              </table>
              <p style="margin: 24px 0 0; font-size: 1rem; line-height: 1.6; color:#4a5568;">Have a wonderful break – you\'ve earned it! &#127807;</p>
              <p style="margin: 20px 0 0; font-size: 0.95rem; color:#718096;">Best regards,<br><strong>Sayu Monitor</strong></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Sayu Monitor <monitor@sayu.co.uk>\r\n";
        if ($manager_email) {
            $headers .= "Cc: " . $manager_email . "\r\n";
        }
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        @mail($employee_email, $subject, $message, $headers);
    }

    header("Location: view_vacations.php");
    exit;
} elseif ($can_approve && ($decline == 1) && $period_id) {
    $period_id = (int) $period_id;
    $db->query("UPDATE days_off SET is_declined = '1' WHERE period_id = " . $period_id);
    header("Location: view_vacations.php");
    exit;
} else {
    header("Location: index.php");
    exit;
}	
				  