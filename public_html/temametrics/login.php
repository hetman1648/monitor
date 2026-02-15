<?php

$is_front_end = 1;
include 'header.php';

?>

<div class="container">
    <h1>Login</h1>
    
    
    <form role="form" id="frmLogin">
      <div class="form-group">
        <label for="exampleInputEmail1">Email address</label>
        <input type="email" class="form-control" style="width:400px;" id="exampleInputEmail1" placeholder="Enter email">
      </div>
      <div class="form-group">
        <label for="exampleInputPassword1">Password</label>
        <input type="password" class="form-control" style="width:400px;" id="exampleInputPassword1" placeholder="Password">
      </div>
      <div class="checkbox">
        <label>
          <input type="checkbox"> Remember Me
        </label>
      </div>
      <button type="submit" class="btn btn-primary">Login</button>
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
    $("#frmLogin").submit(function(){
      window.location = "products.php";
      return false;
    });

 });

</script>
