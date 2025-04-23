<?php

namespace PPtRR\ExternalModule;

$remote_list = $module->framework->getProjectSetting("remote_api_uri");

// $module->framework->loadJS();

$js_str = "<script> var remote_list = " . json_encode($remote_list) . "</script>";
echo $js_str;

$warning_text = $module->framework->getSystemSetting("warning_text") ?? "If you are moving your project to a REDCap instance that is outside of your organization's control, please be aware of any data governance policies that may restrict data transfer by institution or region.";

echo $warning_text;

// HACK: should properly use the JS module object for these data
echo "<script> var pptr_endpoint = '" . $module->framework->getUrl("ajax.php") . "';</script>";
echo "<script> var redcap_csrf_token = '" . $module->framework->getCSRFToken() . "';</script>";

?>
<!-- <form action="<?=$module->framework->getUrl("ajax.php") ?>" method="POST" id="crispi_form"> -->
<form id="crispi_form">
    <label for="remote_index" class="form-label">Target Server API URI</label>
    <select name="remote_index" class="form-select" aria-label="Select remote server" id="remote_select"></select>

	<!--
	<label for="new_status">Change project status after transfer</label>
	<select name="new_status" id="new_status" class="form-select status-control" aria-label"New Status">
	<option value="0" selected></option>
	<option value="1">Completed</option>
	<option value="2">Analysis/cleanup</option>
	</select>
	-->

	<div class="form-check">
	<input name="flush_records" class="form-check-input" type="checkbox" value="1" id="flush_records">
	<label class="form-check-label" for="flush_records">
	Flush records
	</label>
	</div>

	<div class="form-check">
	<!-- HACK: KYLE: default checked to make it easier -->
	<input name="retain_title" class="form-check-input" type="checkbox" value="1" id="retain_title" checked>
	<label class="form-check-label" for="retain_title">
	Retain project title
	</label>
	</div>


    <button type="submit" class="btn btn-primary">Transfer project</button>

</form>

</br>

<div id="status-updates" style="display: none;">
<div class ="progress">
	<div id="port-project-progress-bar" class="progress-bar progress-bar-animated progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
	</div>

	<div id="status-update-alert-template" class="status-update alert" role="alert" style="display: none;">
	</div>

</div>

<?
    $module->includeJs("js/project_page.js");
?>
