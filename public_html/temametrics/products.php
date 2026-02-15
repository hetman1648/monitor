<?php

$is_front_end = false;

include 'header.php';

?>

<div class="container">
  
    <h1>Products</h1>
    <div class="bs-callout bs-callout-info">
      <p>There are <code>1850</code> products in your Amazon account      
        To start repricing process please click set "Max/Min Prices" below  
      </p>
    </div>
    
    <!-- <a href="" class="btn btn-primary">Set Max/Min Prices</a> -->
    
    <!--<ul class="nav nav-tabs">
      <li class="active"><a href="#">All Products</a></li>
      <li><a href="#">Repricing Settings</a></li>
      <li><a href="#">Messages</a></li>
    </ul>
  -->

 <!-- Button trigger modal -->
  <a data-toggle="modal" href="#myModal" class="btn btn-primary ">Set Max/Min Prices</a>

  <!-- Modal -->
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title">Max/Min Prices</h4>
        </div>
        <div class="modal-body">
            <p>To change products prices Temametrics needs you to set Maximum and Minimum
              prices for each product. These settings will be applied to all your products:</p>
            <form class="form-horizontal" role="form">
              <div class="form-group">
                <label for="inputEmail1" class="col-lg-4 control-label">Minimum Price</label>
                <div class="col-lg-4">
                  <input type="text" class="form-control" id="inputEmail1" placeholder="% or fixed value">
                </div>
              </div>
              <div class="form-group">
                <label for="inputPassword1" class="col-lg-4 control-label">Maximum Price</label>
                <div class="col-lg-4">
                  <input type="text" class="form-control" id="inputPassword1" placeholder="% or fixed value">
                </div>
              </div>
             </form>
                 <div class="bs-callout bs-callout-info">
                    For example: a product has price <code>&pound;10.00</code> on Amazon, your minumum price is <code>&pound;9.00</code> and the maximum price is <code>&pound;11.00</code>
                </div>

          


        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary">Save changes</button>
        </div>
      </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
  </div><!-- /.modal -->


<table class="table">
      <thead>
        <th><input type=checkbox></th>
        <th>Status</th>
        <th>Merchant SKU</th>
        <th>ASIN/ISBN</th>
        <th>Product Name</th>
        <th>Your Price</th>
        <th>Min/Max Price</th>
        <th>Buy Box Price</th>
        <th>Lowest Price</th>
        <th>Channel</th>
      </thead>
      <tbody>
      <tr>
        <td><input type=checkbox></td>
        <td>Repricing</td>
        <td>13129</td>
        <td>B00QE01QLY</td>
        <td>Elektra Poqo Stick</td>
        <td>&pound;24.98</td>
        <td>&pound;24.00-&pound;25.00</td>
        <td>&pound;23.00</td>
        <td>&pound;21.98</td>
        <td>Fullfilled by Amazon</td>
      </tr>

      </tbody>
    </table>

        

  
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
