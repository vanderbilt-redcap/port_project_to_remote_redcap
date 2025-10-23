<?php

// initialization step, not actually used
$creds = $module->setProjectCredentials(0);

[$pid, $zip_fp] = $module->dumpLogs();
$zip_loc = stream_get_meta_data($zip_fp)['uri'];

header("Content-type: application/zip");
header("Content-Disposition: attachment; filename=project_{$pid}_dump.zip");
header("Content-Length: " . filesize($zip_loc));
// echo(file_get_contents($zip_loc));
readfile($zip_loc);
