<?php

function compare_by_order($a, $b)
{   
	global $sort_order;
	global $reverse;
	
	
	if (!(isset($a[$sort_order]))) 
	{
		return 0; 
		break;
	}   
	
	if (!(intval($a[$sort_order]))) 
	{
		$a[$sort_order] = strtolower($a[$sort_order]);
		$b[$sort_order] = strtolower($b[$sort_order]);
	}	
			
	if ($a[$sort_order] < $b[$sort_order])
	{
		if ($reverse)
	    {
			return 1;
		}
		else
		{
			 return 0;
		}
	
	}
	else
	{
	    if ($reverse)
	    {
			return 0;
		}
		else
		{
			 return 1;
		}
	}   
} 


function getPageLink($i)
{
	global $_SERVER;
	global $page_num;
	global $return_special_hyperlinks;
	
	$query = $_SERVER['QUERY_STRING'];
	$s = preg_replace('/&page_num=\d+/', '', $query);
	$s .= '&page_num='.$i; 
	
	
	
	if ($i == $page_num) return $i;
	else  
	if ($return_special_hyperlinks)
	return '<a onclick="get_clients_table(\''.$s.'\')" href="#">'.$i.'</a>';
	
	else
	return '<a href=\''.$page_name.'?'.$s.'\'>'.$i.'</a>';
	
}

function getPageLinks($page_col, $page_num, $query)
{
	if ($page_col == 1) return '';
	$html_result = '';
	
	if ($page_col < 20)
	{
		for ($i = 1; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
		return $html_result;
	}
	if ($page_num <= 8)
	{
		for ($i = 1; $i <= max($page_num + 2, 5); $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_col - 4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
	elseif (($page_num > 8) && ($page_num <= $page_col - 8))
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_num+2; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_col-4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
	elseif ($page_num > $page_col - 7)
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i);	
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);	
	}	
	
	return $html_result;	
}




function CreateTable($sql, $block_name, $db_obj_name, $template_obj_name, $sort_order, $page_num = 1, $reverse = false, $records_per_page = 27, $return_special_hyperlinks=false, $page_name = 'view_clients.php')
{
	global $_SERVER;
	$list = array();
	$db = &$GLOBALS[$db_obj_name];
	$T = &$GLOBALS[$template_obj_name];
    $GLOBALS['list'] = &$list;
    $GLOBALS['return_special_hyperlinks'] = &$return_special_hyperlinks;
    $db->query('SELECT COUNT(*) as count FROM ('.$sql.') xxx');
    $db->next_record();
    
    
	$page_col = (int)ceil($db->Record['count']/$records_per_page);
	$sql = 'SELECT * FROM ('.$sql.') qq '.($sort_order ? 'ORDER BY '.$sort_order.' '.($reverse ? '' : 'DESC') : '').($records_per_page == 0 ? '': ' LIMIT '.($page_num - 1)*$records_per_page.', '.$records_per_page);
    $db->query($sql);
    
    
    $T->set_var('pages_navigator', getPageLinks($page_col, $page_num, $_SERVER['QUERY_STRING']));

	if ($db->next_record())
	{
		
		//fill $list array
		do 
		{
			
			$list[] = $db->Record;

		} while ($db->next_record());
		
		//$GLOBALS['sort_order'] = $sort_order;
		//$GLOBALS['reverse'] = $reverse;
		//usort($list, 'compare_by_order');
		$i=0;
 		foreach ($list as $row)// make table row
		{
			$i++;
			foreach ($row as $key => $item)
			{
				
				$T->set_var($key, $item == "NULL" ? '':$item);
			}
			
			$T->set_var('color', $i % 2 ? 'B0B0B0': 'E0E0E0');
			
				
			$T->parse($block_name, true);
		}
		
		$T->set_var("no_".$block_name, ""); 
	
	}
	
	else 
	{
		$T->set_var($block_name, "");
		$T->parse("no_".$block_name, false);
	}
	
	return $sql;
	
	#$pages = '';
	#$T-set_var('pages', $pages);
	#$T->parse("pages_".$block_name, false);
	
}
	
?>