<?php

use RenanBritz\DoctrineUtils\Persistence;
use RenanBritz\DoctrineUtils\Entities\Person;

require "bootstrap.php";

$persistence = new Persistence($entityManager);

$repo = $entityManager->getRepository(Person::class);

//$p = $repo->findOneBy(['id' => 3]);

$r = $entityManager->createQueryBuilder()
    ->from(Person::class, 'p')
    ->select('p, a')
    ->leftJoin('p.addresses', 'a')
    ->getQuery()->getSQL();

dump($r);