<?php

namespace App\Controller;

use App\LocationProvider\LocationProvider;
use App\WeatherProvider\WeatherProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ForecastController extends AbstractController
{
    #[Route('/forecast/', name: 'forecast')]
    public function index(LocationProvider $locationProvider, WeatherProvider $weatherProvider, Request $request): Response
    {
        [$lon, $lat] = explode(',', $request->get('coord'));
        $asl = $request->get('asl');
        $format = $request->get('format');
        $user = (string) $request->query->get('user', '');

        $locationProvider->setCoordinates($user, $lat, $lon, $asl);

        $weather = $weatherProvider->getCSV($user);
        if (!$weather) {
            $weatherProvider->updateCache();
            $weather = $weatherProvider->getCSV($user);
        }

        if ($format && 1 == $format) {
            return new Response($weather);
        }

        if ($format && 2 == $format) { // lets return the same content for format 2 as we don't know the changes
            return new Response($weather);
        }

        throw $this->createNotFoundException();
    }
}
