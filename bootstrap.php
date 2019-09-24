<?php

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

require "vendor/autoload.php";

// Create a simple "default" Doctrine ORM configuration for Annotations
$isDevMode = true;
$config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/Entities"), $isDevMode, null, null, false);

// database configuration parameters
//$conn = array(
//    'driver' => 'pdo_sqlite',
//    'path' => __DIR__ . '/db.sqlite',
//);

$conn = [
    'driver' => 'pdo_mysql',
    'host' => 'database',
    'user' => 'root',
    'password' => 'root',
    'dbname' => 'test',
];

$entityManager = EntityManager::create($conn, $config);
