import React from 'react'
import { createRoot } from 'react-dom/client'

const rootNode = document.getElementById('aiea-admin-app')
if (rootNode) {
  createRoot(rootNode).render(<p dir="rtl">تنظیمات پیشرفته از طریق فرم امن WordPress بارگذاری می‌شود.</p>)
}