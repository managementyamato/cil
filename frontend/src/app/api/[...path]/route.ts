/**
 * PHP バックエンドへのプロキシ Route Handler
 *
 * /api/* へのリクエストを PHP バックエンドに転送する。
 * ブラウザの Cookie（PHPセッションなど）もそのまま転送する。
 */

import { NextRequest, NextResponse } from 'next/server'

const PHP_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

async function proxyRequest(req: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const { path } = await params
  const targetUrl = `${PHP_BASE}/api/${path.join('/')}`

  // クエリパラメータもそのまま転送
  const { search } = new URL(req.url)
  const url = search ? `${targetUrl}${search}` : targetUrl

  // ブラウザの Cookie をそのまま転送
  const cookie = req.headers.get('cookie') ?? ''

  const headers: Record<string, string> = {
    'Content-Type': req.headers.get('content-type') ?? 'application/json',
    ...(cookie ? { Cookie: cookie } : {}),
  }

  // CSRF トークンヘッダーも転送
  const csrfToken = req.headers.get('x-csrf-token')
  if (csrfToken) headers['X-CSRF-Token'] = csrfToken

  const fetchOptions: RequestInit = {
    method: req.method,
    headers,
  }

  if (!['GET', 'HEAD'].includes(req.method)) {
    fetchOptions.body = await req.text()
  }

  const phpRes = await fetch(url, fetchOptions)

  // PHP からのレスポンスをそのまま返す（Set-Cookie も含む）
  const resHeaders = new Headers()
  phpRes.headers.forEach((value, key) => {
    // next.js が自動で設定するものは除外
    if (!['transfer-encoding', 'connection'].includes(key.toLowerCase())) {
      resHeaders.append(key, value)
    }
  })

  const body = await phpRes.text()
  return new NextResponse(body, {
    status: phpRes.status,
    headers: resHeaders,
  })
}

export const GET = proxyRequest
export const POST = proxyRequest
export const PUT = proxyRequest
export const DELETE = proxyRequest
export const OPTIONS = proxyRequest
