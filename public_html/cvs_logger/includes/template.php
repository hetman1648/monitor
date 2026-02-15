<?php

class VA_Template
{
	var $globals        = array();  // initial data:files and blocks
	var $blocks         = array();  // resulted data and variables
	var $templates_path = "./";     // path to templates
	var $parse_array    = array();  // array ready for parsing
	var $position       = 0;        // position in parse string
	var $length         = 0;        // length of parse string 

	var	$delimiter      = "";       // delimit blocks, tags, and html's - 
	var	$tag_sign       = "";       // tag sign - 
	var	$begin_block    = "";       // begin block sign - 
	var	$end_block      = "";       // end block sign - 

	var $show_tags      = false;

	function VA_Template($templates_path)
	{
		$this->templates_path = $templates_path;
		$this->delimiter      = chr(27);   
		$this->tag_sign       = chr(15);  
		$this->begin_block    = chr(16);  
		$this->end_block      = chr(17);  
	}

	function set_file($block_name, $filename)
	{
		global $is_admin_path;
		$file_path = $this->templates_path . "/" . $filename;
		$is_file_exists = file_exists($file_path);
		if (!$is_file_exists) {
			// check default dir for file
			if ($is_admin_path) {
				$file_path = "../templates/user/" . $filename;
			} else {
				$file_path = "./templates/user/" . $filename;
			}
			$is_file_exists = file_exists($file_path);
		}

		if ($is_file_exists)
		{
			$file_content = join("", file($file_path));
			$this->set_block($block_name, $file_content);
		}
		else
		{
			// get original path to file
			$file_path = $this->templates_path . "/" . $filename;
			echo FILE_DOESNT_EXIST_MSG . "<b>" . $file_path . "</b>";
			exit;
		}
	}

	function set_block($block_name, $block_content)
	{
		$delimiter = $this->delimiter;
		$tag_sign = $this->tag_sign;
		$begin_block = $this->begin_block;
		$end_block = $this->end_block;
	
		// preparing file content for parsing
		$block_content = preg_replace("/(<!\-\-\s*begin\s*(\w+)\s*\-\->)/is",  $delimiter . $begin_block . $delimiter . "\\2" . $delimiter, $block_content);
		$block_content = preg_replace("/(<!\-\-\s*end\s*(\w+)\s*\-\->)/is",  $delimiter . $end_block . $delimiter . "\\2" . $delimiter, $block_content);
		$block_content = preg_replace("/(\{(\w+)\})/is", $delimiter . $tag_sign . $delimiter . "\\2" . $delimiter, $block_content);
		$this->parse_array = explode($delimiter, $block_content);
		$this->position = 0;
		$this->length = sizeof($this->parse_array);

		// begin parse
		$this->parse_block($block_name, false);
	}

	function parse_block($block_name, $is_subblock = true)
	{
		$block_array  = array();
		$block_number = 0; // begin from first block and go on
		$block_array[0] = 0;

		$tag_sign = $this->tag_sign;
		$begin_block = $this->begin_block;
		$end_block = $this->end_block;

		while ($this->position < $this->length) 
		{
			$element_array = $this->parse_array[$this->position];
			if ($element_array == $tag_sign)
			{
				$block_number++;
				$block_array[$block_number] = $this->parse_array[$this->position + 1];
				$this->position += 2;
			}
			else if ($element_array == $begin_block)
			{
				$block_number++; // increase block number by one
				$block_array[$block_number] = $this->parse_array[$this->position + 1];
				$this->position += 2;
				$this->parse_block($this->parse_array[$this->position - 1], true);
			}
			else if ($element_array == $end_block && $is_subblock)
			{
				if ($this->parse_array[$this->position + 1] == $block_name)
				{
					$block_array[0] = $block_number;
					$this->position += 2;
					$this->blocks[$block_name] = $block_array;
					return;
				}
				else
				{
					echo PARSE_ERROR_IN_BLOCK_MSG . $block_name;
					exit;
				}
			}
			else
			{
				$block_number++;
				$block_array[$block_number] = $block_name . "#" . $block_number;
				$this->globals[$block_name . "#" . $block_number] = $element_array;
				$this->position++;
			}
		}
		$block_array[0] = $block_number;
		$this->blocks[$block_name] = $block_array;
	}

	function set_var($key, $value)
	{
		$this->globals[$key] = $value;
	}

	function set_vars($values)
	{
		if (is_array($values)) {
			foreach ($values as $key => $value) {
				$this->globals[$key] = $value;
			}
		}
	}

	function get_var($key)
	{
		return (isset($this->globals[$key]) ? $this->globals[$key] : "");
	}

	function copy_var($var_from, $var_to, $accumulate = true)
	{
		$var_value = $this->globals[$var_from];
		$this->globals[$var_to] = ($accumulate && isset($this->globals[$var_to])) ? $this->globals[$var_to] . $var_value : $var_value;
	}

	function block_exists($block_name, $parent_block_name = "") 
	{
		$block_exists = false;
		if ($parent_block_name === "") {
			$block_exists = isset($this->blocks[$block_name]);
		} else if (isset($this->blocks[$parent_block_name])) {
			$block_exists = in_array($block_name, $this->blocks[$parent_block_name]);
		}
		return $block_exists;
	}

	function parse($block_name, $accumulate = true)
	{
		$this->global_parse($block_name, $accumulate, false);
	}

	function rparse($block_name, $accumulate = true)
	{
		$this->global_parse($block_name, $accumulate, true, true);
	}

	function sparse($block_name, $accumulate = true)
	{
		$this->global_parse($block_name, $accumulate, false, true);
	}

	function parse_to($block_name, $parse_to, $accumulate = true)
	{
		$this->global_parse($block_name, $accumulate, false, true, $parse_to);
	}

	function global_parse($block_name, $accumulate = true, $reverse_parse = false, $safe_parse = false, $parse_to = "")
	{
		$block_value = "";
		if (isset($this->blocks[$block_name])) {
			if (!$parse_to) { $parse_to = $block_name; }
			$block_array = $this->blocks[$block_name];
			$globals = $this->globals;
			$array_size = $block_array[0];

			for ($i = 1; $i <= $array_size; $i++) {
				if (isset($globals[$block_array[$i]])) {
					$array_value = $globals[$block_array[$i]];
				} else if (defined($block_array[$i])) {
					$array_value = constant($block_array[$i]);
				} else if ($this->show_tags) {
					$array_value = "{" . $block_array[$i] . "}";
				} else {
					$array_value = "";
				}
				$block_value .= $array_value;
			}
			if ($reverse_parse) {
				$this->globals[$parse_to] = ($accumulate && isset($this->globals[$parse_to])) ? $block_value . $this->globals[$parse_to] : $block_value;
			} else {
				$this->globals[$parse_to] = ($accumulate && isset($this->globals[$parse_to])) ? $this->globals[$parse_to] . $block_value : $block_value;
			}
		} else if (!$safe_parse) {
			echo BLOCK_DOENT_EXIST_MSG . $block_name;
			exit;
		}
	}

	function pparse($block_name, $accumulate = true)
	{
		$this->parse($block_name, $accumulate);
		echo $this->globals[$block_name];
	}

	function print_block($block_name)
	{
		reset($this->blocks[$block_name]);
		echo "<table border=\"1\">";
		while (list($key, $value) = each($this->blocks[$block_name])) {
			if ($key != 0) {
				echo "<tr><th valign=top>$value</th><td>" . nl2br(htmlspecialchars($this->globals[$value])) . "</td></tr>";
			} else {
				echo "<tr><th valign=top>" . NUMBER_OF_ELEMENTS_MSG . "</th><td>" . $value . "</td></tr>";
			}
		}
		echo "</table>";
	}
}

?>