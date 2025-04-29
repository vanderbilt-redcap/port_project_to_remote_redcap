<?php

namespace PPtRR\ExternalModule;

$jsmo = $module->initializeJavascriptModuleObject();
echo $jsmo;

$remote_apis = $module->framework->getSystemSetting("super_remote_api_uri");

// TODO: put JSMO in class
$module->tt_addToJavascriptModuleObject("remote_list", $remote_apis);

$pids = $module->framework->getProjectsWithModuleEnabled();
$module->tt_addToJavascriptModuleObject("source_project_list", $pids);
$module->tt_addToJavascriptModuleObject("pptr_endpoint", $module->framework->getUrl("admin_ajax.php"));

include("admin_page.html");
$module->includeJs("js/admin_page.js");
