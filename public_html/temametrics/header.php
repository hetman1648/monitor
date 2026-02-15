<?php



?>
<!DOCTYPE html>
<html>
  <head>
    <title>Amazon Repricer </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet" media="screen">
    <link href="css/docs.css" rel="stylesheet" media="screen">
    <style type="text/css">

 .twitter-typeahead .tt-query,
.twitter-typeahead .tt-hint {
	margin-bottom: 0;
}
.tt-hint {
	display: block;
	width: 100%;
	height: 38px;
	padding: 8px 12px;
	font-size: 14px;
	line-height: 1.428571429;
	color: #999;
	vertical-align: middle;
	background-color: #ffffff;
	border: 1px solid #cccccc;
	border-radius: 4px;
	-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
	      box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
	-webkit-transition: border-color ease-in-out 0.15s, box-shadow ease-in-out 0.15s;
	      transition: border-color ease-in-out 0.15s, box-shadow ease-in-out 0.15s;
}
.tt-dropdown-menu {
	min-width: 160px;
	margin-top: 2px;
	padding: 5px 0;
	background-color: #ffffff;
	border: 1px solid #cccccc;
	border: 1px solid rgba(0, 0, 0, 0.15);
	border-radius: 4px;
	-webkit-box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
	      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
	background-clip: padding-box;
 
}
.tt-suggestion {
	display: block;
	padding: 3px 20px;
}
.tt-suggestion.tt-is-under-cursor {
	color: #fff;
	background-color: #428bca;
}
.tt-suggestion.tt-is-under-cursor a {
	color: #fff;
}
.tt-suggestion p {
	margin: 0;
}



    </style>
  </head>
<body >


<!-- <div class="container"> -->
<?php if ($is_front_end) { ?>
<header class="navbar navbar-inverse navbar-fixed-top bs-docs-nav" role="banner">
  <div class="container">
    <div class="navbar-header">
      <button class="navbar-toggle" type="button" data-toggle="collapse" data-target=".bs-navbar-collapse">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a href="./" class="navbar-brand">Temametrics</a>
    </div>
    <nav class="collapse navbar-collapse bs-navbar-collapse" role="navigation">
      <ul class="nav navbar-nav">
        <li class="active">
          <a href="step1.php">Getting started</a>
        </li>
        <li>
          <a href="./">Features</a>
        </li>
        <li>
          <a href="./">Prices</a>
        </li>
        <li>
          <a href="./">Contact Us</a>
        </li>
        <li>
          <a href="login.php">Log In</a>
        </li>
      </ul>
    </nav>
  </div>
</header>
<?php } else  { ?>
<header class="navbar navbar-inverse navbar-fixed-top bs-docs-nav" role="banner">
  <div class="container">
    <div class="navbar-header">
      <button class="navbar-toggle" type="button" data-toggle="collapse" data-target=".bs-navbar-collapse">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a href="./" class="navbar-brand">Temametrics</a>
    </div>
    <nav class="collapse navbar-collapse bs-navbar-collapse" role="navigation">
      <ul class="nav navbar-nav">
        <li class="active">
          <a href="step1.php">Initial Setup</a>
        </li>
        <li>
          <a href="products.php">Products</a>
        </li>
        <li>
          <a href="products.php">Settings</a>
        </li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
      	<li><a href="">Repricing Status: Active (156 products)</a></li>
        <li >
          <a href="#">Help</a>
        </li>
        <li >
          <a href="./">Logout</a>
        </li>
      </ul>
    </nav>
  </div>
</header>
<?php } ?>
