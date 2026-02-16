<?php
require_once '../api/auth.php';

$data = getData();

$message = '';
$messageType = '';

// 社員コード自動生成
function generateEmployeeCode($employees) {
    $maxNumber = 0;
    foreach ($employees as $employee) {
        $code = $employee['code'] ?? '';
        if (preg_match('/^YA-(\d+)$/', $code, $matches)) {
            $number = (int)$matches[1];
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
    }
    return 'YA-' . str_pad($maxNumber + 1, 3, '0', STR_PAD_LEFT);
}

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// メールアドレス重複チェック関数
function isEmailDuplicate($email, $employees, $excludeId = null) {
    if (empty($email)) {
        return false;
    }
    foreach ($employees as $emp) {
        // 除外ID（編集時の自分自身）はスキップ
        if ($excludeId !== null && isset($emp['id']) && $emp['id'] == $excludeId) {
            continue;
        }
        if (isset($emp['email']) && !empty($emp['email']) && strtolower($emp['email']) === strtolower($email)) {
            return true;
        }
    }
    return false;
}

// 従業員追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = trim($_POST['name'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');

    if (!$name || !$area) {
        $message = '氏名と担当エリアは必須です';
        $messageType = 'danger';
    } elseif (!empty($email) && !validateEmail($email)) {
        $message = 'メールアドレスの形式が正しくありません';
        $messageType = 'danger';
    } elseif (isEmailDuplicate($email, $data['employees'])) {
        $message = 'このメールアドレスは既に使用されています';
        $messageType = 'danger';
    } else if ($name && $area) {
        $employeeCode = generateEmployeeCode($data['employees']);

        $newEmployee = array(
            'code' => $employeeCode,
            'name' => $name,
            'area' => $area,
            'email' => $email,
            'memo' => trim($_POST['memo'] ?? ''),
            'vehicle_number' => $vehicle_number,
            'qualifications' => trim($_POST['qualifications'] ?? ''),
            'join_date' => trim($_POST['join_date'] ?? ''),
            'leave_date' => '',
            'chat_member' => isset($_POST['chat_member']) ? true : false
        );

        // 権限情報を追加
        if (!empty($role)) {
            $newEmployee['role'] = $role;
        }

        // ID生成（photo-uploadで使用）
        if (empty($data['employees'])) {
            $newEmployee['id'] = 1;
        } else {
            $maxId = 0;
            foreach ($data['employees'] as $emp) {
                if (isset($emp['id']) && $emp['id'] > $maxId) {
                    $maxId = $emp['id'];
                }
            }
            $newEmployee['id'] = $maxId + 1;
        }

        $data['employees'][] = $newEmployee;
        try {
            saveData($data);
            $message = '従業員を追加しました（社員コード: ' . $employeeCode . '）';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'データの保存に失敗しました';
            $messageType = 'danger';
        }
    }
}

// 従業員一括登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_employees'])) {
    $names = $_POST['bulk_name'] ?? [];
    $areas = $_POST['bulk_area'] ?? [];
    $emails = $_POST['bulk_email'] ?? [];
    $vehicles = $_POST['bulk_vehicle_number'] ?? [];
    $roles = $_POST['bulk_role'] ?? [];
    $memos = $_POST['bulk_memo'] ?? [];

    $addedCount = 0;
    $skippedEmails = [];
    $invalidEmails = [];
    $registeredEmails = [];  // 今回登録分のメール重複チェック用

    for ($i = 0; $i < count($names); $i++) {
        $name = trim($names[$i] ?? '');
        $area = trim($areas[$i] ?? '');
        $email = trim($emails[$i] ?? '');
        if (empty($name) || empty($area)) continue;

        // メールアドレス形式チェック
        if (!empty($email) && !validateEmail($email)) {
            $invalidEmails[] = $email;
            continue;
        }

        // 既存データとの重複チェック
        if (!empty($email) && isEmailDuplicate($email, $data['employees'])) {
            $skippedEmails[] = $email;
            continue;
        }

        // 今回登録分との重複チェック
        if (!empty($email) && in_array(strtolower($email), $registeredEmails)) {
            $skippedEmails[] = $email;
            continue;
        }

        $employeeCode = generateEmployeeCode($data['employees']);
        $newEmployee = array(
            'code' => $employeeCode,
            'name' => $name,
            'area' => $area,
            'email' => $email,
            'vehicle_number' => trim($vehicles[$i] ?? ''),
            'memo' => trim($memos[$i] ?? ''),
        );
        if (!empty($roles[$i] ?? '')) {
            $newEmployee['role'] = $roles[$i];
        }
        $maxId = 0;
        foreach ($data['employees'] as $emp) {
            $empId = (int)($emp['id'] ?? 0);
            if ($empId > $maxId) {
                $maxId = $empId;
            }
        }
        $newEmployee['id'] = $maxId + 1;
        $data['employees'][] = $newEmployee;
        if (!empty($email)) {
            $registeredEmails[] = strtolower($email);
        }
        $addedCount++;
    }

    if ($addedCount > 0) {
        try {
            saveData($data);
            $message = "{$addedCount}名の従業員を一括登録しました";
            if (!empty($skippedEmails)) {
                $message .= '（重複メール: ' . implode(', ', $skippedEmails) . ' はスキップ）';
            }
            if (!empty($invalidEmails)) {
                $message .= '（無効なメール: ' . implode(', ', $invalidEmails) . ' はスキップ）';
            }
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'データの保存に失敗しました';
            $messageType = 'danger';
        }
    } else {
        $message = '有効なデータがありません（氏名と担当エリアは必須）';
        if (!empty($skippedEmails)) {
            $message .= '。重複メールアドレス: ' . implode(', ', $skippedEmails);
        }
        if (!empty($invalidEmails)) {
            $message .= '。無効なメール形式: ' . implode(', ', $invalidEmails);
        }
        $messageType = 'danger';
    }
}

// 従業員編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $code = trim($_POST['employee_code'] ?? '');
    $originalCode = trim($_POST['original_employee_code'] ?? '');
    $employeeId = $_POST['employee_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $chat_user_id = trim($_POST['chat_user_id'] ?? '');

    if (!$name) {
        $message = '氏名は必須です';
        $messageType = 'danger';
    } elseif (!empty($email) && !validateEmail($email)) {
        $message = 'メールアドレスの形式が正しくありません';
        $messageType = 'danger';
    } elseif (isEmailDuplicate($email, $data['employees'], $employeeId)) {
        $message = 'このメールアドレスは既に他の従業員で使用されています';
        $messageType = 'danger';
    } else if ($name) {
        foreach ($data['employees'] as $key => $employee) {
            // originalCodeまたはidでマッチング
            $matched = false;
            if (!empty($originalCode) && isset($employee['code']) && $employee['code'] === $originalCode) {
                $matched = true;
            } elseif (!empty($employeeId) && isset($employee['id']) && $employee['id'] === $employeeId) {
                $matched = true;
            }
            if ($matched) {
                $updatedEmployee = array(
                    'id' => $employee['id'] ?? $key + 1,
                    'name' => $name,
                    'area' => $area,
                    'email' => $email,
                    'memo' => trim($_POST['memo'] ?? ''),
                    'vehicle_number' => $vehicle_number,
                    'chat_user_id' => $chat_user_id,
                    'qualifications' => trim($_POST['qualifications'] ?? ''),
                    'join_date' => trim($_POST['join_date'] ?? ''),
                    'leave_date' => trim($_POST['leave_date'] ?? ''),
                    'chat_member' => isset($_POST['chat_member']) ? true : false
                );

                // 従業員コードを更新（新しい値があればそれを使う）
                if (!empty($code)) {
                    $updatedEmployee['code'] = $code;
                } elseif (!empty($employee['code'])) {
                    $updatedEmployee['code'] = $employee['code'];
                }

                // Google OAuth自動登録の情報を保持
                if (isset($employee['created_by'])) {
                    $updatedEmployee['created_by'] = $employee['created_by'];
                }
                if (isset($employee['created_at'])) {
                    $updatedEmployee['created_at'] = $employee['created_at'];
                }

                // 権限情報を更新
                if (!empty($role)) {
                    $updatedEmployee['role'] = $role;
                }

                $data['employees'][$key] = $updatedEmployee;
                try {
                    saveData($data);
                    $message = '従業員情報を更新しました';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'データの保存に失敗しました';
                    $messageType = 'danger';
                }
                break;
            }
        }
    }
}

// 従業員削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $deleteKey = $_POST['delete_employee'];

        // 削除対象の従業員を特定
        $targetEmployee = null;
        $targetIndex = null;
        foreach ($data['employees'] as $idx => $emp) {
            if ((isset($emp['code']) && $emp['code'] === $deleteKey) ||
                (isset($emp['id']) && $emp['id'] === $deleteKey)) {
                $targetEmployee = $emp;
                $targetIndex = $idx;
                break;
            }
        }

        // 自分自身は削除できない
        $currentUserEmail = $_SESSION['user_email'] ?? '';
        if ($targetEmployee && isset($targetEmployee['email']) && $targetEmployee['email'] === $currentUserEmail) {
            $message = '自分自身を削除することはできません';
            $messageType = 'danger';
        }
        // 最後の管理者は削除できない
        elseif ($targetEmployee && ($targetEmployee['role'] ?? '') === 'admin') {
            $adminCount = 0;
            foreach ($data['employees'] as $emp) {
                if (($emp['role'] ?? '') === 'admin' && empty($emp['leave_date']) && empty($emp['deleted_at'])) {
                    $adminCount++;
                }
            }
            if ($adminCount <= 1) {
                $message = '最後の管理者を削除することはできません';
                $messageType = 'danger';
            } else {
                // 論理削除
                $data['employees'][$targetIndex]['deleted_at'] = date('Y-m-d H:i:s');
                $data['employees'][$targetIndex]['deleted_by'] = $_SESSION['user_email'] ?? 'system';
                try {
                    saveData($data);
                    auditDelete('employees', $deleteKey, '従業員を削除: ' . ($targetEmployee['name'] ?? ''), $targetEmployee);
                    $message = '従業員を削除しました';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'データの保存に失敗しました';
                    $messageType = 'danger';
                }
            }
        } else if ($targetEmployee && $targetIndex !== null) {
            // 論理削除
            $data['employees'][$targetIndex]['deleted_at'] = date('Y-m-d H:i:s');
            $data['employees'][$targetIndex]['deleted_by'] = $_SESSION['user_email'] ?? 'system';
            try {
                saveData($data);
                auditDelete('employees', $deleteKey, '従業員を削除: ' . ($targetEmployee['name'] ?? ''), $targetEmployee);
                $message = '従業員を削除しました';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'データの保存に失敗しました';
                $messageType = 'danger';
            }
        }
    }
}

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* 従業員管理固有のスタイル */

/* .card は components.css を使用 */

.employee-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.employee-table th {
    background: #f7fafc;
    padding: 0.75rem;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    font-weight: 600;
    color: #4a5568;
}

.employee-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.employee-table tr:hover {
    background: #f7fafc;
}

/* .btn系 は components.css を使用 */

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-900);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 0.875rem;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.required {
    color: var(--danger);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.modal-header {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 1.5rem;
}

.modal-footer {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.btn-secondary {
    background: var(--gray-500);
    color: white;
}

.btn-secondary:hover {
    background: var(--gray-700);
}

/* アイコンボタン */
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    background: transparent;
}

.btn-icon:hover {
    transform: translateY(-1px);
}

.btn-icon-edit {
    color: var(--success);
}

.btn-icon-edit:hover {
    background: var(--success-light);
}

.btn-icon-delete {
    color: var(--danger);
}

.btn-icon-delete:hover {
    background: var(--danger-light);
}
</style>

<div class="page-container">
    <div   class="page-header d-flex justify-between align-center">
        <h2  class="m-0">従業員マスタ</h2>
        <a href="settings.php" class="btn btn-secondary">設定に戻る</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php
    $today = date('Y-m-d');
    // 退職者 = leave_dateが設定されていて、かつ今日以前の日付
    $isRetired = function($e) use ($today) {
        return !empty($e['leave_date']) && $e['leave_date'] <= $today;
    };
    $showRetired = isset($_GET['show_retired']) && $_GET['show_retired'] === '1';
    $activeEmployees = filterDeleted($data['employees']);
    $displayEmployees = $showRetired ? $activeEmployees : array_filter($activeEmployees, function($e) use ($isRetired) {
        return !$isRetired($e);
    });
    // 従業員コード順にソート（コードなしは末尾）
    usort($displayEmployees, function($a, $b) {
        $codeA = $a['code'] ?? '';
        $codeB = $b['code'] ?? '';
        if ($codeA === '' && $codeB === '') return 0;
        if ($codeA === '') return 1;
        if ($codeB === '') return -1;
        return strcmp($codeA, $codeB);
    });
    $retiredCount = count(array_filter($data['employees'], $isRetired));
    ?>
    <div class="card">
        <div  class="d-flex justify-between align-center mb-2">
            <h2   class="card-title m-0">従業員一覧 （総件数: <?= count($data['employees']) ?>件）</h2>
            <div  class="d-flex gap-1 align-center">
                <a href="?show_retired=<?= $showRetired ? '0' : '1' ?>" class="text-085 text-blue-700">
                    <?= $showRetired ? '退職者を非表示' : "退職者を表示 ({$retiredCount})" ?>
                </a>
                <?php if (canEdit()): ?>
                <button class="btn btn-primary" id="addEmployeeBtn">新規登録</button>
                <button class="btn btn-edit" id="bulkAddBtn">一括登録</button>
                <?php endif; ?>
            </div>
        </div>

        <table class="employee-table" id="employeeTable">
            <thead>
                <tr>
                    <th>NO.</th>
                    <th>従業員コード</th>
                    <th>氏名</th>
                    <th>担当エリア</th>
                    <th>メールアドレス</th>
                    <th>車両ナンバー</th>
                    <th>ユーザー権限</th>
                    <th>入社日</th>
                    <th>備考</th>
                    <th>資格・スキル</th>
                    <th  class="text-center" title="PJ作成時にChatスペースへ自動追加">Chat</th>
                    <th  class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($displayEmployees)): ?>
                    <tr>
                        <td colspan="12"    class="text-center text-gray-718">登録されている従業員はありません</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($displayEmployees as $index => $employee): ?>
                        <?php $deleteKey = $employee['code'] ?? $employee['id'] ?? ''; ?>
                        <?php
                        $empIsRetired = $isRetired($employee);
                        $hasLeaveDate = !empty($employee['leave_date']);
                        $leaveDateFuture = $hasLeaveDate && $employee['leave_date'] > $today;
                        ?>
                        <tr<?= $empIsRetired ? ' class="employee-retired"' : '' ?>>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($employee['code'] ?? '-') ?></td>
                            <td>
                                <?= htmlspecialchars($employee['name'] ?? '') ?>
                                <?php if ($empIsRetired): ?>
                                    <span        class="rounded badge-sm badge-retired">退職</span>
                                <?php elseif ($leaveDateFuture): ?>
                                    <span        class="rounded badge-leave-scheduled">退職予定(<?= htmlspecialchars($employee['leave_date']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($employee['area'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($employee['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($employee['vehicle_number'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($employee['role'])): ?>
                                    <?php
                                    $roleLabels = array('admin' => '管理部', 'product' => '製品管理部', 'sales' => '営業部');
                                    $roleLabel = $roleLabels[$employee['role']] ?? $employee['role'];
                                    $roleColors = array('admin' => '#dbeafe', 'product' => '#d1fae5', 'sales' => '#fef3c7');
                                    $roleTextColors = array('admin' => '#1e40af', 'product' => '#065f46', 'sales' => '#92400e');
                                    $bg = $roleColors[$employee['role']] ?? '#f3f4f6';
                                    $color = $roleTextColors[$employee['role']] ?? '#374151';
                                    ?>
                                    <span        class="rounded text-xs whitespace-nowrap tag-md" style="background: <?= $bg ?>; color: <?= $color ?>;"><?= htmlspecialchars($roleLabel) ?></span>
                                <?php else: ?>
                                    <span     class="text-a0a">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($employee['join_date']) ? htmlspecialchars($employee['join_date']) : '-' ?></td>
                            <td><?= htmlspecialchars($employee['memo'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($employee['qualifications'])): ?>
                                    <?php foreach (explode(',', $employee['qualifications']) as $q): ?>
                                        <span        class="d-inline-block rounded qualification-badge"><?= htmlspecialchars(trim($q)) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span     class="text-a0a">-</span>
                                <?php endif; ?>
                            </td>
                            <td  class="text-center">
                                <?php
                                $empId = $employee['id'] ?? $employee['code'] ?? '';
                                $chatMember = !isset($employee['chat_member']) || $employee['chat_member'] === true;
                                $hasEmail = !empty($employee['email']);
                                ?>
                                <?php if ($hasEmail && !$empIsRetired): ?>
                                <input type="checkbox"
                                    <?= $chatMember ? 'checked' : '' ?>
                                    class="chat-member-checkbox"
                                    data-employee-id="<?= htmlspecialchars($empId) ?>"
                                    class="checkbox-18"
                                    title="<?= $chatMember ? 'Chatスペースに追加する' : 'Chatスペースに追加しない' ?>">
                                <?php else: ?>
                                <span     class="text-a0a">-</span>
                                <?php endif; ?>
                            </td>
                            <td  class="text-center whitespace-nowrap">
                                <?php if (canEdit()): ?>
                                <button class="btn-icon btn-icon-edit edit-employee-btn" data-employee='<?= htmlspecialchars(json_encode($employee), ENT_QUOTES) ?>' title="編集">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <?php endif; ?>
                                <?php if (canDelete()): ?>
                                <form method="POST"  class="d-inline" class="delete-employee-form">
                                    <?= csrfTokenField() ?>
                                    <button type="submit" name="delete_employee" value="<?= htmlspecialchars($deleteKey) ?>" class="btn-icon btn-icon-delete" title="削除">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div id="employeePagination"></div>
    </div>

</div>

<script<?= nonceAttr() ?>>
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('employeeTable');
    if (table && table.querySelector('tbody tr')) {
        new Paginator({
            container: '#employeeTable',
            itemSelector: 'tbody tr',
            perPage: 50,
            paginationTarget: '#employeePagination'
        });
    }
});
</script>

<!-- 新規登録モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">新規従業員登録</div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="form-group">
                <label>社員コード（自動採番）</label>
                <input type="text" value="<?= generateEmployeeCode($data['employees']) ?>" disabled>
                <small   class="text-gray-718">※既存の番号から自動で割り振られます</small>
            </div>

            <div class="form-group">
                <label>氏名 <span class="required">*</span></label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>担当エリア <span class="required">*</span></label>
                <input type="text" name="area" required>
            </div>

            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" id="add_email">
                <small   class="text-gray-718">Googleログイン時にこのメールアドレスで照合されます</small>
            </div>

            <div class="form-group">
                <label>車両ナンバー</label>
                <input type="text" name="vehicle_number" id="add_vehicle_number" placeholder="例: 品川 500 あ 1234">
                <small   class="text-gray-718">アルコールチェック管理で使用します</small>
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo"></textarea>
            </div>

            <div class="form-group">
                <label>資格・スキル</label>
                <input type="text" name="qualifications" id="add_qualifications" placeholder="例: フォークリフト,電気工事士（カンマ区切り）">
                <small   class="text-gray-718">カンマ区切りで複数入力可</small>
            </div>

            <div class="form-group">
                <label>入社日</label>
                <input type="date" name="join_date" id="add_join_date">
            </div>

            <div class="form-group">
                <label>退職日</label>
                <input type="date" name="leave_date" id="add_leave_date">
                <small   class="text-gray-718">退職日を入力すると「退職」状態になります</small>
            </div>

            <div class="form-group">
                <label>権限</label>
                <select class="form-select" name="role" id="add_role">
                    <option value="">設定しない</option>
                    <option value="sales">営業部</option>
                    <option value="product">製品管理部</option>
                    <option value="admin">管理部</option>
                </select>
                <small   class="text-gray-718">Googleログイン時に適用される権限です</small>
            </div>

            <div class="form-group">
                <label  class="d-flex align-center gap-1 cursor-pointer">
                    <input type="checkbox" name="chat_member" value="1" checked  class="w-auto m-0">
                    <span>Google Chatスペースに自動追加</span>
                </label>
                <small   class="text-gray-718">PJ新規作成時に自動でChatスペースのメンバーに追加されます</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-add-modal-btn">戻る</button>
                <button type="submit" name="add_employee" class="btn btn-primary">登録</button>
            </div>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">従業員情報編集</div>
        <form method="POST" id="editForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="employee_id" id="edit_id">
            <input type="hidden" name="original_employee_code" id="edit_original_code">

            <div class="form-group">
                <label>従業員コード</label>
                <input type="text" name="employee_code" id="edit_code">
            </div>

            <div class="form-group">
                <label>氏名 <span class="required">*</span></label>
                <input type="text" name="name" id="edit_name" required>
            </div>

            <div class="form-group">
                <label>担当エリア</label>
                <input type="text" name="area" id="edit_area">
            </div>

            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" id="edit_email">
                <small   class="text-gray-718">Googleログイン時にこのメールアドレスで照合されます</small>
            </div>

            <div class="form-group">
                <label>車両ナンバー</label>
                <input type="text" name="vehicle_number" id="edit_vehicle_number" placeholder="例: 品川 500 あ 1234">
                <small   class="text-gray-718">アルコールチェック管理で使用します</small>
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo" id="edit_memo"></textarea>
            </div>

            <div class="form-group">
                <label>資格・スキル</label>
                <input type="text" name="qualifications" id="edit_qualifications" placeholder="例: フォークリフト,電気工事士（カンマ区切り）">
                <small   class="text-gray-718">カンマ区切りで複数入力可</small>
            </div>

            <div class="form-group">
                <label>入社日</label>
                <input type="date" name="join_date" id="edit_join_date">
            </div>

            <div class="form-group">
                <label>退職日</label>
                <input type="date" name="leave_date" id="edit_leave_date">
                <small   class="text-gray-718">退職日を入力すると「退職」状態になります</small>
            </div>

            <div class="form-group">
                <label>権限</label>
                <select class="form-select" name="role" id="edit_role">
                    <option value="">設定しない</option>
                    <option value="sales">営業部</option>
                    <option value="product">製品管理部</option>
                    <option value="admin">管理部</option>
                </select>
                <small   class="text-gray-718">Googleログイン時に適用される権限です</small>
            </div>

            <div class="form-group">
                <label>Google Chat User ID</label>
                <div  class="d-flex gap-1 align-center">
                    <input type="text" name="chat_user_id" id="edit_chat_user_id" placeholder="例: users/123456789012345678901"    class="flex-1">
                    <button type="button"  id="fetchChatUserIdBtn"        class="btn btn-secondary whitespace-nowrap btn-pad-05-075">自動取得</button>
                </div>
                <div id="chatUserIdStatus"  class="d-none mt-1 p-1 rounded text-xs"></div>
                <small   class="text-gray-718">メールアドレスからGoogle Chat User IDを自動取得します。</small>
            </div>

            <div class="form-group">
                <label  class="d-flex align-center gap-1 cursor-pointer">
                    <input type="checkbox" name="chat_member" id="edit_chat_member" value="1"  class="w-auto m-0">
                    <span>Google Chatスペースに自動追加</span>
                </label>
                <small   class="text-gray-718">PJ新規作成時に自動でChatスペースのメンバーに追加されます</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-edit-modal-btn">キャンセル</button>
                <button type="submit" name="edit_employee" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 一括登録モーダル -->
<div id="bulkAddModal" class="modal">
    <div         class="modal-content modal-content-wide">
        <div class="modal-header">従業員一括登録</div>
        <form method="POST" id="bulkAddForm">
            <?= csrfTokenField() ?>
            <div  class="mb-2 d-flex justify-between align-center">
                <span   class="text-gray-718 text-14">氏名と担当エリアは必須です。空行はスキップされます。</span>
                <button type="button"  id="addBulkRowBtn"        class="btn btn-primary text-2xs btn-xs">+ 行追加</button>
            </div>
            <div    class="overflow-x-auto">
                <table        class="w-full text-sm border-collapse" id="bulkTable">
                    <thead>
                        <tr     class="bulk-table-header">
                            <th    class="p-1 text-left whitespace-nowrap border-b-2-e2">No.</th>
                            <th    class="p-1 text-left border-b-2-e2">氏名 <span class="required">*</span></th>
                            <th    class="p-1 text-left border-b-2-e2">担当エリア <span class="required">*</span></th>
                            <th    class="p-1 text-left border-b-2-e2">メール</th>
                            <th    class="p-1 text-left border-b-2-e2">車両ナンバー</th>
                            <th    class="p-1 text-left border-b-2-e2">権限</th>
                            <th    class="p-1 text-left border-b-2-e2">備考</th>
                            <th    class="p-1 border-b-2-e2"></th>
                        </tr>
                    </thead>
                    <tbody id="bulkTableBody">
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-bulk-modal-btn">キャンセル</button>
                <button type="submit" name="bulk_add_employees" class="btn btn-primary">一括登録</button>
            </div>
        </form>
    </div>
</div>

<script<?= nonceAttr() ?>>
// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // 新規登録ボタン
    const addEmployeeBtn = document.getElementById('addEmployeeBtn');
    if (addEmployeeBtn) {
        addEmployeeBtn.addEventListener('click', openAddModal);
    }

    // 一括登録ボタン
    const bulkAddBtn = document.getElementById('bulkAddBtn');
    if (bulkAddBtn) {
        bulkAddBtn.addEventListener('click', openBulkAddModal);
    }

    // モーダル閉じるボタン
    const closeAddModalBtn = document.querySelector('.close-add-modal-btn');
    if (closeAddModalBtn) {
        closeAddModalBtn.addEventListener('click', closeAddModal);
    }

    const closeEditModalBtn = document.querySelector('.close-edit-modal-btn');
    if (closeEditModalBtn) {
        closeEditModalBtn.addEventListener('click', closeEditModal);
    }

    const closeBulkModalBtn = document.querySelector('.close-bulk-modal-btn');
    if (closeBulkModalBtn) {
        closeBulkModalBtn.addEventListener('click', closeBulkAddModal);
    }

    // 編集ボタン
    document.querySelectorAll('.edit-employee-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const employee = JSON.parse(this.getAttribute('data-employee'));
            openEditModal(employee);
        });
    });

    // 削除フォーム
    document.querySelectorAll('.delete-employee-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('この従業員を削除してもよろしいですか？')) {
                e.preventDefault();
            }
        });
    });

    // Chatメンバーチェックボックス
    document.querySelectorAll('.chat-member-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleChatMember(this.getAttribute('data-employee-id'), this.checked);
        });
    });

    // Chat User ID自動取得ボタン
    const fetchChatUserIdBtn = document.getElementById('fetchChatUserIdBtn');
    if (fetchChatUserIdBtn) {
        fetchChatUserIdBtn.addEventListener('click', fetchChatUserId);
    }

    // 一括登録の行追加ボタン
    const addBulkRowBtn = document.getElementById('addBulkRowBtn');
    if (addBulkRowBtn) {
        addBulkRowBtn.addEventListener('click', addBulkRow);
    }
});

function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function openEditModal(employee) {
    document.getElementById('edit_code').value = employee.code || '';
    document.getElementById('edit_id').value = employee.id || '';
    document.getElementById('edit_original_code').value = employee.code || '';
    document.getElementById('edit_name').value = employee.name || '';
    document.getElementById('edit_area').value = employee.area || '';
    document.getElementById('edit_email').value = employee.email || '';
    document.getElementById('edit_vehicle_number').value = employee.vehicle_number || '';
    document.getElementById('edit_memo').value = employee.memo || '';
    document.getElementById('edit_role').value = employee.role || '';
    document.getElementById('edit_chat_user_id').value = employee.chat_user_id || '';
    document.getElementById('edit_qualifications').value = employee.qualifications || '';
    document.getElementById('edit_join_date').value = employee.join_date || '';
    document.getElementById('edit_leave_date').value = employee.leave_date || '';
    // chat_memberフラグ（未設定の場合はtrue=追加するがデフォルト）
    document.getElementById('edit_chat_member').checked = employee.chat_member !== false;

    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

function openBulkAddModal() {
    const tbody = document.getElementById('bulkTableBody');
    tbody.innerHTML = '';
    for (let i = 0; i < 5; i++) addBulkRow();
    document.getElementById('bulkAddModal').classList.add('active');
}

function closeBulkAddModal() {
    document.getElementById('bulkAddModal').classList.remove('active');
}

let bulkRowCount = 0;
function addBulkRow() {
    bulkRowCount++;
    const tbody = document.getElementById('bulkTableBody');
    const tr = document.createElement('tr');
    tr.id = 'bulkRow' + bulkRowCount;
    const inputStyle = 'width:100%;padding:4px 6px;border:1px solid #cbd5e0;border-radius:4px;font-size:0.85rem;box-sizing:border-box;';
    const rowId = 'bulkRow' + bulkRowCount;
    tr.innerHTML = `
        <td    class="text-center p-05 text-gray-718" class="bulk-row-num"></td>
        <td   class="p-05"><input type="text" name="bulk_name[]" class="bulk-input-style" placeholder="氏名"></td>
        <td   class="p-05"><input type="text" name="bulk_area[]" class="bulk-input-style" placeholder="エリア"></td>
        <td   class="p-05"><input type="email" name="bulk_email[]" class="bulk-input-style" placeholder="email"></td>
        <td   class="p-05"><input type="text" name="bulk_vehicle_number[]" class="bulk-input-style" placeholder="車両"></td>
        <td   class="p-05">
            <select name="bulk_role[]" class="bulk-input-style">
                <option value="">-</option>
                <option value="sales">営業部</option>
                <option value="product">製品管理部</option>
                <option value="admin">管理部</option>
            </select>
        </td>
        <td   class="p-05"><input type="text" name="bulk_memo[]" class="bulk-input-style" placeholder="備考"></td>
        <td   class="p-05"><button type="button"  data-row-id="${escapeHtml(rowId)}" class="bulk-remove-btn">✕</button></td>
    `;
    // イベントリスナーで削除ボタンを登録（onclick属性を使わない）
    tr.querySelector('.bulk-remove-btn').addEventListener('click', function() {
        removeBulkRow(this.getAttribute('data-row-id'));
    });
    tbody.appendChild(tr);
    renumberBulkRows();
}

function removeBulkRow(id) {
    const row = document.getElementById(id);
    if (row) row.remove();
    renumberBulkRows();
}

function renumberBulkRows() {
    document.querySelectorAll('#bulkTableBody tr').forEach((tr, i) => {
        tr.querySelector('.bulk-row-num').textContent = i + 1;
    });
}

// モーダル外クリックで閉じる
['addModal', 'editModal', 'bulkAddModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});

// Google Chat User ID自動取得
function fetchChatUserId() {
    const email = document.getElementById('edit_email').value;
    const statusDiv = document.getElementById('chatUserIdStatus');
    const inputField = document.getElementById('edit_chat_user_id');

    if (!email) {
        statusDiv.style.display = 'block';
        statusDiv.style.background = '#ffebee';
        statusDiv.style.color = '#c62828';
        statusDiv.textContent = 'メールアドレスを入力してください';
        setTimeout(() => { statusDiv.style.display = 'none'; }, 3000);
        return;
    }

    statusDiv.style.display = 'block';
    statusDiv.style.background = '#e3f2fd';
    statusDiv.style.color = '#1565c0';
    statusDiv.textContent = '検索中...';

    fetch('../api/alcohol-chat-sync.php?action=lookup_user&email=' + encodeURIComponent(email))
        .then(r => r.json())
        .then(data => {
            if (data.success && data.user_id) {
                inputField.value = data.user_id;
                statusDiv.style.background = '#e8f5e9';
                statusDiv.style.color = '#2e7d32';
                statusDiv.textContent = 'User IDを取得しました';
            } else {
                statusDiv.style.background = '#ffebee';
                statusDiv.style.color = '#c62828';
                statusDiv.textContent = data.error || 'User IDが見つかりませんでした';
            }
            setTimeout(() => { statusDiv.style.display = 'none'; }, 3000);
        })
        .catch(err => {
            statusDiv.style.background = '#ffebee';
            statusDiv.style.color = '#c62828';
            statusDiv.textContent = '通信エラーが発生しました';
            setTimeout(() => { statusDiv.style.display = 'none'; }, 3000);
        });
}

// Chatメンバー設定をAJAXで更新
function toggleChatMember(employeeId, checked) {
    fetch('../api/employee-chat-member.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= generateCsrfToken() ?>'
        },
        body: JSON.stringify({
            employee_id: employeeId,
            chat_member: checked
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('更新に失敗しました: ' + (data.error || ''));
            // チェックを元に戻す
            event.target.checked = !checked;
        }
    })
    .catch(err => {
        alert('通信エラーが発生しました');
        event.target.checked = !checked;
    });
}
</script>

</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
