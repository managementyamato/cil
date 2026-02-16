# Next.js移行計画書

**作成日**: 2026-02-09
**対象システム**: YA Management System
**目標**: Yamato Basicとの技術スタック統一、保守性向上、パフォーマンス改善

---

## 📋 目次

1. [概要](#概要)
2. [現状分析](#現状分析)
3. [移行戦略](#移行戦略)
4. [技術スタック](#技術スタック)
5. [アーキテクチャ設計](#アーキテクチャ設計)
6. [移行ロードマップ](#移行ロードマップ)
7. [リスクと対策](#リスクと対策)
8. [成功基準](#成功基準)

---

## 概要

### 移行の目的

1. **技術スタック統一**: Yamato Basicと同じ技術（Next.js + React）を使用
2. **開発効率向上**: コンポーネントの再利用、型安全性（TypeScript）
3. **パフォーマンス改善**: SSR/ISR、最適化されたバンドル
4. **保守性向上**: モダンな開発環境、自動テスト、CI/CD

### 移行方針

- **段階的移行（Strangler Fig Pattern）**: 一度にすべてを書き直すのではなく、機能ごとに順次移行
- **PHPバックエンド維持**: 既存のPHP APIはそのまま活用（Next.js API Routesと共存）
- **データ層保持**: `data.json` + ファイルベースストレージは継続使用

---

## 現状分析

### 現在のアーキテクチャ

```
[Browser]
    ↓
[pages/*.php] ← インラインCSS/JS (7,217行CSS + 4,690行JS)
    ↓
[api/*.php] ← PHPバックエンドAPI
    ↓
[data.json] ← JSONファイルベースDB
```

### 技術スタック（現在）

| レイヤー | 技術 |
|---------|------|
| フロントエンド | PHP（HTML生成） + Vanilla JS |
| バックエンド | PHP 8.2 |
| データベース | JSON Files (`data.json`) |
| 認証 | PHPセッション + Google OAuth |
| 外部API | Google Sheets, MF Cloud, Google Chat |

### 課題

1. **コード重複**: モーダル、フォーム、テーブルスタイルが28ファイルに散在
2. **型安全性なし**: JavaScriptでランタイムエラーのリスク
3. **SEO**: 動的コンテンツのSEOが弱い
4. **開発体験**: ホットリロード、型補完がない
5. **テスト**: 自動UIテストが困難

---

## 移行戦略

### Strangler Fig Pattern（段階的置き換え）

既存のPHPアプリケーションを **段階的に** Next.jsに置き換える。

```
フェーズ1: 新規ページはNext.jsで開発
    ↓
フェーズ2: 主要ページを順次移行
    ↓
フェーズ3: 全ページ移行完了
```

### リバースプロキシ構成

```
[Nginx/Apache]
  ├─ /api/* → PHPバックエンド（既存）
  ├─ /pages/*.php → PHP（レガシー・段階的削除）
  └─ /* → Next.js (新規・移行済みページ)
```

---

## 技術スタック

### フロントエンド（Next.js）

| 技術 | バージョン | 用途 |
|------|-----------|------|
| **Next.js** | 14.x (App Router) | Reactフレームワーク、SSR/ISR |
| **React** | 18.x | UIライブラリ |
| **TypeScript** | 5.x | 型安全性 |
| **Tailwind CSS** | 3.x | ユーティリティCSS |
| **Radix UI** | 最新 | アクセシブルUIコンポーネント |
| **React Hook Form** | 7.x | フォーム管理 |
| **Zod** | 3.x | バリデーション |
| **TanStack Query** | 5.x | データフェッチ・キャッシング |
| **Zustand** | 4.x | 状態管理（軽量） |

### バックエンド

| 技術 | 用途 |
|------|------|
| **PHP 8.2** | 既存APIの維持 |
| **Next.js API Routes** | 新規APIエンドポイント |
| **NextAuth.js** | 認証（Google OAuthと統合） |

### 開発ツール

| ツール | 用途 |
|--------|------|
| **ESLint** | Linter |
| **Prettier** | フォーマッター |
| **Vitest** | 単体テスト |
| **Playwright** | E2Eテスト |
| **Storybook** | コンポーネントカタログ |

---

## アーキテクチャ設計

### ディレクトリ構造（Next.js App Router）

```
yamato-next/
├── app/                    # App Router（ルーティング）
│   ├── (auth)/            # 認証付きレイアウト
│   │   ├── dashboard/     # ダッシュボード
│   │   ├── customers/     # 顧客管理
│   │   ├── finance/       # 財務管理
│   │   └── layout.tsx     # 共通レイアウト（サイドバー）
│   ├── api/               # API Routes
│   │   ├── auth/[...nextauth]/ # NextAuth.js
│   │   └── proxy/         # PHP APIプロキシ
│   ├── login/             # ログインページ
│   └── layout.tsx         # ルートレイアウト
│
├── components/            # Reactコンポーネント
│   ├── ui/               # 基本UIコンポーネント
│   │   ├── Button.tsx
│   │   ├── Modal.tsx
│   │   ├── Table.tsx
│   │   └── Form/
│   ├── features/         # 機能別コンポーネント
│   │   ├── customers/
│   │   ├── finance/
│   │   └── dashboard/
│   └── layouts/          # レイアウトコンポーネント
│       ├── Sidebar.tsx
│       └── Header.tsx
│
├── lib/                  # ユーティリティ・ヘルパー
│   ├── api-client.ts     # API呼び出しクライアント
│   ├── auth.ts           # 認証ヘルパー
│   ├── validation.ts     # Zodスキーマ
│   └── constants.ts      # 定数
│
├── hooks/                # カスタムReact Hooks
│   ├── useCustomers.ts
│   ├── useFinance.ts
│   └── useAuth.ts
│
├── types/                # TypeScript型定義
│   ├── api.ts
│   ├── customer.ts
│   └── finance.ts
│
├── public/               # 静的ファイル
│   ├── icons/
│   └── images/
│
├── .env.local            # 環境変数
├── next.config.js
├── tailwind.config.js
├── tsconfig.json
└── package.json
```

### データフロー（Next.js）

```
[Browser]
    ↓
[Next.js Page] → Server Component (SSR/ISR)
    ↓
[API Client (TanStack Query)]
    ├─ Next.js API Routes (/api/*)
    └─ PHP Backend API (/api/*.php) ← 既存を活用
    ↓
[data.json]
```

### コンポーネント設計例

```tsx
// components/ui/Button.tsx
import { ButtonHTMLAttributes, forwardRef } from 'react'
import { cn } from '@/lib/utils'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger'
  size?: 'sm' | 'md' | 'lg'
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', size = 'md', ...props }, ref) => {
    return (
      <button
        ref={ref}
        className={cn(
          'btn',
          `btn-${variant}`,
          `btn-${size}`,
          className
        )}
        {...props}
      />
    )
  }
)
```

---

## 移行ロードマップ

### フェーズ0: 準備（1-2週間）

#### タスク

1. **Next.jsプロジェクト作成**
   ```bash
   npx create-next-app@latest yamato-next --typescript --tailwind --app
   ```

2. **既存CSS/JSの移植**
   - `css/components.css` → Tailwind CSSコンポーネント化
   - `js/common-utils.js` → TypeScript化

3. **型定義の作成**
   ```typescript
   // types/customer.ts
   export interface Customer {
     id: string
     name: string
     phone: string
     email: string
     address: string
   }
   ```

4. **API Client作成**
   ```typescript
   // lib/api-client.ts
   export async function fetchCustomers(): Promise<Customer[]> {
     const res = await fetch('/api/integration/customers.php')
     return res.json()
   }
   ```

#### 成果物

- Next.jsプロジェクトのセットアップ完了
- 基本UIコンポーネント作成（Button, Modal, Table等）
- 型定義ファイル作成

---

### フェーズ1: 並行運用開始（2-3週間）

#### タスク

1. **認証システム統合**
   - NextAuth.jsでGoogle OAuth設定
   - 既存PHPセッションとの互換性確保

2. **最初のページ移行: ダッシュボード (`index.php`)**
   - `app/(auth)/dashboard/page.tsx` 作成
   - Server ComponentでSSR実装
   - TanStack Queryでデータフェッチ

3. **リバースプロキシ設定**
   ```nginx
   # Nginx設定例
   location / {
     try_files $uri @nextjs;
   }

   location /api/ {
     # PHP APIへプロキシ
     fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
   }

   location @nextjs {
     proxy_pass http://localhost:3000;
   }
   ```

#### 成果物

- ダッシュボードがNext.jsで動作
- PHP APIはそのまま利用可能
- 両方のシステムが共存

---

### フェーズ2: 主要ページ移行（4-6週間）

優先度順に移行:

1. **顧客管理 (`customers.php`)** → `app/(auth)/customers/page.tsx`
2. **財務管理 (`finance.php`)** → `app/(auth)/finance/page.tsx`
3. **従業員管理 (`employees.php`)** → `app/(auth)/employees/page.tsx`
4. **タスク管理 (`tasks.php`)** → `app/(auth)/tasks/page.tsx`

#### 各ページの移行手順

1. TypeScript型定義作成
2. Server Component実装（データ取得）
3. Client Component実装（インタラクティブUI）
4. TanStack Queryでキャッシング
5. E2Eテスト作成（Playwright）
6. レガシーPHPページを削除

---

### フェーズ3: 完全移行（2-3週間）

#### タスク

1. **残りページの移行**
   - アルコールチェック
   - マスタ管理
   - 設定ページ

2. **パフォーマンス最適化**
   - 画像最適化（Next.js Image）
   - バンドルサイズ削減
   - ISR（Incremental Static Regeneration）適用

3. **PHP依存削除**
   - Next.js API Routesへ移行可能なAPIを移行
   - `data.json`操作をNext.js側に統合

4. **本番デプロイ**
   - Vercel / AWS / 自社サーバー

---

## リスクと対策

| リスク | 影響度 | 対策 |
|--------|--------|------|
| **PHPセッションとNextAuth.jsの不整合** | 高 | セッションCookieの共有、段階的移行中はPHP認証を優先 |
| **data.jsonの同時書き込み競合** | 中 | ファイルロック継続使用、将来的にはPostgreSQL等に移行検討 |
| **パフォーマンス低下** | 中 | SSR/ISRで対応、CDN活用 |
| **既存機能の動作不良** | 高 | E2Eテスト完備、段階的移行でリスク分散 |
| **開発リソース不足** | 中 | 優先度を絞り、MVP（最小限の機能）から開始 |

---

## 成功基準

### 技術指標

- [ ] ページ読み込み速度 50%改善（Lighthouse Score 90+）
- [ ] TypeScript導入によりランタイムエラー 80%削減
- [ ] コンポーネント再利用率 60%以上
- [ ] E2Eテストカバレッジ 70%以上

### ビジネス指標

- [ ] ユーザー満足度調査で「使いやすさ」向上
- [ ] 新機能開発速度 30%向上
- [ ] バグ発生率 50%削減

---

## 参考資料

- [Next.js App Router Documentation](https://nextjs.org/docs/app)
- [Strangler Fig Pattern - Martin Fowler](https://martinfowler.com/bliki/StranglerFigApplication.html)
- [TanStack Query](https://tanstack.com/query/latest)
- [Tailwind CSS](https://tailwindcss.com/)

---

## 更新履歴

| 日付 | 変更内容 |
|------|----------|
| 2026-02-09 | 初版作成 |
