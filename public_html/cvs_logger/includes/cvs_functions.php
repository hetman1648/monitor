<?php
	class VA_CVS { 
		function get_cvs_modules_list($order_by = "name") {
			$modules = array();
			$d = dir(_CVS_ROOT);
			while (false !== ($entry = $d->read())) {
				if ($entry != "." && $entry != ".." && $entry != "CVSROOT" && is_dir(_CVS_ROOT . "/" . $entry)) {
					$fileatime = date("Y-m-d H:i:s", fileatime(_CVS_ROOT . "/" . $entry));
					$filemtime = date("Y-m-d H:i:s", filemtime(_CVS_ROOT . "/" . $entry));					
					$author_data = posix_getpwuid(fileowner(_CVS_ROOT . "/" . $entry));
					$author = $author_data["name"];
					
					switch ($order_by) {
						case "atime":
							$order_key = $atime . " " . strtolower($entry);
							break;
						case "mtime":
							$order_key = $mtime . " " . strtolower($entry);
							break;
						case "author":
							$order_key = $author . " " . strtolower($entry);
							break;
						default: case "name":
							$order_key = strtolower($entry);
							break;
						
					}
					$modules[$order_key] = array (
						"name"   => $entry,
						"atime"  => $fileatime,
						"mtime"  => $filemtime,
						"author" => $author
					);
				}
			}
			$d->close();

			ksort($modules);
			return array_values($modules);			
		}
	}
	
	if(!function_exists("posix_getpwuid")) {
		function posix_getpwuid($str) {
			return $str;
		}
	}
?>