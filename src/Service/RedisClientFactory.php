<?php

namespace App\Service;

use Predis\Client;

class RedisClientFactory
{
    public static function create(string $redisUrl): Client
    {
        return new Client($redisUrl);
    }
}
