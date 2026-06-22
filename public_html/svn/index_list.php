<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

//getting available repositories list
$repositories = "";
//echo get_page("https://web1.sayu.co.uk/svn/index.php?action=show&username=artem&password=111116"); 
$path    = "https://web1.sayu.co.uk/svn/";
$command = "index.php?action=show&username=".$svn_login."&password=".$svn_password;

$command = "index.php?username=".$svn_login."&password=".$svn_password;

$res = get_page($path. $command); 
$monitor_svn_repository = GetParam("repository");
if (!$monitor_svn_repository) {
	if (isset($_COOKIE["monitor_svn_repository"])) {
		$monitor_svn_repository = $_COOKIE["monitor_svn_repository"];
	}
}

echo $res;
exit;


if (strpos($res,'+OK Repositories list') !== false) {
	$lines = explode("+OK Repositories list: ", $res);
	if (sizeof($lines)>1) {
		$repositories_list  = explode("\n", $lines[1]);
		$repositories_strk  = "";
		$repositories_typehead = "";

		
		foreach ($repositories_list as $repository) {
			if (strlen($repository)) {
		/*		if ($monitor_svn_repository == $repository) {
					$repositories_strk .= "<OPTION SELECTED value='$repository'>$repository</OPTION>";
				} else {
					$repositories_strk .= "<OPTION value='$repository'>$repository</OPTION>";
				}
		*/
        if (strlen($repositories_typehead)) $repositories_typehead .= ",";
        //$repositories_typehead .= "&quot;".trim($repository)."&quot;";
        $repositories_typehead .= '"'.trim($repository).'"';
				
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
    		font-size:11px;
    	}
        #btnDevelopers,#bntHistory {
            float:right;
            margin-right: 10px;
        }
        #divProgress, #divAlert {
          display: none;
        }
        
    </style>
  </head>
  <body>

 <div class="container">
 
      <form class="form-signin">
      	<a href="../index.php" style="margin-top:5px;"><i class="icon-home"></i> Back to Monitor</a>
        <h2 class="form-signin-heading">SVN Updater</h2>
        Find a Repository: 
        <input type="text" class="span3" id="lstRepositories" autocomplete="off" value="<?php echo $monitor_svn_repository; ?>" style="margin: 0 auto; width:300px;" data-provide="typeahead" data-items="4" >
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

<div id="modalDevelopers" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
    <h3>Developers Tools</h3>
  </div>
  <div class="modal-body">
    <p><i class="icon-wrench"></i>Automatically download recent database and images from Sayu Hosting servers
        to Sayu Kyiv Development server.
        <p><small>Please note: before download you have to make SVN checkout to Development server</small></p>


        <form class="form-horizontal" role="form" id="frmDownload">          
          <div class="form-group">
            <div class="col-lg-offset-2 col-lg-10">
              <div class="checkbox">
                <label>
                  <input type="checkbox" id="chkDownloadDB" checked> Download Project Database (<span id="spanDBSize"></span>)
                </label>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-lg-offset-2 col-lg-10">
              <div class="checkbox">
                <label>
                  <input type="checkbox" id="chkDownloadImages" checked> Download Images Folder (<span id="spanImagesSize"></span>)
                </label>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-lg-offset-2 col-lg-10">
              <button type="submit" class="btn btn-primary" id="btnStartDownload">Start Download</button>

            </div>
          </div>
        </form>
      
      <div class="progress progress-striped active" id="divProgress">
        <div class="bar" style="width: 100%;"></div>
      </div>

        
      <div class="alert" id="divAlert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <div id="divDownloadInfo"></div>
      </div>
        
    </p>
  </div>
  <div class="modal-footer">
    <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
  </div>
</div>
      
    </div> <!-- /container -->

    <script src="https://code.jquery.com/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
    var defaultRepository = "<?php echo $monitor_svn_repository; ?>";
    $(document).ready(function() {  
    	$('#lstRepositories').typeahead({
    	    source: [<?php echo $repositories_typehead; ?>],
        	updater:function (item) {
    	        get_recent_files(item);

            	return item; //returning the repository
        	}
    	});    

        $("#lstRepositories").focus(function() {
            var repository = $("#lstRepositories").val();
            if (repository == defaultRepository) {
                $("#lstRepositories").val("");
            }
        });

    	$("#lstRepositories2").change(function() {
    		//alert("aaa");
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
    $('#modalDevelopers').on('show', function () {
      $("#divAlert").hide();
      repository = $("#lstRepositories").val();
          $.post("hosting_get_sizes.php", {project:repository}, function(xml) {
            //$("#infoBox").html(xml);
            console.log(xml);
            var res = jQuery.parseJSON(xml);
            $("#spanDBSize").html(res.db_size);
            $("#spanImagesSize").html(res.images_size);

            
            //alert(res.db_size);
          });
    })      

    $("#frmDownload").submit(function(){
       var url        = "hosting_download.php";
       repository     = $("#lstRepositories").val();
       var is_db      = $('#chkDownloadDB').is(':checked');
       var is_images  = $('#chkDownloadImages').is(':checked');
       //alert(is_images);
       $("#divAlert").hide();
       $("#btnStartDownload").addClass("disabled");
       $("#divProgress").fadeIn();
       $.get(url, {project:repository,is_db:is_db,is_images:is_images}, function(xml) {          
         //alert-error
         if (xml.substring(0, 4) == "-ERR") {
            $("#divAlert").addClass("alert-error");
         } else {
            $("#divAlert").removeClass("alert-error");
         }
 


          $("#divDownloadInfo").html(xml);
          $("#divProgress").hide();
          $("#divAlert").fadeIn();
          $("#btnStartDownload").removeClass("disabled");

       });
       return false;
    });

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

	function get_recent_files(rep) {
		var repository = rep;
		if (!repository) repository = $("#lstRepositories").val();
		
		var repositoryPath = "<b>Repository path:</b> svn://web1.sayu.co.uk/mnt/drive2/webclients/" + repository;
		$("#divRepositoryPath").html(repositoryPath);
        $("#lstRepositories").addClass("disabled");		

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

            $("#btnDevelopers").click(function(){
                $("#modalDevelopers").modal();
                //alert("aaa");
                return false;
            });


            $("#lstRepositories").removeClass("disabled");
            defaultRepository = $("#lstRepositories").val();
        });
		
	}
    
    </script>
  </body>
</html>