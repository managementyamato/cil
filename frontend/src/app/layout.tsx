import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'YA管理一覧',
  description: '社内管理システム',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="ja">
      <head>
        <meta name="robots" content="noindex, nofollow" />
        <link rel="icon" type="image/png" href="/favicon.png" />
        <link rel="apple-touch-icon" href="/favicon.png" />
        {/* PHPシステムと同じCSSを流用 */}
        <link rel="stylesheet" href="/style.css" />
        <link rel="stylesheet" href="/css/components.css" />
      </head>
      <body>{children}</body>
    </html>
  )
}
