<?php
include("./includes/date_functions.php");
include("./includes/common.php");

function CreateTable($sql, $block_name)
{
	global $db;
	global $T;
    global $statuses_classes;
	
	$db->query($sql);

	 $i = 1;
	
	if ($db->next_record())
	{
		
		//fill $list array
		do {
			
			$list[] = $db->Record;

		} while ($db->next_record());
		
		foreach ($list as $row)// make table row for this bug
		{
			foreach ($row as $key => $item)
			{
				
				$T->set_var($key, $item == "NULL" ? '':$item);
								
				
			}
			
			if (isset($row['status'])) { 
				$T->set_var('STATUS', $statuses_classes[$row['status']]);
			}
			
			if (isset($row['task_id'])) { 
				$T->set_var('task_id', $row['task_id']);
				$i ++;
				$T->set_var('title_id', $i);
			}
			
			$T->parse($block_name, true);
		}
		
		$T->set_var("no_".$block_name, ""); 
	
	}
	
	else 
	{
		$T->set_var($block_name, "");
		$T->parse("no_".$block_name, false);
	}
}

$user_id1 = 0;
$user_id2 = 0;

if (GetParam('user_id')) $user_id1 = GetParam('user_id');
if (GetParam('compare'))
{
	$user_id1 = GetParam('person1');
	$user_id2 = GetParam('person2');
}

$T = new iTemplate($sAppPath);
$T->set_file("main", "hours_compare.html");

$sql = '
CREATE TEMPORARY TABLE IF NOT EXISTS t
SELECT YEAR(started_date) as year, MONTHNAME(started_date) as month, started_date 
FROM time_report 
GROUP BY MONTHNAME(started_date), YEAR(started_date) 
ORDER BY started_date';
$db->query($sql);

$sql = '
CREATE TEMPORARY TABLE IF NOT EXISTS hours1 
SELECT t.year, t.month, t1.hours FROM 
	t
LEFT OUTER JOIN
(
	SELECT YEAR(started_date) as year, MONTHNAME(started_date) as month, CAST(ROUND(SUM(spent_hours), 2) as BINARY) as hours 
	FROM time_report 
	WHERE user_id = '.$user_id1.' 
	GROUP BY MONTHNAME(started_date), YEAR(started_date) 
	ORDER BY started_date
) t1
ON t.year = t1.year AND t.month = t1.month';
$db->query($sql);

$sql = '
CREATE TEMPORARY TABLE IF NOT EXISTS hours2 
SELECT t.year, t.month, t2.hours FROM 
t
LEFT OUTER JOIN
(
	SELECT YEAR(started_date) as year, MONTHNAME(started_date) as month, CAST(ROUND(SUM(spent_hours), 2) as BINARY) as hours 
	FROM time_report 
	WHERE user_id = '.$user_id2.' 
	GROUP BY MONTHNAME(started_date), YEAR(started_date) 
	ORDER BY started_date

) t2
on t.year = t2.year AND t.month = t2.month';
$db->query($sql);

$sql = '
CREATE TEMPORARY TABLE IF NOT EXISTS total_days 
SELECT t.started_date as started_date, t.year as year, t.month as month, t3.total_days as total_days FROM 
t
LEFT OUTER JOIN
(
	SELECT YEAR(started_date) as year, MONTHNAME(started_date) as month, 
	COUNT(DISTINCT DATE(tt.started_date)) - 
	(

		SELECT COUNT(*) 
		FROM holiday_template 
		WHERE MONTHNAME(holiday_date)=MONTHNAME(tt.started_date)
		
	) as total_days
	FROM time_report tt 
	WHERE DAYOFWEEK(started_date) NOT IN (1, 7) 
	GROUP BY MONTHNAME(started_date), YEAR(started_date)

) t3
ON t.year = t3.year AND t.month = t3.month';
$db->query($sql);

$sql = '
CREATE TEMPORARY TABLE IF NOT EXISTS holiday_days1 
SELECT t.started_date as started_date, t.year as year, t.month as month, t4.holiday_days1 as holiday_days1, t4.holiday_hours1 as holiday_hours1 FROM 
t
LEFT OUTER JOIN
(
	SELECT YEAR(started_date) as year, MONTHNAME(started_date) as month, 
	COUNT(DISTINCT DATE(started_date)) as holiday_days1, 
	CAST(ROUND(SUM(spent_hours), 2) as BINARY) as holiday_hours1
	FROM time_report t  
	WHERE user_id = '.$user_id1.' 
	AND 
	(
	CONCAT(DAYOFMONTH(started_date), MONTHNAME(started_date)) IN 
	(
			SELECT CONCAT(DAYOFMONTH(holiday_date), MONTHNAME(holiday_date)) 
				FROM holiday_template
	)      		
	OR 
	DAYOFWEEK(started_date IN(1,7)
	)
	OR
  	DAYOFWEEK(started_date) IN (1,7)
  	)
	GROUP BY MONTHNAME(started_date), YEAR(started_date) 
	ORDER BY started_date

) t4
ON t.year = t4.year AND t.month = t4.month';
$db->query($sql);


$sql = '
CREATE TEMPORARY TABLE IF NOT EXISTS report
SELECT * FROM(SELECT hours1.year year, hours1.month month, hours1.hours hours1, hours2.hours hours2, total_days.total_days total_days, CONCAT(total_days.total_days*8 ,\'.00\') total_hours, holiday_days1.holiday_days1 as holiday_days1, holiday_days1.holiday_hours1 as holiday_hours1
FROM total_days
JOIN 
hours2 USING(year, month)
JOIN 
hours1 USING(year, month)
JOIN 
holiday_days1 USING(year, month)
GROUP BY hours1.year, hours1.month
ORDER BY total_days.started_date) xx
';
$db->query($sql);

$sql = '
		CREATE TEMPORARY TABLE IF NOT EXISTS total_report
		SELECT CONCAT(\'<b>\', \'Total \', year, \'</b>\') as year, \'\' as month,  
			CONCAT(\'<b>\', CAST(ROUND(SUM(hours1), 2) as BINARY), \'</b>\') as hours1, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(hours2), 2) as BINARY), \'</b>\') as hours2, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(hours2)) as BINARY), \'</b>\') as total_days, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(total_hours), 2) as BINARY), \'</b>\') as total_hours, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(holiday_days1)) as BINARY), \'</b>\') as holiday_days1, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(holiday_hours1), 2) as BINARY), \'</b>\') as holiday_hours1  
		FROM report 
		GROUP BY year';

$db->query($sql);

		
$sql = 'CREATE TEMPORARY TABLE IF NOT EXISTS total_total_report
		SELECT CONCAT(\'<b>\', \'Total \', \'</b>\') as year, \'\' as month, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(hours1), 2) as BINARY), \'</b>\') as hours1, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(hours2), 2) as BINARY), \'</b>\') as hours2, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(hours2)) as BINARY), \'</b>\') as total_days, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(total_hours), 2) as BINARY), \'</b>\') as total_hours, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(holiday_days1)) as BINARY), \'</b>\') as holiday_days1, 
			CONCAT(\'<b>\', CAST(ROUND(SUM(holiday_hours1), 2) as BINARY), \'</b>\') as holiday_hours1  
		FROM report 
		GROUP BY 1';

$db->query($sql);

$sql = 'SELECT * FROM report UNION 
		SELECT * FROM total_report UNION
		SELECT * FROM total_total_report';


CreateTable($sql, 'records');


$sql = 'SELECT IF('.$user_id1.' = user_id, \'selected\', \'\') as selected, user_id, CONCAT(first_name, \' \',last_name) as person_name FROM users WHERE is_deleted IS NULL ORDER BY person_name';
CreateTable($sql, 'person1');
$sql = 'SELECT IF('.$user_id2.' = user_id, \'selected\', \'\') as selected, user_id, CONCAT(first_name, \' \',last_name) as person_name FROM users WHERE is_deleted IS NULL ORDER BY person_name';
CreateTable($sql, 'person2');

$sql = 'SELECT CONCAT(first_name, \' \', last_name) as user_name FROM users WHERE user_id = '.$user_id1;
$db->query($sql);
$db->next_record();
if ($user_id1 > 0) $T->set_var('person1_title', $db->Record['user_name']);
else $T->set_var('person1_title', 'none');

$sql = 'SELECT CONCAT(first_name, \' \', last_name) as user_name FROM users WHERE user_id = '.$user_id2;
$db->query($sql);
$db->next_record();
if ($user_id2 > 0) $T->set_var('person2_title', $db->Record['user_name']);
else $T->set_var('person2_title', 'none');


$T->pparse("main"); 

$sql = 'DROP TABLE IF EXISTS t, hours1, hours2, total_days, report, total_report, total_total_report';
$db->query($sql);
?>
