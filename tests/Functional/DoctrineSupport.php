<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\Functional;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait DoctrineSupport
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    abstract protected static function getServiceByClassName(string $className): object;

    private static function createDataBase(): void
    {
        $entityManager = self::getServiceByClassName(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }
}