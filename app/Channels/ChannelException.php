<?php

namespace App\Channels;

use RuntimeException;

/** Raised by drivers; the message is safe to show to the store owner (never contains credentials). */
class ChannelException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $needsReauth = false, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
