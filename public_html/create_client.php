<?php
include("./includes/date_functions.php");
include("./includes/common.php");
CheckSecurity(1);

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

$action_value   = GetParam("action_value");
$action         = GetParam("action");
$client_name    = trim(GetParam("client_name"));
$sayu_user_id   = GetParam("sayu_user_id");
$client_email   = GetParam("client_email");
$is_viart       = GetParam("is_viart")=="on"?1:0;
$is_viart_hosted= GetParam("is_viart_hosted")=="on"?1:0;
$is_active      = GetParam("is_active")=="on"?1:0;
$notes          = GetParam("notes");
$client_id      = GetParam("client_id");
$delete         = GetParam("delete");
$date_added     = GetParam("date_added");
$company_name   = GetParam("company_name");
$google_id      = GetParam("google_id");
$mcc_account    = GetParam("mcc_account");
$is_sayu_active = GetParam("is_sayu_active");
$client_type    = GetParam("client_type");
$new_sayu_user  = GetParam("new_sayu_user");

$error = "";

if ($action == "Cancel") {
    header("Location: view_clients.php");
    exit;
}

if ($action_value == 'Update Client') {
    if (!$client_name) { $error .= "Client name is required"; }
    if (!$error) {
        $sql = "UPDATE clients
                SET sayu_user_id    = ".ToSQL($sayu_user_id,"integer",false,false).",
                    client_name     = ".ToSQL($client_name,"string").",
                    client_company  = ".ToSQL($company_name,"string").",
                    client_email    = ".ToSQL($client_email,"string").",
                    account_mcc     = ".ToSQL($mcc_account,"string").",
                    google_id       = ".ToSQL($google_id,"string").",
                    is_viart        = ".ToSQL($is_viart,"integer",false,false).",
                    is_viart_hosted = ".ToSQL($is_viart_hosted,"integer",false,false).",
                    is_active       = ".ToSQL($is_active,"integer",false,false).",
                    notes           = ".ToSQL($notes,"string")."
                WHERE client_id = ".ToSQL($client_id,"integer",false,false);
        $db->query($sql,__FILE__,__LINE__);
        header("Location: view_clients.php");
        exit;
    }
} elseif ($action_value == 'Create Client') {
    if (!$client_name) { $error .= "Client name is required"; }
    if (!$error) {
        $sql = "INSERT INTO clients SET ";
        if ($new_sayu_user == 1)
            $sql .= " sayu_user_id = -1,"; 
        else 
            $sql .= " sayu_user_id = ".ToSQL($sayu_user_id,"integer").",";
        $sql .= "   client_name     = ".ToSQL($client_name,"string").",
                    client_company  = ".ToSQL($company_name,"string").",
                    client_email    = ".ToSQL($client_email,"string").",
                    account_mcc     = ".ToSQL($mcc_account,"string").",
                    google_id       = ".ToSQL($google_id,"string").",
                    is_viart        = ".ToSQL($is_viart,"integer").",
                    is_viart_hosted = ".ToSQL($is_viart_hosted,"integer").",
                    is_active       = 1,
                    notes           = ".ToSQL($notes,"string").",
                    date_added = NOW()";
        $db->query($sql,__FILE__,__LINE__);
        $sql="SELECT LAST_INSERT_ID() as last_id";
        $db->query($sql,__FILE__,__LINE__);
        $db->next_record();
        header("Location: create_client.php?client_id=".$db->Record["last_id"]);
        exit;
    }
}

if ($delete) {
    $sql = 'DELETE FROM clients_sites WHERE client_id = '.ToSQL($client_id,"integer");
    $db->query($sql,__FILE__,__LINE__);
    $sql = 'DELETE FROM clients WHERE client_id = '.ToSQL($client_id,"integer");
    $db->query($sql,__FILE__,__LINE__);
    header("Location: view_clients.php");
    exit;
}

// Load existing client data
$client = null;
$sites = array();

if ($client_id) {
    $sql = "SELECT notes, client_name, sayu_user_id, client_email, client_company,
                   account_mcc, google_id, is_viart, is_viart_hosted, is_active,
                   client_type, client_id, DATE(date_added) as date_added
            FROM clients
            WHERE client_id=".ToSQL($client_id,"integer");
    $db->query($sql,__FILE__,__LINE__);
    
    if ($db->next_record()) {
        $client = array(
            'client_id' => $db->Record['client_id'],
            'client_name' => $db->Record['client_name'],
            'sayu_user_id' => $db->Record['sayu_user_id'],
            'client_email' => $db->Record['client_email'],
            'company_name' => $db->Record['client_company'],
            'mcc_account' => $db->Record['account_mcc'],
            'google_id' => $db->Record['google_id'],
            'is_viart' => $db->Record['is_viart'],
            'is_viart_hosted' => $db->Record['is_viart_hosted'],
            'is_active' => $db->Record['is_active'],
            'client_type' => $db->Record['client_type'],
            'date_added' => $db->Record['date_added'],
            'notes' => $db->Record['notes']
        );
        $client_type = $db->Record['client_type'];
    }
    
    // Load sites
    $sql2 = "SELECT site_id, web_address, admin_web_address, admin_web_site_login,
                    admin_web_site_password, ftp_address, ftp_login, ftp_password,
                    DATE(date_added) as date_added, DATE(date_changed) as date_changed, notes
             FROM clients_sites
             WHERE client_id = ".ToSQL($client_id,"integer");
    $db->query($sql2,__FILE__,__LINE__);
    
    while ($db->next_record()) {
        $site_id = $db->f('site_id');
        $web_address = str_replace("http://", "", $db->Record['web_address']);
        $web_address = str_replace("https://", "", $web_address);
        $admin_web_address = str_replace("http://", "", $db->Record['admin_web_address']);
        $admin_web_address = str_replace("https://", "", $admin_web_address);
        $svn_domain = str_replace("www.", "", $web_address);
        $svn_domain = rtrim($svn_domain, "/");
        
        // Get tags for this site
        $tags = array();
        $sql3 = "SELECT t.title FROM clients_sites_tags st
                 INNER JOIN clients_tags t ON t.id=st.tag_id
                 WHERE st.site_id=" . ToSQL($site_id, "integer");
        $db2->query($sql3, __FILE__,__LINE__);
        while ($db2->next_record()) {
            $tags[] = $db2->f("title");
        }
        
        $sites[] = array(
            'site_id' => $site_id,
            'web_address' => $web_address,
            'admin_web_address' => $admin_web_address,
            'admin_login' => $db->Record['admin_web_site_login'],
            'admin_password' => $db->Record['admin_web_site_password'],
            'ftp_address' => $db->Record['ftp_address'],
            'ftp_login' => $db->Record['ftp_login'],
            'ftp_password' => $db->Record['ftp_password'],
            'svn_domain' => $svn_domain,
            'notes' => $db->Record['notes'],
            'tags' => $tags
        );
    }
}

$isEdit = ($client !== null);
$pageTitle = $isEdit ? htmlspecialchars($client['client_name']) : 'New Client';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Control</title>
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
        
        .client-container {
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
        
        .header-actions {
            display: flex;
            gap: 10px;
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
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .btn-danger {
            background: #fc8181;
            color: #742a2a;
        }
        
        .btn-danger:hover {
            background: #f56565;
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
        
        .client-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #fed7d7;
            color: #822727;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 12px 20px;
            align-items: center;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            text-align: right;
            font-size: 0.85rem;
        }
        
        .form-field {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-field input[type="text"],
        .form-field input[type="email"],
        .form-field textarea {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            max-width: 350px;
        }
        
        .form-field input:focus,
        .form-field textarea:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }
        
        .form-field textarea {
            max-width: 100%;
            min-height: 80px;
            resize: vertical;
        }
        
        .form-field .form-text {
            padding: 10px 0;
            color: #4a5568;
            font-size: 0.85rem;
        }
        
        .copy-btn {
            background: #edf2f7;
            border: none;
            border-radius: 4px;
            padding: 6px 8px;
            cursor: pointer;
            color: #718096;
            transition: all 0.15s;
            flex-shrink: 0;
            font-size: 12px;
        }
        
        .copy-btn:hover {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .copy-btn.copied {
            background: #d4edda;
            border-color: #28a745;
            color: #28a745;
        }
        
        .checkbox-field {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-field input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #2c5aa0;
        }
        
        .checkbox-field label {
            cursor: pointer;
            color: #4a5568;
            font-size: 0.85rem;
        }
        
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .form-actions .btn-danger {
            margin-left: auto;
        }
        
        /* Sites Section */
        .sites-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        
        .sites-header {
            background: #f7fafc;
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sites-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1a202c;
        }
        
        .site-card {
            padding: 20px 24px;
            border-bottom: 1px solid #edf2f7;
        }
        
        .site-card:last-child {
            border-bottom: none;
        }
        
        .site-url {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .site-url a {
            color: #2c5aa0;
            text-decoration: none;
        }
        
        .site-url a:hover {
            text-decoration: underline;
        }
        
        .site-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        
        .site-detail {
            background: #f7fafc;
            padding: 10px 14px;
            border-radius: 6px;
        }
        
        .site-detail-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            color: #718096;
            margin-bottom: 4px;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        
        .site-detail-value {
            font-size: 0.8rem;
            color: #2d3748;
            word-break: break-all;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }
        
        .site-detail-value .copy-btn {
            padding: 3px 6px;
            font-size: 10px;
        }
        
        .site-tags {
            margin-top: 12px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-admin-login {
            background: #48bb78;
            color: #fff !important;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
        }
        
        .btn-admin-login:hover {
            background: #38a169;
            color: #fff !important;
            text-decoration: none;
        }
        
        .tag {
            display: inline-block;
            background: #ebf4ff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            color: #2c5aa0;
            text-decoration: none;
        }
        
        .tag:hover {
            background: #c3dafe;
            text-decoration: none;
        }
        
        .no-sites {
            padding: 40px;
            text-align: center;
            color: #718096;
        }
        
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
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-label {
                text-align: left;
            }
            
            .site-details {
                grid-template-columns: 1fr;
            }
        }
        
        /* Dark mode */
        html.dark-mode .client-container { color: #e2e8f0; }
        html.dark-mode .page-title { color: #e2e8f0; }
        html.dark-mode .page-subtitle { color: #a0aec0; }
        html.dark-mode .client-form {
            background: #161b22;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .form-label { color: #cbd5e0; }
        html.dark-mode .form-field input[type="text"],
        html.dark-mode .form-field input[type="email"],
        html.dark-mode .form-field textarea {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .form-field input:focus,
        html.dark-mode .form-field textarea:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        html.dark-mode .form-field .form-text { color: #a0aec0; }
        html.dark-mode .copy-btn { background: #1c2333; color: #a0aec0; border: 1px solid #2d333b; }
        html.dark-mode .copy-btn:hover { background: #2d333b; color: #e2e8f0; }
        html.dark-mode .copy-btn.copied { background: #22543d; color: #9ae6b4; border-color: #276749; }
        html.dark-mode .checkbox-field label { color: #cbd5e0; }
        html.dark-mode .checkbox-field input[type="checkbox"] { accent-color: #667eea; }
        html.dark-mode .form-actions { border-top-color: #2d333b; }
        html.dark-mode .btn-secondary { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .btn-secondary:hover { background: #2d333b; color: #fff; }
        html.dark-mode .btn-success { background: #22543d; color: #9ae6b4; }
        html.dark-mode .btn-success:hover { background: #276749; color: #c6f6d5; }
        html.dark-mode .btn-danger { background: #742a2a; color: #feb2b2; }
        html.dark-mode .btn-danger:hover { background: #9b2c2c; color: #fed7d7; }
        html.dark-mode .sites-section { background: #161b22; border: 1px solid #2d333b; }
        html.dark-mode .sites-header { background: #1c2333; border-bottom-color: #2d333b; }
        html.dark-mode .sites-header h2 { color: #e2e8f0; }
        html.dark-mode .site-card { border-bottom-color: #2d333b; }
        html.dark-mode .site-url a { color: #90cdf4; }
        html.dark-mode .site-detail { background: #1c2333; }
        html.dark-mode .site-detail-label { color: #8b949e; }
        html.dark-mode .site-detail-value { color: #e2e8f0; }
        html.dark-mode .tag { background: #1c2333; color: #90cdf4; }
        html.dark-mode .tag:hover { background: #2d333b; }
        html.dark-mode .no-sites { color: #a0aec0; }
        html.dark-mode .error-message { background: #742a2a; color: #feb2b2; border: 1px solid #9b2c2c; }
    </style>
</head>
<body class="PageBODY">

<?php 
$user_name = GetSessionParam("UserName");
include("./includes/modern_header.php"); 
?>

<div class="client-container">
    <div class="page-header">
        <div>
            <h1 class="page-title"><?php echo $isEdit ? 'Edit Client' : 'New Client'; ?></h1>
            <p class="page-subtitle"><?php echo $isEdit ? htmlspecialchars($client['client_name']) : 'Create a new client record'; ?></p>
        </div>
        <div class="header-actions">
            <a href="view_clients.php" class="btn btn-secondary">&larr; Back to Clients</a>
        </div>
    </div>
    
    <form class="client-form" action="create_client.php" method="GET">
        <input type="hidden" name="client_type" value="<?php echo $client_type ?: 1; ?>">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        
        <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-grid">
            <div class="form-label">Client Name</div>
            <div class="form-field">
                <input type="text" name="client_name" value="<?php echo htmlspecialchars($client ? $client['client_name'] : $client_name); ?>" required>
                <button type="button" class="copy-btn" onclick="copyField(this)" title="Copy to clipboard">&#128203;</button>
            </div>
            
            <div class="form-label">Company</div>
            <div class="form-field">
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($client ? $client['company_name'] : $company_name); ?>">
                <button type="button" class="copy-btn" onclick="copyField(this)" title="Copy to clipboard">&#128203;</button>
            </div>
            
            <div class="form-label">Sayu User ID</div>
            <div class="form-field">
                <input type="text" name="sayu_user_id" value="<?php echo htmlspecialchars($client ? $client['sayu_user_id'] : $sayu_user_id); ?>" style="max-width: 120px;">
                <button type="button" class="copy-btn" onclick="copyField(this)" title="Copy to clipboard">&#128203;</button>
            </div>
            
            <div class="form-label">Email</div>
            <div class="form-field">
                <input type="text" name="client_email" value="<?php echo htmlspecialchars($client ? $client['client_email'] : $client_email); ?>">
                <button type="button" class="copy-btn" onclick="copyField(this)" title="Copy to clipboard">&#128203;</button>
            </div>
            
            <div class="form-label">MCC Account</div>
            <div class="form-field">
                <input type="text" name="mcc_account" value="<?php echo htmlspecialchars($client ? $client['mcc_account'] : $mcc_account); ?>">
                <button type="button" class="copy-btn" onclick="copyField(this)" title="Copy to clipboard">&#128203;</button>
            </div>
            
            <div class="form-label">Google ID</div>
            <div class="form-field">
                <input type="text" name="google_id" value="<?php echo htmlspecialchars($client ? $client['google_id'] : $google_id); ?>">
                <button type="button" class="copy-btn" onclick="copyField(this)" title="Copy to clipboard">&#128203;</button>
            </div>
            
            <div class="form-label">Notes</div>
            <div class="form-field">
                <textarea name="notes"><?php echo htmlspecialchars($client ? $client['notes'] : $notes); ?></textarea>
            </div>
            
            <?php if ($isEdit): ?>
            <div class="form-label">Date Added</div>
            <div class="form-field">
                <span class="form-text"><?php echo $client['date_added'] ? date('j M Y', strtotime($client['date_added'])) : '-'; ?></span>
            </div>
            <?php endif; ?>
            
            <div class="form-label">Options</div>
            <div class="form-field" style="flex-direction: column; align-items: flex-start; gap: 12px;">
                <div class="checkbox-field">
                    <input type="checkbox" name="is_viart" id="is_viart" <?php echo ($client && $client['is_viart']) ? 'checked' : ($is_viart ? 'checked' : ''); ?>>
                    <label for="is_viart">Is Sayu Client</label>
                </div>
                <div class="checkbox-field">
                    <input type="checkbox" name="is_viart_hosted" id="is_viart_hosted" <?php echo ($client && $client['is_viart_hosted']) ? 'checked' : ($is_viart_hosted ? 'checked' : ''); ?>>
                    <label for="is_viart_hosted">Is Sayu Hosted</label>
                </div>
                <?php if ($isEdit): ?>
                <div class="checkbox-field">
                    <input type="checkbox" name="is_active" id="is_active" <?php echo ($client && $client['is_active']) ? 'checked' : ($is_active ? 'checked' : ''); ?>>
                    <label for="is_active">Active</label>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="action_value" value="<?php echo $isEdit ? 'Update Client' : 'Create Client'; ?>" class="btn btn-success">
                    <?php echo $isEdit ? 'Update Client' : 'Create Client'; ?>
                </button>
                <button type="submit" name="action" value="Cancel" class="btn btn-secondary">Cancel</button>
                <?php if ($isEdit): ?>
                <button type="submit" name="delete" value="1" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this client and all their sites?')">Delete Client</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
    
    <?php if ($isEdit): ?>
    <div class="sites-section">
        <div class="sites-header">
            <h2>Sites (<?php echo count($sites); ?>)</h2>
            <a href="create_site.php?client_id=<?php echo $client_id; ?>" class="btn btn-primary">+ Add Site</a>
        </div>
        
        <?php if (empty($sites)): ?>
        <div class="no-sites">
            <p>No sites added for this client yet.</p>
            <a href="create_site.php?client_id=<?php echo $client_id; ?>" class="btn btn-secondary" style="margin-top: 10px;">Add First Site</a>
        </div>
        <?php else: ?>
        <?php foreach ($sites as $site): 
            // Build admin login URL
            $admin_login_url = '';
            if ($site['admin_web_address'] && $site['admin_login']) {
                $admin_base = $site['admin_web_address'];
                if (strpos($admin_base, 'http') !== 0) {
                    $admin_base = 'http://' . $site['web_address'] . '/' . ltrim($admin_base, '/');
                }
                $admin_login_url = rtrim($admin_base, '/') . '/admin_login.php?operation=login&login=' . urlencode($site['admin_login']) . '&password=' . urlencode($site['admin_password']);
            }
        ?>
        <div class="site-card">
            <div class="site-url">
                <a href="http://<?php echo htmlspecialchars($site['web_address']); ?>" target="_blank"><?php echo htmlspecialchars($site['web_address']); ?></a>
                <?php if ($admin_login_url): ?>
                <a href="<?php echo htmlspecialchars($admin_login_url); ?>" target="_blank" class="btn-admin-login">&#128274; Admin Login</a>
                <?php endif; ?>
                <a href="create_site.php?site_id=<?php echo $site['site_id']; ?>" class="btn btn-outline" style="padding: 4px 10px; font-size: 11px;">Edit</a>
            </div>
            
            <div class="site-details">
                <?php if ($site['admin_login']): ?>
                <div class="site-detail">
                    <div class="site-detail-label">Admin Login</div>
                    <div class="site-detail-value">
                        <span><?php echo htmlspecialchars($site['admin_login']); ?></span>
                        <button type="button" class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($site['admin_login'], ENT_QUOTES); ?>')" title="Copy">&#128203;</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($site['admin_password']): ?>
                <div class="site-detail">
                    <div class="site-detail-label">Admin Password</div>
                    <div class="site-detail-value">
                        <span><?php echo htmlspecialchars($site['admin_password']); ?></span>
                        <button type="button" class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($site['admin_password'], ENT_QUOTES); ?>')" title="Copy">&#128203;</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($site['ftp_address']): ?>
                <div class="site-detail">
                    <div class="site-detail-label">FTP Host</div>
                    <div class="site-detail-value">
                        <span><?php echo htmlspecialchars($site['ftp_address']); ?></span>
                        <button type="button" class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($site['ftp_address'], ENT_QUOTES); ?>')" title="Copy">&#128203;</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($site['ftp_login']): ?>
                <div class="site-detail">
                    <div class="site-detail-label">FTP Login</div>
                    <div class="site-detail-value">
                        <span><?php echo htmlspecialchars($site['ftp_login']); ?></span>
                        <button type="button" class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($site['ftp_login'], ENT_QUOTES); ?>')" title="Copy">&#128203;</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($site['ftp_password']): ?>
                <div class="site-detail">
                    <div class="site-detail-label">FTP Password</div>
                    <div class="site-detail-value">
                        <span><?php echo htmlspecialchars($site['ftp_password']); ?></span>
                        <button type="button" class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($site['ftp_password'], ENT_QUOTES); ?>')" title="Copy">&#128203;</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($site['svn_domain']): ?>
                <div class="site-detail">
                    <div class="site-detail-label">SVN Repository</div>
                    <div class="site-detail-value">
                        <a href="svn?repository=<?php echo htmlspecialchars($site['svn_domain']); ?>" style="font-size: 12px; word-break: break-all;">svn://web1.sayu.co.uk/mnt/drive2/webclients/<?php echo htmlspecialchars($site['svn_domain']); ?></a>
                        <button type="button" class="copy-btn" onclick="copyText('svn://web1.sayu.co.uk/mnt/drive2/webclients/<?php echo htmlspecialchars($site['svn_domain'], ENT_QUOTES); ?>')" title="Copy">&#128203;</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($site['notes']): ?>
                <div class="site-detail" style="grid-column: 1 / -1;">
                    <div class="site-detail-label">Notes</div>
                    <div class="site-detail-value"><?php echo nl2br(htmlspecialchars($site['notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($site['tags'])): ?>
            <div class="site-tags">
                <?php foreach ($site['tags'] as $tag): ?>
                <a href="view_clients.php?ftag=[<?php echo urlencode($tag); ?>]&submit=1" class="tag"><?php echo htmlspecialchars($tag); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="toast" id="toast">Copied to clipboard!</div>

<script>
function copyField(btn) {
    const input = btn.previousElementSibling;
    const value = input.value || input.textContent;
    copyToClipboard(value, btn);
}

function copyText(text) {
    copyToClipboard(text, event.target);
}

function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast
        const toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2000);
        
        // Animate button
        if (btn) {
            btn.classList.add('copied');
            btn.innerHTML = '&#10003;';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = '&#128203;';
            }, 1500);
        }
    }).catch(err => {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        
        const toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2000);
    });
}
</script>

</body>
</html>
t>

</body>
</html>
