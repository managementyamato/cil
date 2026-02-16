<?php
/**
 * 配色サンプルページ
 * 参考: https://baigie.me/officialblog/2020/07/21/web-color-planning/
 */
require_once __DIR__ . '/../api/auth.php';

$pageTitle = '配色サンプル';
$currentPage = 'color-samples.php';
include __DIR__ . '/../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* ===== サンプル1: 現行モノトーン配色 ===== */
.sample-1 {
    --s-primary: #333;
    --s-primary-light: #f5f5f5;
    --s-success: #555;
    --s-success-light: #f0f0f0;
    --s-warning: #666;
    --s-warning-light: #f5f5f5;
    --s-danger: #c62828;
    --s-danger-light: #ffebee;
    --s-text: #212121;
    --s-bg: #fafafa;
}

/* ===== サンプル2: ネイビー基調（ビジネス向け） ===== */
.sample-2 {
    --s-primary: #2c3e50;
    --s-primary-light: #ecf0f1;
    --s-success: #27ae60;
    --s-success-light: #e8f8f0;
    --s-warning: #f39c12;
    --s-warning-light: #fef9e7;
    --s-danger: #c0392b;
    --s-danger-light: #fdecea;
    --s-text: #2c3e50;
    --s-bg: #f8f9fa;
}

/* ===== サンプル3: グリーン基調（信頼・安心） ===== */
.sample-3 {
    --s-primary: #1e8449;
    --s-primary-light: #e8f6ef;
    --s-success: #27ae60;
    --s-success-light: #d4efdf;
    --s-warning: #d68910;
    --s-warning-light: #fef5e7;
    --s-danger: #c0392b;
    --s-danger-light: #fadbd8;
    --s-text: #1c2833;
    --s-bg: #f4f6f6;
}

/* ===== サンプル4: パープル基調（モダン・クリエイティブ） ===== */
.sample-4 {
    --s-primary: #5b2c6f;
    --s-primary-light: #f4ecf7;
    --s-success: #1e8449;
    --s-success-light: #d5f5e3;
    --s-warning: #b9770e;
    --s-warning-light: #fef9e7;
    --s-danger: #922b21;
    --s-danger-light: #f9ebea;
    --s-text: #2c2c54;
    --s-bg: #faf8fc;
}

/* ===== サンプル5: ティール基調（清潔感・医療系） ===== */
.sample-5 {
    --s-primary: #117a65;
    --s-primary-light: #e8f6f3;
    --s-success: #1abc9c;
    --s-success-light: #d1f2eb;
    --s-warning: #d68910;
    --s-warning-light: #fcf3cf;
    --s-danger: #c0392b;
    --s-danger-light: #f9ebea;
    --s-text: #1c2833;
    --s-bg: #f7fafa;
}

/* ===== サンプル6: ダークモード ===== */
.sample-6 {
    --s-primary: #5dade2;
    --s-primary-light: #2c3e50;
    --s-success: #58d68d;
    --s-success-light: #1e3d2f;
    --s-warning: #f4d03f;
    --s-warning-light: #3d3d1e;
    --s-danger: #ec7063;
    --s-danger-light: #4a2020;
    --s-text: #ecf0f1;
    --s-bg: #1c2833;
}

/* ===== 共通スタイル ===== */
.sample-section {
    margin-bottom: 2rem;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.sample-header {
    padding: 1rem 1.5rem;
    background: var(--s-primary);
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
}

.sample-body {
    padding: 1.5rem;
    background: var(--s-bg);
    color: var(--s-text);
}

.sample-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.sample-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.sample-card h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: var(--s-text);
}

.sample-card p {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
}

/* ボタンサンプル */
.sample-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.sample-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
}

.sample-btn-primary {
    background: var(--s-primary);
    color: white;
}

.sample-btn-success {
    background: var(--s-success);
    color: white;
}

.sample-btn-warning {
    background: var(--s-warning);
    color: white;
}

.sample-btn-danger {
    background: var(--s-danger);
    color: white;
}

.sample-btn-outline {
    background: white;
    border: 2px solid var(--s-primary);
    color: var(--s-primary);
}

/* ステータスバッジ */
.sample-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.sample-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.sample-badge-pending {
    background: var(--s-danger-light);
    color: var(--s-danger);
}

.sample-badge-progress {
    background: var(--s-warning-light);
    color: #92400e;
}

.sample-badge-complete {
    background: var(--s-success-light);
    color: #065f46;
}

/* アラート */
.sample-alerts {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sample-alert {
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    border-left: 4px solid;
}

.sample-alert-success {
    background: var(--s-success-light);
    border-color: var(--s-success);
    color: #065f46;
}

.sample-alert-warning {
    background: var(--s-warning-light);
    border-color: var(--s-warning);
    color: #92400e;
}

.sample-alert-danger {
    background: var(--s-danger-light);
    border-color: var(--s-danger);
    color: #991b1b;
}

/* テーブル */
.sample-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    margin-top: 1rem;
}

.sample-table th {
    background: var(--s-primary-light);
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--s-text);
    border-bottom: 2px solid #ddd;
}

.sample-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #eee;
    font-size: 0.875rem;
}

.sample-table tr:hover {
    background: var(--s-primary-light);
}

/* ポイント解説 */
.design-points {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 1rem;
    border-radius: 0 8px 8px 0;
    margin-top: 1rem;
}

.design-points h4 {
    margin: 0 0 0.5rem 0;
    color: #856404;
}

.design-points ul {
    margin: 0;
    padding-left: 1.25rem;
    color: #856404;
    font-size: 0.875rem;
}

.design-points li {
    margin-bottom: 0.25rem;
}

/* サイドバー風サンプル */
.sample-sidebar {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    max-width: 240px;
}

.sample-sidebar-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: var(--s-text);
    text-decoration: none;
    font-size: 0.875rem;
    border-left: 3px solid transparent;
    transition: all 0.2s;
}

.sample-sidebar-link:hover {
    background: var(--s-primary-light);
    border-left-color: var(--s-primary);
}

.sample-sidebar-link.active {
    background: var(--s-primary-light);
    border-left-color: var(--s-primary);
    font-weight: 600;
}

/* コントラスト比表示 */
.contrast-info {
    display: inline-block;
    background: #e3f2fd;
    color: #1565c0;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.contrast-pass {
    background: #e8f5e9;
    color: #2e7d32;
}

.contrast-fail {
    background: #ffebee;
    color: #c62828;
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1>配色サンプル</h1>
        <p     class="text-gray-500 text-09">
            参考: Webサイトにおける色彩設計の9つのポイント
        </p>
    </div>

    <!-- 設計ポイントの解説 -->
    <div         class="card" class="mb-4">
        <h3  class="mb-2">配色設計の重要ポイント</h3>
        <div        class="gap-2 grid grid-auto-280">
            <div      class="p-2 rounded-lg bg-f8">
                <strong     class="text-c62828">1. 赤はアラート専用</strong>
                <p      class="text-sm text-gray-666 mt-1">
                    赤をメインカラーにすると警告機能が薄れる。エラー表示のみに限定。
                </p>
            </div>
            <div      class="p-2 rounded-lg bg-f8">
                <strong     class="text-1565c0">2. 青いテキストはNG</strong>
                <p      class="text-sm text-gray-666 mt-1">
                    青はリンクと認識される。本文やステータスに青を使わない。
                </p>
            </div>
            <div      class="p-2 rounded-lg bg-f8">
                <strong     class="text-333">3. コントラスト比4.5:1以上</strong>
                <p      class="text-sm text-gray-666 mt-1">
                    本文テキストは#333以上の濃さが必要（WCAG基準）。
                </p>
            </div>
            <div      class="p-2 rounded-lg bg-f8">
                <strong   class="text-gray-666">4. 背景は淡いグレー</strong>
                <p      class="text-sm text-gray-666 mt-1">
                    純白(#fff)より淡いグレー(#f5f5f5〜#fafafa)が目に優しい。
                </p>
            </div>
        </div>
    </div>

    <!-- サンプル1: 現行配色 -->
    <div class="sample-section sample-1">
        <div class="sample-header">
            サンプル1: 現行モノトーン配色
            <span class="contrast-info contrast-pass">コントラスト比 OK</span>
        </div>
        <div class="sample-body">
            <div class="sample-grid">
                <div class="sample-card">
                    <h4>統計カード</h4>
                    <p      class="font-bold text-2xl text-s-text">128</p>
                    <p>今月の案件数</p>
                </div>
                <div class="sample-card">
                    <h4>進行中</h4>
                    <p      class="font-bold text-2xl text-s-text">45</p>
                    <p>対応中のトラブル</p>
                </div>
                <div class="sample-card">
                    <h4>完了</h4>
                    <p      class="font-bold text-2xl text-s-text">83</p>
                    <p>解決済み</p>
                </div>
            </div>

            <h4>ボタン</h4>
            <div class="sample-buttons">
                <button class="sample-btn sample-btn-primary">保存する</button>
                <button class="sample-btn sample-btn-success">完了</button>
                <button class="sample-btn sample-btn-warning">保留</button>
                <button class="sample-btn sample-btn-danger">削除</button>
                <button class="sample-btn sample-btn-outline">キャンセル</button>
            </div>

            <h4>ステータスバッジ</h4>
            <div class="sample-badges">
                <span class="sample-badge sample-badge-pending">未対応</span>
                <span class="sample-badge sample-badge-progress">対応中</span>
                <span class="sample-badge sample-badge-complete">完了</span>
            </div>

            <div class="design-points">
                <h4>評価</h4>
                <ul>
                    <li>シンプルで落ち着いた印象</li>
                    <li>コントラスト十分で視認性良好</li>
                    <li>やや地味で活気がない印象も</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- サンプル2: ネイビー基調 -->
    <div class="sample-section sample-2">
        <div class="sample-header">
            サンプル2: ネイビー基調（ビジネス向け・推奨）
            <span class="contrast-info contrast-pass">コントラスト比 OK</span>
        </div>
        <div class="sample-body">
            <div class="sample-grid">
                <div class="sample-card">
                    <h4>統計カード</h4>
                    <p      class="font-bold text-2xl text-s-text">128</p>
                    <p>今月の案件数</p>
                </div>
                <div class="sample-card">
                    <h4>進行中</h4>
                    <p      class="font-bold text-2xl text-s-warning">45</p>
                    <p>対応中のトラブル</p>
                </div>
                <div class="sample-card">
                    <h4>完了</h4>
                    <p      class="font-bold text-2xl text-s-success">83</p>
                    <p>解決済み</p>
                </div>
            </div>

            <h4>ボタン</h4>
            <div class="sample-buttons">
                <button class="sample-btn sample-btn-primary">保存する</button>
                <button class="sample-btn sample-btn-success">完了</button>
                <button class="sample-btn sample-btn-warning">保留</button>
                <button class="sample-btn sample-btn-danger">削除</button>
                <button class="sample-btn sample-btn-outline">キャンセル</button>
            </div>

            <h4>ステータスバッジ</h4>
            <div class="sample-badges">
                <span class="sample-badge sample-badge-pending">未対応</span>
                <span class="sample-badge sample-badge-progress">対応中</span>
                <span class="sample-badge sample-badge-complete">完了</span>
            </div>

            <h4>アラート</h4>
            <div class="sample-alerts">
                <div class="sample-alert sample-alert-success">保存が完了しました。</div>
                <div class="sample-alert sample-alert-warning">入力内容を確認してください。</div>
                <div class="sample-alert sample-alert-danger">エラーが発生しました。</div>
            </div>

            <table class="sample-table">
                <thead>
                    <tr>
                        <th>PJ番号</th>
                        <th>案件名</th>
                        <th>ステータス</th>
                        <th>担当者</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>PJ-2024-001</td>
                        <td>新規システム開発</td>
                        <td><span class="sample-badge sample-badge-progress">対応中</span></td>
                        <td>田中太郎</td>
                    </tr>
                    <tr>
                        <td>PJ-2024-002</td>
                        <td>サーバー移行</td>
                        <td><span class="sample-badge sample-badge-complete">完了</span></td>
                        <td>鈴木花子</td>
                    </tr>
                </tbody>
            </table>

            <div class="design-points">
                <h4>評価（推奨）</h4>
                <ul>
                    <li>ネイビーは信頼感・誠実さを表現</li>
                    <li>青をテキストに使わず、リンクと混同しない</li>
                    <li>機能色（成功・警告・エラー）が明確に分離</li>
                    <li>ビジネス管理システムに最適</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- サンプル3: グリーン基調 -->
    <div class="sample-section sample-3">
        <div class="sample-header">
            サンプル3: グリーン基調（信頼・安心）
            <span class="contrast-info contrast-pass">コントラスト比 OK</span>
        </div>
        <div class="sample-body">
            <div class="sample-grid">
                <div class="sample-card">
                    <h4>統計カード</h4>
                    <p      class="font-bold text-2xl text-s-text">128</p>
                    <p>今月の案件数</p>
                </div>
                <div class="sample-card">
                    <h4>進行中</h4>
                    <p      class="font-bold text-2xl text-s-warning">45</p>
                    <p>対応中のトラブル</p>
                </div>
                <div class="sample-card">
                    <h4>完了</h4>
                    <p      class="font-bold text-2xl text-s-success">83</p>
                    <p>解決済み</p>
                </div>
            </div>

            <h4>ボタン</h4>
            <div class="sample-buttons">
                <button class="sample-btn sample-btn-primary">保存する</button>
                <button class="sample-btn sample-btn-success">完了</button>
                <button class="sample-btn sample-btn-warning">保留</button>
                <button class="sample-btn sample-btn-danger">削除</button>
                <button class="sample-btn sample-btn-outline">キャンセル</button>
            </div>

            <h4>ステータスバッジ</h4>
            <div class="sample-badges">
                <span class="sample-badge sample-badge-pending">未対応</span>
                <span class="sample-badge sample-badge-progress">対応中</span>
                <span class="sample-badge sample-badge-complete">完了</span>
            </div>

            <div class="design-points">
                <h4>評価</h4>
                <ul>
                    <li>緑は安心感・成長を表現</li>
                    <li>環境系・健康系サービスに適合</li>
                    <li>成功表示との区別が難しくなる場合あり</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- サンプル4: パープル基調 -->
    <div class="sample-section sample-4">
        <div class="sample-header">
            サンプル4: パープル基調（モダン・クリエイティブ）
            <span class="contrast-info contrast-pass">コントラスト比 OK</span>
        </div>
        <div class="sample-body">
            <div class="sample-grid">
                <div class="sample-card">
                    <h4>統計カード</h4>
                    <p      class="font-bold text-2xl text-s-text">128</p>
                    <p>今月の案件数</p>
                </div>
                <div class="sample-card">
                    <h4>進行中</h4>
                    <p      class="font-bold text-2xl text-s-warning">45</p>
                    <p>対応中のトラブル</p>
                </div>
                <div class="sample-card">
                    <h4>完了</h4>
                    <p      class="font-bold text-2xl text-s-success">83</p>
                    <p>解決済み</p>
                </div>
            </div>

            <h4>ボタン</h4>
            <div class="sample-buttons">
                <button class="sample-btn sample-btn-primary">保存する</button>
                <button class="sample-btn sample-btn-success">完了</button>
                <button class="sample-btn sample-btn-warning">保留</button>
                <button class="sample-btn sample-btn-danger">削除</button>
                <button class="sample-btn sample-btn-outline">キャンセル</button>
            </div>

            <h4>ステータスバッジ</h4>
            <div class="sample-badges">
                <span class="sample-badge sample-badge-pending">未対応</span>
                <span class="sample-badge sample-badge-progress">対応中</span>
                <span class="sample-badge sample-badge-complete">完了</span>
            </div>

            <div class="design-points">
                <h4>評価</h4>
                <ul>
                    <li>紫は高級感・創造性を表現</li>
                    <li>クリエイティブ系サービスに適合</li>
                    <li>ビジネス管理システムには少し派手かも</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- サンプル5: ティール基調 -->
    <div class="sample-section sample-5">
        <div class="sample-header">
            サンプル5: ティール基調（清潔感・医療系）
            <span class="contrast-info contrast-pass">コントラスト比 OK</span>
        </div>
        <div class="sample-body">
            <div class="sample-grid">
                <div class="sample-card">
                    <h4>統計カード</h4>
                    <p      class="font-bold text-2xl text-s-text">128</p>
                    <p>今月の案件数</p>
                </div>
                <div class="sample-card">
                    <h4>進行中</h4>
                    <p      class="font-bold text-2xl text-s-warning">45</p>
                    <p>対応中のトラブル</p>
                </div>
                <div class="sample-card">
                    <h4>完了</h4>
                    <p      class="font-bold text-2xl text-s-success">83</p>
                    <p>解決済み</p>
                </div>
            </div>

            <h4>ボタン</h4>
            <div class="sample-buttons">
                <button class="sample-btn sample-btn-primary">保存する</button>
                <button class="sample-btn sample-btn-success">完了</button>
                <button class="sample-btn sample-btn-warning">保留</button>
                <button class="sample-btn sample-btn-danger">削除</button>
                <button class="sample-btn sample-btn-outline">キャンセル</button>
            </div>

            <h4>ステータスバッジ</h4>
            <div class="sample-badges">
                <span class="sample-badge sample-badge-pending">未対応</span>
                <span class="sample-badge sample-badge-progress">対応中</span>
                <span class="sample-badge sample-badge-complete">完了</span>
            </div>

            <div class="design-points">
                <h4>評価</h4>
                <ul>
                    <li>ティールは清潔感・信頼性を表現</li>
                    <li>医療・健康・金融系サービスに適合</li>
                    <li>落ち着いた印象で長時間利用に向く</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- サンプル6: ダークモード -->
    <div class="sample-section sample-6">
        <div class="sample-header">
            サンプル6: ダークモード
            <span class="contrast-info contrast-pass">コントラスト比 OK</span>
        </div>
        <div class="sample-body">
            <div class="sample-grid">
                <div       class="sample-card bg-2c">
                    <h4    class="text-ecf">統計カード</h4>
                    <p      class="font-bold text-2xl text-ecf">128</p>
                    <p    class="text-bdc">今月の案件数</p>
                </div>
                <div       class="sample-card bg-2c">
                    <h4    class="text-ecf">進行中</h4>
                    <p        class="font-bold text-2xl" class="text-f4d">45</p>
                    <p    class="text-bdc">対応中のトラブル</p>
                </div>
                <div       class="sample-card bg-2c">
                    <h4    class="text-ecf">完了</h4>
                    <p        class="font-bold text-2xl text-58d68d">83</p>
                    <p    class="text-bdc">解決済み</p>
                </div>
            </div>

            <h4>ボタン</h4>
            <div class="sample-buttons">
                <button class="sample-btn sample-btn-primary">保存する</button>
                <button class="sample-btn sample-btn-success">完了</button>
                <button class="sample-btn sample-btn-warning">保留</button>
                <button class="sample-btn sample-btn-danger">削除</button>
                <button         class="sample-btn text-ecf bg-34495e">キャンセル</button>
            </div>

            <h4>ステータスバッジ</h4>
            <div class="sample-badges">
                <span class="sample-badge sample-badge-pending">未対応</span>
                <span class="sample-badge sample-badge-progress">対応中</span>
                <span class="sample-badge sample-badge-complete">完了</span>
            </div>

            <div         class="design-points bg-34495e border-f4d03f">
                <h4     class="text-f4d">評価</h4>
                <ul    class="text-ecf">
                    <li>目の疲れを軽減（長時間作業向け）</li>
                    <li>OLED画面で省電力効果</li>
                    <li>オプション機能として提供が理想</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 比較まとめ -->
    <div         class="card mt-4">
        <h3  class="mb-2">配色比較まとめ</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>サンプル</th>
                    <th>印象</th>
                    <th>適合シーン</th>
                    <th>推奨度</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1. モノトーン</td>
                    <td>シンプル・落ち着き</td>
                    <td>汎用的</td>
                    <td>★★★☆☆</td>
                </tr>
                <tr     class="bg-e8f5e9">
                    <td><strong>2. ネイビー</strong></td>
                    <td>信頼・誠実・プロフェッショナル</td>
                    <td>ビジネス管理システム</td>
                    <td><strong>★★★★★</strong></td>
                </tr>
                <tr>
                    <td>3. グリーン</td>
                    <td>安心・成長</td>
                    <td>環境・健康系</td>
                    <td>★★★☆☆</td>
                </tr>
                <tr>
                    <td>4. パープル</td>
                    <td>高級・創造性</td>
                    <td>クリエイティブ系</td>
                    <td>★★☆☆☆</td>
                </tr>
                <tr>
                    <td>5. ティール</td>
                    <td>清潔・信頼</td>
                    <td>医療・金融系</td>
                    <td>★★★★☆</td>
                </tr>
                <tr>
                    <td>6. ダーク</td>
                    <td>モダン・省眼精疲労</td>
                    <td>オプション機能</td>
                    <td>★★★☆☆</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../functions/footer.php'; ?>
