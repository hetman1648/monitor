function confirm_close()
{
	if (document.tasks.tasks_selected.value.length && confirm("Are you sure you want to CLOSE selected tasks?")) {
		return true;
	} else {
		return false;
	}
}

function closeTask(task_id,title)
{
	if (confirm("Are you sure you want to CLOSE task '" + title + "'?"))
	{
		document.location = "index.php?action=close&task_id=" + task_id;
	}
}

function startTask(task_id,t_status,title,is_form,completion,active_task_title,is_periodic, url_page)
{
	if (url_page == "undefined") {url_page = 'index.php';}
	if (t_status == "Stop")
	{
		if (!is_periodic)
		{
			x=0;
			do
			{
				x = prompt("Please enter completion status in % \nbefore STOPPING task '"+title+"'",completion);
			}
			while (!(x>=0 && x<=100));

			if(x!=null) document.location = url_page+"?action=stop&task_id=" + task_id + "&completion=" + x;
			//"index.php?action=stop&task_id=" + task_id + "&completion=" + x;
		}
		else
		{
			if (confirm("Are you sure you want to STOP task '" + title + "'?"))
				document.location = url_page+"?action=stop&task_id=" + task_id;
				//"index.php?action=stop&task_id=" + task_id;
		}
	}
	else
	{
		if (confirm("Are you sure you want to START task '" + title + "'?"))
		{
			if(!is_periodic)
			{
				x=0;
				if (active_task_title)
				{
  				  do
				  {
				    x = prompt("Please enter completion status %\nbefore stopping ACTIVE task '"+active_task_title+"'",completion);
				  } while (!(x>=0 && x<=100));
				}
				if (x!=null) {
					document.location = url_page+"?action=start&task_id=" + task_id + "&completion=" + x;
				}
				//"index.php?action=start&task_id=" + task_id + "&completion=" + x;
			}
			else {
				document.location = url_page+"?action=start&task_id=" + task_id;
			}
			//"index.php?action=start&task_id=" + task_id;
		}
	}
	if (is_form) return false;
}

function isDigit(num)
{
	if (num.length>1){return false;}
	var digits="1234567890";
	if (digits.indexOf(num)!=-1) return true;
	return false;
}

function isDigitOrDot(num)
{
	if (num.length>1){return false;}
	var digits="1234567890.";
	if (digits.indexOf(num)!=-1) return true;
	return false;
}

function isDHMS(num)
{
	if (num.length>1){return false;}
	var smbs="dhmw ";
	if (smbs.indexOf(num)!=-1) return true;
	return false;
}

function checkAdditionalTime(thiselem,thisform,uhelem,submitelement)
{
	/* additional time parser */

	var error=false;
	var bcolor="";
	var days=0;
	var hours=0;
	var minutes=0;
	var weeks=0;
	var mcolon;
	var uhours=0;
	var number=0;
	var hstr='';
	var mstr='';
	var m1,m2;
	var i=0;
	var text=thiselem.value;
	var disabledstatus;
	var tlen=0;

	if (submitelement) disabledstatus=submitelement.disabled;

	/* if there is some text */

	if (text && text.length>0)
	{
	   /* replace
	              , -> .
	         day(s) -> d
	        hour(s) -> h
	    min(ute)(s) -> m	    */
		tlen=text.length;

		text=text.toLowerCase();
		text=text.replace(/(\,)/gi,".");
		text=text.replace(/(day)s?/gi,"d");
		text=text.replace(/(hour)s?/gi,"h");
		text=text.replace(/(minute)s?/gi,"m");
		text=text.replace(/(min)s?/gi,"m");
		text=text.replace(/(week)s?/gi,"w");

		error=false;

		i=text.indexOf(":");
		while (i>0 && !error)
		{
			m1=text.charAt(i+1); m2=text.charAt(i+2); m3=text.charAt(i+3);

			if (m3&&isDigit(m3)) error=true;

			hstr='';
			j=i-1;
			while (j>=0 && isDigit(text.charAt(j))) hstr=text.charAt(j--)+hstr;

			if (isDigit(m1)&&isDigit(m2)) mstr=m1+m2; else mstr='';

			if (mstr.length==2&&hstr.length)
			{
			  mcolon=parseInt(mstr);
			  if (mcolon<60 && mcolon>=0) minutes+=mcolon; else error=true;
			  hours+=parseInt(hstr);
			  text=text.substring(0,i-hstr.length)+"|"+text.substring(i+3,text.length);
			}
			else error=true;

			i=text.indexOf(":");
		}

		number=0;
		bend=true;
		while (text.length>0 && !error)
		{
			hstr='';
			j=0;
			/*skip leading spaces and '|' symbols */
			while (j<text.length && (text.charAt(j)==" " || text.charAt(j)=="|")) {j++};
			if (j) text=text.substring(j,text.length);

			if (text.length)
			{
				j=0;
				dotcount=0;
				while (j<text.length && (isDigitOrDot(text.charAt(j) || text.charAt(j)==" ")) && dotcount<=1)
				{
					hstr+=text.charAt(j);
					if(text.charAt(j++)==".") dotcount++;
				}

				if (j)
				{
					number=parseFloat(text);
					if (isNaN(number)) number=0;
					text=text.substring(j,text.length);

					hms=false;
					k=0;
					while (k<text.length && isDHMS(text.charAt(k)) && !hms && !isDigitOrDot(text.charAt(k)))
					{
						switch(text.charAt(k))
						{
						  case "d": days+=number;   hms=true;break;
						  case "h": hours+=number;  hms=true;break;
						  case "m": minutes+=number;hms=true;break;
						  case "w": weeks+=number;  hms=true;break;
						}
						k++;
					}
					if (!hms) hours+=number;

					text=text.substring(k,text.length);
				} else error=true;
			}
		}

		if (days<0    || days>250)	error=true;
		if (hours<0   || hours>2000)	error=true;
		if (minutes<0 || minutes>120000)error=true;
		if (weeks<0   || weeks>50)	error=true;

		uhours=weeks*40+days*8+hours+minutes/60;

		if (uhours<0.2 || uhours>2000)	error=true;

	}

	if (error)
	{
	   bcolor="#fcc";
	   if(submitelement) submitelement.disabled=true;
	}
	else
	{
	   bcolor="#cfc";
	   if(submitelement) submitelement.disabled=false;
	}

	if(!tlen) bcolor="";

	thiselem.style.backgroundColor=bcolor;

	if (!error && uhours) uhelem.value=uhours; else uhelem.value=0;

/*	status="Weeks:"+weeks+"  days:"+days+"  hours:"+hours+"  minutes:"+minutes+"   ERROR:"+error;*/
	return !error;
}

	function changeStatus(checkedStatus)
	{
		/*var checkedStatus = document.tasks.all_tasks_1.checked;*/
		var tasksNumber = document.tasks.tasks_number.value;
		for (var i = 1; i <= tasksNumber; i++) {
			document.tasks.elements["id_" + i].checked = checkedStatus;
		}
		if (document.tasks.all_tasks_1) {
			document.tasks.all_tasks_1.checked = checkedStatus;
		}
		if (document.tasks.all_tasks_2) {
			document.tasks.all_tasks_2.checked = checkedStatus;
		}
		
		checkTasks();
	}

	function checkTasks()
	{
		var taskId = "";
		var tasksIds = "";
		var tasksNumber = document.tasks.tasks_number.value;
		var totalSelected = 0;
		/*alert(document.tasks.elements);*/
		for (var i = 1; i <= tasksNumber; i++) {			
			if (document.tasks.elements["id_" + i].checked) {
				totalSelected++;
				taskId = document.tasks.elements["id_" + i].value;
				if(tasksIds != "") { tasksIds += ","; }
				tasksIds += taskId;
			}
		}
		var closetasksLink = document.getElementById("close_tasks_link");
		document.tasks.tasks_selected.value = tasksIds;
		if (closetasksLink) {
			if (tasksIds == "") {
				closetasksLink.innerHTML = "<font class=\"ColumnFONT\">Close Selected (0)</font>";
				closetasksLink.href = "index.php";				
			} else {
				closetasksLink.innerHTML = "<font class=\"ColumnFONT\">Close Selected (" + totalSelected + ")</font>";
				closetasksLink.href = "index.php?action=close&task_ids=" + tasksIds;				
			}
		}		
	}	
	