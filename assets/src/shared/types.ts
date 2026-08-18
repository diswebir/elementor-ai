export type AgentMode = 'ask' | 'plan' | 'build'
export type ExecutionMode = 'step' | 'auto'
export type JobState = 'idle' | 'analyzing' | 'planning' | 'waiting_approval' | 'executing' | 'validating' | 'repairing' | 'completed' | 'failed' | 'cancelled' | 'needs_review'

export interface AIEAAdminConfig {
  restUrl: string
  nonce: string
  providerConfigured: boolean
}

export interface AIEAConfig {
  restUrl: string
  nonce: string
  postId: number
  canUse: boolean
  canExecute: boolean
  pageStatus: string
  allowAutoMode: boolean
  providerConfigured: boolean
  defaultScope: 'current' | 'site' | 'project'
}

export interface ApiSuccess<T> {
  success: true
  request_id: string
  data: T
}

export interface ContextSnapshot {
  data: Record<string, unknown>
  hash: string
}

export interface Session {
  id: string
  page_id: number
  context_scope: string
  context_hash: string
  status: string
}

export interface PlanAction {
  id: string
  tool: string
  description: string
  risk_level: 'low' | 'medium' | 'high'
  requires_approval: boolean
  state?: 'pending' | 'running' | 'completed' | 'failed' | 'skipped'
}

export interface PlanView {
  goal: string
  assumptions: string[]
  acceptanceCriteria: string[]
  actions: PlanAction[]
}

declare global {
  interface Window {
    AIEA_CONFIG?: AIEAConfig
    AIEA_ADMIN_CONFIG?: AIEAAdminConfig
  }
}