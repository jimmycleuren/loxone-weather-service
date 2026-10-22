<?php

declare(strict_types=1);

namespace App\Loxone;

use App\Forecast\Forecast;
use App\Forecast\ForecastRow;
use App\Forecast\WeatherIcon;

/**
 * Renders a forecast in the CSV format the Loxone Miniserver expects from
 * weather.loxone.com. Pinned byte-for-byte by the golden-file tests.
 */
final class LoxoneCsvRenderer
{
    private const string METADATA = "<mb_metadata>\n"
        ."id;name;longitude;latitude;height (m.asl.);country;timezone;utc-timedifference;sunrise;sunset;\n"
        ."local date;weekday;local time;temperature(C);feeledTemperature(C);windspeed(km/h);winddirection(degr);wind gust(km/h);low clouds(%);medium clouds(%);high clouds(%);precipitation(mm);probability of Precip(%);snowFraction;sea level pressure(hPa);relative humidity(%);CAPE;picto-code;radiation (W/m2)\n"
        ."</mb_metadata>\n"
        ."<valid_until>2100-01-01</valid_until>\n";

    public function render(Forecast $forecast): string
    {
        $header = [
            '',
            $forecast->city,
            $forecast->lon,
            $forecast->lat,
            $forecast->asl,
            $forecast->country,
            $forecast->timezone,
            'UTC'.($forecast->utcOffsetHours > 0 ? '+' : '').$forecast->utcOffsetHours,
            $forecast->sunrise,
            $forecast->sunset,
            '',
        ];

        $rows = array_map($this->row(...), $forecast->rows);

        return self::METADATA
            ."<station>\n"
            .implode(';', $header)."\n"
            .implode("\n", $rows)."\n"
            ."</station>\n";
    }

    private function row(ForecastRow $row): string
    {
        return implode(';', [
            date('d.m.Y', $row->timestamp),
            date('l', $row->timestamp),
            date('H', $row->timestamp),
            \sprintf('%5.1f', $row->temperature),
            \sprintf('%5.1f', $row->apparentTemperature),
            \sprintf('%3.0f', $row->windSpeed),
            \sprintf('%3.0f', $row->windDirection),
            \sprintf('%3.0f', $row->windGustSpeed),
            \sprintf('%3.0f', $row->cloudsLow),
            \sprintf('%3.0f', $row->cloudsMid),
            \sprintf('%3.0f', $row->cloudsHigh),
            \sprintf('%5.1f', $row->precipitationIntensity),
            \sprintf('%3.0f', $row->precipitationProbability),
            \sprintf('%3.1f', $row->snow),
            \sprintf('%4.0f', $row->pressure),
            \sprintf('%3.0f', $row->humidity),
            \sprintf('%6.0f', 0.0), // CAPE
            \sprintf('%d', self::pictoCode($row->icon)),
            \sprintf('%4.0f', $row->solarRadiation),
        ]);
    }

    /**
     * Loxone picto codes; 0 when the condition is unknown.
     */
    public static function pictoCode(?WeatherIcon $icon): int
    {
        return match ($icon) {
            WeatherIcon::Clear => 1,
            WeatherIcon::CloudsLight => 2,
            WeatherIcon::CloudsMedium => 7,
            WeatherIcon::CloudsHeavy => 19,
            WeatherIcon::Cloudy => 22,
            WeatherIcon::Fog => 16,
            WeatherIcon::RainLight => 31,
            WeatherIcon::RainMedium => 23,
            WeatherIcon::RainHeavy => 25,
            WeatherIcon::StormMedium => 28,
            WeatherIcon::StormHeavy => 27,
            WeatherIcon::SnowLight => 32,
            WeatherIcon::SnowMedium => 24,
            WeatherIcon::SnowHeavy => 26,
            null => 0,
        };
    }
}
