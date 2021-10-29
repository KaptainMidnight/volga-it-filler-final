#!/usr/bin/env php
<?php


//use App\Command\FillerCommand;

use App\Command\FillerCommand;

require_once __DIR__ . '/../vendor/autoload.php';

// Create the Application
$application = new Symfony\Component\Console\Application;

// Adding command
$application->add(new FillerCommand());

// Run it
$application->run();
