'use client'

import { useEffect, useState } from 'react'
import AuthGuard from '@/components/AuthGuard'
import PHPLayout from '@/components/PHPLayout'
import { apiGet } from '@/lib/api'
import { User } from '@/types'

function escapeHtml(s: string): string {
  return s.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
}

interface PayrollData {
  userRole: string
  canEdit: boolean
}

interface PayrollResponse {
  success: boolean
  data: PayrollData
}

function PayrollContent({ user }: { user: User }) {
  const [_data, setData] = useState<PayrollData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    apiGet<PayrollResponse>('/api/pages/payroll-data.php')
      .then((res) => {
        if (res.success) {
          setData(res.data)
        } else {
          setError('データの取得に失敗しました')
        }
      })
      .catch(() => setError('APIエラーが発生しました'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return <div style={{ textAlign: 'center', padding: '3rem', color: '#6b7280' }}>読み込み中...</div>
  }

  if (error) {
    return <div className="alert alert-danger">{escapeHtml(error)}</div>
  }

  return (
    <div className="payroll-container">
      <style>{PAGE_CSS}</style>

      <div className="info-card">
        <div className="info-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <line x1="2" y1="10" x2="22" y2="10" />
          </svg>
        </div>
        <h2 className="info-title">給与仕訳</h2>
        <p className="info-message">
          この機能はExcelファイルを使用した複雑な処理が必要です。PHPページで操作してください。
        </p>
        <a href="/pages/payroll-journal.php" className="open-btn">
          給与仕訳ページを開く
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="5" y1="12" x2="19" y2="12" />
            <polyline points="12 5 19 12 12 19" />
          </svg>
        </a>
      </div>

      <div className="steps-card">
        <h3 className="steps-title">処理の流れ</h3>
        <ol className="steps-list">
          <li className="step-item">
            <span className="step-number">1</span>
            <div className="step-content">
              <strong>Excelアップロード</strong>
              <p>給与計算ソフトからエクスポートしたExcelファイルをアップロードします。</p>
            </div>
          </li>
          <li className="step-item">
            <span className="step-number">2</span>
            <div className="step-content">
              <strong>仕訳変換</strong>
              <p>アップロードされたデータを会計仕訳形式に自動変換します。</p>
            </div>
          </li>
          <li className="step-item">
            <span className="step-number">3</span>
            <div className="step-content">
              <strong>MF会計エクスポート</strong>
              <p>変換した仕訳データをMoneyForward会計にインポートできる形式で出力します。</p>
            </div>
          </li>
        </ol>
      </div>
    </div>
  )
}

export default function PayrollPage() {
  return (
    <AuthGuard>
      {(user) => (
        <PHPLayout user={user} title="給与仕訳">
          <PayrollContent user={user} />
        </PHPLayout>
      )}
    </AuthGuard>
  )
}

const PAGE_CSS = `
  .payroll-container { max-width: 720px; margin: 0 auto; padding: 2rem 1rem; display: flex; flex-direction: column; gap: 1.5rem; }
  .alert { padding: 0.875rem 1rem; border-radius: 6px; margin: 1rem 0; }
  .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
  .info-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
  .info-icon { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: #eff6ff; border-radius: 50%; color: #3b82f6; margin-bottom: 1.25rem; }
  .info-title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0 0 0.75rem; }
  .info-message { font-size: 0.95rem; color: #6b7280; line-height: 1.6; margin: 0 0 1.75rem; }
  .open-btn { display: inline-flex; align-items: center; gap: 0.5rem; background: #3b82f6; color: #fff; font-weight: 600; font-size: 0.95rem; padding: 0.7rem 1.5rem; border-radius: 8px; text-decoration: none; transition: background 0.15s; }
  .open-btn:hover { background: #2563eb; }
  .steps-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.75rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
  .steps-title { font-size: 1rem; font-weight: 700; color: #374151; margin: 0 0 1.25rem; }
  .steps-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem; }
  .step-item { display: flex; align-items: flex-start; gap: 1rem; }
  .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; min-width: 28px; background: #3b82f6; color: #fff; font-size: 0.8rem; font-weight: 700; border-radius: 50%; }
  .step-content { flex: 1; }
  .step-content strong { display: block; font-size: 0.9rem; color: #111827; margin-bottom: 0.2rem; }
  .step-content p { font-size: 0.85rem; color: #6b7280; margin: 0; line-height: 1.5; }
`