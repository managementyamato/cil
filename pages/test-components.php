<?php
/**
 * 共通コンポーネントのテストページ
 * 作成日: 2026-02-09
 */

require_once __DIR__ . '/../api/auth.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../functions/header.php';
?>

<div         class="container max-w-1200 mx-auto my-2 px-1">
    <div class="card">
        <div class="card-header">
            <h2>共通コンポーネント テストページ</h2>
        </div>
        <div class="card-body">
            <p       class="text-gray-500" class="mb-4">
                新しく作成した共通コンポーネント（CSS・JavaScript・SVGアイコン）の動作確認ページです。
            </p>

            <!-- ボタンコンポーネント -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">ボタン (.btn)</h3>
                <div    class="d-flex flex-wrap gap-075">
                    <button class="btn btn-primary">Primary</button>
                    <button class="btn btn-secondary">Secondary</button>
                    <button class="btn btn-success">Success</button>
                    <button class="btn btn-danger">Danger</button>
                    <button class="btn btn-warning">Warning</button>
                    <button class="btn btn-outline">Outline</button>
                    <button class="btn btn-primary btn-sm">Small</button>
                    <button class="btn btn-primary btn-lg">Large</button>
                    <button class="btn btn-primary" disabled>Disabled</button>
                </div>
            </section>

            <!-- アイコンボタン -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">アイコンボタン (.btn-icon)</h3>
                <div  class="d-flex gap-1 flex-wrap">
                    <button class="btn-icon" title="編集" id="testEditBtn">
                        <script<?= nonceAttr() ?>>document.getElementById('testEditBtn').innerHTML = getIcon('edit');</script>
                    </button>
                    <button class="btn-icon danger" title="削除" id="testDeleteBtn">
                        <script<?= nonceAttr() ?>>document.getElementById('testDeleteBtn').innerHTML = getIcon('delete');</script>
                    </button>
                    <button class="btn-icon success" title="確認" id="testCheckBtn">
                        <script<?= nonceAttr() ?>>document.getElementById('testCheckBtn').innerHTML = getIcon('check');</script>
                    </button>
                    <button class="btn-icon" title="設定" id="testSettingsBtn">
                        <script<?= nonceAttr() ?>>document.getElementById('testSettingsBtn').innerHTML = getIcon('settings');</script>
                    </button>
                    <button class="btn-icon" title="検索" id="testSearchBtn">
                        <script<?= nonceAttr() ?>>document.getElementById('testSearchBtn').innerHTML = getIcon('search');</script>
                    </button>
                </div>
            </section>

            <!-- アラート -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">アラート (.alert)</h3>
                <div class="alert alert-success">
                    <div class="alert-icon">✓</div>
                    <div class="alert-content">
                        <div class="alert-title">成功</div>
                        データが正常に保存されました。
                    </div>
                </div>
                <div class="alert alert-danger">
                    <div class="alert-icon">✕</div>
                    <div class="alert-content">
                        <div class="alert-title">エラー</div>
                        入力内容に誤りがあります。
                    </div>
                </div>
                <div class="alert alert-warning">
                    <div class="alert-icon">⚠</div>
                    <div class="alert-content">
                        <div class="alert-title">警告</div>
                        この操作は取り消せません。
                    </div>
                </div>
                <div class="alert alert-info">
                    <div class="alert-icon">ℹ</div>
                    <div class="alert-content">
                        <div class="alert-title">情報</div>
                        システムメンテナンスのお知らせ。
                    </div>
                </div>
            </section>

            <!-- フォーム -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">フォーム (.form-*)</h3>
                <div   class="max-w-500">
                    <div class="form-group">
                        <label class="form-label required">名前</label>
                        <input type="text" class="form-input" placeholder="山田太郎" id="testNameInput">
                    </div>
                    <div class="form-group">
                        <label class="form-label">メールアドレス</label>
                        <input type="email" class="form-input" placeholder="example@example.com" id="testEmailInput">
                        <span class="form-help">会社のメールアドレスを入力してください</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">コメント</label>
                        <textarea class="form-textarea" rows="3" placeholder="コメントを入力..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">役職</label>
                        <select class="form-select">
                            <option value="">選択してください</option>
                            <option value="sales">営業部</option>
                            <option value="product">製品管理部</option>
                            <option value="admin">管理部</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" id="testValidationBtn">バリデーションテスト</button>
                </div>
            </section>

            <!-- バッジ -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">バッジ (.badge)</h3>
                <div    class="d-flex flex-wrap gap-075">
                    <span class="badge badge-primary">Primary</span>
                    <span class="badge badge-success">Success</span>
                    <span class="badge badge-danger">Danger</span>
                    <span class="badge badge-warning">Warning</span>
                    <span class="badge badge-gray">Gray</span>
                </div>
            </section>

            <!-- テーブル -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">データテーブル (.data-table)</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名前</th>
                            <th>部署</th>
                            <th>ステータス</th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>001</td>
                            <td>山田太郎</td>
                            <td>営業部</td>
                            <td><span class="badge badge-success">有効</span></td>
                            <td>
                                <div class="data-table-actions">
                                    <button class="btn-icon" title="編集" id="editBtn1">
                                        <script<?= nonceAttr() ?>>document.getElementById('editBtn1').innerHTML = getIcon('edit');</script>
                                    </button>
                                    <button class="btn-icon danger" title="削除" id="deleteBtn1">
                                        <script<?= nonceAttr() ?>>document.getElementById('deleteBtn1').innerHTML = getIcon('delete');</script>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr class="active">
                            <td>002</td>
                            <td>佐藤花子</td>
                            <td>製品管理部</td>
                            <td><span class="badge badge-primary">選択中</span></td>
                            <td>
                                <div class="data-table-actions">
                                    <button class="btn-icon" title="編集" id="editBtn2">
                                        <script<?= nonceAttr() ?>>document.getElementById('editBtn2').innerHTML = getIcon('edit');</script>
                                    </button>
                                    <button class="btn-icon danger" title="削除" id="deleteBtn2">
                                        <script<?= nonceAttr() ?>>document.getElementById('deleteBtn2').innerHTML = getIcon('delete');</script>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>003</td>
                            <td>鈴木一郎</td>
                            <td>管理部</td>
                            <td><span class="badge badge-success">有効</span></td>
                            <td>
                                <div class="data-table-actions">
                                    <button class="btn-icon" title="編集" id="editBtn3">
                                        <script<?= nonceAttr() ?>>document.getElementById('editBtn3').innerHTML = getIcon('edit');</script>
                                    </button>
                                    <button class="btn-icon danger" title="削除" id="deleteBtn3">
                                        <script<?= nonceAttr() ?>>document.getElementById('deleteBtn3').innerHTML = getIcon('delete');</script>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- モーダル -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">モーダル (.modal)</h3>
                <button class="btn btn-primary" id="openTestModalBtn">モーダルを開く</button>
            </section>

            <!-- JavaScript関数テスト -->
            <section   class="mb-4">
                <h3    class="mb-2 text-gray-700">JavaScript関数テスト</h3>
                <div    class="d-flex flex-wrap gap-075">
                    <button class="btn btn-primary toast-success-btn">成功トースト</button>
                    <button class="btn btn-danger toast-danger-btn">エラートースト</button>
                    <button class="btn btn-warning toast-warning-btn">警告トースト</button>
                    <button class="btn btn-secondary test-formatting-btn">フォーマット関数</button>
                </div>
            </section>

            <!-- ローディング -->
            <section>
                <h3    class="mb-2 text-gray-700">ローディング (.spinner)</h3>
                <div  class="d-flex gap-2 align-center">
                    <div class="spinner"></div>
                    <div class="spinner-lg"></div>
                    <button class="btn btn-primary"><span class="spinner"></span> 読み込み中...</button>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- テストモーダル -->
<div id="testModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>テストモーダル</h3>
            <button type="button" class="modal-close close-test-modal-btn">&times;</button>
        </div>
        <div class="modal-body">
            <p>共通モーダルコンポーネントのテストです。</p>
            <p>外側をクリックするか、×ボタンで閉じることができます。</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-test-modal-btn">キャンセル</button>
            <button type="button" class="btn btn-primary close-test-modal-btn">OK</button>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
// モーダルの外側クリックで閉じる
setupModalClickOutside('testModal');

// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // バリデーションテストボタン
    const testValidationBtn = document.getElementById('testValidationBtn');
    if (testValidationBtn) {
        testValidationBtn.addEventListener('click', testValidation);
    }

    // モーダルを開くボタン
    const openTestModalBtn = document.getElementById('openTestModalBtn');
    if (openTestModalBtn) {
        openTestModalBtn.addEventListener('click', function() {
            openModal('testModal');
        });
    }

    // モーダルを閉じるボタン
    document.querySelectorAll('.close-test-modal-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal('testModal');
        });
    });

    // トーストボタン
    const toastSuccessBtn = document.querySelector('.toast-success-btn');
    if (toastSuccessBtn) {
        toastSuccessBtn.addEventListener('click', function() {
            testToast('success');
        });
    }

    const toastDangerBtn = document.querySelector('.toast-danger-btn');
    if (toastDangerBtn) {
        toastDangerBtn.addEventListener('click', function() {
            testToast('danger');
        });
    }

    const toastWarningBtn = document.querySelector('.toast-warning-btn');
    if (toastWarningBtn) {
        toastWarningBtn.addEventListener('click', function() {
            testToast('warning');
        });
    }

    // フォーマット関数テストボタン
    const testFormattingBtn = document.querySelector('.test-formatting-btn');
    if (testFormattingBtn) {
        testFormattingBtn.addEventListener('click', testFormatting);
    }
});

// バリデーションテスト
function testValidation() {
    const nameInput = document.getElementById('testNameInput');
    const emailInput = document.getElementById('testEmailInput');

    clearFieldError('testNameInput');
    clearFieldError('testEmailInput');

    let hasError = false;

    if (!validateRequired(nameInput.value)) {
        showFieldError('testNameInput', '名前は必須です');
        hasError = true;
    }

    if (emailInput.value && !validateEmail(emailInput.value)) {
        showFieldError('testEmailInput', '有効なメールアドレスを入力してください');
        hasError = true;
    }

    if (!hasError) {
        showToast('バリデーション成功！', 'success');
    }
}

// トーストテスト
function testToast(type) {
    const messages = {
        'success': '操作が成功しました',
        'danger': 'エラーが発生しました',
        'warning': '注意が必要です',
        'info': '情報メッセージ'
    };
    showToast(messages[type] || messages.info, type);
}

// フォーマット関数テスト
function testFormatting() {
    const results = [
        `formatNumber(1234567): ${formatNumber(1234567)}`,
        `formatCurrency(1234567): ${formatCurrency(1234567)}`,
        `formatDateJP(new Date()): ${formatDateJP(new Date())}`,
        `formatDateTimeJP(new Date()): ${formatDateTimeJP(new Date())}`
    ];
    alert(results.join('\n'));
}
</script>

<?php require_once __DIR__ . '/../functions/footer.php'; ?>
