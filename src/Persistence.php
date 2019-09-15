<?php

namespace RenanBritz\DoctrineUtils;

use LogicException;
use Doctrine\ORM\Query;
use InvalidArgumentException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

class Persistence
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Query[]
     */
    protected $deleteQueries = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Calls setter methods for entity fields.
     *
     * @param $entity
     * @param ClassMetadata $metadata
     * @param array $data
     */
    private function setFields($entity, ClassMetadata $metadata, array &$data)
    {
        foreach ($metadata->fieldMappings as $fieldName => $fieldMapping) {
            if ($fieldName === 'id') {
                continue;
            }

            if (isset($data[$fieldName])) {
                $value = $data[$fieldName];
                $setter = 'set' . ucfirst($fieldName);

                if (!method_exists($entity, $setter)) {
                    throw new LogicException("Setter method not found for field {$fieldName} in class {$metadata->name}");
                }

                $entity->{$setter}($value);
            }
        }
    }

    private function _persist($entity, array &$data, $parentRef = null)
    {
        $className = get_class($entity);
        $metadata = $this->em->getClassMetadata($className);

        $this->setFields($entity, $metadata, $data);

        // Set associations
        foreach ($metadata->associationMappings as $assocName => $assocMapping) {
            $childData = $data[$assocName];
            $ucField = ucfirst($assocMapping['fieldName']);

            if (in_array($assocMapping['type'], [ClassMetadata::MANY_TO_MANY, ClassMetadata::ONE_TO_MANY])) {
                $collection = new ArrayCollection();
                $presentIds = [];

                foreach ($childData ?? [] as $datum) {
                    $child = null;

                    if (isset($datum['id'])) {
                        $child = $this->em->getRepository($assocMapping['targetEntity'])->findOneBy(['id' => $datum['id']]);

                        if ($child === null) {
                            throw new LogicException("Invalid id {$datum['id']} for entity {$assocMapping['targetEntity']}");
                        }

                        $presentIds[] = $datum['id'];
                    } else {
                        $child = new $assocMapping['targetEntity'];
                    }

                    $collection->add($this->_persist($child, $datum, $entity));
                }

                $entity->{'set' . $ucField}($collection);

                if (isset($assocMapping['mappedBy'])) {
                    // Remove non present ids.
                    $this->deleteQueries[] = $this->em->createQueryBuilder()
                        ->from($assocMapping['targetEntity'], 'te')
                        ->delete()
                        ->where('te.id NOT IN (:presentIds)')
                        ->andWhere('te.' . $assocMapping['mappedBy'] . ' = :entityId')
                        ->setParameter('presentIds', $presentIds ?: [-1])
                        ->setParameter('entityId', $entity->getId())
                        ->getQuery();
                }
            } else {
                $parentClass = null;

                if (isset($parentRef)) {
                    $parentClass = get_class($parentRef);
                }

                if ($parentClass && $assocMapping['targetEntity'] === $parentClass) {
                    $entity->{'set' . $ucField}($parentRef);
                } else if (isset($childData)) {
                    $child = null;

                    if (isset($childData['id'])) {
                        $child = $this->em->getReference($assocMapping['targetEntity'], $childData['id']);

                        if ($child === null) {
                            throw new LogicException("Invalid id {$childData['id']} for entity {$assocMapping['targetEntity']}");
                        }
                    }  else {
                        $child = new $assocMapping['targetEntity'];
                    }

                    $entity->{'set' . $ucField}($this->_persist($child, $childData, $entity));
                }
            }
        }

        $this->em->persist($entity);

        return $entity;
    }

    /**
     * @param object|string $entityObject The root entity being persisted.
     * @param array $data
     * @throws \Doctrine\ORM\ORMException
     */
    public function persist($entityObject, array $data)
    {
        $className = null;

        if (!is_object($entityObject)) {
            if (!class_exists($entityObject)) {
                throw new InvalidArgumentException('The entityObject parameter must be either an object or a valid class name.');
            }

            $entityObject = new $entityObject;
        }

        $this->_persist($entityObject, $data);

        // Run the delete queries (removes non-present collection items).
        $this->em->beginTransaction();

        foreach ($this->deleteQueries as $query) {
            $query->execute();
        }

        $this->em->flush();
        $this->em->commit();

        $this->deleteQueries = [];
    }
}