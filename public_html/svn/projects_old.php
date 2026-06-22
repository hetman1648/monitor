<?php

$root_inc_path = "../";
//include ("../includes/common.php");
//include ("./auth.php");



?>

<!DOCTYPE html>
<html>
  <head>
    <title>Sayu Web Clients Projects Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet" media="screen">
    <style>
    	#divRepositoryPath  {
    		font-size:10px;
    	}
      .taskBusy {
        background-color: #dff0d8;
      }
      .projectBusy {
        background-color: blue;
      }
    </style>
  </head>
  <body>

 <div class="container-fluid">
 
      <form class="form-signin">
      	<a href="../index.php" style="margin-top:5px;"><i class="icon-home"></i> Back to Monitor</a>
        <h2 class="form-signin-heading">Projects List</h2>
        <!-- Find a Repository: 
        <input type="text" class="span3" id="lstRepositories" autocomplete="off" value="<?php echo $monitor_svn_repository; ?>" style="margin: 0 auto; width:300px;" data-provide="typeahead" data-items="4" >
      -->
        
        <!--<blockquote class="pull-right">
      <p>It is extraordinary that whole populations have no projects for the future, none at all. It certainly is extraordinary, but it is certainly true.</p>
          <small><cite title="Source Title">Gertrude Stein</cite></small>
 </blockquote>-->

 <div class="btn-group pull-right" >
  <button class="btn">Show Calendar (2 weeks)</button>
  <button class="btn dropdown-toggle" data-toggle="dropdown">
    <span class="caret"></span>
  </button>
  <ul class="dropdown-menu pull-right">
    <!-- dropdown menu links -->
      <li><a href="#">Week</a></li>
      <li><a href="#">2 weeks</a></li>
      <li><a href="#">Month</a></li>
      <li><a href="#">2 months</a></li>
  </ul>
</div>
        <table class="table table-hover">
          <thead>
          <tr>
            <th>#</th>
            <th colspan=2>Project Name / Task Name</th>
            <th>Est.</th>
            <th>Status</th>
            <th>%</th>
            <th>Responsible</th>
            <th>Dependant</th>
            <th>26</th>
            <th>27</th>
            <th>28</th>
            <th>29</th>
            <th>30</th>
            <th>31</th>
            <th>1</th>
            <th>2</th>
            <th>3</th>
            <th>4</th>
            <th>5</th>
            <th>6</th>
            <th>7</th>
            <th>8</th>
            <th>9</th>
            <th>10</th>
          </tr>
          </thead>
          <tbody>
         
          <tr class="info">
            <td class="text-info" colspan=3>toysdirect.co.uk</td>
            <td>2 days</td>
            <td>In Progress</td>
            <td></td>
            <td>Ravi</td>
            <td></td>
            <td class="projectBusy"></td>
            <td class="projectBusy"></td>
            <td class="projectBusy"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
          <tr>
            <td>1</td>
            <td colspan=2>Build XML Site Map and extra chars</td>
            <td>1 day</td>
            <td>Not Started</td>
            <td>20%</td>
            <td>Olexiy Vlasov</td>
            <td></td>
            <td class="taskBusy"></td>
            <td class="taskBusy"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            
          </tr>
          <tr>
            <td>2</td>
            <td colspan=2>Changes to header</td>
            <td>4 hours</td>
            <td>Not Started</td>
            <td>0%</td>
            <td>Natalya Chikunova</td>
            <td><i class="icon-circle-arrow-right"></i> 1</td>
             <td></td>
            <td></td>
            <td class="taskBusy"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>


 <tr class="warning">
            <td class="text-info" colspan=3>TheFlashCentre.com</td>
            <td>4 days</td>
            <td>On Hold</td>
            <td></td>
            <td>Gavin</td>
            <td></td>
            <td class="projectBusy"></td>
            <td class="projectBusy"></td>
            <td class="projectBusy"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
          <tr>
            <td>3</td>
            <td colspan=2>HTML coding</td>
            <td>3 days</td>
            <td>Not Started</td>
            <td>0%</td>
            <td>Egor Syrodoev</td>
            <td></td>
            <td </td>
            <td ></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            
          </tr>
          <tr>
            <td>4</td>
            <td colspan=2>Changes to header</td>
            <td>4 hours</td>
            <td>Not Started</td>
            <td>0%</td>
            <td>Natalya Chikunova</td>
            <td></td>
             <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>


          </tbody>
        </table>



        <div id="divRepositoryPath"></div>
        <div id="divFilesList"></div>
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
    var defaultRepository = "<?php echo $monitor_svn_repository; ?>";
    $(document).ready(function() {  
    	
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
            $("#lstRepositories").removeClass("disabled");
            defaultRepository = $("#lstRepositories").val();
        });
		
	}
    
    </script>
  </body>
</html>