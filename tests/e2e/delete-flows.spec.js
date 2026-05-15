// @ts-check
const { test, expect } = require('./fixtures');

/**
 * 削除フローの E2E テスト
 *
 * 過去事故:
 *   - <form> ネストでチェックボックスが孤児化
 *   - 生 SQL で DB 不存在エラー → リダイレクトでダッシュボードへ迷子
 *   - 削除ボタンにクラス無し
 *
 * これらを検出するためのテスト群。
 */

test.describe('削除ボタンの統一性', () => {
  test('トラブル: bulkDeleteForm がテーブル外に独立配置されている (nested form 防止)', async ({ authedPage }) => {
    await authedPage.goto('/pages/troubles');

    // form が table の中にあれば ネストしている可能性大
    const formInsideTable = await authedPage
      .locator('#troubleTable #bulkDeleteForm')
      .count();
    expect(formInsideTable).toBe(0);

    // form は存在し、checkbox の数だけ trouble_ids[] 要素が動的に追加される構造
    await expect(authedPage.locator('#bulkDeleteForm')).toBeAttached();
    await expect(authedPage.locator('#bulkDeleteIdsContainer')).toBeAttached();
  });

  test('トラブル: 一括削除で 0件選択時にバリデーション alert が出る', async ({ authedPage }) => {
    await authedPage.goto('/pages/troubles');

    let alertMessage = null;
    authedPage.on('dialog', async (dialog) => {
      alertMessage = dialog.message();
      await dialog.dismiss();
    });

    // チェックボックス無しで一括削除モーダルバーは出ないが、念のため直接呼ぶ
    await authedPage.evaluate(() => {
      if (typeof bulkDelete === 'function') bulkDelete();
    });

    expect(alertMessage).toContain('削除する項目を選択');
  });

  test('トラブル: 削除ハンドラがダッシュボードにリダイレクトしない', async ({ authedPage }) => {
    await authedPage.goto('/pages/troubles');

    const action = await authedPage.locator('#bulkDeleteForm').getAttribute('action');
    const csrf = await authedPage
      .locator('#bulkDeleteForm input[name="csrf_token"]')
      .getAttribute('value');

    // 存在しない ID を送信 → bulk_deleted=0 にリダイレクトされるべき
    const response = await authedPage.request.post(action, {
      form: {
        csrf_token: csrf,
        bulk_delete: '1',
        'trouble_ids[]': '999999999',
      },
    });

    expect(response.ok()).toBeTruthy();
    expect(response.url()).toMatch(/\/pages\/troubles\?bulk_deleted=0/);
    expect(response.url()).not.toContain('error=');

    // ダッシュボード（index.php）にリダイレクトされていないことを確認
    const body = await response.text();
    expect(body).toContain('トラブル対応一覧');
    expect(body).not.toContain('<h2>ダッシュボード</h2>');
  });

  test('トラブル: ステータス変更 select が data-trouble-id 属性を持つ (nested form 解消の証拠)', async ({ authedPage }) => {
    await authedPage.goto('/pages/troubles');
    const selects = authedPage.locator('select.status-select');
    const count = await selects.count();
    if (count > 0) {
      const first = selects.first();
      await expect(first).toHaveAttribute('data-trouble-id', /.+/);

      // 旧式の <form method="POST"> でラップされていないこと
      const wrappedInForm = await first.evaluate((el) =>
        el.closest('form')?.id === 'bulkDeleteForm' || el.closest('form') === null
      );
      expect(wrappedInForm).toBe(true);
    }
  });

  test('IP削除ボタンに btn-icon danger クラスが付いている', async ({ authedPage }) => {
    await authedPage.goto('/pages/integration-settings');

    // テスト用 IP を追加
    const csrf = await authedPage
      .locator('input[name="csrf_token"]')
      .first()
      .getAttribute('value');
    await authedPage.request.post('/pages/integration-settings', {
      form: { csrf_token: csrf, add_ip: '1', ip_address: '192.0.2.77' },
    });
    await authedPage.reload();

    const deleteBtn = authedPage.locator('button[name="delete_ip"]').first();
    await expect(deleteBtn).toBeVisible();
    await expect(deleteBtn).toHaveClass(/btn-icon/);
    await expect(deleteBtn).toHaveClass(/danger/);

    // 親 form に delete-ip-form クラス（confirm ハンドラ接続用）
    const parentForm = deleteBtn.locator('xpath=ancestor::form[1]');
    await expect(parentForm).toHaveClass(/delete-ip-form/);

    // 後始末: 追加した IP を削除
    await authedPage.evaluate(() => {
      window.confirm = () => true;
      document.querySelector('.delete-ip-form button[name="delete_ip"]').click();
    });
  });
});

test.describe('削除挙動の機能テスト', () => {
  test('トラブル: 1件選択 → 一括削除 → DBから論理削除されている', async ({ authedPage }) => {
    await authedPage.goto('/pages/troubles');

    // 現在の件数を取得
    const initialCount = await authedPage.locator('.trouble-checkbox').count();
    if (initialCount === 0) {
      test.skip(true, 'no trouble rows to delete');
    }

    // 一番上の trouble の ID を取得
    const targetId = await authedPage
      .locator('.trouble-checkbox')
      .first()
      .getAttribute('value');

    // フォームを直接 POST（ボタン経由だと confirm の自動化が必要）
    const csrf = await authedPage
      .locator('#bulkDeleteForm input[name="csrf_token"]')
      .getAttribute('value');
    const action = await authedPage
      .locator('#bulkDeleteForm')
      .getAttribute('action');

    const response = await authedPage.request.post(action, {
      form: { csrf_token: csrf, bulk_delete: '1', 'trouble_ids[]': targetId },
    });
    expect(response.url()).toMatch(/bulk_deleted=1/);

    // ページ再読込で件数が減っているか
    await authedPage.goto('/pages/troubles');
    const afterCount = await authedPage.locator('.trouble-checkbox').count();
    expect(afterCount).toBe(initialCount - 1);
  });
});
