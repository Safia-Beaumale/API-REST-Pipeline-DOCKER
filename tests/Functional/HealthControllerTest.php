<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $response = $client->getResponse();

        $this->assertResponseStatusCodeSame(200);
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testTasksListEndpointReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks');

        $this->assertResponseStatusCodeSame(200);
        $this->assertJson($client->getResponse()->getContent());
    }

    public function testCreateTaskReturns422WithoutTitle(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/tasks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['description' => 'No title here'])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetNonExistentTaskReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks/99999');

        $this->assertResponseStatusCodeSame(404);
    }
}
