<?php

include(__DIR__."/vendor/autoload.php");


$application = new \Symfony\Component\Console\Application("SyringeVerifier", "1.0");
$application->add(new \Silktide\SyringeVerifier\VerifyCommand());
$application->run();