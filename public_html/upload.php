<?
	include ("./includes/common.php");

	CheckSecurity(1);

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}

	$session_now = session_id();

	$temp_path = "./temp_attachments/";
	$handle=opendir($temp_path);

	$hash 	= GetParam("hash") ? GetParam("hash") : "00000000";

	$action = GetParam("action");

	//----------------- get_nice_bytes
	function get_nice_bytes($bytes) {
	    if ($bytes >= 1024 && $bytes<1048576) return floor($bytes/1024) . "kB";
	    else if ($bytes >= 1048576) return floor($bytes/1048576) . "mB";
	    else return $bytes."B";
	}
	//--------------------------------

	if (($action == "delete") && $_REQUEST["file_name"]) {
	    $cur_file = $temp_path.strval($session_now).$hash.$_REQUEST["file_name"];

	    if (file_exists($cur_file)) {	    	unlink($cur_file);
	    }
	}

	if (($action == "upload_file") && isset($_FILES["user_file"])) {

		$user_file_name = strval($session_now) . GetParam("hash") . strtolower($_FILES["user_file"]["name"]);
		$cur_file = substr($user_file_name,strlen($session_now)+8);

		if (file_exists($_FILES["user_file"]["tmp_name"])) {
		    if (!file_exists($temp_path . $user_file_name)) {
			    if (copy($_FILES["user_file"]["tmp_name"], $temp_path.$user_file_name)) {
			    	$message = "<br><font color='navy'>File <b>$cur_file</b> has been successfully uploaded.</font><br>";
			  		$user_file_size = filesize($temp_path.$user_file_name);
			    }else {			    	$upload_errors = "Errors during upload. Please upload again.";
				}
		    }else {		    	$upload_errors = "File <b>$cur_file</b> can't be created. <br>Check permissions.";
		    }
	    }else {	    	$upload_errors = "File <b>$cur_file</b> already exists. <br>Please rename your file and upload it again.";
	    }

	    if ($upload_errors) $message = "<br><font color='red'>$upload_errors</font>";
	}

	$t = new iTemplate("./templates");
	$t->set_file("main","upload.html");

	// hash
	$t->set_var("hash",strval($hash));

	$t->set_var("form_errors",$form_errors);
	$t->set_var("message_id",$message_id);

	$total_size = 0;

	$filelist = "";

	if ($file = readdir($handle)) {
	    $i = 1;
	    $attached_files ="";
		//    $script_path = substr($PATH_INFO,0,strlen($PATH_INFO) - 9);
	    do {
	    	$cur_file = substr($file,strlen($session_now)+8);

		    if ($file != "." && $file != ".." &&
		    		(strval($session_now) == substr($file,0,strlen($session_now))) &&
		    		strval($hash)==substr($file,strlen($session_now),8)  ) {
			    $file_size 	= filesize($temp_path.$file);
			    $filelist[]	=$cur_file;

			    $t->set_var("file_name",$cur_file);
			    $t->set_var("file_size",get_nice_bytes($file_size));
			    $attached_files.="<img src='images/attach.gif' border=0>&nbsp;".$cur_file."&nbsp;".get_nice_bytes($file_size)."</a><br>";
			    $t->parse("attachments",true);
			    $i++;
			    $total_size += $file_size;
		    }
	    } while ($file = readdir($handle));
	    closedir($handle);
	 }

	if($i==1) { $t->set_var("attachments",""); }
		else { $attached_files="<b>Attached files:</b><br>".$attached_files; }

	$t->set_var("total_files",$i-1);
	$t->set_var("total_size",get_nice_bytes($total_size));
	$t->set_var("message_number",$message_number);
	$t->set_var("attached_files",$attached_files);
	//$t->set_var("files_name",$cur_file);
	if (count($filelist)>0){		$t->set_var("files_name", @implode("][",$filelist) );// . ((count($filelist)>1)?"]":"")
	} else {		$t->set_var("files_name","");
	}

	$t->set_var("remaining_space",get_nice_bytes((7*1024*1024) - $total_size));
	$t->set_var("message",$message);

	$t->pparse("main");
?>