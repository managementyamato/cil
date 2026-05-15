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
    const csrf = await authedPage.evaluate(() => {
      return document.cookie.match(/csrf_token=([^;]+)/)?.[1] ?? '';
    });
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
