<?php

namespace PPtRR\ExternalModule;

$creds = $module->getCredentials((int) $_POST["remote_index"]);

if ($_POST["flush_records"]) {
    $records_flushed = $module->flushRemoteRecords($creds);
}


$fields = $module->updateRemoteMetadata($creds);
$records_pushed = $module->portRemoteRecords($creds);
$meta_conf = $module->dumpMetadataToFileRepository($creds);

$report_arr = [];

if ($records_flushed) {
    $report_arr["Records flushed from remote project"] = $records_flushed;
}
$report_arr["Fields sent to remote project"] = $fields;
$report_arr["Records sent to remote project"] = $records_pushed;
$report_arr["Logs sent to remote project"] = $meta_conf;

foreach ($report_arr as $label => $value) {
    echo $label . ": " . $value;
    echo "</br>";
}
