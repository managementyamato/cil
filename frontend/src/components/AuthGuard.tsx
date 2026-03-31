'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { getSessionInfo } from '@/lib/auth'
import { User } from '@/types'

interface AuthGuardProps {
  children: (user: User) => React.ReactNode
}

export default function AuthGuard({ children }: AuthGuardProps) {
  const router = useRouter()
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    getSessionInfo().then((session) => {
      if (!session.authenticated) {
        router.replace('/login')
      } else {
        setUser(session.user)
        setLoading(false)
      }
    })
  }, [router])

  if (loading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
        <p>読み込み中...</p>
      </div>
    )
  }

  if (!user) return null

  return <>{children(user)}</>
}
