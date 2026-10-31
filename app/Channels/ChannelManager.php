<?php

namespace App\Channels;

use App\Models\Channel;
use InvalidArgumentException;

/** Adding a platform = one new driver class + one line in config/channels.php. */
class ChannelManager
{
    /** @param array<string, class-string<ChannelDriver>> $drivers */
    public function __construct(private array $drivers)
    {
    }

    public function driver(Channel|string $channel): ChannelDriver
    {
        $name = $channel instanceof Channel ? $channel->driver : $channel;

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Unsupported channel driver [{$name}].");
        }

        return app($this->drivers[$name]);
    }

    /** @return string[] */
    public function available(): array
    {
        return array_keys($this->drivers);
    }

    public function supports(string $name): bool
    {
        return isset($this->drivers[$name]);
    }
}
