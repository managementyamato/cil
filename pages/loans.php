<?php
require_once '../config/config.php';
require_once '../api/loans-api.php';
require_once '../api/google-drive.php';
require_once '../api/google-sheets.php';
require_once '../api/pdf-processor.php';

// 編集者以上のみアクセス可能
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

$api = new LoansApi();
$driveClient = new GoogleDriveClient();
$sheetsClient = new GoogleSheetsClient();
$message = '';
$error = '';

// セッションからメッセージを取得（Drive連携コールバック用）
if (isset($_SESSION['drive_success'])) {
    $message = $_SESSION['drive_success'];
    unset($_SESSION['drive_success']);
}
if (isset($_SESSION['drive_error'])) {
    $error = $_SESSION['drive_error'];
    unset($_SESSION['drive_error']);
}

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// Drive連携解除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect_drive'])) {
    $driveClient->disconnect();
    $message = 'Google Driveとの連携を解除しました';
}

// 連携フォルダを設定
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_sync_folder'])) {
    $folderId = $_POST['folder_id'] ?? '';
    $folderName = $_POST['folder_name'] ?? '';
    if (!empty($folderId)) {
        $driveClient->saveSyncFolder($folderId, $folderName);
        $message = '連携フォルダを設定しました: ' . $folderName;
    }
}

// 連携フォルダを解除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_sync_folder'])) {
    $driveClient->saveSyncFolder('', '');
    $message = '連携フォルダを解除しました';
}

// Driveキャッシュをクリア
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_drive_cache'])) {
    $driveClient->clearCache();
    $message = 'Driveのキャッシュをクリアしました';
}

// Drive連携URL生成（既存のgoogle-callback.phpを使用）
$driveAuthUrl = '';
$googleConfigFile = __DIR__ . '/../config/google-config.json';
if (file_exists($googleConfigFile)) {
    $googleConfig = json_decode(file_get_contents($googleConfigFile), true);
    if ($googleConfig) {
        $scopes = ['openid', 'email', 'profile', 'https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/spreadsheets'];
        $params = [
            'client_id' => $googleConfig['client_id'],
            'redirect_uri' => $googleConfig['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => 'drive_connect'
        ];
        $driveAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
}

// Driveからデータ取得
$driveFolders = [];
$syncFolder = null;
$periodFolders = [];  // 期フォルダ一覧
$selectedPeriod = $_GET['period'] ?? $_POST['period'] ?? '';  // 選択中の期
$monthlyFolders = [];  // 月次フォルダ一覧
$selectedMonth = $_GET['month'] ?? $_POST['month'] ?? '';  // 選択中の月
$selectedFolderId = $_GET['folder_id'] ?? $_POST['folder_id'] ?? '';
$selectedFileId = $_GET['file_id'] ?? $_POST['file_id'] ?? '';
$folderContents = null;
$fileInfo = null;
$filePreview = null;
$breadcrumbs = [];  // パンくずリスト用

// 遅延読み込みフラグ（初期表示ではAPI呼び出しを最小限に）
$useLazyLoad = true;

if ($driveClient->isConfigured()) {
    try {
        // 連携フォルダ設定を取得（軽量な処理）
        $syncFolder = $driveClient->getSyncFolder();

        // POST時や特定の操作時のみ同期読み込み
        $needsSyncLoad = $_SERVER['REQUEST_METHOD'] === 'POST' ||
                         !empty($selectedFileId) ||
                         !empty($selectedFolderId) ||
                         !empty($selectedMonth);

        if ($needsSyncLoad && $syncFolder && !empty($syncFolder['id'])) {
            $useLazyLoad = false;

            // 連携フォルダ（01_会計業務）内のフォルダを取得
            $contents = $driveClient->listFolderContents($syncFolder['id']);

            // 期フォルダを抽出（「○○期_」パターン）
            foreach ($contents['folders'] as $folder) {
                if (preg_match('/^\d+期_/', $folder['name'])) {
                    $periodFolders[] = $folder;
                }
            }
            // 期フォルダを名前の降順でソート（最新期が先頭）
            usort($periodFolders, fn($a, $b) => strcmp($b['name'], $a['name']));

            // 期が選択されていない場合、最新の期を自動選択
            if (empty($selectedPeriod) && !empty($periodFolders)) {
                $selectedPeriod = $periodFolders[0]['id'];
            }

            // 選択中の期フォルダ内の月次フォルダを取得
            if (!empty($selectedPeriod)) {
                $periodContents = $driveClient->listFolderContents($selectedPeriod);
                foreach ($periodContents['folders'] as $folder) {
                    if (preg_match('/^\d{4}_月次資料$/', $folder['name'])) {
                        $monthlyFolders[] = $folder;
                    }
                }
                // 月次フォルダを名前の降順でソート（最新月が先頭）
                usort($monthlyFolders, fn($a, $b) => strcmp($b['name'], $a['name']));
            }

            // 月が選択されている場合、その中身を取得
            if (!empty($selectedMonth)) {
                $folderContents = $driveClient->listFolderContents($selectedMonth);
            }

            // サブフォルダが選択されている場合
            if (!empty($selectedFolderId)) {
                $folderContents = $driveClient->listFolderContents($selectedFolderId);
                // デバッグ: フォルダの種類を確認
                $folderInfo = $driveClient->getFileInfo($selectedFolderId);
                // ショートカットの場合は実際のフォルダを取得
                if (isset($folderInfo['mimeType']) && $folderInfo['mimeType'] === 'application/vnd.google-apps.shortcut') {
                    if (isset($folderInfo['shortcutDetails']['targetId'])) {
                        $folderContents = $driveClient->listFolderContents($folderInfo['shortcutDetails']['targetId']);
                    }
                }
            }
        } else if (!$syncFolder || empty($syncFolder['id'])) {
            // ルートのフォルダ一覧を取得（連携フォルダ未設定時）
            $driveFolders = $driveClient->listFolders();
            $useLazyLoad = false;
        }

        // ファイル詳細・プレビュー
        if (!empty($selectedFileId)) {
            $fileInfo = $driveClient->getFileInfo($selectedFileId);
            // PDFの場合はプレビューなし（Driveで開くリンクのみ）
        }
    } catch (Exception $e) {
        $error = 'Google Drive接続エラー: ' . $e->getMessage();
    }
}

// PDFテキスト抽出処理
$extractedText = '';
$extractedAmounts = [];
$matchedLoan = null;  // マッチした借入先
$matchedRepayment = null;  // マッチした返済データ
$sheetRepaymentData = null;  // スプレッドシートの返済データ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extract_text']) && !empty($_POST['file_id'])) {
    try {
        $extractedText = $driveClient->extractTextFromPdf($_POST['file_id']);
        $extractedAmounts = $driveClient->extractAmountsFromText($extractedText);

        // 借入先データを取得して照合
        $loansData = $api->getLoans();
        $loans = $loansData['loans'] ?? [];
        $repayments = $loansData['repayments'] ?? [];

        // ファイル名または抽出テキストから銀行名を特定
        $fileName = $fileInfo['name'] ?? '';
        $searchText = $fileName . ' ' . $extractedText;

        foreach ($loans as $loan) {
            // 銀行名がファイル名またはテキストに含まれているか
            if (mb_strpos($searchText, $loan['name']) !== false) {
                $matchedLoan = $loan;

                // 該当する返済データを探す（選択中の月から判定）
                foreach ($repayments as $rep) {
                    if ($rep['loan_id'] === $loan['id']) {
                        $repTotal = ($rep['principal'] ?? 0) + ($rep['interest'] ?? 0);
                        // 抽出金額と返済総額が一致するか確認
                        foreach ($extractedAmounts as $amount) {
                            if ($amount === $repTotal) {
                                $matchedRepayment = $rep;
                                $matchedRepayment['total'] = $repTotal;
                                break 2;
                            }
                        }
                    }
                }
                break;
            }
        }

        // PDFファイル名から年月を抽出してスプレッドシートのデータを取得
        $pdfFileNameForExtract = $fileInfo['name'] ?? '';
        if (preg_match('/^(\d{2})(\d{2})_/', $pdfFileNameForExtract, $ymMatches)) {
            $extractedYearMonth = '20' . $ymMatches[1] . '.' . $ymMatches[2];

            // 銀行名も抽出
            $extractedBankName = null;
            if (preg_match('/_([^_]+)\.pdf$/i', $pdfFileNameForExtract, $bnMatches)) {
                $extractedBankName = $bnMatches[1];
            }
            // matchedLoanがあればそちらを優先
            if ($matchedLoan) {
                $extractedBankName = $matchedLoan['name'];
            }

            // スプレッドシートから該当データを取得
            if ($extractedBankName) {
                $sheetRepaymentData = $sheetsClient->getBankRepaymentData($extractedYearMonth, $extractedBankName);

                // 複数の借入がある場合は配列で返ってくる
                if ($sheetRepaymentData) {
                    // 単一データの場合
                    if (isset($sheetRepaymentData['total'])) {
                        $sheetRepaymentData['yearMonth'] = $extractedYearMonth;
                        $sheetRepaymentData['searchBankName'] = $extractedBankName;
                        $sheetRepaymentData = [$sheetRepaymentData]; // 配列に統一
                    } else {
                        // 複数データの場合
                        foreach ($sheetRepaymentData as &$data) {
                            $data['yearMonth'] = $extractedYearMonth;
                            $data['searchBankName'] = $extractedBankName;
                        }
                        unset($data);
                    }

                    // PDFの金額と一致するものを探す
                    foreach ($sheetRepaymentData as &$data) {
                        $data['matchedAmount'] = null;
                        foreach ($extractedAmounts as $amount) {
                            if ($amount === $data['total']) {
                                $data['matchedAmount'] = $amount;
                                break;
                            }
                        }
                    }
                    unset($data);
                }
            }
        }

        $message = 'テキスト抽出が完了しました';
    } catch (Exception $e) {
        $error = 'テキスト抽出エラー: ' . $e->getMessage();
    }
}

// スプレッドシート照合・色変更処理
$sheetsResult = null;
$sheetsDebugInfo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_spreadsheet'])) {
    try {
        $markAmount = intval($_POST['mark_amount'] ?? 0);
        $markYearMonth = $_POST['mark_year_month'] ?? '';
        $markBankName = $_POST['mark_bank_name'] ?? null;

        if ($markAmount <= 0) {
            throw new Exception('金額が指定されていません');
        }
        if (empty($markYearMonth)) {
            throw new Exception('年月が指定されていません');
        }

        // デバッグ情報を取得
        $sheetsDebugInfo = [
            'searchYearMonth' => $markYearMonth,
            'columnBSample' => $sheetsClient->getColumnBSample()
        ];

        $sheetsResult = $sheetsClient->markMatchingCell($markAmount, $markYearMonth, $markBankName);

        if ($sheetsResult['success']) {
            $message = $sheetsResult['message'];
        } else {
            $error = $sheetsResult['message'];
        }
    } catch (Exception $e) {
        $error = 'スプレッドシート更新エラー: ' . $e->getMessage();
        // エラー時もデバッグ情報を表示
        if (!$sheetsDebugInfo) {
            try {
                $sheetsDebugInfo = [
                    'searchYearMonth' => $markYearMonth ?? '',
                    'columnBSample' => $sheetsClient->getColumnBSample()
                ];
            } catch (Exception $e2) {
                // 無視
            }
        }
    }
}

// 一括色付け処理（一致した全エントリに色を付ける）
$bulkMarkResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_mark_spreadsheet'])) {
    try {
        $bulkEntries = json_decode($_POST['bulk_entries'] ?? '[]', true);
        $bulkYearMonth = $_POST['bulk_year_month'] ?? '';

        if (empty($bulkEntries)) {
            throw new Exception('色付け対象のエントリがありません');
        }
        if (empty($bulkYearMonth)) {
            throw new Exception('年月が指定されていません');
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($bulkEntries as $entry) {
            $amount = intval($entry['amount'] ?? 0);
            $startCol = intval($entry['startCol'] ?? 0);

            if ($amount <= 0) continue;

            try {
                // 金額で該当セルを特定して色付け
                $result = $sheetsClient->markMatchingCell($amount, $bulkYearMonth, null);
                if ($result['success']) {
                    $successCount++;
                    $bulkMarkResults[] = [
                        'success' => true,
                        'message' => $result['message']
                    ];
                } else {
                    $failCount++;
                    $bulkMarkResults[] = [
                        'success' => false,
                        'message' => $result['message']
                    ];
                }
            } catch (Exception $e) {
                $failCount++;
                $bulkMarkResults[] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        if ($successCount > 0) {
            $message = "{$successCount}件の色付けが完了しました";
            if ($failCount > 0) {
                $message .= "（{$failCount}件失敗）";
            }
        } else {
            $error = '色付けに失敗しました';
        }
    } catch (Exception $e) {
        $error = '一括色付けエラー: ' . $e->getMessage();
    }
}

// 一括照合処理（フォルダ内の全PDFを照合）
$bulkMatchResults = null;
$bulkMatchYearMonth = '';
$pdfProcessor = new PdfProcessor();  // キャッシュ付きPDF処理

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_match_folder'])) {
    try {
        $matchFolderId = $_POST['match_folder_id'] ?? '';
        $matchFolderName = $_POST['match_folder_name'] ?? '';

        if (empty($matchFolderId)) {
            throw new Exception('フォルダが指定されていません');
        }

        // フォルダ名から年月を抽出（例: "2512_銀行明細" → "2025.12"）
        if (preg_match('/^(\d{2})(\d{2})_/', $matchFolderName, $ym)) {
            $bulkMatchYearMonth = '20' . $ym[1] . '.' . $ym[2];
        } else {
            throw new Exception('フォルダ名から年月を抽出できません（YYMM_形式が必要です）');
        }

        // フォルダ内のファイル一覧を取得
        $matchFolderContents = $driveClient->listFolderContents($matchFolderId);
        $pdfFiles = [];

        foreach ($matchFolderContents['files'] ?? [] as $file) {
            if (stripos($file['mimeType'] ?? '', 'pdf') !== false ||
                stripos($file['name'] ?? '', '.pdf') !== false) {
                $pdfFiles[] = $file;
            }
        }

        if (empty($pdfFiles)) {
            throw new Exception('PDFファイルが見つかりません');
        }

        // スプレッドシートの全銀行データを取得
        $sheetData = $sheetsClient->getRepaymentDataByYearMonth($bulkMatchYearMonth);
        if (!$sheetData['success']) {
            throw new Exception($sheetData['message']);
        }

        $bulkMatchResults = [
            'yearMonth' => $bulkMatchYearMonth,
            'folderName' => $matchFolderName,
            'matches' => [],
            'noMatches' => [],
            'errors' => [],
            'cacheHits' => 0
        ];

        // 各PDFを処理（キャッシュ付き）
        foreach ($pdfFiles as $pdfFile) {
            $fileName = $pdfFile['name'];
            $fileId = $pdfFile['id'];
            $modifiedTime = $pdfFile['modifiedTime'] ?? null;

            // ファイル名から銀行名を抽出
            $bankNameFromFile = '';
            if (preg_match('/_([^_]+)\.pdf$/i', $fileName, $bn)) {
                $bankNameFromFile = $bn[1];
            }

            try {
                // PDFからテキスト抽出（キャッシュ優先）
                $pdfResult = $pdfProcessor->processSinglePdf($fileId, $modifiedTime);
                if (!$pdfResult['success']) {
                    throw new Exception($pdfResult['error'] ?? 'PDF処理エラー');
                }
                if ($pdfResult['from_cache']) {
                    $bulkMatchResults['cacheHits']++;
                }
                $pdfAmounts = $pdfResult['amounts'];

                if (empty($pdfAmounts)) {
                    $bulkMatchResults['errors'][] = [
                        'fileName' => $fileName,
                        'bankName' => $bankNameFromFile,
                        'message' => '金額を抽出できませんでした'
                    ];
                    continue;
                }

                // スプレッドシートデータと照合（同一銀行の複数借入に対応）
                // 元金、利息、合計のいずれかが一致すればマッチとする
                $matchedAmounts = [];  // マッチした金額を記録（重複防止）
                foreach ($sheetData['data'] as $key => $bankData) {
                    $sheetTotal = $bankData['total'];
                    $sheetPrincipal = $bankData['principal'] ?? 0;
                    $sheetInterest = $bankData['interest'] ?? 0;
                    $sheetBankName = $bankData['bankName'] ?? '';

                    // 完済済みはスキップ
                    if ($sheetTotal === 0) continue;

                    // 照合対象の金額リスト（合計、元金、利息）
                    $sheetAmountsToCheck = [$sheetTotal];
                    if ($sheetPrincipal > 0) $sheetAmountsToCheck[] = $sheetPrincipal;
                    if ($sheetInterest > 0) $sheetAmountsToCheck[] = $sheetInterest;

                    // PDFの金額とスプレッドシートの金額を照合
                    foreach ($pdfAmounts as $pdfAmount) {
                        foreach ($sheetAmountsToCheck as $sheetAmount) {
                            // 既にこの金額でマッチ済みならスキップ
                            $matchKey = $pdfAmount . '_' . $sheetBankName . '_' . $sheetAmount;
                            if (isset($matchedAmounts[$matchKey])) continue;

                            if ($pdfAmount === $sheetAmount) {
                                // 銀行名の一致も確認（表記ゆれ対応）
                                $nameMatch = false;
                                if (!empty($bankNameFromFile) && !empty($sheetBankName)) {
                                    // ひらがな→カタカナ変換して比較
                                    $n1 = mb_convert_kana($bankNameFromFile, 'C', 'UTF-8');
                                    $n2 = mb_convert_kana($sheetBankName, 'C', 'UTF-8');
                                    $nameMatch = (mb_strpos($n1, $n2) !== false || mb_strpos($n2, $n1) !== false);
                                }

                                // マッチの種類を判定
                                $matchType = 'total';
                                if ($pdfAmount === $sheetPrincipal) $matchType = 'principal';
                                elseif ($pdfAmount === $sheetInterest) $matchType = 'interest';

                                $bulkMatchResults['matches'][] = [
                                    'fileName' => $fileName,
                                    'fileId' => $fileId,
                                    'pdfBankName' => $bankNameFromFile,
                                    'sheetBankName' => $sheetBankName,
                                    'loanAmount' => $bankData['loanAmount'] ?? '',
                                    'amount' => $pdfAmount,
                                    'matchType' => $matchType,
                                    'sheetTotal' => $sheetTotal,
                                    'startCol' => $bankData['startCol'],
                                    'nameMatch' => $nameMatch
                                ];
                                $matchedAmounts[$matchKey] = true;
                                // break しない：同じPDFで複数の借入先にマッチする可能性があるため続行
                            }
                        }
                    }
                }

                if (empty($matchedAmounts)) {
                    // 不一致時はスプレッドシートの期待金額も記録
                    $expectedAmounts = [];
                    foreach ($sheetData['data'] as $bankData) {
                        if ($bankData['total'] > 0) {
                            $expectedAmounts[] = [
                                'bankName' => $bankData['bankName'],
                                'total' => $bankData['total'],
                                'principal' => $bankData['principal'] ?? 0,
                                'interest' => $bankData['interest'] ?? 0
                            ];
                        }
                    }
                    $bulkMatchResults['noMatches'][] = [
                        'fileName' => $fileName,
                        'bankName' => $bankNameFromFile,
                        'pdfAmounts' => $pdfAmounts,
                        'expectedAmounts' => $expectedAmounts
                    ];
                }
            } catch (Exception $e) {
                $bulkMatchResults['errors'][] = [
                    'fileName' => $fileName,
                    'bankName' => $bankNameFromFile,
                    'message' => $e->getMessage()
                ];
            }
        }

        $matchCount = count($bulkMatchResults['matches']);
        $noMatchCount = count($bulkMatchResults['noMatches']);
        $errorCount = count($bulkMatchResults['errors']);

        $message = "一括照合完了: 一致 {$matchCount}件";
        if ($noMatchCount > 0) {
            $message .= "、不一致 {$noMatchCount}件";
        }
        if ($errorCount > 0) {
            $message .= "、エラー {$errorCount}件";
        }

    } catch (Exception $e) {
        $error = '一括照合エラー: ' . $e->getMessage();
    }
}

// 一括照合結果からの一括色付け
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_bulk_match'])) {
    try {
        $applyEntries = json_decode($_POST['apply_entries'] ?? '[]', true);
        $applyYearMonth = $_POST['apply_year_month'] ?? '';

        if (empty($applyEntries)) {
            throw new Exception('適用対象がありません');
        }
        if (empty($applyYearMonth)) {
            throw new Exception('年月が指定されていません');
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($applyEntries as $entry) {
            $amount = intval($entry['amount'] ?? 0);
            if ($amount <= 0) continue;

            try {
                $result = $sheetsClient->markMatchingCell($amount, $applyYearMonth, null);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (Exception $e) {
                $failCount++;
            }
        }

        if ($successCount > 0) {
            $message = "一括登録完了: {$successCount}件の色付けが完了しました";
            if ($failCount > 0) {
                $message .= "（{$failCount}件失敗）";
            }
        } else {
            $error = '色付けに失敗しました';
        }
    } catch (Exception $e) {
        $error = '一括登録エラー: ' . $e->getMessage();
    }
}

// 現在選択中の期の名前を取得
$selectedPeriodName = '';
foreach ($periodFolders as $pf) {
    if ($pf['id'] === $selectedPeriod) {
        $selectedPeriodName = $pf['name'];
        break;
    }
}

// 借入先追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_loan'])) {
    $loan = array(
        'name' => trim($_POST['name'] ?? ''),
        'initial_amount' => intval($_POST['initial_amount'] ?? 0),
        'start_date' => $_POST['start_date'] ?? '',
        'interest_rate' => floatval($_POST['interest_rate'] ?? 0),
        'repayment_day' => intval($_POST['repayment_day'] ?? 25),
        'notes' => trim($_POST['notes'] ?? '')
    );

    if (empty($loan['name'])) {
        $error = '借入先名を入力してください';
    } else {
        $api->addLoan($loan);
        $message = '借入先を追加しました';
    }
}

// 借入先削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_loan'])) {
    $loanId = $_POST['loan_id'] ?? '';
    if ($loanId) {
        $api->deleteLoan($loanId);
        $message = '借入先を削除しました';
    }
}

$loans = $api->getLoans();

require_once '../functions/header.php';
?>

<style>
.loans-container {
    max-width: 1200px;
}

.loan-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.loan-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.loan-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.loan-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.loan-info-item {
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 6px;
}

.loan-info-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.loan-info-value {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

.add-loan-form {
    background: #f0f9ff;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.btn-group {
    display: flex;
    gap: 0.5rem;
}

/* Google Drive連携スタイル */
.drive-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.drive-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.connection-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.connection-status.connected {
    background: #d1fae5;
    color: #065f46;
}

.connection-status.disconnected {
    background: #fef3c7;
    color: #92400e;
}

/* フォルダ・ファイルブラウザ */
.drive-browser {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.browser-header {
    background: #f9fafb;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.breadcrumb a {
    color: #3b82f6;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb .separator {
    color: #9ca3af;
}

.folder-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
}

.folder-item, .file-item-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: inherit;
    border: 2px solid transparent;
}

.folder-item:hover, .file-item-card:hover {
    background: #eff6ff;
    border-color: #3b82f6;
}

.folder-icon {
    width: 40px;
    height: 40px;
    background: #fbbf24;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.file-icon {
    width: 40px;
    height: 40px;
    background: #10b981;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.item-info {
    flex: 1;
    min-width: 0;
}

.item-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-meta {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

/* ファイル詳細モーダル */
.file-detail {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.file-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.file-detail-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.file-detail-title h3 {
    margin: 0;
    word-break: break-all;
}

.file-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.file-meta-item {
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 6px;
}

.file-meta-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.file-meta-value {
    font-weight: 500;
}

/* CSVプレビュー */
.csv-preview {
    margin-top: 1.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.csv-preview-header {
    background: #f9fafb;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 500;
}

.csv-preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.csv-preview-table th {
    background: #f3f4f6;
    padding: 0.75rem;
    text-align: left;
    border-bottom: 2px solid #e5e7eb;
    font-weight: 600;
    white-space: nowrap;
    position: sticky;
    top: 0;
}

.csv-preview-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #f3f4f6;
}

.csv-preview-table tr:hover {
    background: #f9fafb;
}

.csv-preview-scroll {
    max-height: 400px;
    overflow: auto;
}

/* 銀行フォルダカード */
.bank-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.bank-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.bank-card-header {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.bank-card-icon {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bank-card-title {
    font-size: 1.125rem;
    font-weight: 600;
}

.bank-card-body {
    padding: 1rem;
}

.bank-card-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.bank-card-stat:last-child {
    border-bottom: none;
}

.bank-card-stat-label {
    color: #6b7280;
    font-size: 0.875rem;
}

.bank-card-stat-value {
    font-weight: 500;
}

.bank-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* タブ */
.tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0;
}

.tab {
    padding: 0.75rem 1.5rem;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.tab:hover {
    color: #3b82f6;
}

.tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.sync-folder-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

/* モーダルスタイル */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: #1f2937;
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    color: #6b7280;
    border-radius: 6px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    border-radius: 0 0 12px 12px;
}
</style>

<div class="loans-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2>借入金管理</h2>
        <div style="display: flex; gap: 0.5rem;">
            <a href="loan-repayments.php" class="btn btn-primary">返済管理</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Google Drive連携セクション -->
    <div class="drive-section">
        <h3>
            <svg width="20" height="20" viewBox="0 0 87.3 78" fill="none">
                <path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H1.2c0 1.55.4 3.1 1.2 4.5l4.2 9.35z" fill="#0066DA"/>
                <path d="M43.65 25L29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3L1.2 52.5c-.8 1.4-1.2 2.95-1.2 4.5h27.5L43.65 25z" fill="#00AC47"/>
                <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75L86.1 57c.8-1.4 1.2-2.95 1.2-4.5H57.05L43.65 25 27.5 57l13.75 23.8h32.3z" fill="#EA4335"/>
                <path d="M43.65 25L27.5 57h29.55L73.2 33.2l-4.6-8c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25z" fill="#00832D"/>
                <path d="M57.05 52.5h29.25c0-1.55-.4-3.1-1.2-4.5L65.3 21.9c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25l13.4 27.5z" fill="#2684FC"/>
                <path d="M27.5 57L13.75 80.8c1.35.8 2.85 1.2 4.5 1.2h50.8c1.65 0 3.15-.4 4.5-1.2L57.05 52.5H27.5z" fill="#FFBA00"/>
            </svg>
            Google Drive 書類管理
        </h3>

        <?php if ($driveClient->isConfigured()): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <span class="connection-status connected">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                    連携中
                </span>
                <div style="display: flex; gap: 0.5rem;">
                    <form method="POST" style="display: inline;">
                        <?= csrfTokenField() ?>
                        <button type="submit" name="clear_drive_cache" class="btn btn-sm btn-secondary" title="最新データを取得">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                            更新
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <?= csrfTokenField() ?>
                        <button type="submit" name="disconnect_drive" class="btn btn-sm btn-danger" onclick="return confirm('連携を解除しますか？')">連携解除</button>
                    </form>
                </div>
            </div>

            <?php if ($syncFolder && !empty($syncFolder['id'])): ?>
                <!-- 連携フォルダが設定されている場合 -->

                <?php if (!empty($selectedFileId) && $fileInfo): ?>
                    <!-- ファイル詳細表示 -->
                    <div class="file-detail">
                        <div class="file-detail-header">
                            <div class="file-detail-title">
                                <div class="file-icon" style="background: #ef4444;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8" fill="none" stroke="white" stroke-width="2"/>
                                        <text x="7" y="17" font-size="6" fill="white" font-weight="bold">PDF</text>
                                    </svg>
                                </div>
                                <h3><?= htmlspecialchars($fileInfo['name']) ?></h3>
                            </div>
                            <a href="?period=<?= htmlspecialchars($selectedPeriod) ?>&month=<?= htmlspecialchars($selectedMonth) ?>&folder_id=<?= htmlspecialchars($selectedFolderId) ?>" class="btn btn-secondary">戻る</a>
                        </div>

                        <div class="file-meta-grid">
                            <div class="file-meta-item">
                                <div class="file-meta-label">ファイルサイズ</div>
                                <div class="file-meta-value"><?= isset($fileInfo['size']) ? number_format($fileInfo['size'] / 1024, 1) . ' KB' : '-' ?></div>
                            </div>
                            <div class="file-meta-item">
                                <div class="file-meta-label">更新日時</div>
                                <div class="file-meta-value"><?= isset($fileInfo['modifiedTime']) ? date('Y/m/d H:i', strtotime($fileInfo['modifiedTime'])) : '-' ?></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                            <?php if (!empty($fileInfo['webViewLink'])): ?>
                                <a href="<?= htmlspecialchars($fileInfo['webViewLink']) ?>" target="_blank" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                        <polyline points="15 3 21 3 21 9"/>
                                        <line x1="10" y1="14" x2="21" y2="3"/>
                                    </svg>
                                    Google Driveで開く
                                </a>
                            <?php endif; ?>

                            <?php if (strpos($fileInfo['mimeType'] ?? '', 'pdf') !== false): ?>
                                <form method="POST" style="display: inline;">
                                    <?= csrfTokenField() ?>
                                    <input type="hidden" name="file_id" value="<?= htmlspecialchars($selectedFileId) ?>">
                                    <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                    <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                    <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                    <button type="submit" name="extract_text" class="btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <path d="M14 2v6h6"/>
                                            <path d="M16 13H8"/>
                                            <path d="M16 17H8"/>
                                            <path d="M10 9H8"/>
                                        </svg>
                                        金額を抽出
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php
                        // PDFファイル名から年月と銀行名を抽出（例: "2512_お支払済額明細書_日本政策金融公庫.pdf"）
                        $yearMonthForSheet = '';
                        $bankNameFromFile = '';
                        $pdfFileName = $fileInfo['name'] ?? '';

                        // 年月抽出: "2512_" → "2025.12"
                        if (preg_match('/^(\d{2})(\d{2})_/', $pdfFileName, $matches)) {
                            $year = '20' . $matches[1];
                            $month = $matches[2];
                            $yearMonthForSheet = $year . '.' . $month;
                        }

                        // 銀行名抽出: ファイル名末尾の「_銀行名.pdf」部分
                        if (preg_match('/_([^_]+)\.pdf$/i', $pdfFileName, $matches)) {
                            $bankNameFromFile = $matches[1];
                        }

                        // matchedLoanがあればそちらを優先
                        $displayBankName = $matchedLoan ? $matchedLoan['name'] : $bankNameFromFile;
                        ?>

                        <?php if ($matchedLoan): ?>
                            <!-- 借入先が特定された場合 -->
                            <div style="background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                <h4 style="margin: 0 0 0.75rem; color: #1e40af; font-size: 0.95rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                        <path d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4z"/>
                                    </svg>
                                    検出された借入先
                                </h4>
                                <div style="font-size: 1.2rem; font-weight: 600; color: #1e40af; margin-bottom: 0.5rem;">
                                    <?= htmlspecialchars($matchedLoan['name']) ?>
                                </div>
                                <?php if ($matchedRepayment): ?>
                                    <div style="background: #dcfce7; padding: 0.75rem; border-radius: 6px; margin-top: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #166534;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                <polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                            <span style="font-weight: 600;">返済額が一致しました</span>
                                        </div>
                                        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                                            返済総額: <strong>¥<?= number_format($matchedRepayment['total']) ?></strong>
                                            （元金: ¥<?= number_format($matchedRepayment['principal']) ?> + 利息: ¥<?= number_format($matchedRepayment['interest']) ?>）
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif (!empty($bankNameFromFile)): ?>
                            <!-- ファイル名から銀行名を検出 -->
                            <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                <h4 style="margin: 0 0 0.5rem; color: #92400e; font-size: 0.95rem;">
                                    ファイル名から検出: <?= htmlspecialchars($bankNameFromFile) ?>
                                </h4>
                                <p style="margin: 0; font-size: 0.85rem; color: #78350f;">
                                    ※借入先マスタに一致するデータがありません
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($extractedAmounts)): ?>
                            <?php
                            // スプレッドシートの金額との照合（配列に対応）
                            $hasAnyMatch = false;
                            $matchedSheetEntry = null;
                            if ($sheetRepaymentData && is_array($sheetRepaymentData)) {
                                foreach ($sheetRepaymentData as $entry) {
                                    if (!empty($entry['matchedAmount'])) {
                                        $hasAnyMatch = true;
                                        $matchedSheetEntry = $entry;
                                        break;
                                    }
                                }
                            }
                            ?>

                            <!-- PDFから抽出された金額 -->
                            <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                <h4 style="margin: 0 0 0.75rem; color: #166534; font-size: 0.95rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <path d="M14 2v6h6"/>
                                    </svg>
                                    PDFから抽出された金額
                                </h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                    <?php foreach ($extractedAmounts as $amount): ?>
                                        <?php
                                        // 配列の中でマッチするものを探す
                                        $isSheetMatch = false;
                                        if ($sheetRepaymentData && is_array($sheetRepaymentData)) {
                                            foreach ($sheetRepaymentData as $entry) {
                                                if ($amount === $entry['total']) {
                                                    $isSheetMatch = true;
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <span style="background: <?= $isSheetMatch ? '#dcfce7' : 'white' ?>; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 1.1rem; color: #166534; <?= $isSheetMatch ? 'border: 2px solid #22c55e;' : '' ?>">
                                            ¥<?= number_format($amount) ?>
                                            <?php if ($isSheetMatch): ?>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3" style="vertical-align: middle; margin-left: 0.25rem;">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- スプレッドシートの金額（複数の借入に対応） -->
                            <?php if ($sheetRepaymentData && is_array($sheetRepaymentData)): ?>
                                <?php foreach ($sheetRepaymentData as $sheetEntry): ?>
                                    <?php
                                    $entryHasMatch = !empty($sheetEntry['matchedAmount']);
                                    $isPaidOff = !empty($sheetEntry['isPaidOff']);
                                    $displayName = $sheetEntry['bankName'] ?? '';
                                    $loanAmountLabel = $sheetEntry['loanAmount'] ?? '';
                                    if ($loanAmountLabel) {
                                        $displayName .= '（' . $loanAmountLabel . '）';
                                    }

                                    // 完済済みの場合はグレー、一致は緑、不一致は黄色
                                    if ($isPaidOff) {
                                        $bgColor = '#f3f4f6';
                                        $borderColor = '#d1d5db';
                                        $textColor = '#6b7280';
                                    } elseif ($entryHasMatch) {
                                        $bgColor = '#dcfce7';
                                        $borderColor = '#86efac';
                                        $textColor = '#166534';
                                    } else {
                                        $bgColor = '#fef3c7';
                                        $borderColor = '#fbbf24';
                                        $textColor = '#92400e';
                                    }
                                    ?>
                                    <div style="background: <?= $bgColor ?>; border: 1px solid <?= $borderColor ?>; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; <?= $isPaidOff ? 'opacity: 0.7;' : '' ?>">
                                        <h4 style="margin: 0 0 0.75rem; color: <?= $textColor ?>; font-size: 0.95rem;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                <line x1="3" y1="9" x2="21" y2="9"/>
                                                <line x1="9" y1="21" x2="9" y2="9"/>
                                            </svg>
                                            スプレッドシート: <?= htmlspecialchars($displayName) ?>
                                            <?php if ($isPaidOff): ?>
                                                <span style="background: #9ca3af; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">完済済み</span>
                                            <?php elseif ($entryHasMatch): ?>
                                                <span style="background: #22c55e; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">一致</span>
                                            <?php else: ?>
                                                <span style="background: #f59e0b; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">不一致</span>
                                            <?php endif; ?>
                                        </h4>
                                        <?php if ($isPaidOff): ?>
                                            <p style="margin: 0; font-size: 0.9rem; color: #6b7280;">この借入は完済済みです（データなし）</p>
                                        <?php else: ?>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem;">
                                            <div style="background: white; padding: 0.5rem; border-radius: 4px;">
                                                <div style="font-size: 0.75rem; color: #6b7280;">元金</div>
                                                <div style="font-weight: 600;">¥<?= number_format($sheetEntry['principal']) ?></div>
                                            </div>
                                            <div style="background: white; padding: 0.5rem; border-radius: 4px;">
                                                <div style="font-size: 0.75rem; color: #6b7280;">利息</div>
                                                <div style="font-weight: 600;">¥<?= number_format($sheetEntry['interest']) ?></div>
                                            </div>
                                            <div style="background: white; padding: 0.5rem; border-radius: 4px;">
                                                <div style="font-size: 0.75rem; color: #6b7280;">合計（元金+利息）</div>
                                                <div style="font-weight: 600; color: <?= $entryHasMatch ? '#166534' : '#dc2626' ?>;">¥<?= number_format($sheetEntry['total']) ?></div>
                                            </div>
                                            <div style="background: white; padding: 0.5rem; border-radius: 4px;">
                                                <div style="font-size: 0.75rem; color: #6b7280;">残高</div>
                                                <div style="font-weight: 600;">¥<?= number_format($sheetEntry['balance']) ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php
                                // 一致したエントリを収集（完済済みは除外）
                                $matchedEntries = [];
                                foreach ($sheetRepaymentData as $entry) {
                                    // 完済済みはスキップ
                                    if (!empty($entry['isPaidOff'])) {
                                        continue;
                                    }
                                    if (!empty($entry['matchedAmount'])) {
                                        $matchedEntries[] = [
                                            'amount' => $entry['total'],
                                            'startCol' => $entry['startCol'],
                                            'bankName' => $entry['bankName'] ?? '',
                                            'loanAmount' => $entry['loanAmount'] ?? ''
                                        ];
                                    }
                                }
                                ?>

                                <!-- 一括色付けボタン -->
                                <?php if (!empty($matchedEntries) && !empty($yearMonthForSheet)): ?>
                                    <div style="background: #ecfdf5; border: 2px solid #22c55e; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                        <h4 style="margin: 0 0 0.75rem; color: #166534; font-size: 0.95rem;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                            一致した <?= count($matchedEntries) ?>件 をスプレッドシートに反映
                                        </h4>
                                        <div style="margin-bottom: 0.75rem; font-size: 0.9rem;">
                                            <?php foreach ($matchedEntries as $me): ?>
                                                <div style="padding: 0.25rem 0;">
                                                    ・<?= htmlspecialchars($me['bankName']) ?>
                                                    <?php if ($me['loanAmount']): ?>（<?= htmlspecialchars($me['loanAmount']) ?>）<?php endif; ?>
                                                    : ¥<?= number_format($me['amount']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <form method="POST" onsubmit="handleBulkMarkSpreadsheet(event); return false;">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="bulk_entries" value="<?= htmlspecialchars(json_encode($matchedEntries)) ?>">
                                            <input type="hidden" name="bulk_year_month" value="<?= htmlspecialchars($yearMonthForSheet) ?>">
                                            <input type="hidden" name="file_id" value="<?= htmlspecialchars($selectedFileId) ?>">
                                            <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                            <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                            <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                            <button type="submit" name="bulk_mark_spreadsheet" class="btn" style="background: #16a34a; color: white; border: none; padding: 0.75rem 1.5rem; font-size: 1rem;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                                一括で色付けする
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- 個別スプレッドシート反映セクション（一致がない場合のみ表示） -->
                            <?php if (!empty($yearMonthForSheet) && !empty($displayBankName) && empty($matchedEntries)): ?>
                                <div style="background: #f0f9ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                    <h4 style="margin: 0 0 0.75rem; color: #1d4ed8; font-size: 0.95rem;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="3" y1="9" x2="21" y2="9"/>
                                            <line x1="9" y1="21" x2="9" y2="9"/>
                                        </svg>
                                        手動でスプレッドシートに反映
                                    </h4>
                                    <p style="margin: 0 0 0.75rem; font-size: 0.9rem; color: #1e40af;">
                                        対象: <strong><?= htmlspecialchars($yearMonthForSheet) ?></strong> / <strong><?= htmlspecialchars($displayBankName) ?></strong>
                                    </p>
                                    <form method="POST" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                        <?= csrfTokenField() ?>
                                        <label style="font-size: 0.9rem;">金額を選択:</label>
                                        <select name="mark_amount" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
                                            <?php foreach ($extractedAmounts as $amount): ?>
                                                <option value="<?= $amount ?>" <?= ($matchedRepayment && $amount === $matchedRepayment['total']) ? 'selected' : '' ?>>
                                                    ¥<?= number_format($amount) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="mark_year_month" value="<?= htmlspecialchars($yearMonthForSheet) ?>">
                                        <input type="hidden" name="mark_bank_name" value="<?= htmlspecialchars($displayBankName) ?>">
                                        <input type="hidden" name="file_id" value="<?= htmlspecialchars($selectedFileId) ?>">
                                        <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                        <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                        <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                        <button type="submit" name="mark_spreadsheet" class="btn btn-sm" style="background: #16a34a; color: white; border: none;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                            反映する
                                        </button>
                                    </form>
                                </div>
                            <?php elseif (empty($yearMonthForSheet)): ?>
                                <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem;">
                                    <p style="margin: 0; font-size: 0.85rem; color: #92400e;">
                                        ファイル名「<?= htmlspecialchars($pdfFileName) ?>」から年月を抽出できませんでした（YYMM_形式が必要です）
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($sheetsDebugInfo): ?>
                                <!-- デバッグ情報 -->
                                <details style="margin-top: 1rem; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 0.75rem;">
                                    <summary style="cursor: pointer; color: #92400e; font-weight: 500;">デバッグ情報を表示</summary>
                                    <div style="margin-top: 0.75rem; font-size: 0.85rem;">
                                        <p><strong>検索した年月:</strong> <?= htmlspecialchars($sheetsDebugInfo['searchYearMonth']) ?></p>
                                        <p><strong>スプレッドシートB列の値（最初の30行）:</strong></p>
                                        <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                            <?php foreach ($sheetsDebugInfo['columnBSample'] as $item): ?>
                                                <li>行<?= $item['row'] ?>: "<?= htmlspecialchars($item['value']) ?>"</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </details>
                            <?php endif; ?>
                        <?php elseif (!empty($extractedText)): ?>
                            <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                <p style="margin: 0; color: #92400e;">金額が見つかりませんでした（スキャン画像PDFの可能性があります）</p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($extractedText)): ?>
                            <details style="margin-top: 1rem;">
                                <summary style="cursor: pointer; color: #6b7280; font-size: 0.875rem;">抽出されたテキストを表示</summary>
                                <pre style="background: #f9fafb; padding: 1rem; border-radius: 6px; margin-top: 0.5rem; font-size: 0.8rem; max-height: 300px; overflow: auto; white-space: pre-wrap;"><?= htmlspecialchars(mb_substr($extractedText, 0, 5000)) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <!-- 遅延読み込み時のローディング表示 -->
                    <?php if ($useLazyLoad && $syncFolder && !empty($syncFolder['id'])): ?>
                        <div id="lazy-load-container">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                                <label style="font-weight: 500;">期:</label>
                                <select id="period-select" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;" disabled>
                                    <option>読み込み中...</option>
                                </select>

                                <label style="font-weight: 500; margin-left: 1rem;">月次:</label>
                                <select id="month-select" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;" disabled>
                                    <option value="">期を選択してください</option>
                                </select>
                            </div>
                            <div id="folder-contents-container"></div>
                        </div>
                        <script>
                        // 遅延読み込み
                        document.addEventListener('DOMContentLoaded', function() {
                            const syncFolderId = '<?= htmlspecialchars($syncFolder['id']) ?>';
                            const periodSelect = document.getElementById('period-select');
                            const monthSelect = document.getElementById('month-select');
                            const contentsContainer = document.getElementById('folder-contents-container');

                            // 期フォルダを読み込み
                            fetch('../api/drive-api.php?action=list_periods')
                            .then(r => r.json())
                            .then(data => {
                                if (data.success && data.periods) {
                                    periodSelect.innerHTML = data.periods.map((p, i) =>
                                        `<option value="${p.id}" ${i === 0 ? 'selected' : ''}>${p.name}</option>`
                                    ).join('');
                                    periodSelect.disabled = false;

                                    // 最初の期の月次フォルダを読み込み
                                    if (data.periods.length > 0) {
                                        loadMonths(data.periods[0].id);
                                    }
                                }
                            })
                            .catch(err => {
                                periodSelect.innerHTML = '<option>エラー</option>';
                                console.error('期フォルダ読み込みエラー:', err);
                            });

                            // 期が変更されたら月次フォルダを読み込み
                            periodSelect.addEventListener('change', function() {
                                loadMonths(this.value);
                            });

                            // 月次フォルダを読み込み
                            function loadMonths(periodId) {
                                monthSelect.innerHTML = '<option>読み込み中...</option>';
                                monthSelect.disabled = true;
                                contentsContainer.innerHTML = '';

                                fetch('../api/drive-api.php?action=list_months&period_id=' + periodId)
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success && data.months) {
                                        monthSelect.innerHTML = '<option value="">選択してください</option>' +
                                            data.months.map(m =>
                                                `<option value="${m.id}">${m.name.replace(/_月次資料$/, '')}</option>`
                                            ).join('');
                                        monthSelect.disabled = false;
                                    }
                                })
                                .catch(err => {
                                    monthSelect.innerHTML = '<option>エラー</option>';
                                    console.error('月次フォルダ読み込みエラー:', err);
                                });
                            }

                            // 月次が選択されたらページ遷移
                            monthSelect.addEventListener('change', function() {
                                if (this.value) {
                                    location.href = '?period=' + periodSelect.value + '&month=' + this.value;
                                }
                            });
                        });
                        </script>
                    <?php elseif (!$useLazyLoad): ?>
                    <!-- 期選択（同期読み込み済み） -->
                    <?php if (!empty($periodFolders)): ?>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                            <label style="font-weight: 500;">期:</label>
                            <select onchange="location.href='?period='+this.value" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
                                <?php foreach ($periodFolders as $pf): ?>
                                    <option value="<?= htmlspecialchars($pf['id']) ?>" <?= $pf['id'] === $selectedPeriod ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pf['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (!empty($monthlyFolders)): ?>
                                <label style="font-weight: 500; margin-left: 1rem;">月次:</label>
                                <select onchange="location.href='?period=<?= htmlspecialchars($selectedPeriod) ?>&month='+this.value" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
                                    <option value="">選択してください</option>
                                    <?php foreach ($monthlyFolders as $mf): ?>
                                        <option value="<?= htmlspecialchars($mf['id']) ?>" <?= $mf['id'] === $selectedMonth ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(preg_replace('/_月次資料$/', '', $mf['name'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($selectedMonth) && $folderContents): ?>
                        <!-- 月次フォルダ内のサブフォルダ/ファイル一覧 -->
                        <?php if (!empty($selectedFolderId)): ?>
                            <!-- サブフォルダ内（銀行明細など） -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                                <div class="breadcrumb">
                                    <a href="?period=<?= htmlspecialchars($selectedPeriod) ?>&month=<?= htmlspecialchars($selectedMonth) ?>">月次資料</a>
                                    <span class="separator">›</span>
                                    <span><?= htmlspecialchars($_GET['folder_name'] ?? 'フォルダ') ?></span>
                                </div>
                                <?php
                                // フォルダ名がYYMM_で始まる場合のみ一括照合ボタンを表示
                                $currentFolderName = $_GET['folder_name'] ?? '';
                                if (preg_match('/^\d{4}_/', $currentFolderName)):
                                ?>
                                <form method="POST" style="display: inline;">
                                    <?= csrfTokenField() ?>
                                    <input type="hidden" name="match_folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                    <input type="hidden" name="match_folder_name" value="<?= htmlspecialchars($currentFolderName) ?>">
                                    <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                    <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                    <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                    <input type="hidden" name="folder_name" value="<?= htmlspecialchars($currentFolderName) ?>">
                                    <button type="submit" name="bulk_match_folder" class="btn" style="background: #8b5cf6; color: white; border: none;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        一括照合
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                            <?php if ($bulkMatchResults !== null): ?>
                                <!-- 一括照合結果表示 -->
                                <div style="background: white; border: 2px solid #8b5cf6; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem;">
                                    <h4 style="margin: 0 0 1rem; color: #6d28d9; display: flex; align-items: center; gap: 0.5rem;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        一括照合結果: <?= htmlspecialchars($bulkMatchResults['folderName']) ?> (<?= htmlspecialchars($bulkMatchResults['yearMonth']) ?>)
                                        <?php if (isset($bulkMatchResults['cacheHits']) && $bulkMatchResults['cacheHits'] > 0): ?>
                                        <span style="font-size: 0.8rem; font-weight: normal; color: #6b7280; margin-left: auto;">キャッシュ: <?= $bulkMatchResults['cacheHits'] ?>件</span>
                                        <?php endif; ?>
                                    </h4>

                                    <?php if (!empty($bulkMatchResults['matches'])): ?>
                                        <div style="background: #ecfdf5; border: 1px solid #86efac; border-radius: 6px; padding: 1rem; margin-bottom: 1rem;">
                                            <h5 style="margin: 0 0 0.75rem; color: #166534; font-size: 0.95rem;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                                一致: <?= count($bulkMatchResults['matches']) ?>件
                                            </h5>
                                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                                <thead>
                                                    <tr style="background: rgba(255,255,255,0.5);">
                                                        <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #86efac;">PDFファイル</th>
                                                        <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #86efac;">銀行（スプシ）</th>
                                                        <th style="padding: 0.5rem; text-align: right; border-bottom: 1px solid #86efac;">金額</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($bulkMatchResults['matches'] as $match): ?>
                                                    <tr>
                                                        <td style="padding: 0.5rem; border-bottom: 1px solid #d1fae5;"><?= htmlspecialchars($match['fileName']) ?></td>
                                                        <td style="padding: 0.5rem; border-bottom: 1px solid #d1fae5;">
                                                            <?= htmlspecialchars($match['sheetBankName']) ?>
                                                            <?php if ($match['loanAmount']): ?>
                                                                <span style="color: #6b7280; font-size: 0.8rem;">（<?= htmlspecialchars($match['loanAmount']) ?>）</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td style="padding: 0.5rem; text-align: right; border-bottom: 1px solid #d1fae5; font-weight: 600;">¥<?= number_format($match['amount']) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($bulkMatchResults['noMatches'])): ?>
                                        <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 6px; padding: 1rem; margin-bottom: 1rem;">
                                            <h5 style="margin: 0 0 0.75rem; color: #92400e; font-size: 0.95rem;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                                </svg>
                                                不一致: <?= count($bulkMatchResults['noMatches']) ?>件
                                            </h5>
                                            <div style="font-size: 0.85rem;">
                                                <?php foreach ($bulkMatchResults['noMatches'] as $noMatch): ?>
                                                <div style="padding: 0.5rem 0; border-bottom: 1px solid #fde68a;">
                                                    <div style="font-weight: 500; margin-bottom: 0.25rem;"><?= htmlspecialchars($noMatch['fileName']) ?></div>
                                                    <div style="color: #78350f; margin-bottom: 0.25rem;">
                                                        PDF抽出金額: <?php echo implode(', ', array_map(fn($a) => '¥' . number_format($a), array_slice($noMatch['pdfAmounts'], 0, 10))); ?>
                                                        <?php if (count($noMatch['pdfAmounts']) > 10): ?>...他<?= count($noMatch['pdfAmounts']) - 10 ?>件<?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($bulkMatchResults['errors'])): ?>
                                        <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; padding: 1rem; margin-bottom: 1rem;">
                                            <h5 style="margin: 0 0 0.5rem; color: #dc2626; font-size: 0.95rem;">エラー: <?= count($bulkMatchResults['errors']) ?>件</h5>
                                            <div style="font-size: 0.85rem; color: #991b1b;">
                                                <?php foreach ($bulkMatchResults['errors'] as $err): ?>
                                                <div style="padding: 0.25rem 0;"><?= htmlspecialchars($err['fileName']) ?>: <?= htmlspecialchars($err['message']) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($bulkMatchResults['matches'])): ?>
                                        <!-- 一括登録ボタン -->
                                        <form method="POST" style="margin-top: 1rem;" onsubmit="handleApplyBulkMatch(event); return false;">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="apply_entries" value="<?= htmlspecialchars(json_encode($bulkMatchResults['matches'])) ?>">
                                            <input type="hidden" name="apply_year_month" value="<?= htmlspecialchars($bulkMatchResults['yearMonth']) ?>">
                                            <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                            <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                            <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                            <input type="hidden" name="folder_name" value="<?= htmlspecialchars($_GET['folder_name'] ?? '') ?>">
                                            <button type="submit" name="apply_bulk_match" class="btn" style="background: #16a34a; color: white; border: none; padding: 0.75rem 2rem; font-size: 1rem;">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                                一括登録（<?= count($bulkMatchResults['matches']) ?>件の色付け）
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($folderContents['files'])): ?>
                                <div class="file-list-table">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                                                <th style="padding: 0.75rem; text-align: left;">ファイル名</th>
                                                <th style="padding: 0.75rem; text-align: right; width: 100px;">サイズ</th>
                                                <th style="padding: 0.75rem; text-align: center; width: 120px;">更新日</th>
                                                <th style="padding: 0.75rem; text-align: center; width: 100px;">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($folderContents['files'] as $file): ?>
                                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                                    <td style="padding: 0.75rem;">
                                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                            <div style="width: 32px; height: 32px; background: #ef4444; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                                            </div>
                                                            <span style="font-weight: 500;"><?= htmlspecialchars($file['name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem; text-align: right; color: #6b7280; font-size: 0.875rem;">
                                                        <?= isset($file['size']) ? number_format($file['size'] / 1024, 0) . 'KB' : '-' ?>
                                                    </td>
                                                    <td style="padding: 0.75rem; text-align: center; color: #6b7280; font-size: 0.875rem;">
                                                        <?= isset($file['modifiedTime']) ? date('Y/m/d', strtotime($file['modifiedTime'])) : '-' ?>
                                                    </td>
                                                    <td style="padding: 0.75rem; text-align: center;">
                                                        <a href="?period=<?= htmlspecialchars($selectedPeriod) ?>&month=<?= htmlspecialchars($selectedMonth) ?>&folder_id=<?= htmlspecialchars($selectedFolderId) ?>&file_id=<?= htmlspecialchars($file['id']) ?>" class="btn btn-sm btn-primary">詳細</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif (!empty($folderContents['folders'])): ?>
                                <div class="folder-grid">
                                    <?php foreach ($folderContents['folders'] as $folder): ?>
                                        <a href="?period=<?= htmlspecialchars($selectedPeriod) ?>&month=<?= htmlspecialchars($selectedMonth) ?>&folder_id=<?= htmlspecialchars($folder['id']) ?>&folder_name=<?= urlencode($folder['name']) ?>" class="folder-item">
                                            <div class="folder-icon">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                                    <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                                                </svg>
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name"><?= htmlspecialchars($folder['name']) ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <p>このフォルダは空です</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- 月次フォルダ直下 -->
                            <?php if (!empty($folderContents['folders'])): ?>
                                <div class="folder-grid">
                                    <?php foreach ($folderContents['folders'] as $folder): ?>
                                        <a href="?period=<?= htmlspecialchars($selectedPeriod) ?>&month=<?= htmlspecialchars($selectedMonth) ?>&folder_id=<?= htmlspecialchars($folder['id']) ?>&folder_name=<?= urlencode($folder['name']) ?>" class="folder-item">
                                            <div class="folder-icon">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                                    <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                                                </svg>
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name"><?= htmlspecialchars($folder['name']) ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($folderContents['files'])): ?>
                                <div class="folder-grid" style="margin-top: 1rem;">
                                    <?php foreach ($folderContents['files'] as $file): ?>
                                        <a href="?period=<?= htmlspecialchars($selectedPeriod) ?>&month=<?= htmlspecialchars($selectedMonth) ?>&file_id=<?= htmlspecialchars($file['id']) ?>" class="file-item-card">
                                            <div class="file-icon" style="background: #ef4444;">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name"><?= htmlspecialchars($file['name']) ?></div>
                                                <div class="item-meta"><?= isset($file['modifiedTime']) ? date('Y/m/d', strtotime($file['modifiedTime'])) : '' ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($folderContents['folders']) && empty($folderContents['files'])): ?>
                                <div class="empty-state">
                                    <p>この月次フォルダは空です</p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php elseif (!empty($selectedPeriod) && empty($selectedMonth)): ?>
                        <!-- 月次未選択時のガイド -->
                        <div class="empty-state">
                            <p>上のドロップダウンから月次を選択してください</p>
                        </div>
                    <?php elseif (empty($periodFolders)): ?>
                        <div class="empty-state">
                            <p>期フォルダが見つかりません</p>
                            <p>「○○期_XXXX-XXXX」形式のフォルダを作成してください</p>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- 連携フォルダ未設定 - フォルダID直接入力 -->
                <div style="background: #f0f9ff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <p style="margin: 0 0 0.75rem; font-weight: 500;">共有フォルダのIDを入力:</p>
                    <p style="margin: 0 0 1rem; font-size: 0.875rem; color: #6b7280;">
                        Google DriveのフォルダURLから「folders/」の後ろの部分をコピーしてください<br>
                        例: https://drive.google.com/drive/folders/<strong>1iCPEOmRroKpI1N_Iyi1mWFlfsPJRNiXa</strong>
                    </p>
                    <form method="POST" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?= csrfTokenField() ?>
                        <input type="text" name="folder_id" class="form-input" style="flex: 1; min-width: 300px;" placeholder="フォルダID（例: 1iCPEOmRroKpI1N_Iyi1mWFlfsPJRNiXa）" required>
                        <input type="text" name="folder_name" class="form-input" style="width: 200px;" placeholder="フォルダ名（任意）" value="借入金返済">
                        <button type="submit" name="set_sync_folder" class="btn btn-primary">設定</button>
                    </form>
                </div>

                <?php if (!empty($driveFolders)): ?>
                    <p style="margin-bottom: 1rem; color: #4b5563;">または、マイドライブからフォルダを選択:</p>
                    <div class="folder-grid">
                        <?php foreach ($driveFolders as $folder): ?>
                            <form method="POST" style="display: contents;">
                                <?= csrfTokenField() ?>
                                <input type="hidden" name="folder_id" value="<?= htmlspecialchars($folder['id']) ?>">
                                <input type="hidden" name="folder_name" value="<?= htmlspecialchars($folder['name']) ?>">
                                <button type="submit" name="set_sync_folder" class="folder-item" style="border: none; width: 100%; text-align: left;">
                                    <div class="folder-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                            <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                                        </svg>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?= htmlspecialchars($folder['name']) ?></div>
                                        <div class="item-meta">クリックで選択</div>
                                    </div>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <span class="connection-status disconnected">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                未連携
            </span>
            <p style="margin: 1rem 0; color: #4b5563;">
                Google Driveに保存されている借入金関連書類を表示・管理できます。
            </p>
            <a href="<?= htmlspecialchars($driveAuthUrl) ?>" class="btn btn-primary">Drive連携</a>
        <?php endif; ?>
    </div>
</div>

<!-- 借入先追加モーダル -->
<div id="addLoanModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                    <path d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4z"/>
                </svg>
                借入先を追加
            </h3>
            <button type="button" class="modal-close" onclick="closeAddLoanModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">借入先名 <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="name" class="form-input" placeholder="例: 中国銀行" required style="width: 100%;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">借入額</label>
                        <input type="number" name="initial_amount" class="form-input" placeholder="例: 10000000" style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">借入開始日</label>
                        <input type="date" name="start_date" class="form-input" style="width: 100%;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">金利 (%)</label>
                        <input type="number" name="interest_rate" class="form-input" step="0.01" placeholder="例: 1.5" style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">返済日（毎月）</label>
                        <input type="number" name="repayment_day" class="form-input" min="1" max="31" value="25" style="width: 100%;">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">備考</label>
                    <input type="text" name="notes" class="form-input" placeholder="メモ" style="width: 100%;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddLoanModal()">キャンセル</button>
                <button type="submit" name="add_loan" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    追加
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddLoanModal() {
    document.getElementById('addLoanModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    // 最初の入力欄にフォーカス
    setTimeout(function() {
        document.querySelector('#addLoanModal input[name="name"]').focus();
    }, 100);
}

function closeAddLoanModal() {
    document.getElementById('addLoanModal').classList.remove('active');
    document.body.style.overflow = '';
}

// オーバーレイクリックで閉じる
document.getElementById('addLoanModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddLoanModal();
    }
});

// ESCキーで閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddLoanModal();
    }
});

// バックグラウンド色付け処理（ページ遷移可能）
function startBackgroundColoring(entries, yearMonth, buttonElement, type) {
    // ボタンを処理中状態に変更
    buttonElement.disabled = true;
    buttonElement.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem; animation: spin 1s linear infinite;">
            <circle cx="12" cy="12" r="10" stroke-dasharray="30" stroke-dashoffset="10"/>
        </svg>
        処理を開始中...
    `;
    buttonElement.style.opacity = '0.8';

    // ジョブを作成（即座にレスポンスが返る）
    fetch('/api/loans-color.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= generateCsrfToken() ?>' },
        body: JSON.stringify({
            action: type,
            entries: entries,
            year_month: yearMonth
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'ジョブの作成に失敗しました');
        }

        // ボタンを更新
        buttonElement.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            処理開始済み
        `;
        buttonElement.style.background = '#6b7280';

        // 進捗表示エリア
        let progressDiv = document.getElementById('coloring-progress-' + type);
        if (!progressDiv) {
            progressDiv = document.createElement('div');
            progressDiv.id = 'coloring-progress-' + type;
            progressDiv.style.cssText = 'margin-top: 0.75rem; padding: 0.5rem 1rem; background: #dbeafe; border-radius: 6px; font-size: 0.875rem; color: #1e40af;';
            buttonElement.parentNode.appendChild(progressDiv);
        }
        progressDiv.innerHTML = '✓ 処理を開始しました。別のページに移動しても処理は続行されます。右下の通知で進捗を確認できます。';
        progressDiv.style.display = 'block';
    })
    .catch(error => {
        console.error('Job creation error:', error);
        buttonElement.disabled = false;
        buttonElement.innerHTML = '色付けする（エラー - 再試行）';
        buttonElement.style.opacity = '1';
        alert('処理の開始に失敗しました: ' + error.message);
    });
}

// 一括色付けボタンのクリックハンドラ
function handleBulkMarkSpreadsheet(event) {
    event.preventDefault();
    const form = event.target.closest('form');
    const entries = JSON.parse(form.querySelector('[name="bulk_entries"]').value);
    const yearMonth = form.querySelector('[name="bulk_year_month"]').value;
    const button = form.querySelector('button[type="submit"]');

    startBackgroundColoring(entries, yearMonth, button, 'bulk_mark');
}

// 一括登録ボタンのクリックハンドラ
function handleApplyBulkMatch(event) {
    event.preventDefault();
    const form = event.target.closest('form');
    const entries = JSON.parse(form.querySelector('[name="apply_entries"]').value);
    const yearMonth = form.querySelector('[name="apply_year_month"]').value;
    const button = form.querySelector('button[type="submit"]');

    startBackgroundColoring(entries, yearMonth, button, 'apply_bulk');
}
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<?php require_once '../functions/footer.php'; ?>
