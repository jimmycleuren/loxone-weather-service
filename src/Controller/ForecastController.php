<?php

namespace App\Controller;

use App\LocationProvider\LocationProvider;
use App\WeatherProvider\WeatherProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ForecastController extends AbstractController
{
    /**
     * @Route("/forecast/", name="forecast")
     */
    public function index(LocationProvider $locationProvider, WeatherProvider $weatherProvider, Request $request): Response
    {
        list($lon, $lat) = explode(",", $request->get('coord'));
        $asl = $request->get('asl');
        $format = $request->get('format');
        $user = $request->get('user');

        $locationProvider->setCoordinates($user, $lat, $lon, $asl);

        $weather = $weatherProvider->getCSV($user);
        if (!$weather) {
            $weatherProvider->updateCache();
            $weather = $weatherProvider->getCSV($user);
        }

        if ($format && $format == 1) {
            return new Response($weather);
        }

        throw $this->createNotFoundException();
    }
}
