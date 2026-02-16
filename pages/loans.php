<?php
require_once '../api/auth.php';
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

// Google Drive フォルダIDの形式を検証
function isValidDriveFolderId($folderId) {
    if (empty($folderId)) {
        return false;
    }
    // Google Drive IDは通常33文字程度の英数字とハイフン、アンダースコアで構成
    // 最小10文字、最大100文字、英数字・ハイフン・アンダースコアのみ許可
    return preg_match('/^[a-zA-Z0-9_-]{10,100}$/', $folderId) === 1;
}

// 連携フォルダを設定
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_sync_folder'])) {
    $folderId = trim($_POST['folder_id'] ?? '');
    $folderName = $_POST['folder_name'] ?? '';
    if (empty($folderId)) {
        $error = 'フォルダIDを入力してください';
    } elseif (!isValidDriveFolderId($folderId)) {
        $error = 'フォルダIDの形式が正しくありません（英数字・ハイフン・アンダースコアのみ使用可）';
    } else {
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

// 借入先削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_loan'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $loanId = $_POST['loan_id'] ?? '';
        if ($loanId) {
            $api->deleteLoan($loanId);
            $message = '借入先を削除しました';
        }
    }
}

$loans = $api->getLoans();

// 返済スケジュールサマリー
$loansData = $api->getData();
$loansForSummary = $loansData['loans'] ?? [];
$repayments = $loansData['repayments'] ?? [];

// 借入金サマリー
$totalLoanAmount = 0;
$totalMonthlyRepayment = 0;
$loanSummary = [];
foreach ($loansForSummary as $loan) {
    $loanId = $loan['id'] ?? '';
    $loanName = $loan['name'] ?? '';
    $balance = floatval($loan['balance'] ?? 0);
    $totalLoanAmount += $balance;

    // 最新の返済データを取得
    $latestRepayment = null;
    $monthlyTotal = 0;
    foreach ($repayments as $rep) {
        if (($rep['loan_id'] ?? '') === $loanId) {
            $repTotal = floatval($rep['principal'] ?? 0) + floatval($rep['interest'] ?? 0);
            if (!$latestRepayment || ($rep['date'] ?? '') > ($latestRepayment['date'] ?? '')) {
                $latestRepayment = $rep;
                $monthlyTotal = $repTotal;
            }
        }
    }
    $totalMonthlyRepayment += $monthlyTotal;

    $loanSummary[] = [
        'name' => $loanName,
        'balance' => $balance,
        'monthly' => $monthlyTotal,
        'end_date' => $loan['end_date'] ?? '',
    ];
}

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* 借入金管理固有のスタイルはここに */

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
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
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
    border-bottom: 1px solid var(--gray-200);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--gray-900);
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    color: var(--gray-500);
    border-radius: 6px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--gray-100);
    color: var(--gray-900);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--gray-200);
    background: var(--gray-50);
}
</style>

<div class="page-container">
    <div class="page-header">
        <h2>借入金管理</h2>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Google Drive連携セクション -->
    <div class="drive-section">
        <h3>Google Drive 書類管理</h3>

        <?php if ($driveClient->isConfigured()): ?>
            <div  class="d-flex justify-between align-center mb-2">
                <span class="connection-status connected">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                    連携中
                </span>
                <div  class="d-flex gap-1">
                    <form method="POST"  class="d-inline">
                        <?= csrfTokenField() ?>
                        <button type="submit" name="clear_drive_cache" class="btn btn-sm btn-secondary" title="最新データを取得">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                            更新
                        </button>
                    </form>
                    <form method="POST"  class="d-inline" class="disconnect-drive-form">
                        <?= csrfTokenField() ?>
                        <button type="submit" name="disconnect_drive" class="btn btn-sm btn-danger">連携解除</button>
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
                                <div         class="file-icon" class="bg-ef4">
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

                        <div  class="d-flex gap-2 flex-wrap mb-3">
                            <?php if (!empty($fileInfo['webViewLink'])): ?>
                                <a href="<?= htmlspecialchars($fileInfo['webViewLink']) ?>" target="_blank" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"  class="mr-1">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                        <polyline points="15 3 21 3 21 9"/>
                                        <line x1="10" y1="14" x2="21" y2="3"/>
                                    </svg>
                                    Google Driveで開く
                                </a>
                            <?php endif; ?>

                            <?php if (strpos($fileInfo['mimeType'] ?? '', 'pdf') !== false): ?>
                                <form method="POST"  class="d-inline">
                                    <?= csrfTokenField() ?>
                                    <input type="hidden" name="file_id" value="<?= htmlspecialchars($selectedFileId) ?>">
                                    <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                    <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                    <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                    <button type="submit" name="extract_text" class="btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"  class="mr-1">
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
                            <div        class="info-box-primary rounded-lg p-2 mb-2">
                                <h4      class="text-095 mb-075-m text-1e4">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
                                        <path d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4z"/>
                                    </svg>
                                    検出された借入先
                                </h4>
                                <div        class="mb-1 font-semibold section-title-lg text-1e4">
                                    <?= htmlspecialchars($matchedLoan['name']) ?>
                                </div>
                                <?php if ($matchedRepayment): ?>
                                    <div        class="mt-1 p-075 bg-dcfce7 rounded-6">
                                        <div      class="d-flex align-center gap-1 text-166">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                <polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                            <span   class="font-semibold">返済額が一致しました</span>
                                        </div>
                                        <div      class="mt-1 text-09">
                                            返済総額: <strong>¥<?= number_format($matchedRepayment['total']) ?></strong>
                                            （元金: ¥<?= number_format($matchedRepayment['principal']) ?> + 利息: ¥<?= number_format($matchedRepayment['interest']) ?>）
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif (!empty($bankNameFromFile)): ?>
                            <!-- ファイル名から銀行名を検出 -->
                            <div        class="rounded-lg p-2 mb-2" class="warning-box">
                                <h4      class="text-924 text-095" class="m-0-05">
                                    ファイル名から検出: <?= htmlspecialchars($bankNameFromFile) ?>
                                </h4>
                                <p        class="m-0 text-sm" class="text-783">
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
                            <div        class="info-box-success rounded-lg p-2 mb-2">
                                <h4      class="text-166 text-095 mb-075-m">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <path d="M14 2v6h6"/>
                                    </svg>
                                    PDFから抽出された金額
                                </h4>
                                <div  class="d-flex flex-wrap gap-1">
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
                                        <span       class="font-semibold text-166 p-05-10 rounded-6 section-title <?= $isSheetMatch ? 'bg-dcfce7 border-2-22c55e' : 'bg-white' ?>">
                                            ¥<?= number_format($amount) ?>
                                            <?php if ($isSheetMatch): ?>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3"       class="align-middle ml-025">
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
                                    <div        class="rounded-lg p-2 mb-2" style="background: <?= $bgColor ?>; border: 1px solid <?= $borderColor ?>; <?= $isPaidOff ? 'opacity: 0.7; ' : '' ?>">
                                        <h4      class="text-095 mb-075-m" style="color: <?= $textColor ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                <line x1="3" y1="9" x2="21" y2="9"/>
                                                <line x1="9" y1="21" x2="9" y2="9"/>
                                            </svg>
                                            スプレッドシート: <?= htmlspecialchars($displayName) ?>
                                            <?php if ($isPaidOff): ?>
                                                <span        class="badge-complete rounded text-xs ml-1">完済済み</span>
                                            <?php elseif ($entryHasMatch): ?>
                                                <span        class="badge-match rounded text-xs ml-1">一致</span>
                                            <?php else: ?>
                                                <span        class="badge-mismatch rounded text-xs ml-1">不一致</span>
                                            <?php endif; ?>
                                        </h4>
                                        <?php if ($isPaidOff): ?>
                                            <p      class="m-0 text-gray-500 text-09">この借入は完済済みです（データなし）</p>
                                        <?php else: ?>
                                        <div       class="grid gap-075 grid-auto-120">
                                            <div      class="p-1 rounded bg-white">
                                                <div  class="text-xs text-gray-500">元金</div>
                                                <div   class="font-semibold">¥<?= number_format($sheetEntry['principal']) ?></div>
                                            </div>
                                            <div      class="p-1 rounded bg-white">
                                                <div  class="text-xs text-gray-500">利息</div>
                                                <div   class="font-semibold">¥<?= number_format($sheetEntry['interest']) ?></div>
                                            </div>
                                            <div      class="p-1 rounded bg-white">
                                                <div  class="text-xs text-gray-500">合計（元金+利息）</div>
                                                <div       class="font-semibold <?= $entryHasMatch ? 'text-166534' : 'text-dc2626' ?>">¥<?= number_format($sheetEntry['total']) ?></div>
                                            </div>
                                            <div      class="p-1 rounded bg-white">
                                                <div  class="text-xs text-gray-500">残高</div>
                                                <div   class="font-semibold">¥<?= number_format($sheetEntry['balance']) ?></div>
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
                                    <div        class="rounded-lg p-2 mb-2 bg-ecfdf5 border-2-22c55e">
                                        <h4      class="text-166 text-095 mb-075-m">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                            一致した <?= count($matchedEntries) ?>件 をスプレッドシートに反映
                                        </h4>
                                        <div     class="mb-075 text-09">
                                            <?php foreach ($matchedEntries as $me): ?>
                                                <div     class="py-025">
                                                    ・<?= htmlspecialchars($me['bankName']) ?>
                                                    <?php if ($me['loanAmount']): ?>（<?= htmlspecialchars($me['loanAmount']) ?>）<?php endif; ?>
                                                    : ¥<?= number_format($me['amount']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <form method="POST" class="bulk-mark-spreadsheet-form">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="bulk_entries" value="<?= htmlspecialchars(json_encode($matchedEntries)) ?>">
                                            <input type="hidden" name="bulk_year_month" value="<?= htmlspecialchars($yearMonthForSheet) ?>">
                                            <input type="hidden" name="file_id" value="<?= htmlspecialchars($selectedFileId) ?>">
                                            <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                            <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                            <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                            <button type="submit" name="bulk_mark_spreadsheet"         class="btn bg-success text-base p-075-15">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
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
                                <div        class="rounded-lg p-2 mb-2 bg-f0f9ff border-90caf9">
                                    <h4      class="text-095 mb-075-m" class="text-1d4">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="3" y1="9" x2="21" y2="9"/>
                                            <line x1="9" y1="21" x2="9" y2="9"/>
                                        </svg>
                                        手動でスプレッドシートに反映
                                    </h4>
                                    <p      class="text-09 mb-075-m" class="text-1e4">
                                        対象: <strong><?= htmlspecialchars($yearMonthForSheet) ?></strong> / <strong><?= htmlspecialchars($displayBankName) ?></strong>
                                    </p>
                                    <form method="POST"    class="d-flex align-center flex-wrap gap-075">
                                        <?= csrfTokenField() ?>
                                        <label    class="text-09">金額を選択:</label>
                                        <select name="mark_amount"        class="p-1 text-base border-d1 rounded-6">
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
                                        <button type="submit" name="mark_spreadsheet"         class="btn btn-sm bg-success">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle mr-05">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                            反映する
                                        </button>
                                    </form>
                                </div>
                            <?php elseif (empty($yearMonthForSheet)): ?>
                                <div        class="rounded-lg mb-2 p-075" class="warning-box">
                                    <p      class="m-0 text-sm text-924">
                                        ファイル名「<?= htmlspecialchars($pdfFileName) ?>」から年月を抽出できませんでした（YYMM_形式が必要です）
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($sheetsDebugInfo): ?>
                                <!-- デバッグ情報 -->
                                <details        class="mt-2 rounded-lg p-075" class="warning-box">
                                    <summary      class="cursor-pointer font-medium text-924">デバッグ情報を表示</summary>
                                    <div        class="text-sm mt-075">
                                        <p><strong>検索した年月:</strong> <?= htmlspecialchars($sheetsDebugInfo['searchYearMonth']) ?></p>
                                        <p><strong>スプレッドシートB列の値（最初の30行）:</strong></p>
                                        <ul      class="pl-15 my-05-m">
                                            <?php foreach ($sheetsDebugInfo['columnBSample'] as $item): ?>
                                                <li>行<?= $item['row'] ?>: "<?= htmlspecialchars($item['value']) ?>"</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </details>
                            <?php endif; ?>
                        <?php elseif (!empty($extractedText)): ?>
                            <div        class="rounded-lg p-2 mb-2" class="warning-box">
                                <p      class="m-0 text-924">金額が見つかりませんでした（スキャン画像PDFの可能性があります）</p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($extractedText)): ?>
                            <details  class="mt-2">
                                <summary    class="cursor-pointer text-gray-500 text-14">抽出されたテキストを表示</summary>
                                <pre        class="p-2 mt-1 overflow-auto text-2xs max-h-300 bg-f9fafb rounded-6 whitespace-pre-wrap"><?= htmlspecialchars(mb_substr($extractedText, 0, 5000)) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <!-- 遅延読み込み時のローディング表示 -->
                    <?php if ($useLazyLoad && $syncFolder && !empty($syncFolder['id'])): ?>
                        <div id="lazy-load-container">
                            <div  class="d-flex align-center gap-2 mb-2 flex-wrap">
                                <label  class="font-medium">期:</label>
                                <select id="period-select"        class="text-base border-d1" class="p-pad-btn" disabled>
                                    <option>読み込み中...</option>
                                </select>

                                <label      class="font-medium ml-2">月次:</label>
                                <select id="month-select"        class="text-base border-d1" class="p-pad-btn" disabled>
                                    <option value="">期を選択してください</option>
                                </select>
                            </div>
                            <div id="folder-contents-container"></div>
                        </div>
                        <script<?= nonceAttr() ?>>
                        // XSS対策：HTMLエスケープ関数
                        function escapeHtml(str) {
                            if (!str) return '';
                            const div = document.createElement('div');
                            div.textContent = str;
                            return div.innerHTML;
                        }

                        // 遅延読み込み（高速化版：期と月次を1回のAPIで取得）
                        document.addEventListener('DOMContentLoaded', function() {
                            const syncFolderId = '<?= htmlspecialchars($syncFolder['id']) ?>';
                            const periodSelect = document.getElementById('period-select');
                            const monthSelect = document.getElementById('month-select');
                            const contentsContainer = document.getElementById('folder-contents-container');
                            let currentPeriodId = null;

                            // 期フォルダと最新期の月次フォルダを一度に取得（高速化）
                            fetch('../api/drive-api.php?action=list_periods_with_months')
                            .then(r => r.json())
                            .then(data => {
                                if (data.success && data.periods) {
                                    periodSelect.innerHTML = data.periods.map((p, i) =>
                                        `<option value="${escapeHtml(p.id)}" ${i === 0 ? 'selected' : ''}>${escapeHtml(p.name)}</option>`
                                    ).join('');
                                    periodSelect.disabled = false;
                                    currentPeriodId = data.latestPeriodId;

                                    // 月次フォルダも同時に設定（追加API不要）
                                    if (data.months && data.months.length > 0) {
                                        monthSelect.innerHTML = '<option value="">選択してください</option>' +
                                            data.months.map(m =>
                                                `<option value="${escapeHtml(m.id)}">${escapeHtml(m.name.replace(/_月次資料$/, ''))}</option>`
                                            ).join('');
                                        monthSelect.disabled = false;
                                    } else {
                                        monthSelect.innerHTML = '<option value="">月次フォルダなし</option>';
                                    }
                                }
                            })
                            .catch(err => {
                                periodSelect.innerHTML = '<option>エラー</option>';
                                console.error('フォルダ読み込みエラー:', err);
                            });

                            // 期が変更されたら月次フォルダを読み込み
                            periodSelect.addEventListener('change', function() {
                                loadMonths(this.value);
                            });

                            // 月次フォルダを読み込み（期変更時のみ使用）
                            function loadMonths(periodId) {
                                if (periodId === currentPeriodId) return; // 既に読み込み済み
                                currentPeriodId = periodId;
                                monthSelect.innerHTML = '<option>読み込み中...</option>';
                                monthSelect.disabled = true;
                                contentsContainer.innerHTML = '';

                                fetch('../api/drive-api.php?action=list_months&period_id=' + periodId)
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success && data.months) {
                                        monthSelect.innerHTML = '<option value="">選択してください</option>' +
                                            data.months.map(m =>
                                                `<option value="${escapeHtml(m.id)}">${escapeHtml(m.name.replace(/_月次資料$/, ''))}</option>`
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
                        <div  class="d-flex align-center gap-2 mb-2 flex-wrap">
                            <label  class="font-medium">期:</label>
                            <select id="periodSelect"        class="text-base border-d1" class="p-pad-btn">
                                <?php foreach ($periodFolders as $pf): ?>
                                    <option value="<?= htmlspecialchars($pf['id']) ?>" <?= $pf['id'] === $selectedPeriod ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pf['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (!empty($monthlyFolders)): ?>
                                <label      class="font-medium ml-2">月次:</label>
                                <select id="monthSelect" data-period="<?= htmlspecialchars($selectedPeriod) ?>" class="p-05-10 border-d1 rounded-6 text-10">
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
                            <div  class="d-flex justify-between align-center mb-2 flex-wrap gap-1">
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
                                <form method="POST"  class="d-inline">
                                    <?= csrfTokenField() ?>
                                    <input type="hidden" name="match_folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                    <input type="hidden" name="match_folder_name" value="<?= htmlspecialchars($currentFolderName) ?>">
                                    <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                    <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                    <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                    <input type="hidden" name="folder_name" value="<?= htmlspecialchars($currentFolderName) ?>">
                                    <button type="submit" name="bulk_match_folder"         class="btn bg-purple">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle mr-05">
                                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        一括照合
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                            <?php if ($bulkMatchResults !== null): ?>
                                <!-- 一括照合結果表示 -->
                                <div        class="info-box-purple rounded-lg p-3 mb-3 bg-white">
                                    <h4        class="d-flex align-center gap-1 m-0-1 text-6d28d9">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        一括照合結果: <?= htmlspecialchars($bulkMatchResults['folderName']) ?> (<?= htmlspecialchars($bulkMatchResults['yearMonth']) ?>)
                                        <?php if (isset($bulkMatchResults['cacheHits']) && $bulkMatchResults['cacheHits'] > 0): ?>
                                        <span    class="font-normal text-gray-500 ml-auto text-2xs">キャッシュ: <?= $bulkMatchResults['cacheHits'] ?>件</span>
                                        <?php endif; ?>
                                    </h4>

                                    <?php if (!empty($bulkMatchResults['matches'])): ?>
                                        <div        class="p-2 mb-2 bg-ecfdf5 border-86efac rounded-6">
                                            <h5      class="text-166 text-095 mb-075-m">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle mr-05">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                                一致: <?= count($bulkMatchResults['matches']) ?>件
                                            </h5>
                                            <table        class="w-full text-09 border-collapse">
                                                <thead>
                                                    <tr     class="bg-white-50">
                                                        <th      class="p-1 text-left border-b-86">PDFファイル</th>
                                                        <th      class="p-1 text-left border-b-86">銀行（スプシ）</th>
                                                        <th      class="p-1 text-right border-b-86">金額</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($bulkMatchResults['matches'] as $match): ?>
                                                    <tr>
                                                        <td      class="p-1 border-b-d1"><?= htmlspecialchars($match['fileName']) ?></td>
                                                        <td      class="p-1 border-b-d1">
                                                            <?= htmlspecialchars($match['sheetBankName']) ?>
                                                            <?php if ($match['loanAmount']): ?>
                                                                <span    class="text-gray-500 text-2xs">（<?= htmlspecialchars($match['loanAmount']) ?>）</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td      class="p-1 text-right font-semibold border-b-d1">¥<?= number_format($match['amount']) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($bulkMatchResults['noMatches'])): ?>
                                        <div        class="p-2 mb-2 bg-fef3c7 border-fbbf24 rounded-6">
                                            <h5      class="text-924 text-095 mb-075-m">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle mr-05">
                                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                                </svg>
                                                不一致: <?= count($bulkMatchResults['noMatches']) ?>件
                                            </h5>
                                            <div  class="text-sm">
                                                <?php foreach ($bulkMatchResults['noMatches'] as $noMatch): ?>
                                                <div     class="py-05 border-fde68a">
                                                    <div      class="font-medium mb-025"><?= htmlspecialchars($noMatch['fileName']) ?></div>
                                                    <div      class="mb-025" class="text-783">
                                                        PDF抽出金額: <?php echo implode(', ', array_map(fn($a) => '¥' . number_format($a), array_slice($noMatch['pdfAmounts'], 0, 10))); ?>
                                                        <?php if (count($noMatch['pdfAmounts']) > 10): ?>...他<?= count($noMatch['pdfAmounts']) - 10 ?>件<?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($bulkMatchResults['errors'])): ?>
                                        <div        class="info-box-danger p-2 mb-2 rounded-6">
                                            <h5       class="text-red text-095" class="m-0-05">エラー: <?= count($bulkMatchResults['errors']) ?>件</h5>
                                            <div        class="text-sm" class="text-991">
                                                <?php foreach ($bulkMatchResults['errors'] as $err): ?>
                                                <div     class="py-025"><?= htmlspecialchars($err['fileName']) ?>: <?= htmlspecialchars($err['message']) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($bulkMatchResults['matches'])): ?>
                                        <!-- 一括登録ボタン -->
                                        <form method="POST"  class="mt-2" class="apply-bulk-match-form">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="apply_entries" value="<?= htmlspecialchars(json_encode($bulkMatchResults['matches'])) ?>">
                                            <input type="hidden" name="apply_year_month" value="<?= htmlspecialchars($bulkMatchResults['yearMonth']) ?>">
                                            <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
                                            <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                                            <input type="hidden" name="folder_id" value="<?= htmlspecialchars($selectedFolderId) ?>">
                                            <input type="hidden" name="folder_name" value="<?= htmlspecialchars($_GET['folder_name'] ?? '') ?>">
                                            <button type="submit" name="apply_bulk_match"         class="btn bg-success text-base p-075-20">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
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
                                    <table        class="w-full border-collapse">
                                        <thead>
                                            <tr     class="bg-f9fafb border-b-2">
                                                <th      class="text-left p-075">ファイル名</th>
                                                <th      class="text-right w-100 p-075">サイズ</th>
                                                <th      class="text-center p-075 w-120">更新日</th>
                                                <th      class="text-center w-100 p-075">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($folderContents['files'] as $file): ?>
                                                <tr     class="border-b-2">
                                                    <td    class="p-075">
                                                        <div    class="d-flex align-center gap-075">
                                                            <div        class="rounded d-flex align-center justify-center w-32 h-32 bg-ef4">
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                                            </div>
                                                            <span  class="font-medium"><?= htmlspecialchars($file['name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td      class="text-right text-gray-500 text-14 p-075">
                                                        <?= isset($file['size']) ? number_format($file['size'] / 1024, 0) . 'KB' : '-' ?>
                                                    </td>
                                                    <td      class="text-center text-gray-500 text-14 p-075">
                                                        <?= isset($file['modifiedTime']) ? date('Y/m/d', strtotime($file['modifiedTime'])) : '-' ?>
                                                    </td>
                                                    <td      class="text-center p-075">
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
                                <div   class="folder-grid mt-2">
                                    <?php foreach ($folderContents['files'] as $file): ?>
                                        <a href="?period=<?= htmlspecialchars($selectedPeriod) ?>&month=<?= htmlspecialchars($selectedMonth) ?>&file_id=<?= htmlspecialchars($file['id']) ?>" class="file-item-card">
                                            <div         class="file-icon" class="bg-ef4">
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
                <div        class="p-2 rounded-lg mb-3 bg-f0f9ff">
                    <p        class="font-medium mb-075-m">共有フォルダのIDを入力:</p>
                    <p        class="text-gray-500 text-14" class="m-0-1">
                        Google DriveのフォルダURLから「folders/」の後ろの部分をコピーしてください<br>
                        例: https://drive.google.com/drive/folders/<strong>1iCPEOmRroKpI1N_Iyi1mWFlfsPJRNiXa</strong>
                    </p>
                    <form method="POST"  class="d-flex gap-1 flex-wrap">
                        <?= csrfTokenField() ?>
                        <input type="text" name="folder_id"         class="form-input flex-1 min-w-300" placeholder="フォルダID（例: 1iCPEOmRroKpI1N_Iyi1mWFlfsPJRNiXa）" required>
                        <input type="text" name="folder_name"       class="form-input w-200" placeholder="フォルダ名（任意）" value="借入金返済">
                        <button type="submit" name="set_sync_folder" class="btn btn-primary">設定</button>
                    </form>
                </div>

                <?php if (!empty($driveFolders)): ?>
                    <p        class="mb-2 text-gray-600">または、マイドライブからフォルダを選択:</p>
                    <div class="folder-grid">
                        <?php foreach ($driveFolders as $folder): ?>
                            <form method="POST"     class="display-contents">
                                <?= csrfTokenField() ?>
                                <input type="hidden" name="folder_id" value="<?= htmlspecialchars($folder['id']) ?>">
                                <input type="hidden" name="folder_name" value="<?= htmlspecialchars($folder['name']) ?>">
                                <button type="submit" name="set_sync_folder"         class="folder-item w-full text-left border-0">
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
            <p     class="my-2 text-gray-600">
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
                    <path d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4z"/>
                </svg>
                借入先を追加
            </h3>
            <button type="button" class="modal-close close-add-loan-modal-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div   class="form-group mb-2">
                    <label  class="d-block mb-1 font-medium">借入先名 <span     class="text-ef4">*</span></label>
                    <input type="text" name="name"  placeholder="例: 中国銀行" required  class="form-input w-full">
                </div>
                <div    class="gap-2 mb-2 grid grid-cols-2">
                    <div class="form-group">
                        <label  class="d-block mb-1 font-medium">借入額</label>
                        <input type="number" name="initial_amount"  placeholder="例: 10000000"  class="form-input w-full">
                    </div>
                    <div class="form-group">
                        <label  class="d-block mb-1 font-medium">借入開始日</label>
                        <input type="date" name="start_date"   class="form-input w-full">
                    </div>
                </div>
                <div    class="gap-2 mb-2 grid grid-cols-2">
                    <div class="form-group">
                        <label  class="d-block mb-1 font-medium">金利 (%)</label>
                        <input type="number" name="interest_rate"  step="0.01" placeholder="例: 1.5"  class="form-input w-full">
                    </div>
                    <div class="form-group">
                        <label  class="d-block mb-1 font-medium">返済日（毎月）</label>
                        <input type="number" name="repayment_day"  min="1" max="31" value="25"  class="form-input w-full">
                    </div>
                </div>
                <div class="form-group">
                    <label  class="d-block mb-1 font-medium">備考</label>
                    <input type="text" name="notes"  placeholder="メモ"  class="form-input w-full">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-add-loan-modal-btn">キャンセル</button>
                <button type="submit" name="add_loan" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle mr-05">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    追加
                </button>
            </div>
        </form>
    </div>
</div>

<script<?= nonceAttr() ?>>
// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // Drive連携解除フォーム
    const disconnectDriveForm = document.querySelector('.disconnect-drive-form');
    if (disconnectDriveForm) {
        disconnectDriveForm.addEventListener('submit', function(e) {
            if (!confirm('連携を解除しますか？')) {
                e.preventDefault();
            }
        });
    }

    // 一括色付けフォーム
    document.querySelectorAll('.bulk-mark-spreadsheet-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            handleBulkMarkSpreadsheet(e);
            e.preventDefault();
            return false;
        });
    });

    // 一括登録フォーム
    document.querySelectorAll('.apply-bulk-match-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            handleApplyBulkMatch(e);
            e.preventDefault();
            return false;
        });
    });

    // 期セレクト
    const periodSelect = document.getElementById('periodSelect');
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            location.href = '?period=' + this.value;
        });
    }

    // 月次セレクト
    const monthSelect = document.getElementById('monthSelect');
    if (monthSelect) {
        monthSelect.addEventListener('change', function() {
            const period = this.getAttribute('data-period');
            location.href = '?period=' + period + '&month=' + this.value;
        });
    }

    // モーダル閉じるボタン
    document.querySelectorAll('.close-add-loan-modal-btn').forEach(btn => {
        btn.addEventListener('click', closeAddLoanModal);
    });
});

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
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"        class="mr-1 align-middle" class="spin">
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
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="mr-1 align-middle">
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

</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
