<?php

namespace tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

class Test extends TestCase
{
    public function test_connection_to_server()
    {
        $client = HttpClient::create();
        $response = $client->request('GET', ' http://volga-it-2021.ml/api/game/617bd2fde9df051bb57d2ef0');
        $this->assertEquals($response->getStatusCode(), 200);
    }
}
