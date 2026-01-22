<?php
/**
 * 損益計算書関連の関数
 */

/**
 * 損益計算書CSVをパース
 *
 * @param string $filePath CSVファイルパス
 * @return array パースされたデータ
 */
function parseProfitLossCSV($filePath) {
    $data = array();

    // ファイル全体を読み込み、エンコーディングを検出して変換
    $content = file_get_contents($filePath);

    // エンコーディングを検出（Shift_JIS, UTF-8, etc）
    $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS', 'EUC-JP', 'ASCII'], true);

    // UTF-8に変換
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    // BOMを除去
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    // 行ごとに分割
    $lines = explode("\n", $content);

    $rowIndex = 0;
    foreach ($lines as $line) {
        $rowIndex++;

        // 1行目（ヘッダー行）をスキップ
        if ($rowIndex === 1) {
            continue;
        }

        // 空行をスキップ
        if (trim($line) === '') {
            continue;
        }

        // CSV行をパース
        $row = str_getcsv($line);

        // 空行をスキップ
        if (empty(array_filter($row))) {
            continue;
        }

        // データをパース
        $parsedRow = array(
            'account' => trim($row[0] ?? ''),  // 勘定科目
            'sub_account' => trim($row[1] ?? ''),  // 補助科目
            'months' => array(
                '09' => parseNumber($row[2] ?? '0'),  // 9月
                '10' => parseNumber($row[3] ?? '0'),  // 10月
                '11' => parseNumber($row[4] ?? '0'),  // 11月
                '12' => parseNumber($row[5] ?? '0'),  // 12月
                '01' => parseNumber($row[6] ?? '0'),  // 1月
                '02' => parseNumber($row[7] ?? '0'),  // 2月
                '03' => parseNumber($row[8] ?? '0'),  // 3月
                '04' => parseNumber($row[9] ?? '0'),  // 4月
                '05' => parseNumber($row[10] ?? '0'), // 5月
                '06' => parseNumber($row[11] ?? '0'), // 6月
                '07' => parseNumber($row[12] ?? '0'), // 7月
                '08' => parseNumber($row[13] ?? '0'), // 8月
            ),
            'adjustment' => parseNumber($row[14] ?? '0'),  // 決算整理
            'total' => parseNumber($row[15] ?? '0'),  // 合計
        );

        $data[] = $parsedRow;
    }

    return $data;
}

/**
 * 数値文字列をパース
 *
 * @param string $value 数値文字列
 * @return float パースされた数値
 */
function parseNumber($value) {
    // カンマを除去
    $value = str_replace(',', '', trim($value));

    // 空文字列は0として扱う
    if ($value === '') {
        return 0;
    }

    // 数値に変換
    return floatval($value);
}

/**
 * 損益計算書データを保存
 *
 * @param string $fiscalYear 会計年度
 * @param array $data 損益計算書データ
 * @return bool 成功した場合true
 */
function saveProfitLossData($fiscalYear, $data) {
    $dataDir = __DIR__ . '/../data/profit-loss';

    // ディレクトリが存在しない場合は作成
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $filePath = $dataDir . '/' . $fiscalYear . '.json';

    $saveData = array(
        'fiscal_year' => $fiscalYear,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'data' => $data
    );

    $result = file_put_contents(
        $filePath,
        json_encode($saveData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    if ($result === false) {
        throw new Exception('データの保存に失敗しました');
    }

    return true;
}

/**
 * 損益計算書データを読み込み
 *
 * @param string $fiscalYear 会計年度
 * @return array|null 損益計算書データ、存在しない場合null
 */
function loadProfitLossData($fiscalYear) {
    $filePath = __DIR__ . '/../data/profit-loss/' . $fiscalYear . '.json';

    if (!file_exists($filePath)) {
        return null;
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);

    return $data;
}

/**
 * 保存されている会計年度の一覧を取得
 *
 * @return array 会計年度の配列
 */
function getAvailableFiscalYears() {
    $dataDir = __DIR__ . '/../data/profit-loss';

    if (!is_dir($dataDir)) {
        return array();
    }

    $files = glob($dataDir . '/*.json');
    $years = array();

    foreach ($files as $file) {
        $filename = basename($file, '.json');
        $years[] = $filename;
    }

    rsort($years); // 降順にソート
    return $years;
}

/**
 * 特定の勘定科目のデータを取得
 *
 * @param array $profitLossData 損益計算書データ
 * @param string $accountName 勘定科目名
 * @return array|null 該当する行、見つからない場合null
 */
function findAccountData($profitLossData, $accountName) {
    if (!isset($profitLossData['data'])) {
        return null;
    }

    foreach ($profitLossData['data'] as $row) {
        if ($row['account'] === $accountName) {
            return $row;
        }
    }

    return null;
}

/**
 * 月次合計を計算
 *
 * @param array $profitLossData 損益計算書データ
 * @param string $month 月（'01'〜'12'）
 * @return float 合計金額
 */
function calculateMonthTotal($profitLossData, $month) {
    if (!isset($profitLossData['data'])) {
        return 0;
    }

    $total = 0;
    foreach ($profitLossData['data'] as $row) {
        if (isset($row['months'][$month])) {
            $total += $row['months'][$month];
        }
    }

    return $total;
}
