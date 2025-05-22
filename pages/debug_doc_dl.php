<?php
namespace PPtRR\ExternalModule;

$doc_id = $_GET["doc_id"];

function queryWrapper(string $sql, array $params, $module): array {
	$result = $module->query($sql, $params);

	$accumulator = [];
	while ($row = $result->fetch_assoc()) {
		$accumulator[] = $row;
	}

	return $accumulator;
}

$sql = "SELECT * FROM redcap_edocs_metadata WHERE doc_id = ? LIMIT 1";
$edocs_tbl = queryWrapper($sql, [$doc_id], $module)[0];

$cfile = curl_file_create(EDOC_PATH . $edocs_tbl['stored_name'], $edocs_tbl['mime_type'], $edocs_tbl['doc_name']);


header("Content-type: {$edocs_tbl['mime_type']}");
header("Content-Disposition: attachment; filename={$edocs_tbl['stored_name']}");
header("Content-Length: " . $edocs_tbl['doc_size']);

// can't readfile a curl_file
readfile($cfile->name);
