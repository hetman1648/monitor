<?php

$is_front_end = 1;
include 'header.php';

?>

<div class="container">
    <h1>Free Trial</h1>
    <p>An easy way to change prices on Amazon, just add your Amazon Marketplace account details 
    and we'll do the rest for you

  <form role="form" id="frmSignUp">
  <div class="form-group">
    <label for="exampleInputEmail1">Your Name</label>
    <input type="text" class="form-control" style="width:400px" id="exampleInputEmail1" placeholder="Enter your name">
  </div>
  <div class="form-group">
    <label for="exampleInputEmail1">Email address</label>
    <input type="email" class="form-control" style="width:400px" id="exampleInputEmail1" placeholder="Enter email">
  </div>
  <button type="submit" id="btnStartTrial" class="btn btn-primary">Start Trial</button>
</form>
  
</div>


</body>
</html>

 <script src="js/jquery.js"></script>
 <script src="js/bootstrap.min.js"></script>
 <script src="js/typeahead.min.js"></script>
    
<script>
 $(document).ready(function() {  
 	//get_tasks();
    $("#frmSignUp").submit(function(){
      window.location = "step1.php";
      return false;
    });

 });

</script>
