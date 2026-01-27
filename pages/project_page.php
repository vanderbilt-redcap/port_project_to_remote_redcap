<?php

namespace Vanderbilt\PortProjectToRemoteREDCap\ExternalModule;

$default_warning_text = "If you are moving your project to a REDCap instance that is outside of your organization's control, please be aware of any data governance policies that may restrict data transfer by institution or region.";

$jsmo = $module->framework->initializeJavascriptModuleObject();
echo $jsmo;

$remote_list = $module->framework->getProjectSetting("remote_api_uri");

$jsmo_settings = [
	"pptr_endpoint" => $module->framework->getUrl("ajax.php"),
	"remote_list" => $remote_list
];
$module->setJsSettings($jsmo_settings);

$warning_text = $module->framework->getSystemSetting("warning_text") ?? $default_warning_text;
echo $warning_text;

$module->includeCss("css/project_page_accordion.css");
include("project_page.html");
$module->includeJs("js/project_page.js");

?>

<table class="table table-striped table-bordered">
	<caption style="caption-side: top;">Previous task results</caption>
	<thead>
		<tr>
			<th scope="col">time</th>
			<th scope="col">task</th>
			<th scope="col">result</th>
		</tr>
	</thead>
	<tbody>

		<?php

		$recent_logs = $module->getRecentLogs();
		$ts = $recent_logs[0]['timestamp'];

		foreach ($recent_logs as $f) {
			$row_str = <<<_html
				<tr>
				<td>{$f['timestamp']}</td>
				<td>{$f['message']}</td>
				<td><pre>{$f['value']}</pre></td>
				</tr>
			_html;
			echo $row_str;
		}
		?>
	</tbody>
</table>
