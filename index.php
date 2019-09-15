<?php

use RenanBritz\DoctrineUtils\Persistence;
use RenanBritz\DoctrineUtils\Entities\Person;

require "bootstrap.php";

$persistence = new Persistence($entityManager);

$input = json_decode(file_get_contents('php://input'), true);

//dump($entityManager->getClassMetadata(Person::class));
//dump($entityManager->getClassMetadata(\RenanBritz\DoctrineUtils\Entities\Address::class));
//dump($entityManager->getClassMetadata(\RenanBritz\DoctrineUtils\Entities\City::class));

$e = $entityManager->getRepository(Person::class)->findOneBy(['id' => 3]);
//$e = Person::class;

$persistence->persist($e, $input ?? []);
$entityManager->flush();