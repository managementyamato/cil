<?php
/**
 * トラブル対応一括登録フォーム
 */
require_once '../api/auth.php';
require_once '../functions/notification-functions.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: /pages/troubles.php');
    exit;
}

$data = getData();
$message = '';
$messageType = '';

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = (int)($_POST['count'] ?? 1);
    $troubles = array();
    $validCount = 0;

    // 各トラブルデータを処理
    for ($i = 0; $i < $count; $i++) {
        $troubleContent = $_POST["trouble_content_$i"] ?? '';

        // トラブル内容が空の場合はスキップ
        if (empty(trim($troubleContent))) {
            continue;
        }

        $trouble = array(
            'pj_number' => $_POST["pj_number_$i"] ?? '',
            'trouble_content' => $troubleContent,
            'response_content' => $_POST["response_content_$i"] ?? '',
            'reporter' => $_POST["reporter_$i"] ?? '',
            'responder' => $_POST["responder_$i"] ?? '',
            'status' => $_POST["status_$i"] ?? '未対応',
            'date' => $_POST["date_$i"] ?? date('Y/m/d'),
            'call_no' => $_POST["call_no_$i"] ?? '',
            'project_contact' => isset($_POST["project_contact_$i"]),
            'case_no' => $_POST["case_no_$i"] ?? '',
            'company_name' => $_POST["company_name_$i"] ?? '',
            'customer_name' => $_POST["customer_name_$i"] ?? '',
            'honorific' => $_POST["honorific_$i"] ?? '様',
            'deadline' => trim($_POST["deadline_$i"] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );

        $troubles[] = $trouble;
        $validCount++;
    }

    if ($validCount > 0) {
        // 最大IDを取得
        $maxId = 0;
        foreach ($data['troubles'] ?? array() as $t) {
            if (isset($t['id']) && $t['id'] > $maxId) {
                $maxId = $t['id'];
            }
        }

        // IDを割り当てて保存
        if (!isset($data['troubles'])) {
            $data['troubles'] = array();
        }

        foreach ($troubles as $trouble) {
            $trouble['id'] = ++$maxId;
            $data['troubles'][] = $trouble;

            // 通知送信
            notifyNewTrouble($trouble);
        }

        saveData($data);
        header('Location: /pages/troubles.php?message=' . urlencode($validCount . '件のトラブル対応を登録しました'));
        exit;
    } else {
        $message = 'トラブル内容が入力されていません';
        $messageType = 'error';
    }
}

// トラブル担当者マスタから対応者リスト取得
$troubleResponders = array_map(fn($r) => $r['name'], $data['troubleResponders'] ?? []);
sort($troubleResponders);

// 記入者リスト（既存データのユニークな記入者 + トラブル担当者マスタ）
$reporters = array();
foreach ($data['troubles'] ?? [] as $t) {
    if (!empty($t['reporter'])) {
        $reporters[] = $t['reporter'];
    }
}
$reporters = array_unique(array_merge($reporters, $troubleResponders));
sort($reporters);

// デフォルトの登録件数
$defaultCount = isset($_GET['count']) ? (int)$_GET['count'] : 1;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>トラブル対応登録</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/style.css">
    <style>
        .bulk-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--gray-900);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .back-link:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
        }
        .count-selector {
            background: white;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--gray-700);
        }
        .count-selector strong {
            color: var(--gray-900);
        }
        .count-selector select {
            padding: 0.375rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--gray-900);
            background: white;
            cursor: pointer;
        }
        .count-selector select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(17, 122, 101, 0.1);
        }
        .count-selector .hint {
            color: var(--gray-500);
            font-size: 0.8125rem;
        }
        .trouble-card {
            background: white;
            padding: 1.25rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary);
        }
        .card-header {
            background: var(--primary);
            color: white;
            padding: 0.625rem 1rem;
            margin: -1.25rem -1.25rem 1rem calc(-1.25rem - 4px);
            margin-right: -1.25rem;
            border-radius: 0 12px 0 0;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .form-group {
            margin-bottom: 0.75rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.375rem;
            color: var(--gray-900);
            font-size: 0.8125rem;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--gray-900);
            background: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(17, 122, 101, 0.1);
        }
        .form-group textarea {
            min-height: 60px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }
        .form-row-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
        }
        .form-row-5 {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.75rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.25rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        .checkbox-group label {
            font-size: 0.8125rem;
            color: var(--gray-700);
            cursor: pointer;
        }
        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--primary);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(17, 122, 101, 0.3);
        }
        .btn-form-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            margin-left: 0.75rem;
            transition: all 0.2s;
        }
        .btn-form-cancel:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
        }
        .btn-copy {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.25rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-copy:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .required { color: var(--danger); margin-left: 2px; }
        .message {
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .message.success {
            background: var(--success-light);
            color: #2E7D32;
            border-left: 4px solid var(--success);
        }
        .message.error {
            background: var(--danger-light);
            color: #C62828;
            border-left: 4px solid var(--danger);
        }
        .submit-area {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            box-shadow: 0 -4px 16px rgba(0,0,0,0.1);
            margin-top: 1rem;
            text-align: center;
            z-index: 10;
        }
        .inline-label { display: inline; font-size: 0.8125rem; }

        @media (max-width: 768px) {
            .bulk-container { padding: 1rem; }
            .form-row-5 { grid-template-columns: repeat(2, 1fr); }
            .form-row-4 { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include '../functions/header.php'; ?>

    <div class="bulk-container">
        <div class="page-header">
            <h1>トラブル対応登録</h1>
            <a href="/pages/troubles.php" class="back-link">← 一覧に戻る</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($troubleResponders) && empty($reporters)): ?>
        <div class="message" style="background: #fff3e0; color: #e65100; border-left: 4px solid #ff9800;">
            トラブル担当者が未登録のため、記入者・対応者は手入力になります。<a href="/pages/masters.php#trouble_responders" style="color: #e65100; font-weight: 600;">マスタ管理</a>で登録すると選択式になります。
        </div>
        <?php endif; ?>

        <div class="count-selector">
            <strong>登録件数:</strong>
            <select id="countSelect" onchange="changeCount()">
                <?php for ($i = 1; $i <= 20; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $defaultCount === $i ? 'selected' : ''; ?>>
                        <?php echo $i; ?>件
                    </option>
                <?php endfor; ?>
            </select>
            <span class="hint">※ トラブル内容が空の項目は登録されません</span>
        </div>

        <form method="POST" id="bulkForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="count" value="<?php echo $defaultCount; ?>">

            <?php for ($i = 0; $i < $defaultCount; $i++): ?>
                <div class="trouble-card">
                    <div class="card-header">
                        <span>トラブル <?php echo $i + 1; ?></span>
                        <?php if ($i > 0): ?>
                            <button type="button" class="btn-copy" onclick="copyFromFirst(<?php echo $i; ?>)">1件目コピー</button>
                        <?php endif; ?>
                    </div>

                    <div class="form-row-5">
                        <div class="form-group">
                            <label>日付<span class="required">*</span></label>
                            <input type="text" name="date_<?php echo $i; ?>" id="date_<?php echo $i; ?>" value="<?php echo date('Y/m/d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>対応期限</label>
                            <input type="date" name="deadline_<?php echo $i; ?>" id="deadline_<?php echo $i; ?>">
                        </div>
                        <div class="form-group">
                            <label>コールNo</label>
                            <input type="text" name="call_no_<?php echo $i; ?>" id="call_no_<?php echo $i; ?>">
                        </div>
                        <div class="form-group">
                            <label>案件No</label>
                            <input type="text" name="case_no_<?php echo $i; ?>" id="case_no_<?php echo $i; ?>">
                        </div>
                        <div class="form-group">
                            <label>P番号<span class="required">*</span></label>
                            <input type="text" name="pj_number_<?php echo $i; ?>" id="pj_number_<?php echo $i; ?>" placeholder="例: 20250119-001" list="pj_list_<?php echo $i; ?>" required>
                            <datalist id="pj_list_<?php echo $i; ?>">
                                <?php foreach ($data['projects'] ?? array() as $proj): ?>
                                    <option value="<?php echo htmlspecialchars($proj['id']); ?>"><?php echo htmlspecialchars($proj['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-row" style="grid-template-columns: 1fr 1fr;">
                        <div class="form-group">
                            <label>トラブル内容<span class="required">*</span></label>
                            <textarea name="trouble_content_<?php echo $i; ?>" id="trouble_content_<?php echo $i; ?>"></textarea>
                        </div>
                        <div class="form-group">
                            <label>対応内容</label>
                            <textarea name="response_content_<?php echo $i; ?>" id="response_content_<?php echo $i; ?>"></textarea>
                        </div>
                    </div>

                    <div class="form-row-5">
                        <div class="form-group">
                            <label>記入者<span class="required">*</span></label>
                            <?php if (empty($reporters)): ?>
                                <input type="text" name="reporter_<?php echo $i; ?>" id="reporter_<?php echo $i; ?>" required placeholder="名前を入力">
                            <?php else: ?>
                            <select name="reporter_<?php echo $i; ?>" id="reporter_<?php echo $i; ?>" required>
                                <option value="">選択</option>
                                <?php foreach ($reporters as $name): ?>
                                    <option value="<?php echo htmlspecialchars($name); ?>" <?php echo ($_SESSION['user_name'] ?? '') === $name ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>対応者<span class="required">*</span></label>
                            <?php if (empty($troubleResponders)): ?>
                                <input type="text" name="responder_<?php echo $i; ?>" id="responder_<?php echo $i; ?>" required placeholder="名前を入力">
                            <?php else: ?>
                            <select name="responder_<?php echo $i; ?>" id="responder_<?php echo $i; ?>" required>
                                <option value="">選択</option>
                                <?php foreach ($troubleResponders as $name): ?>
                                    <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>状態<span class="required">*</span></label>
                            <select name="status_<?php echo $i; ?>" id="status_<?php echo $i; ?>" required>
                                <option value="未対応" selected>未対応</option>
                                <option value="対応中">対応中</option>
                                <option value="保留">保留</option>
                                <option value="完了">完了</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>社名</label>
                            <input type="text" name="company_name_<?php echo $i; ?>" id="company_name_<?php echo $i; ?>">
                        </div>
                        <div class="form-group">
                            <label>顧客名</label>
                            <div style="display:flex;gap:4px;">
                                <input type="text" name="customer_name_<?php echo $i; ?>" id="customer_name_<?php echo $i; ?>" style="flex:1;">
                                <select name="honorific_<?php echo $i; ?>" id="honorific_<?php echo $i; ?>" style="width:50px;">
                                    <option value="様" selected>様</option>
                                    <option value="殿">殿</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="project_contact_<?php echo $i; ?>" name="project_contact_<?php echo $i; ?>">
                        <label for="project_contact_<?php echo $i; ?>" class="inline-label">プロジェクトコンタクト</label>
                    </div>
                </div>
            <?php endfor; ?>

            <div class="submit-area">
                <button type="submit" class="btn-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    登録
                </button>
                <a href="/pages/troubles.php" class="btn-form-cancel">キャンセル</a>
            </div>
        </form>
    </div>

    <script>
        function changeCount() {
            const count = document.getElementById('countSelect').value;
            window.location.href = 'trouble-bulk-form.php?count=' + count;
        }

        function copyFromFirst(targetIndex) {
            const fields = [
                'date', 'deadline', 'call_no', 'case_no', 'pj_number',
                'reporter', 'responder', 'status',
                'company_name', 'customer_name', 'honorific'
            ];

            fields.forEach(field => {
                const source = document.getElementById(field + '_0');
                const target = document.getElementById(field + '_' + targetIndex);
                if (source && target) {
                    target.value = source.value;
                }
            });

            // チェックボックス
            const sourceCheck = document.getElementById('project_contact_0');
            const targetCheck = document.getElementById('project_contact_' + targetIndex);
            if (sourceCheck && targetCheck) {
                targetCheck.checked = sourceCheck.checked;
            }

            alert('1件目の情報をコピーしました（トラブル内容・対応内容は除く）');
        }
    </script>
</body>
</html>
