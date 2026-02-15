<?php
	include("utf8.class.php");
	define("TIMEOUT",30);
	define("DEFAULT_USER_AGENT","Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0; Maxthon)");
	
	$oUTF = new utf8(CP1251);
	$root_path = dirname($_SERVER['PHP_SELF']) . "/";
	$site_url = "http://form.text.pro/index.php";
	$site_url = "http://morphology.ru/";
	$words_file = "words.txt";
	$wordforms_file = "wordforms.txt";
	$words = array();
	$useragents_filename = "useragents.txt";
	$proxylist_filename = "proxies.txt";
	$start_line = 0;
	$end_line = 186;
	
	if (!file_exists($words_file)) { exit; }
	
	$f = @fopen($words_file, "r");	
	if ($f)
	{
		while (!feof($f))
		{
			$line = @fgets($f);
			//$words[] = trim($line);
		}
		@fclose($f);
	}
	$words[] = trim("коньяк");
	$words[] = trim("вино");
	$words[] = trim("водка");
	$words[] = trim("ликер");
	$words[] = trim("мартини");
	$words[] = trim("лимон");
	$words[] = trim("апельсин");
	$words[] = trim("яблоко");
	$words[] = trim("абцент");
	$words[] = trim("пиво");
	$words[] = trim("сахар");
	
	if (sizeof($words) > 0)
	{
		$useragents = array();
		$useragents_count = 0;
		$proxies = array();
		$proxies_count = 0;
		
		// Load UserAgents
		list($useragents, $useragents_count) = LoadUserAgents($useragents_filename);
		if ($useragents_count > 1)
		{
			shuffle($useragents);
			reset($useragents);
			$useragent = next($useragents);
		} 
		elseif ($useragents_count == 1)
		{
			$useragent = $useragents[1];
		} else
		{
			$useragent = DEFAULT_USER_AGENT;
		}
		
		// Load ProxyServersList
		if (file_exists($proxylist_filename)) {
			list($proxies, $proxies_count) = LoadProxyList($proxylist_filename);
		} else {
			//echo date("Y-m-d H:i:s") . ": file ($proxylist_filename) does not exist.\n";
			exit;
		}
		
		srand(MakeSeed());
		//setlocale(LC_ALL, 'ru_RU');
		foreach($words as $word)
		{
			if (strlen($word) < 3) { continue ;}
			
			$post_data = "main_word_org=" . $word . "&dict=rus";
			$post_data = "word=" . win2utf($word);
			$proxy = "";
			if ($proxies_count > 1)
			{
				$randval = rand(0,$proxies_count-1);
				$proxy = $proxies[$randval];
			}
			$proxy = "";
			
			$content = GetContent($site_url, $proxy, $useragent, "", $post_data);
			
			if (strlen($content) > 0)
			{
				$reg = '|<div class="base">(.*)<\/div>|Usi';
				if (preg_match($reg,$content,$out))
				{
				    if (isset($out[1]))
				    {
					echo Utf8ToWin($out[1]) . "<br>";
					WriteWordForm(Utf8ToWin($oUTF->utf8ToStr($out[1])));
				    }
				    
				    $reg = '|<li>(.*)<\/li>|Usi';
				    //$reg = '/<td class="alt2" width="50%" align="left" valign="top">\s+<div>\s+(.*)\s+<\/div>\s+<\/td>/si';
				    if (preg_match_all($reg,$content,$out))
				    {
					    ///var_dump($out);exit;
					    if (isset($out[1]))
					    {
						    $frms = $out[1];
						    foreach($frms as $w)
						    {
							echo Utf8ToWin($w) . "<br>";							
							WriteWordForm(Utf8ToWin($oUTF->utf8ToStr($w)));
						    }						    
					    }
				    }
				}
				//WriteWordForm()$str;
			}
			$useragent = next($useragents);
			
		}
	}
	
	exit;
	/*
	** Functions
	*/
	
	function MakeSeed()
	{
	    list($usec, $sec) = explode(' ', microtime());
	    return (float) $sec + ((float) $usec * 100000);
	}
	
	function LoadProxyList($proxylist_filename = "")
	{
		global $start_line, $end_line;
		
		$proxies_count = 0;
		$proxies = array();
		
		$lines = file($proxylist_filename);
		// use only unique proxy settings
		$lines = array_unique($lines);
		$i = 0;
		for($i=$start_line, $ic = count($lines); ($i<$ic && $i< $end_line); $i++) {
			if (isset($lines[$i])) {
				if (strlen($lines[$i]) > 0) {
					$proxies[$proxies_count] = explode(":", trim($lines[$i]));
					$proxies_count++;
				}
			}
		}
		
		if ($proxies_count == 0) {
			//echo date("Y-m-d H:i:s") . ": There is no proxy server in proxies' list\n";
			exit;
		} else {
			//echo date("Y-m-d H:i:s") . ": $proxies_count proxy servers were used.\n";
		}
		
		return array($proxies,$proxies_count);
	}
	
	function LoadUserAgents($useragents_filename = "")
	{
		$useragents = array();
		$useragents_count = 0;
		if (file_exists($useragents_filename)) {
			$lines = file($useragents_filename);
			foreach ($lines as $line) {
				$line = trim($line);
				if (strlen($line) > 0 && substr($line, 0, 1) != ";") {
					$useragents[$useragents_count] = $line;
					$useragents_count++;
				}
			}
		} else {
			//echo date("Y-m-d H:i:s") . ": file ($useragents_filename) does not exist.\n";
		}
		
		return array($useragents, $useragents_count);
	}
	
	function GetContent($url, $proxy, $useragent = DEFAULT_USER_AGENT, $referer = "", $post_data = "")
	{
		$content = "";
		$proxy_host = ""; $proxy_port = ""; $proxy_user = ""; $proxy_password = ""; $proxy_type = "";
		if (is_array($proxy)) {
			if (isset($proxy[0])) {
				$proxy_host = trim($proxy[0]);
			}
			if (isset($proxy[1])) {
				$proxy_port = intval(trim($proxy[1]));
			}
			if (isset($proxy[2])) {
				$proxy_user = trim($proxy[2]);
			}
			if (isset($proxy[3])) {
				$proxy_password = trim($proxy[3]);
			}
			if (isset($proxy[4])) {
				$proxy_type = trim($proxy[4]);
			}
		}
		$ch = curl_init();
		if ($ch) {
			curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
			curl_setopt($ch, CURLOPT_URL, $url);
			if (strlen($post_data) > 0)
			{
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			}
			else
			{
				curl_setopt($ch, CURLOPT_POST, 0);
			}
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			if (strlen($proxy_host) && $proxy_port > 0) {
				curl_setopt($ch, CURLOPT_PROXY, $proxy_host . ":" . $proxy_port);
				if (strlen($proxy_user) &&  strlen($proxy_password)) {
					curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_user . ":" . $proxy_password);
				}
				if($proxy_type && $proxy_type == 'SOCKS') {
					curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
				}
			}
			//curl_setopt($ch, CURLOPT_COOKIE , " X-Ref-Ok=1;expires=Mon, 17-Apr-08 10:34:13 GMT;path=/" );
			curl_setopt($ch, CURLOPT_COOKIE , "co=1" );
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
			curl_setopt($ch, CURLOPT_REFERER, $referer);
			$content = curl_exec($ch);
			$content = trim($content);
			/*/
			if (curl_errno($ch) == 0)
			{
				$reg = "";
			}
			/**/
			
			curl_close($ch);
		} else {
			//echo date("Y-m-d H:i:s") . " - Cannot initialize cURL\n";
		}
		
		return $content;
	}
	
	function WriteWordForm($str)
	{
		global $wordforms_file;
		
		$f = @fopen($wordforms_file, "a+");
		if ($f)
		{
			@fwrite($f,$str . "\n");
			@fflush($f);
			@fclose($f);
		}
	}
	
	function win2utf($s)    {
	   for($i=0, $m=strlen($s); $i<$m; $i++)    {
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