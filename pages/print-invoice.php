<?php
require_once '../api/auth.php';
require_once '../api/mf-api.php';

// 閲覧権限チェック
if (!hasPermission('product')) {
    header('Location: index.php');
    exit;
}

// MF請求書IDを取得
$billingId = $_GET['id'] ?? null;
if (!$billingId) {
    die('請求書IDが指定されていません');
}

// MF APIから請求書データを取得
try {
    if (!MFApiClient::isConfigured()) {
        die('MFクラウド請求書APIが設定されていません');
    }

    $client = new MFApiClient();
    $template = $client->getInvoiceDetail($billingId);

    if (!isset($template['billing'])) {
        die('請求書が見つかりません');
    }

    $billing = $template['billing'];
    $partner = $template['partner'] ?? [];
    $items = $billing['items'] ?? [];

} catch (Exception $e) {
    die('エラー: ' . htmlspecialchars($e->getMessage()));
}

// 日付フォーマット
function formatJapaneseDate($date) {
    if (!$date) return '';
    $timestamp = strtotime($date);
    return date('Y', $timestamp) . '年' . date('n', $timestamp) . '月' . date('j', $timestamp) . '日';
}

// 金額計算
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
}
$tax = floor($subtotal * 0.1); // 10%消費税
$total = $subtotal + $tax;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>請求書 - <?= htmlspecialchars($partner['name'] ?? '') ?></title>
    <style<?= nonceAttr() ?>>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'MS Gothic', 'Meiryo', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            background: white;
        }

        .invoice-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 10mm;
            background: white;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .header-left h1 {
            font-size: 24pt;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .client-info {
            font-size: 11pt;
            margin-bottom: 10px;
        }

        .client-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-info {
            text-align: right;
            font-size: 9pt;
            line-height: 1.6;
        }

        .company-name {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-logo {
            width: 80px;
            height: 80px;
            border: 1px solid #000;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8pt;
        }

        .registration-number {
            font-size: 9pt;
            margin-top: 5px;
        }

        .tax-code {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }

        .tax-digit {
            width: 20px;
            height: 25px;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11pt;
            font-weight: bold;
        }

        .invoice-date-section {
            text-align: right;
            margin: 20px 0;
            font-size: 12pt;
        }

        .invoice-date {
            display: inline-block;
            background: #ffff00;
            padding: 5px 10px;
            font-weight: bold;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 9pt;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }

        .items-table th {
            background: #e0e0e0;
            font-weight: bold;
        }

        .items-table td.left {
            text-align: left;
        }

        .items-table td.right {
            text-align: right;
        }

        .summary-table {
            width: 100%;
            margin: 20px 0;
            font-size: 10pt;
        }

        .summary-table td {
            padding: 5px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }

        .summary-label {
            font-weight: bold;
            width: 150px;
            text-align: right;
            padding-right: 20px;
        }

        .summary-value {
            width: 150px;
            text-align: right;
            border-bottom: 1px solid #000;
        }

        .total-row {
            font-size: 14pt;
            font-weight: bold;
            margin-top: 15px;
        }

        .footer {
            margin-top: 30px;
            font-size: 9pt;
        }

        .bank-info {
            margin: 20px 0;
            line-height: 1.8;
        }

        .notes {
            margin-top: 20px;
            font-size: 8pt;
            line-height: 1.6;
        }

        @media print {
            body {
                margin: 0;
            }
            .invoice-container {
                width: 100%;
                margin: 0;
                padding: 10mm;
            }
            .no-print {
                display: none;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .print-button:hover {
            background: #1976d2;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" id="printBtn">🖨️ 印刷</button>

    <div class="invoice-container">
        <!-- ヘッダー -->
        <div class="header">
            <div class="header-left">
                <h1>請求書</h1>
                <div class="client-info">
                    <div class="client-name"><?= htmlspecialchars($partner['name'] ?? '') ?> 御中</div>
                </div>
            </div>
            <div class="header-right">
                <div class="company-info">
                    <div class="company-name">ヤマト広告株式会社</div>
                    <div>大阪府大阪市北区西天満2丁目6-8</div>
                    <div>堂島ビルディング 6C号室</div>
                    <div class="registration-number">登録番号（T+13桁）</div>
                    <div class="tax-code">
                        <span>T</span>
                        <?php
                        $regNumber = '6260100006';
                        for ($i = 0; $i < strlen($regNumber); $i++) {
                            echo '<div class="tax-digit">' . $regNumber[$i] . '</div>';
                        }
                        ?>
                    </div>
                    <div     class="mt-10">取引先コード（7桁+3桁000）</div>
                    <div         class="tax-code mt-05">
                        <?php
                        $partnerCode = str_pad($partner['code'] ?? '0000000', 10, '0');
                        for ($i = 0; $i < 10; $i++) {
                            echo '<div class="tax-digit">' . ($partnerCode[$i] ?? '0') . '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 請求日 -->
        <div class="invoice-date-section">
            <span>請求日</span>
            <span class="invoice-date"><?= formatJapaneseDate($billing['billing_date']) ?></span>
        </div>

        <!-- 明細表 -->
        <table class="items-table">
            <thead>
                <tr>
                    <th   class="w-80">納入日</th>
                    <th    class="w-200">品　　　　名</th>
                    <th   class="w-60">軽減<br>税率</th>
                    <th   class="w-50">数 量</th>
                    <th   class="w-80">単　価</th>
                    <th   class="w-100">金　額</th>
                    <th    class="w-120">備　　　考</th>
                    <th   class="w-80">注文No.</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rowCount = 0;
                foreach ($items as $item):
                    $amount = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                    $rowCount++;
                ?>
                <tr>
                    <td></td>
                    <td class="left"><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td></td>
                    <td><?= number_format($item['quantity'] ?? 0) ?></td>
                    <td class="right">¥<?= number_format($item['unit_price'] ?? 0) ?></td>
                    <td class="right">¥<?= number_format($amount) ?></td>
                    <td class="left"><?= htmlspecialchars($item['detail'] ?? '') ?></td>
                    <td></td>
                </tr>
                <?php endforeach; ?>

                <?php
                // 空行を追加して最低12行にする
                for ($i = $rowCount; $i < 12; $i++):
                ?>
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- 合計金額 -->
        <div class="summary-table">
            <div class="summary-row">
                <div     class="w-60-percent">
                    <div     class="border-black p-10 min-h-80">
                        <strong>※軽減税率対象品目</strong>
                        <div     class="mt-10 ml-150">
                            <div      class="d-flex justify-between my-5">
                                <span>税抜</span>
                                <span>¥0</span>
                            </div>
                            <div      class="d-flex justify-between my-5">
                                <span>10%対象小計</span>
                                <span>¥0</span>
                            </div>
                            <div      class="d-flex justify-between my-5">
                                <span>8%対象小計</span>
                                <span>¥0</span>
                            </div>
                            <div      class="d-flex justify-between my-5">
                                <span>非課税小計</span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div     class="w-38-percent ml-2">
                    <div     class="border-black p-10">
                        <strong>弊社使用欄</strong>
                        <div     class="mt-10">
                            <div      class="d-flex justify-between my-5">
                                <span>訂正額</span>
                                <span></span>
                            </div>
                            <div      class="d-flex justify-between my-5">
                                <span>計上額</span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div         class="summary-row mt-20">
                <div class="summary-label">小計</div>
                <div class="summary-value">¥<?= number_format($subtotal) ?></div>
            </div>
            <div class="summary-row">
                <div class="summary-label">消費税</div>
                <div class="summary-value">¥<?= number_format($tax) ?></div>
            </div>
            <div class="summary-row total-row">
                <div class="summary-label">請求合計</div>
                <div         class="summary-value text-16pt">¥<?= number_format($total) ?> 円</div>
            </div>
        </div>

        <!-- フッター -->
        <div class="footer">
            <div class="bank-info">
                <div>1.明細を別紙にて添付いただいたく場合は、必ず用紙はA4サイズにてご提出ください。</div>
                <div     class="ml-10">A4サイズでのご提出が難しいようでしたら各面倒でも指定請求書に明細をご記入ください。</div>
                <div>2.ホッチキスはご使用なさらないようお願い致します。</div>
                <div>3.最新の請求書は <u    class="text-blue">https://www.aktio.co.jp/supplier/download/</u> ※確認はこちらより！</div>
                <div        class="font-bold mt-15">（振込先）</div>
                <table     class="ml-20 line-height-18">
                    <tr>
                        <td  class="w-150">楽天</td>
                        <td   class="w-100">銀行</td>
                        <td   class="w-100">ビート</td>
                        <td>支店</td>
                    </tr>
                    <tr>
                        <td>口座番号</td>
                        <td>普通 No.</td>
                        <td colspan="2">7021429</td>
                    </tr>
                    <tr>
                        <td>口座名義力</td>
                        <td colspan="3">ヤマトコウコク(カ</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <script<?= nonceAttr() ?>>
        // 印刷ボタン
        document.getElementById('printBtn')?.addEventListener('click', function() {
            window.print();
        });

        // URLパラメータにauto_printがあれば自動印刷
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>
