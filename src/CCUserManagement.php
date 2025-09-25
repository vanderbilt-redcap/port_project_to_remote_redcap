<?php

namespace PPtRR\ExternalModule;

class CCUserManagement
{
	private $module;
	private $unified_map = [];

	public const SOURCE_URN_KEY  = 	"local_urn";
	public const TARGET_URN_KEY 	= 	"target_urn";

	public function __construct($module) {
		$this->module = $module;
	}

	/**
	 * Populates $this->unified_map
	 * {
	 *  <role_label> : {
	 *  self::SOURCE_URN_KEY: <source_unique_role_name>
	 *  self::TARGET_URN_KEY: <target_unique_role_name>
	 *  },
	 *  ...
	 * }
	 *
	 */
	public function mapRemoteUserRoles(): void {
		global $mobile_app_enabled;
		$local_project_user_role_arr = \UserRights::getUserRolesDetails($this->module->getSourceProjectId(), $mobile_app_enabled);


		$fetch_post_params = [
			"content" => "userRole",
			"format" => "json",
			"returnFormat" => "json"
		];

		$target_project_user_role_arr = json_decode($this->module->curlPOST($fetch_post_params), 1);

		$local_user_role_map = array_column($local_project_user_role_arr, "unique_role_name", "role_label") ?? [];
		$target_user_role_map = array_column($target_project_user_role_arr, "unique_role_name", "role_label") ?? [];

		// TODO: can optimize by detecting if urns match, very likely with XML import
		// TODO: this doesn't support multiple roles with the same name, consider compounding
		$domap = function ($i) use ($local_user_role_map, $target_user_role_map) {

			$mapping =  [
				self::SOURCE_URN_KEY => $local_user_role_map[$i],
				self::TARGET_URN_KEY => $target_user_role_map[$i]
			];

			$this->unified_map[$i] = $mapping;

			return $mapping;
		};

		// TODO: was I ever using the return for this or should this just be an array_walk?
		$_ = array_map($domap, array_keys($local_user_role_map));
	}

	public function translateUserRoleAssignment(array $user_role_assignments): array {
		array_walk(
			$user_role_assignments,
			fn (&$user) => ($user["unique_role_name"] = $this->getRemoteUrnFromLocalUrn($user["unique_role_name"]))
		);

		return $user_role_assignments;
	}

	public function translateLocalUrnToRemote(string $role_label): string {
		return ($this->unified_map[$role_label][self::TARGET_URN_KEY] ?? "");
	}

	public function getRemoteUrnFromLocalUrn(string $unique_role_name): string {
		if ($unique_role_name === "") {
			return "";
		} // user is not assigned to a role
		if (!str_starts_with($unique_role_name, "U-")) {
			$err_msg = "unique_role_name entries start with U-";
			$err_msg .= "\nDid you mean to call getRemoteUrnFromLocalRoleLabel?";
			throw new \ErrorException($err_msg);
		}

		// simpler map
		// <source_urn>: <target_urn>
		$local_remote_urn_map = array_column($this->unified_map, self::TARGET_URN_KEY, self::SOURCE_URN_KEY);

		return $local_remote_urn_map[$unique_role_name] ?? "";
	}
}
