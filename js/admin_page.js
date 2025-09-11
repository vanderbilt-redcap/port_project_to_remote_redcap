$(document).ready(function() {

	const module = ExternalModules.PPtRR.ExternalModule;
  let remote_list = module.tt("remote_list");
  let source_project_list = module.tt("source_project_list");
  let pptr_endpoint = module.tt("pptr_endpoint");

	const form = $("#crispi_admin_form")[0];
	let remote_select = $("#remote_select")[0];
	let source_select = $("#source_project_select")[0];

	// populate dropdowns
	let i = 0;
	remote_list.forEach(e => {
		remote_select.innerHTML += `<option value=${i}>${e}</option>`;
		i++;
	});

	// TODO: properly rename artifacts from project context
	// TODO: make a unified class/lib for shared functionality between admin and project
	for (const [pid, title] of Object.entries(source_project_list)) {
		source_select.innerHTML += `<option value=${pid}>${title}</option>`;
	}

	$(form).on('submit', (event) => {
		event.preventDefault();
		submitAjax();
	});

	async function submitAjax() {
		const form = $("#crispi_admin_form")[0];
		const formData = new FormData(form);
		const entries = {...Object.fromEntries(formData.entries())};

		await $.ajax({
			type: "POST",
			url: pptr_endpoint,
			data: {
				// "task": task,
				// pass all form items
				...Object.fromEntries(formData.entries())
			},
			error: (jqxhr, status, err) => {
				reportError(jqxhr);
			},
			success: (msg) => {
				setProjectPageLink(entries);
			}
		});
	}

	function reportError(jqxhr) {
		let response = jqxhr.responseJSON;

		let status_div = $("#status-update-alert-template").clone();
		status_div.attr("id", "status-update");
		status_div
			.addClass("alert-danger")
			.text(response.error)
			.show();

		$("#status-updates")
			.append(status_div)
			.show();

		$("#port-project-progress-bar")
			.removeClass("progress-bar-striped")
			.addClass("bg-danger")
			.attr("style", "width: 100%");
	}

	function setProjectPageLink(entries) {
		let project_url = `${module.getUrl('pages/project_page.php')}&pid=${entries.source_project}`;

		$("#source-project-config-page")
			.attr("href", project_url)
			.show();
	}
	
});
