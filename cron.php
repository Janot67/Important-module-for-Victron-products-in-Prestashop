<?php
require_once dirname(__FILE__).'/../../config/config.inc.php';
require_once dirname(__FILE__).'/../../init.php';

$module = Module::getInstanceByName('ps_victronproducts');
if (Validate::isLoadedObject($module)) {
    $module->hookActionCronJob();
}