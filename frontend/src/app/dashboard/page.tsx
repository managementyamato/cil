'use client'

import { useEffect, useState } from 'react'
import AuthGuard from '@/components/AuthGuard'
import PHPLayout from '@/components/PHPLayout'
import { User, DashboardData, CalendarEvent } from '@/types'

// ---- ユーティリティ ----
function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function formatRelativeTime(dateStr: string): string {
  if (!dateStr) return ''
  const diff = Math.floor((Date.now() - new Date(dateStr.replace(/-/g, '/')).getTime()) / 1000)
  if (diff < 3600) return `${Math.floor(diff / 60)}分前`
  if (diff < 86400) return `${Math.floor(diff / 3600)}時間前`
  return `${Math.floor(diff / 86400)}日前`
}

function todayLabel(): string {
  const d = new Date()
  const weekdays = ['日', '月', '火', '水', '木', '金', '土']
  return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${weekdays[d.getDay()]}）`
}

// ---- ダッシュボード本体 ----
function DashboardContent({ user }: { user: User }) {
  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [calendarEvents, setCalendarEvents] = useState<CalendarEvent[]>([])
  const [calendarLoading, setCalendarLoading] = useState(false)
  const [calendarError, setCalendarError] = useState(false)
  const [alcoholSyncing, setAlcoholSyncing] = useState(false)
  const [alcoholStatus, setAlcoholStatus] = useState('')

  useEffect(() => {
    fetch('/api/dashboard.php', { credentials: 'include' })
      .then(r => r.json())
      .then((json: DashboardData) => {
        setData(json)
        setLoading(false)
        if (json.calendarConfigured) {
          fetchCalendarEvents()
        }
      })
      .catch(() => setLoading(false))
  }, [])

  function fetchCalendarEvents() {
    setCalendarLoading(true)
    setCalendarError(false)
    const controller = new AbortController()
    const tid = setTimeout(() => controller.abort(), 8000)
    fetch('/api/calendar-events.php', { credentials: 'include', signal: controller.signal })
      .then(r => r.json())
      .then((json: { events?: CalendarEvent[]; error?: string }) => {
        clearTimeout(tid)
        if (json.error) { setCalendarError(true) } else { setCalendarEvents(json.events ?? []) }
        setCalendarLoading(false)
      })
      .catch((err) => {
        clearTimeout(tid)
        setCalendarError(true)
        setCalendarLoading(false)
        void err
      })
  }

  async function syncAlcohol() {
    setAlcoholSyncing(true)
    setAlcoholStatus('処理中...')
    const today = new Date().toISOString().split('T')[0]
    try {
      // Get CSRF token first
      const csrfRes = await fetch('/api/csrf-token.php', { credentials: 'include' })
      const csrfJson = await csrfRes.json()
      const csrfToken: string = csrfJson.token ?? ''

      const form = new FormData()
      form.append('action', 'sync_images')
      form.append('date', today)
      const res = await fetch('/api/alcohol-chat-sync.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-CSRF-Token': csrfToken },
        body: form,
      })
      const json = await res.json()
      if (json.success) {
        setAlcoholStatus(`✓ ${json.imported ?? 0}件取得`)
      } else {
        setAlcoholStatus(`✗ ${json.error ?? 'エラー'}`)
      }
    } catch {
      setAlcoholStatus('✗ 通信エラー')
    }
    setAlcoholSyncing(false)
    setTimeout(() => setAlcoholStatus(''), 5000)
  }

  if (loading) {
    return (
      <PHPLayout user={user} title="ダッシュボード">
        <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--gray-500)' }}>
          読み込み中...
        </div>
      </PHPLayout>
    )
  }

  if (!data) {
    return (
      <PHPLayout user={user} title="ダッシュボード">
        <div className="alert alert-danger">データの取得に失敗しました。</div>
      </PHPLayout>
    )
  }

  const { troubles, projects, sales, recentActivities, permissions, alcoholCheck } = data
  const ring = 377
  const ringOffset = ring * (1 - troubles.completionRate / 100)
  const statusColors: Record<string, string> = {
    '案件発生': '#999', '成約': '#666', '製品手配中': '#777',
    '設置予定': '#555', '設置済': '#444', '完了': '#333',
  }
  const statusList = ['案件発生', '成約', '製品手配中', '設置予定', '設置済', '完了']

  return (
    <PHPLayout user={user} title="ダッシュボード">
      <style>{DASHBOARD_CSS}</style>

      <div className="dashboard-container">
        <div className="dashboard-header">
          <h2>ダッシュボード</h2>
          <span className="dashboard-date">{todayLabel()}</span>
        </div>

        {/* ---- KPIカード ---- */}
        <div className="kpi-row">
          {/* 売上 */}
          {permissions.canViewFinance && sales && (
            <a href="/pages/finance.php" className="kpi-card primary">
              <div className="kpi-header">
                <div className="kpi-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                  </svg>
                </div>
              </div>
              <div className="kpi-label">今月売上</div>
              <div className="kpi-value">¥{sales.currentMonthWan}<span className="unit">万</span></div>
              {sales.change !== 0 && (
                <div className={`kpi-change ${sales.change >= 0 ? 'up' : 'down'}`}>
                  {sales.change >= 0 ? '↑' : '↓'} {Math.abs(sales.change)}% 前月比
                </div>
              )}
            </a>
          )}

          {/* 未対応トラブル */}
          {permissions.canViewTroubles && (
            <a href="/pages/troubles.php" className={`kpi-card ${troubles.pending > 0 ? 'danger' : 'success'}`}>
              <div className="kpi-header">
                <div className="kpi-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                  </svg>
                </div>
              </div>
              <div className="kpi-label">未対応トラブル</div>
              <div className="kpi-value">{troubles.pending}<span className="unit">件</span></div>
              {troubles.overdueCount > 0 && (
                <div className="kpi-change down">{troubles.overdueCount}件 期限超過</div>
              )}
            </a>
          )}

          {/* 今月トラブル */}
          {permissions.canViewTroubles && (
            <a href="/pages/troubles.php" className="kpi-card warning">
              <div className="kpi-header">
                <div className="kpi-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" />
                  </svg>
                </div>
              </div>
              <div className="kpi-label">今月トラブル</div>
              <div className="kpi-value">{troubles.currentMonthCount}<span className="unit">件</span></div>
              {troubles.monthChange !== 0 && (
                <div className={`kpi-change ${troubles.monthChange > 0 ? 'up' : 'down'}`}>
                  {troubles.monthChange > 0 ? '+' : ''}{troubles.monthChange}% 前月比
                </div>
              )}
            </a>
          )}

          {/* 対応完了率 */}
          {permissions.canViewTroubles && (
            <div className="kpi-card success">
              <div className="kpi-header">
                <div className="kpi-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" />
                  </svg>
                </div>
              </div>
              <div className="kpi-label">対応完了率</div>
              <div className="kpi-value">{troubles.completionRate}<span className="unit">%</span></div>
            </div>
          )}

          {/* アルコールチェック */}
          {alcoholCheck.configured && permissions.canViewPhotoAttendance && (
            <div className="kpi-card purple">
              <div className="kpi-header">
                <div className="kpi-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M8 2h8l4 10H4L8 2z" /><path d="M12 12v10" /><path d="M8 22h8" />
                  </svg>
                </div>
              </div>
              <div className="kpi-label">アルコールチェック</div>
              <div className="alcohol-sync-area">
                <button
                  className="alcohol-sync-btn"
                  onClick={syncAlcohol}
                  disabled={alcoholSyncing}
                >
                  {alcoholSyncing ? '同期中...' : '同期する'}
                </button>
                {alcoholStatus && (
                  <div className="alcohol-sync-status">{alcoholStatus}</div>
                )}
                {!alcoholStatus && alcoholCheck.missingCount > 0 && (
                  <div className="alcohol-sync-status" style={{ color: 'var(--dash-danger)' }}>
                    {alcoholCheck.missingCount}名未提出
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* ---- メインコンテンツグリッド ---- */}
        <div className="dashboard-grid">
          {/* 左カラム */}
          <div className="left-column">

            {/* トラブル対応状況 */}
            {permissions.canViewTroubles && (
              <div className="widget">
                <div className="widget-header">
                  <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                    </svg>
                    トラブル対応状況
                  </h3>
                </div>
                <div className="progress-ring-container">
                  <div className="progress-ring">
                    <svg width="150" height="150" viewBox="0 0 150 150">
                      <circle className="progress-ring-bg" cx="75" cy="75" r="60" />
                      <circle
                        className="progress-ring-fill"
                        cx="75" cy="75" r="60"
                        strokeDasharray={ring}
                        strokeDashoffset={ringOffset}
                      />
                    </svg>
                    <div className="progress-ring-text">
                      <div className="progress-ring-value">{troubles.completionRate}%</div>
                      <div className="progress-ring-label">完了率</div>
                    </div>
                  </div>
                </div>
                <div className="progress-bars">
                  {[
                    { label: '未対応', count: troubles.pending, color: 'red' },
                    { label: '対応中', count: troubles.inProgress, color: 'yellow' },
                    { label: '完了', count: troubles.completed, color: 'green' },
                  ].map(({ label, count, color }) => (
                    <div key={label} className="progress-item">
                      <div className="progress-label">
                        <span>{label}</span>
                        <span>{count}件</span>
                      </div>
                      <div className="progress-bar">
                        <div
                          className={`progress-fill ${color}`}
                          style={{ width: `${troubles.total > 0 ? (count / troubles.total * 100) : 0}%` }}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* 最近のアクティビティ */}
            <div className="widget">
              <div className="widget-header">
                <h3>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                  </svg>
                  最近のアクティビティ
                </h3>
              </div>
              <div className="widget-body">
                {recentActivities.length === 0 ? (
                  <div className="empty-state">
                    <p>最近のアクティビティはありません</p>
                  </div>
                ) : (
                  <ul className="activity-list">
                    {recentActivities.map((act, i) => (
                      <li key={i} className="activity-item">
                        <div className="activity-icon">
                          {act.type === 'trouble' ? (act.action === '完了' ? '✓' : '📝') : '📁'}
                        </div>
                        <div className="activity-content">
                          <div className="activity-text">
                            <strong>{escapeHtml(act.user)}</strong>が{escapeHtml(act.title)}を{escapeHtml(act.action)}
                          </div>
                          <div className="activity-time">{formatRelativeTime(act.date)}</div>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>

          {/* 右カラム */}
          <div className="right-column">

            {/* 今日の予定 (Google Calendar) */}
            {data.calendarConfigured && (
              <div className="widget">
                <div className="widget-header">
                  <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                      <line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    今日の予定
                  </h3>
                  <span className="today-date">
                    {(() => {
                      const d = new Date()
                      const wk = ['日', '月', '火', '水', '木', '金', '土']
                      return `${d.getMonth() + 1}月${d.getDate()}日（${wk[d.getDay()]}）`
                    })()}
                  </span>
                </div>
                <div className="widget-body">
                  <div className="event-list">
                    {calendarLoading && <div className="no-events">読み込み中...</div>}
                    {calendarError && <div className="no-events">カレンダー取得エラー</div>}
                    {!calendarLoading && !calendarError && calendarEvents.length === 0 && (
                      <div className="no-events">今日の予定はありません</div>
                    )}
                    {calendarEvents.map((ev, i) => {
                      let timeStr = ''
                      if (ev.isAllDay) {
                        timeStr = '終日'
                      } else if (ev.start) {
                        const s = new Date(ev.start)
                        timeStr = `${s.getHours().toString().padStart(2, '0')}:${s.getMinutes().toString().padStart(2, '0')}`
                        if (ev.end) {
                          const e = new Date(ev.end)
                          timeStr += `〜${e.getHours().toString().padStart(2, '0')}:${e.getMinutes().toString().padStart(2, '0')}`
                        }
                      }
                      return (
                        <div key={i} className="event-item">
                          <span
                            className="event-calendar-color"
                            style={{ background: ev.calendarColor ?? '#4285f4' }}
                          />
                          <div className={`event-time${ev.isAllDay ? ' all-day' : ''}`}>{timeStr}</div>
                          <div className="event-title">{ev.title ?? '(タイトルなし)'}</div>
                        </div>
                      )
                    })}
                  </div>
                </div>
              </div>
            )}

            {/* クイックアクション */}
            <div className="widget">
              <div className="widget-header">
                <h3>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                  </svg>
                  クイックアクション
                </h3>
              </div>
              <div className="quick-actions">
                {permissions.canViewTroubleForm && (
                  <a href="/pages/trouble-form.php" className="quick-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                      <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    トラブル登録
                  </a>
                )}
                {permissions.canViewMaster && (
                  <a href="/pages/master.php" className="quick-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                      <polyline points="14 2 14 8 20 8" />
                      <line x1="12" y1="18" x2="12" y2="12" /><line x1="9" y1="15" x2="15" y2="15" />
                    </svg>
                    案件一覧
                  </a>
                )}
                {permissions.canViewTroubles && (
                  <a href="/pages/troubles.php?status=未対応" className="quick-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10" />
                      <line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    未対応一覧
                  </a>
                )}
                {permissions.canViewPhotoAttendance && (
                  <a href="/pages/photo-attendance.php" className="quick-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                      <circle cx="8.5" cy="8.5" r="1.5" /><polyline points="21 15 16 10 5 21" />
                    </svg>
                    アルコール確認
                  </a>
                )}
              </div>
            </div>

            {/* 案件ステータス */}
            {permissions.canViewMaster && (
              <div className="widget">
                <div className="widget-header">
                  <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                    </svg>
                    案件ステータス
                  </h3>
                </div>
                <div className="status-grid">
                  {statusList.map(s => (
                    <div key={s} className="status-item">
                      <div className="status-count" style={{ color: statusColors[s] }}>
                        {projects.byStatus[s] ?? 0}
                      </div>
                      <div className="status-label">{s}</div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </PHPLayout>
  )
}

export default function DashboardPage() {
  return (
    <AuthGuard>
      {(user) => <DashboardContent user={user} />}
    </AuthGuard>
  )
}

// ---- ダッシュボード専用CSS ----
const DASHBOARD_CSS = `
:root {
  --dash-bg: #f8f9fa;
  --dash-card: #ffffff;
  --dash-border: #e0e0e0;
  --dash-text: #333333;
  --dash-text-light: #666666;
  --dash-text-muted: #999999;
  --dash-primary: #444444;
  --dash-primary-light: #f5f5f5;
  --dash-success: #555555;
  --dash-success-light: #f0f0f0;
  --dash-warning: #666666;
  --dash-warning-light: #f5f5f5;
  --dash-danger: #c62828;
  --dash-danger-light: #ffebee;
  --dash-purple: #555555;
  --dash-purple-light: #f5f5f5;
}
.dashboard-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 0.5rem;
}
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--dash-border);
}
.dashboard-header h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--dash-text);
  letter-spacing: -0.02em;
}
.dashboard-date {
  font-size: 0.9rem;
  color: var(--dash-text-light);
  font-weight: 500;
}
.kpi-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2rem;
}
.kpi-card {
  background: var(--dash-card);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  transition: all 0.2s ease;
  border: 1px solid var(--dash-border);
  text-decoration: none;
  display: block;
  color: inherit;
}
.kpi-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transform: translateY(-2px);
  text-decoration: none;
  color: inherit;
}
.kpi-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.kpi-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.kpi-icon svg { width: 22px; height: 22px; }
.kpi-card.primary .kpi-icon { background: var(--dash-primary-light); color: var(--dash-primary); }
.kpi-card.success .kpi-icon { background: var(--dash-success-light); color: var(--dash-success); }
.kpi-card.warning .kpi-icon { background: var(--dash-warning-light); color: var(--dash-warning); }
.kpi-card.danger  .kpi-icon { background: var(--dash-danger-light);  color: var(--dash-danger);  }
.kpi-card.purple  .kpi-icon { background: var(--dash-purple-light);  color: var(--dash-purple);  }
.kpi-label {
  font-size: 0.85rem;
  color: var(--dash-text-light);
  font-weight: 500;
  margin-bottom: 0.5rem;
}
.kpi-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--dash-text);
  line-height: 1.1;
}
.kpi-value .unit {
  font-size: 1rem;
  font-weight: 500;
  color: var(--dash-text-light);
  margin-left: 0.25rem;
}
.kpi-change {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
  margin-top: 0.75rem;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  font-weight: 600;
}
.kpi-change.up   { background: #f0f0f0; color: #333; }
.kpi-change.down { background: var(--dash-danger-light); color: var(--dash-danger); }
.alcohol-sync-area {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
.alcohol-sync-btn {
  background: #444;
  color: white;
  border: none;
  padding: 0.6rem 1.25rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.alcohol-sync-btn:hover:not(:disabled) { background: #333; }
.alcohol-sync-btn:disabled { opacity: 0.6; cursor: default; }
.alcohol-sync-status { font-size: 0.8rem; font-weight: 500; }
.dashboard-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 1.5rem;
}
@media (max-width: 1100px) {
  .dashboard-grid { grid-template-columns: 1fr; }
}
.widget {
  background: var(--dash-card);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid var(--dash-border);
  overflow: hidden;
}
.widget-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--dash-border);
  background: #fafbfc;
}
.widget-header h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--dash-text);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.widget-header h3 svg { color: var(--dash-text-light); }
.widget-body { padding: 1.25rem; }
.progress-ring-container {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem 1rem;
}
.progress-ring {
  position: relative;
  width: 150px;
  height: 150px;
}
.progress-ring svg { transform: rotate(-90deg); }
.progress-ring-bg {
  fill: none;
  stroke: #e2e8f0;
  stroke-width: 14;
}
.progress-ring-fill {
  fill: none;
  stroke: #555;
  stroke-width: 14;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.6s ease;
}
.progress-ring-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
}
.progress-ring-value {
  font-size: 2.25rem;
  font-weight: 700;
  color: var(--dash-text);
}
.progress-ring-label {
  font-size: 0.85rem;
  color: var(--dash-text-light);
  font-weight: 500;
}
.progress-bars { padding: 0 1.25rem 1.25rem; }
.progress-item { margin-bottom: 1.25rem; }
.progress-item:last-child { margin-bottom: 0; }
.progress-label {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
  font-size: 0.85rem;
}
.progress-label span:first-child { color: var(--dash-text-light); font-weight: 500; }
.progress-label span:last-child  { font-weight: 700; color: var(--dash-text); }
.progress-bar {
  height: 10px;
  background: #e2e8f0;
  border-radius: 5px;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  border-radius: 5px;
  transition: width 0.5s ease;
}
.progress-fill.blue   { background: #666; }
.progress-fill.green  { background: #555; }
.progress-fill.yellow { background: #888; }
.progress-fill.red    { background: #c62828; }
.activity-list { list-style: none; padding: 0; margin: 0; }
.activity-item {
  display: flex;
  gap: 1rem;
  padding: 1rem 0;
  border-bottom: 1px solid var(--dash-border);
}
.activity-item:last-child { border-bottom: none; padding-bottom: 0; }
.activity-icon {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  background: #f1f5f9;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}
.activity-content { flex: 1; }
.activity-text { font-size: 0.875rem; color: var(--dash-text); line-height: 1.4; }
.activity-text strong { font-weight: 600; }
.activity-time { font-size: 0.8rem; color: var(--dash-text-muted); margin-top: 0.25rem; }
.quick-actions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  padding: 1.25rem;
}
.quick-action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.625rem;
  padding: 1.25rem 1rem;
  background: #f8fafc;
  border: 1px solid var(--dash-border);
  border-radius: 10px;
  text-decoration: none;
  color: var(--dash-text);
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.15s;
}
.quick-action-btn:hover {
  background: #eee;
  border-color: #999;
  color: #333;
  text-decoration: none;
}
.quick-action-btn svg { width: 26px; height: 26px; color: var(--dash-text-light); }
.quick-action-btn:hover svg { color: #333; }
.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: var(--dash-text-muted);
}
.empty-state p { margin: 0; font-size: 0.9rem; }
.status-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  padding: 1.25rem;
}
.status-item {
  text-align: center;
  padding: 1rem 0.5rem;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid var(--dash-border);
}
.status-count { font-size: 1.5rem; font-weight: 700; }
.status-label { font-size: 0.75rem; color: var(--dash-text-light); margin-top: 0.25rem; font-weight: 500; }
.event-list { max-height: 220px; overflow-y: auto; }
.event-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin-bottom: 0.5rem;
  background: #f8fafc;
  border: 1px solid var(--dash-border);
}
.event-item:last-child { margin-bottom: 0; }
.event-calendar-color {
  width: 4px;
  height: 28px;
  border-radius: 2px;
  margin-right: 0.75rem;
  flex-shrink: 0;
}
.event-time {
  min-width: 90px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #444;
}
.event-time.all-day { color: #555; }
.event-title {
  flex: 1;
  font-weight: 500;
  color: var(--dash-text);
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.no-events {
  text-align: center;
  padding: 2rem;
  color: var(--dash-text-muted);
  font-size: 0.9rem;
}
.today-date {
  font-size: 0.8rem;
  color: var(--dash-text-muted);
  font-weight: 500;
}
.left-column, .right-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
`
