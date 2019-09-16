<?php

namespace RenanBritz\DoctrineUtils;

use Doctrine\ORM\PersistentCollection;
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
    private $em;

    /**
     * @var Query[]
     */
    private $deleteQueries = [];

    /**
     * @var array
     */
    private $metadataCache = [];

    /**
     * Removes collection elements using a DQL query. (issue: Many to Many Unidirectional will become orphan, but they
     * should be deleted).
     *
     * @var integer
     */
    const REMOVE_DQL = 1;

    /**
     * Removes collection elements by syncing the original PersistentCollection. (Bad performance).
     *
     * @var integer
     */
    const REMOVE_FROM_COLLECTION = 2;

    public static $COLLECTION_REMOVE_STRATEGY = self::REMOVE_FROM_COLLECTION;

    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    private function getMetadata($className)
    {
        if (isset($this->metadataCache[$className])) {
            return $this->metadataCache[$className];
        }

        $metadata = $this->em->getClassMetadata($className);
        $this->metadataCache[$className] = $metadata;

        return $metadata;
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
        $metadata = $this->getMetadata(get_class($entity));

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
                        $child = $this->em->getRepository($assocMapping['targetEntity'])->findOneById($datum['id']);

                        if ($child === null) {
                            throw new LogicException("Invalid id {$datum['id']} for entity {$assocMapping['targetEntity']}");
                        }

                        $presentIds[] = $datum['id'];
                    } else {
                        $child = new $assocMapping['targetEntity'];
                    }

                    $collection->add($this->_persist($child, $datum, $entity));
                }

                if (self::$COLLECTION_REMOVE_STRATEGY === self::REMOVE_FROM_COLLECTION) {
                    // Use the PersistentCollection to update non-present elements (bad performance).
                    if ($entity->getId() !== null) {
                        /** @var PersistentCollection $originalCollection */
                        $originalCollection = $entity->{'get' . $ucField}();

                        // Add the new elements to the original collection.
                        foreach ($collection as $element) {
                            if (!$originalCollection->contains($element)) {
                                $originalCollection->add($element);
                            }
                        }

                        // Remove non-present elements from the original collection.
                        foreach ($originalCollection as $element) {
                            if (!$collection->contains($element)) {
                                $originalCollection->removeElement($element);
                            }
                        }
                    } else {
                        $entity->{'set' . $ucField}($collection);
                    }
                } else if (self::$COLLECTION_REMOVE_STRATEGY === self::REMOVE_DQL) {
                    $entity->{'set' . $ucField}($collection);

                    // Delete non-present elements with DQL. TODO: (Fix) When assoc is Unidirectional Many to Many the targetEntity becomes orphan, it should be deleted.
                    if ($entity->getId() !== null && isset($assocMapping['mappedBy'])) {
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
                        $child = $this->em->getRepository($assocMapping['targetEntity'])->findOneById($childData['id']);

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
     * @param object $entity The entity to get ids from.
     * @param array $data Data indicates which fields to return (the ones that were persisted).
     * @return array
     */
    private function getIds($entity, array &$data)
    {
        $ids = ['id' => $entity->getId()];
        $metadata = $this->getMetadata(get_class($entity));

        foreach ($metadata->associationMappings as $assocName => $assocMapping) {
            $childData = $data[$assocName];
            $ucField = ucfirst($assocMapping['fieldName']);

            if (isset($childData)) {
                if (in_array($assocMapping['type'], [ClassMetadata::MANY_TO_MANY, ClassMetadata::ONE_TO_MANY])) {
                    $collection = $entity->{'get' . $ucField}();

                    foreach ($collection as $element) {
                        $ids[$assocName][] = $element->getId();
                    }
                } else {
                    $ids[$assocName] = $this->getIds($entity->{'get' . $ucField}(), $childData);
                }
            }
        }

        return $ids;
    }

    /**
     * @param object|string $entityObject The root entity being persisted.
     * @param array $data
     * @throws \Doctrine\ORM\ORMException
     * @return array
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

        if (!empty($this->deleteQueries)) {
            // Run the delete queries (removes non-present collection items).
            $this->em->beginTransaction();

            foreach ($this->deleteQueries as $query) {
                $query->execute();
            }

            $this->em->flush();
            $this->em->commit();

            $this->deleteQueries = [];
        } else {
            $this->em->flush();
        }

        return $this->getIds($entityObject, $data);
    }
}