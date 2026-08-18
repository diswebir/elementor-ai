<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Agent\PlanService;
use AIEA\AI\ProviderException;
use InvalidArgumentException;
use RuntimeException;
use WP_REST_Request;

final class PlanController
{
    public function __construct(private readonly PlanService $plans, private readonly RestResponder $response)
    {
    }

    public function create(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $result = $this->plans->create(
                get_current_user_id(),
                absint($request->get_param('post_id')),
                sanitize_text_field((string) $request->get_param('session_id')),
                sanitize_text_field((string) $request->get_param('context_hash')),
                sanitize_textarea_field((string) $request->get_param('message')),
            );

            return $this->response->success($result, 201);
        } catch (ProviderException $exception) {
            $status = $exception->safeCode === 'provider_rate_limited' ? 429 : 422;

            return $this->response->error('aiea_plan_' . $exception->safeCode, $exception->getMessage(), $status);
        } catch (InvalidArgumentException) {
            return $this->response->error(
                'aiea_plan_invalid_response',
                __('مدل پاسخ برنامه‌ریزی را در قالب معتبر برنگرداند. دوباره تلاش کنید یا درخواست را ساده‌تر و دقیق‌تر بنویسید.', 'ai-elementor-ag'),
                422,
            );
        } catch (RuntimeException $exception) {
            return $this->response->error('aiea_plan_not_ready', $this->safeRuntimeMessage($exception), 409);
        } catch (\Throwable) {
            return $this->response->error('aiea_plan_failed', __('برنامه‌ریزی با خطای غیرمنتظره متوقف شد. Diagnostics provider را بررسی کنید.', 'ai-elementor-ag'), 409);
        }
    }

    public function approve(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $actionIds = $request->get_param('action_ids');
            $idempotency = (string) $request->get_header('Idempotency-Key');
            if ($idempotency === '') {
                $idempotency = sanitize_text_field((string) $request->get_param('idempotency_key'));
            }
            $result = $this->plans->approve(
                get_current_user_id(),
                absint($request->get_param('post_id')),
                sanitize_text_field((string) $request->get_param('plan_id')),
                sanitize_text_field((string) $request->get_param('context_hash')),
                is_array($actionIds) ? $actionIds : [],
                $idempotency,
            );

            return $this->response->success($result, 201);
        } catch (\Throwable) {
            return $this->response->error('aiea_approval_failed', __('The plan approval is no longer valid.', 'ai-elementor-ag'), 409);
        }
    }

    private function safeRuntimeMessage(RuntimeException $exception): string
    {
        return match (true) {
            str_starts_with($exception->getMessage(), 'No provider model') => __('مدل provider تنظیم نشده است. یک مدل معتبر را در تنظیمات افزونه ذخیره کنید.', 'ai-elementor-ag'),
            str_starts_with($exception->getMessage(), 'Page changed since') => __('ساختار صفحه تغییر کرده است. ابتدا «تحلیل محیط» را دوباره اجرا کنید.', 'ai-elementor-ag'),
            str_starts_with($exception->getMessage(), 'Conversation is not available') => __('نشست برنامه‌ریزی منقضی شده است. دوباره درخواست خود را ارسال کنید.', 'ai-elementor-ag'),
            default => __('برنامه‌ریزی آمادهٔ اجرا نیست. صفحه را Refresh کرده و دوباره «تحلیل محیط» را اجرا کنید.', 'ai-elementor-ag'),
        };
    }
}
