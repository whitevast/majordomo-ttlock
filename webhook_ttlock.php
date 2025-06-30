<?php
include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
include_once("./modules/ttlock/ttlock.class.php");
$ttlock_module = new ttlock();
$ttlock_module->getConfig();
$ttlock_module->receive($_REQUEST);
?>
<p>NO data</p>