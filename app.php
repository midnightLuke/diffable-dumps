#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

define('BASE_DIR', __DIR__);

use App\Console\Command\TransformCommand;
use Symfony\Component\Console\Application;

set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$application = new Application();
$application->add(new TransformCommand());
$application->run();
