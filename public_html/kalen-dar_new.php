<?php
	set_time_limit(0);
	//http://www.kalen-dar.ru/pics/img-02-10-582.jpg
	//http://www.kalen-dar.ru/pics/timg-02-09-578.jpg
	//http://www.kalen-dar.ru/pics/simg-02-09-578.jpg
	//http://www.kalen-dar.ru/i/informer.jpg
	//http://www.kalen-dar.ru/calendar/02/09/
	$image_folder = "./attachments/task/";
	//$image_folder = "./";
	$font = "../includes/font/comic.ttf";
	//$font = "./comic.ttf";
	
	$mon = date("m");
	$day = date("d");
	
	if (!CheckImage())
	{
		GetRemoteImage();
	}
	
	$count_image = CountImage();
	srand(MakeSeed());
	$randval = 1;
	if ($count_image > 1)
	{
		$randval = rand(1,$count_image);
		/*/
		if (isset($_COOKIE['viart_kalendar']))
		{
			$randval = $_COOKIE['viart_kalendar'];
			if (($randval+1) > $count_image)
			{
				$randval = 1;
			}
			else
			{
				$randval++;
			}
		}
		else
		{
			$randval = rand(1,$count_image);
		}
		@setcookie("viart_kalendar", $randval, time()+36000);
		/**/
	}
	$image_file = $image_folder . "kalendar" . $mon . $day . "0" . $randval . ".jpg";	
	
	if ($count_image == 1)
	{
		if (@file_exists($image_file))
		{
			header("Expires: Mon, 20 Jul 2006 05:00:00 GMT");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

			// HTTP/1.1
			//header("Cache-Control: private");
			//header("Cache-Control: max-age=120");
			header("Cache-Control: no-store, no-cache, must-revalidate");
			header("Cache-Control: post-check=0, pre-check=0", false);
			// HTTP/1.0
			//header("Pragma: private");
			header("Pragma: no-cache");
			header("Content-type: image/jpeg");
			echo @file_get_contents($image_file);
		}
	}
	else if ($count_image > 1)
	{
		while(true)
		{
			if (@file_exists($image_file))
			{
				header("Expires: Mon, 20 Jul 2006 05:00:00 GMT");
				header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

				// HTTP/1.1
				//header("Cache-Control: private");
				//header("Cache-Control: max-age=120");
				header("Cache-Control: no-store, no-cache, must-revalidate");
				header("Cache-Control: post-check=0, pre-check=0", false);
				// HTTP/1.0
				//header("Pragma: private");
				header("Pragma: no-cache");
				header("Content-type: image/jpeg");
				echo @file_get_contents($image_file);
			}
			sleep(60);
			header("Location: kalen-dar_new.php");
		}
	}
	exit;
	
	/*
	** Functions
	*/
	function CheckImage()
	{
		global $image_folder;
		
		$result = false;
		$mon = date("m");
		$day = date("d");
		$image = "kalendar" . $mon . $day . "01.jpg";
		
		if (@file_exists($image_folder . $image))
		{
			$result = true;
		}
		
		return $result;
	}
	
	function CountImage()
	{
		global $image_folder;
		
		$mon = date("m");
		$day = date("d");
		$image = "kalendar" . $mon . $day . "0";
		$ext = ".jpg";
		$count = 1;
		$image_file = $image  . $count . $ext;
		
		while(@file_exists($image_folder . $image_file))
		{
			$count++;
			$image_file = $image . $count . $ext;
		}
		
		return --$count;
	}
	
	function MakeSeed()
	{
	    list($usec, $sec) = explode(' ', microtime());
	    return (float) $sec + ((float) $usec * 100000);
	}
	
	function GetRemoteImage()
	{
		global $image_folder, $font;
		
		$left = 20;
		$top = 17;
		$mon = date("m");
		$day = date("d");
		$parent_url = "http://www.kalen-dar.ru";
		$url = "http://www.kalen-dar.ru/calendar/" . $mon . "/" . $day . "/";
		
		$white_line_px = 20;
		
		//$fl = @file($url);
		//$content = @file_get_contents($url);
		//$content = implode("\n",$fl);
		$content = "";
		$fl = @fopen($url, "r");
		if ($fl)
		{
			while (!feof($fl))
			{
				$content .= fread($fl, 4096);
			}
			fclose($fl);
		}
		
		if (strlen($content) > 0)
		{
			if (strpos($content, "Страница не найдена") === false)
			{
				$title = "Просто хороший день";
				$reg = "/<title>(.+) - .+<\/title>/i";
				if (preg_match_all($reg,$content,$out))
				{
					if (isset($out[1]) && isset($out[1][0]))
					{
						$title = $out[1][0];
					}
				}
				
				//$wtitle = strlen($title) * 9 + $left;

				$reg = '/<div class="gallery_image_pic">\s+<a href="[\/a-z0-9\.\-]+"><img src="([\/a-z0-9\.\-]+)" alt=""\/><\/a><br\/>/si'; ///
				if (preg_match_all($reg,$content,$out))
				{
					$image_array = $out[1];
					$count = 1;
					$image_name = "kalendar" . $mon . $day . "0";
					foreach ($image_array as $im_url)
					{
						$timg = $parent_url . str_replace("mimg","timg",$im_url);
						$size = @getimagesize($timg);
						$wsize = $size[0];
						$hsize = $size[1];
						
						$wstr = PrepareTitle($title, $left, $wsize);
						/*/
						$key = 0;
						$wstr[$key] = "";
						if ($wtitle > $wsize)
						{
							$warray = explode(" ", $title);
							$wlen = $left;
							foreach($warray as $word)
							{
								$wlen += (strlen($word) + strlen(" ")) * 9;
								
								if ($wlen < $wsize)
								{
									$wstr[$key] .= $word . " ";
								}
								else
								{
									$wstr[$key] = trim($wstr[$key]);
									$wlen = $left;
									$key++;
									$wstr[$key] = $word . " ";
								}
							}
							$wstr[$key] = trim($wstr[$key]);
							if (strlen($wstr[$key]) == 0)
							{
								unset($wstr[$key]);
							}
						}
						else
						{
							$wstr[$key] = $title;
						}
						/**/
						
						/**/
						$white_line = sizeof($wstr)*$white_line_px;
						$hsize_new = $hsize+$white_line;
						$image_p = @imagecreatetruecolor($wsize, $hsize_new);
						$image = @imagecreatefromjpeg($timg);
						$background_color = @imagecolorallocate($image_p, 255, 255, 255);
						@imagefilledrectangle($image_p,0,0,$wsize,$hsize_new,$background_color);
						//@imagecopyresampled($image_p, $image, 0, $white_line_px, 0, 0, $wsize, $hsize_new, $wsize, $hsize);
						@imagecopy($image_p, $image,0,$white_line,0,0,$wsize, $hsize);
						
						$text_color = @imagecolorallocate($image_p, rand(0,200), rand(0,200), rand(0,200));
						for($i=0; $i<sizeof($wstr); $i++)
						{
							$top_text = ($i+1) * $top;
							@imagettftext($image_p,13,0,$left,$top_text,$text_color,$font,$wstr[$i]);
						}
						
						$image_file = $image_folder . $image_name . $count . ".jpg";
						$r = @imagejpeg($image_p, $image_file, 100);
						@imagedestroy($image_p);
						/**/
						$count++;
					}
					
					if ($count > 1)
					{
						DeleteOldImage();
					}
				}
				else
				{
					$reg = '/<img src="([\/a-z0-9\.\-]+)" alt="(.*)" title="(.*)" id=\'day_image\'\/\>/U';
					if (preg_match_all($reg,$content,$out))
					{
						if (sizeof($out) == 4)
						{
							$image_name = "kalendar" . $mon . $day;
							$timg = $parent_url . str_replace("img","timg",$out[1][0]);
							$size = @getimagesize($timg);
							$wsize = $size[0];
							$hsize = $size[1];
							
							$wstr = PrepareTitle($title, $left, $wsize);
							
							$white_line = sizeof($wstr)*$white_line_px;
							$hsize_new = $hsize+$white_line;
							$image_p = @imagecreatetruecolor($wsize, $hsize_new);
							$image = @imagecreatefromjpeg($timg);
							$background_color = @imagecolorallocate($image_p, 255, 255, 255);
							@imagefilledrectangle($image_p,0,0,$wsize,$hsize_new,$background_color);
							@imagecopyresampled($image_p, $image, 0, $white_line_px, 0, 0, $wsize, $hsize_new, $wsize, $hsize);
							
							$text_color = @imagecolorallocate($image_p, rand(0,200), rand(0,200), rand(0,200));
							for($i=0; $i<sizeof($wstr); $i++)
							{
								$top_text = ($i+1) * $top;
								@imagettftext($image_p,13,0,$left,$top_text,$text_color,$font,$wstr[$i]);
							}
							
							$image_file = $image_folder . $image_name . "01.jpg";
							$r = @imagejpeg($image_p, $image_file, 100);
							@imagedestroy($image_p);
							
							if (@file_exists($image_file))
							{
								DeleteOldImage();
							}
						}
					}
					else
					{
						///Copy old images as new
						CopyOldImages();
					}
				}
			}
			else
			{
				///Copy old images as new
				CopyOldImages();
			}
		}
	}
	
	function CopyOldImages()
	{
		global $image_folder;
		
		$mon = date("m");
		$day = date("d");
		
		$dirs = @scandir($image_folder);
		foreach($dirs as $entry)
		{
			if (!is_dir($entry))
			{
				$path_parts = @pathinfo($entry);
				if (strpos($path_parts['basename'],"kalendar") !== false && $path_parts['extension'] == "jpg")
				{
					$reg = "/(\d{2})(\d{2})(\d{2})/i";
					if (preg_match($reg,$path_parts['basename'],$out))
					{
						$m = $out[1];
						$d = $out[2];
						$c = $out[3];
						$new_name = $image_folder . "kalendar" . $mon . $day . $c . ".jpg";
						@rename($entry,$new_name);
					}
				}			
			}
		}
	}
	
	function DeleteOldImage()
	{
		global $image_folder;
		
		$mon = date("m");
		$day = date("d");
		
		$current_name = "kalendar" . $mon . $day;
		
		if ($handle = @opendir($image_folder))
		{
			while (false !== ($file = readdir($handle)))
			{
				$path_parts = @pathinfo($file);
				if (strpos($path_parts['basename'],"kalendar") !== false && $path_parts['extension'] == "jpg" && 
					strpos($path_parts['basename'],$current_name) === false)
				{
					$reg = "/(\d{2})(\d{2})(\d{2})/i";
					if (preg_match($reg,$path_parts['basename'],$out))
					{
						$m = $out[1];
						$d = $out[2];
						$c = $out[3];
						
						if (($mon . $day) != ($m . $d))
						{
							@unlink($image_folder . $file);
						}
					}
				}
			}
		}
	}
	
	function PrepareTitle($title, $left, $wsize)
	{
		$sumbolWidth = 10;
		$wtitle = (strlen(Utf8ToWin($title)) * $sumbolWidth) + $left;
		//$title = Utf8ToWin($title);
		
		$wstr = array();
		$key = 0;
		$wstr[$key] = "";
		if ($wtitle > $wsize)
		{
			$warray = explode(" ", $title);
			$wlen = $left;
			foreach($warray as $word)
			{
				$wlen += ceil((strlen($word) + strlen(" ")) * $sumbolWidth);

				if ($wlen < $wsize)
				{
					$wstr[$key] .= $word . " ";
				}
				else
				{
					$wstr[$key] = trim($wstr[$key]);
					$wlen = $left;
					$key++;
					$wstr[$key] = $word . " ";
				}
			}
			$wstr[$key] = trim($wstr[$key]);
			if (strlen($wstr[$key]) == 0)
			{
				unset($wstr[$key]);
			}
		}
		else
		{
			$wstr[$key] = $title;
		}
		
		return $wstr;
	}
	
	function win2utf($s)    {
		$t = "";
		for($i=0, $m=strlen($s); $i<$m; $i++)
		{
			$c=ord($s[$i]);
			if ($c<=127) {$t.=chr($c); continue; }
			if ($c>=192 && $c<=207)    {$t.=chr(208).chr($c-48); continue; }
			if ($c>=208 && $c<=239) {$t.=chr(208).chr($c-48); continue; }
			if ($c>=240 && $c<=255) {$t.=chr(209).chr($c-112); continue; }
			if ($c==184) { $t.=chr(209).chr(209); continue; };
			if ($c==168) { $t.=chr(208).chr(129);  continue; };
			if ($c==184) { $t.=chr(209).chr(145); continue; }; #ё
			if ($c==168) { $t.=chr(208).chr(129); continue; }; #Ё
			if ($c==179) { $t.=chr(209).chr(150); continue; }; #і
			if ($c==178) { $t.=chr(208).chr(134); continue; }; #І
			if ($c==191) { $t.=chr(209).chr(151); continue; }; #ї
			if ($c==175) { $t.=chr(208).chr(135); continue; }; #ї
			if ($c==186) { $t.=chr(209).chr(148); continue; }; #є
			if ($c==170) { $t.=chr(208).chr(132); continue; }; #Є
			if ($c==180) { $t.=chr(210).chr(145); continue; }; #ґ
			if ($c==165) { $t.=chr(210).chr(144); continue; }; #Ґ
			if ($c==184) { $t.=chr(209).chr(145); continue; }; #Ґ           
	   }
	   return $t;
	}
	
	function Utf8ToWin($fcontents) {
	    $out = $c1 = '';
	    $byte2 = false;
	    for ($c = 0;$c < strlen($fcontents);$c++) {
	        $i = ord($fcontents[$c]);
	        if ($i <= 127) {
	            $out .= $fcontents[$c];
	        }
	        if ($byte2) {
	            $new_c2 = ($c1 & 3) * 64 + ($i & 63);
	            $new_c1 = ($c1 >> 2) & 5;
	            $new_i = $new_c1 * 256 + $new_c2;
	            if ($new_i == 1025) {
	                $out_i = 168;
	            } else {
	                if ($new_i == 1105) {
	                    $out_i = 184;
	                } else {
	                    $out_i = $new_i - 848;
	                }
	            }
	            // UKRAINIAN fix
	            switch ($out_i){
	                case 262: $out_i=179;break;// і
	                case 182: $out_i=178;break;// І
	                case 260: $out_i=186;break;// є
	                case 180: $out_i=170;break;// Є
	                case 263: $out_i=191;break;// ї
	                case 183: $out_i=175;break;// Ї
	                case 321: $out_i=180;break;// ґ
	                case 320: $out_i=165;break;// Ґ
	            }
	            $out .= chr($out_i);
	           
	            $byte2 = false;
	        }
	        if ( ( $i >> 5) == 6) {
	            $c1 = $i;
	            $byte2 = true;
	        }
	    }
	    return $out;
	}
?>