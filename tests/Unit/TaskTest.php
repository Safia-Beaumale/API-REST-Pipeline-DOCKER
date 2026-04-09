<?php

namespace App\Tests\Unit;

use App\Entity\Task;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testDefaultStatus(): void
    {
        $task = new Task();
        $task->setTitle('Test task');

        $this->assertSame('pending', $task->getStatus());
    }

    public function testSettersAndGetters(): void
    {
        $task = new Task();
        $task->setTitle('My task');
        $task->setDescription('A description');
        $task->setStatus('done');

        $this->assertSame('My task', $task->getTitle());
        $this->assertSame('A description', $task->getDescription());
        $this->assertSame('done', $task->getStatus());
    }

    public function testToArrayContainsExpectedKeys(): void
    {
        $task = new Task();
        $task->setTitle('Array test');

        // Simulate lifecycle callback
        $reflection = new \ReflectionClass($task);
        $method = $reflection->getMethod('onPrePersist');
        $method->invoke($task);

        $data = $task->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertSame('Array test', $data['title']);
        $this->assertSame('pending', $data['status']);
        $this->assertNull($data['description']);
    }

    public function testNullableDescription(): void
    {
        $task = new Task();
        $task->setTitle('No desc');
        $task->setDescription(null);

        $this->assertNull($task->getDescription());
    }
}
