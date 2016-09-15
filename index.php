<?php

// Kickstart the framework
$f3=require('lib/base.php');

$f3->set('DEBUG', 1);
if ((float)PCRE_VERSION < 7.9)
    trigger_error('PCRE version is out of date');

// Load configuration
$f3->config('app/config.ini');

$f3->config('app/routes.ini');

$f3->run();
