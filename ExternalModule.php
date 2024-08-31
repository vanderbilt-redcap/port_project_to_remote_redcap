<?php

namespace PPtRR\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;
use ZipArchive;

class ExternalModule extends AbstractExternalModule {

    function dumpMetaData($pid = null) {
        if ($pid === null) {
            $pid = $this->framework->getProjectId();
        }

        $project_sql = "SELECT * FROM redcap_projects WHERE project_id = ?;";

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
        $log_sql = "SELECT * FROM $log_table WHERE project_id = ?;";

        $sql_arrs = [
            "redcap_projects" => $project_sql,
            "redcap_external_module_settings" => $em_sql,
            "redcap_external_modules_log" => $em_log_sql,
            "redcap_log_event" => $log_sql
        ];

        $csv_map = [];
        foreach( $sql_arrs as $name => $sql ) {
            $csv_map[$name] = $this->dumpTableToCSV($this->queryWrapper($sql, [$pid]));
        }
        $zip_loc = $this->makeZip($csv_map);

        return [$pid, $zip_loc];
    }

    private function makeZip(array $files) {
        $z = new ZipArchive();

        $tmp_zip = tmpfile();
        $tmp_zip_loc = stream_get_meta_data($tmp_zip)['uri'];

        $z->open($tmp_zip_loc, ZipArchive::CREATE);

        foreach ($files as $filename => $file) {
            if (!$file) { continue; }
            // https://stackoverflow.com/a/1061862/7418735
            $tmp_loc = stream_get_meta_data($file)['uri'];
            $z->addFromString("{$filename}.csv", file_get_contents($tmp_loc));
        }

        $z->close();

        // NOTE: $tmp_zip itself must be returned to keep the variable in scope or the associated file will be deleted
        return $tmp_zip;
    }


    private function queryWrapper(string $sql, array $params): array {
        $result = $this->framework->query($sql, $params);

        $accumulator = [];
        while ($row = $result->fetch_assoc()) {
            $accumulator[] = $row;
        }

        return $accumulator;
    }


    /*
     * @param array $arr Array of associative arrays in the format [["column_name" => "value"]]; passed by reference to save on memory
     */
    function dumpTableToCSV(array &$arr) {
        $tmp = tmpfile();

        if (!($arr)) { return; }

        // dump header row
        fputcsv($tmp, array_keys($arr[0]));

        foreach($arr as $row) {
            fputcsv($tmp, $row);
        }
        return $tmp;
    }

    function storeZipFilesInRepository($zip_file) {
        $z = new ZipArchive();

        $stream = $z->open($zip_file, 'r');
        if ($stream) {
            $z->extractTo($temp_dir);
            $z->close();
        }

        foreach($files as $file) {
            $filename = "foo.csv";
            REDCap::storeFile($file, $pid, $filename);
        }

    }


    function getCredentials($i = 0) {
        $creds = [];

        $is_system = 0;

        if ($is_system) {
            $system_settings = $this->framework->getSystemSettings();

            $parts = ["super_remote_api_uri", "remote_super_token"];

            foreach ($parts as $part) {
                $creds[$part] = $system_settings[$part]["system_value"][$i];
            }

            $creds["remote_api_uri"] = $creds["super_remote_api_uri"];
            $creds["remote_api_token"] = $creds["remote_super_token"];
        } else {
            $project_settings = $this->framework->getProjectSettings();
            $parts = ["remote_api_uri", "remote_api_token"];

            foreach ($parts as $part) {
                $creds[$part] = $project_settings[$part][$i];
            }
        }

        return $creds;
    }


    function createRemoteProject($pid = null) {
        if ($pid === null) { $pid = $this->framework->getProjectId(); }

        $creds = $this->getCredentials();

        $data = [];

        $proj = $this->framework->getProject($pid);

        $data["project_title"] = $proj->getTitle();

        $fields = "project_title, purpose, purpose_other, project_notes, is_longitudinal, surveys_enabled, record_autonumbering_enabled";
        $fields = "app_title, purpose, purpose_other, project_note, is_longitudinal, surveys_enabled, record_autonumbering_enabled";
        $fields = "app_title, purpose, purpose_other, project_note, surveys_enabled";

        $res = $this->queryWrapper("SELECT {$fields} FROM redcap_projects WHERE project_id = ?", [$pid])[0];

        $data["project_title"] = $res["app_title"];
        $data["project_notes"] = $res["project_note"];
        $data["purpose"] = $res["purpose"];
        $data["purpose_other"] = $res["purpose_other"];
        $data["surveys_enabled"] = $res["surveys_enabled"];

        $post_params = [
            "content" => "project",
            "format" => "json",
            "returnFormat" => "json",
            "data" => [json_encode([0 => $data])]
        ];

        return $this->curlPOST($creds, $post_params);
    }


    function updateRemoteMetadata($creds, $source_project_id = null) {
        $dd = \MetaData::getDataDictionary(
            /*$returnFormat = */ 'json',
            /*$returnCsvLabelHeaders = */ true,
            /*$fields = */ array(),
            /*$forms = */ array(),
            /*$isMobileApp = */ false,
            /*$draft_mode = */ false,
            /*$revision_id = */ null,
            /*$project_id_override = */ $source_project_id,
            /*$delimiter = */ ','
        );

        $reset_data = '[{"field_name":"record_id","form_name":"demographics","section_header":"","field_type":"text","field_label":"Study ID","select_choices_or_calculations":"","field_note":"","text_validation_type_or_show_slider_number":"","text_validation_min":"","text_validation_max":"","identifier":"","branching_logic":"","required_field":"","custom_alignment":"","question_number":"","matrix_group_name":"","matrix_ranking":"","field_annotation":""}]';
        if (0) { $dd = $reset_data; }

        $post_params = [
            "content" => "metadata",
            "format" => "json",
            "returnFormat" => "json",
            "data" => $dd
        ];

        return $this->curlPOST($creds, $post_params);
    }


    function flushRemoteRecords($creds, $source_project_id = null) {
        $post_params = [
            "content" => "record",
            "format" => "json",
            "type" => "flat",
            "fields" => "record_id"
        ];
        $dr = $this->curlPOST($creds, $post_params);

        $del_recs = array_values(array_column(json_decode($dr, true), "record_id"));

        $post_params = [
            "content" => "record",
            "action" => "delete",
            "records" => $del_recs
        ];

        $resp = $this->curlPOST($creds, $post_params);
        return $resp;
    }


    function portRemoteRecords($creds, $source_project_id = null) {
        // FIXME: does not port files

        $get_data_params = [
            "project_id" => $source_project_id,
            "return_format" => "json"
        ];

        $rc_data = REDCap::getData($get_data_params);

        $post_params = [
            "content" => "record",
            "format" => "json",
            "type" => "flat",
            "returnFormat" => "json",
            "data" => $rc_data
        ];

        $response = $this->curlPOST($creds, $post_params);
        return $response;
    }

    function dumpMetadataToFileRepository($creds) {
        [$pid, $zip_pointer] = $this->dumpMetaData();
        $zip_loc = stream_get_meta_data($zip_pointer)['uri'];

        $cfile = curl_file_create($zip_loc, 'application/zip', "logs.zip");

        $post_params = [
            "content" => "fileRepository",
            "action" => "import",
            // "file" => file_get_contents($zip_loc),
            "file" => $cfile,
            "returnFormat" => "json"
        ];

        $r =  $this->curlPOST($creds, $post_params);

        return $r;
    }

    function curlPOST($creds, $post_params) {
        $ch = curl_init();

        if (substr($creds["remote_api_uri"], -5) !== "/api/") {
            throw new \ErrorException("Your remote API URI must end with '/api/'");
        }

        $is_localhost = in_array($creds["remote_api_uri"], ["http://127.0.0.1/api/", "http://localhost/api/"]);

        curl_setopt($ch, CURLOPT_URL, $creds["remote_api_uri"]);
        curl_setopt($ch, CURLOPT_POST, 1);

        $post_params["token"] = $creds["remote_api_token"];

        // cannot http_build_query($post_params, '', '&') with files
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


    protected function includeCss(string $path) {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '">';
    }


    function includeJs(string $path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }


    function setJsSettings(array $settings) {
        foreach ($settings as $k => $v) {
            $this->framework->tt_addToJavascriptModuleObject($k, $v);
        }
    }
}
