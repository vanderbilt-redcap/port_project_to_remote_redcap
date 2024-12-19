<?php

namespace PPtRR\ExternalModule;

$creds = $module->getCredentials((int) $_POST["remote_index"]);

if ($_POST["flush_records"]) {
    $records_flushed = $module->flushRemoteRecords($creds);
}

$report_arr = [];

$project_design = $module->updateRemoteProjectDesign($creds);
$report_arr["project_design"] = $project_design;

$records_pushed = $module->portRemoteRecords($creds);
$module->log("portRemoteRecords");
$report_arr["Records sent to remote project"] = $records_pushed;

$meta_conf = $module->dumpMetadataToFileRepository($creds);
$module->log("dumpMetadataToFileRepository");
$report_arr["Logs sent to remote project"] = $meta_conf;


if ($records_flushed) {
    $report_arr["Records flushed from remote project"] = $records_flushed;
}

foreach ($report_arr as $label => $value) {
    echo $label . ": " . json_encode($value);
    echo "</br>";
}
