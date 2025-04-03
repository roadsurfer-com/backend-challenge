<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class HealthControllerTest extends WebTestCase
{
    public function testSuccessfulHealthResponse(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }
}
