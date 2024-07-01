<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\Functional;

use Monolog\Handler\TestHandler;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase as SymfonyKernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCase extends SymfonyKernelTestCase
{
    private static TestHandler $testLogger;

    /**
     * @before
     *
     * Boots the kernel and makes the logger testable
     */
    public static function initialiseTestLogger(): void
    {
        self::$testLogger = new TestHandler();
        self::getServiceByName('monolog.logger', Logger::class)
            ->pushHandler(self::$testLogger);
    }

    public static function getTestLogger(): TestHandler
    {
        return self::$testLogger;
    }

    protected static function getBootedKernel(): KernelInterface
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        return self::$kernel;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    protected static function getServiceByClassName(string $className): object
    {
        $service = self::getContainer()->get($className);

        if (!$service instanceof $className) {
            throw new \RuntimeException(\sprintf('Service %s not found or not instance of %s', $className, $className));
        }

        return $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    protected static function getServiceByName(string $name, string $className): object
    {
        $service = self::getContainer()->get($name);

        if (!$service instanceof $className) {
            throw new \RuntimeException(\sprintf('Service %s not found or not instance of %s', $className, $className));
        }

        return $service;
    }
}