<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Predis\Client as RedisClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RedisClient $redis,
    ) {}

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $health = [
            'status'    => 'healthy',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        try {
            $this->connection->executeQuery('SELECT 1');
            $health['database'] = 'connected';
        } catch (\Throwable) {
            $health['status']   = 'unhealthy';
            $health['database'] = 'disconnected';
        }

        try {
            $this->redis->ping();
            $health['cache'] = 'connected';
        } catch (\Throwable) {
            $health['status'] = 'unhealthy';
            $health['cache']  = 'disconnected';
        }

        $code = $health['status'] === 'healthy' ? 200 : 503;

        return new JsonResponse($health, $code);
    }
}
