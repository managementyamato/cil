import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // /api/* と /pages/* は Route Handler（src/app/api/[...path]/route.ts 等）で
  // Cookie ごと PHP バックエンドにプロキシしているため rewrites は不要
}

export default nextConfig
