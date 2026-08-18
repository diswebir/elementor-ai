import type { AIEAConfig, ApiSuccess, ContextSnapshot, PlanAction, Session } from './types'

export class ApiError extends Error {
  public constructor(public readonly code: string, message: string, public readonly status: number) { super(message) }
}

export interface GeneratedPlan { id: string; context_hash: string; plan: { goal: string; assumptions: string[]; acceptance_criteria: string[]; actions: PlanAction[] } }
export interface Job { id: string; state: string; page_id: number; document_hash: string }
export interface JobStatus { job: Job; tasks: Array<{ id: string; action_id: string; tool_name: string; state: PlanAction['state']; result_json?: string }> }

export class AIEAApi {
  public constructor(private readonly config: AIEAConfig) {}

  public async getContext(scope: string): Promise<ContextSnapshot> {
    return (await this.request<ContextSnapshot>(`context?${new URLSearchParams({ post_id: String(this.config.postId), scope })}`, { method: 'GET' })).data
  }

  public async createSession(scope: string): Promise<{ session: Session; context_summary: Record<string, unknown> }> {
    return (await this.request<{ session: Session; context_summary: Record<string, unknown> }>('sessions', { method: 'POST', body: JSON.stringify({ post_id: this.config.postId, scope }) })).data
  }

  public async ask(sessionId: string, contextHash: string, message: string): Promise<string> {
    return (await this.request<{ message: string }>('chat', { method: 'POST', body: JSON.stringify({ post_id: this.config.postId, session_id: sessionId, context_hash: contextHash, message }) })).data.message
  }

  public async createPlan(sessionId: string, contextHash: string, message: string): Promise<GeneratedPlan> {
    return (await this.request<GeneratedPlan>('plans', { method: 'POST', body: JSON.stringify({ post_id: this.config.postId, session_id: sessionId, context_hash: contextHash, message }) })).data
  }

  public async approvePlan(planId: string, contextHash: string, actionIds: string[]): Promise<{ job: Job }> {
    const idempotencyKey = crypto.randomUUID().replace(/-/g, '')
    return (await this.request<{ job: Job }>(`plans/${planId}/approve`, { method: 'POST', headers: { 'Idempotency-Key': idempotencyKey }, body: JSON.stringify({ post_id: this.config.postId, plan_id: planId, context_hash: contextHash, action_ids: actionIds }) })).data
  }

  public async jobStatus(jobId: string): Promise<JobStatus> {
    return (await this.request<JobStatus>(`jobs/${jobId}?${new URLSearchParams({ post_id: String(this.config.postId) })}`, { method: 'GET' })).data
  }

  public async runNext(jobId: string): Promise<{ job: Job; receipt?: { snapshot_id?: string; summary?: string }; completed: boolean }> {
    return (await this.request<{ job: Job; receipt?: { snapshot_id?: string; summary?: string }; completed: boolean }>(`jobs/${jobId}/next`, { method: 'POST', body: JSON.stringify({ post_id: this.config.postId }) })).data
  }

  public async rollback(jobId: string, snapshotId: string): Promise<void> {
    await this.request(`jobs/${jobId}/rollback`, { method: 'POST', body: JSON.stringify({ post_id: this.config.postId, snapshot_id: snapshotId }) })
  }

  private async request<T>(path: string, init: RequestInit): Promise<ApiSuccess<T>> {
    const response = await fetch(`${this.config.restUrl}${path}`, { ...init, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce, ...(init.headers ?? {}) } })
    const body = await response.json().catch(() => ({})) as { code?: string; message?: string } | ApiSuccess<T>
    if (!response.ok || !('success' in body) || body.success !== true) throw new ApiError('code' in body && body.code ? body.code : 'aiea_request_failed', 'message' in body && body.message ? body.message : 'درخواست با خطا مواجه شد.', response.status)
    return body
  }
}