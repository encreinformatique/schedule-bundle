<?php

namespace Zenstruck\ScheduleBundle\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Zenstruck\ScheduleBundle\DependencyInjection\Configuration;
use Zenstruck\ScheduleBundle\EventListener\TaskConfigurationSubscriber;
use Zenstruck\ScheduleBundle\Schedule;
use Zenstruck\ScheduleBundle\Schedule\Task\CommandTask;
use Zenstruck\ScheduleBundle\Schedule\Task\ProcessTask;
use Zenstruck\ScheduleBundle\Tests\Fixture\MockScheduleBuilder;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TaskConfigurationTest extends TestCase
{
    /**
     * @test
     */
    public function minimal_task_configuration()
    {
        $schedule = $this->createSchedule([
            [
                'command' => 'my:command',
                'frequency' => '0 * * * *',
            ],
            [
                'command' => 'another:command',
                'frequency' => '0 0 * * *',
            ],
        ]);

        $this->assertCount(2, $schedule->all());

        [$task1, $task2] = $schedule->all();

        $this->assertInstanceOf(CommandTask::class, $task1);
        $this->assertSame('my:command', $task1->getDescription());
        $this->assertNull($task1->getTimezone());
        $this->assertSame('0 * * * *', $task1->getExpression());
        $this->assertCount(0, $task1->getExtensions());

        $this->assertInstanceOf(CommandTask::class, $task2);
        $this->assertSame('another:command', $task2->getDescription());
        $this->assertNull($task1->getTimezone());
        $this->assertSame('0 0 * * *', $task2->getExpression());
        $this->assertCount(0, $task2->getExtensions());
    }

    /**
     * @test
     */
    public function can_configure_process_tasks()
    {
        $schedule = $this->createSchedule([
            [
                'command' => 'bash: /bin/script',
                'frequency' => '0 * * * *',
            ],
        ]);

        $this->assertCount(1, $schedule->all());
        $this->assertInstanceOf(ProcessTask::class, $schedule->all()[0]);
        $this->assertSame('/bin/script', $schedule->all()[0]->getDescription());
        $this->assertSame('0 * * * *', $schedule->all()[0]->getExpression());
        $this->assertCount(0, $schedule->all()[0]->getExtensions());
    }

    /**
     * @test
     */
    public function can_configure_compound_task()
    {
        $schedule = $this->createSchedule([
            [
                'command' => [
                    'my:command arg --option=foo',
                    'bash:/my-script',
                ],
                'frequency' => '0 * * * *',
                'without_overlapping' => null,
            ],
        ]);

        $this->assertCount(2, $schedule->all());

        [$task1, $task2] = $schedule->all();

        $this->assertInstanceOf(CommandTask::class, $task1);
        $this->assertSame('my:command', $task1->getDescription());
        $this->assertSame('0 * * * *', $task1->getExpression());
        $this->assertCount(1, $task1->getExtensions());
        $this->assertSame('Without overlapping', (string) $task1->getExtensions()[0]);

        $this->assertInstanceOf(ProcessTask::class, $task2);
        $this->assertSame('/my-script', $task2->getDescription());
        $this->assertSame('0 * * * *', $task2->getExpression());
        $this->assertCount(1, $task2->getExtensions());
        $this->assertSame('Without overlapping', (string) $task2->getExtensions()[0]);
    }

    /**
     * @test
     */
    public function can_configure_compound_task_with_descriptions()
    {
        $schedule = $this->createSchedule([
            [
                'command' => [
                    'my command' => 'my:command arg --option=foo',
                    'another command' => 'bash:/my-script',
                ],
                'frequency' => '0 * * * *',
                'without_overlapping' => null,
            ],
        ]);

        $this->assertCount(2, $schedule->all());

        [$task1, $task2] = $schedule->all();

        $this->assertInstanceOf(CommandTask::class, $task1);
        $this->assertSame('my command', $task1->getDescription());
        $this->assertSame('0 * * * *', $task1->getExpression());
        $this->assertCount(1, $task1->getExtensions());
        $this->assertSame('Without overlapping', (string) $task1->getExtensions()[0]);

        $this->assertInstanceOf(ProcessTask::class, $task2);
        $this->assertSame('another command', $task2->getDescription());
        $this->assertSame('0 * * * *', $task2->getExpression());
        $this->assertCount(1, $task2->getExtensions());
        $this->assertSame('Without overlapping', (string) $task2->getExtensions()[0]);
    }

    /**
     * @test
     */
    public function full_task_configuration()
    {
        $schedule = $this->createSchedule([
            [
                'command' => 'my:command --option',
                'frequency' => '0 0 * * *',
                'description' => 'my description',
                'timezone' => 'UTC',
                'without_overlapping' => null,
                'between' => [
                    'start' => 9,
                    'end' => 17,
                ],
                'unless_between' => [
                    'start' => 12,
                    'end' => '13:30',
                ],
                'ping_before' => [
                    'url' => 'https://example.com/before',
                ],
                'ping_after' => [
                    'url' => 'https://example.com/after',
                ],
                'ping_on_success' => [
                    'url' => 'https://example.com/success',
                ],
                'ping_on_failure' => [
                    'url' => 'https://example.com/failure',
                    'method' => 'POST',
                ],
                'email_after' => null,
                'email_on_failure' => [
                    'to' => 'sales@example.com',
                    'subject' => 'my subject',
                ],
            ],
        ]);

        $task = $schedule->all()[0];
        $extensions = $task->getExtensions();

        $this->assertSame('my description', $task->getDescription());
        $this->assertSame('UTC', $task->getTimezone()->getName());
        $this->assertCount(9, $extensions);
        $this->assertSame('Without overlapping', (string) $extensions[0]);
        $this->assertSame('Only run between 9:00 and 17:00', (string) $extensions[1]);
        $this->assertSame('Only run if not between 12:00 and 13:30', (string) $extensions[2]);
        $this->assertSame('Before Task, ping "https://example.com/before"', (string) $extensions[3]);
        $this->assertSame('After Task, ping "https://example.com/after"', (string) $extensions[4]);
        $this->assertSame('On Task Success, ping "https://example.com/success"', (string) $extensions[5]);
        $this->assertSame('On Task Failure, ping "https://example.com/failure"', (string) $extensions[6]);
        $this->assertSame('After Task, email output', (string) $extensions[7]);
        $this->assertSame('On Task Failure, email output to "sales@example.com"', (string) $extensions[8]);
    }

    private function createSchedule(array $taskConfig): Schedule
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [['tasks' => $taskConfig]]);

        return (new MockScheduleBuilder())
            ->addSubscriber(new TaskConfigurationSubscriber($config['tasks']))
            ->getRunner()
            ->buildSchedule()
        ;
    }
}