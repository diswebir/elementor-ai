<?php

declare(strict_types=1);

namespace AIEA\Agent;

use AIEA\Admin\Settings;
use AIEA\AI\AIManager;
use AIEA\AI\AIRequestOptions;
use AIEA\Database\ConversationRepository;
use AIEA\Database\PlanRepository;
use AIEA\Elementor\DocumentRepository;
use AIEA\Jobs\JobRepository;
use RuntimeException;

final class PlanService
{
    public function __construct(
        private readonly AIManager $ai,
        private readonly Settings $settings,
        private readonly ContextService $context,
        private readonly ConversationRepository $conversations,
        private readonly PlanRepository $plans,
        private readonly JobRepository $jobs,
        private readonly DocumentRepository $documents,
        private readonly PromptBuilder $prompts,
        private readonly PlanJsonDecoder $decoder,
        private readonly PlanSchemaValidator $validator,
    ) {
    }

    /** @return array<string, mixed> */
    public function create(int $userId, int $postId, string $sessionId, string $contextHash, string $request): array
    {
        $conversation = $this->conversations->findOwned($sessionId, $userId);
        if ($conversation === null || absint($conversation['page_id']) !== $postId) {
            throw new RuntimeException('Conversation is not available for this page.');
        }
        $context = $this->context->collect($postId, (string) $conversation['context_scope']);
        if (!hash_equals((string) $conversation['context_hash'], $contextHash) || !hash_equals($context['hash'], $contextHash)) {
            throw new RuntimeException('Page changed since context was collected. Create a fresh plan.');
        }
        $model = trim((string) $conversation['model_id']);
        if ($model === '') {
            $model = trim((string) ($this->settings->all()['model'] ?? ''));
        }
        if ($model === '') {
            throw new RuntimeException('No provider model is configured.');
        }
        $response = $this->ai->request(
            $this->prompts->planMessages($request, $context['data']),
            new AIRequestOptions($model, 0.2, 3000),
        );
        $plan = $this->validator->validate($this->decoder->decode($response->content));
        $planId = $this->plans->create($sessionId, $plan);
        return ['id' => $planId, 'plan' => $plan, 'context_hash' => $contextHash];
    }

    /** @param list<string> $actionIds
     *  @return array<string, mixed>
     */
    public function approve(int $userId, int $postId, string $planId, string $contextHash, array $actionIds, string $idempotencyKey): array
    {
        $row = $this->plans->findForUser($planId, $userId);
        if ($row === null || absint($row['page_id']) !== $postId) {
            throw new RuntimeException('Plan is not available for this page.');
        }
        $freshContext = $this->context->collect($postId, (string) $row['context_scope']);
        if (!hash_equals((string) $row['context_hash'], $contextHash) || !hash_equals($freshContext['hash'], $contextHash)) {
            throw new RuntimeException('Page context is stale. Rebuild the plan before approval.');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/', $idempotencyKey)) {
            throw new RuntimeException('Approval requires a valid idempotency key.');
        }
        $plan = json_decode((string) $row['plan_json'], true);
        $validated = $this->validator->validate($plan);
        if ($actionIds === []) {
            throw new RuntimeException('At least one action must be approved.');
        }
        $known = array_column($validated['actions'], 'id');
        foreach ($actionIds as $actionId) {
            if (!is_string($actionId) || !in_array($actionId, $known, true)) {
                throw new RuntimeException('Approval includes an unknown action.');
            }
        }
        $approvedActions = array_values(array_filter(
            $validated['actions'],
            static fn (array $action): bool => in_array($action['id'], $actionIds, true),
        ));
        $approvedPlan = [...$validated, 'actions' => $approvedActions];
        $elements = $this->documents->getElements($postId);
        $this->plans->approve($planId, $actionIds);
        $job = $this->jobs->create(
            $planId,
            $postId,
            $userId,
            $this->documents->hash($elements),
            $idempotencyKey,
            $approvedPlan,
        );
        return [
            'plan_id' => $planId,
            'approval_state' => 'approved',
            'approved_action_ids' => $actionIds,
            'job' => $job,
        ];
    }
}
