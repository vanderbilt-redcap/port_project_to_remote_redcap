<?php

namespace PPtRR\ExternalModule;

class SuperToken
{
	private const TESTING_MODE = false;

	private $module;
	private $source_project_id;
	private $super_creds;
	private $remote_project_token = null;

	public function __construct($module, $source_project_id) {
		$this->module = $module;
		$this->source_project_id = $source_project_id;
	}


	public function main($remote_index): bool {
		$this->setSuperCredentials($remote_index);
		$creation_response = $this->createProjectWithXML();

		$status = $this->addToLocalProjectSettings();
		return $status;
	}


	private function setSuperCredentials($idx) {
		$remote_apis = $this->module->framework->getSystemSetting("super_remote_api_uri");
		$remote_tokens = $this->module->framework->getSystemSetting("remote_super_token");

		$super_creds = [
			"remote_api_uri" => $remote_apis[$idx],
			"remote_api_token" => $remote_tokens[$idx]
		];

		$this->super_creds = $super_creds;
	}


	///////////////////////////////////////////////////////////////////////////////
	//                                  XML stuff                                //
	///////////////////////////////////////////////////////////////////////////////

	// TODO: consider moving to base EM class, sending to reserved folder so companion module can support things API does not
	public function getSourceProjectXML() {
		// $real_project_id = PROJECT_ID; // should be nothing since this should only run in CC context
		// must be overriden during getProjectXML call
		// PROJECT_ID = $this->source_project_id;
		define('PROJECT_ID', $this->source_project_id);
		$xml = \Project::getProjectXML(
			/* $project_id = */ $this->source_project_id,
			// this makes all toggles except $exportAllCustomMetadataOptions irrelevant,
			/* $returnMetadataOnly */ true,
			/* $records = */ null,
			/* $fields = */ null,
			/* $events = */ null,
			/* $groups = */ null,
			/* $outputDags = */ null,
			/* $outputSurveyFields = */ null,
			/* $filterLogic = */ null,
			/* $exportFiles = */ null,
			/* $exportAllCustomMetadataOptions = */ true
		);

		// PROJECT_ID = $real_project_id;

		return $xml;
	}


	// Make a project with XML export, requires a super token, returns a project token
	private function createProjectWithXML(): void {
		$project_xml = $this->getSourceProjectXML();

		$placeholder_data = [
			"project_title" => "PPtRR TARGET",
				"purpose" => 0
		];

		$placeholder_data["project_title"] .= " -- {$this->source_project_id}";
		$placeholder_data["project_title"] .= " -- {$this->module->getProject($this->source_project_id)->getTitle()}";

		$post_params = [
			"content" => "project",
			"odm" => $project_xml,
			"format" => "json",
			"data" => json_encode([$placeholder_data]),
			"returnFormat" => "json"
		];

		$response = $this->module->curlPOST(
			$this->super_creds,
			$post_params
		);

		// TODO: deliver error to frontend, direct frontend to make link to recent errors
		// TODO: consider deleting this idx from sys settings
		if (str_contains($response, "error")) {
			$err_response = json_decode($response, true);
			throw new \ErrorException("Remote API Super Token is invalid!");
		}

		$this->remote_project_token = $response;
	}

	///////////////////////////////////////////////////////////////////////////////
	//                                  Local source project settings            //
	///////////////////////////////////////////////////////////////////////////////

	private function addToLocalProjectSettings($prepend = true): bool {
		// TODO: proper error handling during createProjectFromXML
		$it_worked = isset($this->remote_project_token);

		$bad_source_project_settings = $this->module->framework->getProjectSetting("target_remotes", $this->source_project_id) ?? [];
		$source_project_settings = $this->module->framework->getSubSettings("target_remotes", $this->source_project_id);

		$source_project_remote_uris = array_column($source_project_settings, "remote_api_uri") ?? [];
		$source_project_remote_tokens = array_column($source_project_settings, "remote_api_token") ?? [];

		if ($prepend) {
			array_unshift($bad_source_project_settings, "true");
			array_unshift($source_project_remote_uris, $this->super_creds["remote_api_uri"]);
			array_unshift($source_project_remote_tokens, $this->remote_project_token);
		} else {
			$bad_source_project_settings[] = "true";
			$source_project_remote_uris[] = $this->super_creds["remote_api_uri"];
			$source_project_remote_tokens[] = $this->remote_project_token;
		}

		if ($it_worked) {
			$this->module->setProjectSetting("target_remotes", $bad_source_project_settings, $this->source_project_id);
			$this->module->setProjectSetting("remote_api_uri", $source_project_remote_uris, $this->source_project_id);
			$this->module->setProjectSetting("remote_api_token", $source_project_remote_tokens, $this->source_project_id);
		}

		return $it_worked;
	}

	// TODO: integrate with UI, allow select2 selection of any PID
	public function activateThisModuleOnProject(): void {
		$this->module->enableModule($this->source_project_id);
	}


}
