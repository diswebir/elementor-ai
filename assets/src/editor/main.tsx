import { useState, type FormEvent } from 'react'
import { createRoot } from 'react-dom/client'
import type { AIEAConfig } from '../shared/types'
import './editor.css'

const editorRoot = document.getElementById('aiea-editor-root')

type ElementorCommandApi = {
  run: (command: string, args?: Record<string, unknown>) => unknown
}

type ElementorEditorApi = {
  getPreviewContainer?: () => unknown
}

declare global {
  interface Window {
    $e?: ElementorCommandApi
    elementor?: ElementorEditorApi
  }
}

function resolveEditorConfig(): AIEAConfig | undefined {
  if (window.AIEA_CONFIG) return window.AIEA_CONFIG
  const serialized = editorRoot?.dataset.aieaEditorConfig
  if (!serialized) return undefined

  try {
    const parsed = JSON.parse(serialized) as Partial<AIEAConfig>
    const validScope = parsed.defaultScope === 'current' || parsed.defaultScope === 'site' || parsed.defaultScope === 'project'
    if (typeof parsed.restUrl !== 'string' || typeof parsed.nonce !== 'string' || typeof parsed.postId !== 'number' || typeof parsed.canUse !== 'boolean' || typeof parsed.canExecute !== 'boolean' || typeof parsed.pageStatus !== 'string' || typeof parsed.allowAutoMode !== 'boolean' || typeof parsed.providerConfigured !== 'boolean' || !validScope) return undefined
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
  pageStatus: '',
  allowAutoMode: false,
  providerConfigured: false,
  defaultScope: 'current',
}

function normalizeTitle(value: string): string {
  return value.replace(/\s+/g, ' ').trim().slice(0, 180)
}

function animateDrop(title: string): Promise<void> {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return Promise.resolve()

  const iframe = document.querySelector<HTMLElement>('#elementor-preview-iframe')
  const target = iframe?.getBoundingClientRect()
  const startX = Math.max(16, window.innerWidth - 238)
  const startY = Math.max(92, window.innerHeight - 124)
  const endX = target ? target.left + (target.width / 2) - 72 : window.innerWidth / 2 - 72
  const endY = target ? target.top + Math.min(128, target.height / 3) : window.innerHeight / 3
  const ghost = document.createElement('div')

  ghost.className = 'aiea-drop-ghost'
  ghost.setAttribute('aria-hidden', 'true')
  ghost.textContent = title
  ghost.style.left = `${startX}px`
  ghost.style.top = `${startY}px`
  document.body.append(ghost)

  return new Promise((resolve) => {
    requestAnimationFrame(() => {
      ghost.style.transform = `translate(${endX - startX}px, ${endY - startY}px) scale(.96)`
      ghost.style.opacity = '0'
    })
    window.setTimeout(() => {
      ghost.remove()
      resolve()
    }, 420)
  })
}

async function createHeadingInEditor(title: string): Promise<void> {
  const commandApi = window.$e
  const previewContainer = window.elementor?.getPreviewContainer?.()
  if (!commandApi?.run || !previewContainer) {
    throw new Error('رابط داخلی Elementor آماده نیست. Editor را یک‌بار Refresh کنید و دوباره تلاش کنید.')
  }

  const headingBlock = {
    elType: 'container',
    isInner: false,
    settings: {},
    elements: [{
      elType: 'widget',
      widgetType: 'heading',
      settings: {
        title,
        header_size: 'h2',
      },
      elements: [],
    }],
  }

  const dropAnimation = animateDrop(title)
  await Promise.resolve(commandApi.run('document/elements/create', {
    container: previewContainer,
    model: headingBlock,
  }))
  await dropAnimation
}

function App() {
  const [isOpen, setIsOpen] = useState(false)
  const [title, setTitle] = useState('عنوان جدید')
  const [busy, setBusy] = useState(false)
  const [status, setStatus] = useState('فقط یک عملیات فعال است: افزودن یک عنوان به برگه.')
  const [error, setError] = useState<string | null>(null)

  const canInsert = !configurationMissing && config.canExecute && config.pageStatus === 'draft'

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    const normalizedTitle = normalizeTitle(title)
    if (!normalizedTitle) {
      setError('متن عنوان را وارد کنید.')
      return
    }
    if (!canInsert) {
      setError('افزودن محتوا فقط برای برگهٔ Draft و کاربر دارای مجوز اجرا فعال است.')
      return
    }

    setBusy(true)
    setError(null)
    setStatus('در حال افزودن عنوان به بوم Elementor…')
    try {
      await createHeadingInEditor(normalizedTitle)
      setStatus('عنوان به بوم Elementor اضافه شد. برای ثبت دائمی تغییر، دکمهٔ «به‌روزرسانی» Elementor را بزنید.')
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'افزودن عنوان متوقف شد. Editor را Refresh کنید و دوباره تلاش کنید.')
      setStatus('تغییری ثبت نشد.')
    } finally {
      setBusy(false)
    }
  }

  return <aside className={`aiea-panel ${isOpen ? 'aiea-panel--open' : 'aiea-panel--closed'}`} dir="rtl" aria-label="افزودن سریع Elementor">
    <button className="aiea-panel__launcher" onClick={() => setIsOpen((value) => !value)} aria-expanded={isOpen} aria-controls="aiea-editor-panel-content">
      <span className="aiea-panel__launcher-badge" aria-hidden="true">AI</span>
      <span>{isOpen ? 'بستن پنل' : 'افزودن با هوش مصنوعی'}</span>
    </button>
    {isOpen && <div id="aiea-editor-panel-content" className="aiea-panel__content">
      <header className="aiea-panel__header">
        <div><p className="aiea-eyebrow">SIMPLIFIED EDITOR MODE</p><h2>افزودن عنوان</h2></div>
        <span className={`aiea-state aiea-state--${busy ? 'executing' : 'idle'}`} role="status">{busy ? 'در حال افزودن' : 'آماده'}</span>
      </header>
      <section className="aiea-context" aria-label="وضعیت عملیات">
        <div><span>برگه</span><strong>{config.postId ? `#${config.postId}` : 'نامشخص'}</strong></div>
        <div><span>وضعیت</span><strong><bdi dir="ltr">{config.pageStatus || 'unknown'}</bdi></strong></div>
        <div><span>اجرا</span><strong>{canInsert ? 'فعال' : 'غیرفعال'}</strong></div>
      </section>
      <section className="aiea-simple-intro"><strong>حالت ساده فعال است</strong><p>گفت‌وگو، تحلیل، Plan، Job، اجرای خودکار و Rollback موقتاً غیرفعال‌اند. این پنل فقط یک عنوان را به شکل یک block آماده به Editor اضافه می‌کند.</p></section>
      {config.pageStatus !== 'draft' && <section className="aiea-error" role="status"><strong>نیاز به Draft</strong><p>برای افزودن محتوا، وضعیت برگه را به Draft تغییر دهید و Editor را Refresh کنید.</p></section>}
      {configurationMissing && <section className="aiea-error" role="alert"><strong>پیکربندی در دسترس نیست</strong><p>اطلاعات اولیهٔ افزونه بارگیری نشد. Editor را یک‌بار Refresh کنید.</p></section>}
      <form className="aiea-chat" onSubmit={(event) => void handleSubmit(event)}>
        <label htmlFor="aiea-heading-title">متن عنوان</label>
        <textarea id="aiea-heading-title" value={title} onChange={(event) => setTitle(event.target.value)} rows={3} maxLength={180} disabled={busy} placeholder="مثال: خدمات ما" />
        <div className="aiea-actions"><button type="submit" className="aiea-button" disabled={busy || !canInsert}>{busy ? 'در حال افزودن…' : 'درگ و افزودن عنوان'}</button></div>
      </form>
      <section className="aiea-status-card" aria-live="polite"><strong>وضعیت</strong><p>{status}</p></section>
      {error && <section className="aiea-error" role="alert"><strong>نیاز به اقدام</strong><p>{error}</p></section>}
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
