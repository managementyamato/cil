'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { getSessionInfo, getLoginUrl } from '@/lib/auth'

export default function LoginPage() {
  const router = useRouter()

  useEffect(() => {
    // すでにログイン済みならダッシュボードへ
    getSessionInfo().then((session) => {
      if (session.authenticated) {
        router.replace('/dashboard')
      }
    })
  }, [router])

  const handleLogin = () => {
    window.location.href = getLoginUrl()
  }

  return (
    <div style={{
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      minHeight: '100vh',
      backgroundColor: '#f5f5f5',
    }}>
      <div style={{
        backgroundColor: 'white',
        padding: '48px',
        borderRadius: '8px',
        boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
        textAlign: 'center',
        maxWidth: '400px',
        width: '100%',
      }}>
        <h1 style={{ marginBottom: '8px', fontSize: '24px', fontWeight: 'bold' }}>
          YA Management System
        </h1>
        <p style={{ marginBottom: '32px', color: '#666' }}>社内管理システム</p>
        <button
          onClick={handleLogin}
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '8px',
            width: '100%',
            padding: '12px 24px',
            backgroundColor: '#4285f4',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            fontSize: '16px',
            cursor: 'pointer',
          }}
        >
          Googleでログイン
        </button>
      </div>
    </div>
  )
}
