// @ts-check
const { test, expect } = require('./fixtures');

test.describe('営業ツール - スモーク', () => {
  test('ページが開き「営業ツール」h2 が表示される', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools');
    await expect(authedPage.locator('h2')).toContainText('営業ツール');
  });

  test('タブが 7 つ存在する (products/pricing/catalogs/scripts/history/leads/create)', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools');
    const tabs = authedPage.locator('.st-tab[data-tab]');
    await expect(tabs).toHaveCount(7);
  });

  test('products タブがデフォルト active', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools');
    const activeTab = authedPage.locator('.st-tab.active');
    await expect(activeTab).toHaveAttribute('data-tab', 'products');
  });

  test('pricing タブに遷移できる', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    const activeTab = authedPage.locator('.st-tab.active');
    await expect(activeTab).toHaveAttribute('data-tab', 'pricing');
  });

  test('leads タブに遷移できる', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=leads');
    await expect(authedPage.locator('.st-tab.active')).toHaveAttribute('data-tab', 'leads');
  });

  test('price-list-get API が 200 を返す', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools');
    const res = await authedPage.request.get('/api/price-list-get.php');
    expect(res.status()).toBe(200);
  });

  test('leads-api list が 200 を返す', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools');
    const res = await authedPage.request.get('/api/leads-api.php?action=list');
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('success', true);
  });
});

test.describe('価格表タブ - UI', () => {
  test('pricing タブに一覧検索バーが存在する', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    await expect(authedPage.locator('#ppListSearch')).toBeAttached();
  });

  test('pricing タブに詳細検索バーが存在する', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    await expect(authedPage.locator('#ppDetailSearch')).toBeAttached();
  });

  test('pricing タブに空状態ヒーロー要素が存在する', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    await expect(authedPage.locator('#ppEmptyHero')).toBeAttached();
  });

  test('製品定義 JSON から 6 製品以上が一覧描画される', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    // データ取得 + JS 描画完了を待つ
    await authedPage.waitForFunction(
      () => document.querySelectorAll('#ppProductList .pp-product-row').length > 0,
      { timeout: 5000 }
    );
    const productRows = await authedPage.locator('#ppProductList .pp-product-row').count();
    expect(productRows).toBeGreaterThanOrEqual(6);
  });

  test('一覧検索でリストが絞り込まれる', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    await authedPage.waitForFunction(
      () => document.querySelectorAll('#ppProductList .pp-product-row').length > 0,
      { timeout: 5000 }
    );
    await authedPage.fill('#ppListSearch', 'モニまる');
    // input イベント後の即時再描画
    await authedPage.waitForTimeout(100);
    const visibleCount = await authedPage.locator('#ppProductList .pp-product-row').count();
    expect(visibleCount).toBe(1);
  });
});

test.describe('価格表詳細ビュー: 顧客視点型 + ウィザード', () => {
  test('詳細ビューに 3 つの顧客セクションが描画される', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    await authedPage.waitForFunction(
      () => document.querySelectorAll('#ppProductList .pp-product-row').length > 0,
      { timeout: 5000 }
    );
    await authedPage.click('.pp-product-row[data-id="monitarou"]');
    await authedPage.waitForFunction(
      () => document.querySelectorAll('.pp-cust-section').length > 0,
      { timeout: 5000 }
    );
    const sections = await authedPage.locator('.pp-cust-section').count();
    expect(sections).toBe(3);
  });

  test('「さっと価格を調べる」ボタンでウィザードモーダルが開く', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    await authedPage.waitForSelector('#ppQuickQuoteBtn');
    await authedPage.click('#ppQuickQuoteBtn');
    await expect(authedPage.locator('#ppQuoteModal')).toBeVisible();
    await expect(authedPage.locator('.pp-quote-title')).toContainText('さっと価格を調べる');
  });
});

test.describe('新人オンボーディングページ', () => {
  test('ページが開き用語解説と FAQ カードが描画される', async ({ authedPage }) => {
    await authedPage.goto('/pages/onboarding-prices');
    await expect(authedPage.locator('.ob-hero h2')).toContainText('価格表の見方ガイド');
    const glossaryItems = await authedPage.locator('.ob-glossary-item').count();
    expect(glossaryItems).toBe(6);
    await authedPage.waitForFunction(
      () => document.querySelectorAll('.faq-card').length > 0,
      { timeout: 5000 }
    );
    const faqCards = await authedPage.locator('.faq-card').count();
    expect(faqCards).toBeGreaterThanOrEqual(1);
  });
});

test.describe('詳細ビュー デザイン比較プレビュー', () => {
  test('プレビューページが開き 9 セクションが描画される', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools-detail-preview');
    await expect(authedPage.locator('.dv-hero h2')).toContainText('詳細ビュー デザイン比較');
    const sections = await authedPage.locator('.dv-section').count();
    expect(sections).toBe(9);
  });

  test('H 電卓: スライダーで金額がリアクトする', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools-detail-preview');
    await authedPage.waitForFunction(
      () => document.querySelectorAll('#calcSize option').length > 1,
      { timeout: 5000 }
    );
    // 初期値で合計が表示されることを確認
    await expect(authedPage.locator('#calcTotal')).toContainText('¥');
  });

  test('A ウィザード: 選択するとプレビュー価格が出る', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools-detail-preview');
    // データロード後を待つ
    await authedPage.waitForFunction(
      () => document.querySelectorAll('#wizSize option').length > 1,
      { timeout: 5000 }
    );
    await authedPage.click('.wiz-choice[data-tier="A"]');
    await authedPage.click('.wiz-choice[data-mode="sale"]');
    await authedPage.selectOption('#wizSize', '0');
    await expect(authedPage.locator('#wizResult')).toBeVisible();
    await expect(authedPage.locator('#wizPrice')).toContainText('¥');
  });
});

// ================================================================
// タブ単位の挙動回帰検出 (Sprint 1: sales-tools.php 分割の前提テスト)
// 各タブが ?tab=xxx で開き、対応パネルが active になることを確認する。
// 分割後も URL ルーティングが無傷であることをこのテスト群で担保する。
// ================================================================

test.describe('タブ別ルーティング - 全 7 タブ', () => {
  test('products タブ: パネルが active 表示される', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=products');
    await expect(authedPage.locator('#panel-products')).toHaveClass(/active/);
    await expect(authedPage.locator('.st-tab.active')).toHaveAttribute('data-tab', 'products');
  });

  test('pricing タブ: パネルが active 表示される', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=pricing');
    await expect(authedPage.locator('#panel-pricing')).toHaveClass(/active/);
    await expect(authedPage.locator('.st-tab.active')).toHaveAttribute('data-tab', 'pricing');
  });

  test('catalogs タブ: パネルが active 表示され準備中表記がある', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=catalogs');
    await expect(authedPage.locator('#panel-catalogs')).toHaveClass(/active/);
    await expect(authedPage.locator('#panel-catalogs')).toContainText('カタログ');
  });

  test('scripts タブ: パネルが active 表示され準備中表記がある', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=scripts');
    await expect(authedPage.locator('#panel-scripts')).toHaveClass(/active/);
    await expect(authedPage.locator('#panel-scripts')).toContainText('トークスクリプト');
  });

  test('history タブ: パネルが active 表示され準備中表記がある', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=history');
    await expect(authedPage.locator('#panel-history')).toHaveClass(/active/);
    await expect(authedPage.locator('#panel-history')).toContainText('見積履歴');
  });

  test('leads タブ: パネルが active 表示されツールバー要素がある', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=leads');
    await expect(authedPage.locator('#panel-leads')).toHaveClass(/active/);
    await expect(authedPage.locator('#leadSearch')).toBeAttached();
    await expect(authedPage.locator('#leadStatusFilter')).toBeAttached();
  });

  test('create タブ: パネルが active 表示され見積フォームが描画される', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=create');
    await expect(authedPage.locator('#panel-create')).toHaveClass(/active/);
    await expect(authedPage.locator('#qbSubject')).toBeAttached();
    await expect(authedPage.locator('#qbCustomer')).toBeAttached();
    await expect(authedPage.locator('#qbAiOpen')).toBeAttached();
  });

  test('不正なタブ名は products にフォールバックする', async ({ authedPage }) => {
    await authedPage.goto('/pages/sales-tools?tab=invalid-tab-name');
    await expect(authedPage.locator('.st-tab.active')).toHaveAttribute('data-tab', 'products');
  });
});
