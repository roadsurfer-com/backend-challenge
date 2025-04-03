<?php

declare(strict_types=1);

namespace App\Controller;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HealthController extends AbstractFOSRestController
{
    #[Route('/health', name: 'health')]
    public function index(): JsonResponse
    {
        return $this->json('OK', Response::HTTP_OK);
    }
}
