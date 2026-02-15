<?php
/*********************************************************************************
 *          Filename: registration.php
 *********************************************************************************/

include ("./includes/common.php");

if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}



$sFileName = "registration.php";
$sTemplateFileName = "registration.html";



$T= new iTemplate($sAppPath,array("main"=>$sTemplateFileName));


$T->set_var("FileName", $sFileName);


$sRegistrationErr = "";

$sAction = GetParam("FormAction");
$sForm = GetParam("FormName");
switch ($sForm) {
  case "Registration":
    RegistrationAction($sAction);
  break;
}Registration_Show();

$T->parse("main", false);
echo $T->p("main");


//********************************************************************************


function RegistrationCheckFields($sWhere)
{
  global $sAction;
  $sRes = "";
  
  if(!is_number(GetParam("user_id")))
    $sRes .= "The value in field <font color=\"red\"><b>user_id</b></font> is incorrect.<br>";

  
  return $sRes;
}


function RegistrationAction($sAction)
{
  global $db;
  global $T;
  global $sAction;
  global $sForm;
  global $sRegistrationErr;
  $sParams = "";
  $sActionFileName = "index.php";

  
  $sWhere = "";
  $bErr = false;

  if($sAction == "cancel")
    header("Location: index.php" ); 

  
  if($sAction == "update" || $sAction == "delete") 
  {
    $pPKuser_id = GetParam("PK_user_id");
    $sWhere .= "user_id=" . ToSQL($pPKuser_id, "Number");
  }
  
  if($sAction == "insert" || $sAction == "update") 
  {
    $sRegistrationErr = RegistrationCheckFields($sWhere);
    if(strlen($sRegistrationErr) > 0)
      return;
  }
  
  switch(strtolower($sAction)):

    case "update":
      $sSQL = "update users set " .
        "login=" . ToSQL(GetParam("login"), "Text") .
        ",password=" . ToSQL(GetParam("password"), "Text") .
        ",email=" . ToSQL(GetParam("email"), "Text") .
        ",first_name=" . ToSQL(GetParam("first_name"), "Text") .
        ",last_name=" . ToSQL(GetParam("last_name"), "Text") .
        ",day_phone=" . ToSQL(GetParam("day_phone"), "Text") .
        ",evn_phone=" . ToSQL(GetParam("evn_phone"), "Text") .
        ",address=" . ToSQL(GetParam("address"), "Text") .
        ",postcode=" . ToSQL(GetParam("postcode"), "Text") .
        ",country=" . ToSQL(GetParam("country"), "Text");
      $sSQL .= " where " . $sWhere;

      SetSessionParam("UserName", GetParam("first_name")." ".GetParam("last_name"));
      
    break;

    break;

  endswitch;

  $db->query($sSQL);

  header("Location: " . $sActionFileName);
}

function Registration_Show()
{
  global $db;
  global $T;
  global $sAction;
  global $sForm;
  global $sRegistrationErr;

  $sWhere = "";
  
  $bPK = true;
	$flduser_id = "";
	$fldlogin = "";
	$fldpassword = "";
	$fldemail = "";
	$fldfirst_name = "";
	$fldlast_name = "";
	$fldday_phone = "";
	$fldevn_phone = "";
	$fldaddress = "";
	$fldpostcode = "";
	$fldcountry = "";
	$fldprivilege_id = "";
  

  if ($sAction != "" && $sForm == "Registration") 
  {
    $flduser_id = stripslashes(GetParam("user_id"));
    $fldlogin = stripslashes(GetParam("login"));
    $fldpassword = stripslashes(GetParam("password"));
    $fldemail = stripslashes(GetParam("email"));
    $fldfirst_name = stripslashes(GetParam("first_name"));
    $fldlast_name = stripslashes(GetParam("last_name"));
    $fldday_phone = stripslashes(GetParam("day_phone"));
    $fldevn_phone = stripslashes(GetParam("evn_phone"));
    $fldaddress = stripslashes(GetParam("address"));
    $fldpostcode = stripslashes(GetParam("postcode"));
    $fldcountry = stripslashes(GetParam("country"));
    $fldprivilege_id = stripslashes(GetParam("privilege_id"));
  }
  $puser_id = GetSessionParam("UserID");

  
  if(strlen($puser_id)) 
  {
    $sWhere .= "user_id=" . ToSQL($puser_id, "Number");
    $T->set_var("PK_user_id", $puser_id);
  }
  else
  {
    $bPK = false;
    Header("Location: index.php");
  }
  

  $sSQL = "select * from users where " . $sWhere;

  if($bPK && $sAction != "insert")
  {
    $db->query($sSQL);
    $db->next_record();
    if($sAction == "")
    {
      
      $flduser_id = GetValue($db, "user_id");
      $fldlogin = GetValue($db, "login");
      $fldpassword = GetValue($db, "password");
      $fldemail = GetValue($db, "email");
      $fldfirst_name = GetValue($db, "first_name");
      $fldlast_name = GetValue($db, "last_name");
      $fldday_phone = GetValue($db, "day_phone");
      $fldevn_phone = GetValue($db, "evn_phone");
      $fldaddress = GetValue($db, "address");
      $fldpostcode = GetValue($db, "postcode");
      $fldcountry = GetValue($db, "country");
      $fldprivilege_id = GetValue($db, "privilege_id");
    }
    $T->set_var("RegistrationInsert", "");
    $T->parse("RegistrationEdit", false);

    $T->parse("RegistrationCancel", false);
  }
  else
  {

    

    $T->set_var("RegistrationEdit", "");
    
    $T->parse("RegistrationInsert", false);
    $T->parse("RegistrationCancel", false);
  }
  

    $T->set_var("user_id", ToHTML($flduser_id));
    $T->set_var("login", ToHTML($fldlogin));
    $T->set_var("password", ToHTML($fldpassword));
    $T->set_var("email", ToHTML($fldemail));
    $T->set_var("first_name", ToHTML($fldfirst_name));
    $T->set_var("last_name", ToHTML($fldlast_name));
    $T->set_var("day_phone", ToHTML($fldday_phone));
    $T->set_var("evn_phone", ToHTML($fldevn_phone));
    $T->set_var("address", ToHTML($fldaddress));
    $T->set_var("postcode", ToHTML($fldpostcode));
    $T->set_var("country", ToHTML($fldcountry));
    $T->set_var("LBprivilege_id", "");
    $dbprivilege_id = new DB_Sql();
    $dbprivilege_id->Database = DATABASE_NAME;
    $dbprivilege_id->User     = DATABASE_USER;
    $dbprivilege_id->Password = DATABASE_PASSWORD;
    $dbprivilege_id->Host     = DATABASE_HOST;

    
    $dbprivilege_id->query("select privilege_id, privilege_desc from lookup_users_privileges order by 2");
    while($dbprivilege_id->next_record())
    {
      $T->set_var("ID", $dbprivilege_id->f(0));
      $T->set_var("Value", $dbprivilege_id->f(1));
      if($dbprivilege_id->f(0) == $fldprivilege_id)
        $T->set_var("Selected", "SELECTED" );
      else 
        $T->set_var("Selected", "");
      $T->parse("LBprivilege_id", true);
    }
    
  if($sRegistrationErr == "") 
    $T->set_var("RegistrationError", "");
  else
  {
    $T->set_var("sRegistrationErr", $sRegistrationErr);
    $T->parse("RegistrationError", false);
  }
  $T->parse("FormRegistration", false);
  
}

?>