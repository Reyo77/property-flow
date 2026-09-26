<?php

namespace App\Support\Webhooks;

/**
 * Decides whether we may send to a URL. Endpoints are typed in by customers, so without this an
 * endpoint could point at our own internal services (a server-side request forgery). Checked when
 * an endpoint is saved and again before every delivery, since DNS can change in between.
 */
class WebhookUrlGuard
{
    /**
     * Why the URL can't be used, or null when it can.
     */
    public function problem(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return __('Enter a full web address, starting with https://.');
        }

        if ($scheme !== 'https' && config('webhooks.require_https') === true) {
            return __('Use an https:// address, so deliveries are encrypted.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return __('Leave the username and password out of the address.');
        }

        if (config('webhooks.block_private_networks') !== true) {
            return null;
        }

        $host = trim($host, '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            return __('That address could not be found.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return __('That address points inside a private network, which isn\'t allowed.');
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }
}
