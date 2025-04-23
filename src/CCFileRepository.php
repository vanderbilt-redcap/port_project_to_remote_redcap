<?php

namespace PPtRR\ExternalModule;

class CCFileRepository {

	private $module;
	private $local_file_repo = [];

	public function __construct($module) {
		$this->module = $module;
		$this->local_file_repo = $this->buildLocalFileRepo();
	}


	public function getFileRepositoryFolderContents($folder_id = null, $project_id = PROJECT_ID) {

		$recycle_bin = false;

		$response = [];

		$sql = "select d.docs_id, d.docs_name, d.docs_size, e.stored_date, d.docs_comment, ff.folder_id, e.delete_date, e.doc_id
			from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d
			left join redcap_docs_attachments a on a.docs_id = d.docs_id
			left join redcap_docs_folders_files ff on ff.docs_id = d.docs_id
			left join redcap_docs_folders f on ff.folder_id = f.folder_id
			where d.project_id = $project_id and d.export_file = 0 and a.docs_id is null
			and de.docs_id = d.docs_id and de.doc_id = e.doc_id and e.date_deleted_server is null";
		if ($recycle_bin) {
			// Recycle bin: Show ALL files from ALL folders (flat display) - apply DAG/Role restriction here since we normally apply it at folder level outside the Recycle Bin
			$dagsql = ($user_rights['group_id'] == "") ? "" : "and (f.dag_id is null or f.dag_id = ".$user_rights['group_id'].")";
			$rolesql = ($user_rights['role_id'] == "") ? "" : "and (f.role_id is null or f.role_id = ".$user_rights['role_id'].")";
			$sql .= " and e.delete_date is not null $dagsql $rolesql";
		} else {
			$sql .= " and e.delete_date is null and ff.folder_id " . (isinteger($folder_id) ? "= $folder_id" : "is null");
		}

		$result = $this->module->query($sql, []);

		while ($row = db_fetch_assoc($result)) {
			$response[] = $row["doc_id"];
		}

		return $response;
	}

	function getLocalFileRepo() {
		return $this->local_file_repo;
	}

	function buildLocalFileRepo($project_id = PROJECT_ID) {

		// folder contents
		$sql = <<<_SQL
			SELECT name, folder_id, parent_folder_id, dag_id, role_id FROM redcap_docs_folders
			WHERE project_id = ?
			AND deleted = 0;
		_SQL;
		$mysql_result = $this->module->query($sql, [$project_id]);

		return $this->mysqlToArr($mysql_result);
	}

	function createRemoteFolders() {
		$this->mapRemoteToLocal();
		foreach($this->local_file_repo as &$local_folder) {
			$response = $this->createRemoteFolder($local_folder);

	// TODO: is it possible to hit this condition?
	if ($response === "already_mapped") {
	continue;
}
	$local_folder["remote_info"] = $response;
		}
	}

	function createRemoteFolder($input_folder) {

	if ($input_folder["remote_info"]["folder_id"]) {
	return $input_folder["remote_info"];
}

		$post_params = [
			"content" => "fileRepository",
			"action" => "createFolder",
			"format" => "json",
			"name" => $input_folder["name"],
			// TODO: make sure support nested folders properly during tree recreation, protect against mismatch
			// "folder_id" => ($input_folder["remote_info"]["parent_folder"] ?? $input_folder["folder_id"]) ?? null,
			"folder_id" => ($input_folder["remote_info"]["parent_folder"] ?? null),
			// TODO: these must be built from a local:remote map with name as an intermediary
			// "dag_id" => $input_folder["dag_id"],
			// "role_id" => $input_folder["role_id"]
			"returnFormat" => "json"
		];

		// TODO: handle if this dir exists
		$raw_response = $this->module->curlPOST(null, $post_params);
		$response = json_decode($raw_response, true);

		if (!is_null($response["error"])) {
			$err = $response["error"];
	// NOTE: this shouldn't actually happen
			if(str_ends_with($err, "could not be created because a folder with that same name already exists in this directory. You should create a new folder with a different name instead.")) {
				// find remote folder id with this name
				$foo = true;
				$this->mapRemoteToLocal();

				return "already_mapped";
			} else {
				// TODO: handle other errors
			}
		} else {
// TODO: is this ever more than a single array?
			$response = $response[0];
		}

		return $response;
	}

	function mapRemoteToLocal() {
		$remote_file_repo = json_decode($this->module->getRemoteFileRepository(), true);

		foreach($this->local_file_repo as $_ => &$local_folder) {
			foreach($remote_file_repo as $_ => $remote_folder) {
				// TODO: what if subdirs have identical names?
				if ($local_folder["name"] == $remote_folder["name"]) {
					$local_folder["remote_info"] = $remote_folder;
				}
			}
		}
	}

	function getRemoteFolderId($local_folder_id) {
		return;
	}

	function resolveDocIdFromFileRepo($docs_id): int {

		$sql = <<<_SQL
			SELECT doc_id FROM redcap_docs_to_edocs
			WHERE docs_id = ?
		_SQL;

		$mysql_result = $this->module->query($sql, [$docs_id]);

		// TODO: catastrophic error if more than 1 row returned

		$response_arr = $this->mysqlToArr($mysql_result);

		return $response_arr[0]["doc_id"];
	}

	private function mysqlToArr($mysql_result) {
		$response = [];

		while ($row = db_fetch_assoc($mysql_result)) {
			$response[] = $row;
		}

		return $response;
	}


}
