<?php

namespace Vanderbilt\PortProjectToRemoteREDCap\ExternalModule;

$default_warning_text = "If you are moving your project to a REDCap instance that is outside of your organization's control, please be aware of any data governance policies that may restrict data transfer by institution or region.";

$jsmo = $module->framework->initializeJavascriptModuleObject();
echo $jsmo;

$remote_list = $module->framework->getProjectSetting("remote_api_uri");

$jsmo_settings = [
	"pptr_endpoint" => $module->framework->getUrl("ajax.php"),
	"remote_list" => $remote_list
];
$module->setJsSettings($jsmo_settings);

$warning_text = $module->framework->getSystemSetting("warning_text") ?? $default_warning_text;
echo $warning_text;

$module->includeCss("css/project_page_accordion.css");
include("project_page.html");
$module->includeJs("js/project_page.js");
