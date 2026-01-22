<?php
require_once '../api/auth.php';

// 役員判定用の社員番号（馬庭社長）
$EXECUTIVE_EMPLOYEE_NO = '000001';

// 仕訳マッピング定義
$JOURNAL_MAPPINGS = [
    // 借方（費用）
    'debit' => [
        'executive_salary' => [
            'account' => '役員報酬',
            'sub_account' => '',
            'tax_class' => '対象外仕入',
            'description' => '役員報酬'
        ],
        'salary' => [
            'account' => '給料賃金',
            'sub_account' => '',
            'tax_class' => '対象外仕入',
            'description' => '従業員給与'
        ],
        'commute_taxable' => [
            'account' => '旅費交通費',
            'sub_account' => '通勤手当',
            'tax_class' => '課税仕入 10%',
            'description' => '通勤交通費'
        ]
    ],
    // 貸方（控除・負債）
    'credit' => [
        'social_insurance' => [
            'account' => '預り金',
            'sub_account' => '社会保険料',
            'tax_class' => '対象外',
            'description' => '健康保険料・介護保険料・厚生年金'
        ],
        'employment_insurance' => [
            'account' => '法定福利費',
            'sub_account' => '',
            'tax_class' => '対象外仕入',
            'description' => '雇用保険料'
        ],
        'income_tax' => [
            'account' => '預り金',
            'sub_account' => '源泉所得税_給与',
            'tax_class' => '対象外',
            'description' => '源泉所得税'
        ],
        'resident_tax' => [
            'account' => '預り金',
            'sub_account' => '住民税',
            'tax_class' => '対象外',
            'description' => '住民税'
        ],
        'defined_contribution' => [
            'account' => '預り金',
            'sub_account' => '確定拠出年金',
            'tax_class' => '対象外',
            'description' => '確定拠出年金'
        ],
        'net_payment' => [
            'account' => '未払費用',
            'sub_account' => '給与',
            'tax_class' => '対象外',
            'description' => '支給額'
        ],
        'company_social_insurance' => [
            'account' => '未払費用',
            'sub_account' => '社会保険料',
            'tax_class' => '対象外',
            'description' => '社会保険料概算（社保+2.33%）'
        ],
        'advance_payment' => [
            'account' => '未払金',
            'sub_account' => '従業員立替_',
            'tax_class' => '対象外',
            'description' => '立替金'
        ],
        'misc_income_family' => [
            'account' => '雑収入',
            'sub_account' => '',
            'tax_class' => '非課税売上',
            'description' => '甘家賃'
        ],
        'misc_income_rent' => [
            'account' => '雑収入',
            'sub_account' => '',
            'tax_class' => '非課税売上',
            'description' => '周家賃'
        ]
    ]
];

require_once '../functions/header.php';
?>

<style>
.payroll-container {
    max-width: 1400px;
    margin: 0 auto;
}

.upload-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.upload-section h2 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--gray-800);
}

.upload-area {
    border: 2px dashed var(--gray-300);
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--gray-50);
}

.upload-area:hover {
    border-color: var(--primary);
    background: rgba(59, 130, 246, 0.05);
}

.upload-area.dragover {
    border-color: var(--primary);
    background: rgba(59, 130, 246, 0.1);
}

.upload-area svg {
    width: 48px;
    height: 48px;
    color: var(--gray-400);
    margin-bottom: 1rem;
}

.upload-area p {
    margin: 0;
    color: var(--gray-600);
}

.upload-area .file-types {
    font-size: 0.875rem;
    color: var(--gray-500);
    margin-top: 0.5rem;
}

#fileInput {
    display: none;
}

.date-input-group {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.date-input-group label {
    font-weight: 500;
    color: var(--gray-700);
}

.date-input-group input {
    padding: 0.5rem 1rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 1rem;
}

/* 仕訳プレビュー */
.journal-preview {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: none;
}

.journal-preview.active {
    display: block;
}

.journal-preview h2 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--gray-800);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.journal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.journal-table th,
.journal-table td {
    padding: 0.75rem;
    border: 1px solid var(--gray-200);
    text-align: left;
}

.journal-table th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
    white-space: nowrap;
}

.journal-table td {
    background: white;
}

.journal-table td.amount {
    text-align: right;
    font-family: monospace;
}

.journal-table tr:hover td {
    background: var(--gray-50);
}

.journal-table input {
    width: 100%;
    padding: 0.375rem 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 0.875rem;
}

.journal-table input.amount-input {
    text-align: right;
    font-family: monospace;
}

.journal-table input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

/* 集計表示 */
.summary-row {
    background: var(--gray-100) !important;
    font-weight: 600;
}

.summary-row td {
    background: var(--gray-100) !important;
}

/* ボタン */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-secondary {
    background: var(--gray-200);
    color: var(--gray-700);
}

.btn-secondary:hover {
    background: var(--gray-300);
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* 履歴セクション */
.history-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.history-section h2 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--gray-800);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.history-list {
    max-height: 300px;
    overflow-y: auto;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: var(--gray-50);
    transition: all 0.2s;
}

.history-item:hover {
    background: white;
    border-color: var(--primary);
}

.history-item-info {
    flex: 1;
}

.history-item-month {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 1rem;
}

.history-item-date {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
}

.history-item-amount {
    text-align: right;
    margin-right: 1rem;
}

.history-item-amount .debit {
    font-size: 0.875rem;
    color: var(--gray-600);
}

.history-item-amount .balance {
    font-size: 0.75rem;
    color: #10b981;
}

.history-item-actions {
    display: flex;
    gap: 0.5rem;
}

.history-item-actions button {
    padding: 0.375rem 0.75rem;
    border: none;
    border-radius: 4px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-load {
    background: var(--primary);
    color: white;
}

.btn-load:hover {
    background: #2563eb;
}

.btn-delete {
    background: var(--gray-200);
    color: var(--gray-600);
}

.btn-delete:hover {
    background: #fee2e2;
    color: #dc2626;
}

.no-history {
    text-align: center;
    color: var(--gray-500);
    padding: 2rem;
}

/* ローディング */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.8);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.loading-overlay.active {
    display: flex;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--gray-200);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* 借方・貸方ラベル */
.debit-label {
    color: #dc2626;
    font-weight: 600;
}

.credit-label {
    color: #2563eb;
    font-weight: 600;
}

/* アラート */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
</style>

<div class="payroll-container">
    <h1 style="margin-bottom: 1.5rem;">給与仕訳変換</h1>

    <div class="alert alert-info">
        <strong>使い方:</strong> 支払い控除一覧表のExcelファイルをアップロードすると、自動でMF会計用の仕訳データに変換します。
    </div>

    <!-- 処理履歴セクション -->
    <div class="history-section">
        <h2>
            処理履歴
            <button type="button" class="btn btn-secondary" id="clearHistoryBtn" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                履歴をクリア
            </button>
        </h2>
        <div class="history-list" id="historyList">
            <p class="no-history">まだ処理履歴がありません</p>
        </div>
    </div>

    <!-- アップロードセクション -->
    <div class="upload-section">
        <h2>1. Excelファイルをアップロード</h2>

        <div class="date-input-group">
            <label for="payrollMonth">対象年月:</label>
            <input type="month" id="payrollMonth" value="<?= date('Y-m') ?>" style="padding: 0.5rem 1rem; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 1rem;">
        </div>

        <div class="date-input-group">
            <label for="journalDate">仕訳日付:</label>
            <input type="date" id="journalDate" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="date-input-group">
            <label for="yearEndAdjustment">
                <input type="checkbox" id="yearEndAdjustment" style="margin-right: 0.5rem;">
                年末調整を含める（12月のみ）
            </label>
        </div>

        <div class="upload-area" id="uploadArea">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <p>クリックまたはドラッグ&ドロップでファイルを選択</p>
            <p class="file-types">対応形式: .xlsx, .xls</p>
        </div>
        <input type="file" id="fileInput" accept=".xlsx,.xls">
    </div>

    <!-- 仕訳プレビュー -->
    <div class="journal-preview" id="journalPreview">
        <h2>
            2. 仕訳データの確認・編集
            <span style="font-size: 0.875rem; font-weight: normal; color: var(--gray-500);">
                必要に応じて金額や科目を修正できます
            </span>
        </h2>

        <div style="overflow-x: auto;">
            <table class="journal-table" id="journalTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 100px;">取引日</th>
                        <th style="width: 100px;"><span class="debit-label">借方</span>勘定科目</th>
                        <th style="width: 100px;"><span class="debit-label">借方</span>補助科目</th>
                        <th style="width: 100px;"><span class="debit-label">借方</span>税区分</th>
                        <th style="width: 100px;"><span class="debit-label">借方</span>金額</th>
                        <th style="width: 100px;"><span class="credit-label">貸方</span>勘定科目</th>
                        <th style="width: 100px;"><span class="credit-label">貸方</span>補助科目</th>
                        <th style="width: 100px;"><span class="credit-label">貸方</span>税区分</th>
                        <th style="width: 100px;"><span class="credit-label">貸方</span>金額</th>
                        <th style="width: 150px;">摘要</th>
                    </tr>
                </thead>
                <tbody id="journalBody">
                </tbody>
            </table>
        </div>

        <div class="action-buttons">
            <button type="button" class="btn btn-secondary" id="resetBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="1 4 1 10 7 10"/>
                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                </svg>
                リセット
            </button>
            <button type="button" class="btn btn-primary" id="saveHistoryBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                履歴に保存
            </button>
            <button type="button" class="btn btn-success" id="downloadBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                MF会計用CSVダウンロード
            </button>
        </div>
    </div>
</div>

<!-- ローディング -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- SheetJS (Excel読み込み用) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
// グローバル変数
let sourceData = null;
let journalEntries = [];

// 役員の社員番号
const EXECUTIVE_EMPLOYEE_NO = '<?= $EXECUTIVE_EMPLOYEE_NO ?>';

// 仕訳マッピング
const JOURNAL_MAPPINGS = <?= json_encode($JOURNAL_MAPPINGS, JSON_UNESCAPED_UNICODE) ?>;

// DOM要素
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const journalPreview = document.getElementById('journalPreview');
const loadingOverlay = document.getElementById('loadingOverlay');

// ファイルアップロード処理
uploadArea.addEventListener('click', () => fileInput.click());

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) processFile(file);
});

fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) processFile(file);
});

// Excelファイル処理
function processFile(file) {
    if (!file.name.match(/\.(xlsx|xls)$/i)) {
        alert('Excelファイル（.xlsx, .xls）を選択してください');
        return;
    }

    loadingOverlay.classList.add('active');

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });

            // 最初のシートを取得
            const sheetName = workbook.SheetNames[0];
            const sheet = workbook.Sheets[sheetName];

            // JSONに変換
            const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1 });

            // データを解析
            sourceData = parsePayrollData(jsonData);

            if (sourceData) {
                generateJournalEntries(sourceData);
                displayJournalEntries();
                journalPreview.classList.add('active');
            } else {
                alert('データの形式が正しくありません。支払い控除一覧表のExcelファイルを選択してください。');
            }
        } catch (error) {
            console.error('Error processing file:', error);
            alert('ファイルの読み込みに失敗しました: ' + error.message);
        } finally {
            loadingOverlay.classList.remove('active');
        }
    };
    reader.readAsArrayBuffer(file);
}

// 給与データを解析
function parsePayrollData(jsonData) {
    // このExcelフォーマットの特徴:
    // - 縦型レイアウト（項目名が縦に並び、社員データが横に並ぶ）
    // - 10行目あたり: NO行（社員番号）
    // - 13行目あたり: 氏名行
    // - その後に各項目（基本給、手当、控除など）が縦に並ぶ

    let noRowIndex = -1;
    let nameRowIndex = -1;
    let dataColStartIndex = -1;

    // デバッグ: 最初の15行の内容を出力
    console.log('=== Excel Data Preview (first 15 rows) ===');
    for (let i = 0; i < Math.min(15, jsonData.length); i++) {
        const row = jsonData[i];
        if (row) {
            console.log(`Row ${i}:`, row.slice(0, 10));
        }
    }

    // NO行と氏名行を探す（A列だけでなく、全列を検索）
    for (let i = 0; i < Math.min(50, jsonData.length); i++) {
        const row = jsonData[i];
        if (!row) continue;

        // 各セルを検索
        for (let j = 0; j < Math.min(10, row.length); j++) {
            const cell = (row[j] || '').toString().trim().toUpperCase();

            // NO行を探す（大文字小文字を無視、様々な表記に対応）
            if (cell === 'NO' || cell === 'NO.' || cell === 'ＮＯ' || cell === '社員番号' || cell === '番号') {
                noRowIndex = i;
                // データが始まる列を特定（NOの次の列から最初の数字がある列）
                for (let k = j + 1; k < row.length; k++) {
                    if (row[k] && row[k].toString().match(/^\d+$/)) {
                        dataColStartIndex = k;
                        break;
                    }
                }
            }

            // 氏名行を探す
            const cellOriginal = (row[j] || '').toString().trim();
            if (cellOriginal === '氏名' || cellOriginal === '社員名' || cellOriginal === '名前') {
                nameRowIndex = i;
            }
        }
    }

    console.log('Found rows - NO:', noRowIndex, 'Name:', nameRowIndex, 'DataColStart:', dataColStartIndex);

    if (noRowIndex === -1) {
        console.error('NO row not found');
        return null;
    }

    // 項目名と対応する行番号のマッピングを作成（最初の数列を検索）
    const rowMapping = {};
    for (let i = 0; i < jsonData.length; i++) {
        const row = jsonData[i];
        if (!row) continue;

        // 最初の数列（A〜E列あたり）を検索
        for (let j = 0; j < Math.min(5, row.length); j++) {
            if (row[j]) {
                const itemName = row[j].toString().trim();
                if (itemName && !rowMapping[itemName]) {
                    rowMapping[itemName] = i;
                }
            }
        }
    }

    console.log('Row mapping:', rowMapping);

    // 社員番号と氏名を取得
    const noRow = jsonData[noRowIndex] || [];
    const nameRow = jsonData[nameRowIndex] || [];

    // 各項目の行インデックスを取得する関数
    function findRowIndex(keywords) {
        for (const keyword of keywords) {
            if (rowMapping[keyword] !== undefined) {
                return rowMapping[keyword];
            }
        }
        return -1;
    }

    // 行インデックス
    const rows = {
        no: noRowIndex,
        name: nameRowIndex,
        basicSalary: findRowIndex(['基本給']),
        taxableTotal: findRowIndex(['課税計']),
        nonTaxableTotal: findRowIndex(['非課税計']),
        grossTotal: findRowIndex(['総支給額']),
        commuteTaxable: findRowIndex(['通勤課税']),
        commuteNonTaxable: findRowIndex(['通勤非課税']),
        healthInsurance: findRowIndex(['健康保険']),
        careInsurance: findRowIndex(['介護保険']),
        pension: findRowIndex(['厚生年金']),
        employmentInsurance: findRowIndex(['雇用保険']),
        incomeTax: findRowIndex(['源泉所得税']),
        residentTax: findRowIndex(['住民税']),
        definedContribution: findRowIndex(['確定拠出', '確定拠出年金', '確定拠出出']),
        advancePayment: findRowIndex(['立替金']),
        rent: findRowIndex(['家賃']),
        yearEndAdjustment: findRowIndex(['年末調整', '年調']),
        deductionTotal: findRowIndex(['控除合計']),
        netPayment: findRowIndex(['差引支給額']),
        bankTransfer1: findRowIndex(['銀行振込1']),
        bankTransfer2: findRowIndex(['銀行振込2']),
        cashPayment: findRowIndex(['現金支給額'])
    };

    console.log('Found row indices:', rows);

    // 合計列のインデックスを探す
    let totalColIndex = -1;
    for (let j = 0; j < noRow.length; j++) {
        const cell = (noRow[j] || '').toString();
        if (cell === '合計' || cell.includes('合計')) {
            totalColIndex = j;
            break;
        }
    }
    // 合計列がなければ、名前行から探す
    if (totalColIndex === -1 && nameRow) {
        for (let j = 0; j < nameRow.length; j++) {
            const cell = (nameRow[j] || '').toString();
            if (cell === '合計' || cell.includes('合計')) {
                totalColIndex = j;
                break;
            }
        }
    }

    console.log('Total column index:', totalColIndex);

    // ダミーヘッダー（表示用）
    const headers = noRow;

    // 行からデータを取得する関数
    function getRowValue(rowIndex, colIndex) {
        if (rowIndex === -1 || !jsonData[rowIndex]) return 0;
        return parseFloat(jsonData[rowIndex][colIndex]) || 0;
    }

    // 従業員データを抽出
    const employees = [];
    const totals = {
        basicSalary: 0,
        taxableTotal: 0,
        nonTaxableTotal: 0,
        grossTotal: 0,
        commuteTaxable: 0,
        commuteNonTaxable: 0,
        healthInsurance: 0,
        careInsurance: 0,
        pension: 0,
        employmentInsurance: 0,
        incomeTax: 0,
        residentTax: 0,
        definedContribution: 0,
        advancePayment: 0,
        rent: 0,
        yearEndAdjustment: 0,
        deductionTotal: 0,
        netPayment: 0,
        executiveSalary: 0
    };

    // 各社員列をループ（dataColStartIndexから合計列の前まで）
    const endCol = totalColIndex > 0 ? totalColIndex : noRow.length;

    for (let col = dataColStartIndex; col < endCol; col++) {
        const no = noRow[col];
        const name = nameRow ? nameRow[col] : '';

        if (no && no.toString().match(/^\d+$/)) {
            const empNo = no.toString().padStart(6, '0');
            const empName = name ? name.toString() : '';

            const employee = {
                no: empNo,
                name: empName,
                basicSalary: getRowValue(rows.basicSalary, col),
                taxableTotal: getRowValue(rows.taxableTotal, col),
                advancePayment: getRowValue(rows.advancePayment, col),
                rent: getRowValue(rows.rent, col)
            };

            employees.push(employee);

            // 役員かどうか判定
            if (empNo === EXECUTIVE_EMPLOYEE_NO) {
                totals.executiveSalary += employee.basicSalary;
            }
        }
    }

    // 合計列がある場合はそこから取得、なければ集計
    if (totalColIndex > 0) {
        totals.basicSalary = getRowValue(rows.basicSalary, totalColIndex);
        totals.taxableTotal = getRowValue(rows.taxableTotal, totalColIndex);
        totals.nonTaxableTotal = getRowValue(rows.nonTaxableTotal, totalColIndex);
        totals.grossTotal = getRowValue(rows.grossTotal, totalColIndex);
        totals.commuteTaxable = getRowValue(rows.commuteTaxable, totalColIndex);
        totals.commuteNonTaxable = getRowValue(rows.commuteNonTaxable, totalColIndex);
        totals.healthInsurance = getRowValue(rows.healthInsurance, totalColIndex);
        totals.careInsurance = getRowValue(rows.careInsurance, totalColIndex);
        totals.pension = getRowValue(rows.pension, totalColIndex);
        totals.employmentInsurance = getRowValue(rows.employmentInsurance, totalColIndex);
        totals.incomeTax = getRowValue(rows.incomeTax, totalColIndex);
        totals.residentTax = getRowValue(rows.residentTax, totalColIndex);
        totals.definedContribution = getRowValue(rows.definedContribution, totalColIndex);
        totals.advancePayment = getRowValue(rows.advancePayment, totalColIndex);
        totals.rent = getRowValue(rows.rent, totalColIndex);
        totals.yearEndAdjustment = getRowValue(rows.yearEndAdjustment, totalColIndex);
        totals.deductionTotal = getRowValue(rows.deductionTotal, totalColIndex);
        totals.netPayment = getRowValue(rows.netPayment, totalColIndex);
    } else {
        // 各従業員を集計
        employees.forEach(emp => {
            totals.basicSalary += emp.basicSalary;
            totals.taxableTotal += emp.taxableTotal;
            totals.advancePayment += emp.advancePayment;
            totals.rent += emp.rent;
        });
    }

    console.log('Employees:', employees);
    console.log('Totals:', totals);

    return {
        headers,
        rows,
        employees,
        totals,
        rawData: jsonData
    };
}

// 仕訳データを生成
function generateJournalEntries(data) {
    journalEntries = [];
    const date = document.getElementById('journalDate').value;
    const totals = data.totals;

    let rowNo = 1;

    // ========================================
    // 借方（費用）
    // ========================================

    // 借方1: 役員報酬
    if (totals.executiveSalary > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '役員報酬',
            debitSubAccount: '',
            debitTaxClass: '対象外仕入',
            debitAmount: totals.executiveSalary,
            creditAccount: '',
            creditSubAccount: '',
            creditTaxClass: '',
            creditAmount: 0,
            description: '役員報酬'
        });
    }

    // 借方2: 給料賃金（課税計 - 役員報酬）
    const salaryAmount = totals.taxableTotal - totals.executiveSalary;
    if (salaryAmount > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '給料賃金',
            debitSubAccount: '',
            debitTaxClass: '対象外仕入',
            debitAmount: salaryAmount,
            creditAccount: '',
            creditSubAccount: '',
            creditTaxClass: '',
            creditAmount: 0,
            description: '従業員給与'
        });
    }

    // 借方3: 旅費交通費（通勤手当 - 課税分）
    if (totals.commuteTaxable > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '旅費交通費',
            debitSubAccount: '通勤手当',
            debitTaxClass: '課税仕入 10%',
            debitAmount: totals.commuteTaxable,
            creditAccount: '',
            creditSubAccount: '',
            creditTaxClass: '',
            creditAmount: 0,
            description: '通勤交通費（課税）'
        });
    }

    // 借方4: 旅費交通費（通勤手当 - 非課税分）
    if (totals.commuteNonTaxable > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '旅費交通費',
            debitSubAccount: '通勤手当',
            debitTaxClass: '対象外仕入',
            debitAmount: totals.commuteNonTaxable,
            creditAccount: '',
            creditSubAccount: '',
            creditTaxClass: '',
            creditAmount: 0,
            description: '通勤交通費（非課税）'
        });
    }

    // ========================================
    // 貸方（控除・負債）
    // ========================================

    // 貸方1: 預り金（社会保険料）
    const socialInsurance = totals.healthInsurance + totals.careInsurance + totals.pension;
    if (socialInsurance > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '',
            debitSubAccount: '',
            debitTaxClass: '',
            debitAmount: 0,
            creditAccount: '預り金',
            creditSubAccount: '社会保険料',
            creditTaxClass: '対象外',
            creditAmount: socialInsurance,
            description: '健康保険料・介護保険料・厚生年金'
        });
    }

    // 貸方2: 法定福利費（雇用保険）
    if (totals.employmentInsurance > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '',
            debitSubAccount: '',
            debitTaxClass: '',
            debitAmount: 0,
            creditAccount: '法定福利費',
            creditSubAccount: '',
            creditTaxClass: '対象外仕入',
            creditAmount: totals.employmentInsurance,
            description: '雇用保険料'
        });
    }

    // 貸方3: 預り金（源泉所得税）
    if (totals.incomeTax > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '',
            debitSubAccount: '',
            debitTaxClass: '',
            debitAmount: 0,
            creditAccount: '預り金',
            creditSubAccount: '源泉所得税_給与',
            creditTaxClass: '対象外',
            creditAmount: totals.incomeTax,
            description: '源泉所得税'
        });
    }

    // 貸方4: 預り金（住民税）
    if (totals.residentTax > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '',
            debitSubAccount: '',
            debitTaxClass: '',
            debitAmount: 0,
            creditAccount: '預り金',
            creditSubAccount: '住民税',
            creditTaxClass: '対象外',
            creditAmount: totals.residentTax,
            description: '住民税'
        });
    }

    // 貸方5: 預り金（確定拠出年金）
    if (totals.definedContribution > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '',
            debitSubAccount: '',
            debitTaxClass: '',
            debitAmount: 0,
            creditAccount: '預り金',
            creditSubAccount: '確定拠出年金',
            creditTaxClass: '対象外',
            creditAmount: totals.definedContribution,
            description: '確定拠出年金'
        });
    }

    // 年末調整（チェックボックスがONの場合のみ）
    const yearEndAdjustmentEnabled = document.getElementById('yearEndAdjustment').checked;
    if (yearEndAdjustmentEnabled && totals.yearEndAdjustment !== 0) {
        // 年末調整がマイナス（還付）の場合: 借方に預り金/源泉所得税_給与
        // 年末調整がプラス（徴収）の場合: 貸方に預り金/源泉所得税_給与
        if (totals.yearEndAdjustment < 0) {
            journalEntries.push({
                no: rowNo++,
                date: date,
                debitAccount: '預り金',
                debitSubAccount: '源泉所得税_給与',
                debitTaxClass: '対象外',
                debitAmount: Math.abs(totals.yearEndAdjustment),
                creditAccount: '',
                creditSubAccount: '',
                creditTaxClass: '',
                creditAmount: 0,
                description: '年末調整還付金'
            });
        } else {
            journalEntries.push({
                no: rowNo++,
                date: date,
                debitAccount: '',
                debitSubAccount: '',
                debitTaxClass: '',
                debitAmount: 0,
                creditAccount: '預り金',
                creditSubAccount: '源泉所得税_給与',
                creditTaxClass: '対象外',
                creditAmount: totals.yearEndAdjustment,
                description: '年末調整徴収金'
            });
        }
    }

    // 立替金処理 - 各従業員ごと
    // プラス: 従業員が会社に返済 → 貸方（未払金）
    // マイナス: 会社が従業員に支払う → 借方（未払金）
    // 末吉さんの場合は駐車場代15,000円を差し引く
    const PARKING_FEE = 15000; // 末吉さんの駐車場代

    data.employees.forEach(emp => {
        let advanceAmount = emp.advancePayment;

        // 末吉さんの場合は駐車場代を加算（立替金に駐車場代が含まれているため）
        if (emp.name && emp.name.includes('末吉')) {
            advanceAmount += PARKING_FEE;
            console.log('末吉さん検出:', emp.name, '元の立替金:', emp.advancePayment, '→ 調整後:', advanceAmount);
        }

        if (advanceAmount > 0) {
            // 貸方: 従業員から控除
            journalEntries.push({
                no: rowNo++,
                date: date,
                debitAccount: '',
                debitSubAccount: '',
                debitTaxClass: '',
                debitAmount: 0,
                creditAccount: '未払金',
                creditSubAccount: '従業員立替_' + emp.name,
                creditTaxClass: '対象外',
                creditAmount: advanceAmount,
                description: '立替金返済'
            });
        } else if (advanceAmount < 0) {
            // 借方: 会社が従業員に支払う（立替金のマイナス＝追加支給）
            journalEntries.push({
                no: rowNo++,
                date: date,
                debitAccount: '未払金',
                debitSubAccount: '従業員立替_' + emp.name,
                debitTaxClass: '対象外仕入',
                debitAmount: Math.abs(advanceAmount),
                creditAccount: '',
                creditSubAccount: '',
                creditTaxClass: '',
                creditAmount: 0,
                description: '立替金精算'
            });
        }
    });

    // 未払費用（給与）- 差引支給額
    if (totals.netPayment > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '',
            debitSubAccount: '',
            debitTaxClass: '',
            debitAmount: 0,
            creditAccount: '未払費用',
            creditSubAccount: '給与',
            creditTaxClass: '対象外',
            creditAmount: totals.netPayment,
            description: '支給額'
        });
    }

    // 雑収入（家賃）- 各従業員ごと
    data.employees.forEach(emp => {
        if (emp.rent > 0) {
            journalEntries.push({
                no: rowNo++,
                date: date,
                debitAccount: '',
                debitSubAccount: '',
                debitTaxClass: '',
                debitAmount: 0,
                creditAccount: '雑収入',
                creditSubAccount: '',
                creditTaxClass: '非課税売上',
                creditAmount: emp.rent,
                description: emp.name + '家賃'
            });
        }
    });

    // 駐車場（末吉さんの立替金から差引済み）
    // 借方: 旅費交通費/駐車場 15,000円
    // 貸方: 0円（立替金から差引済みなので）
    journalEntries.push({
        no: rowNo++,
        date: date,
        debitAccount: '旅費交通費',
        debitSubAccount: '駐車場',
        debitTaxClass: '課税仕入 10%',
        debitAmount: 15000,
        creditAccount: '',
        creditSubAccount: '',
        creditTaxClass: '',
        creditAmount: 0,
        description: '駐車場代_末吉'
    });

    // 会社負担社会保険料（社保×1.0233）
    // 借方: 法定福利費 / 貸方: 未払費用/社会保険料
    const companySocialInsurance = Math.round(socialInsurance * 1.0233);
    if (companySocialInsurance > 0) {
        journalEntries.push({
            no: rowNo++,
            date: date,
            debitAccount: '法定福利費',
            debitSubAccount: '',
            debitTaxClass: '対象外仕入',
            debitAmount: companySocialInsurance,
            creditAccount: '未払費用',
            creditSubAccount: '社会保険料',
            creditTaxClass: '対象外',
            creditAmount: companySocialInsurance,
            description: '社会保険料概算（社保+2.33%）'
        });
    }

    // ========================================
    // デバッグ: 貸借バランスチェック
    // ========================================
    const totalDebit = journalEntries.reduce((sum, e) => sum + e.debitAmount, 0);
    const totalCredit = journalEntries.reduce((sum, e) => sum + e.creditAmount, 0);
    console.log('=== Journal Balance Check ===');
    console.log('借方合計:', totalDebit);
    console.log('貸方合計:', totalCredit);
    console.log('差額:', totalDebit - totalCredit);
    console.log('');
    console.log('借方内訳:');
    console.log('  役員報酬:', totals.executiveSalary);
    console.log('  給料賃金:', salaryAmount);
    console.log('  通勤課税:', totals.commuteTaxable);
    console.log('  通勤非課税:', totals.commuteNonTaxable);
    console.log('貸方内訳:');
    console.log('  社会保険料:', socialInsurance);
    console.log('  雇用保険:', totals.employmentInsurance);
    console.log('  源泉所得税:', totals.incomeTax);
    console.log('  住民税:', totals.residentTax);
    console.log('  確定拠出:', totals.definedContribution);
    console.log('  立替金:', totals.advancePayment);
    console.log('  家賃:', totals.rent);
    console.log('  差引支給額:', totals.netPayment);
}

// 仕訳データを表示
function displayJournalEntries() {
    const tbody = document.getElementById('journalBody');

    let html = journalEntries.map((entry, index) => `
        <tr>
            <td>${entry.no}</td>
            <td><input type="date" value="${entry.date}" data-index="${index}" data-field="date"></td>
            <td><input type="text" value="${entry.debitAccount}" data-index="${index}" data-field="debitAccount"></td>
            <td><input type="text" value="${entry.debitSubAccount}" data-index="${index}" data-field="debitSubAccount"></td>
            <td><input type="text" value="${entry.debitTaxClass}" data-index="${index}" data-field="debitTaxClass"></td>
            <td class="amount"><input type="text" class="amount-input" value="${entry.debitAmount > 0 ? entry.debitAmount.toLocaleString() : ''}" data-index="${index}" data-field="debitAmount"></td>
            <td><input type="text" value="${entry.creditAccount}" data-index="${index}" data-field="creditAccount"></td>
            <td><input type="text" value="${entry.creditSubAccount}" data-index="${index}" data-field="creditSubAccount"></td>
            <td><input type="text" value="${entry.creditTaxClass}" data-index="${index}" data-field="creditTaxClass"></td>
            <td class="amount"><input type="text" class="amount-input" value="${entry.creditAmount > 0 ? entry.creditAmount.toLocaleString() : ''}" data-index="${index}" data-field="creditAmount"></td>
            <td><input type="text" value="${entry.description}" data-index="${index}" data-field="description"></td>
        </tr>
    `).join('');

    // 集計行
    const totalDebit = journalEntries.reduce((sum, e) => sum + e.debitAmount, 0);
    const totalCredit = journalEntries.reduce((sum, e) => sum + e.creditAmount, 0);

    html += `
        <tr class="summary-row">
            <td colspan="5" style="text-align: right; font-weight: bold;">借方合計:</td>
            <td class="amount" style="font-weight: bold;">${totalDebit.toLocaleString()}</td>
            <td colspan="3" style="text-align: right; font-weight: bold;">貸方合計:</td>
            <td class="amount" style="font-weight: bold;">${totalCredit.toLocaleString()}</td>
            <td style="color: ${totalDebit === totalCredit ? '#10b981' : '#ef4444'}; font-weight: bold;">
                ${totalDebit === totalCredit ? '貸借一致' : '差額: ' + Math.abs(totalDebit - totalCredit).toLocaleString()}
            </td>
        </tr>
    `;

    tbody.innerHTML = html;

    // 入力変更イベント
    tbody.querySelectorAll('input').forEach(input => {
        input.addEventListener('change', (e) => {
            const index = parseInt(e.target.dataset.index);
            const field = e.target.dataset.field;
            let value = e.target.value;

            if (field === 'debitAmount' || field === 'creditAmount') {
                value = parseInt(value.replace(/,/g, '')) || 0;
            }

            journalEntries[index][field] = value;
            displayJournalEntries(); // 再描画して合計更新
        });
    });
}

// リセット
document.getElementById('resetBtn').addEventListener('click', () => {
    if (confirm('入力内容をリセットしますか？')) {
        sourceData = null;
        journalEntries = [];
        sourcePreview.classList.remove('active');
        journalPreview.classList.remove('active');
        fileInput.value = '';
    }
});

// CSVダウンロード
document.getElementById('downloadBtn').addEventListener('click', () => {
    if (journalEntries.length === 0) {
        alert('仕訳データがありません');
        return;
    }

    // MF会計フォーマットのCSV生成
    const headers = [
        '取引No', '取引日', '借方勘定科目', '借方補助科目', '借方部門', '借方取引先',
        '借方税区分', '借方インボイス', '借方金額(円)', '借方税額',
        '貸方勘定科目', '貸方補助科目', '貸方部門', '貸方取引先',
        '貸方税区分', '貸方インボイス', '貸方金額(円)', '貸方税額',
        '摘要', 'タグ'
    ];

    const rows = journalEntries.map(entry => [
        '', // 取引No
        entry.date.replace(/-/g, '/'), // 取引日
        entry.debitAccount, // 借方勘定科目
        entry.debitSubAccount, // 借方補助科目
        '', // 借方部門
        '', // 借方取引先
        entry.debitTaxClass, // 借方税区分
        '', // 借方インボイス
        entry.debitAmount || '', // 借方金額
        '', // 借方税額
        entry.creditAccount, // 貸方勘定科目
        entry.creditSubAccount, // 貸方補助科目
        '', // 貸方部門
        '', // 貸方取引先
        entry.creditTaxClass, // 貸方税区分
        '', // 貸方インボイス
        entry.creditAmount || '', // 貸方金額
        '', // 貸方税額
        entry.description, // 摘要
        '' // タグ
    ]);

    // BOM付きUTF-8でCSV生成
    const bom = '\uFEFF';
    const csvContent = bom + [headers, ...rows].map(row =>
        row.map(cell => `"${(cell || '').toString().replace(/"/g, '""')}"`).join(',')
    ).join('\r\n');

    // ダウンロード
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `給与仕訳_${document.getElementById('journalDate').value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
});

// 日付変更時に仕訳を再生成
document.getElementById('journalDate').addEventListener('change', () => {
    if (sourceData) {
        generateJournalEntries(sourceData);
        displayJournalEntries();
    }
});

// 年末調整チェックボックス変更時に仕訳を再生成
document.getElementById('yearEndAdjustment').addEventListener('change', () => {
    if (sourceData) {
        generateJournalEntries(sourceData);
        displayJournalEntries();
    }
});

// ========================================
// 履歴管理機能
// ========================================

const HISTORY_STORAGE_KEY = 'payroll_journal_history';

// 履歴をロード
function loadHistory() {
    const historyJson = localStorage.getItem(HISTORY_STORAGE_KEY);
    return historyJson ? JSON.parse(historyJson) : [];
}

// 履歴を保存
function saveHistoryToStorage(history) {
    localStorage.setItem(HISTORY_STORAGE_KEY, JSON.stringify(history));
}

// 履歴を表示
function displayHistory() {
    const historyList = document.getElementById('historyList');
    const history = loadHistory();

    if (history.length === 0) {
        historyList.innerHTML = '<p class="no-history">まだ処理履歴がありません</p>';
        return;
    }

    // 新しい順にソート
    history.sort((a, b) => new Date(b.savedAt) - new Date(a.savedAt));

    historyList.innerHTML = history.map((item, index) => {
        const savedDate = new Date(item.savedAt);
        const formattedDate = savedDate.toLocaleString('ja-JP', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <div class="history-item" data-index="${index}">
                <div class="history-item-info">
                    <div class="history-item-month">${item.payrollMonth}分 給与仕訳</div>
                    <div class="history-item-date">保存日時: ${formattedDate}</div>
                </div>
                <div class="history-item-amount">
                    <div class="debit">借方: ${item.totalDebit.toLocaleString()}円</div>
                    <div class="balance">${item.isBalanced ? '貸借一致' : '差額あり'}</div>
                </div>
                <div class="history-item-actions">
                    <button type="button" class="btn-load" onclick="loadHistoryItem('${item.id}')">読込</button>
                    <button type="button" class="btn-delete" onclick="deleteHistoryItem('${item.id}')">削除</button>
                </div>
            </div>
        `;
    }).join('');
}

// 履歴に保存
document.getElementById('saveHistoryBtn').addEventListener('click', () => {
    if (journalEntries.length === 0) {
        alert('保存する仕訳データがありません');
        return;
    }

    const payrollMonth = document.getElementById('payrollMonth').value;
    const totalDebit = journalEntries.reduce((sum, e) => sum + e.debitAmount, 0);
    const totalCredit = journalEntries.reduce((sum, e) => sum + e.creditAmount, 0);

    const historyItem = {
        id: Date.now().toString(),
        payrollMonth: payrollMonth,
        journalDate: document.getElementById('journalDate').value,
        yearEndAdjustment: document.getElementById('yearEndAdjustment').checked,
        journalEntries: JSON.parse(JSON.stringify(journalEntries)),
        totals: sourceData ? sourceData.totals : null,
        totalDebit: totalDebit,
        totalCredit: totalCredit,
        isBalanced: totalDebit === totalCredit,
        savedAt: new Date().toISOString()
    };

    const history = loadHistory();

    // 同じ月のデータがあるか確認
    const existingIndex = history.findIndex(h => h.payrollMonth === payrollMonth);
    if (existingIndex >= 0) {
        if (!confirm(`${payrollMonth}分のデータは既に保存されています。上書きしますか？`)) {
            return;
        }
        history[existingIndex] = historyItem;
    } else {
        history.push(historyItem);
    }

    saveHistoryToStorage(history);
    displayHistory();
    alert(`${payrollMonth}分の仕訳データを保存しました`);
});

// 履歴から読み込み
function loadHistoryItem(id) {
    const history = loadHistory();
    const item = history.find(h => h.id === id);

    if (!item) {
        alert('履歴データが見つかりません');
        return;
    }

    if (!confirm(`${item.payrollMonth}分のデータを読み込みますか？\n現在の編集内容は失われます。`)) {
        return;
    }

    // データを復元
    document.getElementById('payrollMonth').value = item.payrollMonth;
    document.getElementById('journalDate').value = item.journalDate;
    document.getElementById('yearEndAdjustment').checked = item.yearEndAdjustment;

    journalEntries = item.journalEntries;
    displayJournalEntries();

    // プレビュー表示
    journalPreview.classList.add('active');

    alert(`${item.payrollMonth}分のデータを読み込みました`);
}

// 履歴を削除
function deleteHistoryItem(id) {
    const history = loadHistory();
    const item = history.find(h => h.id === id);

    if (!item) {
        alert('履歴データが見つかりません');
        return;
    }

    if (!confirm(`${item.payrollMonth}分のデータを削除しますか？`)) {
        return;
    }

    const newHistory = history.filter(h => h.id !== id);
    saveHistoryToStorage(newHistory);
    displayHistory();
}

// 履歴をクリア
document.getElementById('clearHistoryBtn').addEventListener('click', () => {
    const history = loadHistory();
    if (history.length === 0) {
        alert('削除する履歴がありません');
        return;
    }

    if (!confirm('全ての処理履歴を削除しますか？\nこの操作は取り消せません。')) {
        return;
    }

    localStorage.removeItem(HISTORY_STORAGE_KEY);
    displayHistory();
    alert('履歴を削除しました');
});

// ページ読み込み時に履歴を表示
document.addEventListener('DOMContentLoaded', () => {
    displayHistory();
});
</script>

<?php require_once '../functions/footer.php'; ?>
