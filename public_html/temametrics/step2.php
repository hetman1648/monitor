<?php

$is_front_end = false;

include 'header.php';

?>

<div class="container">
  <ul class="nav nav-tabs">
    <li ><a href="#">Setup</a></li>
    <li><a href="#">Link to Amazon</a></li>
    <li class="active"><a href="#">Import products from Amazon</a></li>
  </ul>

    <h1>Import Products</h1>
    <div class="bs-callout bs-callout-warning">
      <p>We have successfully linked your Amazon MWS account with TemaMetrics
        The Import Products proccess has been started - usually it takes 5-10 minutes to 
        get your products from Amazon into TemaMetrics
        You will recieve an email notification when products are imported.
        <div class="progress progress-striped active">
          <div class="progress-bar"  role="progressbar" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100" style="width: 45%">
            <span class="sr-only">45% Complete</span>
          </div>
        </div>
        </p>
    </div>
  
</div>


</body>
</html>

 <script src="js/jquery.js"></script>
 <script src="js/bootstrap.min.js"></script>
 <script src="js/typeahead.min.js"></script>
    
<script>
 $(document).ready(function() {  
 	//get_tasks();

 });

</script>
