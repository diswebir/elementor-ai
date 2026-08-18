<?php

declare(strict_types=1);

namespace AIEA\AI;

use AIEA\Admin\Settings;

final class ProviderRegistry
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SecretManager $secrets,
        private readonly EndpointValidator $endpointValidator,
    ) {
    }

    public function current(): ProviderInterface
    {
        $config = $this->settings->all();
        return new OpenAICompatibleProvider($config, $this->secrets, $this->endpointValidator);
    }
}
