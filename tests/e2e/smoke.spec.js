// @ts-check
const { test, expect } = require('./fixtures');

/**
 * スモーク: 主要ページが正常表示されることを確認
 * これだけでもデプロイ前に走らせれば「画面が真っ白」「ダッシュボードに飛ばされる」を検出できる
 */

test.describe('smoke - 主要ページの応答確認', () => {
  test('トラブル対応一覧が開ける', async ({ authedPage }) => {
    await authedPage.goto('/pages/troubles');
    await expect(authedPage.locator('h2')).toContainText('トラブル対応一覧');
    await expect(authedPage.locator('#troubleTable')).toBeVisible();
  });

  test('プロジェクト管理が開ける', async ({ authedPage }) => {
    await authedPage.goto('/pages/master');
    // メインコンテンツ領域に「プロジェクト管理」または「案件」関連表示があること
    await expect(authedPage.locator('main, .page-container').first()).toBeVisible();
    // ダッシュボード固有要素（KPIカード）が無いこと = ダッシュボードに迷子していない
    await expect(authedPage.locator('.kpi-card')).toHaveCount(0);
  });

  test('顧客マスタが開ける', async ({ authedPage }) => {
    await authedPage.goto('/pages/customers');
    await expect(authedPage.locator('h2').first()).toBeVisible();
  });

  test('マスタ管理が開ける', async ({ authedPage }) => {
    await authedPage.goto('/pages/masters');
    await expect(authedPage.locator('h2').first()).toBeVisible();
  });

  test('API連携設定が開ける', async ({ authedPage }) => {
    await authedPage.goto('/pages/integration-settings');
    await expect(authedPage.locator('body')).toContainText('API連携設定');
  });
});
