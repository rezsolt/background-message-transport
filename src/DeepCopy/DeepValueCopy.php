<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\DeepCopy;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Contracts\Service\ResetInterface;

final class DeepValueCopy implements ResetInterface
{
    private array $objectTargetObjectsForSourceIds = [];

    public function __construct(
        private readonly PropertyAccessor $propertyAccessor,
    ) {}

    public function copyValues(object $source, object $target): void
    {
        $this->reset();
        $this->copyObjectValues($source, $target);
    }

    public function reset(): void
    {
        $this->objectTargetObjectsForSourceIds = [];
    }

    private function copyObjectValues(object $source, object $target): void
    {
        if ($source::class !== $target::class) {
            throw new \InvalidArgumentException(
                'Source and target must be of the same class'
            );
        }

        $this->objectDataCopyStarted($source, $target);

        $sourceReflection = new \ReflectionObject($source);
        $sourceProperties = $sourceReflection->getProperties();
        foreach ($sourceProperties as $sourceProperty) {
            if (!$this->propertyAccessor->isReadable($source, $sourceProperty->getName())) {
                continue;
            }

            $sourceValue = $this->propertyAccessor->getValue($source, $sourceProperty->getName());
            $targetValue = $this->propertyAccessor->getValue($target, $sourceProperty->getName());

            if (\is_object($sourceValue) && \is_object($targetValue)) {
                if ($sourceValue instanceof Collection && $targetValue instanceof Collection) {
                    $this->copyCollection($sourceValue, $targetValue);
                    continue;
                }

                if ($this->isObjectsDataCopyStarted($sourceValue)) {
                    if (!$this->propertyAccessor->isWritable($target, $sourceProperty->getName())) {
                        throw new \RuntimeException(
                            \sprintf(
                                'Can not copy %s into %s->%s as that field is not writable.',
                                \get_class($sourceValue),
                                \get_class($target),
                                $sourceProperty->getName()
                            )
                        );
                    }
                    $newTargetValue = $this->getObjectFromCache($this->getObjectCacheKey($sourceValue));
                    $this->propertyAccessor->setValue($target, $sourceProperty->getName(), $newTargetValue);
                    continue;
                }
                $this->copyObjectValues($sourceValue, $targetValue);
                continue;
            }

            if ($sourceProperty->isReadOnly()
                || !$this->propertyAccessor->isWritable($target, $sourceProperty->getName())
            ) {
                continue;
            }

            if (\is_scalar($sourceValue) || \is_null($sourceValue) || \is_null($targetValue)) {
                $this->propertyAccessor->setValue($target, $sourceProperty->getName(), $sourceValue);
                continue;
            }

            if (\is_array($sourceValue)) {
                $this->copyArray($sourceValue, $targetValue, $target, $sourceProperty->getName());
                continue;
            }

            throw new \RuntimeException(\sprintf('Unexpected value type: %s', gettype($sourceValue)));
        }
    }

    private function copyCollection(
        Collection $sourceCollection,
        Collection $targetCollection,
    ): void {
        // Delete from target what was deleted from source
        $deletedItems = clone $targetCollection;

        foreach ($sourceCollection as $key => $sourceItem) {
            $targetItem = $this->findObjectInCollection($targetCollection, $sourceItem);
            if ($targetItem !== null) {
                $this->copyObjectValues($sourceItem, $targetItem);
                $deletedItems->removeElement($targetItem);
                continue;
            }

            $targetItem = $targetCollection->get($key);
            if ($targetItem !== null && $this->objectsHasSameIds($sourceItem, $targetItem)) {
                $this->copyObjectValues($sourceItem, $targetItem);
                $deletedItems->removeElement($targetItem);
                continue;
            }

            $this->updateChildObjectReferences($sourceItem);
            $targetCollection->add($sourceItem);
        }

        foreach ($deletedItems as $deletedItem) {
            $targetCollection->removeElement($deletedItem);
        }
    }

    private function copyArray(
        array &$sourceValue,
        array &$targetValue,
        object $target,
        string $propertyName
    ) {
        $newTargetValue = [];
        foreach ($sourceValue as $key => $value) {
            if (\is_object($value)
                &&\array_key_exists($key, $targetValue)
                && \is_object($targetValue[$key])
                && $value::class === $targetValue[$key]::class
            ) {
                $this->copyObjectValues($value, $targetValue[$key]);
                $newTargetValue[$key] = $targetValue[$key];
                continue;
            }

            $newTargetValue[$key] = $value;
        }

        $this->propertyAccessor->setValue($target, $propertyName, $newTargetValue);
    }

    private function updateChildObjectReferences(object $source): void
    {
        $sourceReflection = new \ReflectionObject($source);
        $sourceProperties = $sourceReflection->getProperties();
        foreach ($sourceProperties as $sourceProperty) {
            if (!$this->propertyAccessor->isReadable($source, $sourceProperty->getName())) {
                continue;
            }

            $sourceValue = $this->propertyAccessor->getValue($source, $sourceProperty->getName());

            if (!\is_object($sourceValue)) {
                continue;
            }

            if ($sourceValue instanceof Collection) {
                foreach ($sourceValue as $item) {
                    if (!\is_object($item)) {
                        $this->updateChildObjectReferences($item);
                    }
                }
                continue;
            }

            if ($this->isObjectsDataCopyStarted($sourceValue)) {
                $newTargetValue = $this->getObjectFromCache($this->getObjectCacheKey($sourceValue));
                $this->propertyAccessor->setValue($source, $sourceProperty->getName(), $newTargetValue);
            }
        }
    }

    private function isObjectsDataCopyStarted(object $source): bool
    {
        return isset($this->objectTargetObjectsForSourceIds[$this->getObjectCacheKey($source)]);
    }

    private function getObjectCacheKey(object $object): int
    {
        return \spl_object_id($object);
    }

    private function objectDataCopyStarted(object $source, object $target): void
    {
        $this->objectTargetObjectsForSourceIds[$this->getObjectCacheKey($source)] = $target;
    }

    private function getObjectFromCache(int $objectId): object
    {
        if (!isset($this->objectTargetObjectsForSourceIds[$objectId])) {
            throw new \InvalidArgumentException('Object not found.');
        }

        return $this->objectTargetObjectsForSourceIds[$objectId];
    }

    private function objectInCollection(Collection $collection, object $item): bool
    {
        return $this->findObjectInCollection($collection, $item) !== null;
    }

    private function findObjectInCollection(Collection $collection, object $item): ?object
    {
        $identifierProperty = 'id';
        $id = $this->propertyAccessor->getValue($item, $identifierProperty);
        if ($id === null) {
            return null;
        }

        foreach ($collection as $itemFormTheCollection) {
            if ($this->propertyAccessor->getValue($itemFormTheCollection, $identifierProperty) === $id) {
                return $itemFormTheCollection;
            }
        }

        return null;
    }

    private function objectsHasSameIds(object $sourceItem, object $targetItem): bool
    {
        $identifierProperty = 'id';

        return $this->propertyAccessor->getValue($sourceItem, $identifierProperty) === $this->propertyAccessor->getValue($targetItem, $identifierProperty);
    }
}
