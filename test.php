<?php

use RenanBritz\DoctrineUtils\Persistence;
use RenanBritz\DoctrineUtils\Entities\Person;

require "bootstrap.php";

$persistence = new Persistence($entityManager);

$repo = $entityManager->getRepository(Person::class);

$e = $repo->findOneById(3);
dump($e->getAddresses());