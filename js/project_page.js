$(document).ready(function() {
	const task_list = ["port_records", "port_users", "port_file_repository", "store_logs"];
	let n_update_steps = task_list.length + 1;

  let i = 0;
  let remote_select = $("#remote_select")[0];
  remote_list.forEach(e => {
    console.log(e);
    remote_select.innerHTML += `<option value=${i}>${e}</option>`;
    i++;
  });

	const form = $("#crispi_form")[0];

	// NOTE: bugs in this section will result in a redirect due to altering the form
	$(form).on('submit', (event) => {
		event.preventDefault();

		// prevent resubmitting while xfer is occurring
		$($(form).children("button")[0]).prop("disabled", true);

		$("#status-updates").show();
		initializeProgress("update_remote_project_design");
    // TODO: allow override of task_list with checkboxes
		// const task_list = ["port_file_repository"];
		task_list.forEach((task) => {initializeProgress(task); });

    // port project design, this must be done before records
		portDesign().then(() => {
			performTasks(task_list);
		});

    // TODO: consider allowing additional submission attempts
		// will need to reset the progress div to support this
		$($(form).children("button")[0]).prop("disabled", false);
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
		} else {
			status_div.addClass("alert-success");
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
			ul.append(`<li class="list-group-item">${k}: ${response_obj[task][k]}</li>`);
		}

		status_div.append(ul);
	}

  // TODO: add sections to bar, use response to colorize section
	function updateProgressBar(n_steps = 4) {
		let cur_prog = parseInt($(".progress-bar").attr("aria-valuenow"));
		let increment = 100 / n_steps;
		cur_prog += increment;

		let element = $("#port-project-progress-bar");
		element
			.css('width', `${cur_prog}%`)
			.attr("aria-valuenow", cur_prog);

		if (cur_prog >= 100) {
		element
			.css('width', "100%")
			.attr("aria-valuenow", 100)
			.removeClass("progress-bar-animated")
			.removeClass("progress-bar-striped")
			.addClass("bg-success");
		}
	}

});
