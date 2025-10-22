<?php

namespace Vanderbilt\PortProjectToRemoteREDCap\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;
use ZipArchive;

require_once __DIR__ . '/src/CCFileRepository.php';
require_once __DIR__ . '/src/SuperToken.php';
require_once __DIR__ . '/src/CCUserManagement.php';
require_once __DIR__ . '/src/QueryBatcher.php';

class ExternalModule extends AbstractExternalModule
{
	public const RESERVED_FILE_REPO_DIR_NAME = "PPtRR_reserved_folder";

	private $file_fields = [];
	private $Proj;
	private $file_repo_structure;
	private $creds;
	private $remote_file_repo_folder = null;
	// private $testing_mode = true;
	private $testing_mode = false;

	public function setProjectCredentials($idx = 0, $source_project_id = null) {
		// this function is equivalent to module instantiation for Proj object purposes
		$creds = [];

		$project_settings = $this->framework->getProjectSettings($source_project_id);
		$parts = ["remote_api_uri", "remote_api_token"];

		foreach ($parts as $part) {
			$creds[$part] = $project_settings[$part][$idx];
		}

		$this->setCredentials($creds);
		$this->Proj = new \Project($source_project_id);

		return $creds;
	}


	public function setCredentials(array $credentials): void {
		$this->creds = $credentials;
	}


	public function getCredentials(): array {
		return $this->creds;
	}


	public function getSourceProjectId(): int {
		return $this->Proj->project_id;
	}


	public function createReservedFileRepoFolder() {
		// TODO: ensure this allows override with the same module object
		// better alternative would likely just be to have a pseudo-constructor function refresh everything
		$input_folder = [
			"name" => self::RESERVED_FILE_REPO_DIR_NAME
		];

		$CCFR = new CCFileRepository($this);

		// TODO: check if reserved folder exists
		$folder_creation_response = $CCFR->createRemoteFolder($input_folder);
		$this->remote_file_repo_folder = $folder_creation_response["folder_id"];
	}

	public function getReservedFileRepoFolder() {
		if (is_null($this->remote_file_repo_folder)) {
			$remote_file_repo = json_decode($this->getRemoteFileRepositoryDirectory(null), true);

			$found = false;
			foreach ($remote_file_repo as $dir) {
				if ($dir["name"] == self::RESERVED_FILE_REPO_DIR_NAME) {
					$found = true;
					$this->remote_file_repo_folder = $dir["folder_id"];
					break;
				}
			}

			if (!$found) {
				$this->createReservedFileRepoFolder();
				return $this->getReservedFileRepoFolder();
				// TODO: err if this results in more than 1 add'l stack frame
			}
		}

		return $this->remote_file_repo_folder;
	}

	public function dumpLogs() {
		$pid = $this->getSourceProjectId();
		$sql_arrs = $this->getLogQueryStatements();

		$csv_map = [];
		foreach ($sql_arrs as $name => $sql) {
			$QB = new QueryBatcher(
				$this,
				$sql,
				[$this->getSourceProjectId()]
			);

			$csv_map[$name] = $QB->dumpTableToCSV($this->queryWrapper($sql, [$pid]));
		}
		$zip_loc = $this->makeZip($csv_map);

		return [$pid, $zip_loc];
	}


	public function getLogQueryStatements() {
		$project_sql = "SELECT * FROM redcap_projects WHERE project_id = ?";

		$em_table_join = <<<_SQL
						INNER JOIN redcap_external_modules AS em
						ON ema.external_module_id = em.external_module_id
						_SQL;

		$em_sql = "SELECT *, em.directory_prefix as module_name FROM redcap_external_module_settings AS ema ";
		$em_sql .= $em_table_join;
		$em_sql .= " WHERE project_id = ?";

		$em_log_sql = "SELECT *, em.directory_prefix as module_name FROM redcap_external_modules_log AS ema ";
		$em_log_sql .= $em_table_join;
		$em_log_sql .= " WHERE project_id = ?";

		$log_table = $this->framework->getLogTable();
		$log_sql = "SELECT * FROM $log_table WHERE project_id = ?";

		$data_quality_sql = <<<_SQL
					SELECT * FROM redcap_data_quality_status AS rdqs
						INNER JOIN redcap_data_quality_resolutions AS rdqr
						ON rdqs.status_id = rdqr.status_id
						WHERE project_id = ?
					_SQL;

		// TODO: event_id is still ambiguous, field comments will have partial information loss
		// TODO: create compound table from redcap_events_metadata, redcap_arms, redcap_projects

		// TODO: alerts and alerts_sent

		$sql_arrs = [
			"redcap_projects" => $project_sql,
			"redcap_external_module_settings" => $em_sql,
			"redcap_external_modules_log" => $em_log_sql,
			"redcap_data_quality" => $data_quality_sql,
			"redcap_log_event" => $log_sql
		];

		return $sql_arrs;
	}


	private function makeZip(array $files) {
		$z = new ZipArchive();

		$tmp_zip = tmpfile();
		$tmp_zip_loc = stream_get_meta_data($tmp_zip)['uri'];

		$z->open($tmp_zip_loc, ZipArchive::CREATE);

		foreach ($files as $filename => $file) {
			if (!$file) {
				continue;
			}
			// https://stackoverflow.com/a/1061862/7418735
			$tmp_loc = stream_get_meta_data($file)['uri'];
			$z->addFromString("{$filename}.csv", file_get_contents($tmp_loc));
		}

		$z->close();

		// NOTE: $tmp_zip itself must be returned to keep the variable in scope or the associated file will be deleted
		return $tmp_zip;
	}


	/////////////////////////////////////////////////////////////////////////////
	//                              Project Design                             //
	/////////////////////////////////////////////////////////////////////////////

	public function updateRemoteProjectDesign($retain_title = false) {
		// HACK: these take long
		if ($this->testing_mode) {
			$report = [
				"project_info" => "26",
				"fields" => "16",
				"arms" => null,
				"events" => "1",
				"event_mapping" => "2",
				"repeats" => "this project is not repeating"
			];

			return json_encode($report);
		}

		$project_info = $this->updateRemoteProjectInfo($retain_title);
		$fields = $this->updateRemoteDataDictionary(true);
		$arms = $this->updateRemoteArms();
		if ($this->Proj->project['repeatforms']) {
			// these functions call die if the above is false
			$events = $this->updateRemoteEvents();
			$event_mapping = $this->updateRemoteEventMapping();
		}
		$repeats = $this->updateRemoteRepeats();

		$report = [
			"project_info" => $project_info,
			"fields" => $fields,
			"arms" => $arms,
			"events" => $events,
			"event_mapping" => $event_mapping,
			"repeats" => $repeats
		];

		return json_encode($report);
	}


	public function updateRemoteDataDictionary(bool $reset_remote_metadata = false) {
		// NOTE: Proj->metadata is a superset of data necessary for the API, this static function call is used in lieu of manually paring down the map
		$dd = $this->tabulateFileFields();

		$post_params = [
			"content" => "metadata",
			"format" => "json",
			"returnFormat" => "json",
			"data" => $dd
		];

		if ($reset_remote_metadata) {
			$reset_data = '[{"field_name":"record_id","form_name":"demographics","section_header":"","field_type":"text","field_label":"Study ID","select_choices_or_calculations":"","field_note":"","text_validation_type_or_show_slider_number":"","text_validation_min":"","text_validation_max":"","identifier":"","branching_logic":"","required_field":"","custom_alignment":"","question_number":"","matrix_group_name":"","matrix_ranking":"","field_annotation":""}]';
			$reset_params = [
				"content" => "metadata",
				"format" => "json",
				"returnFormat" => "json",
				"data" => $reset_data
			];
			$this->curlPOST($reset_params);
		}

		return $this->curlPOST($post_params);
	}


	// Required for event mapping to import
	public function updateRemoteProjectInfo($retain_title = false) {

		// Copied from API/export.php getItems funciton
		global $lang;
		// Get project object of attributes
		$Proj = $this->Proj;
		// Set array of fields we want to return, along with their user-facing names
		$project_fields = \Project::getAttributesApiExportProjectInfo();
		//print_array($Proj->project);
		// Add values for all the project fields
		$project_values = [];
		foreach ($project_fields as $key => $hdr) {
			// Add to array
			if (!isset($Proj->project[$key])) {
				// Leave blank if not in array above
				$val = '';
			} elseif (is_bool($Proj->project[$key])) {
				// Convert boolean to 0 and 1
				$val = ($Proj->project[$key] === false) ? 0 : 1;
			} else {
				// Normal value
				$val = label_decode($Proj->project[$key]);
			}
			$project_values[$hdr] = isinteger($val) ? (int)$val : $val;
		}
		// Add longitudinal
		$project_values['is_longitudinal'] = $Proj->longitudinal ? 1 : 0;
		// Add repeating instruments and events flag
		$project_values['has_repeating_instruments_or_events'] = ($Proj->hasRepeatingFormsEvents() ? 1 : 0);
		// Add any External Modules that are enabled in the project
		$versionsByPrefix = \ExternalModules\ExternalModules::getEnabledModules($Proj->project_id);
		$project_values['external_modules'] = implode(",", array_keys($versionsByPrefix));
		// Reformat the missing data codes to be pipe-separated
		$theseMissingCodes = [];
		foreach (parseEnum($project_values['missing_data_codes']) as $key => $val) {
			$theseMissingCodes[] = "$key, $val";
		}
		$project_values['missing_data_codes'] = implode(" | ", $theseMissingCodes);
		// Mobile App only
		if (isset($_POST['mobile_app']) && $_POST['mobile_app'] == '1') {
			// Add list of records that have been locked at the record-level
			$Locking = new \Locking();
			$Locking->findLockedWholeRecord($Proj->project_id);
			$project_values['locked_records'] = implode("\n", array_keys($Locking->lockedWhole));
			// Add Form Display Logic settings
			$project_values['form_display_logic'] = \FormDisplayLogic::outputFormDisplayLogicForMobileApp($Proj->project_id);
		}

		if ($retain_title) {
			unset($project_values["project_title"]);
		}

		// report

		// actual send
		$post_params = [
			"content" => "project_settings",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($project_values)
		];

		return $this->curlPOST($post_params);

	}

	public function updateRemoteArms() {
		// NOTE: the following function makes a call to die if arms <= 1
		// $result = \Project::getArmRecords([]);

		if (!$this->Proj->longitudinal) {
			// As a sanity check, ensure the project doesn't have some empty arms, in which case it is "sort of" longitudinal
			$sql = "select count(*) from redcap_events_arms where project_id = ?";
			$numArms = db_result(db_query($sql, $this->getSourceProjectId()));
			if ($numArms <= 1) {
				return;
			}
		}

		$result = $this->Proj->extractArms();

		$post_params = [
			"content" => "arm",
			"action" => "import",
			"override" => "1",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($result)
		];

		return $this->curlPOST($post_params);
	}

	public function updateRemoteEvents() {

		// TODO: this instantaties a new Proj object and is thus not suitable for cron context without define(PROJECT_ID)
		$result = \Project::getEventRecords([], $this->Proj->project['scheduling'], false);
		// $result = Project::getEventRecords($post, $Proj->project['scheduling'], !(isset($post['mobile_app']) && $post['mobile_app'] == '1'));


		$post_params = [
			"content" => "event",
			"action" => "import",
			"override" => "1",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($result)
		];

		return $this->curlPOST($post_params);
	}

	public function updateRemoteEventMapping() {

		// TODO: this instantaties a new Proj object and is thus not suitable for cron context without define(PROJECT_ID)
		$result = \Project::getInstrEventMapRecords([]);

		$post_params = [
			"content" => "formEventMapping",
			"action" => "import",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($result)
		];

		return $this->curlPOST($post_params);
	}

	public function updateRemoteRepeats() {

		if (!$this->Proj->hasRepeatingFormsEvents()) {
			return "this project is not repeating";
		}

		// adapted from API export
		$raw_values = $this->Proj->getRepeatingFormsEvents();
		if ($this->Proj->longitudinal) {
			$eventForms = $this->Proj->eventsForms;
			foreach ($eventForms as $dkey => $row) {
				$event_name = \Event::getEventNameById($this->Proj->project_id, $dkey);
				$sql = "select form_name, custom_repeat_form_label from redcap_events_repeat where event_id = " . db_escape($dkey) . "";
				$q = db_query($sql);
				if (db_num_rows($q) > 0) {
					while ($row = db_fetch_assoc($q)) {
						$form_name = ($row['form_name'] ? $row['form_name'] : '');
						$form_label = ($row['custom_repeat_form_label'] ? $row['custom_repeat_form_label'] : '');
						$results[] = ['event_name' => $event_name, 'form_name' => $form_name, 'custom_form_label' => $form_label];
					}
				}
			}
		} else {//classic project
			foreach (array_values($raw_values)[0] as $dkey => $row) {
				$results[] = ['form_name' => $dkey, 'custom_form_label' => $row];
			}
		}


		$post_params = [
			"content" => "repeatingFormsEvents",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($results)
		];

		return $this->curlPOST($post_params);
	}


	/////////////////////////////////////////////////////////////////////////////
	//                             Record Handling                             //
	/////////////////////////////////////////////////////////////////////////////

	public function flushRemoteRecords() {
		// NOTE: this assumes the remote project's primary key is identical to local
		$target_project_pk = $this->Proj->table_pk;

		$post_params = [
			"content" => "record",
			"format" => "json",
			"type" => "flat",
			"fields" => $target_project_pk
		];
		$dr = $this->curlPOST($post_params);

		$del_recs = array_unique(
			array_values(
				array_column(json_decode($dr, true), $target_project_pk)
			)
		);

		if (empty($del_recs)) {
			$response = "no records exist in target project";
			return $response;
		}

		$post_params = [
			"content" => "record",
			"action" => "delete",
			"records" => $del_recs
			// "records" => json_encode($del_recs),
			// "format" => "json"
		];
		$response = $this->curlPOST($post_params);


		return $response;
	}


	public function portRemoteRecords($port_file_fields = true) {
		// this batch size identified from TIN
		$batch_size = 10;
		$response = [];

		$record_ids = $this->getAllRecordPks();

		// NOTE: check API Sync EM for a more robust batching solution
		$record_batches = array_chunk($record_ids, $batch_size);

		// need to build list of file fields now due to moving this to sequential ajax calls
		$this->tabulateFileFields();

		// TODO: wrap this in a try catch, detect OOM error and continue with halved batch size
		//  or use memory_get_usage to track as limit is approached
		//  Fatal error: Allowed memory size of 1073741824 bytes exhausted (tried to allocate 327680 bytes) in /app001/www/redcap/redcap_v14.9.3/Classes/Records.php on line 2500
		$batch_idx = 1;
		foreach ($record_batches as $record_batch) {
			$response[$batch_idx++] = $this->portRecordList($record_batch, $port_file_fields);
		}

		return $response;
	}


	public function getAllRecordPks() {
		$source_project_id = $this->getSourceProjectId();

		$get_data_params = [
			"project_id" => $source_project_id,
			"fields" => $this->Proj->table_pk,
			"return_format" => "json-array"
		];

		$get_data_return = REDCap::getData($get_data_params);

		$rc_records = [];

		foreach ($get_data_return as $record) {
			$rc_records[] = $record[$this->Proj->table_pk];
		}

		return $rc_records;
	}

	public function portRecordList(array $records, $port_file_fields = true) {
		$source_project_id = $this->getSourceProjectId();

		$get_data_params = [
			"project_id" => $source_project_id,
			"records" => $records,
			"return_format" => "json",
			"returnBlankForGrayFormStatus" => true
		];

		$rc_data = REDCap::getData($get_data_params);

		$post_params = [
			"content" => "record",
			"format" => "json",
			"type" => "flat",
			"returnFormat" => "json",
			"data" => $rc_data
		];

		$response = $this->curlPOST($post_params);


		// port edocs individually
		// NOTE: as per the API documentation this is NOT suitable for signatures
		if ($port_file_fields) {
			$this->portFileFields($rc_data);
		}
		return $response;
	}


	public function getRecordRange($start_record, $end_record) {
		$record_list = [];

		$all_record_ids = $this->getAllRecordPks();
		$start_idx = array_search((string) $start_record, $all_record_ids, true);
		$end_idx = array_search((string) $end_record, $all_record_ids, true);
		$end_delta = ($end_idx - $start_idx) + 1; # add 1 to include end of range
		$record_list = array_slice(
			$all_record_ids,
			$start_idx,
			($end_delta > 0) ? $end_delta : null # if end is after
		);
		return $record_list;
	}
	///////////////////////////////////////////////////////////////////////////////
	//                                  File operations                          //
	///////////////////////////////////////////////////////////////////////////////


	private function portFileFields($rc_data) {

		if ($this->Proj->hasFileUploadFields) {
			$rc_data_arr = json_decode($rc_data, 1);
			$record_primary_key = $this->Proj->table_pk;

			foreach ($this->file_fields as $doc_field => $doc_validation) {
				foreach ($rc_data_arr as $_ => $record) {

					$this_doc_id = $record[$doc_field];

					$file_data = [
						"record_primary_key" => $record[$record_primary_key],
						"doc_id" => $this_doc_id,
						"field_name" => $doc_field,
						"validation" => $doc_validation
					];

					if ($file_data["doc_id"] === "") {
						continue;
					}

					if ($this->Proj->longitudinal) {
						$file_data["redcap_event_name"] = $record["redcap_event_name"];
						if ($record["redcap_repeat_instance"] !== "") {
							$file_data["redcap_repeat_instance"] = $record["redcap_repeat_instance"];
						}
					}

					if ($record["redcap_repeat_instrument"] !== "") {
						$file_data["redcap_repeat_instance"] = $record["redcap_repeat_instance"];
					}

					$this->portFile($file_data);
				}
			}
		}
	}


	public function portFile(array $file_data, $file_repo = false) {
		// REDCap::getFile was not used as it returns file content directly instead of stored_name, requiring a tmp_file to be created and passed to curl_file_create
		$sql = "SELECT stored_name, mime_type, doc_name, project_id FROM redcap_edocs_metadata WHERE doc_id = ? LIMIT 1";
		$edocs_tbl = $this->queryWrapper($sql, [$file_data['doc_id']])[0];

		// TODO: detect cloud storage and tell user this is unlikely to work
		// TODO: actually support cloud storage?

		$file_real_path = EDOC_PATH . $edocs_tbl['stored_name'];
		if (!file_exists($file_real_path)) {
			// system may have been configured to put edocs in specific folders for PIDs
			$file_real_path = EDOC_PATH . "pid{$edocs_tbl['project_id']}" . DS . "{$edocs_tbl['stored_name']}";
			if (!file_exists($file_real_path)) {
				// TODO; throw error, make sure it's caught in portFileFields and file repo functions without preventing later xfer attempts
				return false;
			}
		}

		$cfile = curl_file_create($file_real_path, $edocs_tbl['mime_type'], $edocs_tbl['doc_name']);

		if ($file_repo || ($file_data["validation"] == "signature")) {

			if ($file_repo) {
				// allow null for root-level items that come from the file repo
				$remote_folder_id = $file_data["target_folder"];
			} else {
				// signatures cannot be imported to their fields, upload them to the file repo instead
				// TODO: specific signature dir
				// TODO: build a map of signature files to their fields
				$remote_folder_id = ($file_data["target_folder"] ?? $this->getReservedFileRepoFolder());
			}
			$post_params = [
				"content" => "fileRepository",
				"action" => "import",
				"file" => $cfile,
				"returnFormat" => "json",
				"folder_id" => $remote_folder_id
			];

		} else {
			$post_params = [
				"content" => "file",
				"action" => "import",
				"record" => $file_data['record_primary_key'],
				"field" => $file_data['field_name'],
				"file" => $cfile,
				"returnFormat" => "json"
			];

			if ($this->Proj->longitudinal) {
				$post_params["event"] = $file_data["redcap_event_name"];
			}
			// handle repeats
			if ($file_data["redcap_repeat_instance"]) {
				$post_params["repeat_instance"] = $file_data["redcap_repeat_instance"];
			}
		}

		$response = $this->curlPOST($post_params, true);
		return $response;
	}


	public function dumpLogBatchesToFileRepository() {
		$timestamp = date("Y-m-d_H.i.s");
		$bytes_per_mb = 1024**2;
		$log_qs = $this->getLogQueryStatements();

		$lml_setting = $this->getSystemSetting("local_memory_limit");
		$max_batch_size = (is_numeric($lml_setting)) ? ($lml_setting * $bytes_per_mb) : null;

		$remote_server_idx = array_search($this->creds["remote_api_uri"], $this->getSystemSetting("super_remote_api_uri"));
		$fsl_setting = $this->getSystemSetting("file_size_limit")[$remote_server_idx] ?? null;
		$max_csv_size = (is_numeric($fsl_setting)) ? ($fsl_setting * $bytes_per_mb) : null;

		foreach ($log_qs as $table => $sql) {
			$QB = new QueryBatcher(
				$this,
				$sql,
				[$this->getSourceProjectId()],
				$max_batch_size,
				$max_csv_size
			);

			$file_prefix = "{$timestamp}_{$table}";

			$QB->portAllBatches($file_prefix);
		}
	}


	public function portFileRepository() {
		// TODO: it may be easier to gather this data via the API for the source project
		// NOTE: \FileRepository functions are tightly coupled with UI functionality
		// getFolderList is almost ok, but getFileList returns an array of html-formatted descriptive information

		$file_list = [];

		$CCFR = new CCFileRepository($this);

		$file_list["root"] = $CCFR->getFileRepositoryFolderContents(null);

		$CCFR->createRemoteFolders();
		$file_repo_tree = $CCFR->getLocalFileRepo();

		// manually add file repo root as first item
		array_unshift(
			$file_repo_tree,
			[
			"name" => "root",
				"folder_id" => null
				// TODO: might need to set some remote_info here
			]
		);

		$total_files_transferred = 0;

		// NOTE: getFileList assumes PROJECT_ID is reliable, account this if batch porting entire projects
		foreach ($file_repo_tree as &$folder_info) {
			$folder_id = $folder_info["folder_id"];
			$folder_info["files_transferred"] = 0;

			// $file_list[$folder_id] = \FileRepository::getFileList($folder_id);
			$file_list[$folder_id] = $CCFR->getFileRepositoryFolderContents($folder_id);

			foreach ($file_list[$folder_id] as $local_folder_id => $file_id) {
				$remote_folder_id = $folder_info["remote_info"]["folder_id"] ?? null;
				$file_data = [
					"doc_id" => $file_id,
					"target_folder" => $remote_folder_id
				];
				// HACK: easier to delete empty folder
				$port_response = json_decode($this->portFile($file_data, true), true);
				if (isset($port_response["error"])) {
					// TODO get this to the user
					$err_arr = [
						"portFile_response" => $port_response,
						"file_data" => $file_data
					];
					$errlist[] = $err_arr;
					continue;
				}
				$folder_info["files_transferred"]++;
				$total_files_transferred++;
			}
		}

		// get only the folder names and xferred files
		$out = [];

		array_filter($file_repo_tree, function ($v, $k) use (&$out) {
			$nr1 = array_filter($v, function ($v1, $k1) use (&$out) {
				$is_match = in_array($k1, ["name", "files_transferred"]);
				return $is_match;
			}, ARRAY_FILTER_USE_BOTH);
			$out[$nr1["name"]] = $nr1["files_transferred"];
		}, ARRAY_FILTER_USE_BOTH);

		$return_item = array_merge(
			["total_items_transferred" => $total_files_transferred],
			$out
		);

		return json_encode($return_item);
	}

	public function getRemoteFileRepositoryDirectory($folder_id = null) {
		// TODO: check for extant file structure
		$post_params = [
			"content" => "fileRepository",
			"action" => "list",
			"format" => "json",
			"folder_id" => $folder_id
		];

		$r =  $this->curlPOST($post_params);

		return $r;
	}

	public function getRemoteFileRepoTreeStructure($remote_file_repo) {
		// TODO
		// do I still need this with the CFFR class?
		return;
	}

	/////////////////////////////////////////////////////////////////////////////
	//                          User related functions                         //
	/////////////////////////////////////////////////////////////////////////////

	public function deleteUserRoles() {
		// fetch and delete all user roles

		$fetch_roles_post_params = [
			"content" => "userRole",
			"format" => "json"
		];

		$r1 =  json_decode($this->curlPOST($fetch_roles_post_params), 1);
		$user_role_data = array_column($r1, "unique_role_name") ?? [];

		if ($user_role_data === []) {
			return "No user roles in remote project.";
		}

		$delete_roles_post_params = [
			"content" => "userRole",
			"action" => "delete",
			"roles" => $user_role_data
		];

		$response = $this->curlPOST($delete_roles_post_params);

		return $response;
	}


	public function portUserRoles() {
		global $mobile_app_enabled;
		$project_id = $this->getSourceProjectId();

		// NOTE: if a user role's unique name doesn't already exist in the target project the unique_role_name must be set to blank
		$user_role_arr = \UserRights::getUserRolesDetails($project_id, $mobile_app_enabled);

		$CCUM = new CCUserManagement($this);
		$CCUM->mapRemoteUserRoles();

		// TODO: this could perhaps simply be done in the CCUM object
		// TODO: issuing each user role individually may produce more granular reporting potentially significant overhead cost
		foreach ($user_role_arr as $user_role_idx => &$user_role_data) {
			$user_role_data["unique_role_name"] = $CCUM->translateLocalUrnToRemote($user_role_data["role_label"]);
		}
		$post_params = [
			"content" => "userRole",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($user_role_arr)
		];
		$r =  json_decode($this->curlPOST($post_params), true);

		return json_encode($r);
	}

	public function portUsers() {
		global $mobile_app_enabled;
		$project_id = $this->getSourceProjectId();

		$users = \UserRights::getUserDetails($project_id, $mobile_app_enabled);

		$post_params = [
			"content" => "user",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($users)
		];

		$r = $this->curlPOST($post_params);
		return $r;
	}


	public function portUserRoleAssignment() {
		// TODO: perhaps this object should be a class member
		// re-running the mapping at this step is necessary anyway as it potentially changed during portUserRoles
		$CCUM = new CCUserManagement($this);
		$CCUM->mapRemoteUserRoles();

		$user_role_assignments = \Project::getUserRoleRecords();
		$remote_user_role_assignments = $CCUM->translateUserRoleAssignment($user_role_assignments);

		$post_params = [
			"content" => "userRoleMapping",
			"action" => "import",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($remote_user_role_assignments)
		];

		$response = $this->curlPOST($post_params);
		return $response;
	}


	// DAGS /////////////////////////////////////////////////////////////////////

	public function portDAGs() {
		// TODO: this results in duplicate dag creation, should export dags first
		// NOTE: this is handled by XML, as is DAG membership

		$dags = \Project::getDAGRecords();
		if (empty($dags)) {
			return "This project does not have any DAGs";
		}

		array_walk(
			$dags,
			function (&$dag) {
				// unset($dag['unique_group_name']);
				$dag['unique_group_name'] = "";
				unset($dag['data_access_group_id']);
			}
		);

		$post_params = [
			"content" => "dag",
			"action" => "import",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($dags)
		];

		$r = $this->curlPOST($post_params);
		return $r;
	}


	public function portDAGMapping() {
		// NOTE: this is handled by super XML project creation
		// NOTE: users are appropriately mapped to the initial dag rather than any duplicates

		$dag_mapping = \Project::getUserDagRecords($this->getSourceProjectId());

		$populated_dags = array_filter(
			array_column($dag_mapping, "redcap_data_access_group"),
			fn ($v) => $v !== ""
		);
		if (empty($populated_dags)) {
			return "No users assigned to DAGs";
		}

		$post_params = [
			"content" => "userDagMapping",
			"action" => "import",
			"format" => "json",
			"returnFormat" => "json",
			"data" => json_encode($dag_mapping)
		];

		$r = $this->curlPOST($post_params);
		return $r;
	}
	/////////////////////////////////////////////////////////////////////////////
	//                            Utility Functions                            //
	/////////////////////////////////////////////////////////////////////////////
	public function curlPOST($post_params, $is_file = false) {
		$creds = $this->creds;
		$ch = curl_init();

		if (substr($creds["remote_api_uri"], -5) !== "/api/") {
			throw new \ErrorException("Your remote API URI must end with '/api/'");
		}

		$is_localhost = in_array($creds["remote_api_uri"], ["http://127.0.0.1/api/", "http://localhost/api/"]);

		curl_setopt($ch, CURLOPT_URL, $creds["remote_api_uri"]);
		curl_setopt($ch, CURLOPT_POST, 1);

		$post_params["token"] = $creds["remote_api_token"];

		// cannot http_build_query($post_params, '', '&') with files
		if (!$is_file) {
			$post_params = http_build_query($post_params, '', '&');
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $is_localhost);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

		$server_output = curl_exec($ch);

		curl_close($ch);
		return $server_output;
	}


	public function queryWrapper(string $sql, array $params): array {
		$result = $this->framework->query($sql, $params);

		$cur_size = 0;

		$accumulator = [];
		while ($row = $result->fetch_assoc()) {
			$accumulator[] = $row;

			$cur_size = strlen(serialize($accumulator));

		}
		$result->free();

		return $accumulator;
	}


	public function tabulateFileFields() {
		$source_project_id = $this->getSourceProjectId();
		$dd = \MetaData::getDataDictionary(
			/*$returnFormat = */
			'json',
			/*$returnCsvLabelHeaders = */
			true,
			/*$fields = */
			[],
			/*$forms = */
			[],
			/*$isMobileApp = */
			false,
			/*$draft_mode = */
			false,
			/*$revision_id = */
			null,
			/*$project_id_override = */
			$source_project_id,
			/*$delimiter = */
			','
		);

		if ($this->Proj->hasFileUploadFields) {
			$dd_arr = json_decode($dd, 1);
			foreach ($dd_arr as $field) {
				if ($field['field_type'] === 'file') {
					// $this->file_fields[] = $field['field_name'];
					// $this->file_fields[] = [
					// 	$field['field_name'] => $field['text_validation_type_or_show_slider_number']
					// ];

					$this->file_fields[$field['field_name']] = $field['text_validation_type_or_show_slider_number'];
					// $this->file_fields[$field['field_name']]['is_sig'] = $field['text_validation_type_or_show_slider_number'];
				}
			}
		}

		return $dd;
	}


	public function includeCss(string $path): void {
		echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '">';
	}


	public function includeJs(string $path): void {
		echo '<script src="' . $this->getUrl($path) . '"></script>';
	}


	public function setJsSettings(array $settings) {
		foreach ($settings as $k => $v) {
			$this->framework->tt_addToJavascriptModuleObject($k, $v);
		}
	}

	public function getRemoteProjectInfo() {
		$post_params = [
			"content" => "project",
			"format" => "json"
		];

		return $this->curlPOST($post_params);
	}


	public function getRemoteProjectRecordList() {
		$post_params = [
			"content" => "record",
			"type" => "flat",
			"format" => "json",
			"fields" => $this->Proj->table_pk
		];

		$response = json_decode($this->curlPOST($post_params), true);

		try {
			// TODO: what if pk is different on source and target?
			$result = [
				"min" => $response[0][$this->Proj->table_pk],
				"max" => end($response)[$this->Proj->table_pk]
			];
		} catch (Exception $e) {
			$result = ["error" => "Cannot fetch records from target project"];
		}

		return $result;
	}
}
