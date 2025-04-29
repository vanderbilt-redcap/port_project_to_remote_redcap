$(document).ready(function() {

	const module = ExternalModules.PPtRR.ExternalModule;
  let remote_list = module.tt("remote_list");
  let source_project_list = module.tt("source_project_list");
  let pptr_endpoint = module.tt("pptr_endpoint");

	const form = $("#crispi_admin_form")[0];
	let remote_select = $("#remote_select")[0];

  // populate dropdowns
  let i = 0;
	remote_list.forEach(e => {
		console.log(e);
		remote_select.innerHTML += `<option value=${i}>${e}</option>`;
		i++;
	});

	let source_select = $("#source_project_select")[0];
	source_project_list.forEach(e => {
		source_select.innerHTML += `<option value=${e}>${e}</option>`;
	});

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
			success: (msg) => {
				setProjectPageLink(entries);
			}
		});
	}

	function setProjectPageLink(entries) {
		let project_url = `${module.getUrl('pages/project_page.php')}&pid=${entries.source_project}`;

		$("#source-project-config-page")
			.attr("href", project_url)
			.show();
	}
	
});
