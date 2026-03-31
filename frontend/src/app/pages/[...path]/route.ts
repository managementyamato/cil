/**
 * PHP ページへのプロキシ Route Handler
 *
 * /pages/* へのリクエストを PHP バックエンドに転送する。
 * ログアウトなど既存 PHP ページへのアクセスに使用。
 */

import { NextRequest, NextResponse } from 'next/server'

const PHP_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

async function proxyRequest(req: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const { path } = await params
  const targetUrl = `${PHP_BASE}/pages/${path.join('/')}`

  const { search } = new URL(req.url)
  const url = search ? `${targetUrl}${search}` : targetUrl

  const cookie = req.headers.get('cookie') ?? ''
  const headers: Record<string, string> = {
    ...(cookie ? { Cookie: cookie } : {}),
  }

  const fetchOptions: RequestInit = {
    method: req.method,
    headers,
    redirect: 'manual', // PHP側のリダイレクトをそのまま返す
  }

  if (!['GET', 'HEAD'].includes(req.method)) {
    fetchOptions.body = await req.text()
  }

  const phpRes = await fetch(url, fetchOptions)

  const resHeaders = new Headers()
  phpRes.headers.forEach((value, key) => {
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
