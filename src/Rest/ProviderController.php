<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\AI\AIManager;
use AIEA\AI\ProviderException;
use AIEA\Audit\AuditLogger;
use AIEA\Audit\AuditRepository;
use WP_REST_Request;
use WP_REST_Response;

final class ProviderController
{
    public function __construct(
        private readonly AIManager $manager,
        private readonly AuditLogger $audit,
        private readonly AuditRepository $auditRepository,
        private readonly RestResponder $response,
    ) {
    }

    public function test(WP_REST_Request $request): WP_REST_Response
    {
        $message = trim((string) $request->get_param('message'));
        if ($message === '') {
            $message = 'Reply exactly with: CONNECTION_OK';
        }

        $startedAt = microtime(true);
        try {
            $result = $this->manager->sendTestMessage($message);
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $this->audit->log('provider_test', 'completed', [
                'arguments_hash' => $message,
                'result_summary' => 'Provider response received.',
                'duration_ms' => $latency,
            ]);

            return $this->response->success([
                'healthy' => true,
                'latency_ms' => $latency,
                'message' => 'پیام آزمایشی با موفقیت ارسال و پاسخ دریافت شد.',
                'assistant_message' => mb_substr(wp_strip_all_tags($result->content), 0, 4000),
                'usage' => $result->usage,
                'request_id' => $result->requestId,
            ]);
        } catch (ProviderException $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $this->audit->log('provider_test', 'failed', [
                'arguments_hash' => $message,
                'result_summary' => 'Provider test failed.',
                'duration_ms' => $latency,
                'error_code' => $exception->safeCode,
            ]);

            return $this->response->success([
                'healthy' => false,
                'latency_ms' => $latency,
                'message' => $exception->getMessage(),
                'assistant_message' => null,
                'usage' => [],
                'request_id' => null,
            ], 422);
        } catch (\Throwable) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $this->audit->log('provider_test', 'failed', [
                'arguments_hash' => $message,
                'result_summary' => 'Provider test stopped unexpectedly.',
                'duration_ms' => $latency,
                'error_code' => 'provider_test_unexpected',
            ]);

            return $this->response->success([
                'healthy' => false,
                'latency_ms' => $latency,
                'message' => __('آزمون اتصال با خطای غیرمنتظره متوقف شد. لاگ را بررسی کنید.', 'ai-elementor-agent'),
                'assistant_message' => null,
                'usage' => [],
                'request_id' => null,
            ], 500);
        }
    }

    public function logs(): WP_REST_Response
    {
        return $this->response->success([
            'entries' => $this->auditRepository->recentProviderTests(),
        ]);
    }
}
