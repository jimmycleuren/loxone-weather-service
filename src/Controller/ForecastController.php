<?php

declare(strict_types=1);

namespace App\Controller;

use App\Location\InvalidLocation;
use App\Location\Location;
use App\Location\LocationProvider;
use App\WeatherProvider\WeatherProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ForecastController extends AbstractController
{
    private const int MAX_USER_LENGTH = 128;

    #[Route('/forecast/', name: 'forecast', methods: ['GET'])]
    public function index(LocationProvider $locationProvider, WeatherProvider $weatherProvider, Request $request): Response
    {
        // Format 2 gets the same content as 1: its differences are unknown. Checked
        // first, so an unsupported request does not store a location or call an API.
        if (!\in_array($request->query->get('format'), ['1', '2'], true)) {
            throw $this->createNotFoundException();
        }

        $user = $request->query->getString('user');
        if (\strlen($user) > self::MAX_USER_LENGTH) {
            return $this->badRequest('user is too long');
        }

        try {
            $location = Location::fromQuery($request->query->get('coord'), $request->query->get('asl'));
        } catch (InvalidLocation $e) {
            return $this->badRequest($e->getMessage());
        }

        $locationProvider->setLocation($user, $location);

        $weather = $weatherProvider->getCSV($user);
        if (null === $weather) {
            $weatherProvider->updateCache();
            $weather = $weatherProvider->getCSV($user);
        }

        return new Response($weather);
    }

    private function badRequest(string $message): Response
    {
        return new Response($message."\n", Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/plain']);
    }
}
