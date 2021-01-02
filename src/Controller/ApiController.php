<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    /**
     * @Route("/interface/api", name="api")
     */
    public function index(): Response
    {
        return new JsonResponse([
            "1" => "2100-01-01",
            "2" => "2100-01-01"
        ]);
    }
}
