<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\AI\AIManager;

final class ProviderController
{
    public function __construct(private readonly AIManager $manager, private readonly RestResponder $response)
    {
    }

    public function test(): \WP_REST_Response
    {
        $health = $this->manager->testCurrentProvider();
        return $this->response->success([
            'healthy' => $health->healthy,
            'latency_ms' => $health->latencyMs,
            'message' => $health->message,
            'request_id' => $health->requestId,
        ], $health->healthy ? 200 : 422);
    }
}
