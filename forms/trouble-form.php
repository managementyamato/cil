<?php
/**
 * トラブル対応入力・編集フォーム
 */
require_once '../api/auth.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: /pages/troubles.php');
    exit;
}

// CSRFトークン生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$data = getData();
$isEdit = false;
$trouble = array(
    'id' => null,
    'pj_number' => '',
    'trouble_content' => '',
    'response_content' => '',
    'reporter' => '',
    'responder' => '',
    'status' => '未対応',
    'date' => date('Y/m/d'),
    'call_no' => '',
    'project_contact' => false,
    'case_no' => '',
    'company_name' => '',
    'customer_name' => '',
    'honorific' => '様'
);

// 編集モード
if (isset($_GET['id'])) {
    $isEdit = true;
    $editId = (int)$_GET['id'];
    foreach ($data['troubles'] ?? array() as $t) {
        if ($t['id'] === $editId) {
            $trouble = $t;
            break;
        }
    }
}

$message = '';
$messageType = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trouble = array(
        'id' => isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null,
        'pj_number' => $_POST['pj_number'] ?? '',
        'trouble_content' => $_POST['trouble_content'] ?? '',
        'response_content' => $_POST['response_content'] ?? '',
        'reporter' => $_POST['reporter'] ?? '',
        'responder' => $_POST['responder'] ?? '',
        'status' => $_POST['status'] ?? '',
        'date' => $_POST['date'] ?? '',
        'call_no' => $_POST['call_no'] ?? '',
        'project_contact' => isset($_POST['project_contact']),
        'case_no' => $_POST['case_no'] ?? '',
        'company_name' => $_POST['company_name'] ?? '',
        'customer_name' => $_POST['customer_name'] ?? '',
        'honorific' => $_POST['honorific'] ?? '様',
        'updated_at' => date('Y-m-d H:i:s')
    );

    // バリデーション
    if (empty($trouble['trouble_content'])) {
        $message = 'トラブル内容を入力してください';
        $messageType = 'error';
    } else {
        // troublesが存在しない場合は初期化
        if (!isset($data['troubles'])) {
            $data['troubles'] = array();
        }

        if ($trouble['id']) {
            // 更新
            foreach ($data['troubles'] as &$t) {
                if ($t['id'] === $trouble['id']) {
                    $t = $trouble;
                    break;
                }
            }
            $message = 'トラブル対応を更新しました';
        } else {
            // 新規追加
            $maxId = 0;
            foreach ($data['troubles'] ?? array() as $t) {
                if (isset($t['id']) && $t['id'] > $maxId) {
                    $maxId = $t['id'];
                }
            }
            $trouble['id'] = $maxId + 1;
            $trouble['created_at'] = date('Y-m-d H:i:s');
            if (!isset($data['troubles'])) {
                $data['troubles'] = array();
            }
            $data['troubles'][] = $trouble;
            $message = 'トラブル対応を登録しました';
        }

        saveData($data);
        $messageType = 'success';

        // 成功時は一覧にリダイレクト
        header('Location: /pages/troubles.php?message=' . urlencode($message));
        exit;
    }
}

// 従業員リスト取得
$employees = $data['employees'] ?? array();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $isEdit ? 'トラブル対応編集' : 'トラブル対応登録'; ?></title>
    <link rel="stylesheet" href="/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .btn-submit {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
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
            padding: 12px 30px;
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
        .btn-delete {
            background: #f44336;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            float: right;
        }
        .btn-delete:hover {
            background: #d32f2f;
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
        .required {
            color: #f44336;
            margin-left: 4px;
        }
        .form-hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <?php include '../functions/header.php'; ?>

    <div class="form-container">
        <h1><?php echo $isEdit ? 'トラブル対応編集' : 'トラブル対応登録'; ?></h1>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" id="troubleForm">
                <input type="hidden" name="id" value="<?php echo $trouble['id'] ?? ''; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>日付<span class="required">*</span></label>
                        <input type="text" name="date" value="<?php echo htmlspecialchars($trouble['date']); ?>" required>
                        <div class="form-hint">例: 2025/9/2</div>
                    </div>
                    <div class="form-group">
                        <label>コールNo</label>
                        <input type="text" name="call_no" value="<?php echo htmlspecialchars($trouble['call_no']); ?>">
                        <div class="form-hint">例: 25090201</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>P番号<span class="required">*</span></label>
                    <input type="text"
                           name="pj_number"
                           value="<?php echo htmlspecialchars($trouble['pj_number'] ?? $trouble['project_name'] ?? ''); ?>"
                           placeholder="例: 20250119-001"
                           list="pj_list"
                           required>
                    <datalist id="pj_list">
                        <?php foreach ($data['projects'] ?? array() as $proj): ?>
                            <option value="<?php echo htmlspecialchars($proj['id']); ?>">
                                <?php echo htmlspecialchars($proj['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-hint">入力後に存在しない場合は新規登録が必要です</div>
                </div>

                <div class="form-group">
                    <label>トラブル内容<span class="required">*</span></label>
                    <textarea name="trouble_content" required><?php echo htmlspecialchars($trouble['trouble_content']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>対応内容</label>
                    <textarea name="response_content"><?php echo htmlspecialchars($trouble['response_content']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>記入者<span class="required">*</span></label>
                        <select name="reporter" required>
                            <option value="">選択してください</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['name']); ?>"
                                    <?php echo $trouble['reporter'] === $emp['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>対応者<span class="required">*</span></label>
                        <select name="responder" required>
                            <option value="">選択してください</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['name']); ?>"
                                    <?php echo $trouble['responder'] === $emp['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>状態<span class="required">*</span></label>
                    <select name="status" required>
                        <option value="未対応" <?php echo $trouble['status'] === '未対応' ? 'selected' : ''; ?>>未対応</option>
                        <option value="対応中" <?php echo $trouble['status'] === '対応中' ? 'selected' : ''; ?>>対応中</option>
                        <option value="保留" <?php echo $trouble['status'] === '保留' ? 'selected' : ''; ?>>保留</option>
                        <option value="完了" <?php echo $trouble['status'] === '完了' ? 'selected' : ''; ?>>完了</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>案件No</label>
                        <input type="text" name="case_no" value="<?php echo htmlspecialchars($trouble['case_no']); ?>">
                        <div class="form-hint">例: T1, T2</div>
                    </div>
                    <div class="form-group">
                        <label>社名</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($trouble['company_name']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>お客様お名前</label>
                        <input type="text" name="customer_name" value="<?php echo htmlspecialchars($trouble['customer_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>敬称</label>
                        <select name="honorific">
                            <option value="様" <?php echo $trouble['honorific'] === '様' ? 'selected' : ''; ?>>様</option>
                            <option value="殿" <?php echo $trouble['honorific'] === '殿' ? 'selected' : ''; ?>>殿</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="project_contact" name="project_contact"
                            <?php echo $trouble['project_contact'] ? 'checked' : ''; ?>>
                        <label for="project_contact" style="margin: 0;">プロジェクトコンタクト</label>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn-submit">
                        <?php echo $isEdit ? '更新' : '登録'; ?>
                    </button>
                    <a href="/pages/troubles.php" class="btn-cancel">キャンセル</a>

                    <?php if ($isEdit && canEdit()): ?>
                        <button type="button" class="btn-delete" onclick="confirmDelete()">削除</button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($isEdit && canEdit()): ?>
            <!-- 削除用フォーム（CSRF対策） -->
            <form id="deleteForm" method="POST" action="/forms/trouble-delete.php" style="display: none;">
                <input type="hidden" name="id" value="<?php echo $trouble['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmDelete() {
            if (confirm('このトラブル対応を削除してもよろしいですか？')) {
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
