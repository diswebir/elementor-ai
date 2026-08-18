import React, { FormEvent, useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { AIEAApi, ApiError, type JobStatus } from '../shared/api'
import type { AIEAConfig, AgentMode, ExecutionMode, JobState, PlanAction, PlanView } from '../shared/types'
import './editor.css'

const editorRoot = document.getElementById('aiea-editor-root')

function resolveEditorConfig(): AIEAConfig | undefined {
  if (window.AIEA_CONFIG) return window.AIEA_CONFIG
  const serialized = editorRoot?.dataset.aieaEditorConfig
  if (!serialized) return undefined
  try {
    const parsed = JSON.parse(serialized) as Partial<AIEAConfig>
    const validScope = parsed.defaultScope === 'current' || parsed.defaultScope === 'site' || parsed.defaultScope === 'project'
    if (typeof parsed.restUrl !== 'string' || typeof parsed.nonce !== 'string' || typeof parsed.postId !== 'number' || typeof parsed.canUse !== 'boolean' || typeof parsed.canExecute !== 'boolean' || typeof parsed.providerConfigured !== 'boolean' || !validScope) return undefined
    return parsed as AIEAConfig
  } catch {
    return undefined
  }
}

const resolvedConfig = resolveEditorConfig()
const configurationMissing = !resolvedConfig
const queryPostId = Number(new URL(window.location.href).searchParams.get('post') ?? '0')
const config: AIEAConfig = resolvedConfig ?? {
  restUrl: `${window.location.origin}/wp-json/ai-elementor/v1/`,
  nonce: '',
  postId: Number.isFinite(queryPostId) ? queryPostId : 0,
  canUse: false,
  canExecute: false,
  providerConfigured: false,
  defaultScope: 'current'
}
const modeLabels: Record<AgentMode, string> = { ask: 'پرسش', plan: 'برنامه‌ریزی', build: 'ساخت' }
const stateLabels: Record<JobState, string> = { idle: 'آماده', analyzing: 'در حال تحلیل', planning: 'در حال برنامه‌ریزی', waiting_approval: 'منتظر تأیید', executing: 'در حال اجرا', validating: 'در حال اعتبارسنجی', repairing: 'در حال ترمیم', completed: 'کامل شد', failed: 'ناموفق', cancelled: 'لغو شد', needs_review: 'نیازمند بازبینی' }

function App() {
  const [isOpen, setIsOpen] = useState(false)
  const [mode, setMode] = useState<AgentMode>('plan')
  const [executionMode, setExecutionMode] = useState<ExecutionMode>('step')
  const [scope, setScope] = useState(config?.defaultScope ?? 'current')
  const [state, setState] = useState<JobState>('idle')
  const [message, setMessage] = useState('')
  const [answer, setAnswer] = useState<string | null>(null)
  const [status, setStatus] = useState('برای شروع، هدف صفحه را بنویسید.')
  const [error, setError] = useState<string | null>(null)
  const [session, setSession] = useState<{ id: string; contextHash: string } | null>(null)
  const [plan, setPlan] = useState<PlanView | null>(null)
  const [planId, setPlanId] = useState<string | null>(null)
  const [jobStatus, setJobStatus] = useState<JobStatus | null>(null)
  const [lastSnapshot, setLastSnapshot] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const api = useMemo(() => configurationMissing ? null : new AIEAApi(config), [])

  useEffect(() => {
    if (configurationMissing) {
      setStatus('دادهٔ اولیهٔ افزونه در Editor بارگیری نشد. صفحه را یک‌بار Refresh کنید؛ اگر ادامه داشت، افزونه را دوباره نصب یا assetهای build را بررسی کنید.')
      return
    }
    if (!config.providerConfigured) setStatus('Provider پیکربندی نشده است؛ تحلیل context فعال است، اما پاسخ و Plan نیازمند تنظیم Provider هستند.')
  }, [])

  const actions = plan?.actions ?? []
  const updateTasks = async (jobId: string): Promise<JobStatus> => {
    if (!api) throw new Error('API unavailable')
    const current = await api.jobStatus(jobId)
    setJobStatus(current)
    return current
  }

  async function analyze(): Promise<void> {
    if (!api) {
      setError('دادهٔ اولیه و nonce Editor در دسترس نیست. صفحه را Refresh کنید.')
      return
    }
    setBusy(true); setError(null); setState('analyzing')
    try {
      const context = await api.getContext(scope)
      setStatus(`Context ایمن آماده شد: ${context.hash.slice(0, 10)}. داده‌های حساس redacted شده‌اند.`)
      setState('idle')
    } catch (err) { setState('failed'); setError(err instanceof ApiError ? err.message : 'خواندن context ناموفق بود.') } finally { setBusy(false) }
  }

  async function submit(event: FormEvent): Promise<void> {
    event.preventDefault()
    if (!message.trim()) { setError('هدف یا درخواست خود را وارد کنید.'); return }
    if (!api || config?.canUse !== true) { setError('مجوز خواندن context این صفحه را ندارید.'); return }
    if ((mode === 'plan' || mode === 'build') && !config.providerConfigured) { setError('ابتدا Provider و کلید API را از تنظیمات افزونه پیکربندی کنید.'); return }
    setBusy(true); setError(null); setAnswer(null); setPlan(null); setPlanId(null); setJobStatus(null); setState(mode === 'ask' ? 'analyzing' : 'planning')
    try {
      const created = await api.createSession(scope)
      const active = { id: created.session.id, contextHash: created.session.context_hash }
      setSession(active)
      if (mode === 'ask') {
        setAnswer(await api.ask(active.id, active.contextHash, message.trim()))
        setStatus('پاسخ دریافت شد؛ هیچ تغییری در صفحه اعمال نشده است.')
        setState('idle')
        return
      }
      const generated = await api.createPlan(active.id, active.contextHash, message.trim())
      setPlanId(generated.id)
      setPlan({ goal: generated.plan.goal, assumptions: generated.plan.assumptions, acceptanceCriteria: generated.plan.acceptance_criteria, actions: generated.plan.actions })
      setStatus('Plan معتبر دریافت شد. هیچ عملیاتی قبل از تأیید شما اجرا نمی‌شود.')
      setState('waiting_approval')
    } catch (err) { setState('failed'); setError(err instanceof ApiError ? err.message : 'درخواست عامل متوقف شد.') } finally { setBusy(false) }
  }

  async function approve(): Promise<void> {
    if (!api || !planId || !session) return
    setBusy(true); setError(null)
    try {
      const result = await api.approvePlan(planId, session.contextHash, actions.map((action) => action.id))
      const next = await updateTasks(result.job.id)
      setStatus('Plan تأیید شد. Job ایجاد شده و آمادهٔ اجرای اولین گام است.')
      setState('waiting_approval')
      if (executionMode === 'auto') await runRemaining(next.job.id)
    } catch (err) { setState('needs_review'); setError(err instanceof ApiError ? err.message : 'تأیید Plan ممکن نشد.') } finally { setBusy(false) }
  }

  async function runNext(jobId?: string): Promise<void> {
    if (!api || config?.canExecute !== true) { setError('فقط کاربر مجاز می‌تواند تغییرات Draft را اجرا کند.'); return }
    const activeJob = jobId ?? jobStatus?.job.id
    if (!activeJob) return
    setBusy(true); setError(null); setState('executing')
    try {
      const result = await api.runNext(activeJob)
      if (result.receipt?.snapshot_id) setLastSnapshot(result.receipt.snapshot_id)
      await updateTasks(activeJob)
      setStatus(result.receipt?.summary ?? (result.completed ? 'همهٔ گام‌ها اجرا و برای اعتبارسنجی آماده‌اند.' : 'یک گام با receipt ثبت‌شده اجرا شد.'))
      setState(result.completed ? 'completed' : 'waiting_approval')
    } catch (err) { setState('needs_review'); setError(err instanceof ApiError ? err.message : 'اجرای گام با توقف ایمن روبه‌رو شد.') } finally { setBusy(false) }
  }

  async function runRemaining(jobId: string): Promise<void> {
    let keepRunning = true
    while (keepRunning) {
      await runNext(jobId)
      const current = await updateTasks(jobId)
      keepRunning = current.tasks.some((task) => task.state === 'pending')
      if (!keepRunning) setState('completed')
    }
  }

  async function rollback(): Promise<void> {
    if (!api || !jobStatus || !lastSnapshot) return
    setBusy(true); setError(null)
    try { await api.rollback(jobStatus.job.id, lastSnapshot); await updateTasks(jobStatus.job.id); setStatus('Snapshot با موفقیت بازیابی شد.'); setState('needs_review') } catch (err) { setError(err instanceof ApiError ? err.message : 'Rollback ناموفق بود.') } finally { setBusy(false) }
  }

  const taskState = (action: PlanAction): string => jobStatus?.tasks.find((task) => task.action_id === action.id)?.state ?? action.state ?? 'pending'
  return <aside className={`aiea-panel ${isOpen ? 'aiea-panel--open' : 'aiea-panel--closed'}`} dir="rtl" aria-label="عامل هوش مصنوعی المنتور">
    <button className="aiea-panel__launcher" onClick={() => setIsOpen((value) => !value)} aria-expanded={isOpen} aria-controls="aiea-panel-content">
      <span className="aiea-panel__launcher-badge" aria-hidden="true">AI</span>
      <span>{isOpen ? 'بستن پنل هوش مصنوعی' : 'ویرایش با هوش مصنوعی'}</span>
    </button>
    {isOpen && <div id="aiea-panel-content" className="aiea-panel__content">
      <header className="aiea-panel__header"><div><p className="aiea-eyebrow">ELEMENTOR AI AGENT</p><h2>عامل ساخت صفحه</h2></div><span className={`aiea-state aiea-state--${state}`} role="status">{stateLabels[state]}</span></header>
      <section className="aiea-context" aria-label="وضعیت محیط"><div><span>صفحه</span><strong>{config.postId ? `#${config.postId}` : 'نامشخص'}</strong></div><div><span>Provider</span><strong>{config.providerConfigured ? 'آماده' : 'نیازمند تنظیم'}</strong></div><div><span>Draft</span><strong>{config.canExecute ? 'قابل اجرا' : 'فقط خواندنی'}</strong></div></section>
      <div className="aiea-mode-tabs" role="tablist" aria-label="حالت عامل">{(['ask', 'plan', 'build'] as AgentMode[]).map((item) => <button key={item} role="tab" aria-selected={mode === item} className={mode === item ? 'is-active' : ''} onClick={() => setMode(item)}>{modeLabels[item]}</button>)}</div>
      <form className="aiea-chat" onSubmit={submit}><label htmlFor="aiea-request">درخواست شما</label><textarea id="aiea-request" value={message} onChange={(event) => setMessage(event.target.value)} placeholder="مثال: یک بخش معرفی راست‌چین با عنوان، توضیح و دکمه بساز." rows={5} disabled={busy} /><div className="aiea-chat__toolbar"><label>دامنهٔ داده<select value={scope} onChange={(event) => setScope(event.target.value as typeof scope)} disabled={busy}><option value="current">فقط صفحهٔ فعلی</option><option value="site">دادهٔ طراحی سایت</option><option value="project">دستورالعمل پروژه</option></select></label>{mode === 'build' && <label>اجرا<select value={executionMode} onChange={(event) => setExecutionMode(event.target.value as ExecutionMode)} disabled={busy}><option value="step">گام‌به‌گام</option><option value="auto">خودکار کنترل‌شده</option></select></label>}</div><div className="aiea-actions"><button type="button" className="aiea-button aiea-button--secondary" onClick={analyze} disabled={busy || configurationMissing}>تحلیل محیط</button><button type="submit" className="aiea-button" disabled={busy || !config.canUse}>{busy ? 'در حال پردازش…' : mode === 'ask' ? 'ارسال پرسش' : 'ساخت Plan'}</button></div></form>
      <section className="aiea-status-card" aria-live="polite"><strong>وضعیت</strong><p>{status}</p></section>
      {error && <section className="aiea-error" role="alert"><strong>نیاز به اقدام</strong><p>{error}</p></section>}
      {answer && <section className="aiea-status-card"><strong>پاسخ عامل</strong><p>{answer}</p></section>}
      {plan && <section className="aiea-plan" aria-label="برنامهٔ پیشنهادی"><div className="aiea-section-heading"><h3>Plan پیشنهادی</h3><span>{executionMode === 'step' ? 'گام‌به‌گام' : 'خودکار کنترل‌شده'}</span></div><p className="aiea-plan__goal">{plan.goal}</p><details open><summary>فرض‌ها و معیارهای پذیرش</summary><ul>{[...plan.assumptions, ...plan.acceptanceCriteria].map((item) => <li key={item}>{item}</li>)}</ul></details><ol className="aiea-tasks">{actions.map((action, index) => <li key={action.id}><span className="aiea-task-index">{index + 1}</span><div><strong>{action.tool}</strong><p>{action.description}</p><small>{taskState(action)}</small></div><span className={`aiea-risk aiea-risk--${action.risk_level}`}>{action.risk_level === 'low' ? 'کم‌ریسک' : action.risk_level === 'medium' ? 'میانه' : 'بالا'}</span></li>)}</ol><div className="aiea-actions">{!jobStatus && <button type="button" className="aiea-button" onClick={approve} disabled={busy || !config.canExecute}>تأیید و ساخت Job</button>}{jobStatus && <button type="button" className="aiea-button" onClick={() => runNext()} disabled={busy || !config.canExecute || jobStatus.job.state === 'completed'}>اجرای گام بعدی</button>}{lastSnapshot && <button type="button" className="aiea-button aiea-button--secondary" onClick={rollback} disabled={busy}>Rollback آخرین گام</button>}<button type="button" className="aiea-button aiea-button--secondary" onClick={() => { setPlan(null); setPlanId(null) }} disabled={busy}>رد Plan</button></div></section>}
      {session && <p className="aiea-session">Session: <bdi className="aiea-ltr">{session.id}</bdi></p>}
    </div>}
  </aside>
}

function mountPanel(): void {
  let rootNode = document.getElementById('aiea-editor-root')
  if (!rootNode) {
    rootNode = document.createElement('div')
    rootNode.id = 'aiea-editor-root'
    rootNode.dataset.aieaEditorRoot = 'fallback'
    document.body.append(rootNode)
  }
  createRoot(rootNode).render(<App />)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountPanel, { once: true })
} else {
  mountPanel()
}