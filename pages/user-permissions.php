<?php
/**
 * ユーザー権限設定ページ
 * 各アカウントの閲覧・編集権限を管理します
 */
require_once '../api/auth.php';

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$data = getData();

$message = '';
$messageType = '';

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// ページ権限設定ファイル
$pagePermissionsFile = __DIR__ . '/../config/page-permissions.json';

// デフォルトのページ権限（閲覧/編集）
$defaultPagePermissions = [
    'index.php' => ['view' => 'sales', 'edit' => 'product'],
    'master.php' => ['view' => 'product', 'edit' => 'product'],
    'troubles.php' => ['view' => 'sales', 'edit' => 'product'],
    'finance.php' => ['view' => 'product', 'edit' => 'product'],
    'loans.php' => ['view' => 'product', 'edit' => 'product'],
    'payroll-journal.php' => ['view' => 'product', 'edit' => 'product'],
    'photo-attendance.php' => ['view' => 'product', 'edit' => 'product'],
    'masters.php' => ['view' => 'product', 'edit' => 'product'],
    'contacts.php' => ['view' => 'sales', 'edit' => 'admin'],
    'company-rules.php' => ['view' => 'sales', 'edit' => 'admin'],
    'slides.php' => ['view' => 'sales', 'edit' => 'sales'],
    'settings.php' => ['view' => 'admin', 'edit' => 'admin'],
    'reports-hub.php' => ['view' => 'sales', 'edit' => 'sales'],
    'invoice-confirm.php' => ['view' => 'product', 'edit' => 'product'],
    'invoice-requests.php' => ['view' => 'admin', 'edit' => 'admin'],
    'custom-invoice-list.php' => ['view' => 'admin', 'edit' => 'admin'],
    'custom-invoice-create.php' => ['view' => 'admin', 'edit' => 'admin'],
    'custom-invoice-manual.php' => ['view' => 'admin', 'edit' => 'admin'],
    'cms-news.php' => ['view' => 'admin', 'edit' => 'admin'],
    'cms-settings.php' => ['view' => 'admin', 'edit' => 'admin'],
    'cms-templates.php' => ['view' => 'admin', 'edit' => 'admin'],
];

// ページ名の日本語ラベル
$pageLabels = [
    'index.php' => 'ダッシュボード',
    'master.php' => 'プロジェクト管理',
    'troubles.php' => 'トラブル対応',
    'finance.php' => '売上管理',
    'loans.php' => '借入金管理',
    'payroll-journal.php' => '給与仕訳',
    'photo-attendance.php' => 'アルコールチェック',
    'masters.php' => 'マスタ管理',
    'contacts.php' => '社内連絡先',
    'company-rules.php' => '社内規則',
    'slides.php' => '社内マニュアル',
    'settings.php' => '設定',
    'reports-hub.php' => '申請・報告',
    'invoice-confirm.php' => '請求書確認',
    'invoice-requests.php' => '請求書作成依頼',
    'custom-invoice-list.php' => '指定請求書一覧',
    'custom-invoice-create.php' => '指定請求書作成',
    'custom-invoice-manual.php' => '指定請求書マニュアル',
    'cms-news.php' => 'HP更新（お知らせ）',
    'cms-settings.php' => 'HP更新 設定',
    'cms-templates.php' => 'HP更新 テンプレート',
];

// ページ権限を読み込み
function loadPagePermissionsEx($file, $defaults) {
    if (file_exists($file)) {
        $saved = json_decode(file_get_contents($file), true);
        if ($saved && isset($saved['permissions'])) {
            // 新形式（view/edit分離）と旧形式の互換性を保つ
            $merged = $defaults;
            foreach ($saved['permissions'] as $page => $perm) {
                if (is_array($perm)) {
                    $merged[$page] = $perm;
                } else {
                    // 旧形式：単一の権限を閲覧権限として扱い、編集は1段階上
                    $merged[$page] = [
                        'view' => $perm,
                        'edit' => $perm === 'sales' ? 'product' : ($perm === 'product' ? 'product' : 'admin')
                    ];
                }
            }
            return $merged;
        }
    }
    return $defaults;
}

// ページ権限を保存
function savePagePermissionsEx($file, $permissions) {
    $data = [
        'permissions' => $permissions,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

$pagePermissions = loadPagePermissionsEx($pagePermissionsFile, $defaultPagePermissions);

// 権限の説明
$roleDescriptions = [
    'admin' => [
        'label' => '管理部',
        'description' => 'すべての機能にアクセス可能（設定変更含む）',
        'color' => '#dbeafe',
        'textColor' => '#1e40af'
    ],
    'product' => [
        'label' => '製品技術部',
        'description' => 'データの閲覧・編集が可能（設定変更は不可）',
        'color' => '#d1fae5',
        'textColor' => '#065f46'
    ],
    'sales' => [
        'label' => '営業部',
        'description' => '限定的なデータ閲覧・写真アップロードのみ',
        'color' => '#fef3c7',
        'textColor' => '#92400e'
    ]
];

// 単一ユーザー権限更新（AJAX用）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_single'])) {
    $empKey = $_POST['emp_key'] ?? '';
    $newRole = $_POST['role'] ?? '';

    // ★ 安全策: 保存前に employees の件数を必ず確認。
    //   もし $data['employees'] が空 or 件数が極端に少ない場合は保存を拒否。
    //   これがないと saveEntityUpsert が「DBの全行削除」を実行してしまう (重大事故)。
    if (empty($data['employees']) || !is_array($data['employees']) || count($data['employees']) < 1) {
        error_log('user-permissions update_single: $data[employees] が空。保存をキャンセル');
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'データ整合性エラー: 従業員一覧が空。管理者に連絡してください。']);
        exit;
    }

    $matched = false;
    foreach ($data['employees'] as $key => $employee) {
        $currentKey = $employee['code'] ?? $employee['id'] ?? $key;
        if ($currentKey == $empKey) {
            if (!empty($newRole)) {
                $data['employees'][$key]['role'] = $newRole;
            } else {
                unset($data['employees'][$key]['role']);
            }
            // ★ entitiesFilter を指定して employees のみ保存。
            //   無指定だと weekly_reports 等の巨大テーブルへの不要書き込み +
            //   万が一どこかでエラー出ても影響範囲が他テーブルに広がらない。
            saveData($data, ['employees']);
            $matched = true;
            break;
        }
    }

    header('Content-Type: application/json');
    if ($matched) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ユーザーが見つかりません']);
    }
    exit;
}

// ページ権限更新（AJAX用）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_page_permission'])) {
    $page = $_POST['page'] ?? '';
    $permType = $_POST['perm_type'] ?? 'view'; // 'view' or 'edit'
    $newRole = $_POST['role'] ?? '';

    if (isset($pageLabels[$page]) && in_array($newRole, ['sales', 'product', 'admin']) && in_array($permType, ['view', 'edit'])) {
        if (!isset($pagePermissions[$page]) || !is_array($pagePermissions[$page])) {
            $pagePermissions[$page] = ['view' => 'sales', 'edit' => 'product'];
        }
        $pagePermissions[$page][$permType] = $newRole;

        // 編集権限は閲覧権限以上でなければならない
        $roleLevel = ['sales' => 1, 'product' => 2, 'admin' => 3];
        if ($roleLevel[$pagePermissions[$page]['edit']] < $roleLevel[$pagePermissions[$page]['view']]) {
            $pagePermissions[$page]['edit'] = $pagePermissions[$page]['view'];
        }

        savePagePermissionsEx($pagePermissionsFile, $pagePermissions);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'permissions' => $pagePermissions[$page]]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '無効なページまたは権限です']);
    exit;
}

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>

.role-legend {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.role-legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.perm-role-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 500;
    border: none;
}

.role-description {
    font-size: 0.8rem;
    color: #6b7280;
}

.permissions-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.permissions-table th {
    background: #f3f4f6;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.permissions-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.permissions-table tr:hover {
    background: #f9fafb;
}

.permissions-table tr:last-child td {
    border-bottom: none;
}

.perm-user-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.perm-user-name {
    font-weight: 500;
    color: #111827;
}

.perm-user-email {
    font-size: 0.8rem;
    color: #6b7280;
}

.role-select {
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 0.875rem;
    min-width: 150px;
    cursor: pointer;
}

.role-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.role-select.admin {
    border-color: #3b82f6;
    background: #eff6ff;
}

.role-select.product {
    border-color: #10b981;
    background: #ecfdf5;
}

.role-select.sales {
    border-color: #f59e0b;
    background: #fffbeb;
}

.no-email {
    color: #9ca3af;
    font-style: italic;
}

.save-indicator {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 4px;
    margin-left: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s;
}

.save-indicator.saving {
    background: #fef3c7;
    color: #92400e;
    opacity: 1;
}

.save-indicator.saved {
    background: #d1fae5;
    color: #065f46;
    opacity: 1;
}

.page-matrix {
    margin-top: 2rem;
}

.matrix-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.matrix-table th,
.matrix-table td {
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    text-align: center;
}

.matrix-table th {
    background: #f3f4f6;
    font-weight: 600;
}

.matrix-table td:first-child {
    text-align: left;
    font-weight: 500;
}

.matrix-select {
    padding: 0.25rem 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    min-width: 100px;
}

.matrix-select.sales {
    border-color: #f59e0b;
    background: #fffbeb;
}

.matrix-select.product {
    border-color: #10b981;
    background: #ecfdf5;
}

.matrix-select.admin {
    border-color: #3b82f6;
    background: #eff6ff;
}

.page-save-indicator {
    display: inline-block;
    font-size: 0.7rem;
    margin-left: 0.25rem;
    opacity: 0;
    transition: opacity 0.3s;
}

.page-save-indicator.saved {
    color: #059669;
    opacity: 1;
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-title h3 {
    margin: 0;
}

.help-text {
    font-size: 0.8rem;
    color: #6b7280;
}

.access-yes {
    color: #059669;
    font-weight: bold;
}

.access-no {
    color: #dc2626;
}

.perm-header {
    font-size: 0.7rem;
    color: #6b7280;
    font-weight: normal;
}

.perm-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.perm-label {
    font-size: 0.7rem;
    color: #6b7280;
}
</style>

<div class="page-container">
<div class="page-header">
    <h2>設定</h2>
    <a href="settings.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
</div>
<div class="settings-detail-header">
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        アカウント権限設定
    </h2>
</div>

<div class="card">
    <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'info' ? 'info' : 'error') ?> mb-2">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- 権限レベルの説明 -->
            <div class="role-legend">
                <?php foreach ($roleDescriptions as $role => $info): ?>
                <div class="role-legend-item">
                    <span         class="perm-role-badge" style="background: <?= htmlspecialchars($info['color'], ENT_QUOTES) ?>; color: <?= htmlspecialchars($info['textColor'], ENT_QUOTES) ?>">
                        <?= $info['label'] ?>
                    </span>
                    <span class="role-description"><?= $info['description'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ユーザー一覧 -->
            <form method="POST" id="permissionsForm">
                <?= csrfTokenField() ?>
                <table class="permissions-table">
                    <thead>
                        <tr>
                            <th   class="w-50">No.</th>
                            <th>ユーザー</th>
                            <th    class="w-120">社員コード</th>
                            <th    class="w-200">権限</th>
                            <th   class="w-100">状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['employees'])): ?>
                            <tr>
                                <td colspan="5"        class="text-center text-gray-500 p-2rem">
                                    登録されているユーザーがいません。<br>
                                    <a href="employees.php">従業員マスタ</a>から従業員を登録してください。
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data['employees'] as $index => $employee):
                                $empKey = $employee['code'] ?? $employee['id'] ?? $index;
                                $currentRole = $employee['role'] ?? '';
                            ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="perm-user-info">
                                        <span class="perm-user-name"><?= htmlspecialchars($employee['name'] ?? '(名前なし)') ?></span>
                                        <?php if (!empty($employee['email'])): ?>
                                            <span class="perm-user-email"><?= htmlspecialchars($employee['email']) ?></span>
                                        <?php else: ?>
                                            <span class="perm-user-email no-email">メール未設定</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($employee['code'] ?? '-') ?></td>
                                <td>
                                    <select name="permissions[<?= htmlspecialchars($empKey) ?>]"
                                            class="role-select <?= $currentRole ?>"
                                            data-emp-key="<?= htmlspecialchars($empKey) ?>">
                                        <option value="" <?= empty($currentRole) ? 'selected' : '' ?>>権限なし</option>
                                        <option value="sales" <?= $currentRole === 'sales' ? 'selected' : '' ?>>営業部</option>
                                        <option value="product" <?= $currentRole === 'product' ? 'selected' : '' ?>>製品技術部</option>
                                        <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>管理部</option>
                                    </select>
                                    <span class="save-indicator" id="indicator-<?= htmlspecialchars($empKey) ?>"></span>
                                </td>
                                <td>
                                    <?php if (!empty($employee['email'])): ?>
                                        <span       class="text-2xs text-059">ログイン可</span>
                                    <?php else: ?>
                                        <span    class="text-gray-400 text-2xs">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>

            <!-- ページ別アクセス権限マトリクス -->
            <div class="page-matrix">
                <div class="section-title">
                    <h3>ページ別アクセス権限</h3>
                    <span class="help-text">閲覧と編集で別々の権限を設定できます</span>
                </div>
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th     class="w-180">ページ</th>
                            <th    class="w-140">閲覧権限</th>
                            <th    class="w-140">編集権限</th>
                            <th colspan="3">営業部</th>
                            <th colspan="3">製品技術部</th>
                            <th colspan="3">管理部</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th class="perm-header">閲覧</th>
                            <th class="perm-header">編集</th>
                            <th class="perm-header"></th>
                            <th class="perm-header">閲覧</th>
                            <th class="perm-header">編集</th>
                            <th class="perm-header"></th>
                            <th class="perm-header">閲覧</th>
                            <th class="perm-header">編集</th>
                            <th class="perm-header"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageLabels as $page => $label):
                            $perm = $pagePermissions[$page] ?? ['view' => 'admin', 'edit' => 'admin'];
                            if (!is_array($perm)) {
                                $perm = ['view' => $perm, 'edit' => $perm];
                            }
                            $viewPerm = $perm['view'];
                            $editPerm = $perm['edit'];

                            // 各権限でのアクセス可否を計算
                            $roleLevel = ['sales' => 1, 'product' => 2, 'admin' => 3];
                            $salesCanView = $roleLevel['sales'] >= $roleLevel[$viewPerm];
                            $salesCanEdit = $roleLevel['sales'] >= $roleLevel[$editPerm];
                            $productCanView = $roleLevel['product'] >= $roleLevel[$viewPerm];
                            $productCanEdit = $roleLevel['product'] >= $roleLevel[$editPerm];
                        ?>
                        <tr data-page="<?= htmlspecialchars($page) ?>">
                            <td><?= htmlspecialchars($label) ?></td>
                            <td>
                                <select class="matrix-select page-perm-select <?= $viewPerm ?>"
                                        data-page="<?= htmlspecialchars($page) ?>"
                                        data-perm-type="view">
                                    <option value="sales" <?= $viewPerm === 'sales' ? 'selected' : '' ?>>営業部以上</option>
                                    <option value="product" <?= $viewPerm === 'product' ? 'selected' : '' ?>>製品技術部以上</option>
                                    <option value="admin" <?= $viewPerm === 'admin' ? 'selected' : '' ?>>管理部のみ</option>
                                </select>
                            </td>
                            <td>
                                <select class="matrix-select page-perm-select <?= $editPerm ?>"
                                        data-page="<?= htmlspecialchars($page) ?>"
                                        data-perm-type="edit">
                                    <option value="sales" <?= $editPerm === 'sales' ? 'selected' : '' ?>>営業部以上</option>
                                    <option value="product" <?= $editPerm === 'product' ? 'selected' : '' ?>>製品技術部以上</option>
                                    <option value="admin" <?= $editPerm === 'admin' ? 'selected' : '' ?>>管理部のみ</option>
                                </select>
                                <span class="page-save-indicator" id="page-indicator-<?= htmlspecialchars($page) ?>"></span>
                            </td>
                            <td class="sales-view <?= $salesCanView ? 'access-yes' : 'access-no' ?>"><?= $salesCanView ? '○' : '×' ?></td>
                            <td class="sales-edit <?= $salesCanEdit ? 'access-yes' : 'access-no' ?>"><?= $salesCanEdit ? '○' : '×' ?></td>
                            <td></td>
                            <td class="product-view <?= $productCanView ? 'access-yes' : 'access-no' ?>"><?= $productCanView ? '○' : '×' ?></td>
                            <td class="product-edit <?= $productCanEdit ? 'access-yes' : 'access-no' ?>"><?= $productCanEdit ? '○' : '×' ?></td>
                            <td></td>
                            <td class="access-yes">○</td>
                            <td class="access-yes">○</td>
                            <td></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p    class="mt-2 text-gray-500 text-2xs">
                    ※ 上位の権限は下位の権限を含みます（管理部 > 製品技術部 > 営業部）<br>
                    ※ 編集権限は閲覧権限以上のレベルが必要です
                </p>
        </div>
    </div>
</div>
</div><!-- /.page-container -->

<script<?= nonceAttr() ?>>
// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // ユーザー権限セレクト
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            updateRole(this);
        });
    });

    // ページ権限セレクト
    document.querySelectorAll('.page-perm-select').forEach(select => {
        select.addEventListener('change', function() {
            updatePagePermission(this);
        });
    });
});

function updateRole(select) {
    const empKey = select.dataset.empKey;
    const newRole = select.value;
    const indicator = document.getElementById('indicator-' + empKey);

    select.className = 'role-select ' + newRole;

    indicator.textContent = '保存中...';
    indicator.className = 'save-indicator saving';

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'update_single=1&emp_key=' + encodeURIComponent(empKey) + '&role=' + encodeURIComponent(newRole) + '&csrf_token=' + encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            indicator.textContent = '保存済み';
            indicator.className = 'save-indicator saved';
            setTimeout(() => {
                indicator.className = 'save-indicator';
            }, 2000);
        } else {
            indicator.textContent = 'エラー';
            indicator.className = 'save-indicator';
            alert('保存に失敗しました: ' + (data.message || '不明なエラー'));
        }
    })
    .catch(error => {
        indicator.textContent = 'エラー';
        indicator.className = 'save-indicator';
        alert('通信エラーが発生しました');
    });
}

function updatePagePermission(select) {
    const page = select.dataset.page;
    const permType = select.dataset.permType;
    const newRole = select.value;
    // CSS.escape を使ってIDセレクタのエスケープを正しく行う
    const indicatorId = 'page-indicator-' + page;
    const indicator = document.getElementById(indicatorId);
    const row = select.closest('tr');

    select.className = 'matrix-select ' + newRole;

    indicator.textContent = '✓';
    indicator.className = 'page-save-indicator saved';

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'update_page_permission=1&page=' + encodeURIComponent(page) + '&perm_type=' + encodeURIComponent(permType) + '&role=' + encodeURIComponent(newRole) + '&csrf_token=' + encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // UIを更新
            const perms = data.permissions;
            const roleLevel = {sales: 1, product: 2, admin: 3};

            // 編集権限が調整された場合、セレクトも更新
            const editSelect = row.querySelector('[data-perm-type="edit"]');
            const viewSelect = row.querySelector('[data-perm-type="view"]');
            if (editSelect && perms.edit) {
                editSelect.value = perms.edit;
                editSelect.className = 'matrix-select ' + perms.edit;
            }

            // マトリクスの○×を更新
            const salesCanView = roleLevel['sales'] >= roleLevel[perms.view];
            const salesCanEdit = roleLevel['sales'] >= roleLevel[perms.edit];
            const productCanView = roleLevel['product'] >= roleLevel[perms.view];
            const productCanEdit = roleLevel['product'] >= roleLevel[perms.edit];

            row.querySelector('.sales-view').className = 'sales-view ' + (salesCanView ? 'access-yes' : 'access-no');
            row.querySelector('.sales-view').textContent = salesCanView ? '○' : '×';
            row.querySelector('.sales-edit').className = 'sales-edit ' + (salesCanEdit ? 'access-yes' : 'access-no');
            row.querySelector('.sales-edit').textContent = salesCanEdit ? '○' : '×';
            row.querySelector('.product-view').className = 'product-view ' + (productCanView ? 'access-yes' : 'access-no');
            row.querySelector('.product-view').textContent = productCanView ? '○' : '×';
            row.querySelector('.product-edit').className = 'product-edit ' + (productCanEdit ? 'access-yes' : 'access-no');
            row.querySelector('.product-edit').textContent = productCanEdit ? '○' : '×';

            setTimeout(() => {
                indicator.className = 'page-save-indicator';
            }, 2000);
        } else {
            alert('保存に失敗しました: ' + (data.message || '不明なエラー'));
        }
    })
    .catch(error => {
        alert('通信エラーが発生しました');
    });
}
</script>

<?php require_once '../functions/footer.php'; ?>
