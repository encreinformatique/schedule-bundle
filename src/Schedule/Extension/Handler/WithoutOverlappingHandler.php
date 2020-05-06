<?php

namespace Zenstruck\ScheduleBundle\Schedule\Extension\Handler;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Zenstruck\ScheduleBundle\Schedule\Extension;
use Zenstruck\ScheduleBundle\Schedule\Extension\ExtensionHandler;
use Zenstruck\ScheduleBundle\Schedule\Extension\WithoutOverlappingExtension;
use Zenstruck\ScheduleBundle\Schedule\Task\TaskRunContext;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class WithoutOverlappingHandler extends ExtensionHandler
{
    private $lockFactory;

    public function __construct(LockFactory $lockFactory = null)
    {
        if (null === $lockFactory && !\class_exists(LockFactory::class)) {
            throw new \LogicException(\sprintf('Symfony Lock is required to use the "%s" extension. Install with "composer require symfony/lock".', WithoutOverlappingExtension::class));
        }

        $this->lockFactory = $lockFactory ?: new LockFactory(self::createLocalStore());
    }

    /**
     * @param WithoutOverlappingExtension|Extension $extension
     */
    public function filterTask(TaskRunContext $context, Extension $extension): void
    {
        $extension->aquireLock($this->lockFactory, $context->getTask()->getId());
    }

    /**
     * @param WithoutOverlappingExtension|Extension $extension
     */
    public function afterTask(TaskRunContext $context, Extension $extension): void
    {
        $extension->releaseLock();
    }

    public function supports(Extension $extension): bool
    {
        return $extension instanceof WithoutOverlappingExtension;
    }

    private static function createLocalStore(): PersistingStoreInterface
    {
        if (SemaphoreStore::isSupported()) {
            return new SemaphoreStore();
        }

        return new FlockStore();
    }
}
