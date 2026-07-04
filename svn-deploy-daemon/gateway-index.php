<? 
    if ($_GET["action"] == "") {
	echo "<b>to create svn user send:</b> " . basename(__FILE__) . "?action=createuser&username=&lt;your_username&gt;&password=&lt;your_password&gt;&newuser=&lt;new_username&gt;&newpassword=&lt;new_password&gt;&subdomain=&lt;associated_subdomain&gt;&usertype=&lt;admin|developer&gt;<br><br>";
	echo "<b>to change password for user send:</b> " . basename(__FILE__) . "?action=passwd&username=&lt;your_username&gt;&password=&lt;your_password&gt;&chusername=&lt;username_to_change&gt;&chpassword=&lt;new_password&gt;<br><br>";
	echo "<b>to change password for current user send:</b> " . basename(__FILE__) . "?action=mypasswd&username=&lt;your_username&gt;&password=&lt;your_password&gt;&chpassword=&lt;new_password&gt;<br><br>";
	echo "<b>to show user's permissions:</b> " . basename(__FILE__) . "?action=shperm&username=&lt;your_username&gt;&password=&lt;your_password&gt;&shusername=&lt;username_to_show_permissions&gt;<br><br>";
	echo "<b>to show current user permissions:</b> " . basename(__FILE__) . "?action=shmyperm&username=&lt;your_username&gt;&password=&lt;your_password&gt;<br><br>";
	echo "<b>to show all users:</b> " . basename(__FILE__) . "?action=showusers&username=&lt;your_username&gt;&password=&lt;your_password&gt;<br><br>";
	echo "<b>to make checkout from repository:</b> " . basename(__FILE__) . "?action=checkout&username=&lt;username&gt;&password=&lt;password&gt;&repository=&lt;repository_name&gt;&path=&lt;additional_path&gt;<br><br>";
	echo "<b>to show all available repositories:</b> " . basename(__FILE__) . "?action=show&username=&lt;username&gt;&password=&lt;password&gt;<br><br>";
	echo "<b>to see repository state:</b> " . basename(__FILE__) . "?action=state&username=&lt;username&gt;&password=&lt;password&gt;&repository=&lt;repository&gt;<br><br>";
	echo "<b>to show repository updates:</b> " . basename(__FILE__) . "?action=showupdates&username=&lt;username&gt;&password=&lt;password&gt;&repository=&lt;repository&gt;<br><br>";
	echo "<b>to show last 50 errors messages from log:</b> " . basename(__FILE__) . "?action=shlasterr&username=&lt;username&gt;&password=&lt;password&gt;&repository=&lt;repository&gt;[&start_date_time=&lt;timestamp&gt;]<br><br>";
	echo "<b>to show last critical errors messages from log:</b> " . basename(__FILE__) . "?action=shcriterr&username=&lt;username&gt;&password=&lt;password&gt;&repository=&lt;repository&gt;[&start_date_time=&lt;timestamp&gt;]<br><br>";
	echo "<b>to show cron jobs of client:</b> " . basename(__FILE__) . "?action=showcrons&username=&lt;username&gt;&password=&lt;password&gt;&repository=&lt;repository&gt;<br><br>";
	echo "<b>to show DB backups of client:</b> " . basename(__FILE__) . "?action=shdbbackup&username=&lt;username&gt;&password=&lt;password&gt;&repository=&lt;repository&gt;<br><br>";
    }
    else {
	if ($_GET["action"] == "createuser") talk_to_daemon("LOCAL", "Createuser<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "passwd") talk_to_daemon("LOCAL", "Passwd<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "shperm") talk_to_daemon("LOCAL", "Show permissions<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "shmyperm") talk_to_daemon("LOCAL", "Show MY permissions<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "mypasswd") talk_to_daemon("LOCAL", "Change MY password<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "showusers") talk_to_daemon("LOCAL", "Show all users<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "shlasterr") talk_to_daemon("LOCAL", "Show last 50 errors messages from log<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "shcriterr") talk_to_daemon("LOCAL", "Show last critical errors messages from log<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "showcrons") talk_to_daemon("LOCAL", "Show installed cron jobs<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "shdbbackup") talk_to_daemon("LOCAL", "Show available DB backups<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "show") talk_to_daemon("LOCAL", "Show<br>", $_SERVER["QUERY_STRING"], $_GET);

	if ($_GET["action"] == "checkout") talk_to_daemon("VARIABLE", "Checkout<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "state") talk_to_daemon("VARIABLE", "State<br>", $_SERVER["QUERY_STRING"], $_GET);
	if ($_GET["action"] == "showupdates") talk_to_daemon("VARIABLE", "Show repository updates<br>", $_SERVER["QUERY_STRING"], $_GET);

    }

    function talk_to_daemon($type, $message, $request, $get) {
	echo $message;

	if ($type == "LOCAL") {
	    $sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
	    socket_connect($sock,"/var/run/sayu-svn/sayu-svn.sock");
	    $resp = socket_read($sock, 16384);
	    if (preg_match('/SayuSvn daemon ready.../',$resp)) {
		if (socket_write($sock, $request . "\n")) {
		    $resp = socket_read($sock, 16384);
		    if (preg_match('/\+OK /', $resp)) echo "Resumed normal operation. Server response is: $resp";
		    else echo "Client-Server talking error: $resp";
		}
		else echo "Can't write to socket<br>";
	    }
	    else echo "Can't read socket<br>";
	    socket_close($sock);
	}

	if ($type == "VARIABLE") {
	    $db = mysqli_init();
	    mysqli_real_connect($db, "localhost", "root", getenv("FTPDB_ROOT_PW"), "ftpdb"  /* real password redacted for git; see deployed /var/secure_www/svn/index.php */);
	    $sql  = "SELECT repository_connections.* FROM repository_connections INNER JOIN http_users_data ON (repository_connections.server_id=http_users_data.repository_id) ";
	    $sql .= "WHERE http_users_data.hostname='" . ($get["repository"]) . "';";
	    $result = mysqli_query($db, $sql);
	    $config = mysqli_fetch_all($result, MYSQLI_ASSOC);
	    mysqli_free_result($result);
	    mysqli_close($db);
	    if ($config[0]["server_type"] == "UNIX") {
		$sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
		socket_connect($sock, $config[0]["server_port"]);
		$resp = socket_read($sock, 16384);
		if (preg_match('/SayuSvn daemon ready.../',$resp)) {
		    if (socket_write($sock, $request . "\n")) {
			$resp = socket_read($sock, 16384);
			if (preg_match('/\+OK /', $resp)) echo "Resumed normal operation. Server response is: $resp";
			else echo "Client-Server talking error: $resp";
		    }
		    else echo "Can't write to socket<br>";
		}
		else echo "Can't read socket<br>";
		socket_close($sock);
	    }
	    if ($config[0]["server_type"] == "TCP") {
		$sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname("tcp"));
		socket_connect($sock, $config[0]["server_ip"], $config[0]["server_port"]);
		$resp = socket_read($sock, 16384);
		if (preg_match('/SayuSvn daemon ready.../', $resp)) {
		    if (socket_write($sock, $request . "\n")) {
			$resp = socket_read($sock, 16384);
			if (preg_match('/\+OK /', $resp)) echo "Resumed normal operation. Server response is: $resp";
			else echo "Client-Server talking error: $resp";
		    }
		    else echo "Can't write to socket<br>";
		}
		else echo "Can't read socket<br>";
		socket_close($sock);
	    }
	}
    }
?>
