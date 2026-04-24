<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestControllerTest extends WebTestCase
{
    public function test_index(): void
    {
        $client = static::createClient();
        $client->request('GET', '/test');

        $this->assertResponseIsSuccessful();
        $this->assertSame('OK', $client->getResponse()->getContent());
    }
}