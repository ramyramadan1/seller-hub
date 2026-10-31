<?php

namespace App\Channels;

/** Basic SSRF protection for store URLs entered by users: https only, no private/loopback/reserved targets. */
final class UrlGuard
{
    /** @return string normalized base URL without trailing slash
     *  @throws ChannelException */
    public static function normalize(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);

        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new ChannelException('Enter the full store address starting with https://');
        }

        $host = $parts['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // gethostbyname returns the input unchanged when it cannot resolve; the HTTP call will then fail on its own.
        if (filter_var($ip, FILTER_VALIDATE_IP)
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new ChannelException('That address points to a private network and cannot be used.');
        }

        return $url;
    }
}
