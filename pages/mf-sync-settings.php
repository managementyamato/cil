<?php
require_once '../api/auth.php';

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$configFile = __DIR__ . '/../config/mf-sync-config.json';

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 設定を保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sync_settings'])) {
    $targetMonth = trim($_POST['target_month'] ?? '');

    // 年月の形式をチェック (YYYY-MM)
    if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        $error = '年月の形式が正しくありません（YYYY-MM形式で入力してください）';
    } else {
        $config = [
            'target_month' => $targetMonth,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        header('Location: mf-sync-settings.php?saved=1');
        exit;
    }
}

// 設定を読み込み
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?? [];
}

$targetMonth = $config['target_month'] ?? date('Y-m');

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
}

.form-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 1rem;
}

.form-help {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--gray-600);
}

.info-box {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
}

.info-box p {
    margin: 0;
    color: #1e40af;
}

.sync-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.sync-card h3 {
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
    color: var(--gray-800);
}

.sync-result {
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 6px;
    display: none;
}

.sync-result.success {
    background: #dcfce7;
    color: #166534;
}

.sync-result.error {
    background: #fef2f2;
    color: #dc2626;
}

.sync-result.loading {
    background: #f3f4f6;
    color: var(--gray-600);
    display: block;
}

.month-quick-select {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.month-quick-select button {
    padding: 0.375rem 0.75rem;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 4px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.15s;
}

.month-quick-select button:hover {
    background: var(--gray-50);
    border-color: var(--primary);
}

.month-quick-select button.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">
        同期設定を保存しました。次回の同期から適用されます。
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- 今すぐ同期 -->
<div class="sync-card">
    <h3>🔄 今すぐ同期</h3>
    <p    class="mb-2 text-gray-600">指定した月のMF請求書を同期します。</p>

    <div   class="form-group mb-2">
        <label class="form-label">同期する月を選択</label>
        <input
            type="month"
            class="form-input"
            id="sync_month"
            value="<?= htmlspecialchars($targetMonth) ?>"
            class="max-w-200"
        >
        <div class="month-quick-select">
            <?php
            // 直近6ヶ月のボタンを生成
            for ($i = 0; $i < 6; $i++):
                $m = date('Y-m', strtotime("-{$i} month"));
                $label = date('Y年n月', strtotime("-{$i} month"));
                $isActive = ($m === $targetMonth) ? 'active' : '';
            ?>
                <button type="button" class="<?= $isActive ?>" data-month="<?= $m ?>"><?= $label ?></button>
            <?php endfor; ?>
        </div>
    </div>

    <button type="button" class="btn btn-primary" id="syncBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05">
            <path d="M23 4v6h-6M1 20v-6h6"/>
            <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
        </svg>
        今すぐ同期
    </button>

    <div id="syncResult" class="sync-result"></div>
</div>

<div class="card">
    <div class="card-header">
        <h2  class="m-0">MF請求書 同期設定</h2>
    </div>
    <div class="card-body">
        <div class="info-box">
            <p>
                <strong>⚠️ 注意:</strong> 指定した月の請求書のみを同期します。
                請求日を基準に、その月に請求された請求書が対象となります。
            </p>
        </div>

        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <div class="form-group">
                <label class="form-label" for="target_month">
                    デフォルト同期対象月
                </label>
                <input
                    type="month"
                    class="form-input"
                    id="target_month"
                    name="target_month"
                    value="<?= htmlspecialchars($targetMonth) ?>"
                    required
                    class="max-w-250"
                >
                <div class="form-help">
                    同期時にデフォルトで使用される月（請求日を基準）
                </div>
            </div>

            <div        class="d-flex gap-1 mt-4">
                <button type="submit" name="save_sync_settings" class="btn btn-primary">
                    設定を保存
                </button>
                <a href="settings.php" class="btn btn-secondary">
                    戻る
                </a>
            </div>
        </form>

        <?php if (!empty($config)): ?>
            <div     class="mt-4 border-top-gray-200">
                <h3    class="text-base mb-1 text-gray-700">現在の設定</h3>
                <p    class="m-0 text-gray-600">
                    デフォルト同期対象: <strong><?= date('Y年n月', strtotime($targetMonth . '-01')) ?></strong><br>
                    <?php if (isset($config['updated_at'])): ?>
                        最終更新: <?= htmlspecialchars($config['updated_at']) ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';

function selectMonth(month) {
    document.getElementById('sync_month').value = month;

    // ボタンのアクティブ状態を更新
    document.querySelectorAll('.month-quick-select button').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.includes(formatMonth(month))) {
            btn.classList.add('active');
        }
    });
}

function formatMonth(ym) {
    const [y, m] = ym.split('-');
    return y + '年' + parseInt(m) + '月';
}

async function syncNow() {
    const month = document.getElementById('sync_month').value;
    if (!month) {
        alert('同期する月を選択してください');
        return;
    }

    const btn = document.getElementById('syncBtn');
    const result = document.getElementById('syncResult');

    btn.disabled = true;
    btn.textContent = '同期中...';
    result.className = 'sync-result loading';
    result.textContent = '同期中です。しばらくお待ちください...';
    result.style.display = 'block';

    try {
        const response = await fetch('/api/sync-invoices.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: 'target_month=' + encodeURIComponent(month)
        });

        const data = await response.json();

        if (data.success) {
            result.className = 'sync-result success';
            result.innerHTML = '<strong>✓ ' + escapeHtml(data.message) + '</strong>';
            if (data.period) {
                result.innerHTML += '<br><small>期間: ' + escapeHtml(data.period.from) + ' 〜 ' + escapeHtml(data.period.to) + '</small>';
            }
        } else {
            result.className = 'sync-result error';
            result.textContent = '❌ エラー: ' + (data.error || '同期に失敗しました');
        }
    } catch (e) {
        result.className = 'sync-result error';
        result.textContent = '❌ エラー: ' + e.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>今すぐ同期';
    }
}
</script>

<script<?= nonceAttr() ?>>
// 月選択ボタン
document.querySelectorAll('.month-quick-select button[data-month]').forEach(btn => {
    btn.addEventListener('click', function() {
        const month = this.getAttribute('data-month');
        if (month) {
            selectMonth(month);
        }
    });
});

// 同期実行ボタン
document.getElementById('syncBtn')?.addEventListener('click', syncNow);
</script>

<?php require_once '../functions/footer.php'; ?>
