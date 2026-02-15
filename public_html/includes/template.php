<?php

class iTemplate
{
/********** Functions: ********************************************
          function iTemplate($root,$files=array(),$unknowns = "remove")
          function set_block($file_var,$value)
          function set_file($files,$file_var="")
          function load_file($file_name,$file_var)
          function set_blocks($file_var)
          function copy_var($new_vars)
          function set_var($new_vars,$value = "")
          function p_blocks($showHTML=true)
          function p_vars($showHTML=true)
          function get_var($var)
          function get_block($block)
          function p($var)
          function set_root($root)
          function halt($error_message)
          function rparse($block_name)
          function aparse($block_names,$accumulate=true,$reverse=false)
          function parse($block_name,$accumulate=true,$reverse=false)
          function pparse($block_name,$accumulate=true,$reverse=false)
          function get_undefined($handle)
          function load_tags($file_name)
*/
  var $classname = "iTemplate";
  //-- directory where template files are stored
  var $root=".";

  //-- initial data:files and blocks
  var $blocks= array();

  //-- resulted data and variables
  var $vars = array();

  //-- blocks for controls
  var $controls = array();

  //-- indication whether block parsed at least once
  var $not_parsed= array();

  var $default_file="";

  //-- constructor
  function iTemplate($root,$files=array(),$unknowns = "remove")
  {
    $this->set_root($root);
    $this->set_file($files);

    $this->load_tags($root."/tags.txt");


  }

  function set_block($file_var,$value)
  {
      $this->blocks[$file_var]=$value;

      //-- entering the content of all {ITEMPLATE=file_name} tags
      preg_match_all("/\{ITEMPLATE=.*\}/U",$this->blocks[$file_var],$tmp_ar);
      reset($tmp_ar[0]);
      while (list($key,$val)=each($tmp_ar[0]))
      {
        //echo $val."<hr>";
        $sub_template = preg_split("/=|\}/",$val);
        $sub_template_var= preg_split("/\./",$sub_template[1]);

        //-- load sub-template
        if ($this->load_file($sub_template[1],$sub_template_var[0]))
        {
          $pattern="/$val/";
          $this->blocks[$file_var]=preg_replace($pattern,$this->blocks[$sub_template_var[0]],$this->blocks[$file_var]);

          //$this->blocks[$sub_template_var[0]]="";
          unset($this->blocks[$sub_template_var[0]]);
        }
      }

      $this->set_blocks($file_var);

  }

  //-- loading files
  //-- 1) "$files" is array of pairs file_variable/file_name
  //-  2) if specified $file_var we use pair $file_var/$files
  //-  3) if file_var is ommited then we throw away extension "file"/"file.html"
  function set_file($files,$file_var="")
  {
    //-- reading all files in the array
    if(is_array($files))
    {
      reset($files);
      while (list($file_var,$file_name)=each($files))
      {
        $this->load_file($file_name,$file_var);
        $this->default_file=$file_var;
      }
    }
    else
    {
      if (strlen($file_var)>0)
      {
        $this->load_file($file_var,$files);
        $this->default_file=$files;
        //echo $this->default_file;
      }
      else //-- if only file_name specified then we throw away extension
      {
        //$pos=strpos(".",$files);

        if(list($file_var)=preg_split("/\./",$files))
        {
          //echo "we are here";
          $this->load_file($files,$file_var);
          $this->default_file=$file_var;
        }
      }
    }
  }

  //-- function loads file into "blocks" array
  //-- it includes files and sets blocks as well
  function load_file($file_name,$file_var)
  {
    if (isset($this->blocks[$file_var])) {
	    if (strlen($this->blocks[$file_var])>0)
	    {
	      return false;
	    }
    }
    $file_name=$this->root."/".$file_name;
    if (file_exists($file_name))
    {
      $value=join('',file($file_name));
      $this->set_block($file_var,$value);

      return true;
    }
    else
    {
      echo "<h2>File $file_name doesn't exist</h2>";
      return false;
    }
  }

  //-- function finds all blocks in the file extract,store them and replace for
  //- {BLOCK_NAME} tag
  function set_blocks($file_var)
  {
      //-- replacing dynamic blocks
      //preg_match_all("/<!--\s*BEGIN\s*\w\s*-->/",$this->blocks[$file_var],$tmp_ar);
      preg_match_all("/<!--\s*BEGIN\s*(\w+)\s*-->/U",$this->blocks[$file_var],$tmp_ar);

      reset($tmp_ar[0]);
      while (list($key,$val)=each($tmp_ar[0]))
      {
//        echo htmlspecialchars($val);
        //-- looking for the name of the block
        $sub_template_var= preg_split("/(BEGIN\s*)|(-->)/U",$val);
        $block_name=trim($sub_template_var[1]);

        //-- extracting body of the block
        preg_match_all("/$val.*<!--\s*END\s*$block_name\s*-->/sU",$this->blocks[$file_var],$block_body);
        if (list($t1,$t2)=each($block_body[0]))
        {
          //-- found body block
          $t3=preg_replace("/$val|(<!--\s*END\s*$block_name\s*-->)/sU","",$t2);
          //-- storing body in the hash
          $this->blocks[$block_name]=$t3;

          //-- if block is control then add reference to controls array
          if (substr($block_name,0,4)=="crl_")
          {
            //echo "Control found - '$block_name'<br>\n";
            $this->controls[$block_name]=substr($block_name,4);
          }

          //$this->vars[$block_name]=$t2;
          $this->parse($block_name,false);
          $this->not_parsed[$block_name]=true;

          //-- replacing body block to {BLOCK_NAME}
          $this->blocks[$file_var]=preg_replace("/$val.*<!--\s*END\s*$block_name\s*-->/sU","{".trim($block_name)."}", $this->blocks[$file_var]);

          //-- finding recursively sub-blocks
          $this->set_blocks($block_name);
        }
      }

  }

  function copy_var($new_vars)
  {
    $this->vars[$new_vars] = $this->blocks[$new_vars];
  }

  //-- Setting vaiables
  //-- could be used in two ways:
  //-- 1) $new_vars is array of pairs key/value
  //-- 2) $new_vars is key,$value is value
  function set_var($new_vars,$value = "")
  {
    if (is_array($new_vars))
    {
      reset($new_vars);
      while(list($key, $value) = each($new_vars))
      {
        $this->vars[$key] =$value;
      }
    }
    else
    {
      $this->vars[$new_vars] = $value;
    }
  }


  //-- prints contents of "blocks" array
  //-- useful for debugging
  //-- showHTML determines - if to show HTML code (convert to &lg;&gt; ...)
  function p_blocks($showHTML=true)
  {
    reset($this->blocks);
    echo "<table cellpadding=0 border=1 bordercolor=#D0D0D0 cellspacing=0 bgcolor=white>";
    echo "<tr bgcolor=navy><td colspan=2 align=center><font color=white size=3 face=Arial><b>List of Blocks:</b></font></td></tr>";
    echo "<tr bgcolor=navy><td><font color=white size=2 face=Arial>Block name</font></td><td><font color=white size=2 face=Arial>Value</font></td></tr>";
    while (list($key,$value)=each($this->blocks))
    {
      echo "<tr>";
      echo "<td><font size=2 face=Arial><b>$key</b></font></td>";
      if ($showHTML)
      {
        echo "<td><font size=2 face=Arial>".htmlspecialchars($value)."</font></td>";
      }
      else
      {
        echo "<td><font size=2 face=Arial>".$value."</font></td>";
      }
      echo "</tr>";
    }
  }

  //-- prints contents of "vars" array
  //-- useful for debugging
  //-- showHTML determines - if to show HTML code (convert to &lg;&gt; ...)
  function p_vars($showHTML=true)
  {
    reset($this->vars);
    echo "<table cellpadding=0 border=1 bordercolor=#D0D0D0 cellspacing=0 bgcolor=white>";
    echo "<tr bgcolor=navy><td colspan=2 align=center><font color=white size=3 face=Arial><b>List of Variables:</b></font></td></tr>";
    echo "<tr bgcolor=navy><td><font color=white size=2 face=Arial>Variable name</font></td><td><font color=white size=2 face=Arial>Value</font></td></tr>";
    while (list($key,$value)=each($this->vars))
    {
      echo "<tr>";
      echo "<td><font size=2 face=Arial><b>$key</b></font></td>";
      if ($showHTML)
      {
        echo "<td><font size=2 face=Arial>".htmlspecialchars($value)."</font></td>";
      }
      else
      {
        echo "<td><font size=2 face=Arial>".$value."</font></td>";
      }
      echo "</tr>";
    }
  }


  //-- returns the value of variable from "vars" array
  function get_var($var)
  {
    return $this->vars[$var];
  }

  //-- returns the value of variable from "blocks" array
  function get_block($block)
  {
    return $this->blocks[$block];
  }

  //-- prints the value of variable from "vars" array
  function p($var)
  {
    echo $this->vars[$var];
  }

  /* public: setroot(pathname $root)
   * root:   new template directory.
   */
  function set_root($root)
  {
    if (!is_dir($root))
    {
      $this->halt("set_root: $root is not a directory.");
      return false;
    }

    $this->root = $root;
    return true;
  }

  function halt($error_message)
  {
    echo $error_message;
  }

  function rparse($block_name)
  {
    $this->parse($block_name,true,true);
  }

  function aparse($block_names,$accumulate=true,$reverse=false)
  {
    if(is_array($block_names))
    {
      while(list($key,$val)=each($block_names))
      {
        $this->parse($val,$accumulate,$reverse);
      }
    }
    else
    {
      $this->parse($block_names,$accumulate,$reverse);
    }
  }

  //-- function parses $block_name; if accumulate then the result of parsing is
  //-- added to the previous result of parsing $reverse determines weather to add
  //-- the result to the begining of the previous one or not
  function parse($block_name,$accumulate=true,$reverse=false)
  {
    //-- on default we are looking for main,MAIN,Main
    //if ($this->blocks["main"]) $block_name="main";
    if (strlen($block_name)==0) $block_name=$this->default_file;
    $tmp_result=$this->blocks[$block_name];
    reset($this->vars);
    while(list($key,$value)=each($this->vars))
    {
      if ($key!=$block_name)
      {
        $tmp_result=str_replace("{".$key."}",$value,$tmp_result);
      }
    }

    if($accumulate)
    {
      //-- checking if the previous result is result of loading
      if (isset($this->not_parsed[$block_name]) && $this->not_parsed[$block_name]==true)
      {
        $this->vars[$block_name]=$tmp_result;
      }
      else
      {
      	if (!isset($this->vars[$block_name])) $this->vars[$block_name] = "";
        if($reverse)
        {
          $this->vars[$block_name]=$tmp_result.$this->vars[$block_name];
        }
        else
        {
          $this->vars[$block_name]=$this->vars[$block_name].$tmp_result;
        }
      }
    }
    else
    {
      $this->vars[$block_name]=$tmp_result;
    }
    $this->not_parsed[$block_name]=false;

    return $this->vars[$block_name];
  }

  //-- shortcut to print parse(..)
  function pparse($block_name,$accumulate=true,$reverse=false)
  {
    $this->set_var("user_id", GetSessionParam('UserID'));
    echo $this->parse($block_name,$accumulate,$reverse);
  }

  //-- returns all undefined tags (for which set_var() wasn't called) in array
  //-- false if there are no undefined tags
  function get_undefined($handle)
  {
    preg_match_all("/\{([^}]+)\}/", $this->blocks[$handle], $m);
    $m = $m[1];
    if (!is_array($m))
      return false;

    reset($m);
    while(list($k, $v) = each($m))
    {
      if (!isset($this->vars[$v]))
        $result[$v] = "";
    }

    if (count($result))
      return $result;
    else
      return false;
  }

  //-- reading tags from ini-file into the array
  function load_tags($file_name)
  {
    if(file_exists($file_name))
    {
      $iniFile = file($file_name);
      while ( list( $line_num, $line ) = each($iniFile  ) )
      {
        $firstCh=substr($line,0,1);
        if ($firstCh!="#" && $firstCh!="/" && $firstCh!=";")
        {
          $pos = strpos($line,"=");

          $name=substr($line,0,$pos);
          $value=substr($line,$pos+1);

          //$message_vars[$name]=trim($value);
          $this->set_var($name,trim($value));
        }
      }
    }
  }

  //-- reading tags from ini-file into the array
  function get_controls($print=false)
  {
    reset($this->controls);
    if ($print)
    {
      echo "<table cellpadding=0 border=1 bordercolor=#D0D0D0 cellspacing=0 bgcolor=white>";
      echo "<tr bgcolor=navy><td colspan=2 align=center><font color=white size=3 face=Arial><b>List of controls:</b></font></td></tr>";
      echo "<tr bgcolor=navy><td><font color=white size=2 face=Arial>Control name</font></td><td><font color=white size=2 face=Arial>Control value</font></td></tr>";
      while (list($key,$value)=each($this->controls))
      {
        echo "<tr>";
        echo "<td><font size=2 face=Arial><b>$value</b></font></td>";
        echo "<td><font size=2 face=Arial>".htmlspecialchars($this->blocks[$key])."</font></td>";
        echo "</tr>";
      }
      echo "</table>";
    }
    return $this->controls;
  }

	function block_exists($block_name) 
	{
		return isset($this->blocks[$block_name]);
	}

}
?>