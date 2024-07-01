<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\Unit\DeepCopy;

use BackgroundMessageTransport\DeepCopy\DeepValueCopy;
use BackgroundMessageTransport\Tests\Unit\DeepCopy\Examples\ContainsArrays;
use BackgroundMessageTransport\Tests\Unit\DeepCopy\Examples\ContainsObjects;
use BackgroundMessageTransport\Tests\Unit\DeepCopy\Examples\Scalar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class DeepValueCopyTest extends TestCase
{
    private DeepValueCopy $testedObject;

    protected function setUp(): void
    {
        $this->testedObject = new DeepValueCopy(
            new PropertyAccessor(),
        );
    }

    public function test_it_can_copy_scalar_values(): void
    {
        $object1 = new Scalar(
            string: 'string1',
            int: 1,
            float: 1.1,
            bool: true,
        );

        $object2 = new Scalar(
            string: 'string2',
            int: 2,
            float: 2.2,
            bool: false,
        );

        $this->testedObject->copyValues($object1, $object2);

        self::assertSame('string1', $object2->getString());
        self::assertSame(1, $object2->getInt());
        self::assertSame(1.1, $object2->getFloat());
        self::assertTrue($object2->isBool());
    }

    public function test_it_can_copy_array_values(): void
    {
        $object1 = new ContainsArrays(
            [
                1,
                2,
                [
                    'foo' => 'bar',
                    'baz' => 'qux',
                ]
            ],
            [
                'a' => 1,
                '3' => 'Foo',
                'theObject' => new Scalar(
                    string: 'string1',
                    int: 1,
                    float: 1.1,
                    bool: true,
                ),
            ],
            null,
        );

        $object2 = new ContainsArrays(
            [3, 4],
            [
                'b' => 2,
                '4' => 'Bar',
                'theObject' => new Scalar(
                    string: 'string2',
                    int: 2,
                    float: 2.2,
                    bool: false,
                ),
            ],
            [],
        );

        $this->testedObject->copyValues($object1, $object2);

        self::assertSame(
            [
                1,
                2,
                [
                    'foo' => 'bar',
                    'baz' => 'qux',
                ]
            ],
            $object2->getArray1()
        );
        self::assertArrayHasKey('theObject', $object2->getArray2());
        self::assertNotSame(
            $object1->getArray2()['theObject'],
            $object2->getArray2()['theObject']
        );
        self::assertEquals(
            $object1->getArray2()['theObject'],
            $object2->getArray2()['theObject']
        );
        self::assertEquals(
            [
                'a' => 1,
                '3' => 'Foo',
                'theObject' => $object1->getArray2()['theObject'],
            ],
            $object2->getArray2(),
        );
        self::assertNull($object2->getArray3());
    }

    public function test_copies_object_values(): void
    {
        $targetObject1 = new Scalar(string: 'string1', int: 1, float: 1.1, bool: false);
        $targetObject2 = new Scalar(string: 'string2', int: 2, float: 2.2, bool: false);
        $target = new ContainsObjects($targetObject1);
        $target->setObject2($targetObject2);
        $targetObjectToBeDeleted = new Scalar(string: 'delete me', int: 6, float: 6.6, bool: true, id: 1);
        $targetObjectToBeUpdated = new Scalar(string: 'update me 1', int: 7, float: 7.7, bool: true, id: 2);
        $targetObjectToBeUpdatedNew = new Scalar(string: 'update me 2', int: 8, float: 8.8, bool: true);
        $target->addScalar($targetObjectToBeDeleted);
        $target->addScalar($targetObjectToBeUpdated);
        $target->addScalar($targetObjectToBeUpdatedNew);

        $sourceObject1 = new Scalar(string: 'string10', int: 10, float: 10.1, bool: true);
        $source = new ContainsObjects($sourceObject1);
        $sourceObject2 = new Scalar(string: 'string20', int: 20, float: 20.2, bool: true);
        $sourceObject3 = new Scalar(string: 'string30', int: 30, float: 30.3, bool: true);
        $source->setObject2($sourceObject2);
        $source->setObject3($sourceObject3);
        $sourceObjectToBeUpdated = new Scalar(string: 'It was updated', int: 70, float: 70.7, bool: true, id: 2);
        $sourceObjectToBeUpdatedNew = new Scalar(string: 'It was updated 2', int: 80, float: 80.8, bool: true);
        $sourceObject4 = new Scalar(string: 'string40', int: 40, float: 40.4, bool: true);
        $sourceObject5 = new Scalar(string: 'string50', int: 50, float: 50.5, bool: true);
        $source->addScalar($targetObjectToBeDeleted);
        $source->removeScalar($targetObjectToBeDeleted);
        $source->addScalar($sourceObjectToBeUpdated);
        $source->addScalar($sourceObjectToBeUpdatedNew);
        $source->addScalar($sourceObject4);
        $source->addScalar($sourceObject5);

        $this->testedObject->copyValues($source, $target);

        self::assertEquals($sourceObject1, $target->getObject1());
        self::assertNotSame($sourceObject1, $target->getObject1());
        self::assertEquals($sourceObject2, $target->getObject2());
        self::assertNotSame($sourceObject2, $target->getObject2());
        self::assertCount(4, $target->getScalars());
        self::assertEquals(
            [
                1 => $sourceObjectToBeUpdated,
                2 => $sourceObjectToBeUpdatedNew,
                3 => $sourceObject4,
                4 => $sourceObject5,
            ],
            $target->getScalars()->toArray(),
        );
    }
}
