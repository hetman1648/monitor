<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");
	CheckSecurity(1);
	$t = new iTemplate($sAppPath);
	$t->set_file("main","document_view.html");

	$doc_id = GetParam("doc_id");
	$ver = GetParam("ver");
	$session_user_id = GetSessionParam("UserID");	
	$error = "";
	$file_name = "";
	
	$user_allow_edit = true;
	$user_allow_view = true;
	$doc = array();
	if($doc_id>0) {
		$sql = "SELECT * FROM docs WHERE doc_id=".ToSQL($doc_id, 'integer');
		$db->query($sql);
		if ($db->next_record()) {
			$doc_id = $db->f("doc_id");
			$doc = $db->Record;
		} else {
			$error = "<BR>Such document doesn't exist";
		}
		
		if (isset($doc) && sizeof($doc)) {			
			$user_allow_view = is_allowed($session_user_id, $doc["author_id"], get_set_array($doc["allow_view"], $permission_groups));
			if (!$user_allow_view) {
				$error.="<BR>You are not allowed to view this document";
			} else {
				if ($ver>0) {
					//select version file
					$sql = " SELECT file_name, user_file_name FROM docs_versions ";
					$sql.= " WHERE doc_id=".ToSQL($doc_id, "integer")." AND version_number=".ToSQL($ver, "number");
					$db->query($sql);
					if ($db->next_record()) {
						$file_name = $db->f("file_name");
						$user_file_name = $db->f("user_file_name");
					} else {
						$error.="<BR>Specified version of document doesn't exist";						
					}
				} else {
					$file_name = $doc["file_name"];
					$user_file_name = $doc["user_file_name"];
				}
				
				if ($file_name && !$error) {
					$resource = @fopen($doc_path.$file_name, "r");
					if (!$resource) {
						$error.="<BR>File can't be downloaded";
					}
				}
			}
		}
	}
	
    if ($error) {
    	$t->set_var("error_message",$error);
    	$t->parse("error", false);
		$t->pparse("main", false);
	} else {
		$content_type = "application/octet-stream";
		$ext = strtolower(substr($user_file_name, strrpos($user_file_name, '.') + 1));
		switch($ext) {
			case "pdf":	$content_type="application/pdf"; break;
			case "gif":	$content_type="image/gif"; break;
			case "bmp":	$content_type="image/bmp"; break;
			case "png":	$content_type="image/png"; break;
			case "jpg":
			case "jpeg":
			case "jpe": $content_type="image/jpeg"; break;
			case "tiff":$content_type="image/tiff"; break;
			case "doc":	$content_type="application/msword"; break;
			case "rtf":	$content_type="text/rtf"; break;
			case "xls":	$content_type="application/x-excel"; break;
			case "pff":	$content_type="application/ms-powerpoint"; break;
			case "zip":	$content_type="application/zip"; break;
			case "ppt":	$content_type="application/vnd.ms-powerpoint"; break;
			case "js":	$content_type="application/x-javascript"; break;
			case "txt":	$content_type="text/plain"; break;
			case "css":	$content_type="text/css"; break;
			case "php": $content_type="application/x-httpd-php"; break;
			case "htm":
			case "html":$content_type="text/html"; break;
			case "tgz":
			case "tar": $content_type="application/x-tar"; break;
			case "djvu":
			case "djv": $content_type="image/vnd.djvu"; break;
		}
		
		header('Content-Description: File Transfer');
		//header('Content-Type: application/force-download');
		header("Content-Type: ".$content_type);		
		header('Content-Length: ' . filesize($doc_path.$file_name));
		header('Content-Disposition: attachment; filename="'.$user_file_name.'"');
    	fpassthru($resource);
    	exit;
    }
?>