<?php

namespace Vanderbilt\PortProjectToRemoteREDCap\ExternalModule;

$jsmo = $module->initializeJavascriptModuleObject();
echo $jsmo;

$remote_apis = $module->framework->getSystemSetting("super_remote_api_uri");

// TODO: put JSMO in class
$module->tt_addToJavascriptModuleObject("remote_list", $remote_apis);

$source_project_list = [];
$pids = $module->framework->getProjectsWithModuleEnabled();
foreach ($pids as $pid) {
	$source_project_list[$pid] = "{$pid} - {$module->getProject($pid)->getTitle()}";
}

$module->tt_addToJavascriptModuleObject("source_project_list", $source_project_list);
$module->tt_addToJavascriptModuleObject("pptr_endpoint", $module->framework->getUrl("admin_ajax.php"));

include("admin_page.html");
$module->includeJs("js/admin_page.js");
