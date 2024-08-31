<?php

namespace PPtRR\ExternalModule;

$remote_list = $module->framework->getProjectSetting("remote_api_uri");

$js_str = "<script> var remote_list = " . json_encode($remote_list) . "</script>";
echo $js_str;

?>
<form action="<?=$module->framework->getUrl("ajax.php") ?>" method="POST">
    <label for="remote_index" class="form-label">Target Server API URI</label>
    <select name="remote_index" class="form-select" aria-label="Select remote server" id="remote_select"></select>

    <div class="form-check">
    <input name="flush_records" class="form-check-input" type="checkbox" value="1" id="flexCheckChecked">
    <label class="form-check-label" for="flexCheckChecked">
    Flush records
    </label>
    </div>


    <button type="submit" class="btn btn-primary">Transfer project</button>

</form>

<?
    $module->includeJs("js/project_page.js");
?>
