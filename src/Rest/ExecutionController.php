<?php

declare(strict_types=1);

namespace AIEA\Rest;

use AIEA\Jobs\DocumentTransaction;
use AIEA\Jobs\JobRepository;
use AIEA\Jobs\JobRunner;
use WP_REST_Request;

final class ExecutionController
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobRunner $runner,
        private readonly DocumentTransaction $transactions,
        private readonly RestResponder $response,
    ) {
    }

    public function status(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $job = $this->jobs->findOwned(sanitize_text_field((string) $request->get_param('job_id')), get_current_user_id());
        if ($job === null || absint($job['page_id']) !== absint($request->get_param('post_id'))) {
            return $this->response->error('aiea_job_not_found', __('Job was not found.', 'ai-elementor-agent'), 404);
        }
        return $this->response->success(['job' => $job, 'tasks' => $this->jobs->tasks((string) $job['id'])]);
    }

    public function next(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $result = $this->runner->runNext(sanitize_text_field((string) $request->get_param('job_id')), get_current_user_id());
            return $this->response->success($result);
        } catch (\Throwable $exception) {
            return $this->response->error('aiea_execution_stopped', __('Action stopped safely and needs review.', 'ai-elementor-agent'), 409);
        }
    }

    public function rollback(WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $result = $this->transactions->rollback(absint($request->get_param('post_id')), sanitize_text_field((string) $request->get_param('snapshot_id')));
            return $this->response->success($result);
        } catch (\Throwable $exception) {
            return $this->response->error('aiea_rollback_failed', __('The snapshot could not be restored safely.', 'ai-elementor-agent'), 409);
        }
    }
}
