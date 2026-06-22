<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

//getting available repositories list
$repositories = "";
//echo get_page("https://web1.sayu.co.uk/svn/index.php?action=show&username=artem&password=111116"); 
$path    = "https://web1.sayu.co.uk/svn/";
$command = "index.php?action=show&username=".$svn_login."&password=".$svn_password;
$res = get_page($path. $command); 
$monitor_svn_repository = GetParam("repository");
if (!$monitor_svn_repository) {
	if (isset($_COOKIE["monitor_svn_repository"])) {
		$monitor_svn_repository = $_COOKIE["monitor_svn_repository"];
	}
}

if (strpos($res,'+OK Repositories list') !== false) {
	$lines = explode("+OK Repositories list: ", $res);
	if (sizeof($lines)>1) {
		$repositories_list  = explode("\n", $lines[1]);
		$repositories_strk  = "";
		foreach ($repositories_list as $repository) {
			if (strlen($repository)) {
				if ($monitor_svn_repository == $repository) {
					$repositories_strk .= "<OPTION SELECTED value='$repository'>$repository</OPTION>";
				} else {
					$repositories_strk .= "<OPTION value='$repository'>$repository</OPTION>";
				}
			}
		}
		
	} else die ("No repositories available");
} else {
	die ("ERROR:Can't get a repository list:".$res);
}

 


?>

<!DOCTYPE html>
<html>
  <head>
    <title>Sayu hosting SVN Updater</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet" media="screen">
    <style>
    	#divRepositoryPath  {
    		font-size:10px;
    	}
    </style>
  </head>
  <body>

 <div class="container">
 
      <form class="form-signin">
      	<a href="../index.php" style="margin-top:5px;"><i class="icon-home"></i> Back to Monitor</a>
        <h2 class="form-signin-heading">SVN Updater</h2>
        Choose a Repository: 
        <select id="lstRepositories"><?php echo $repositories_strk; ?></select>
        <div id="divRepositoryPath"></div>
        <div id="divFilesList"></div>
        <button class="btn btn-large btn-block btn-primary" id="btnUpdate" type="button">Update Site Now</button>
      </form>

      

<!-- Button to trigger modal -->
<!--<a href="#myModal" role="button" class="btn" data-toggle="modal">Launch demo modal</a>-->
 
<!-- Modal -->
<div id="myModal" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
    <h3 id="myModalLabel">History</h3>
  </div>
  <div class="modal-body">
    <p id="infoBox"></p>
  </div>
  <div class="modal-footer">
    <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
  </div>
</div>
      
    </div> <!-- /container -->

    <script src="https://code.jquery.com/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {  
    	$("#lstRepositories").change(function() {
			var repositoryPath = "<b>Repository path:</b> svn://web1.sayu.co.uk/mnt/drive2/webclients/" + $("#lstRepositories").val();
			$("#divRepositoryPath").html(repositoryPath);
    	
    		get_recent_files();
    	});

		$('#myModal').on('show', function () {
			repository = $("#lstRepositories").val();
        	$.post("history.php", {repository:repository}, function(xml) {
        		$("#infoBox").html(xml);
        	});    		
		})    	

    	$("#btnUpdate").click(function() {
    		repository = $("#lstRepositories").val();
    		if (confirm("Are you sure you want to update "+repository+" with a working copy from SVN repository")) {
        		$("#btnUpdate").addClass("disabled");
        		$.post("update_repository.php", {repository:repository}, function(xml) {
        			$("#btnUpdate").removeClass("disabled");
        			//alert(xml);
        			get_recent_files();
        		});    		
        	}	
    	});
    	
    	get_recent_files();
    	var int = setInterval(function(){
    		get_recent_files();
    	},20000); //20secs
	});

	function get_recent_files() {
		repository = $("#lstRepositories").val();
		$.post("get_recent_files.php", {repository:repository}, function(xml) {
			$("#divFilesList").html(xml);
			filesNumber =  $("#hdnFilesNumber").val();
			if (filesNumber) {
				if (filesNumber ==1) $("#btnUpdate").text("Update Site (" + filesNumber + " file)");
				else $("#btnUpdate").text("Update Site ("+ filesNumber +" files)");
				$("#btnUpdate").removeClass("disabled");
			} else {
				$("#btnUpdate").text("Nothing to update");
				$("#btnUpdate").addClass("disabled");
			}
        });
		
	}
    
    </script>
  </body>
</html>