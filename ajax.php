<?php

namespace PPtRR\ExternalModule;

$creds = $module->getCredentials((int) $_POST["remote_index"]);
$report_arr = [];

$task = $_POST["task"];
$report_arr[$task] = [];

switch ($task) {
case "update_remote_project_design":
	if ($_POST["flush_records"]) {
		$records_flushed = $module->flushRemoteRecords($creds);
	}
	$project_design = json_decode($module->updateRemoteProjectDesign($creds), true);
	$report_arr[$task] = $project_design;
	$report_arr[$task]["records_flushed"] = $records_flushed;
	break;
case "port_users":
	$report_arr[$task]["users"] = json_decode($module->portUsers($creds), true);
	$module->log("portUsers");
	$report_arr[$task]["user_roles"] = json_decode($module->portUserRoles($creds), true);
	$module->log("portUserRoles");
	break;
case "port_records":
	$records_pushed = $module->portRemoteRecords($creds);
	$module->log("portRemoteRecords");
	$report_arr[$task] = $records_pushed;
	break;
case "store_logs":
	$meta_conf = $module->dumpMetadataToFileRepository($creds);
	$module->log("dumpMetadataToFileRepository");
	$report_arr[$task] = $meta_conf;
	if ($_POST['new_status']) {
		// TODO: only address this after all actions are completed
	}
	break;
	default:
		$report_arr["error"] = "Not a valid action";
}

echo json_encode($report_arr);
die;



