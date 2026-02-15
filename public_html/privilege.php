<?php

	include("./includes/common.php");

	CheckSecurity(3);

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}
	
	$post_array = array();
	if (isset($HTTP_POST_VARS)) {
		$post_array = $HTTP_POST_VARS;
	} elseif(isset($_POST)) {
		$post_array = $_POST;
	}	

	//$= GetParam("");//
	$pid	= GetParam("pid");
	$operation	= GetParam("operation");
	$privilege_desc	= GetParam("privilege_desc");

	if ($operation == "submit" ) {
		if ($pid) {			
			$update = "privilege_desc='$privilege_desc'";
			foreach($perms as $key=>$value) {
				if ($update) { $update .= ",";}
				if (isset($post_array[$key])) { $update .= " $key=1 ";}
					else				   { $update .= " $key=0 ";}
			}
			$sql = "UPDATE lookup_users_privileges SET $update WHERE privilege_id=$pid";
			$db->query($sql,__FILE__,__LINE__);
		}
		else {
			$insert_fields = "privilege_desc";
			$insert_values = "'$privilege_desc'";

			foreach($perms as $key=>$value) {
				if ($insert_fields) {
					$insert_fields.= ","; $insert_values.= ",";
				}

				if (isset($post_array[$key])) {
					$insert_fields .= "$key"; $insert_values .= "1";
				}
				else {
					$insert_fields .= "$key"; $insert_values .= "0";
				}
			}
			$sql = "INSERT INTO lookup_users_privileges ($insert_fields) VALUES ($insert_values)";
			$db->query($sql,__FILE__,__LINE__);
		}
				
		header("Location: users.php");
		exit;
	}

	//  if (GetSessionParam("privilege_id") == 7)
	$T = new iTemplate("./templates",array("page"=>"privilege.html"));
	$T->set_var("privilege_desc",$privilege_desc);

	//-- privilege details
	$assigned = array();
	if ($pid) {
		$sql = "SELECT * FROM lookup_users_privileges WHERE privilege_id=$pid";
		$db->query($sql,__FILE__,__LINE__);
		if($db->next_record()) {
        	$T->set_var($db->Record);
        	$assigned = $db->Record;
        }
	}
		
	foreach($perms as $perm_name=>$val) {
		$T->set_var("privilege_name",$perm_name);
		$T->set_var("privilege_title",$val);
		
		if (array_key_exists($perm_name,$assigned) && $assigned[$perm_name]>0)	{
			$T->set_var("checked","checked");
		}else { $T->set_var("checked","");}
		$T->parse("list",true);
	}

	$T->set_var("user_name",GetSessionParam("UserName"));
	$T->set_var("pid",$pid);
	$T->pparse("page");

?>