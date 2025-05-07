$(document).ready(function() {
	const module = ExternalModules.PPtRR.ExternalModule;
  let cur_prog = 0;
  let bypass_design = false;

  // TODO: allow individual toggling of these tasks, perhaps after initial run
	const potential_task_list = ["update_remote_project_design", "port_records", "port_users", "port_dags", "port_file_repository", "store_logs"];
	let task_list = potential_task_list;
	let n_update_steps = task_list.length;

  const pptr_endpoint = module.tt("pptr_endpoint");
  const remote_list = module.tt("remote_list");

  buildAccordionTaskList();

	let remote_select = $("#remote_select")[0];
  remote_list.forEach((e, idx) => {
		remote_select.innerHTML += `<option value=${idx}>${idx}: ${e}</option>`;
		getRemoteProjectInfo(idx);
  });

	const form = $("#crispi_form")[0];

	// NOTE: bugs in this section will result in a redirect due to altering the form
	$(form).on('submit', (event) => {
		event.preventDefault();

		task_list = setTaskListFromForm();
		n_update_steps = task_list.length;

		let design_idx = task_list.indexOf("update_remote_project_design");
		bypass_design = (design_idx === -1);

		// remove from task list to avoid duplicate run; design must preempt all other tasks
		if (!bypass_design) { task_list.splice(design_idx, 1); }

		// prevent resubmitting while xfer is occurring
		const transfer_button = $($(form).children("button")[0]);
		transfer_button.prop("disabled", true);

		$("#status-updates").show();
		if (!bypass_design) {
			initializeProgress("update_remote_project_design");
		}
		task_list.forEach((task) => {initializeProgress(task); });

		if (bypass_design) {
			performTasks(task_list);
		} else {
			// this typically must be done before records as it updates the data dictionary
			portDesign().then(() => {
				performTasks(task_list);
			});

		}

		// TODO: consider allowing additional submission attempts
		// will need to reset the progress div to support this
		// NOTE: this can't be done here since everything else is async
		// transfer_button.prop("disabled", false);

		// must return false to prevent form actually submitting
		return false;
	});

	async function performTasks(task_list) {
		const form = $("#crispi_form")[0];
		const formData = new FormData(form);
		for (const task of task_list) {
			beginProgress(task);

			await $.ajax({
				type: "POST",
				url: pptr_endpoint,
				data: {
					"task": task,
					// pass all form items
					...Object.fromEntries(formData.entries())
				},
				success: (msg) => {
					updateProgress(msg, n_update_steps);
				}
			});
		}
	}

	async function portDesign() {
		// must be done as async await with then as design has to match records
		await performTasks(["update_remote_project_design"]);
	}

	///////////////////////////////////////////////////////////////////////////////
	//                                  Status update controls                   //
	///////////////////////////////////////////////////////////////////////////////

	function initializeProgress(task) {
		let status_div = $("#status-update-alert-template").clone();

		status_div.attr("id", `status-update-${task}`);

		status_div
			.addClass("alert-secondary")
			.show();

		status_div.text(task);

		$("#status-updates")
			.append(status_div);
	}

	function beginProgress(task) {
		let status_div = $(`#status-update-${task}`);

		status_div
			.removeClass("alert-secondary")
			.addClass("alert-info");
	}

	function updateProgress(response, n_steps = 5) {
		updateProgressBar(n_steps);
		updateInformation(response);
	}

	function updateInformation(response) {
		let response_obj = JSON.parse(response);

		let task = Object.keys(response_obj)[0];

		let status_div = $(`#status-update-${task}`);

		status_div.removeClass("alert-info");

		// lazily search for "error" instead of recursively checking key names
		// https://stackoverflow.com/a/52984049/7418735
		if( response.indexOf("error") > -1 ) {
			status_div.addClass("alert-danger");
			status_div.prepend(`<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Danger:"><use xlink:href="#exclamation-triangle-fill"/></svg>`);
		} else {
			status_div.addClass("alert-success");
			status_div.prepend(`<svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Success:"><use xlink:href="#check-circle-fill"/></svg>`)
		}

		let ul = $("<ul class='list-group'></ul>");

		// HACK: store_logs is an invalid key for some odd reason, resulting in a dump of the function to the UI, funny but confusing
		if (task === "store_logs") {
			ul.append(`<li class="list-group-item">${task}: complete</li>`);
			status_div.append(ul);
			return;
		}

    // TODO: make recursive for nested data, i.e. batched records, port_users
		for (const k in response_obj[task]) {
			let subtask_result = response_obj[task][k];

      // HACK: anything more complex than a string is now just a string
			if (typeof subtask_result === 'object' && subtask_result !== null) {
				subtask_result = `<code>${JSON.stringify(subtask_result)}</code>`;
			}

			ul.append(`<li class="list-group-item">${k}: ${subtask_result}</li>`);
		}

		status_div.append(ul);
	}

	// TODO: add sections to bar, use response to colorize section
	function updateProgressBar(n_steps = 4) {
		let increment = 100 / n_steps;
		cur_prog += increment;

		let element = $("#port-project-progress-bar");
		element
			.css('width', `${cur_prog}%`)
			.attr("aria-valuenow", cur_prog);

		// console.log(cur_prog);

		// HACK: workaround floating point issues accumulating in entire integers being missed
		if (cur_prog >= 99) {
			element
				.css('width', "100%")
				.attr("aria-valuenow", 100)
				.removeClass("progress-bar-animated")
				.removeClass("progress-bar-striped")
				.addClass("bg-success");

			// set up for another run without page reload
			cur_prog = 0;
		}
	}

  async function getRemoteProjectInfo(idx) {
		await $.ajax({
			type: "POST",
			url: pptr_endpoint,
			data: {
				"task": "get_remote_project_info",
				"remote_index": idx,
				"redcap_csrf_token": redcap_csrf_token
			},
			success: (msg) => {
				setRemoteProjectInfo(idx, msg);
        // TODO: activate select2 once all remotes have info
				// below is not reliable, typically results in at least 1 not finishing
        // if (idx === remote_select.length - 1) {
        //   $(remote_select).select2();
        // }
			}
		});
	}

	async function setRemoteProjectInfo(idx, msg) {
		let better_title = "something is wrong with this endpoint";

		// TODO: does not update title for item 0
		let target_option = $(`select#remote_select > option[value="${idx}"]`);
		try {
			let remote_project_info = JSON.parse(msg);
			let rpi1 = remote_project_info["get_remote_project_info"]
			let rpi2 = JSON.parse(rpi1["remote_project_info"]);

			if ("error" in rpi2) {
				better_title = rpi2["error"];
			} else {
				better_title = `PID: ${rpi2["project_id"]} | Title: ${rpi2["project_title"]}`;
			}

		} catch(error) {
      console.log(error);
			console.log(`${idx} is not json`);
			console.log(msg);
		} finally {
			target_option.text(
				`${target_option.text()} :: ${better_title}`
			);
		}
	}

	// deprecated, used if accordion for subtask toggles are not used
	// keeping this in case users desire the non-accordion UI
	function buildTaskList() {

		potential_task_list.forEach((task) => {

			let task_checkbox = $(
				$("#task_toggle_template_div")
					.clone()
			);

			task_checkbox
				.attr("id", `task_toggle_div-${task}`)
				.show();

			$(
				task_checkbox
					.find("input")[0]
			)
				.attr("id", `task_toggle-${task}`)
				// .attr("name", `task_toggle-${task}`)
				.attr("name", `${task}`)
				.show();

			$(
				task_checkbox
					.find("label")[0]
			)
				// .attr("for", `task_toggle-${task}`)
				.attr("for", `task_toggle-${task}`)
				.text(task);

			$("#task_toggles").append(task_checkbox);
		});
	}

	function buildAccordionTaskList() {
		potential_task_list.forEach((task) => {
			let task_checkbox = $(
				$("#task_toggle_template_div")
					.clone()
			);

			// set up main container
			task_checkbox
				.attr("id", `task_toggle_div-${task}`)
				.show();

			$(
				task_checkbox
					.find("input")[0]
			)
				.attr("id", `task_toggle-${task}`)
				.attr("name", `${task}`)
				.show();

			$(
				task_checkbox
					.find("label")[0]
			)
				.attr("for", `task_toggle-${task}`)
        .attr("data-bs-target", `#task_toggle_container-${task}-collapse`)
				.text(task);

			$(
				task_checkbox
			)
				.on('click', function(e) {
					uncheckChildCheckboxes(e);
				})

			$("#task_toggles").append(task_checkbox);

			// set up sub container(s)
			addTaskOptionToggles(task);
		});

  }

	function setTaskListFromForm() {

		const formData = new FormData(form);

		const form_selections = {...Object.fromEntries(formData.entries())};

		const task_toggles = Object.keys(form_selections).filter((k) => {
			// return k.indexOf("task_toggle") == 0;
			return potential_task_list.includes(k);
			// return (k !== "update_remote_project_design" && potential_task_list.includes(k));
			// return
		});
		// tasks_to_run.splice(0, 1); // remove template item

		return task_toggles;
		// return tasks_to_run;
	}

	function addTaskOptionToggles(task) {
		const delete_remote_records_info = {
			"name": "flush_records",
			"label": "Delete remote records before importing",
			"default": 0
		};
		const delete_remote_user_roles_info = {
			"name": "delete_user_roles",
			"label": "Delete remote user roles before importing",
			"default": 0
		};

		const task_option_map = {
			"update_remote_project_design": [
				{
					"name": "retain_title",
					"label": "Retain remote project title",
					"default": 1
				},
				delete_remote_records_info,
				delete_remote_user_roles_info
			],
			"port_records": [delete_remote_records_info],
			"port_users": [delete_remote_user_roles_info],
			"port_dags": [],
			"port_file_repository": [],
			"store_logs": []
		};

		task_options = task_option_map[task] ?? [];
		if (task_options === []) { return; }

		let task_options_container = $("#template-task_toggle_options")
				.clone();

		$(task_options_container)
			.attr("id", `task_toggle_options-${task}`);

		$("#task_toggles").append(task_options_container);

		// TODO: if [] unclassify as accordion
		task_options.forEach((task_option) => {

			let task_options_element = $(
				$(
					$(task_options_container)
						.find("#template-task_toggle_collapse")[0]
				)
					.clone()
			);

			task_options_element
				.attr("id", "")
				.attr("data-bs-parent", `#task_toggle_container-${task}-collapse`);

			// TODO: each sub-option has the same ID, breaking HTML rules
			// however, this is currently required to fold the fold options when the task is toggled off
			$(
				task_options_element
			)
				.attr("id", `task_toggle_container-${task}-collapse`);

			let input_field = $(
				task_options_element
					.find("input")[0]
			)
			input_field
				// .attr("id", `task_toggle-${task}-collapse`)
				.attr("id", `${task_option['name']}`)
				.attr("name", `${task_option['name']}`);
			if (task_option["default"] === 1) {
				input_field.attr("checked", "");
			}

			$(
				task_options_element
					.find("label")[0]
			)
				.attr("for", `${task_option['name']}`)
				.text(task_option['label']);

			task_options_element.show();

			// $("#task_toggles").append(task_options_element);
			$(task_options_container).append(task_options_element);
		});
	}

	function uncheckChildCheckboxes(parent_element) {
		const task_name = $(parent_element.delegateTarget).attr("id").substring(length("task_toggle_div-"));
		const target_element = $(`#task_toggle_options-${task_name}`);
		for (const [k, e] of Object.entries($(target_element.find("input[type='checkbox']")))) {
			try {
				$(e)
					.prop("checked", false);
			} catch (err) {
				// errors arise if target element isn't a checkbox
				// this is irrelevant, so the error is ignored
			}
		}
		return;
	}

});
