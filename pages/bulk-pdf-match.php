<?php
/**
 * PDF一括マッチング＆スプレッドシート反映ページ
 * 設定されたPDFファイルから金額を抽出し、スプレッドシートのデータと照合
 */
require_once '../api/auth.php';
require_once '../api/google-drive.php';
require_once '../api/google-sheets.php';

// 編集者以上のみアクセス可能
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

$drive = new GoogleDriveClient();
$sheets = new GoogleSheetsClient();

$error = null;
$message = null;
$matchResults = [];

// 設定ファイルのパス
$configFile = __DIR__ . '/../config/pdf-sources.json';

// 設定を読み込み
function loadPdfSources($configFile) {
    if (!file_exists($configFile)) {
        return [];
    }
    $data = json_decode(file_get_contents($configFile), true);
    return $data['sources'] ?? [];
}

// 設定を保存
function savePdfSources($configFile, $sources) {
    $data = ['sources' => $sources, 'updated_at' => date('Y-m-d H:i:s')];
    file_put_contents($configFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$pdfSources = loadPdfSources($configFile);

// 年月を取得
$yearMonth = $_GET['ym'] ?? $_POST['ym'] ?? date('Y.m');
if (preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $m)) {
    $yearMonth = $m[1] . '.' . ltrim($m[2], '0');
} elseif (preg_match('/^(\d{4})(\d{2})$/', $yearMonth, $m)) {
    $yearMonth = $m[1] . '.' . ltrim($m[2], '0');
}

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// PDFソース追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_source'])) {
    $fileId = trim($_POST['file_id'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');

    if (!empty($fileId) && !empty($bankName)) {
        try {
            $fileInfo = $drive->getFileInfo($fileId);
            $pdfSources[] = [
                'file_id' => $fileId,
                'file_name' => $fileInfo['name'] ?? 'Unknown',
                'bank_name' => $bankName
            ];
            savePdfSources($configFile, $pdfSources);
            $message = 'PDFソースを追加しました';
        } catch (Exception $e) {
            $error = 'ファイル情報の取得に失敗: ' . $e->getMessage();
        }
    }
}

// PDFソース削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_source'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $index = intval($_POST['source_index']);
        if (isset($pdfSources[$index])) {
            $deletedSource = $pdfSources[$index];
            array_splice($pdfSources, $index, 1);
            savePdfSources($configFile, $pdfSources);
            writeAuditLog('delete', 'pdf_source', 'PDFソースを削除', ['source' => $deletedSource]);
            $message = 'PDFソースを削除しました';
        }
    }
}

// PDFキャッシュ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    $cacheDir = __DIR__ . '/../cache/pdf';
    $count = 0;
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*.json') as $file) {
            unlink($file);
            $count++;
        }
    }
    $message = "PDFキャッシュを削除しました（{$count}件）";
}

// 一括照合実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_match'])) {
    // スプレッドシートから銀行データを取得
    $sheetData = $sheets->getRepaymentDataByYearMonth($yearMonth);

    if (!$sheetData || empty($sheetData['data'])) {
        $error = "スプレッドシートの {$yearMonth} のデータが見つかりません";
    } else {
        foreach ($pdfSources as $source) {
            try {
                // キャッシュから金額を取得（高速化）
                $cacheDir = __DIR__ . '/../cache/pdf';
                $cacheFile = $cacheDir . '/' . md5($source['file_id']) . '.json';
                $amounts = [];
                $fromCache = false;
                $cacheTTL = 86400 * 30; // 30日間キャッシュ有効

                if (file_exists($cacheFile)) {
                    $cached = json_decode(file_get_contents($cacheFile), true);
                    // キャッシュが有効期限内なら更新チェックをスキップ
                    if ($cached && isset($cached['cached_at']) && (time() - $cached['cached_at']) < $cacheTTL && !empty($cached['amounts'])) {
                        $amounts = $cached['amounts'];
                        $fromCache = true;
                    }
                }

                // キャッシュがない・期限切れの場合はPDFからテキスト抽出
                if (empty($amounts)) {
                    $text = $drive->extractTextFromPdf($source['file_id']);
                    $amounts = $drive->extractAmountsFromText($text);
                }

                // 該当銀行のデータを検索
                $bankMatches = [];
                foreach ($sheetData['data'] as $key => $bankData) {
                    // 銀行名が一致するかチェック
                    if (mb_strpos($key, $source['bank_name']) !== false ||
                        mb_strpos($source['bank_name'], $bankData['bankName'] ?? '') !== false ||
                        mb_strpos($bankData['bankName'] ?? '', $source['bank_name']) !== false) {

                        // 完済済みはスキップ
                        if ($bankData['principal'] === 0 && $bankData['interest'] === 0 && $bankData['balance'] === 0) {
                            $bankData['isPaidOff'] = true;
                            $bankData['matched'] = false;
                            $bankData['key'] = $key;
                            $bankMatches[] = $bankData;
                            continue;
                        }

                        $total = $bankData['total'];
                        $matchMethod = null; // マッチ方法を記録

                        // 1. 単一金額で厳密比較
                        $matched = in_array($total, $amounts, true);
                        if ($matched) {
                            $matchMethod = 'single';
                        }

                        // 2. 単一金額で許容範囲（±1円）チェック
                        if (!$matched) {
                            foreach ($amounts as $pdfAmount) {
                                if (abs($total - $pdfAmount) <= 1) {
                                    $matched = true;
                                    $matchMethod = 'single_fuzzy';
                                    break;
                                }
                            }
                        }

                        // 3. 2つの金額の合計でチェック（元金+利息が別々に記載されている場合）
                        $sumPair = null;
                        if (!$matched && count($amounts) >= 2) {
                            $amountCount = count($amounts);
                            for ($i = 0; $i < $amountCount - 1 && !$matched; $i++) {
                                for ($j = $i + 1; $j < $amountCount; $j++) {
                                    $sum = $amounts[$i] + $amounts[$j];
                                    if (abs($total - $sum) <= 1) {
                                        $matched = true;
                                        $matchMethod = 'sum_pair';
                                        $sumPair = [$amounts[$i], $amounts[$j]];
                                        break 2;
                                    }
                                }
                            }
                        }

                        // デバッグ情報を追加
                        $bankData['debug'] = [
                            'total_value' => $total,
                            'total_type' => gettype($total),
                            'raw_principal' => $bankData['raw_principal'] ?? '',
                            'raw_interest' => $bankData['raw_interest'] ?? '',
                            'amounts_sample' => array_slice($amounts, 0, 10),
                            'amounts_types' => array_map('gettype', array_slice($amounts, 0, 5)),
                            'match_method' => $matchMethod,
                            'sum_pair' => $sumPair
                        ];

                        $bankData['matched'] = $matched;
                        $bankData['key'] = $key;
                        $bankData['isPaidOff'] = false;
                        $bankMatches[] = $bankData;
                    }
                }

                $matchResults[] = [
                    'source' => $source,
                    'amounts' => $amounts,
                    'matches' => $bankMatches,
                    'error' => null
                ];
            } catch (Exception $e) {
                $matchResults[] = [
                    'source' => $source,
                    'amounts' => [],
                    'matches' => [],
                    'error' => $e->getMessage()
                ];
            }
        }
    }
}

// 一括色付け実行（バッチ処理で高速化）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_colors'])) {
    $markData = json_decode($_POST['mark_data'] ?? '[]', true);

    try {
        // バッチ処理で一括色付け（1回のAPIリクエスト）
        $result = $sheets->markCellsBatch($markData, $yearMonth);

        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } catch (Exception $e) {
        $error = '色付けエラー: ' . $e->getMessage();
    }
}

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
.bulk-container {
    max-width: 1200px;
}

.config-section {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.config-section h3 {
    margin: 0 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.source-list {
    margin-bottom: 1rem;
}

.source-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

.source-item .bank-name {
    font-weight: 600;
    min-width: 150px;
}

.source-item .file-name {
    flex: 1;
    color: #6b7280;
    font-size: 0.9rem;
}

.add-source-form {
    display: flex;
    gap: 0.5rem;
    align-items: end;
    flex-wrap: wrap;
    padding: 1rem;
    background: #f0fdf4;
    border-radius: 8px;
    margin-top: 1rem;
}

.year-month-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #eff6ff;
    border-radius: 8px;
}

.result-section {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.match-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

.match-row.matched {
    background: #dcfce7;
}

.match-row.unmatched {
    background: #fef3c7;
}

.match-row.paidoff {
    background: #f3f4f6;
    opacity: 0.7;
}

.match-status {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.match-status.ok {
    background: #16a34a;
    color: white;
}

.match-status.ng {
    background: #f59e0b;
    color: white;
}

.match-status.paidoff {
    background: #9ca3af;
    color: white;
}

.amounts-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.amount-tag {
    background: #e0e7ff;
    color: #3730a3;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-family: monospace;
}

.amount-tag.matched {
    background: #dcfce7;
    color: #166534;
}

.apply-section {
    background: #f0fdf4;
    border: 2px solid #86efac;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.summary-inline {
    display: flex;
    gap: 2rem;
    margin-bottom: 1rem;
}

.summary-inline .stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.summary-inline .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}

.summary-inline .stat-value.ok {
    color: #16a34a;
}

.summary-inline .stat-value.ng {
    color: #f59e0b;
}
</style>

<div class="bulk-container">
    <div  class="d-flex justify-between align-center mb-3">
        <h2>一括照合＆色付け</h2>
        <a href="loans.php" class="btn btn-secondary">借入先管理に戻る</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= nl2br(htmlspecialchars($error)) ?></div>
    <?php endif; ?>

    <!-- PDFソース設定 -->
    <div class="config-section">
        <h3>📄 PDFソース設定</h3>
        <p  class="text-gray-500 mb-2">照合に使用するPDFファイルと対応する銀行名を設定してください</p>

        <?php if (empty($pdfSources)): ?>
            <p  class="text-gray-400">PDFソースが設定されていません</p>
        <?php else: ?>
            <div class="source-list">
                <?php foreach ($pdfSources as $index => $source): ?>
                <div class="source-item">
                    <span class="bank-name"><?= htmlspecialchars($source['bank_name']) ?></span>
                    <span class="file-name">📄 <?= htmlspecialchars($source['file_name']) ?></span>
                    <form method="POST"  class="m-0">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="source_index" value="<?= $index ?>">
                        <button type="submit" name="delete_source"         class="btn btn-sm text-red bg-fee2e2">削除</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="add-source-form">
            <?= csrfTokenField() ?>
            <div class="form-group">
                <label      class="d-block text-sm mb-025">銀行名</label>
                <input type="text" name="bank_name"  placeholder="例: 中国銀行" required  class="form-input w-150">
            </div>
            <div       class="form-group flex-1">
                <label      class="d-block text-sm mb-025">Google Drive ファイルID</label>
                <input type="text" name="file_id" class="form-input" placeholder="1abc123xyz..." required>
            </div>
            <button type="submit" name="add_source" class="btn btn-primary">追加</button>
        </form>
        <p    class="text-gray-500 mt-1 text-2xs">
            ※ファイルIDはGoogle DriveのURL（https://drive.google.com/file/d/<strong>ファイルID</strong>/view）から取得できます
        </p>
    </div>

    <?php if (!empty($pdfSources)): ?>
    <!-- 年月選択＆実行 -->
    <div class="year-month-bar">
        <form method="POST"  class="d-flex gap-2 align-center w-full">
            <?= csrfTokenField() ?>
            <label  class="font-medium">対象年月:</label>
            <input type="month" name="ym" value="<?= htmlspecialchars(str_replace('.', '-', preg_replace('/\.(\d)$/', '.0$1', $yearMonth))) ?>" class="form-input w-auto">
            <input type="hidden" name="run_match" value="1">
            <button type="submit"         class="btn btn-primary bg-primary">
                🔍 一括照合を実行
            </button>
            <span  class="ml-auto text-gray-500">
                登録済みPDF: <?= count($pdfSources) ?>件
            </span>
        </form>
        <form method="POST"    class="ml-2" id="clearCacheForm">
            <?= csrfTokenField() ?>
            <button type="submit" name="clear_cache"         class="btn btn-sm bg-warning-light text-b45309">
                🗑️ キャッシュ削除
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($matchResults)): ?>
        <?php
        $totalMatched = 0;
        $totalUnmatched = 0;
        $applyData = [];

        foreach ($matchResults as $result) {
            foreach ($result['matches'] as $match) {
                if (!empty($match['isPaidOff'])) continue;
                if ($match['matched']) {
                    $totalMatched++;
                    $applyData[] = [
                        'bankName' => ($match['bankName'] ?? '') . ($match['loanAmount'] ? '（' . $match['loanAmount'] . '）' : ''),
                        'amount' => $match['total'],
                        'startCol' => $match['startCol']
                    ];
                } else {
                    $totalUnmatched++;
                }
            }
        }
        ?>

        <!-- 結果サマリー -->
        <div class="config-section">
            <div class="summary-inline">
                <div class="stat">
                    <span class="stat-value ok"><?= $totalMatched ?></span>
                    <span>一致</span>
                </div>
                <div class="stat">
                    <span class="stat-value ng"><?= $totalUnmatched ?></span>
                    <span>不一致</span>
                </div>
            </div>
        </div>

        <!-- 各PDFの結果 -->
        <?php foreach ($matchResults as $result): ?>
        <div class="result-section">
            <h4     class="mb-075-m">
                <?= htmlspecialchars($result['source']['bank_name']) ?>
                <span  class="font-normal text-gray-500 text-sm">
                    - <?= htmlspecialchars($result['source']['file_name']) ?>
                </span>
            </h4>

            <?php if ($result['error']): ?>
                <div   class="alert alert-error m-0"><?= htmlspecialchars($result['error']) ?></div>
            <?php elseif (empty($result['matches'])): ?>
                <p  class="text-gray-400 m-0">該当する銀行データがスプレッドシートに見つかりません</p>
            <?php else: ?>
                <?php
                $matchedAmounts = [];
                foreach ($result['matches'] as $m) {
                    if ($m['matched']) $matchedAmounts[] = $m['total'];
                }
                ?>

                <div   class="mb-075">
                    <small  class="text-gray-500">抽出金額:</small>
                    <div class="amounts-preview">
                        <?php foreach (array_slice($result['amounts'], 0, 10) as $amt): ?>
                        <span class="amount-tag <?= in_array($amt, $matchedAmounts) ? 'matched' : '' ?>">¥<?= number_format($amt) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($result['amounts']) > 10): ?>
                        <span class="amount-tag">他<?= count($result['amounts']) - 10 ?>件</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php foreach ($result['matches'] as $match): ?>
                <div class="match-row <?= !empty($match['isPaidOff']) ? 'paidoff' : ($match['matched'] ? 'matched' : 'unmatched') ?>">
                    <span class="match-status <?= !empty($match['isPaidOff']) ? 'paidoff' : ($match['matched'] ? 'ok' : 'ng') ?>">
                        <?= !empty($match['isPaidOff']) ? '完済' : ($match['matched'] ? '一致' : 'なし') ?>
                    </span>
                    <span  class="min-w-100"><?= htmlspecialchars($match['loanAmount'] ?? '') ?></span>
                    <span     class="font-mono">
                        ¥<?= number_format($match['principal']) ?> + ¥<?= number_format($match['interest']) ?> =
                        <strong>¥<?= number_format($match['total']) ?></strong>
                    </span>
                    <?php if ($match['matched'] && !empty($match['debug']['match_method']) && $match['debug']['match_method'] === 'sum_pair'): ?>
                    <span        class="text-xs ml-1 text-059">
                        (¥<?= number_format($match['debug']['sum_pair'][0]) ?> + ¥<?= number_format($match['debug']['sum_pair'][1]) ?>)
                    </span>
                    <?php endif; ?>
                    <?php if (!$match['matched'] && !empty($match['debug'])): ?>
                    <div        class="text-xs text-gray-500 mt-1 p-1 rounded bg-f8fafc">
                        <strong>デバッグ:</strong><br>
                        スプシ生データ: 元金="<?= htmlspecialchars($match['debug']['raw_principal']) ?>" 利息="<?= htmlspecialchars($match['debug']['raw_interest']) ?>"<br>
                        スプシ合計: <?= $match['debug']['total_value'] ?> (<?= $match['debug']['total_type'] ?>)<br>
                        PDF金額(上位10件): <?= implode(', ', $match['debug']['amounts_sample']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($totalMatched > 0): ?>
        <div class="apply-section">
            <h3  class="mt-0">🎨 スプレッドシートに色付け</h3>
            <p>一致した <?= $totalMatched ?> 件をスプレッドシートに緑色で反映します</p>
            <form method="POST">
                <?= csrfTokenField() ?>
                <input type="hidden" name="ym" value="<?= htmlspecialchars($yearMonth) ?>">
                <input type="hidden" name="mark_data" value="<?= htmlspecialchars(json_encode($applyData)) ?>">
                <button type="submit" name="apply_colors"         class="btn btn-primary bg-success">
                    ✓ 一括で色付けを実行
                </button>
            </form>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script<?= nonceAttr() ?>>
// キャッシュ削除フォームの確認
document.getElementById('clearCacheForm')?.addEventListener('submit', function(e) {
    if (!confirm('PDFキャッシュを削除しますか？\n次回の照合時に再読み込みされます。')) {
        e.preventDefault();
    }
});
</script>

<?php require_once '../functions/footer.php'; ?>
