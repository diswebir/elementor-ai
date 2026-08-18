import React, { useEffect, useState } from 'react'
import { createRoot } from 'react-dom/client'
import type { AIEAAdminConfig, ApiSuccess } from '../shared/types'
import './admin.css'

type TestResult = {
  healthy: boolean
  latency_ms: number
  message: string
  assistant_message: string | null
  usage: Record<string, number>
  request_id: string | null
}

type AuditEntry = {
  id: string | number
  status: string
  result_summary: string | null
  duration_ms: string | number | null
  error_code: string | null
  created_at: string
}

const config: AIEAAdminConfig | undefined = window.AIEA_ADMIN_CONFIG

async function apiRequest<T>(path: string, method: 'GET' | 'POST', body?: Record<string, string>): Promise<{ data: T, status: number }> {
  if (!config) throw new Error('دادهٔ اولیهٔ صفحهٔ تنظیمات بارگیری نشده است.')
  const response = await fetch(`${config.restUrl}${path}`, {
    method,
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': config.nonce,
      ...(body ? { 'Content-Type': 'application/json' } : {})
    },
    ...(body ? { body: JSON.stringify(body) } : {})
  })
  const payload = await response.json() as ApiSuccess<T> | { message?: string }
  if (!('success' in payload) || payload.success !== true) {
    throw new Error(('message' in payload && payload.message) || 'درخواست تست ناموفق بود.')
  }
  return { data: payload.data, status: response.status }
}

function ProviderTestPanel() {
  const [message, setMessage] = useState('Reply exactly with: CONNECTION_OK')
  const [result, setResult] = useState<TestResult | null>(null)
  const [logs, setLogs] = useState<AuditEntry[]>([])
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const loadLogs = async (): Promise<void> => {
    try {
      const response = await apiRequest<{ entries: AuditEntry[] }>('providers/test/logs', 'GET')
      setLogs(response.data.entries)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'خواندن لاگ‌های تست ناموفق بود.')
    }
  }

  useEffect(() => { void loadLogs() }, [])

  const runTest = async (): Promise<void> => {
    setBusy(true)
    setError(null)
    setResult(null)
    try {
      const response = await apiRequest<TestResult>('providers/test', 'POST', { message })
      setResult(response.data)
      await loadLogs()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'آزمون ارسال پیام ناموفق بود.')
      await loadLogs()
    } finally {
      setBusy(false)
    }
  }

  if (!config) return <section className="aiea-admin-test" dir="rtl"><p className="aiea-admin-error">پنل تست بارگیری نشد. صفحه را Refresh کنید.</p></section>

  return <section className="aiea-admin-test" dir="rtl" aria-labelledby="aiea-test-title">
    <div className="aiea-admin-test__heading">
      <div><p className="aiea-admin-eyebrow">PROVIDER DIAGNOSTICS</p><h2 id="aiea-test-title">تست اتصال و پیام</h2></div>
      <span className={config.providerConfigured ? 'aiea-admin-chip aiea-admin-chip--ok' : 'aiea-admin-chip aiea-admin-chip--warn'}>{config.providerConfigured ? 'کلید API تنظیم شده' : 'کلید API تنظیم نشده'}</span>
    </div>
    <p className="aiea-admin-muted">پیام زیر به provider ذخیره‌شده ارسال می‌شود. پاسخ، زمان رفت‌وبرگشت و شناسهٔ درخواست نمایش داده می‌شود؛ کلید API و متن خام آن در لاگ ذخیره یا نمایش داده نمی‌شود.</p>
    <label className="aiea-admin-label" htmlFor="aiea-provider-test-message">پیام آزمایشی</label>
    <textarea id="aiea-provider-test-message" className="aiea-admin-textarea" value={message} onChange={(event) => setMessage(event.target.value)} maxLength={1000} rows={3} disabled={busy} />
    <div className="aiea-admin-actions">
      <button type="button" className="button button-primary" onClick={() => void runTest()} disabled={busy || !config.providerConfigured}>{busy ? 'در حال ارسال…' : 'ارسال و دریافت پیام آزمایشی'}</button>
      <button type="button" className="button" onClick={() => void loadLogs()} disabled={busy}>بازخوانی لاگ‌ها</button>
    </div>
    {!config.providerConfigured && <p className="aiea-admin-warning">ابتدا تنظیمات provider و کلید API را ذخیره کنید.</p>}
    {error && <div className="aiea-admin-error" role="alert">{error}</div>}
    {result && <div className={result.healthy ? 'aiea-admin-result aiea-admin-result--ok' : 'aiea-admin-result aiea-admin-result--fail'}>
      <strong>{result.healthy ? 'اتصال و دریافت پاسخ موفق بود' : 'آزمون اتصال ناموفق بود'}</strong>
      <p>{result.message}</p>
      <dl><div><dt>زمان پاسخ</dt><dd>{result.latency_ms} ms</dd></div><div><dt>شناسهٔ درخواست</dt><dd>{result.request_id ?? '—'}</dd></div></dl>
      {result.assistant_message && <div className="aiea-admin-answer"><span>پاسخ provider</span><pre>{result.assistant_message}</pre></div>}
    </div>}
    <div className="aiea-admin-logs"><div className="aiea-admin-logs__heading"><h3>لاگ تست‌های اخیر</h3><span>{logs.length} مورد</span></div>
      {logs.length === 0 ? <p className="aiea-admin-muted">هنوز تستی ثبت نشده است.</p> : <table><thead><tr><th>زمان</th><th>وضعیت</th><th>خلاصه</th><th>زمان پاسخ</th></tr></thead><tbody>{logs.map((entry) => <tr key={entry.id}><td>{entry.created_at}</td><td><span className={entry.status === 'completed' ? 'aiea-admin-chip aiea-admin-chip--ok' : 'aiea-admin-chip aiea-admin-chip--fail'}>{entry.status === 'completed' ? 'موفق' : 'ناموفق'}</span></td><td>{entry.result_summary ?? '—'}{entry.error_code ? ` (${entry.error_code})` : ''}</td><td>{entry.duration_ms ? `${entry.duration_ms} ms` : '—'}</td></tr>)}</tbody></table>}
    </div>
  </section>
}

const rootNode = document.getElementById('aiea-admin-app')
if (rootNode) createRoot(rootNode).render(<ProviderTestPanel />)
