<?php
include ("./includes/common.php");

$sFormErr = "";
$sAction = GetParam("FormAction");
if (!$sAction) $sAction = GetParam("action");

// Check for 'remembered' login in cookies
$r_login = "";
$r_password = "";
$remembered_login = false;

if (isset($_COOKIE["monitor_login"]) && $_COOKIE["monitor_login"] && $sAction !== "logout") {
    $login_array = explode("|", $_COOKIE["monitor_login"]);
    if (count($login_array) >= 2 && $login_array[0] && $login_array[1]) {
        $remembered_login = true;
        $r_login = $login_array[0];
        $r_password = $login_array[1];
        $sAction = "login";
    }
}

// Get remember_me from form
$remember_me = GetParam("remember_me") ? true : false;

// Process actions
switch(strtolower($sAction)) {
    case "login":
        if ($remembered_login) {
            $sLogin = $r_login;
            $sPassword = $r_password;
        } else {
            $sLogin = GetParam("Login");
            $sPassword = GetParam("Password");
        }

        $sql  = " SELECT * FROM (users u";
        $sql .= " LEFT JOIN lookup_users_privileges p ON u.privilege_id=p.privilege_id)";
        $sql .= " WHERE login ='" . addslashes($sLogin) . "' AND password='" . addslashes($sPassword) . "' ";
        $sql .= " AND p.PERM_LOGIN_INTERNAL_MONITOR=1";
        $sql .= " AND u.is_deleted IS NULL";
        $db->query($sql);
        
        if($db->next_record()) {
            header("Cache-Control: public");
            
            foreach($perms AS $key => $value) {
                $perms[$key] = $db->Record[$key];
            }

            SetSessionParam("UserID", $db->f("user_id"));
            SetSessionParam("privilege_id", $db->f("privilege_id"));
            SetSessionParam("UserName", $db->f("first_name") . " " . $db->f("last_name"));
            SetSessionParam("session_perms", $perms);

            // Set remember me cookie for 1 year
            if ($remember_me) {
                setcookie("monitor_login", $sLogin . "|" . $sPassword, time() + 3600 * 24 * 365, "/");
            }

            $sPage = GetParam("ret_page");
            if (strlen($sPage)) {
                header("Location: " . $sPage);
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $sFormErr = "Login or Password is incorrect";
        }
        break;
        
    case "logout":
        unset($_SESSION["UserID"]);
        unset($_SESSION["privilege_id"]);
        unset($_SESSION["UserName"]);
        unset($_SESSION["session_perms"]);
        
        // Clear the remember me cookie
        setcookie("monitor_login", "", time() - 3600, "/");
        break;
}

$isLoggedIn = (GetSessionParam("UserID") != "");
$currentUser = "";
if ($isLoggedIn) {
    $db->query("SELECT login FROM users WHERE user_id=" . ToSQL(GetSessionParam("UserID"), "integer"));
    if ($db->next_record()) {
        $currentUser = $db->f("login");
    }
}

$ret_page = GetParam("ret_page");
$querystring = GetParam("querystring");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sayu Monitor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
        }

        .login-header h1 {
            color: white;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .login-body {
            padding: 30px;
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

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
            cursor: pointer;
        }

        .checkbox-group label {
            color: #4a5568;
            font-size: 0.9rem;
            cursor: pointer;
            user-select: none;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
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

        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message svg {
            flex-shrink: 0;
        }

        .logged-in-box {
            text-align: center;
        }

        .logged-in-box .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .logged-in-box .user-avatar svg {
            width: 40px;
            height: 40px;
            color: white;
        }

        .logged-in-box .username {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .logged-in-box .status {
            color: #38a169;
            font-size: 0.9rem;
            margin-bottom: 24px;
        }

        .logged-in-box .btn {
            margin-bottom: 12px;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .logo svg {
            width: 36px;
            height: 36px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <h1>Sayu Monitor</h1>
            <p><?php echo $isLoggedIn ? 'Welcome back!' : 'Sign in to continue'; ?></p>
        </div>
        
        <div class="login-body">
            <?php if ($isLoggedIn): ?>
            <!-- Logged in state -->
            <div class="logged-in-box">
                <div class="user-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <div class="username"><?php echo htmlspecialchars($currentUser); ?></div>
                <div class="status">Currently logged in</div>
                
                <a href="index.php" class="btn btn-primary">Go to Dashboard</a>
                
                <form action="login.php" method="POST">
                    <input type="hidden" name="FormAction" value="logout">
                    <button type="submit" class="btn btn-secondary">Logout</button>
                </form>
            </div>
            
            <?php else: ?>
            <!-- Login form -->
            <?php if ($sFormErr): ?>
            <div class="error-message">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <?php echo htmlspecialchars($sFormErr); ?>
            </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" name="loginForm" id="loginForm">
                <input type="hidden" name="ret_page" value="<?php echo htmlspecialchars($ret_page); ?>">
                <input type="hidden" name="querystring" value="<?php echo htmlspecialchars($querystring); ?>">
                <input type="hidden" name="FormAction" value="login">
                
                <div class="form-group">
                    <label for="Login">Login</label>
                    <input type="text" id="Login" name="Login" maxlength="50" autocomplete="username" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="Password">Password</label>
                    <input type="password" id="Password" name="Password" maxlength="50" autocomplete="current-password">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me">Remember me on this device</label>
                </div>
                
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Focus on login field
    document.addEventListener('DOMContentLoaded', function() {
        const loginField = document.getElementById('Login');
        if (loginField) {
            loginField.focus();
        }
    });
    
    // Submit on Enter
    document.getElementById('loginForm')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.submit();
        }
    });
    </script>
</body>
</html>
