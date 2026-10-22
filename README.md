[![Coverage Status](https://coveralls.io/repos/github/jimmycleuren/loxone-weather-service/badge.svg?branch=master)](https://coveralls.io/github/jimmycleuren/loxone-weather-service?branch=master)

# Loxone weather service

This is a small app to emulate the loxone weather service using Weatherbit or OpenWeatherMap as a data source.

## What you need
- Some place to run the application (on a server running php, or as a docker container)
- A Weatherbit or OpenWeatherMap api key
- Override the 'weather.loxone.com' dns record and point it to this application (I've done this on my pihole).

## Usage
### Docker-compose
- Copy the 2 files from examples/compose
- Adjust <weatherbit-key> in the docker-compose.yml file with your own key
- docker compose up -d

### Configuration
All settings are environment variables.

| Variable | Default | |
|---|---|---|
| `WEATHERBIT_KEY` | empty | Weatherbit api key |
| `OPENWEATHERMAP_KEY` | empty | OpenWeatherMap api key |
| `WEATHER_PROVIDER` | `auto` | `auto` (Weatherbit if its key is set, else OpenWeatherMap), `weatherbit` or `openweathermap` |
| `REDIS_URL` | empty | Optional, e.g. `redis://redis:6379`. Empty: locations and forecasts are stored as files in `var/data` (mount a volume there) |

Upgrading from a release that required Redis: keep `REDIS_URL` set and the stored locations are kept.

## Limitations
- Your miniserver will also check if you have a valid weather subscription (through service monitoring) and show you a warning that you do not have an active weather service. In the future, this will be handled by the application as well, so the warning disappears.

## Credits
Parts of this code are based on
- https://github.com/mjesun/loxberry-simple-weather-service
- https://github.com/sarnau/Inside-The-Loxone-Miniserver