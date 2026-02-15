<?php

$is_front_end = false;

include 'header.php';

?>

<div class="container">
  <ul class="nav nav-tabs">
    <li ><a href="#">Setup</a></li>
    <li class="active"><a href="#">Link to Amazon</a></li>
    <li><a href="#">Import products from Amazon</a></li>
  </ul>

    <h1>Link your Amazon account with TemaMetrics</h1>
    <div class="bs-callout bs-callout-warning">
      <p>To link your Amazon account with TemaMetrics please follow steps below:
        </p>
    </div>

    <ol>
          <li>Login to Amazon Marketplace Web Service</li>
          <li>Choose "I want to use an application to access my Amazon seller account with MWS"
          <li>Populate Amazon form with these two fields:
            <table class="table">
                <tr>
                  <td>Application Name:</td>
                  <td><code>temametrics</code></td>
                </tr>
                <tr>
                    <td>EU Application's Developer Account Number:</td>
                    <td><code>5682-3289-3232</code> (Applicable to UK, Germany, France)</td>
                </tr>
            </table>
           <li>When you click "Next" Amazon will display your "Merchant ID" value.
            Fill it in the field below "Merchant ID".   
          </ol>
        

  <form role="form" id="frmLinkAccount">
  <div class="form-group">
    <label for="exampleInputEmail1">Merchant ID</label>
    <input type="text" class="form-control" id="exampleInputEmail1" placeholder="Enter Merchant ID obtained from Amazon MWS">
  </div>
  <button type="submit" id="lnkLinkAccount" class="btn btn-primary">Link Your Account</button>
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
  $("#frmLinkAccount").submit(function() {
    window.location = "step2.php";
    return false;
  });

 });

</script>
