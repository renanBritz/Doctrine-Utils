<?php

namespace RenanBritz\DoctrineUtils;

use LogicException;
use InvalidArgumentException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Common\Collections\ArrayCollection;

class Persistence
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

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

    public function __construct(EntityManagerInterface $entityManager)
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

    /**
     * @param $entity
     * @param array $data
     * @param null $parentRef
     * @return object
     * @throws EntityNotFoundException
     */
    private function _persist($entity, array &$data, $parentRef = null)
    {
        $metadata = $this->getMetadata(get_class($entity));

        $this->setFields($entity, $metadata, $data);

        // Set associations
        foreach ($metadata->associationMappings as $assocName => $assocMapping) {
            $childData = $data[$assocName] ?? null;
            $ucField = ucfirst($assocMapping['fieldName']);

            if (in_array($assocMapping['type'], [ClassMetadata::MANY_TO_MANY, ClassMetadata::ONE_TO_MANY])) {
                if (!$childData) {
                    continue;
                }

                $collection = new ArrayCollection();
                $presentIds = [];

                foreach ($childData as $datum) {
                    $child = null;

                    // Attempts to find child entity by the given id in data.
                    if (isset($datum['id'])) {
                        $child = $this->em->getRepository($assocMapping['targetEntity'])->findOneById($datum['id']);

                        if ($child === null) {
                            throw new EntityNotFoundException("Invalid id {$datum['id']} for entity {$assocMapping['targetEntity']}");
                        }

                        $presentIds[] = $datum['id'];
                    } else {
                        // If no id is given, create a new instance of the entity.
                        $child = new $assocMapping['targetEntity'];
                    }

                    // Persist the given data to the child entity
                    $collection->add($this->_persist($child, $datum, $entity));
                }

                // Use the PersistentCollection to remove non-present elements.
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
                    // If the parent entity has not been persisted yet (no id given), simply set the collection.
                    $entity->{'set' . $ucField}($collection);
                }
            } else {
                $parentClass = null;

                if (isset($parentRef)) {
                    $parentClass = get_class($parentRef);
                }

                $targetEntity = $metadata->associationMappings[$assocName]['targetEntity'];

                // Sets parent ref to the appropriate association. Must have a setter method.
                if ($parentClass && ($targetEntity === $parentClass || is_subclass_of($parentClass, $targetEntity)) && method_exists($entity, 'set' . $ucField)) {
                    $entity->{'set' . $ucField}($parentRef);
                } else if (!!$childData) {
                    $child = null;

                    if (isset($childData['id'])) {
                        $child = $this->em->getRepository($assocMapping['targetEntity'])->findOneById($childData['id']);

                        if ($child === null) {
                            throw new LogicException("Invalid id {$childData['id']} for entity {$assocMapping['targetEntity']}");
                        }
                    } else {
                        $child = new $assocMapping['targetEntity'];
                    }

                    // Should not persist association if its blacklisted or only contains 'id'
                    if (isset($this->persistBlacklist[$assocName]) || count(array_diff_key($childData, ['id' => 'id'])) === 0) {
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
     * @param $entityObject
     * @param array $data
     * @return array
     * @throws EntityNotFoundException
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
        $this->em->flush();

        return $this->getIds($entityObject, $data);
    }
}