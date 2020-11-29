# Loxone weather service

This is a small app to emulate the loxone weather service using weatherbit as a data source.

## What you need
- Some place to run the application (on a server running php, or as a docker container)
- Weatherbit api key
- Override the 'weather.loxone.com' dns record and point it to this application (I've done this on my pihole).

## Credits
Parts of this code are based on
- https://github.com/mjesun/loxberry-simple-weather-service
- https://github.com/sarnau/Inside-The-Loxone-Miniserver