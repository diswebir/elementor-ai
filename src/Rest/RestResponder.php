<?php

declare(strict_types=1);

namespace AIEA\Rest;

use WP_Error;
use WP_REST_Response;

final class RestResponder
{
    /** @param array<string, mixed> $data */
    public function success(array $data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'request_id' => wp_generate_uuid4(),
            'data' => $data,
        ], $status);
    }

    public function error(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status, 'request_id' => wp_generate_uuid4()]);
    }
}
