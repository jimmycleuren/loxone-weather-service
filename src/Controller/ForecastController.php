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
        $coord = $request->get('coord');
        list($lon, $lat) = explode(",", $coord);
        $asl = $request->get('asl');
        $format = $request->get('format');

        $locationProvider->setCoordinates($lat, $lon, $asl);

        if ($format && $format == 1) {
            return new Response($weatherProvider->getCSV());
        }

        throw $this->createNotFoundException();
    }
}
