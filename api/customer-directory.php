<?php
/**
 * 顧客ディレクトリ API（営業情報統合: 顧客マスター × AM連動）
 *
 *   GET  ?action=list[&q=&rank=&am=]   顧客一覧（ランク/AM/CC人数/最終更新）
 *   GET  ?action=detail&id=            顧客詳細（AM・CC・基本情報・最近の取引・実効ランク）
 *   GET  ?action=employees             AM/CC 候補となる従業員一覧
 *   POST action=update_basic           基本情報＋AM＋ランクモードを更新
 *   POST action=cc_add                 CC候補を追加
 *   POST action=cc_update              CC候補を更新
 *   POST action=cc_delete              CC候補を論理削除
 *   POST action=recompute_ranks        全顧客の自動ランクを再計算して保存
 *
 * 閲覧: sales 以上 / 編集: product 以上（CLAUDE.md 権限規約）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/customer-rank.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'rateLimit'      => 200,
    'allowedMethods' => ['GET', 'POST'],
]);

$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------------
// GET
// ----------------------------------------------------------------
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    switch ($action) {
        case 'list':      successResponse(cdList());                      break;
        case 'detail':    successResponse(cdDetail($_GET['id'] ?? ''));   break;
        case 'employees': successResponse(cdEmployees());                 break;
        default:          errorResponse('不明なアクション: ' . $action, 400);
    }
}

// ----------------------------------------------------------------
// POST（編集系: product 以上）
// ----------------------------------------------------------------
$input  = getJsonInput();
$action = $input['action'] ?? '';

if ($action === 'recompute_ranks') {
    if (!canEdit()) errorResponse('編集権限がありません', 403);
    successResponse(cdRecomputeRanks(), 'ランクを再計算しました');
}

if (!canEdit()) errorResponse('編集権限がありません', 403);

switch ($action) {
    case 'update_basic': successResponse(cdUpdateBasic($input), '更新しました');  break;
    case 'cc_add':       successResponse(cdCcAdd($input), 'CC候補を追加しました'); break;
    case 'cc_update':    successResponse(cdCcUpdate($input), '更新しました');      break;
    case 'cc_delete':    cdCcDelete($input); successResponse([], '削除しました');  break;
    default:             errorResponse('不明なアクション: ' . $action, 400);
}

// ================================================================
// 実装
// ================================================================

/** 顧客行の確定ランク（S/A/B、未設定は ''） */
function cdEffectiveRank(array $c): string {
    return effectiveCustomerRank($c);
}

function cdEmployeeMap(): array {
    $emps = Database::queryEntity('employees', ['not_deleted' => true]);
    $map = [];
    foreach ($emps as $e) {
        $map[$e['id']] = $e;
    }
    return $map;
}

/** CC人数を customer_id ごとに集計（未削除のみ） */
function cdCcCounts(): array {
    $rows = Database::queryEntity('customer_cc', ['not_deleted' => true]);
    $counts = [];
    foreach ($rows as $r) {
        $cid = $r['customer_id'] ?? '';
        if ($cid === '') continue;
        $counts[$cid] = ($counts[$cid] ?? 0) + 1;
    }
    return $counts;
}

function cdList(): array {
    $q      = trim((string)($_GET['q'] ?? ''));
    $rank   = trim((string)($_GET['rank'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));   // 既存 / 休眠
    $tanto  = trim((string)($_GET['tanto'] ?? ''));    // 担当（am_person）

    $customers = Database::queryEntity('customers', [
        'not_deleted' => true,
        'order_by'    => 'updated_at DESC',
    ]);
    $ccCount = cdCcCounts();

    // 名寄せ用: MF確定顧客の正規化名セット（照合バッジ用）
    $mfNames = [];
    foreach ($customers as $c) {
        if (isMfVerifiedCustomer($c)) $mfNames[normalizeCompanyName($c['companyName'] ?? '')] = true;
    }

    // 担当一覧（フィルタ用）
    $tantoSet = [];

    $out = [];
    $counts = ['既存' => 0, '休眠' => 0];
    foreach ($customers as $c) {
        // 母集団: アカウントマネジメント対象（AMナンバーあり）のみ
        $amNo = $c['am_number'] ?? '';
        if ($amNo === '' || $amNo === null) continue;

        $name = $c['companyName'] ?? '';
        $st   = $c['account_status'] ?? '';
        if (isset($counts[$st])) $counts[$st]++;
        if (($c['am_person'] ?? '') !== '') $tantoSet[$c['am_person']] = true;

        if ($status !== '' && $st !== $status) continue;
        if ($tanto !== '' && ($c['am_person'] ?? '') !== $tanto) continue;
        if ($q !== '' && mb_stripos($name, $q) === false) continue;
        $eff = cdEffectiveRank($c);
        if ($rank !== '' && $eff !== $rank) continue;

        $norm = normalizeCompanyName($name);
        $matchStatus = isMfVerifiedCustomer($c) ? 'mf' : (($norm !== '' && isset($mfNames[$norm])) ? 'dup' : 'unmatched');

        $out[] = [
            'id'             => $c['id'],
            'am_number'      => $amNo,
            'companyName'    => $name,
            'account_status' => $st,
            'account_type'   => $c['account_type'] ?? '',
            'type_memo'      => $c['account_type_memo'] ?? '',
            'priority'       => $c['priority'] ?? '',
            'rank'           => $eff,
            'rank_challenge' => $c['rank_challenge'] ?? '',
            'am_person'      => $c['am_person'] ?? '',
            'cc_count'       => $ccCount[$c['id']] ?? 0,
            'match_status'   => $matchStatus,
            'updated_at'     => $c['updated_at'] ?? '',
        ];
    }

    // AMナンバー昇順（AM1, AM2 …）で並べる
    usort($out, function ($a, $b) {
        $na = (int) preg_replace('/\D/', '', $a['am_number']);
        $nb = (int) preg_replace('/\D/', '', $b['am_number']);
        return $na <=> $nb;
    });

    $tantoList = array_keys($tantoSet);
    sort($tantoList);

    return ['customers' => $out, 'total' => count($out), 'counts' => $counts, 'tanto_list' => $tantoList];
}

function cdDetail(string $id): array {
    if ($id === '') errorResponse('id が必要です', 400);
    $c = Database::findEntityById('customers', $id);
    if (!$c || !empty($c['deleted_at'])) errorResponse('顧客が見つかりません', 404);

    $empMap = cdEmployeeMap();
    $amId   = $c['am_employee_id'] ?? '';
    $am     = $amId !== '' ? ($empMap[$amId] ?? null) : null;

    // CC候補（未削除・sort_order順）
    $ccRows = Database::queryEntity('customer_cc', [
        'where'       => ['customer_id' => $id],
        'not_deleted' => true,
        'order_by'    => 'sort_order ASC, created_at ASC',
    ]);

    // 確定ランク（人が割り当てた値）＋ MF実績からの目安＋直近5ヶ月請求
    $assigned = effectiveCustomerRank($c);
    $suggest  = suggestCustomerRank($c['companyName'] ?? '');
    $recent5  = customerRecentBilling($c['companyName'] ?? '', 5);

    // 照合状態（MF確定 / 未照合 / 重複候補）
    $matchStatus = 'mf';
    if (!isMfVerifiedCustomer($c)) {
        $norm = normalizeCompanyName($c['companyName'] ?? '');
        $matchStatus = 'unmatched';
        if ($norm !== '') {
            foreach (Database::queryEntity('customers', ['not_deleted' => true]) as $v) {
                if (isMfVerifiedCustomer($v) && normalizeCompanyName($v['companyName'] ?? '') === $norm) {
                    $matchStatus = 'dup';
                    break;
                }
            }
        }
    }

    // 最近の取引（請求: 直近5件）
    $invoices = Database::queryEntity('mf_invoices', [
        'where'       => ['partner_name' => $c['companyName'] ?? ''],
        'not_deleted' => true,
        'order_by'    => 'billing_date DESC',
        'limit'       => 5,
    ]);
    $recent = array_map(fn($iv) => [
        'billing_number' => $iv['billing_number'] ?? '',
        'billing_date'   => $iv['billing_date'] ?? '',
        'total_amount'   => $iv['total_amount'] ?? $iv['amount'] ?? null,
        'title'          => $iv['title'] ?? '',
    ], $invoices);

    return [
        'customer' => [
            'id'            => $c['id'],
            'companyName'   => $c['companyName'] ?? '',
            'customer_code' => $c['customer_code'] ?? '',
            'industry'      => $c['industry'] ?? '',
            'trade_start'   => $c['trade_start'] ?? '',
            'credit_limit'  => $c['credit_limit'] ?? null,
            'area'          => $c['area'] ?? '',
            'phone'         => $c['phone'] ?? '',
            'email'         => $c['email'] ?? '',
            'address'       => $c['address'] ?? '',
            'notes'         => $c['notes'] ?? '',
            'customer_rank' => $assigned,
            'am_employee_id'=> $amId,
            // アカウントマネジメント項目
            'am_number'         => $c['am_number'] ?? '',
            'account_status'    => $c['account_status'] ?? '',
            'account_type'      => $c['account_type'] ?? '',
            'account_type_memo' => $c['account_type_memo'] ?? '',
            'hq_location'       => $c['hq_location'] ?? '',
            'priority'          => $c['priority'] ?? '',
            'rank_challenge'    => $c['rank_challenge'] ?? '',
            'am_person'         => $c['am_person'] ?? '',
            'am_memo'           => $c['am_memo'] ?? '',
        ],
        'rank'           => $assigned,
        'suggested_rank' => $suggest['rank'],
        'annual_sales'   => $suggest['annual_sales'],
        'recent5_total'  => $recent5['total'],
        'recent5_avg'    => $recent5['monthly_avg'],
        'match_status'   => $matchStatus,
        'am'           => $am ? [
            'id'    => $am['id'],
            'name'  => $am['name'] ?? '',
            'email' => $am['email'] ?? '',
            'phone' => $am['phone'] ?? '',
        ] : null,
        'cc'      => array_map(fn($r) => [
            'id'          => $r['id'],
            'employee_id' => $r['employee_id'] ?? '',
            'name'        => $r['name'] ?? '',
            'email'       => $r['email'] ?? '',
            'role_label'  => $r['role_label'] ?? '',
            'note'        => $r['note'] ?? '',
        ], $ccRows),
        'recent'  => $recent,
    ];
}

function cdEmployees(): array {
    $emps = Database::queryEntity('employees', [
        'not_deleted' => true,
        'order_by'    => 'name ASC',
    ]);
    return ['employees' => array_map(fn($e) => [
        'id'         => $e['id'],
        'name'       => $e['name'] ?? '',
        'email'      => $e['email'] ?? '',
        'department' => $e['department'] ?? '',
        'role'       => $e['role'] ?? '',
    ], $emps)];
}

function cdUpdateBasic(array $input): array {
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') errorResponse('id が必要です', 400);
    $c = Database::findEntityById('customers', $id);
    if (!$c || !empty($c['deleted_at'])) errorResponse('顧客が見つかりません', 404);

    $c['customer_code']  = sanitizeInput($input['customer_code'] ?? ($c['customer_code'] ?? ''), 'string');
    $c['industry']       = sanitizeInput($input['industry'] ?? ($c['industry'] ?? ''), 'string');
    $c['trade_start']    = trim((string)($input['trade_start'] ?? ($c['trade_start'] ?? '')));
    $c['area']           = sanitizeInput($input['area'] ?? ($c['area'] ?? ''), 'string');
    $c['am_employee_id'] = trim((string)($input['am_employee_id'] ?? ($c['am_employee_id'] ?? '')));

    // ランクは人が割り当てる S/A/B（空=未設定）。それ以外の値は未設定に正規化。
    if (array_key_exists('rank', $input)) {
        $rank = strtoupper(trim((string)$input['rank']));
        $c['customer_rank'] = isValidCustomerRank($rank) ? $rank : '';
    }
    if (array_key_exists('rank_challenge', $input)) {
        $rc = strtoupper(trim((string)$input['rank_challenge']));
        $c['rank_challenge'] = isValidCustomerRank($rc) ? $rc : '';
    }

    // アカウントマネジメント項目
    foreach (['am_number','account_status','account_type','account_type_memo','hq_location','priority','am_person'] as $f) {
        if (array_key_exists($f, $input)) $c[$f] = sanitizeInput($input[$f], 'string');
    }
    if (array_key_exists('am_memo', $input)) $c['am_memo'] = trim((string)$input['am_memo']);

    $credit = $input['credit_limit'] ?? null;
    $c['credit_limit'] = ($credit === '' || $credit === null) ? null : (float)preg_replace('/[^\d.]/', '', (string)$credit);

    $c['updated_at'] = formatDateIso();
    Database::saveEntityRow('customers', $c);

    auditUpdate('customers', $id, '顧客の営業情報を更新: ' . ($c['companyName'] ?? ''));
    return ['id' => $id, 'rank' => effectiveCustomerRank($c)];
}

function cdCcAdd(array $input): array {
    $customerId = trim((string)($input['customer_id'] ?? ''));
    if ($customerId === '') errorResponse('customer_id が必要です', 400);

    $row = [
        'id'          => uniqid('cc_'),
        'customer_id' => $customerId,
        'employee_id' => trim((string)($input['employee_id'] ?? '')),
        'name'        => sanitizeInput($input['name'] ?? '', 'string'),
        'email'       => trim((string)($input['email'] ?? '')),
        'role_label'  => sanitizeInput($input['role_label'] ?? '', 'string'),
        'note'        => sanitizeInput($input['note'] ?? '', 'string'),
        'sort_order'  => (int)($input['sort_order'] ?? 0),
        'created_by'  => $_SESSION['user_email'] ?? '',
        'created_at'  => formatDateIso(),
        'updated_at'  => formatDateIso(),
        'deleted_at'  => null,
    ];
    if ($row['email'] !== '' && !validateEmail($row['email'])) {
        errorResponse('メールアドレスの形式が不正です', 400);
    }
    Database::saveEntityRow('customer_cc', $row);
    return ['id' => $row['id']];
}

function cdCcUpdate(array $input): array {
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') errorResponse('id が必要です', 400);
    $row = Database::findEntityById('customer_cc', $id);
    if (!$row || !empty($row['deleted_at'])) errorResponse('CC候補が見つかりません', 404);

    if (array_key_exists('employee_id', $input)) $row['employee_id'] = trim((string)$input['employee_id']);
    if (array_key_exists('name', $input))        $row['name']        = sanitizeInput($input['name'], 'string');
    if (array_key_exists('email', $input))       $row['email']       = trim((string)$input['email']);
    if (array_key_exists('role_label', $input))  $row['role_label']  = sanitizeInput($input['role_label'], 'string');
    if (array_key_exists('note', $input))        $row['note']        = sanitizeInput($input['note'], 'string');
    if (array_key_exists('sort_order', $input))  $row['sort_order']  = (int)$input['sort_order'];

    if (!empty($row['email']) && !validateEmail($row['email'])) {
        errorResponse('メールアドレスの形式が不正です', 400);
    }
    $row['updated_at'] = formatDateIso();
    Database::saveEntityRow('customer_cc', $row);
    return ['id' => $id];
}

function cdCcDelete(array $input): void {
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') errorResponse('id が必要です', 400);
    $row = Database::findEntityById('customer_cc', $id);
    if (!$row) errorResponse('CC候補が見つかりません', 404);

    $row['deleted_at'] = formatDateIso();
    $row['deleted_by'] = $_SESSION['user_email'] ?? '';
    Database::saveEntityRow('customer_cc', $row);
}

/**
 * 未設定（または旧5段階の無効値）の顧客に、MF実績からのランク目安を初期反映する。
 * 既に S/A/B が割り当てられている顧客は上書きしない（人の分類を尊重）。
 */
function cdRecomputeRanks(): array {
    $customers = Database::queryEntity('customers', ['not_deleted' => true]);
    $updated = 0;
    foreach ($customers as $c) {
        if (isValidCustomerRank($c['customer_rank'] ?? '')) continue; // 割当済みは触らない
        $suggest = suggestCustomerRank($c['companyName'] ?? '');
        $c['customer_rank'] = $suggest['rank'];
        $c['updated_at']    = formatDateIso();
        Database::saveEntityRow('customers', $c);
        $updated++;
    }
    return ['updated' => $updated, 'total' => count($customers)];
}
