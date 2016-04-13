<?php

$baseAutoloader = __DIR__."/vendor/autoload.php";
if (file_exists($baseAutoloader)) {
    require_once($baseAutoloader);
} else {
    // Otherwise, we're probably included via require-dev
    require_once("vendor/autoload.php");
}

$application = new \Symfony\Component\Console\Application("SyringeVerifier", "1.0");
$application->add(new \Silktide\SyringeVerifier\VerifyCommand());
$application->run();