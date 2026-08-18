<?php

declare(strict_types=1);

namespace AIEA\AI;

final class EndpointValidator
{
    /** @throws ProviderException */
    public function assertSafe(string $url, bool $allowHttpForLocalDevelopment = false): void
    {
        $parts = wp_parse_url($url);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        if ($host === '' || !in_array($scheme, $allowHttpForLocalDevelopment ? ['https', 'http'] : ['https'], true)) {
            throw new ProviderException('Provider endpoint must use HTTPS and have a hostname.', 'unsafe_endpoint');
        }

        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new ProviderException('Provider endpoint points to a prohibited host.', 'unsafe_endpoint');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if ($addresses === false || $addresses === []) {
            throw new ProviderException('Provider hostname cannot be resolved safely.', 'unsafe_endpoint');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new ProviderException('Provider endpoint resolves to a prohibited network.', 'unsafe_endpoint');
            }
        }
    }
}
