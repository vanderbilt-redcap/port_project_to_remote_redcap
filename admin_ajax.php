<?php

namespace Vanderbilt\PortProjectToRemoteREDCap\ExternalModule;

$source_project_id = $_POST["source_project"];
$remote_index = $_POST["remote_index"];

$st = new SuperToken($module, $source_project_id);
$result = $st->main($remote_index);

echo json_encode($result);
die;
