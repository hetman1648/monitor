<?php

	include("./includes/common.php");

	if (!GetSessionParam("UserID")) {
		header("Location: login.php");
		exit();
	}	

	//$sql = " ALTER TABLE users ADD COLUMN svn_subdomain VARCHAR(255)";
	// $db->query($sql);


	$err		= "";
	$success_msg = "";
	$operation	= GetParam("operation");
	$action		= GetParam("action");
	$first_name	= GetParam("first_name");
	$last_name	= GetParam("last_name");
	$email		= GetParam("email");
	$user_id	= GetParam("user_id");
	$password	= GetParam("password");
	$login		= GetParam("login");
	$day_phone	= GetParam("day_phone");
	$evn_phone	= GetParam("evn_phone");
	$birth_date	= GetParam("birth_date")?GetParam("birth_date"):"0000-00-00";
	$start_date	= GetParam("start_date")?GetParam("start_date"):date("Y-m-d");
	$address	= GetParam("address");
	$cvs_login	= GetParam("cvs_login");	
	$is_viart	= GetParam("is_viart");
	$ticket_tasks = GetParam("ticket_tasks");
	$office_id	= GetParam("office_id");
	$is_sms		= GetParam("is_sms");
	$cell_phone	= GetParam("cell_phone");
	$manager_id	= GetParam("manager_list");
	$is_flexible		= GetParam("is_flexible");
	$privilege_group	= GetParam("privilege_group");
	$msn_account		= GetParam("msn_account");
	$sms_account		= GetParam("sms_account");
	$skype_account		= GetParam("skype_account");
	$svn_login          = GetParam("svn_login");
	$svn_password       = GetParam("svn_password");
	$svn_subdomain      = GetParam("svn_subdomain");
	$web_clients_resource = GetParam("web_clients_resource");
	$helpdesk_user_id	= GetParam("helpdesk_user_id");
	$team_name			= GetParam("team_name");
	$show_users_list		= GetParam("show_users_list");
	$show_projects_list		= GetParam("show_projects_list");
	$is_cvs_notification    = GetParam("is_cvs_notification");
	
	$team_id = 0;

	if ($action == "self") {
		if ($operation == "submit") {
			if (!$first_name)	$err .= "First Name is required. ";
			if (!$last_name)	$err .= "Last Name is required. ";
			if (!$email)		$err .= "Email is required. ";
			if (!$err) {

				$sql = "UPDATE users
						SET email		=".ToSQL($email,"string").",
							first_name	=".ToSQL($first_name,"string").",
							last_name	=".ToSQL($last_name,"string").",
							day_phone	=".ToSQL($day_phone,"string").",
							evn_phone	=".ToSQL($evn_phone,"string").",
							cell_phone	=".ToSQL($cell_phone,"string").",
							msn_account	=".ToSQL($msn_account,"string").",
							sms_account	=".ToSQL($sms_account,"string").",
							skype_account=".ToSQL($skype_account,"string").",
							svn_login   =".ToSQL($svn_login,"string").",
							svn_password=".ToSQL($svn_password,"string").",
							svn_subdomain=".ToSQL($svn_subdomain,"string").",							
							web_clients_resource=".ToSQL(web_clients_resource,"integer").",
							birth_date	=".ToSQL($birth_date,"date").",
							start_date	=".(ToSQL($start_date,"date")=="0000-00-00"?"'".date("Y-m-d")."'":ToSQL($start_date,"date")).",
							address		=".ToSQL($address,"string").",
							show_users_list	=".ToSQL($show_users_list,"integer",false).",
							show_projects_list	=".ToSQL($show_projects_list,"integer",false).",
							cvs_login =".ToSQL($cvs_login,"string")."
						WHERE user_id= " . GetSessionParam("UserID");
				 $db->query($sql,__FILE__,__LINE__);
				 $success_msg = "Profile updated successfully!";
			}
		}

		// Load user data
		$sql = " SELECT * FROM users WHERE user_id=" . GetSessionParam("UserID");
		$db->query($sql,__FILE__,__LINE__);
		$user_data = array();
		if($db->next_record()) {
			$user_data = $db->Record;
		}
		
		$user_name = GetSessionParam("UserName");
		
		// Format dates for display
		$birth_date_display = ($user_data['birth_date'] && $user_data['birth_date'] != '0000-00-00') ? $user_data['birth_date'] : '';
		$start_date_display = ($user_data['start_date'] && $user_data['start_date'] != '0000-00-00') ? date('j M Y', strtotime($user_data['start_date'])) : '';
		
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Sayu Monitor</title>
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
            padding: 0;
        }

        .container {
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a202c;
        }

        .page-header .subtitle {
            font-size: 0.9rem;
            color: #718096;
            font-weight: 400;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #5a67d8;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
        }

        .card-body {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #4a5568;
        }

        .form-group label .required {
            color: #e53e3e;
        }

        .form-input {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #fff;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input:read-only {
            background: #f7fafc;
            color: #718096;
            cursor: not-allowed;
        }

        .form-input.textarea {
            min-height: 80px;
            resize: vertical;
        }

        .input-with-suffix {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-with-suffix .form-input {
            width: 80px;
            text-align: center;
        }

        .input-suffix {
            font-size: 0.85rem;
            color: #718096;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }

        .alert-success {
            background: #c6f6d5;
            color: #276749;
            border: 1px solid #68d391;
        }

        .section-divider {
            font-size: 0.75rem;
            font-weight: 600;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 0 8px 0;
            grid-column: 1 / -1;
            border-top: 1px solid #e2e8f0;
            margin-top: 8px;
        }

        .section-divider:first-child {
            border-top: none;
            padding-top: 0;
            margin-top: 0;
        }

        .static-value {
            padding: 10px 14px;
            background: #f7fafc;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        @media (max-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Dark mode - My Profile */
        html.dark-mode .form-input,
        html.dark-mode input.form-input,
        html.dark-mode textarea.form-input {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .form-input::placeholder,
        html.dark-mode textarea.form-input::placeholder { color: #6b7280 !important; }
        html.dark-mode .form-input:read-only { background: #161b22 !important; color: #8b949e !important; }
        html.dark-mode .static-value {
            background: #1c2333 !important;
            color: #cbd5e0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .form-group label { color: #a0aec0; }
        html.dark-mode .input-suffix { color: #8b949e; }
        html.dark-mode .section-divider { color: #8b949e; border-top-color: #2d333b; }
        html.dark-mode .form-actions { border-top-color: #2d333b; }
        html.dark-mode .alert-error { background: rgba(220, 53, 69, 0.2); color: #fca5a5; border-color: #dc3545; }
        html.dark-mode .alert-success { background: rgba(34, 197, 94, 0.2); color: #86efac; border-color: #22c55e; }
        html.dark-mode .checkbox-label { color: #cbd5e0; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>My Profile</h1>
                <span class="subtitle">Update your personal information</span>
            </div>
        </div>

        <?php if ($err): ?>
        <div class="alert alert-error">
            <span>&#9888;</span>
            <?php echo htmlspecialchars($err); ?>
        </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <span>&#10003;</span>
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
        <?php endif; ?>

        <form name="frmUser" action="user_profile.php?action=self" method="POST">
            <input type="hidden" name="operation" value="submit">

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Contact Information</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Work Phone</label>
                            <input type="tel" name="day_phone" class="form-input" value="<?php echo htmlspecialchars($user_data['day_phone']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Home Phone</label>
                            <input type="tel" name="evn_phone" class="form-input" value="<?php echo htmlspecialchars($user_data['evn_phone']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Mobile Phone</label>
                            <input type="tel" name="cell_phone" class="form-input" value="<?php echo htmlspecialchars($user_data['cell_phone']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Skype</label>
                            <input type="text" name="skype_account" class="form-input" value="<?php echo htmlspecialchars($user_data['skype_account']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Birth Date</label>
                            <input type="date" name="birth_date" class="form-input" value="<?php echo htmlspecialchars($birth_date_display); ?>">
                        </div>

                        <div class="form-group">
                            <label>Start Date</label>
                            <div class="static-value"><?php echo $start_date_display ?: 'Not set'; ?></div>
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($user_data['start_date']); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label>Address</label>
                            <textarea name="address" class="form-input textarea"><?php echo htmlspecialchars($user_data['address']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Developer Settings</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>SVN Login</label>
                            <input type="text" name="svn_login" id="svn_login" class="form-input" value="<?php echo htmlspecialchars($user_data['svn_login']); ?>">
                        </div>

                        <div class="form-group">
                            <label>SVN Password</label>
                            <input type="text" name="svn_password" id="svn_password" class="form-input" value="<?php echo htmlspecialchars($user_data['svn_password']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Developer Subdomain</label>
                            <div class="input-with-suffix">
                                <input type="text" name="svn_subdomain" id="svn_subdomain" class="form-input" value="<?php echo htmlspecialchars($user_data['svn_subdomain']); ?>" placeholder="e.g. ab">
                                <span class="input-suffix">.sayuconnect.com</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Web Clients Resource Allocation (%)</label>
                            <input type="number" name="web_clients_resource" class="form-input" value="<?php echo htmlspecialchars($user_data['web_clients_resource']); ?>" min="0" max="100">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Display Preferences</span>
                </div>
                <div class="card-body">
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="show_users_list" value="1" <?php echo $user_data['show_users_list'] ? 'checked' : ''; ?>>
                            Show users list on dashboard
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="show_projects_list" value="1" <?php echo $user_data['show_projects_list'] ? 'checked' : ''; ?>>
                            Show projects list on dashboard
                        </label>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
<?php
	} else {
		// ==================== Admin User Profile ====================
		$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()) {
			$perm_user_profile = $db->f("PERM_USER_PROFILE");
		} else {
			exit($db->Error);
		}

		if (!$perm_user_profile) {
			exit("You don't have permission for this!");
		}

		// Handle AJAX save
		if ($operation == "ajax_save") {
			while (ob_get_level()) ob_end_clean();
			header('Content-Type: application/json');

			$errors = array();
			if (!$login)          $errors[] = "Login is required";
			if (!$password)       $errors[] = "Password is required";
			if (!$first_name)     $errors[] = "First Name is required";
			if (!$last_name)      $errors[] = "Last Name is required";
			if (!$privilege_group) $errors[] = "Privilege Group is required";

			if (!empty($errors)) {
				echo json_encode(array('success' => false, 'error' => implode(', ', $errors)));
				exit;
			}

			$is_viart            = ($is_viart) ? 1 : 0;
			$is_sms              = ($is_sms) ? 1 : 0;
			$is_cvs_notification = ($is_cvs_notification) ? 1 : 0;
			$is_flexible         = ($is_flexible) ? 1 : 0;
			$ticket_tasks        = ($ticket_tasks) ? 1 : 0;
			$show_users_list     = ($show_users_list) ? 1 : 0;
			$show_projects_list  = ($show_projects_list) ? 1 : 0;

			if ($privilege_group == 3 && (!$manager_id || $manager_id == -1) || $user_id == $manager_id) { $manager_id = 0; }

			if ($user_id) {
				$sql = "UPDATE users
						SET	login		=".ToSQL($login,"string").",
							password	=".ToSQL($password,"string").",
							email		=".ToSQL($email,"string").",
							first_name	=".ToSQL($first_name,"string").",
							last_name	=".ToSQL($last_name,"string").",
							day_phone	=".ToSQL($day_phone,"string").",
							evn_phone	=".ToSQL($evn_phone,"string").",
							cell_phone	=".ToSQL($cell_phone,"string").",
							msn_account	=".ToSQL($msn_account,"string").",
							sms_account	=".ToSQL($sms_account,"string").",
							skype_account=".ToSQL($skype_account,"string").",
							svn_login   =".ToSQL($svn_login,"string").",
							svn_password=".ToSQL($svn_password,"string").",
							svn_subdomain=".ToSQL($svn_subdomain,"string").",
							web_clients_resource=".ToSQL($web_clients_resource,"integer").",
							birth_date	=".ToSQL($birth_date,"date").",
							start_date	=".(ToSQL($start_date,"date")=="0000-00-00"?"'".date("Y-m-d")."'":ToSQL($start_date,"date")).",
							address		=".ToSQL($address,"string").",
							cvs_login   =".ToSQL($cvs_login,"string").",
							privilege_id=".ToSQL($privilege_group,"integer",false).",
							office_id	=".ToSQL($office_id,"integer", false).",
							is_viart	=".ToSQL($is_viart,"integer",false).",
							is_send_sms	=".ToSQL($is_sms,"integer",false).",
							is_cvs_notification	=".ToSQL($is_cvs_notification,"integer",false).",
							is_flexible	=".ToSQL($is_flexible,"integer",false).",
							ticket_tasks =".ToSQL($ticket_tasks,"integer",false).",
							manager_id	=".ToSQL($manager_id,"integer",false).",
							show_users_list	=".ToSQL($show_users_list,"integer",false).",
							show_projects_list	=".ToSQL($show_projects_list,"integer",false).",
							helpdesk_user_id =".ToSQL($helpdesk_user_id,"integer")."
						WHERE user_id=".ToSQL($user_id,"integer",false);
				$db->query($sql,__FILE__,__LINE__);
				echo json_encode(array('success' => true, 'message' => 'Profile saved successfully'));
			} else {
				$sql = " INSERT INTO users (login, password, email, first_name, last_name, day_phone, evn_phone, cell_phone,
						msn_account, sms_account, skype_account, svn_login, svn_password, svn_subdomain, web_clients_resource,
						birth_date, start_date, address, cvs_login, privilege_id, office_id, is_viart, is_send_sms, is_cvs_notification,
						is_flexible, ticket_tasks, manager_id, helpdesk_user_id, show_users_list, show_projects_list)
						VALUES(	".ToSQL($login,"string").",".ToSQL($password,"string").",".ToSQL($email,"string").",
								".ToSQL($first_name,"string").",".ToSQL($last_name,"string").",".ToSQL($day_phone,"string").",
								".ToSQL($evn_phone,"string").",".ToSQL($cell_phone,"string").",".ToSQL($msn_account,"string").",
								".ToSQL($sms_account,"string").",".ToSQL($skype_account,"string").",".ToSQL($svn_login,"string").",
								".ToSQL($svn_password,"string").",".ToSQL($svn_subdomain,"string").",".ToSQL($web_clients_resource,"integer").",
								".ToSQL($birth_date,"date").",".(ToSQL($start_date,"date")=="0000-00-00"?"'".date("Y-m-d")."'":ToSQL($start_date,"date")).",
								".ToSQL($address,"string").",".ToSQL($cvs_login,"string").",".ToSQL($privilege_group,"integer",false).",
								".ToSQL($office_id,"integer").",".ToSQL($is_viart,"integer",false).",".ToSQL($is_sms,"integer",false).",
								".ToSQL($is_cvs_notification,"integer",false).",".ToSQL($is_flexible,"integer",false).",
								".ToSQL($ticket_tasks,"integer",false).",".ToSQL($manager_id,"integer",false).",
								".ToSQL($helpdesk_user_id,"integer").",".ToSQL($show_users_list,"integer",false).",
								".ToSQL($show_projects_list,"integer",false).")";
				$db->query($sql);
				$db->query("SELECT LAST_INSERT_ID()");
				$new_id = 0;
				if ($db->next_record()) $new_id = $db->f(0);
				echo json_encode(array('success' => true, 'message' => 'User created successfully', 'user_id' => $new_id));
			}
			exit;
		}

		// Handle delete/undelete
		if ($operation == "delete" && $user_id) {
			$sql = "UPDATE users SET is_deleted=1 WHERE user_id=".ToSQL($user_id,"integer",false);
			$db->query($sql,__FILE__,__LINE__);
			header("Location: users.php");
			exit;
		}
		if ($operation == "undelete" && $user_id) {
			$sql = "UPDATE users SET is_deleted=Null WHERE user_id=".ToSQL($user_id,"integer",false);
			$db->query($sql,__FILE__,__LINE__);
			header("Location: users.php");
			exit;
		}

		// Load user data
		$u = array();
		$is_new_user = true;
		$is_deleted = false;
		if ($user_id && is_numeric($user_id)) {
			$sql = "SELECT u.*, CONCAT(u.first_name,' ',u.last_name) as user_name, ut.team_name, ut.team_id
					FROM users u
					LEFT JOIN users_teams ut ON (IF(u.manager_id>0,u.manager_id,u.user_id) = ut.manager_id)
					WHERE u.user_id=".ToSQL($user_id,"integer",false);
			$db->query($sql,__FILE__,__LINE__);
			if ($db->next_record()) {
				$u = $db->Record;
				$is_new_user = false;
				$is_deleted = !empty($u['is_deleted']);
			}
		}

		// Load lookups
		$privileges = array();
		$db->query("SELECT privilege_id, privilege_desc FROM lookup_users_privileges ORDER BY privilege_id");
		while ($db->next_record()) {
			$privileges[] = array('id' => $db->f("privilege_id"), 'name' => $db->f("privilege_desc"));
		}

		$offices = array();
		$db->query("SELECT office_id, office_title FROM offices ORDER BY office_id");
		while ($db->next_record()) {
			$offices[] = array('id' => $db->f("office_id"), 'name' => $db->f("office_title"));
		}

		$managers = array();
		$db->query("SELECT user_id, CONCAT(first_name,' ',last_name) as name FROM users WHERE is_deleted IS NULL AND privilege_id=4 ORDER BY first_name, last_name");
		while ($db->next_record()) {
			$managers[] = array('id' => $db->f("user_id"), 'name' => $db->f("name"));
		}

		$page_title = $is_new_user ? 'Create User' : htmlspecialchars($u['user_name']);
		$birth_fmt = (!empty($u['birth_date']) && $u['birth_date'] != '0000-00-00') ? $u['birth_date'] : '';
		$start_fmt = (!empty($u['start_date']) && $u['start_date'] != '0000-00-00') ? $u['start_date'] : '';

		// Helper to safely get value
		function uv($u, $key, $default = '') {
			return isset($u[$key]) ? $u[$key] : $default;
		}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - User Profile - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); min-height: 100vh; color: #1a202c; }
        .container { padding: 24px; max-width: 960px; margin: 0 auto; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-header-left { display: flex; align-items: center; gap: 16px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 700; color: #1a202c; }
        .page-header .subtitle { font-size: 0.85rem; color: #718096; font-weight: 400; }
        .page-header-actions { display: flex; gap: 10px; align-items: center; }

        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #667eea; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
        .back-link:hover { color: #5a67d8; }

        .user-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }

        .badge-deleted { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #fed7d7; color: #c53030; }

        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 14px 24px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-header.green { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); }
        .card-header.orange { background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); }
        .card-header.gray { background: linear-gradient(135deg, #718096 0%, #4a5568 100%); }
        .card-title { font-size: 0.95rem; font-weight: 600; color: #fff; }
        .card-body { padding: 24px; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-size: 0.82rem; font-weight: 600; color: #4a5568; }
        .form-group label .req { color: #e53e3e; }

        .form-input, .form-select { padding: 9px 13px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; font-family: inherit; transition: all 0.2s; background: #fff; width: 100%; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .form-input:read-only { background: #f7fafc; color: #718096; }
        .form-select { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        textarea.form-input { min-height: 70px; resize: vertical; }

        .input-row { display: flex; align-items: center; gap: 8px; }
        .input-suffix { font-size: 0.85rem; color: #718096; white-space: nowrap; }

        .checkbox-row { display: flex; flex-wrap: wrap; gap: 16px 28px; padding: 4px 0; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.88rem; color: #4a5568; }
        .checkbox-label input[type="checkbox"] { width: 17px; height: 17px; accent-color: #667eea; cursor: pointer; }

        .form-actions { display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #e2e8f0; margin-top: 20px; }

        .btn { padding: 10px 22px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: none; font-family: inherit; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-secondary { background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #edf2f7; }
        .btn-danger { background: #e53e3e; color: #fff; }
        .btn-danger:hover { background: #c53030; }
        .btn-success { background: #38a169; color: #fff; }
        .btn-success:hover { background: #2f855a; }
        .btn-sm { padding: 7px 14px; font-size: 0.82rem; }

        .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); color: #fff; padding: 12px 24px; border-radius: 10px; font-size: 0.9rem; font-weight: 500; z-index: 9999; opacity: 0; transition: opacity 0.3s; pointer-events: none; box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
        .toast.show { opacity: 1; }
        .toast.success { background: #48bb78; }
        .toast.error { background: #e53e3e; }

        .saving-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .container { padding: 12px; }
        }

        /* Dark mode - Admin User Profile */
        html.dark-mode .form-input,
        html.dark-mode input.form-input,
        html.dark-mode textarea.form-input {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .form-select {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23a0aec0' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
        }
        html.dark-mode .form-input::placeholder { color: #6b7280 !important; }
        html.dark-mode .form-input:read-only { background: #161b22 !important; color: #8b949e !important; }
        html.dark-mode .form-group label { color: #a0aec0; }
        html.dark-mode .input-suffix { color: #8b949e; }
        html.dark-mode .form-actions { border-top-color: #2d333b; }
        html.dark-mode .checkbox-label { color: #cbd5e0; }
        html.dark-mode .badge-deleted { background: rgba(220, 53, 69, 0.4); color: #fca5a5; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div class="page-header-left">
                <div class="user-avatar"><?php echo strtoupper(substr(uv($u,'first_name','N'), 0, 1) . substr(uv($u,'last_name','U'), 0, 1)); ?></div>
                <div>
                    <h1><?php echo $page_title; ?></h1>
                    <span class="subtitle"><?php echo $is_new_user ? 'Fill in the details to create a new user' : 'User ID: ' . intval($user_id); ?>
                    <?php if ($is_deleted): ?> <span class="badge-deleted">Deleted</span><?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="page-header-actions">
                <a href="users.php" class="back-link">&larr; All Users</a>
                <?php if (!$is_new_user): ?>
                <a href="report_people.php?report_user_id=<?php echo intval($user_id); ?>" class="btn btn-secondary btn-sm">View Report</a>
                <?php endif; ?>
            </div>
        </div>

        <form id="userForm" onsubmit="return false;">
            <input type="hidden" name="operation" value="ajax_save">
            <input type="hidden" name="user_id" value="<?php echo intval($user_id); ?>">

            <!-- Contact Information -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Contact Information</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name <span class="req">*</span></label>
                            <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars(uv($u,'first_name')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="req">*</span></label>
                            <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars(uv($u,'last_name')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars(uv($u,'email')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Work Phone</label>
                            <input type="tel" name="day_phone" class="form-input" value="<?php echo htmlspecialchars(uv($u,'day_phone')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Home Phone</label>
                            <input type="tel" name="evn_phone" class="form-input" value="<?php echo htmlspecialchars(uv($u,'evn_phone')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Mobile Phone</label>
                            <input type="tel" name="cell_phone" class="form-input" value="<?php echo htmlspecialchars(uv($u,'cell_phone')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Skype</label>
                            <input type="text" name="skype_account" class="form-input" value="<?php echo htmlspecialchars(uv($u,'skype_account')); ?>">
                        </div>
                        <div class="form-group">
                            <label>MSN Account</label>
                            <input type="text" name="msn_account" class="form-input" value="<?php echo htmlspecialchars(uv($u,'msn_account')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Birth Date</label>
                            <input type="date" name="birth_date" class="form-input" value="<?php echo htmlspecialchars($birth_fmt); ?>">
                        </div>
                        <div class="form-group">
                            <label>Start Working Date</label>
                            <input type="date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($start_fmt); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>Address</label>
                            <textarea name="address" class="form-input"><?php echo htmlspecialchars(uv($u,'address')); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login & Access -->
            <div class="card">
                <div class="card-header orange">
                    <span class="card-title">Login &amp; Access</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Login <span class="req">*</span></label>
                            <input type="text" name="login" class="form-input" value="<?php echo htmlspecialchars(uv($u,'login')); ?>" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label>Password <span class="req">*</span></label>
                            <input type="text" name="password" class="form-input" value="<?php echo htmlspecialchars(uv($u,'password')); ?>" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label>Privilege Group <span class="req">*</span></label>
                            <select name="privilege_group" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($privileges as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo (uv($u,'privilege_id') == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Office</label>
                            <select name="office_id" class="form-select">
                                <?php foreach ($offices as $o): ?>
                                <option value="<?php echo $o['id']; ?>" <?php echo (uv($u,'office_id') == $o['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($o['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Manager</label>
                            <select name="manager_list" class="form-select">
                                <option value="-1">-- None --</option>
                                <?php foreach ($managers as $mgr): ?>
                                <option value="<?php echo $mgr['id']; ?>" <?php echo (uv($u,'manager_id') == $mgr['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mgr['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Team Name</label>
                            <input type="text" name="team_name" class="form-input" value="<?php echo htmlspecialchars(uv($u,'team_name')); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Developer Settings -->
            <div class="card">
                <div class="card-header gray">
                    <span class="card-title">Developer Settings</span>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>SVN Login</label>
                            <input type="text" name="svn_login" id="svn_login" class="form-input" value="<?php echo htmlspecialchars(uv($u,'svn_login')); ?>">
                        </div>
                        <div class="form-group">
                            <label>SVN Password</label>
                            <input type="text" name="svn_password" id="svn_password" class="form-input" value="<?php echo htmlspecialchars(uv($u,'svn_password')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Developer Subdomain</label>
                            <div class="input-row">
                                <input type="text" name="svn_subdomain" id="svn_subdomain" class="form-input" style="width:80px;" value="<?php echo htmlspecialchars(uv($u,'svn_subdomain')); ?>" placeholder="e.g. ab">
                                <span class="input-suffix">.sayuconnect.com</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Web Clients Resource (%)</label>
                            <input type="number" name="web_clients_resource" class="form-input" value="<?php echo htmlspecialchars(uv($u,'web_clients_resource')); ?>" min="0" max="100" style="width:100px;">
                        </div>
                        <div class="form-group">
                            <label>Helpdesk User ID</label>
                            <input type="number" name="helpdesk_user_id" class="form-input" value="<?php echo htmlspecialchars(uv($u,'helpdesk_user_id')); ?>" style="width:100px;">
                        </div>
                        <div class="form-group">
                            <label>SMS Account</label>
                            <input type="text" name="sms_account" class="form-input" value="<?php echo htmlspecialchars(uv($u,'sms_account')); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="card">
                <div class="card-header green">
                    <span class="card-title">Options</span>
                </div>
                <div class="card-body">
                    <div class="checkbox-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_viart" value="1" <?php echo uv($u,'is_viart') ? 'checked' : ''; ?>>
                            Dev team member
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_flexible" value="1" <?php echo uv($u,'is_flexible') ? 'checked' : ''; ?>>
                            Flexible schedule
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="ticket_tasks" value="1" <?php echo uv($u,'ticket_tasks') ? 'checked' : ''; ?>>
                            Helpdesk tickets
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_sms" value="1" <?php echo uv($u,'is_send_sms') ? 'checked' : ''; ?>>
                            SMS notifications
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_cvs_notification" value="1" <?php echo uv($u,'is_cvs_notification') ? 'checked' : ''; ?>>
                            CVS commit check on close
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="show_users_list" value="1" <?php echo uv($u,'show_users_list') ? 'checked' : ''; ?>>
                            Show users list on dashboard
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="show_projects_list" value="1" <?php echo uv($u,'show_projects_list') ? 'checked' : ''; ?>>
                            Show projects list on dashboard
                        </label>
                    </div>

                    <div class="form-actions">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <?php if (!$is_new_user): ?>
                            <?php if ($is_deleted): ?>
                            <button type="button" class="btn btn-success btn-sm" onclick="userAction('undelete')">Restore User</button>
                            <?php else: ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="userAction('delete')">Delete User</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary" id="saveBtn" onclick="saveProfile()">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    function showToast(msg, type) {
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast show ' + (type || 'success');
        clearTimeout(t._timer);
        t._timer = setTimeout(function() { t.className = 'toast'; }, 3000);
    }

    function saveProfile() {
        var btn = document.getElementById('saveBtn');
        var origText = btn.innerHTML;
        btn.innerHTML = '<span class="saving-spinner"></span> Saving...';
        btn.disabled = true;

        var form = document.getElementById('userForm');
        var formData = new FormData(form);

        // Ensure unchecked checkboxes are sent as 0
        var checkboxes = ['is_viart','is_flexible','ticket_tasks','is_sms','is_cvs_notification','show_users_list','show_projects_list'];
        for (var i = 0; i < checkboxes.length; i++) {
            if (!form.querySelector('[name="' + checkboxes[i] + '"]').checked) {
                formData.set(checkboxes[i], '0');
            }
        }

        var userId = formData.get('user_id');
        var url = 'user_profile.php' + (userId ? '?user_id=' + userId : '');

        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.innerHTML = origText;
            btn.disabled = false;
            if (data.success) {
                showToast(data.message || 'Saved successfully', 'success');
                if (data.user_id && !userId) {
                    // New user created - redirect to edit page
                    window.location = 'user_profile.php?user_id=' + data.user_id;
                }
            } else {
                showToast(data.error || 'Save failed', 'error');
            }
        })
        .catch(function(err) {
            btn.innerHTML = origText;
            btn.disabled = false;
            showToast('Network error: ' + err.message, 'error');
        });
    }

    function userAction(action) {
        var msg = action === 'delete' ? 'Are you sure you want to delete this user?' : 'Restore this user?';
        if (!confirm(msg)) return;
        var userId = document.querySelector('[name="user_id"]').value;
        window.location = 'user_profile.php?user_id=' + userId + '&operation=' + action;
    }

    // Ctrl+S / Cmd+S to save
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveProfile();
        }
    });
    </script>
</body>
</html>
<?php
	}
?>