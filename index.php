<?php

use RenanBritz\DoctrineUtils\Persistence;
use RenanBritz\DoctrineUtils\Entities2\Person;

require "bootstrap.php";

$persistence = new Persistence($entityManager);

$input = json_decode(file_get_contents('php://input'), true);

//$e = \App\Entities\Sale\Sale::class;
$e = $entityManager->getRepository(\App\Entities\Sale\Sale::class)->findOneById(3);

$input['saleDate'] = DateTime::createFromFormat('d/m/Y', $input['saleDate']);
$input['payment']['initialDate'] = DateTime::createFromFormat('d/m/Y', $input['payment']['initialDate']);
$input['payment']['endDate'] = DateTime::createFromFormat('d/m/Y', $input['payment']['endDate']);

header('Content-Type: application/json');
echo json_encode($persistence->persist($e, $input ?? []));
