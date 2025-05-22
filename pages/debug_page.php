<?php
namespace PPtRR\ExternalModule;
$doc_id = $_GET['doc_id'];

// HACK; this entire page is a hack

function queryWrapper(string $sql, array $params, $module): array {
	$result = $module->query($sql, $params);

	$accumulator = [];
	while ($row = $result->fetch_assoc()) {
		$accumulator[] = $row;
	}

	return $accumulator;
}

echo "Server EDOC_PATH: " . EDOC_PATH;

echo "</br>";

$sql = "SELECT * from redcap_edocs_metadata WHERE doc_id = ? LIMIT 1";

if (is_null($doc_id)) {

	echo "To investigate a single doc, set a valid doc_id as a GET parameter or click one of the 'focus on this file' links.";
	echo "</br>";
	echo "</br>";

	echo "<h2>RECORD FILE DETAILS</h2>";
	echo "</br>";

	$module->getCredentials(); // init Proj for next step
	$file_fields = array_keys($module->getFileFields());
	$get_data_params = [
		"project_id" => PROJECT_ID,
		"return_format" => "array",
		"fields" => $file_fields
	];
	$gdr = \REDCap::getData($get_data_params);

	foreach($gdr as $record_id => $event) {
		echo "<h3>=== Record ===</h3>";
		// echo "</br>";
		echo "#: {$record_id}";
		echo "</br>";
		foreach($file_fields as $file_field) {
			// HACK: remove unpopulated fields
			$vals = array_diff(
				array_column($event, $file_field),
				[""]
				);
			if (empty($vals)) { continue; }
			echo "<h4>--- Field ---</h4>";
			// echo "</br>";
			echo "Field: {$file_field}";
			var_dump($vals);
			echo "</br>";

			$doc_id = $vals[0];
			echo "focus on this file:";
			echo "</br>";
			$this_doc_get_page = $module->getUrl("pages/debug_page.php") . "&doc_id={$doc_id}";
			echo "<a href='{$this_doc_get_page}'>{$this_doc_get_page}</a>";
			echo "</br>";
			$edocs_tbl = queryWrapper($sql, [$doc_id], $module)[0];
			echo "file data dump";
			echo "</br>";
			var_dump($edocs_tbl);
		}
	}


	echo "<h2>FILE REPOSITORY DETAILS</h2>";
	echo "</br>";

	$CCFR = new CCFileRepository($module);
	$local_file_repo = $CCFR->getLocalFileRepo() ?? [];

	array_unshift(
		$local_file_repo,
		[
			"name" => "<root>",
			"folder_id" => null
		]
	);


	foreach($local_file_repo as $_ => $file_repo_dir) {
		$folder_id = $file_repo_dir["folder_id"];
		echo "<h3>=== Folder ===</h3>";
		echo "folder id: {$folder_id}";
		echo "</br>";
		var_dump($file_repo_dir);
		$docs_in_folder = $CCFR->getFileRepositoryFolderContents($folder_id);

		echo "- - -";
		echo "</br>";
		echo "folder contents:";
		echo "</br>";

		foreach($docs_in_folder as $_ => $doc_id) {
			echo "<h4>--- File ---</h4>";
			echo "</br>";
			echo "doc_id: <b>{$doc_id}</b>";
			echo "</br>";
			echo "focus on this file:";
			echo "</br>";
			$this_doc_get_page = $module->getUrl("pages/debug_page.php") . "&doc_id={$doc_id}";
			echo "<a href='{$this_doc_get_page}'>{$this_doc_get_page}</a>";
			echo "</br>";
			$edocs_tbl = queryWrapper($sql, [$doc_id], $module)[0];
			echo "file data dump";
			echo "</br>";
			var_dump($edocs_tbl);

			echo "</br>";
			$cfile = curl_file_create(EDOC_PATH . $edocs_tbl['stored_name'], $edocs_tbl['mime_type'], $edocs_tbl['doc_name']);
			echo "</br>";
			echo "curlfile dump";
			echo "</br>";
			var_dump($cfile);
		}
	}
	die;
}

echo "Focus on doc id: {$doc_id}";
echo "</br>";

$edocs_tbl = queryWrapper($sql, [$doc_id], $module)[0];

echo "file data dump";
echo "</br>";
var_dump($edocs_tbl);

$cfile = curl_file_create(EDOC_PATH . $edocs_tbl['stored_name'], $edocs_tbl['mime_type'], $edocs_tbl['doc_name']);
echo "</br>";
echo "curlfile dump";
echo "</br>";
var_dump($cfile);

echo "attempt to download with this link:";
echo "</br>";

$dl_link = $module->getUrl("pages/debug_doc_dl.php") . "&doc_id={$doc_id}";

echo "<a href='{$dl_link}'>{$dl_link}</a>";
echo "</br>";
