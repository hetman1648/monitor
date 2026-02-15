<?php
	include("./includes/common.php");
		
	CheckSecurity(1);
	
	$fp = fopen('cache/hidden_santa.log', "r");
	$contents = fread($fp, filesize('cache/hidden_santa.log'));
	fclose($fp);
	
	if ($contents) {
		$hidden_santa_users = unserialize($contents);		
	} else {
		$sql  = " SELECT user_id, first_name, last_name ";
		$sql .= " FROM users ";
		$sql .= " WHERE (is_deleted IS NULL OR is_deleted =0) AND is_viart=1";
		$sql .= " AND user_id NOT IN (60,118,105,34)";
		$db->query($sql, __FILE__, __LINE__);
		
		$hidden_santa_users = array();
		$hidden_santa_users_ids = array();
		while($db->next_record()) {
			$hidden_santa_users[$db->f("user_id")]["name"] = $db->f("first_name") . " " . $db->f("last_name");
			$hidden_santa_users_ids[$db->f("user_id")] = $db->f("user_id");
		}	
		
		do {
			shuffle($hidden_santa_users_ids);
			$errors = false;
			$i = 0;
			foreach ($hidden_santa_users AS $user_id => $user) {
				$hidden_santa_users[$user_id]['my_santa_is'] = $hidden_santa_users_ids[$i];
				$hidden_santa_users[$hidden_santa_users_ids[$i]]['is_santa_of'] = $user_id;
				if ($hidden_santa_users_ids[$i] == $user_id ){
					$errors = true;
					break;
				}
				$i++;
			}	
		} while ($errors);
		
		$fp = fopen('cache/hidden_santa.log', 'w+');
		fwrite($fp, serialize($hidden_santa_users));
		fclose($fp);	
	}	
	
	echo "<table style='font-size:20px' cellpadding='0px' cellspacing='0px'><tr>";
	$i = 0;
	foreach ($hidden_santa_users AS $user_id => $user) {
		$i++;
		echo "<td height=100px width=250px valign='middle' align='center' style='border:1px solid black'>";
		echo $hidden_santa_users[$user['my_santa_is']]["name"];
		echo "</td>";
		if ($i % 3 == 0) {
			echo '</tr><tr>';
		}
	}
	echo "</tr></table>";
	exit;
	
	echo "<table>";
	foreach ($hidden_santa_users AS $user_id => $user) {
		echo "<tr><td>"  . $hidden_santa_users[$user['my_santa_is']]["name"];
		echo "</td><td>-></td><td>" . $hidden_santa_users[$user_id]["name"];
		echo "</td><td>-></td><td>" . $hidden_santa_users[$user['is_santa_of']]["name"];
		echo "</td></tr>";
	}
	echo "</table>";
	
	$session_user_id = GetSessionParam("UserID");
	
	echo "Im hidden Santa of " . $hidden_santa_users[$hidden_santa_users[$session_user_id]['is_santa_of']]['name'];
	
	
?>