	function openUpload() {		
		var hash = $("hash").value;
		var UploadWin = window.open ('upload.php?message_number=1&hash=' + hash, 'UploadWin', 'toolbar=no,location=no,directories=no,status=yes,menubar=no,scrollbars=yes,resizable=yes,width=500,height=400');
		UploadWin.focus();
	}
	
	function onChangeProject() {
		saveHidden();
		loadSubProjects();
	}
	function onChangeSubProject() {
		saveHidden();
		loadUsers();
	}
	function onChangeFilters() {
		loadParentProjects();
		loadSubProjects();
	}
	function onChangeUser() {
		saveHidden();
		reloadPriorities();
	}
	
	function saveHidden() {
		var project_id = $("project_id") ? $("project_id").value : 0;
		$("hidden_project_id").value = project_id;
		var sub_project_id = $("sub_project_id") ? $("sub_project_id").value : 0;
		$("hidden_sub_project_id").value = sub_project_id;
		var responsible_user_id = $("responsible_user_id") ? $("responsible_user_id").value : 0;
		$("hidden_responsible_user_id").value = responsible_user_id;
	}
	function loadClients() {
		alert("todo");
	}
	function setClient(client_id, client_desc){
		$("client_id").value = client_id;
		$("client_desc").update(client_desc);
	}
	function editProject() {
		var project_id     = $("project_id").value;
		if ($("sub_project_id")) {
			var sub_project_id = $("sub_project_id").value;
		} else {
			var sub_project_id = 0;
		}
		var task_id        = $("task_id").value;
		if (sub_project_id > 0) {
			var href ='edit_project.php?project_id=' + sub_project_id + '&return_page=create_task.php';
		} else if (project_id) {
			var href ='edit_project.php?project_id=' + project_id + '&return_page=create_task.php';
		} else {
			return false;
		}
		if (task_id > 0) {
			href+="?task_id=" + task_id;
		} else {
			href+="?" + $('task_form').serialize();
		}
		location.href = href;
	}
	
	function loadParentProjects() {
		new Ajax.Request('create_task.php?action=get_projects_list',
		{
			method:     'get',
			parameters:
			{
				'project_id': $("project_id").value,
				'project_filter_my' : $("project_filter_my").value,
				'project_filter_in_progress' : $("project_filter_in_progress").value
			},
			onSuccess: function(transport){
				var response = transport.responseText || "no response text";
				$("project_block").update(response);
				loadUsers();
			},
			onFailure: function(){
				$("project_block").update("Something went wrong...");
			}
		});
	}
	
	function loadSubProjects() {
		var project_id     = $("project_id").value;
		if (project_id) {
			new Ajax.Request('create_task.php?action=get_subprojects_list',
			{
				method:     'get',
				parameters:
				{
					'project_id': project_id,
					'sub_project_id' : $("sub_project_id") ? $("sub_project_id").value : 0,
					'project_filter_my' : $("project_filter_my").value,
					'project_filter_in_progress' : $("project_filter_in_progress").value
				},
				onSuccess: function(transport){
					var response = transport.responseText || "no response text";
					$("sub_project_block").update(response);
					loadUsers();
				},
				onFailure: function(){
					$("sub_project_block").update("Something went wrong...");
				}
			});
		}
	}
	
	function loadUsers() {
		var sub_project_id = $("sub_project_id") ? $("sub_project_id").value : 0;
		if (sub_project_id > 0) {
			new Ajax.Request('create_task.php?action=get_projectusers_list',
			{
				method:     'get',
				parameters:
				{
					'project_id':          $("project_id").value,
					'sub_project_id' :     sub_project_id,
					'task_id':             $("task_id").value,
					'responsible_user_id': $("responsible_user_id") ? $("responsible_user_id").value : 0
				},
				onSuccess: function(transport){
					var response = transport.responseText || "no response text";
					$("responsible_user_block").update(response);
					reloadPriorities();
				},
				onFailure: function(){
					$("responsible_user_block").update("Something went wrong...");
				}
			});
		} else {
			$("responsible_user_block").update("Please choose sub-project");
			responsible_user_shown = 0;
			$("responsible_user_priorities").hide();
		}
	}
	
	var responsible_user_shown = 0;
	function reloadPriorities() {
		var responsible_user_id = $("responsible_user_id") ? $("responsible_user_id").value : 0;
		if (responsible_user_shown) {
			if (responsible_user_id > 0) {
				if (responsible_user_shown != responsible_user_id) {
					loadPriorities();
				}
			} else {
				responsible_user_shown = 0;
				$("responsible_user_priorities").hide();
			}
		} else {
			$("responsible_user_priorities").hide();
		}
	}
	function loadPriorities() {
		var responsible_user_id = $("responsible_user_id") ? $("responsible_user_id").value : 0;
		if (responsible_user_id > 0) {
			if (responsible_user_shown == responsible_user_id) {
				$("responsible_user_priorities").hide();
				$("task_type_id").show();
				responsible_user_shown = 0;
			} else {
				responsible_user_shown = responsible_user_id;
				new Ajax.Request('ajax_responder.php?action=get_tasks_list',
				{
					method:     'get',
					parameters:
					{
						'user_id': responsible_user_id
					},
					onSuccess: function(transport){
						var response = transport.responseText || "no response text";
						$("responsible_user_priorities").update(response);
						$("responsible_user_priorities").show();
						$("task_type_id").hide();
					},
					onFailure: function(){
						$("responsible_user_priorities").update("Something went wrong...");
					}
				});
			}
		} else {
			$("responsible_user_priorities").update("Please choose user");
			$("task_type_id").show();
		}
	}
	function moveTask(task_id, dir) {
		var responsible_user_id = $("responsible_user_id") ? $("responsible_user_id").value : 0;
		if (responsible_user_id > 0) {
			new Ajax.Request('ajax_responder.php?action=get_tasks_list&operation=move',
			{
				method:     'get',
				parameters:
				{
					'user_id': responsible_user_id,
					'task_id': task_id,
					'dir': dir
				},
				onSuccess: function(transport){
					var response = transport.responseText || "no response text";
					$("responsible_user_priorities").update(response);
					$("responsible_user_priorities").show();
					$("task_type_id").hide();
				},
				onFailure: function(){
					$("responsible_user_priorities").update("Something went wrong...");
				}
			});
		}
	}
	
	var task_estimated_time_timeout;
	var task_estimated_time_loading = false;
	function taskEstimatedTimePressed() {
		clearTimeout(task_estimated_time_timeout);
		var task_estimated_time_timeout = setTimeout("taskEstimatedTimeLoad()", 100);
	}
	function taskEstimatedTimeLoad() {
		var task_estimated_time = $("task_estimated_time").value;
		if (task_estimated_time.length && task_estimated_time!=task_estimated_time_loading) {
			task_estimated_time_loading = task_estimated_time;
			clearTimeout(task_estimated_time_timeout);
			new Ajax.Request('create_task.php?action=get_estimated_hours',
			{
				method:     'get',
				parameters:
				{
					'task_estimated_time': task_estimated_time
				},
				onSuccess: function(transport){
					var response = transport.responseText || "no response text";
					if (response > 0) {
						$("task_estimated_time").setStyle({backgroundColor : '#CCFFCC'});
					} else {
						$("task_estimated_time").setStyle({backgroundColor : '#FFCCCC'});
					}
				}
			});
		}
	}
	function taskDomainsHide() {
		$("task_domains").hide();
	}
	function selectDomain(domain_id, domain_url) {
		$("task_domain").value = domain_url;
		taskDomainsHide();
	}
	
	var task_domains_timeout;
	var task_domains_loading_domain = false;
	function taskDomainPressed() {
		clearTimeout(task_domain_timeout);
		var task_domain_timeout = setTimeout("taskDomainLoad()", 1000);
	}
	function taskDomainLoad() {
		var domain = $("task_domain").value;
		if (domain.length && domain !=task_domains_loading_domain) {
			task_domains_loading_domain = domain;
			$("task_domains").show();
			$("task_domains").update("fetching domains list ...");
			clearTimeout(task_domains_timeout);
			new Ajax.Request('ajax_responder.php?action=get_domains_list',
			{
				method:     'get',
				parameters:
				{
					'domain': domain
				},
				onSuccess: function(transport){
					var response = transport.responseText || "no response text";
					$("task_domains").update(response);
					var task_domains_timeout = setTimeout("taskDomainsHide()", 25000);
				},
				onFailure: function(){
					$("task_domains").update("Something went wrong...");
					var task_domains_timeout = setTimeout("taskDomainsHide()", 5000);
				}
			});
		}
	}
	function taskDomainsHide() {
		$("task_domains").hide();
	}
	function selectDomain(domain_id, domain_url, client_id, client_name) {
		$("task_domain").value = domain_url;
		$("client_id").value   = client_id;
		$("client_desc").update(client_name);s
		taskDomainsHide();
	}
	
	function changeType(type_id) {
		if (type_id == 4) {
			if ($("task_title").value.substring(0,10) != "Quotation:" ) {			
				$("task_title").value = "Quotation: " + $("task_title").value;
			}
		}
	}