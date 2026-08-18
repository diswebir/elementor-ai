<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Agent\ConversationService;
use AIEA\AI\ProviderException;
use RuntimeException;
use WP_REST_Request;

final class ChatController
{
    public function __construct(private readonly ConversationService $chat, private readonly RestResponder $response)
    {
    }

    public function send(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $content = $this->chat->ask(
                get_current_user_id(),
                absint($request->get_param('post_id')),
                sanitize_text_field((string) $request->get_param('session_id')),
                sanitize_text_field((string) $request->get_param('context_hash')),
                sanitize_textarea_field((string) $request->get_param('message')),
            );

            return $this->response->success(['message' => $content]);
        } catch (ProviderException $exception) {
            $status = $exception->safeCode === 'provider_rate_limited' ? 429 : 422;

            return $this->response->error(
                'aiea_chat_' . $exception->safeCode,
                $exception->getMessage(),
                $status,
            );
        } catch (RuntimeException $exception) {
            return $this->response->error('aiea_chat_not_ready', $this->safeRuntimeMessage($exception), 409);
        } catch (\Throwable) {
            return $this->response->error('aiea_chat_failed', __('The assistant could not answer safely. Check the provider diagnostic panel.', 'ai-elementor-ag'), 409);
        }
    }

    private function safeRuntimeMessage(RuntimeException $exception): string
    {
        return match (true) {
            str_starts_with($exception->getMessage(), 'No provider model') => __('مدل provider تنظیم نشده است. یک مدل معتبر را در تنظیمات افزونه ذخیره کنید.', 'ai-elementor-ag'),
            str_starts_with($exception->getMessage(), 'Page context changed') => __('ساختار صفحه تغییر کرده است. ابتدا «تحلیل محیط» را دوباره اجرا کنید.', 'ai-elementor-ag'),
            str_starts_with($exception->getMessage(), 'Conversation is unavailable') => __('نشست گفت‌وگو منقضی شده است. دوباره پرسش خود را ارسال کنید.', 'ai-elementor-ag'),
            default => __('درخواست آمادهٔ اجرا نیست. صفحه را Refresh کرده و دوباره «تحلیل محیط» را اجرا کنید.', 'ai-elementor-ag'),
        };
    }
}
