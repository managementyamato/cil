// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * YA管理一覧 E2E テスト設定
 *
 * 実行前提:
 *   1. ローカル PHP サーバーが localhost:8000 で起動中
 *      (scripts/php.exe -S localhost:8000 router.php)
 *   2. ローカル MySQL に adyamato_gear DB が用意済み
 *      (docs/local-setup.md 参照)
 *   3. .env.local の DB_MODE=dual
 *
 * 実行コマンド:
 *   npm test              # ヘッドレス実行
 *   npm run test:headed   # ブラウザ表示で実行
 *   npm run test:ui       # UI モード
 *   npm run test:debug    # デバッグモード
 */
module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  fullyParallel: false,           // セッション干渉を避けるため直列
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,                      // セッション干渉を避けるため1並列
  reporter: process.env.CI ? 'github' : 'list',

  use: {
    baseURL: 'http://localhost:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // ローカル PHP サーバーは別途立ち上げる前提。
  // CI/CD ではここで起動する設定を入れる余地あり。
  // webServer: {
  //   command: 'scripts/php.exe -S localhost:8000 router.php',
  //   url: 'http://localhost:8000',
  //   reuseExistingServer: !process.env.CI,
  // },
});
