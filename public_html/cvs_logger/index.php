<?php
	exec("tac /home/cvs/CVSROOT/history  > /var/www/monitor/cvs_logger/tmp/history_reversed");

	include_once("./includes/constants.php");
	include_once("./includes/common_functions.php");
	include_once("./includes/template.php");
	include_once("./includes/record.php");
	include_once("./includes/var_definition.php");

	$t = new VA_Template(dirname(__FILE__) . "/templates/");
	$t->set_file("main", "index.html");

	$r = new VA_Record("search");
	$r->add_select("s_author", TEXT, array_merge(array(array("", "--Select author--")), $_AUTHORS));
	$r->add_select("s_module", TEXT, array_merge(array(array("", "--Select module--")), $_MODULES));
	$r->add_select("s_period", INTEGER, array(
		array("7", "Last 7 days"),
		array("30", "Last 30 days"),
		array("90", "Last 90 days")
	));
	$r->get_form_parameters();
	
	// set search params
	if ($r->get_value("s_period")) {
		$commited_since = strtotime("-" .  $r->get_value("s_period") . " days");
	} else {
		$r->set_value("s_period", "7");
		$commited_since = strtotime("-7 days");
	}
	
	// run search
	$s_author = $r->get_value("s_author");
	$s_module = $r->get_value("s_module");
	
	$commited_files = array();
	$commited_modules_by_authors = array();
	$handle = fopen("./tmp/history_reversed", "r");
	while (!feof($handle) && $i < 10000000) {
		
	    $line = fgets($handle, 4096);
	    list($date, $author, $type, $filepath, $revision, $filename)= explode("|", $line);
	    
	    $operation_type = substr($date, 0, 1);
	    $date           = hexdec($date);
	    if ($date < $commited_since) 
	    	break;
	    
	    $tmp    = explode("/", $filepath);
	    $module = $tmp[0];
	    
	    if ($s_author || $s_module) {
	    	if ($s_author && $s_author == $author
	    		&& (!$s_module || $s_module == $module) ) {
	    		$commited_files[$module][] = array(
				    date("Y-m-d H:i:s", $date),
				    $operation_type,
				    $type,
				    $filepath,
				    $revision,
				    $filename
				);
	    	} elseif (!$s_author && $s_module && $s_module == $module) {
	    		$commited_files[$author][] = array(
				    date("Y-m-d H:i:s", $date),
				    $operation_type,
				    $type,
				    $filepath,
				    $revision,
				    $filename
				);
	    	}
	    } else {
	    	if (!isset($commited_modules_by_authors[$author][$module][$operation_type])) {
		    	$commited_modules_by_authors[$author][$module][$operation_type] = date("Y-m-d H:i:s", $date);
		    }
	    }
	    $i++;
	}
	fclose($handle);
	
	// parse template
	if ($commited_modules_by_authors) {		
		ksort($commited_modules_by_authors);
		foreach ($commited_modules_by_authors AS $author => $modules) {
			$t->set_var("author_module_line", "");
			$t->set_var("author", $author);			
			foreach ($modules AS $module => $dates) {
				$t->set_var("module", $module);
				$last_checkout = isset($dates["O"]) ? $dates["O"] : "";
				$last_update   = isset($dates["U"]) ? $dates["U"] : "";
				$last_addition = isset($dates["A"]) ? $dates["A"] : "";
				$last_commit   = isset($dates["M"]) ? $dates["M"] : "";
				$last_removal  = isset($dates["R"]) ? $dates["R"] : "";
				$t->set_var("last_checkout", $last_checkout);
				$t->set_var("last_update", $last_update);
				$t->set_var("last_addition", $last_addition);
				$t->set_var("last_commit", $last_commit);
				$t->set_var("last_removal", $last_removal);
				$t->parse("author_module_line");
			}
			$t->parse("author_module_block");
		}
		$t->parse("commited_modules_by_authors");
	}
	
	if ($commited_files ) {	
		foreach ($commited_files AS $title => $line) {
			$t->set_var("details_line", "");
			$t->set_var("title", $title);
			$lines_count = array();
			foreach ($line AS $info) {
				list($date, $operation_type, $type, $filepath, $revision, $filename) = $info;
				$status = $_OPERATION_TYPES[$operation_type];
				$t->set_var("date", $date);
				$t->set_var("status", $status);
				$t->set_var("filepath", $filepath);
				$t->set_var("revision", $revision);
				$t->set_var("filename", $filename);
				$t->parse("details_line");
				if (isset($lines_count[$operation_type])) {
					$lines_count[$operation_type]++;
				} else {
					$lines_count[$operation_type] = 1;
				}
			}
			$t->set_var("lines_count", $lines_count);
			
			$lines_checkout = isset($lines_count["O"]) ? $lines_count["O"] : 0;
			$lines_update   = isset($lines_count["U"]) ? $lines_count["U"] : 0;
			$lines_addition = isset($lines_count["A"]) ? $lines_count["A"] : 0;
			$lines_commit   = isset($lines_count["M"]) ? $lines_count["M"] : 0;
			$lines_removal  = isset($lines_count["R"]) ? $lines_count["R"] : 0;

			$t->set_var("lines_checkout", $lines_checkout);
			$t->set_var("lines_update", $lines_update);
			$t->set_var("lines_addition", $lines_addition);
			$t->set_var("lines_commit", $lines_commit);
			$t->set_var("lines_removal", $lines_removal);
				
			$t->parse("details_summary_line");
			$t->parse("details_block");
		}
		$t->parse("details");
	}


	$r->set_form_parameters();
	$t->pparse("main");
?>