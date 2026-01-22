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

// 従業員リスト取得
$employees = $data['employees'] ?? array();

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
    <link rel="stylesheet" href="/style.css">
    <style>
        .bulk-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .count-selector {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .trouble-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: #2196F3;
            color: white;
            padding: 10px 15px;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
            font-size: 13px;
        }
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .btn-submit {
            background: #4CAF50;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-submit:hover {
            background: #45a049;
        }
        .btn-cancel {
            background: #f5f5f5;
            color: #333;
            padding: 15px 40px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        .btn-cancel:hover {
            background: #e0e0e0;
        }
        .btn-copy {
            background: #FF9800;
            color: white;
            padding: 5px 15px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-copy:hover {
            background: #F57C00;
        }
        .required {
            color: #f44336;
            margin-left: 4px;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .submit-area {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include '../functions/header.php'; ?>

    <div class="bulk-container">
        <div class="page-header">
            <h1>トラブル対応登録</h1>
            <a href="/pages/troubles.php" class="btn-cancel">← 一覧に戻る</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="count-selector">
            <label><strong>登録件数:</strong></label>
            <select id="countSelect" onchange="changeCount()">
                <?php for ($i = 1; $i <= 20; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $defaultCount === $i ? 'selected' : ''; ?>>
                        <?php echo $i; ?>件
                    </option>
                <?php endfor; ?>
            </select>
            <span style="color: #666; font-size: 13px;">※トラブル内容が空の項目は登録されません</span>
        </div>

        <form method="POST" id="bulkForm">
            <input type="hidden" name="count" value="<?php echo $defaultCount; ?>">

            <?php for ($i = 0; $i < $defaultCount; $i++): ?>
                <div class="trouble-card">
                    <div class="card-header">
                        <span>トラブル <?php echo $i + 1; ?></span>
                        <?php if ($i > 0): ?>
                            <button type="button" class="btn-copy" onclick="copyFromFirst(<?php echo $i; ?>)">
                                1件目の情報をコピー
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label>日付<span class="required">*</span></label>
                            <input type="text" name="date_<?php echo $i; ?>" id="date_<?php echo $i; ?>"
                                   value="<?php echo date('Y/m/d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>コールNo</label>
                            <input type="text" name="call_no_<?php echo $i; ?>" id="call_no_<?php echo $i; ?>">
                        </div>
                        <div class="form-group">
                            <label>案件No</label>
                            <input type="text" name="case_no_<?php echo $i; ?>" id="case_no_<?php echo $i; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>P番号<span class="required">*</span></label>
                        <input type="text"
                               name="pj_number_<?php echo $i; ?>"
                               id="pj_number_<?php echo $i; ?>"
                               placeholder="例: 20250119-001"
                               list="pj_list_<?php echo $i; ?>"
                               required>
                        <datalist id="pj_list_<?php echo $i; ?>">
                            <?php foreach ($data['projects'] ?? array() as $proj): ?>
                                <option value="<?php echo htmlspecialchars($proj['id']); ?>">
                                    <?php echo htmlspecialchars($proj['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            入力後に存在しない場合は新規登録画面が表示されます
                        </small>
                    </div>

                    <div class="form-group">
                        <label>トラブル内容<span class="required">*</span></label>
                        <textarea name="trouble_content_<?php echo $i; ?>" id="trouble_content_<?php echo $i; ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <label>対応内容</label>
                        <textarea name="response_content_<?php echo $i; ?>" id="response_content_<?php echo $i; ?>"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>記入者<span class="required">*</span></label>
                            <select name="reporter_<?php echo $i; ?>" id="reporter_<?php echo $i; ?>" required>
                                <option value="">選択してください</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['name']); ?>"
                                        <?php echo ($_SESSION['user_name'] ?? '') === $emp['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>対応者<span class="required">*</span></label>
                            <select name="responder_<?php echo $i; ?>" id="responder_<?php echo $i; ?>" required>
                                <option value="">選択してください</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['name']); ?>">
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>社名</label>
                            <input type="text" name="company_name_<?php echo $i; ?>" id="company_name_<?php echo $i; ?>">
                        </div>
                        <div class="form-group">
                            <label>お客様お名前</label>
                            <input type="text" name="customer_name_<?php echo $i; ?>" id="customer_name_<?php echo $i; ?>">
                        </div>
                        <div class="form-group">
                            <label>敬称</label>
                            <select name="honorific_<?php echo $i; ?>" id="honorific_<?php echo $i; ?>">
                                <option value="様" selected>様</option>
                                <option value="殿">殿</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="project_contact_<?php echo $i; ?>"
                                   name="project_contact_<?php echo $i; ?>">
                            <label for="project_contact_<?php echo $i; ?>" style="margin: 0;">プロジェクトコンタクト</label>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <div class="submit-area">
                <button type="submit" class="btn-submit">登録</button>
                <a href="/pages/troubles.php" class="btn-cancel">キャンセル</a>
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
                'date', 'call_no', 'case_no', 'pj_number',
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
