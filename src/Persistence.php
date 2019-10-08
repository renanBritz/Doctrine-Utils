<?php

namespace RenanBritz\DoctrineUtils;

use DateTime;
use LogicException;
use Doctrine\ORM\Query;
use InvalidArgumentException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
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
     * Names of associations that should not be persisted.
     *
     * @var array
     */
    private $persistBlacklist = [];

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

    /**
     * The strategy used to remove resource collection associations.
     *
     * @var int
     */
    public static $COLLECTION_REMOVE_STRATEGY = self::REMOVE_FROM_COLLECTION;

    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    public function addPersistBlacklist($name)
    {
        $this->persistBlacklist[$name] = true;
    }

    public function getPersistBlacklist()
    {
        return $this->persistBlacklist;
    }

    public function clearPersistBlacklist()
    {
        $this->persistBlacklist = [];
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
            $childData = $data[$assocName] ?? null;
            $ucField = ucfirst($assocMapping['fieldName']);

            if (in_array($assocMapping['type'], [ClassMetadata::MANY_TO_MANY, ClassMetadata::ONE_TO_MANY]) && isset($childData)) {
                $collection = new ArrayCollection();
                $presentIds = [];

                foreach ($childData as $datum) {
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

                        // Remove non-present elements from the original collection.
                        foreach ($originalCollection as $element) {
                            if (!in_array($element->getId(), $presentIds)) {
                                $originalCollection->removeElement($element);
                            }
                        }

                        // Add the new elements to the original collection.
                        foreach ($collection as $element) {
                            if (!$originalCollection->contains($element)) {
                                $originalCollection->add($element);
                            }
                        }
                    } else {
                        $entity->{'set' . $ucField}($collection);
                    }
                } else if (self::$COLLECTION_REMOVE_STRATEGY === self::REMOVE_DQL) {
                    $entity->{'set' . $ucField}($collection);

                    // Delete non-present elements with DQL.
                    // TODO: (Fix) When assoc is Unidirectional Many to Many the targetEntity becomes orphan, it should be deleted.
                    // TODO: Add SoftDelete support.
                    if ($entity->getId() !== null && isset($assocMapping['mappedBy'])) {
                        $deleteQuery = $this->em->createQueryBuilder()
                            ->from($assocMapping['targetEntity'], 'te');

                        if (isset($this->getMetadata($assocMapping['targetEntity'])->fieldMappings['deletedAt'])) {
                            $deleteQuery->update();
                            $deleteQuery->set('te.deletedAt', ':deletedAt');
                            $deleteQuery->setParameter('deletedAt', new DateTime());
                        } else {
                            $deleteQuery->delete();
                        }

                        // Remove non present ids.
                        $deleteQuery->where('te.id NOT IN (:presentIds)')
                            ->andWhere('te.' . $assocMapping['mappedBy'] . ' = :entityId')
                            ->setParameter('presentIds', $presentIds ?: [-1])
                            ->setParameter('entityId', $entity->getId());

                        $this->deleteQueries[] = $deleteQuery->getQuery();
                    }
                }
            } else {
                $parentClass = null;

                if (isset($parentRef)) {
                    $parentClass = get_class($parentRef);
                }

                if ($parentClass && $metadata->associationMappings[$assocName]['targetEntity'] === $parentClass && method_exists($entity, 'set' . $ucField)) {
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

                    if (isset($this->persistBlacklist[$assocName])) {
                        $entity->{'set' . $ucField}($child);
                    } else {
                        $entity->{'set' . $ucField}($this->_persist($child, $childData, $entity));
                    }
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
            $childData = $data[$assocName] ?? null;
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

    /**
     * Useful when making multiple persists in the same request. Dont forget to call commit()
     */
    public function beginTransaction()
    {
        $this->em->beginTransaction();
    }

    public function commit()
    {
        $this->em->commit();
    }
}