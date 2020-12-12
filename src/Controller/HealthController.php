<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    /**
     * @Route("/health", name="health")
     */
    public function index(): Response
    {
        return new Response("healty");
    }
}
