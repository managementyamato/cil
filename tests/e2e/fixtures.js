// @ts-check
const { test: base } = require('@playwright/test');

/**
 * 認証済み page を提供する fixture
 *
 * test-login.php はローカル/開発環境専用のバックドア。
 * Google OAuth を介さずに任意のメールアドレスでセッション確立できる。
 */
const test = base.extend({
  authedPage: async ({ page }, use) => {
    // テストログインで管理者セッションを確立
    const res = await page.request.get(
      '/pages/test-login.php?email=managementsupport@yamato-agency.com&format=json'
    );
    if (!res.ok()) {
      throw new Error(
        `test-login failed: ${res.status()} ${await res.text()}`
      );
    }
    const body = await res.json();
    if (!body.success) {
      throw new Error('test-login returned success=false');
    }
    await use(page);
  },
});

module.exports = { test, expect: require('@playwright/test').expect };
