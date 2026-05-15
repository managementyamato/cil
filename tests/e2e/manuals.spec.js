// @ts-check
const { test, expect } = require('./fixtures');

test.describe('マニュアル一覧 - スモーク', () => {
  test('ページが開き「マニュアル一覧」h2 が表示される', async ({ authedPage }) => {
    await authedPage.goto('/pages/manuals');
    await expect(authedPage.locator('.manuals-header h2')).toContainText('マニュアル一覧');
  });

  test('検索インプットが存在する', async ({ authedPage }) => {
    await authedPage.goto('/pages/manuals');
    await expect(authedPage.locator('.search-hero input.search-input')).toBeVisible();
  });

  test('manuals-api list が 200 を返す', async ({ authedPage }) => {
    await authedPage.goto('/pages/manuals');
    const res = await authedPage.request.get('/api/manuals-api.php?action=list');
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('success', true);
  });

  test('ダッシュボードにリダイレクトされない', async ({ authedPage }) => {
    const res = await authedPage.goto('/pages/manuals');
    expect(res.url()).toContain('/pages/manuals');
    expect(res.url()).not.toContain('index');
    const body = await authedPage.content();
    expect(body).not.toContain('<h2>ダッシュボード</h2>');
  });
});
