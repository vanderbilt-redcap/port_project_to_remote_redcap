<?php

namespace PPtRR\ExternalModule;

$creds = $module->getCredentials((int) $_POST["remote_index"]);
$report_arr = [];

$task = $_POST["task"];
$report_arr[$task] = [];

$perform_log = true;


switch ($task) {
	case "update_remote_project_design":
		if ($_POST["flush_records"]) {
			$records_flushed = $module->flushRemoteRecords($creds);
			$report_arr[$task]["records_deleted"] = $records_flushed;
		}
		$project_design = json_decode($module->updateRemoteProjectDesign($creds, (bool) $_POST["retain_title"]), true);
		$report_arr[$task] = $project_design;
		break;
	case "port_users":
		// user roles may prevent the repeated import of users if they have a role
		if ($_POST["delete_user_roles"]) {
			$report_arr[$task]["user_roles_deleted"] = $module->deleteUserRoles($creds);
		}
		$report_arr[$task]["users"] = json_decode($module->portUsers($creds), true);
		$report_arr[$task]["user_roles_imported"] = json_decode($module->portUserRoles($creds), true);
		$report_arr[$task]["user_roles_mapped"] = json_decode($module->portUserRoleAssignment($creds), true);
		break;
	case "port_records":
		if ($_POST["flush_records"]) {
			$records_flushed = $module->flushRemoteRecords($creds);
			$report_arr[$task]["records_deleted"] = $records_flushed;
		}
		$port_file_fields = (bool) $_POST["port_file_fields"];
		if (!((bool) $_POST["port_record_range"])) {
			$records_pushed = $module->portRemoteRecords($creds, null, $port_file_fields);
		} else {
			$record_list = $module->getRecordRange($_POST["record_range_start"], $_POST["record_range_end"]);
			$records_pushed = $module->portRecordList($creds, $record_list, null, $port_file_fields);
		}
		$report_arr[$task]["records_sent"] = $records_pushed;
		break;
	case "port_file_repository":
		// $module->createReservedFileRepoFolder();
		$port_result = json_decode($module->portFileRepository($creds));
		$report_arr[$task] = $port_result;
		break;
	case "store_logs":
		$meta_conf = $module->dumpLogsToFileRepository($creds);
		$report_arr[$task] = $meta_conf;
		if ($_POST['new_status']) {
			// TODO: only address this after all actions are completed
		}
		break;
	case "port_dags":
		$report_arr[$task]["dags_ported"] = $module->portDAGs($creds);
		$report_arr[$task]["dags_mapped"] = $module->portDAGMapping($creds);
		break;
	default:
		$report_arr["error"] = "Not a valid action: {$task}";
		// Utils for data display
		// no break
	case "get_remote_project_info":
		$perform_log = false; // this is used solely to add context to the project page dropdown menu
		$report_arr[$task]["remote_project_info"] = $module->getRemoteProjectInfo($creds);
		break;
	case "get_remote_project_record_ids":
		$perform_log = false; // this is used solely to add context to the project page dropdown menu
		$report_arr[$task]["remote_project_record_ids"] = $module->getRemoteProjectRecordList($creds);
		break;
	case "get_local_project_record_ids":
		$perform_log = false; // this is used solely to add context to the project page dropdown menu
		$report_arr[$task]["local_project_record_ids"] = $module->getAllRecordPks();
		break;
}

if ($perform_log) {
	// HACK: fw log doesn't like n>2 dimensional arrays :(
	$module->log($task, ["report_json" => json_encode($report_arr)]);
}
echo json_encode($report_arr);
die;
