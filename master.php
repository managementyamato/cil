<?php
require_once 'config.php';
$data = getData();

$message = '';
$messageType = '';

// PJ追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pj'])) {
    $pjNumber = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['pj_number'] ?? ''));
    $pjName = trim($_POST['pj_name'] ?? '');

    if ($pjNumber && $pjName) {
        // 重複チェック
        $exists = false;
        foreach ($data['projects'] as $p) {
            if ($p['id'] === $pjNumber) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $message = 'このPJ番号は既に登録されています';
            $messageType = 'danger';
        } else {
            $data['projects'][] = ['id' => $pjNumber, 'name' => $pjName];
            saveData($data);
            $message = 'PJを追加しました';
            $messageType = 'success';
        }
    }
}

// PJ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pj'])) {
    $deleteId = $_POST['delete_pj'];
    $data['projects'] = array_values(array_filter($data['projects'], fn($p) => $p['id'] !== $deleteId));
    saveData($data);
    $message = 'PJを削除しました';
    $messageType = 'success';
}

// 担当者追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignee'])) {
    $assigneeName = trim($_POST['assignee_name'] ?? '');

    if ($assigneeName) {
        // 重複チェック
        $exists = false;
        foreach ($data['assignees'] as $a) {
            if ($a['name'] === $assigneeName) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $message = 'この担当者は既に登録されています';
            $messageType = 'danger';
        } else {
            $maxId = 0;
            foreach ($data['assignees'] as $a) {
                if ($a['id'] > $maxId) $maxId = $a['id'];
            }
            $data['assignees'][] = ['id' => $maxId + 1, 'name' => $assigneeName];
            saveData($data);
            $message = '担当者を追加しました';
            $messageType = 'success';
        }
    }
}

// 担当者削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignee'])) {
    $deleteId = (int)$_POST['delete_assignee'];
    $data['assignees'] = array_values(array_filter($data['assignees'], fn($a) => $a['id'] !== $deleteId));
    saveData($data);
    $message = '担当者を削除しました';
    $messageType = 'success';
}

// スプレッドシートインポート処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_spreadsheet'])) {
    $url = trim($_POST['spreadsheet_url'] ?? '');
    $type = $_POST['import_type'] ?? 'pj';

    if ($url) {
        // URLをCSV形式に変換
        if (strpos($url, '/edit') !== false) {
            preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches);
            if (isset($matches[1])) {
                $url = 'https://docs.google.com/spreadsheets/d/' . $matches[1] . '/export?format=csv';
            }
        }

        $csvContent = @file_get_contents($url);

        if ($csvContent === false) {
            $message = 'スプレッドシートを取得できませんでした。公開設定を確認してください。';
            $messageType = 'danger';
        } else {
            $lines = explode("\n", $csvContent);
            $headers = str_getcsv(array_shift($lines));
            $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

            $addedPj = 0;
            $addedAssignee = 0;
            $addedTrouble = 0;
            $skipped = 0;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $values = str_getcsv($line);
                $row = array_combine($headers, array_pad($values, count($headers), ''));

                if ($type === 'pj') {
                    // PJマスタインポート
                    $pjNumber = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $row['pj番号'] ?? ''));
                    $pjName = $row['案件名'] ?? $row['現場名'] ?? '';
                    $assignee = $row['ya担当'] ?? $row['担当者'] ?? '';

                    if ($pjNumber && $pjName && $pjName !== '-') {
                        $exists = false;
                        foreach ($data['projects'] as $p) {
                            if ($p['id'] === $pjNumber) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $data['projects'][] = ['id' => $pjNumber, 'name' => $pjName];
                            $addedPj++;
                        }
                    }

                    if ($assignee && $assignee !== '-') {
                        $exists = false;
                        foreach ($data['assignees'] as $a) {
                            if ($a['name'] === $assignee) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $maxId = 0;
                            foreach ($data['assignees'] as $a) {
                                if ($a['id'] > $maxId) $maxId = $a['id'];
                            }
                            $data['assignees'][] = ['id' => $maxId + 1, 'name' => $assignee];
                            $addedAssignee++;
                        }
                    }
                } else {
                    // トラブルデータインポート
                    // 柔軟な列名検索（現場名 or プロジェクト番号など）
                    $pjRaw = '';
                    foreach ($row as $key => $value) {
                        $keyLower = strtolower($key);
                        if (strpos($keyLower, '現場') !== false ||
                            strpos($keyLower, 'プロジェクト') !== false ||
                            strpos($keyLower, 'pj') !== false) {
                            $pjRaw = $value;
                            break;
                        }
                    }

                    // PJ番号抽出（P17, p8などを抽出）
                    $pjNumber = '';
                    if (preg_match('/[pP](\d+)/', $pjRaw, $matches)) {
                        $pjNumber = 'p' . $matches[1];
                    } else {
                        $pjNumber = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $pjRaw));
                    }

                    // PJ検索
                    $foundPj = null;
                    foreach ($data['projects'] as $p) {
                        if ($p['id'] === $pjNumber) {
                            $foundPj = $p;
                            break;
                        }
                    }

                    if (!$foundPj && $pjRaw) {
                        foreach ($data['projects'] as $p) {
                            if (strpos($p['name'], $pjRaw) !== false || strpos($pjRaw, $p['name']) !== false) {
                                $foundPj = $p;
                                break;
                            }
                        }
                    }

                    if (!$foundPj) {
                        $skipped++;
                        continue;
                    }

                    // 柔軟な列名検索（各フィールド）
                    $content = '';
                    $solution = '';
                    $reporter = '';
                    $assignee = '';
                    $rawStatus = '';
                    $dateRaw = '';

                    foreach ($row as $key => $value) {
                        $keyLower = strtolower($key);
                        if (strpos($keyLower, 'トラブル') !== false || strpos($keyLower, '内容') !== false && !$content) {
                            $content = $value;
                        }
                        if (strpos($keyLower, '対応') !== false && strpos($keyLower, '内容') !== false && !$solution) {
                            $solution = $value;
                        }
                        if (strpos($keyLower, '記入') !== false || strpos($keyLower, '報告') !== false && !$reporter) {
                            $reporter = $value;
                        }
                        if (strpos($keyLower, '対応者') !== false || strpos($keyLower, '担当') !== false && !$assignee) {
                            $assignee = $value;
                        }
                        if (strpos($keyLower, '状態') !== false || strpos($keyLower, 'ステータス') !== false && !$rawStatus) {
                            $rawStatus = $value;
                        }
                        if (strpos($keyLower, '日付') !== false && !$dateRaw) {
                            $dateRaw = $value;
                        }
                    }

                    // ステータス変換
                    $rawStatusLower = strtolower($rawStatus);
                    $status = '未対応';
                    if (strpos($rawStatusLower, '解決') !== false || strpos($rawStatusLower, '完了') !== false) {
                        $status = '完了';
                    } elseif (strpos($rawStatusLower, '対応待ち') !== false || strpos($rawStatusLower, '対応中') !== false) {
                        $status = '対応中';
                    }

                    // トラブル内容が空の場合はスキップ
                    if (empty(trim($content))) {
                        continue;
                    }

                    $maxId = 0;
                    foreach ($data['troubles'] as $t) {
                        if ($t['id'] > $maxId) $maxId = $t['id'];
                    }

                    $createdAt = date('c');
                    if ($dateRaw) {
                        $parsed = strtotime($dateRaw);
                        if ($parsed) $createdAt = date('c', $parsed);
                    }

                    $data['troubles'][] = [
                        'id' => $maxId + 1,
                        'pjNumber' => $foundPj['id'],
                        'pjName' => $foundPj['name'],
                        'deviceType' => 'その他',
                        'content' => $content,
                        'solution' => $solution,
                        'reporter' => $reporter,
                        'assignee' => $assignee,
                        'status' => $status,
                        'createdAt' => $createdAt,
                        'updatedAt' => $createdAt,
                        'history' => [['date' => $createdAt, 'action' => 'スプレッドシートからインポート']]
                    ];
                    $addedTrouble++;
                }
            }

            saveData($data);

            if ($type === 'pj') {
                $message = "PJ {$addedPj}件、担当者 {$addedAssignee}件を追加しました";
            } else {
                $message = "トラブル {$addedTrouble}件を追加しました";
                if ($skipped > 0) {
                    $message .= "（{$skipped}件はPJ未登録のためスキップ）";
                }
            }
            $messageType = 'success';
        }
    }
}

require_once 'header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- スプレッドシートインポート -->
<div class="card">
    <h2 class="card-title">データインポート</h2>

    <div style="background: var(--gray-50); padding: 1rem; border-radius: 8px;">
        <h3 style="font-size: 1rem; margin-bottom: 0.5rem;">📊 スプレッドシートから読み込み</h3>
        <p style="font-size: 0.75rem; color: var(--gray-500); margin-bottom: 1rem;">
            スプシを「ウェブに公開」または「リンクを知っている全員が閲覧可」に設定してURLを入力
        </p>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">インポートタイプ</label>
                <select class="form-select" name="import_type" style="max-width: 300px;">
                    <option value="pj">PJマスタ（PJ番号, 案件名, YA担当）</option>
                    <option value="trouble">トラブルデータ</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">スプレッドシートURL</label>
                <input type="text" class="form-input" name="spreadsheet_url" placeholder="https://docs.google.com/spreadsheets/d/...">
            </div>
            <button type="submit" name="import_spreadsheet" class="btn btn-primary">読み込み</button>
        </form>
    </div>
</div>

<!-- PJマスタ登録 -->
<div class="card">
    <h2 class="card-title">PJマスタ登録</h2>
    <form method="POST">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div class="form-group" style="flex: 0 0 120px;">
                <label class="form-label required">PJ番号</label>
                <input type="text" class="form-input" name="pj_number" placeholder="001" required>
            </div>
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label class="form-label required">現場名</label>
                <input type="text" class="form-input" name="pj_name" placeholder="現場名を入力" required>
            </div>
            <div class="form-group" style="flex: 0 0 auto; display: flex; align-items: flex-end;">
                <button type="submit" name="add_pj" class="btn btn-primary">追加</button>
            </div>
        </div>
    </form>
</div>

<!-- PJ一覧 -->
<div class="card">
    <h2 class="card-title">PJ一覧 <span style="font-size: 0.875rem; color: var(--gray-500);">（<?= count($data['projects']) ?>件）</span></h2>
    <div class="table-wrapper" style="max-height: 400px; overflow-y: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>PJ番号</th>
                    <th>現場名</th>
                    <th>トラブル件数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['projects'] as $pj): ?>
                    <?php
                    $troubleCount = count(array_filter($data['troubles'], fn($t) => $t['pjNumber'] === $pj['id']));
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pj['id']) ?></strong></td>
                        <td><?= htmlspecialchars($pj['name']) ?></td>
                        <td><?= $troubleCount ?>件</td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('削除しますか？');">
                                <input type="hidden" name="delete_pj" value="<?= htmlspecialchars($pj['id']) ?>">
                                <button type="submit" class="btn-icon" title="削除">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($data['projects'])): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--gray-500);">データがありません</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 担当者マスタ -->
<div class="card">
    <h2 class="card-title">担当者マスタ</h2>
    <form method="POST">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label class="form-label required">担当者名</label>
                <input type="text" class="form-input" name="assignee_name" placeholder="担当者名を入力" required>
            </div>
            <div class="form-group" style="flex: 0 0 auto; display: flex; align-items: flex-end;">
                <button type="submit" name="add_assignee" class="btn btn-primary">追加</button>
            </div>
        </div>
    </form>
    <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
        <?php foreach ($data['assignees'] as $a): ?>
            <span style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--gray-100); padding: 0.5rem 1rem; border-radius: 9999px;">
                <?= htmlspecialchars($a['name']) ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('削除しますか？');">
                    <input type="hidden" name="delete_assignee" value="<?= $a['id'] ?>">
                    <button type="submit" style="background: none; border: none; cursor: pointer; color: var(--gray-500);">&times;</button>
                </form>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
