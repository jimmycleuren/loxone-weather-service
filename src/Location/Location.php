<?php

declare(strict_types=1);

namespace App\Location;

/**
 * A Miniserver's location, kept as the decimal strings it sends: they end up
 * verbatim in the provider request and in the CSV header.
 */
final readonly class Location
{
    private const string DECIMAL = '/^-?\d{1,5}(\.\d{1,10})?$/';

    public function __construct(
        public string $latitude,
        public string $longitude,
        public string $altitude,
    ) {
        if (!preg_match(self::DECIMAL, $latitude) || abs((float) $latitude) > 90) {
            throw new InvalidLocation(\sprintf('Invalid latitude "%s"', $latitude));
        }
        if (!preg_match(self::DECIMAL, $longitude) || abs((float) $longitude) > 180) {
            throw new InvalidLocation(\sprintf('Invalid longitude "%s"', $longitude));
        }
        if (!preg_match(self::DECIMAL, $altitude)) {
            throw new InvalidLocation(\sprintf('Invalid altitude "%s"', $altitude));
        }
    }

    /**
     * From the Miniserver request: coord=<longitude>,<latitude> and asl=<metres>.
     *
     * @throws InvalidLocation
     */
    public static function fromQuery(mixed $coord, mixed $asl): self
    {
        if (!\is_string($coord) || 1 !== substr_count($coord, ',')) {
            throw new InvalidLocation('Expected coord=<longitude>,<latitude>');
        }
        [$longitude, $latitude] = explode(',', $coord);

        if (null === $asl || '' === $asl) {
            $asl = '0';
        }
        if (!\is_string($asl)) {
            throw new InvalidLocation('Invalid altitude');
        }

        return new self($latitude, $longitude, $asl);
    }
}
