'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { User } from '@/types'

type Role = 'admin' | 'product' | 'sales'

const roleLevel: Record<Role, number> = { sales: 1, product: 2, admin: 3 }

function hasPermission(userRole: Role, required: Role): boolean {
  return (roleLevel[userRole] ?? 0) >= (roleLevel[required] ?? 999)
}

const roleLabels: Record<Role, string> = {
  admin: '管理部',
  product: '製品管理部',
  sales: '営業部',
}

interface PHPLayoutProps {
  user: User
  children: React.ReactNode
  title?: string
}

export default function PHPLayout({ user, children, title = 'YA管理一覧' }: PHPLayoutProps) {
  const pathname = usePathname()
  const role = user.role as Role

  // サイドバー開閉（style.cssの .sidebar-closed クラスに依存）
  const [sidebarOpen, setSidebarOpen] = useState(true)

  // トップへ戻るボタン
  const [showBackToTop, setShowBackToTop] = useState(false)
  useEffect(() => {
    const handleScroll = () => setShowBackToTop(window.scrollY > 400)
    window.addEventListener('scroll', handleScroll, { passive: true })
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

  return (
    <>
      <header className="header">
        <div className="header-content">
          <div className="header-left">
            <button
              className="menu-toggle"
              id="menuToggle"
              onClick={() => setSidebarOpen(o => !o)}
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="18" x2="21" y2="18" />
              </svg>
            </button>
            <h1>{title}</h1>
          </div>

          {/* グローバル検索 */}
          <div className="global-search-wrapper" style={{ flex: 1, maxWidth: '400px', margin: '0 1rem', position: 'relative' }}>
            <div style={{ position: 'relative' }}>
              <input
                type="text"
                id="globalSearchInput"
                placeholder="検索... (Ctrl+K)"
                autoComplete="off"
                style={{ width: '100%', padding: '0.4rem 0.75rem 0.4rem 2rem', border: '1px solid var(--gray-300)', borderRadius: '6px', fontSize: '0.875rem', background: 'var(--gray-50)' }}
              />
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" strokeWidth="2"
                style={{ position: 'absolute', left: '0.6rem', top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }}>
                <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
            </div>
            <div id="globalSearchResults" className="global-search-dropdown" style={{ display: 'none' }} />
          </div>

          <div className="user-info">
            <span>
              {user.name}
              {' '}
              <span className="role-badge">{roleLabels[role] ?? role}</span>
            </span>
            <a href={`${API_BASE}/pages/logout.php`} className="logout-btn">ログアウト</a>
          </div>
        </div>
      </header>

      <div className="layout">
        <aside className={`sidebar${sidebarOpen ? '' : ' sidebar-closed'}`} id="sidebar">
          <nav className="sidebar-nav">
            {/* 戻る */}
            <a
              href="#"
              className="sidebar-link"
              style={{ borderBottom: '1px solid var(--gray-200)', marginBottom: '1rem' }}
              onClick={e => { e.preventDefault(); window.history.back() }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="19" y1="12" x2="5" y2="12" /><polyline points="12 19 5 12 12 5" />
              </svg>
              <span>戻る</span>
            </a>

            {/* ダッシュボード */}
            <Link href="/dashboard" className={`sidebar-link${pathname === '/dashboard' ? ' active' : ''}`}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" />
              </svg>
              <span>ダッシュボード</span>
            </Link>

            {/* プロジェクト管理 */}
            {hasPermission(role, 'sales') && (
              <Link href="/projects" className={`sidebar-link${pathname === '/projects' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
                </svg>
                <span>プロジェクト管理</span>
              </Link>
            )}

            {/* トラブル対応 */}
            {hasPermission(role, 'sales') && (
              <Link href="/troubles" className={`sidebar-link${pathname === '/troubles' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                  <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                <span>トラブル対応</span>
              </Link>
            )}

            {/* 損益 */}
            {hasPermission(role, 'sales') && (
              <Link href="/finance" className={`sidebar-link${pathname === '/finance' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <line x1="12" y1="1" x2="12" y2="23" />
                  <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
                <span>損益</span>
              </Link>
            )}

            {/* 借入金 */}
            {hasPermission(role, 'sales') && (
              <Link href="/loans" className={`sidebar-link${pathname === '/loans' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
                <span>借入金</span>
              </Link>
            )}

            {/* 給与仕訳 */}
            {hasPermission(role, 'sales') && (
              <Link href="/payroll" className={`sidebar-link${pathname === '/payroll' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                <span>給与仕訳</span>
              </Link>
            )}

            {/* アルコールチェック */}
            {hasPermission(role, 'sales') && (
              <Link href="/photo-attendance" className={`sidebar-link${pathname === '/photo-attendance' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                  <circle cx="8.5" cy="8.5" r="1.5" />
                  <polyline points="21 15 16 10 5 21" />
                </svg>
                <span>アルコールチェック</span>
              </Link>
            )}

            {/* 顧客管理 */}
            {hasPermission(role, 'sales') && (
              <Link href="/customers" className={`sidebar-link${pathname === '/customers' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                <span>顧客管理</span>
              </Link>
            )}

            {/* マスタ管理 */}
            {hasPermission(role, 'sales') && (
              <Link href="/masters" className={`sidebar-link${pathname === '/masters' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                <span>マスタ管理</span>
              </Link>
            )}

            {/* デバイス管理（外部リンク） */}
            <a href="https://inventory.yamato-mgt.com/" target="_blank" rel="noreferrer" className="sidebar-link">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
              </svg>
              <span>デバイス管理</span>
            </a>

            {/* MF請求書一覧（admin only） */}
            {hasPermission(role, 'admin') && (
              <Link href="/mf-invoices" className={`sidebar-link${pathname === '/mf-invoices' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                  <line x1="16" y1="13" x2="8" y2="13" />
                  <line x1="16" y1="17" x2="8" y2="17" />
                  <polyline points="10 9 9 9 8 9" />
                </svg>
                <span>MF請求書一覧</span>
              </Link>
            )}

            {/* 設定（admin only） */}
            {hasPermission(role, 'admin') && (
              <Link href="/settings" className={`sidebar-link${pathname === '/settings' ? ' active' : ''}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <circle cx="12" cy="12" r="3" />
                  <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                </svg>
                <span>設定</span>
              </Link>
            )}
          </nav>
        </aside>

        <main className="main-content">
          {children}
        </main>
      </div>

      <div className="toast" id="toast" />

      {/* トップへ戻るボタン */}
      {showBackToTop && (
        <button
          className="back-to-top-btn"
          onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
        >
          ▲
        </button>
      )}
    </>
  )
}
