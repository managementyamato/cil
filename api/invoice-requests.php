<?php
/**
 * 請求書作成依頼 API
 * - GET: list / get
 * - POST: create / update / delete / send_to_mf
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/mf-api.php';
require_once __DIR__ . '/google-sheets.php';

// 請求書作成依頼フォームのスプレッドシートID
define('IR_SHEET_ID', '1seVAXU1TCo3PIJjRckdlp58qoEcv0vYLzuOIqDfUBYI');
// 取得対象シート名（フォーム回答シート・通常は「フォームの回答 1」）
define('IR_SHEET_RANGE', 'A1:DZ');

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['GET', 'POST'],
]);

// 管理部のみアクセス可
if (!isAdmin()) errorResponse('権限がありません（管理部のみ）', 403);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$now = date('Y-m-d H:i:s');
$currentUser = $_SESSION['user_email'] ?? '';
$userName = $_SESSION['user_name'] ?? $currentUser;

// ★ このAPIはMF API・Google Sheets API を多数呼び出し30〜90秒かかるアクションがある。
//   PHP のデフォルト挙動ではセッションファイルを排他ロックし続けるため、
//   同じユーザーの別タブ操作・ログインが全て待たされる (= 「数十秒〜数分ログインできない」現象)。
//   読み取り済みなのでここでロックを解放する。以降このリクエストでは $_SESSION を変更しない。
if (function_exists('session_write_close')) {
    session_write_close();
}

$data = getData();
if (!isset($data['invoice_requests'])) $data['invoice_requests'] = [];

// ─── GET: 一覧・詳細 ───
if ($method === 'GET') {
    if ($action === 'list') {
        $items = array_values(array_filter($data['invoice_requests'], fn($r) => empty($r['deleted_at'])));
        // 申請日時 (source_timestamp = フォーム送信日時) 降順。なければ created_at にフォールバック。
        $appTime = function(array $r): string {
            $ts = trim((string)($r['source_timestamp'] ?? ''));
            if ($ts !== '') return str_replace('/', '-', $ts);
            return (string)($r['created_at'] ?? '');
        };
        usort($items, fn($a, $b) => strcmp($appTime($b), $appTime($a)));
        successResponse(['items' => $items]);
    }
    if ($action === 'get') {
        $id = $_GET['id'] ?? '';
        foreach ($data['invoice_requests'] as $r) {
            if (($r['id'] ?? '') === $id && empty($r['deleted_at'])) {
                successResponse(['item' => $r]);
            }
        }
        errorResponse('見つかりません', 404);
    }
    if ($action === 'verify_mf_partner') {
        // 引数: id（依頼ID）または partner_id（直接指定）
        $partnerId = trim($_GET['partner_id'] ?? '');
        $id = $_GET['id'] ?? '';
        $partnerName = '';

        if (empty($partnerId) && !empty($id)) {
            foreach ($data['invoice_requests'] as $r) {
                if (($r['id'] ?? '') === $id) {
                    $partnerId = $r['mf_partner_id'] ?? '';
                    $partnerName = $r['partner_name'] ?? '';
                    break;
                }
            }
        }
        if (empty($partnerId)) errorResponse('partner_id または id（mf_partner_id付き依頼）が必要です', 400);

        try {
            $mf = new MFApiClient();
            $result = ['mf_partner_id' => $partnerId, 'partner_name' => $partnerName];

            // 1. 取引先取得
            try {
                $partner = $mf->request('GET', '/partners/' . urlencode($partnerId));
                $result['partner_check'] = '取引先取得成功';
                $result['partner_data'] = $partner;
            } catch (Exception $e) {
                $result['partner_check'] = '取引先取得失敗: ' . $e->getMessage();
            }

            // 2. 部門一覧
            try {
                $departments = $mf->getPartnerDepartments($partnerId);
                $result['departments_count'] = count($departments);
                $result['departments'] = array_map(fn($d) => [
                    'id' => $d['id'] ?? '',
                    'name' => $d['name'] ?? '',
                    'is_default' => $d['is_default'] ?? false,
                ], $departments);
            } catch (Exception $e) {
                $result['departments_check'] = '部門取得失敗: ' . $e->getMessage();
            }

            successResponse($result);
        } catch (Exception $e) {
            errorResponse('検証失敗: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'test_update_invoice') {
        // 既存のMF請求書をPATCHで更新できるかテスト
        // ?invoice_id=... を指定（既存のmf_invoicesから）、または最新1件を自動使用
        $invoiceId = trim($_GET['invoice_id'] ?? '');
        if (empty($invoiceId)) {
            $mfInvoices = $data['mf_invoices'] ?? [];
            // memo がからのものを探す
            foreach ($mfInvoices as $inv) {
                if (!empty($inv['id']) && empty($inv['deleted_at'])) {
                    $invoiceId = $inv['id'];
                    break;
                }
            }
        }
        if (empty($invoiceId)) errorResponse('テスト対象のMF請求書IDが見つかりません', 400);

        $testMemo = '【APIテスト】 ' . date('Y-m-d H:i:s');

        try {
            $mf = new MFApiClient();
            $result = [];
            $result['target_invoice_id'] = $invoiceId;
            $result['test_memo'] = $testMemo;

            // まず GET で取得できるか
            try {
                $existing = $mf->getInvoiceDetail($invoiceId);
                $result['get_check'] = 'OK';
                $result['existing_memo'] = $existing['data']['memo'] ?? $existing['memo'] ?? '(取得不能)';
            } catch (Exception $e) {
                $result['get_check'] = '失敗: ' . $e->getMessage();
            }

            // 複数の更新メソッド・URL を試す
            $attempts = [
                ['method' => 'PUT',   'endpoint' => '/billings/' . $invoiceId, 'body' => ['memo' => $testMemo]],
                ['method' => 'PATCH', 'endpoint' => '/billings/' . $invoiceId, 'body' => ['memo' => $testMemo]],
                ['method' => 'POST',  'endpoint' => '/billings/' . $invoiceId, 'body' => ['memo' => $testMemo]],
                ['method' => 'PUT',   'endpoint' => '/billings/' . $invoiceId, 'body' => ['billing' => ['memo' => $testMemo]]],
            ];
            foreach ($attempts as $i => $a) {
                try {
                    $r = $mf->request($a['method'], $a['endpoint'], $a['body']);
                    $result['update_attempts'][] = [
                        'pattern' => $a['method'] . ' ' . $a['endpoint'] . ' ' . json_encode($a['body']),
                        'status' => 'OK',
                        'response_keys' => is_array($r) ? array_keys($r) : 'non-array',
                    ];
                    $result['success_pattern'] = $a['method'] . ' ' . $a['endpoint'];
                    break;
                } catch (Exception $e) {
                    $result['update_attempts'][] = [
                        'pattern' => $a['method'] . ' ' . $a['endpoint'] . ' ' . json_encode($a['body']),
                        'status' => '失敗',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            successResponse($result);
        } catch (Exception $e) {
            errorResponse('テスト失敗: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'clone_test_invoice') {
        // 既存請求書の構造を取得し、その構造を流用してPOSTを試す
        $invoiceId = trim($_GET['invoice_id'] ?? '');
        if (empty($invoiceId)) {
            $mfInvoices = $data['mf_invoices'] ?? [];
            foreach ($mfInvoices as $inv) {
                if (!empty($inv['id']) && empty($inv['deleted_at'])) {
                    $invoiceId = $inv['id'];
                    break;
                }
            }
        }
        if (empty($invoiceId)) errorResponse('既存MF請求書IDが見つかりません', 400);

        try {
            $mf = new MFApiClient();
            $result = ['source_invoice_id' => $invoiceId];

            // 1. 既存請求書を完全取得
            $existing = $mf->getInvoiceDetail($invoiceId);
            $existingData = $existing['data'] ?? $existing;
            $result['existing_keys'] = is_array($existingData) ? array_keys($existingData) : 'non-array';
            $result['existing_full'] = $existingData;

            // 2. 自動生成フィールドを除いてクローンペイロードを構築
            $autoFields = [
                'id','pdf_url','public_url','operator_name','sent_at','billing_number',
                'created_at','updated_at','deal_status','email_status','posting_status',
                'payment_status','status','order_status','document_number','total_price',
                'excise_price','subtotal_price','sales_tax','tags',
            ];
            $clonePayload = [];
            if (is_array($existingData)) {
                foreach ($existingData as $k => $v) {
                    if (in_array($k, $autoFields, true)) continue;
                    if (is_null($v)) continue;
                    $clonePayload[$k] = $v;
                }
            }
            // 重要フィールドだけ上書き（テスト用に最小変更）
            $clonePayload['title'] = '【クローンPOSTテスト】 ' . date('H:i:s');
            $clonePayload['billing_date'] = date('Y-m-d');
            $clonePayload['due_date'] = date('Y-m-d', strtotime('+30 days'));
            $clonePayload['memo'] = 'クローンテスト ' . date('Y-m-d H:i:s');

            // items を簡略化
            if (isset($clonePayload['items']) && is_array($clonePayload['items'])) {
                $newItems = [];
                foreach ($clonePayload['items'] as $it) {
                    $newItems[] = [
                        'name' => 'テスト品目',
                        'detail' => 'クローン',
                        'quantity' => 1,
                        'unit' => '個',
                        'unit_price' => 1000,
                        'excise' => $it['excise'] ?? 'ten_percent',
                    ];
                    break; // 1個だけ
                }
                $clonePayload['items'] = $newItems ?: [
                    ['name' => 'テスト','detail' => '','quantity' => 1,'unit' => '個','unit_price' => 1000,'excise' => 'ten_percent']
                ];
            }

            $result['clone_payload_keys'] = array_keys($clonePayload);
            $result['clone_payload'] = $clonePayload;

            // 3. 複数のエンドポイントを試す
            $attempts = [
                ['method' => 'POST', 'endpoint' => '/billings', 'payload' => $clonePayload],
                ['method' => 'POST', 'endpoint' => '/billings', 'payload' => ['billing' => $clonePayload]],
                ['method' => 'POST', 'endpoint' => '/billing',  'payload' => $clonePayload],  // 単数形
                ['method' => 'POST', 'endpoint' => '/invoices', 'payload' => $clonePayload],  // 旧名
                ['method' => 'POST', 'endpoint' => '/billings.json', 'payload' => $clonePayload],
            ];
            foreach ($attempts as $a) {
                try {
                    $r = $mf->request($a['method'], $a['endpoint'], $a['payload']);
                    $result['attempts'][] = [
                        'pattern' => $a['method'] . ' ' . $a['endpoint'] . (isset($a['payload']['billing']) ? ' (wrapped)' : ''),
                        'status' => 'OK',
                        'response_id' => $r['data']['id'] ?? $r['id'] ?? null,
                    ];
                    $result['success'] = true;
                    break;
                } catch (Exception $e) {
                    $result['attempts'][] = [
                        'pattern' => $a['method'] . ' ' . $a['endpoint'] . (isset($a['payload']['billing']) ? ' (wrapped)' : ''),
                        'status' => '失敗',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // 4. cURL を使った POST テスト（file_get_contents の POST が壊れている場合の検証）
            try {
                $r = $mf->requestCurl('POST', '/billings', $clonePayload);
                $result['curl_post_attempt'] = ['status' => 'OK', 'id' => $r['data']['id'] ?? $r['id'] ?? null];
                $result['success_via_curl'] = true;
            } catch (Exception $e) {
                $result['curl_post_attempt'] = ['status' => '失敗', 'error' => $e->getMessage()];
            }

            // 5. cURL でラップ形式 POST
            try {
                $r = $mf->requestCurl('POST', '/billings', ['billing' => $clonePayload]);
                $result['curl_post_wrapped_attempt'] = ['status' => 'OK', 'id' => $r['data']['id'] ?? $r['id'] ?? null];
            } catch (Exception $e) {
                $result['curl_post_wrapped_attempt'] = ['status' => '失敗', 'error' => $e->getMessage()];
            }

            // 6. cURL で PUT が動くことの確認（既存IDで memo だけ更新）
            try {
                $r = $mf->requestCurl('PUT', '/billings/' . $invoiceId, ['memo' => '【cURL PUTテスト】 ' . date('H:i:s')]);
                $result['curl_put_attempt'] = ['status' => 'OK'];
            } catch (Exception $e) {
                $result['curl_put_attempt'] = ['status' => '失敗', 'error' => $e->getMessage()];
            }

            // 7. 詳細レスポンスヘッダー取得（POST /billings の404の素の応答を見る）
            try {
                $verbose = $mf->requestCurlVerbose('POST', '/billings', $clonePayload);
                $result['verbose_post'] = $verbose;
            } catch (Exception $e) {
                $result['verbose_post'] = ['error' => $e->getMessage()];
            }

            // 8. 詳細: POST OPTIONS で Allow ヘッダーを取得
            try {
                $options = $mf->requestCurlVerbose('OPTIONS', '/billings', null);
                $result['verbose_options'] = $options;
            } catch (Exception $e) {
                $result['verbose_options'] = ['error' => $e->getMessage()];
            }

            // 9. office_id ベースのエンドポイントを試す
            $officeIdFromExisting = $existingData['office_id'] ?? null;
            if ($officeIdFromExisting) {
                $altEndpoints = [
                    '/offices/' . $officeIdFromExisting . '/billings',
                    '/offices/' . $officeIdFromExisting . '/partners/' . ($existingData['partner_id'] ?? '') . '/billings',
                ];
                $result['office_based_attempts'] = [];
                foreach ($altEndpoints as $ep) {
                    try {
                        $r = $mf->requestCurl('POST', $ep, $clonePayload);
                        $result['office_based_attempts'][] = ['endpoint' => $ep, 'status' => 'OK', 'id' => $r['data']['id'] ?? $r['id'] ?? null];
                    } catch (Exception $e) {
                        $result['office_based_attempts'][] = ['endpoint' => $ep, 'status' => '失敗', 'error' => $e->getMessage()];
                    }
                }
            }

            successResponse($result);
        } catch (Exception $e) {
            errorResponse('クローンテスト失敗: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'list_mf_partners') {
        // MF側の全取引先一覧を取得（ID 確認用）
        try {
            $mf = new MFApiClient();
            $partners = $mf->getPartners();
            $simple = array_map(fn($p) => [
                'id' => $p['id'] ?? '',
                'name' => $p['name'] ?? '',
            ], $partners);
            successResponse(['count' => count($simple), 'partners' => $simple]);
        } catch (Exception $e) {
            errorResponse('取得失敗: ' . $e->getMessage(), 500);
        }
    }
    if ($action === 'debug_headers') {
        try {
            $sheets = new GoogleSheetsClient(IR_SHEET_ID);
            $values = $sheets->getValues(IR_SHEET_RANGE);
            if (empty($values)) errorResponse('シートが空です', 400);
            $headers = $values[0];
            $itemHeaders = [];
            foreach ($headers as $idx => $h) {
                if (preg_match('/品目|単価|日数|数量|単位|消費税|1\s*[-‐−ー－]\s*2/u', (string)$h)) {
                    $itemHeaders[] = ['col' => $idx + 1, 'header' => $h];
                }
            }
            $itemPattern = [];
            foreach ($headers as $idx => $h) {
                if (preg_match('/1[-‐−ー－]2[-‐−ー－](\d+)\s*[:：](.+)/u', (string)$h, $m)) {
                    $itemPattern[] = ['col' => $idx + 1, 'item_idx' => (int)$m[1], 'field' => trim($m[2])];
                }
            }
            $sampleRow = end($values);
            successResponse([
                'total_columns' => count($headers),
                'all_headers' => array_map(fn($i, $h) => ['col' => $i + 1, 'header' => $h], array_keys($headers), $headers),
                'item_related_headers' => $itemHeaders,
                'pattern_matched_headers' => $itemPattern,
                'sample_row' => $sampleRow,
            ]);
        } catch (Exception $e) {
            errorResponse('Sheets取得失敗: ' . $e->getMessage(), 500);
        }
    }
    errorResponse('不正なアクションです', 400);
}

// ─── POST ───
if ($action === 'create') {
    $newId = uniqid('ir_');
    $itemsJson = $_POST['items'] ?? '[]';
    $items = json_decode($itemsJson, true) ?: [];

    $newRequest = [
        'id' => $newId,
        'source' => $_POST['source'] ?? 'manual',
        'source_row_id' => trim($_POST['source_row_id'] ?? ''),
        'source_timestamp' => trim($_POST['source_timestamp'] ?? ''),
        'requester_name' => trim($_POST['requester_name'] ?? ''),
        'attached_file_id' => trim($_POST['attached_file_id'] ?? ''),
        'pj_number' => trim($_POST['pj_number'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'partner_name' => trim($_POST['partner_name'] ?? ''),
        'partner_department' => trim($_POST['partner_department'] ?? ''),
        'mf_partner_id' => trim($_POST['mf_partner_id'] ?? ''),
        'billing_method_1' => trim($_POST['billing_method_1'] ?? ''),
        'billing_method_2' => trim($_POST['billing_method_2'] ?? ''),
        'request_type' => trim($_POST['request_type'] ?? ''),
        'billing_start_date' => trim($_POST['billing_start_date'] ?? ''),
        'payment_due_date' => trim($_POST['payment_due_date'] ?? ''),
        'closing_day' => trim($_POST['closing_day'] ?? ''),
        'rental_period' => trim($_POST['rental_period'] ?? ''),
        'auto_renew' => !empty($_POST['auto_renew']) ? 1 : 0,
        'has_prorated' => !empty($_POST['has_prorated']) ? 1 : 0,
        'items' => $items,
        'notes' => trim($_POST['notes'] ?? ''),
        'special_notes' => trim($_POST['special_notes'] ?? ''),
        'status' => 'pending',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $data['invoice_requests'][] = $newRequest;
    // 同時編集衝突防止: 単一行 UPSERT
    saveEntityRow('invoice_requests', $newRequest);
    successResponse(['item' => $newRequest], '作成しました');
}

if ($action === 'update') {
    $id = $_POST['id'] ?? '';
    $found = false;
    foreach ($data['invoice_requests'] as &$r) {
        if (($r['id'] ?? '') !== $id) continue;
        if (!empty($r['deleted_at'])) errorResponse('削除済みです', 400);

        $itemsJson = $_POST['items'] ?? null;
        if ($itemsJson !== null) {
            $r['items'] = json_decode($itemsJson, true) ?: [];
        }
        // 更新可能フィールド
        $editableFields = [
            'requester_name','attached_file_id','pj_number','subject',
            'partner_name','partner_department','mf_partner_id',
            'billing_method_1','billing_method_2','request_type',
            'billing_start_date','payment_due_date','closing_day','rental_period',
            'notes','special_notes',
        ];
        foreach ($editableFields as $f) {
            if (isset($_POST[$f])) $r[$f] = trim($_POST[$f]);
        }
        if (isset($_POST['auto_renew']))   $r['auto_renew']   = !empty($_POST['auto_renew']) ? 1 : 0;
        if (isset($_POST['has_prorated'])) $r['has_prorated'] = !empty($_POST['has_prorated']) ? 1 : 0;

        $r['updated_at'] = $now;
        $found = true;
        $updated = $r;
        break;
    }
    unset($r);
    if (!$found) errorResponse('見つかりません', 404);
    // 同時編集衝突防止: 単一行 UPSERT
    saveEntityRow('invoice_requests', $updated);
    successResponse(['item' => $updated], '更新しました');
}

if ($action === 'delete') {
    if (!canDelete()) errorResponse('削除権限がありません', 403);
    $id = $_POST['id'] ?? '';
    $found = false;
    $deletedRow = null;
    foreach ($data['invoice_requests'] as &$r) {
        if (($r['id'] ?? '') !== $id) continue;
        $r['deleted_at'] = $now;
        $r['deleted_by'] = $currentUser;
        $found = true;
        $deletedRow = $r;
        break;
    }
    unset($r);
    if (!$found) errorResponse('見つかりません', 404);
    // 同時編集衝突防止: 単一行 UPSERT
    saveEntityRow('invoice_requests', $deletedRow);
    successResponse(['message' => '削除しました']);
}

if ($action === 'send_to_mf') {
    $id = $_POST['id'] ?? '';
    $draft = !empty($_POST['draft']);  // draft=1 ならドラフト

    $req = null;
    $reqIdx = null;
    foreach ($data['invoice_requests'] as $idx => $r) {
        if (($r['id'] ?? '') === $id && empty($r['deleted_at'])) {
            $req = $r;
            $reqIdx = $idx;
            break;
        }
    }
    if (!$req) errorResponse('見つかりません', 404);
    if (($req['status'] ?? '') === 'sent') errorResponse('既にMFに送信済みです', 400);

    // バリデーション
    if (empty($req['mf_partner_id'])) errorResponse('MF取引先IDが未設定です', 400);
    if (empty($req['billing_start_date'])) errorResponse('請求開始日が未設定です', 400);
    // 注: session_write_close() はファイル先頭でグローバルに呼び出し済み (MF API待ちでセッションロックしない)

    // ※ MF未登録時の自動取引先作成機能はまだ開発中（本番未投入）。
    //   検証完了後に再有効化する。コードは PR ブランチ等に温存。

    // items が文字列（JSONデコード忘れ）の場合は復号
    if (is_string($req['items'] ?? null)) {
        $decoded = json_decode($req['items'], true);
        if (is_array($decoded)) $req['items'] = $decoded;
    }

    if (empty($req['items']) || !is_array($req['items'])) {
        $diag = 'items型: ' . gettype($req['items'] ?? null);
        if (is_string($req['items'] ?? null)) $diag .= ' / 値: ' . substr($req['items'], 0, 100);
        if (is_array($req['items'] ?? null)) $diag .= ' / 件数: ' . count($req['items']);
        errorResponse('品目が未設定です（' . $diag . '）', 400);
    }

    // 初回請求書の明細を組み立て（日割り）
    // 注: invoice_template_billings API は unit_price ではなく price を使う
    $billingItems = [];
    foreach ($req['items'] as $it) {
        $name      = trim($it['name'] ?? '');
        $unitPrice = (float)($it['initial_unit_price'] ?? 0);
        $days      = (int)($it['initial_days'] ?? 0);
        if ($name === '' || $unitPrice <= 0 || $days <= 0) continue;
        $excise = $it['tax_type'] ?? '10%対象';
        if (mb_stripos($excise, '軽減') !== false || strpos($excise, '8%') !== false) {
            $exciseCode = 'eight_percent_as_reduced_tax_rate';
        } elseif (mb_stripos($excise, '非課税') !== false) {
            $exciseCode = 'non_taxable';
        } elseif (mb_stripos($excise, '不課税') !== false) {
            $exciseCode = 'untaxable';
        } else {
            $exciseCode = 'ten_percent';
        }

        $billingItems[] = [
            'name'      => $name,
            'detail'    => '日割り（' . $days . '日分）',
            'quantity'  => $days,
            'unit'      => '日',
            'price'     => $unitPrice,  // 新API: unit_price → price
            'excise'    => $exciseCode,
        ];
    }
    if (empty($billingItems)) errorResponse('有効な品目がありません', 400);

    // タグ：PJ番号 + 担当者
    $tags = [];
    if (!empty($req['pj_number']))      $tags[] = $req['pj_number'];
    if (!empty($req['requester_name'])) $tags[] = $req['requester_name'];
    if (!empty($req['request_type']))   $tags[] = $req['request_type'];

    try {
        $mf = new MFApiClient();

        // mf_partner_id から department_id と member_id を解決
        $departmentId = null;
        $memberId = null;
        $deptDiagnostic = '';
        try {
            $departments = $mf->getPartnerDepartments($req['mf_partner_id']);
            $deptDiagnostic = '部門数:' . count($departments);
            foreach ($departments as $dep) {
                if (!empty($dep['is_default'])) {
                    $departmentId = $dep['id'];
                    $memberId = $dep['office_member_id'] ?? null;
                    break;
                }
            }
            if (!$departmentId && !empty($departments)) {
                $departmentId = $departments[0]['id'] ?? null;
                $memberId = $departments[0]['office_member_id'] ?? null;
            }
        } catch (Exception $e) {
            $deptDiagnostic = '部門取得失敗: ' . $e->getMessage();
        }
        if (!$departmentId) {
            throw new Exception("MF取引先ID『" . $req['mf_partner_id'] . "』に対応する部門が見つかりません。" .
                " (" . $deptDiagnostic . ") MF管理画面で取引先IDを確認するか、依頼の『MF取引先ID』を正しいものに更新してください。");
        }

        // office_id を既存請求書から取得（テンプレートとして1件取り出して office_id を借用）
        $officeId = null;
        try {
            $existingMfInvoices = $data['mf_invoices'] ?? [];
            foreach ($existingMfInvoices as $inv) {
                if (!empty($inv['id'])) {
                    $det = $mf->getInvoiceDetail($inv['id']);
                    $officeId = $det['data']['office_id'] ?? $det['office_id'] ?? null;
                    if ($officeId) break;
                }
            }
        } catch (Exception $e) {
            // office_id 取得失敗 → 続行
        }

        // インボイス制度対応の新エンドポイント /invoice_template_billings を使う
        // 旧 /billings POSTは2024年頃に非推奨化され 404 を返す
        // due_date は実在する日付のみ。0000-00-00 や空・無効な場合は billing_date + 30日 を自動セット
        $billingDate = $req['billing_start_date'] ?? '';
        $dueDate = trim($req['payment_due_date'] ?? '');
        $dueDateValid = false;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dueDate, $m)) {
            $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
            $dueDateValid = ($y >= 2020 && checkdate($mo, $d, $y));
        }
        if (!$dueDateValid) {
            $dueDate = ($billingDate && strtotime($billingDate))
                ? date('Y-m-d', strtotime($billingDate . ' +30 days'))
                : date('Y-m-d', strtotime('+30 days'));
        }
        // billing_date も同様にチェック
        $billingDateValid = false;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $billingDate, $m2)) {
            $y2 = (int)$m2[1]; $mo2 = (int)$m2[2]; $d2 = (int)$m2[3];
            $billingDateValid = ($y2 >= 2020 && checkdate($mo2, $d2, $y2));
        }
        if (!$billingDateValid) {
            $billingDate = date('Y-m-d');  // 今日
        }

        $payload = [
            'department_id' => $departmentId,  // 必須
            'billing_date'  => $billingDate,    // 必須
            'sales_date'    => $billingDate,    // 売上計上日（請求日と同じにしておく）
            'due_date'      => $dueDate,
            'title'         => $req['subject'] ?? '',
            'memo'          => trim(($req['notes'] ?? '') . "\n" . ($req['special_notes'] ?? '')),
            'note'          => $req['notes'] ?? '',
            'items'         => $billingItems,
            'config'        => ['consumption_tax_display_type' => 'internal'],
        ];

        try {
            $result = $mf->requestCurl('POST', '/invoice_template_billings', $payload);
        } catch (Exception $apiErr) {
            // フィールド名違いの可能性に備えて代替パターンも試す
            $altAttempts = [];

            // 試行1: due_date を完全に省く
            $payloadNoDue = $payload;
            unset($payloadNoDue['due_date']);
            try {
                $result = $mf->requestCurl('POST', '/invoice_template_billings', $payloadNoDue);
                $altAttempts[] = 'due_date省略 → 成功';
            } catch (Exception $e2) {
                $altAttempts[] = 'due_date省略 → ' . $e2->getMessage();
            }

            // それでもダメならエラー
            if (!isset($result) || !$result) {
                throw new Exception('POST /invoice_template_billings 失敗: ' . $apiErr->getMessage()
                    . "\n[送信内容] " . json_encode($payload, JSON_UNESCAPED_UNICODE)
                    . "\n[代替試行] " . implode(' || ', $altAttempts));
            }
        }
        $mfId = $result['data']['id'] ?? $result['id'] ?? null;
        if (!$mfId) throw new Exception('MFからIDが返却されませんでした: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

        // 成功 → DBに記録
        $data['invoice_requests'][$reqIdx]['status'] = 'sent';
        $data['invoice_requests'][$reqIdx]['mf_initial_billing_id'] = $mfId;
        $data['invoice_requests'][$reqIdx]['mf_sent_at'] = $now;
        $data['invoice_requests'][$reqIdx]['mf_sent_by'] = $currentUser;
        $data['invoice_requests'][$reqIdx]['mf_error_message'] = null;
        $data['invoice_requests'][$reqIdx]['updated_at'] = $now;
        // 同時編集衝突防止: 単一行 UPSERT
        saveEntityRow('invoice_requests', $data['invoice_requests'][$reqIdx]);

        successResponse([
            'mf_billing_id' => $mfId,
            'item' => $data['invoice_requests'][$reqIdx],
        ], 'MFに初回請求書を' . ($draft ? '下書きとして' : '') . '送信しました');
    } catch (Exception $e) {
        $data['invoice_requests'][$reqIdx]['mf_error_message'] = $e->getMessage();
        $data['invoice_requests'][$reqIdx]['updated_at'] = $now;
        // 同時編集衝突防止: 単一行 UPSERT
        saveEntityRow('invoice_requests', $data['invoice_requests'][$reqIdx]);
        errorResponse('MF送信失敗: ' . $e->getMessage(), 500);
    }
}

// ─── Sheets連携 ───
if ($action === 'debug_headers') {
    try {
        $sheets = new GoogleSheetsClient(IR_SHEET_ID);
        $values = $sheets->getValues(IR_SHEET_RANGE);
        if (empty($values)) errorResponse('シートが空です', 400);
        $headers = $values[0];
        $itemHeaders = [];
        foreach ($headers as $idx => $h) {
            // 「品目」「単価」「日数」「数量」「単位」「消費税」「1-2」など含むヘッダー
            if (preg_match('/品目|単価|日数|数量|単位|消費税|1\s*[-‐−ー－]\s*2/u', (string)$h)) {
                $itemHeaders[] = ['col' => $idx + 1, 'header' => $h];
            }
        }
        // 1-2-N パターン検出（半角コロン/全角コロン両対応）
        $itemPattern = [];
        foreach ($headers as $idx => $h) {
            if (preg_match('/1[-‐−ー－]2[-‐−ー－](\d+)\s*[:：](.+)/u', (string)$h, $m)) {
                $itemPattern[] = ['col' => $idx + 1, 'item_idx' => (int)$m[1], 'field' => trim($m[2])];
            }
        }
        // サンプル行（最新1件）のデータも表示
        $sampleRow = end($values);
        successResponse([
            'total_columns' => count($headers),
            'all_headers' => array_map(fn($i, $h) => ['col' => $i + 1, 'header' => $h], array_keys($headers), $headers),
            'item_related_headers' => $itemHeaders,
            'pattern_matched_headers' => $itemPattern,
            'sample_row' => $sampleRow,
        ]);
    } catch (Exception $e) {
        errorResponse('Sheets取得失敗: ' . $e->getMessage(), 500);
    }
}

if ($action === 'force_resync') {
    // 既存をすべて削除して再取込
    try {
        $sheets = new GoogleSheetsClient(IR_SHEET_ID);
        $values = $sheets->getValues(IR_SHEET_RANGE);
        if (empty($values)) errorResponse('シートが空です', 400);
        $headers = $values[0];
        $rows = array_slice($values, 1);

        // 取引先ルックアップ
        $partnerLookup = [];
        foreach (filterDeleted($data['customers'] ?? []) as $c) {
            if (empty($c['mf_partner_id']) || empty($c['companyName'])) continue;
            $partnerLookup[$c['companyName']] = $c['mf_partner_id'];
            foreach ($c['aliases'] ?? [] as $alias) {
                if (!empty($alias)) $partnerLookup[$alias] = $c['mf_partner_id'];
            }
        }

        // 既存の sheets ソースのレコードのみ削除（手動入力は残す）
        $kept = [];
        foreach ($data['invoice_requests'] as $r) {
            if (($r['source'] ?? 'manual') !== 'sheets') $kept[] = $r;
        }
        $data['invoice_requests'] = $kept;

        $imported = 0;
        $autoMatched = 0;
        $errors = [];
        foreach ($rows as $i => $row) {
            $timestamp = trim((string)($row[0] ?? ''));
            if ($timestamp === '') continue;
            $rowKey = 'sheet_' . md5($timestamp);
            try {
                $request = parseInvoiceRequestRow($headers, $row, $rowKey, $timestamp, $now);
                if (empty($request['mf_partner_id']) && !empty($request['partner_name'])) {
                    $pn = trim($request['partner_name']);
                    if (isset($partnerLookup[$pn])) {
                        $request['mf_partner_id'] = $partnerLookup[$pn];
                        $autoMatched++;
                    } else {
                        foreach ($partnerLookup as $name => $mfId) {
                            if (mb_strpos($pn, $name) !== false || mb_strpos($name, $pn) !== false) {
                                $request['mf_partner_id'] = $mfId;
                                $autoMatched++;
                                break;
                            }
                        }
                    }
                }
                $data['invoice_requests'][] = $request;
                $imported++;
            } catch (Exception $e) {
                $errors[] = '行 ' . ($i + 2) . ': ' . $e->getMessage();
            }
        }
        saveData($data, ['invoice_requests']);
        successResponse([
            'imported' => $imported,
            'auto_matched' => $autoMatched,
            'errors' => $errors,
        ], '強制再同期: ' . $imported . ' 件取込（' . $autoMatched . ' 件MF取引先ID自動セット）');
    } catch (Exception $e) {
        errorResponse('再同期失敗: ' . $e->getMessage(), 500);
    }
}

if ($action === 'preview_sheet') {
    try {
        $sheets = new GoogleSheetsClient(IR_SHEET_ID);
        $values = $sheets->getValues(IR_SHEET_RANGE);
        if (empty($values)) errorResponse('シートが空です', 400);
        $headers = $values[0];
        $rows = array_slice($values, 1);
        $existingKeys = [];
        foreach ($data['invoice_requests'] as $r) {
            if (!empty($r['source_row_id'])) $existingKeys[$r['source_row_id']] = true;
        }
        $preview = [];
        foreach ($rows as $i => $row) {
            $timestamp = trim((string)($row[0] ?? ''));
            if ($timestamp === '') continue;
            $rowKey = 'sheet_' . md5($timestamp);
            $preview[] = [
                'row_no' => $i + 2,
                'source_row_id' => $rowKey,
                'timestamp' => $timestamp,
                'imported' => isset($existingKeys[$rowKey]),
            ];
        }
        successResponse([
            'sheet_id' => IR_SHEET_ID,
            'header_count' => count($headers),
            'data_rows' => count($preview),
            'imported_count' => count(array_filter($preview, fn($r) => $r['imported'])),
            'unimported_count' => count(array_filter($preview, fn($r) => !$r['imported'])),
            'headers' => $headers,
            'preview' => array_slice($preview, 0, 50),
        ]);
    } catch (Exception $e) {
        errorResponse('Sheets取得失敗: ' . $e->getMessage(), 500);
    }
}

if ($action === 'sync_from_sheet') {
    try {
        $sheets = new GoogleSheetsClient(IR_SHEET_ID);
        $values = $sheets->getValues(IR_SHEET_RANGE);
        if (empty($values)) errorResponse('シートが空です', 400);
        $headers = $values[0];
        $rows = array_slice($values, 1);
        $existingKeys = [];
        foreach ($data['invoice_requests'] as $r) {
            if (!empty($r['source_row_id'])) $existingKeys[$r['source_row_id']] = true;
        }

        // 取引先名 → MF取引先ID 逆引きマップ（customers から）
        $partnerLookup = [];
        foreach (filterDeleted($data['customers'] ?? []) as $c) {
            if (empty($c['mf_partner_id']) || empty($c['companyName'])) continue;
            $partnerLookup[$c['companyName']] = $c['mf_partner_id'];
            // エイリアスも登録
            foreach ($c['aliases'] ?? [] as $alias) {
                if (!empty($alias)) $partnerLookup[$alias] = $c['mf_partner_id'];
            }
        }

        $imported = 0;
        $skipped = 0;
        $autoMatched = 0;
        $errors = [];
        foreach ($rows as $i => $row) {
            $timestamp = trim((string)($row[0] ?? ''));
            if ($timestamp === '') continue;
            $rowKey = 'sheet_' . md5($timestamp);
            if (isset($existingKeys[$rowKey])) { $skipped++; continue; }
            try {
                $request = parseInvoiceRequestRow($headers, $row, $rowKey, $timestamp, $now);
                // MF取引先IDを自動引き（完全一致 → 部分一致）
                if (empty($request['mf_partner_id']) && !empty($request['partner_name'])) {
                    $pn = trim($request['partner_name']);
                    if (isset($partnerLookup[$pn])) {
                        $request['mf_partner_id'] = $partnerLookup[$pn];
                        $autoMatched++;
                    } else {
                        // 部分一致（取引先名にマッチする顧客名が含まれる）
                        foreach ($partnerLookup as $name => $mfId) {
                            if (mb_strpos($pn, $name) !== false || mb_strpos($name, $pn) !== false) {
                                $request['mf_partner_id'] = $mfId;
                                $autoMatched++;
                                break;
                            }
                        }
                    }
                }
                $data['invoice_requests'][] = $request;
                $existingKeys[$rowKey] = true;
                $imported++;
                // 同時編集衝突防止: 1件ずつ UPSERT で追加
                saveEntityRow('invoice_requests', $request);
            } catch (Exception $e) {
                $errors[] = '行 ' . ($i + 2) . ': ' . $e->getMessage();
            }
        }
        successResponse([
            'imported' => $imported,
            'skipped'  => $skipped,
            'auto_matched' => $autoMatched,
            'errors'   => $errors,
        ], $imported . ' 件取込（' . $autoMatched . ' 件はMF取引先ID自動セット） / ' . $skipped . ' 件スキップ');
    } catch (Exception $e) {
        errorResponse('同期失敗: ' . $e->getMessage(), 500);
    }
}

// 既取込の依頼にも MF取引先ID を自動引き直す
if ($action === 'rematch_partners') {
    try {
        $partnerLookup = [];
        foreach (filterDeleted($data['customers'] ?? []) as $c) {
            if (empty($c['mf_partner_id']) || empty($c['companyName'])) continue;
            $partnerLookup[$c['companyName']] = $c['mf_partner_id'];
            foreach ($c['aliases'] ?? [] as $alias) {
                if (!empty($alias)) $partnerLookup[$alias] = $c['mf_partner_id'];
            }
        }
        $matched = 0;
        $matchedRows = [];
        foreach ($data['invoice_requests'] as &$r) {
            if (!empty($r['mf_partner_id'])) continue;
            if (empty($r['partner_name'])) continue;
            $pn = trim($r['partner_name']);
            $hit = $partnerLookup[$pn] ?? null;
            if (!$hit) {
                foreach ($partnerLookup as $name => $mfId) {
                    if (mb_strpos($pn, $name) !== false || mb_strpos($name, $pn) !== false) {
                        $hit = $mfId; break;
                    }
                }
            }
            if ($hit) {
                $r['mf_partner_id'] = $hit;
                $r['updated_at'] = $now;
                $matched++;
                $matchedRows[] = $r;
            }
        }
        unset($r);
        // 同時編集衝突防止: マッチした行を1件ずつ UPSERT
        foreach ($matchedRows as $row) {
            saveEntityRow('invoice_requests', $row);
        }
        successResponse(['matched' => $matched], $matched . ' 件のMF取引先IDを自動セットしました');
    } catch (Exception $e) {
        errorResponse('紐付け失敗: ' . $e->getMessage(), 500);
    }
}

/**
 * フィールド名から品目データへ値を割り当て
 */
function applyItemField(array &$item, string $field, string $value): void {
    if (mb_stripos($field, '各品目') !== false)        { $item['name'] = $value; return; }
    if (mb_stripos($field, '日額単価') !== false)      { $item['initial_unit_price'] = (float)$value; return; }
    if (mb_stripos($field, '月額単価') !== false)      { $item['monthly_unit_price'] = (float)$value; return; }
    if (mb_stripos($field, '販売単価') !== false)      { $item['monthly_unit_price'] = (float)$value; return; }
    if (mb_stripos($field, '撤去単価') !== false)      { $item['monthly_unit_price'] = (float)$value; return; }
    if (mb_stripos($field, '請求日数') !== false)      { $item['initial_days'] = (int)$value; return; }
    if (mb_stripos($field, '月額数量') !== false)      { $item['monthly_quantity'] = (float)$value; return; }
    if (mb_stripos($field, '販売数量') !== false)      { $item['monthly_quantity'] = (float)$value; return; }
    if (mb_stripos($field, '撤去数量') !== false)      { $item['monthly_quantity'] = (float)$value; return; }
    if (mb_stripos($field, '単位') !== false)          { $item['monthly_unit'] = $value; return; }
    if (mb_stripos($field, '消費税') !== false)        { $item['tax_type'] = $value; return; }
}

/**
 * フォーム回答1行をパースして invoice_request 形式に変換
 */
function parseInvoiceRequestRow(array $headers, array $row, string $rowKey, string $timestamp, string $now): array {
    $headerMap = [];
    foreach ($headers as $idx => $h) $headerMap[trim((string)$h)] = $idx;

    $get = function($pattern) use ($headerMap, $row) {
        foreach ($headerMap as $h => $idx) {
            if (mb_stripos($h, $pattern) !== false) return trim((string)($row[$idx] ?? ''));
        }
        return '';
    };
    $normDate = function($s) {
        $s = trim((string)$s);
        if ($s === '') return '';
        $s = str_replace('/', '-', $s);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return '';
    };

    // 品目を抽出（依頼種別ごとの全パターン対応 + プレフィックス無し追加スロット対応）
    // - プレフィックス有り: 1-2-N / 1-3-N / 2-2-N / 3-2-N / 3-4-N
    // - プレフィックス無し: フォーム拡張で増えた追加スロット（各品目から始まる連続列を1グループ）
    $items = [];

    // 第1パス: プレフィックス有りの列を集約
    foreach ($headerMap as $header => $idx) {
        if (!preg_match('/^(\d+)[-\x{2010}\x{2011}\x{2212}\x{FF0D}](\d+)[-\x{2010}\x{2011}\x{2212}\x{FF0D}](\d+)\s*[:：](.+)$/u', $header, $m)) continue;
        $sectionKey = $m[1] . '-' . $m[2];
        $itemIdx    = (int)$m[3];
        $field      = trim($m[4]);
        $value      = trim((string)($row[$idx] ?? ''));
        if ($value === '') continue;

        $key = sprintf('%s-%03d', $sectionKey, $itemIdx);  // ソート用に0埋め
        if (!isset($items[$key])) {
            $items[$key] = [
                'section' => $sectionKey,
                'name' => '', 'initial_unit_price' => 0, 'initial_days' => 0,
                'monthly_unit_price' => 0, 'monthly_quantity' => 0,
                'monthly_unit' => '月', 'tax_type' => '10%対象',
            ];
        }
        applyItemField($items[$key], $field, $value);
    }

    // 第2パス: プレフィックス無し列を「各品目」をアンカーにグループ化（フォーム拡張スロット対応）
    $headerList = array_keys($headerMap);
    $extraIdx = 0;
    $currentExtraKey = null;
    foreach ($headerList as $header) {
        $idx = $headerMap[$header];
        $hClean = preg_replace('/\s+/u', '', (string)$header);
        // プレフィックス有り列はスキップ（第1パスで処理済み）
        if (preg_match('/^\d+[-\x{2010}\x{2011}\x{2212}\x{FF0D}]\d+[-\x{2010}\x{2011}\x{2212}\x{FF0D}]\d+\s*[:：]/u', (string)$header)) continue;
        // フィールド名のクリーンアップ（改行・空白除去）
        $field = trim((string)$header);
        $value = trim((string)($row[$idx] ?? ''));

        // 「各品目」が来たら新しいスロットを開始
        if ($hClean === '各品目') {
            if ($value === '') { $currentExtraKey = null; continue; }
            $extraIdx++;
            $currentExtraKey = sprintf('z-extra-%03d', $extraIdx);
            $items[$currentExtraKey] = [
                'section' => 'extra',
                'name' => $value, 'initial_unit_price' => 0, 'initial_days' => 0,
                'monthly_unit_price' => 0, 'monthly_quantity' => 0,
                'monthly_unit' => '月', 'tax_type' => '10%対象',
            ];
            continue;
        }
        // 進行中のスロットに値を追加
        if ($currentExtraKey === null) continue;
        if ($value === '') continue;
        applyItemField($items[$currentExtraKey], $field, $value);
    }

    uksort($items, fn($a, $b) => strnatcmp($a, $b));
    $items = array_values(array_filter($items, fn($i) => !empty($i['name'])));

    return [
        'id' => uniqid('ir_'),
        'source' => 'sheets',
        'source_row_id' => $rowKey,
        'source_timestamp' => $timestamp,
        'requester_name' => $get('依頼者名'),
        'attached_file_id' => $get('対象ファイルのアップロード'),
        'pj_number' => $get('プロジェクト番号'),
        'subject' => $get('件名'),
        'partner_name' => $get('請求先名'),
        'partner_department' => $get('部署名'),
        'mf_partner_id' => '',
        'billing_method_1' => $get('請求方法1') ?: $get('請求方法１'),
        'billing_method_2' => $get('請求方法2') ?: $get('請求方法２'),
        'request_type' => $get('請求依頼の種類'),
        'billing_start_date' => $normDate($get('請求開始日')),
        'payment_due_date' => $normDate($get('支払期限')),
        'closing_day' => $get('〆日'),
        'rental_period' => $get('レンタル期間'),
        'auto_renew' => mb_stripos($get('自動作成設定'), '必要') !== false ? 1 : 0,
        'has_prorated' => mb_stripos($get('日割り対象'), 'アリ') !== false ? 1 : 0,
        'items' => $items,
        'notes' => $get('備考欄'),
        'special_notes' => $get('特記事項'),
        'status' => 'pending',
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

errorResponse('不正なアクションです', 400);
