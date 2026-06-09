<?php
/**
 * 営業リード API
 *
 * GET  ?action=list           リード一覧（削除済み除外、検索/フィルタ）
 * GET  ?action=get&id=xxx     1件取得
 * POST action=create          作成（sales 以上）
 * POST action=update          更新（sales 以上）
 * POST action=delete          削除（admin のみ・論理削除）
 *
 * 名刺画像は data URL (base64) で受け取り、サーバー側で
 * uploads/business-cards/yyyymm/ に保存して相対パスを記録する。
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/lead-activity-repository.php'; // タイムライン記録
require_once __DIR__ . '/../functions/business-card-repository.php'; // 名刺 (Phase 2)

initApi([
    'requireAuth' => true,
    'requireCsrf' => false, // POST 内で個別検証
    'rateLimit'   => false,
]);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

const LEAD_STATUSES = ['新規', '接触済', '商談中', '成約', '失注'];
const LEAD_SOURCES  = ['business_card', 'manual'];

/**
 * data URL を受け取り、uploads/business-cards/yyyymm/ に保存して相対パスを返す。
 * 失敗時は空文字。
 *
 * @return string 例: "uploads/business-cards/202605/bc_20260513_abcd.jpg"
 */
function leads_save_data_url(string $dataUrl): string {
    if ($dataUrl === '') return '';
    if (!preg_match('#^data:(image/(jpeg|png|webp|heic|heif));base64,(.+)$#', $dataUrl, $m)) {
        return '';
    }
    $mime = $m[1];
    $b64  = $m[3];
    $bin  = base64_decode($b64, true);
    if ($bin === false || strlen($bin) === 0) return '';
    if (strlen($bin) > 10 * 1024 * 1024) return ''; // 10MB 上限

    $extMap = [
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif',
    ];
    $ext = $extMap[$mime] ?? 'bin';

    $subdir = date('Ym');
    $dir = __DIR__ . '/../uploads/business-cards/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return '';
    }
    $name = 'bc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $abs  = $dir . '/' . $name;
    if (file_put_contents($abs, $bin, LOCK_EX) === false) return '';

    return 'uploads/business-cards/' . $subdir . '/' . $name;
}

/** 入力値を string にキャストして trim */
function leads_s($v): string { return is_string($v) ? trim($v) : ''; }

/** ステータスを正規化（未指定は「新規」） */
function leads_normalize_status(?string $v): string {
    $v = leads_s($v);
    if ($v === '') return '新規';
    return in_array($v, LEAD_STATUSES, true) ? $v : '新規';
}

/** ソースを正規化（未指定は manual） */
function leads_normalize_source(?string $v): string {
    $v = leads_s($v);
    if ($v === '') return 'manual';
    return in_array($v, LEAD_SOURCES, true) ? $v : 'manual';
}

/** 一覧用にエンコード等を整える（現状そのまま返す） */
function leads_view(array $lead): array {
    // 公開不要な内部フィールドはここで除外可（現状なし）
    return $lead;
}

// ==============================
// GET: 一覧
// ==============================
if ($method === 'GET' && $action === 'list') {
    $data  = getData();
    $leads = $data['leads'] ?? [];
    $leads = array_values(array_filter($leads, fn($l) => empty($l['deleted_at'])));

    $q       = trim($_GET['q'] ?? '');
    $status  = trim($_GET['status'] ?? '');

    if ($q !== '') {
        $needle = mb_strtolower($q);
        $leads = array_values(array_filter($leads, function ($l) use ($needle) {
            $hay = mb_strtolower(
                ($l['company_name'] ?? '') . ' ' .
                ($l['person_name'] ?? '') . ' ' .
                ($l['email'] ?? '') . ' ' .
                ($l['product_name'] ?? '') . ' ' .
                ($l['site_name'] ?? '') . ' ' .
                ($l['end_user_company'] ?? '') . ' ' .
                ($l['prefecture'] ?? '') . ' ' .
                ($l['notes'] ?? '')
            );
            return mb_strpos($hay, $needle) !== false;
        }));
    }
    if ($status !== '' && in_array($status, LEAD_STATUSES, true)) {
        $leads = array_values(array_filter($leads, fn($l) => ($l['status'] ?? '') === $status));
    }

    // 新しい順
    usort($leads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

    // ステータス別件数（フィルタ前の集計）
    $allActive = array_values(array_filter($data['leads'] ?? [], fn($l) => empty($l['deleted_at'])));
    $statusCounts = array_fill_keys(LEAD_STATUSES, 0);
    foreach ($allActive as $l) {
        $st = $l['status'] ?? '新規';
        if (isset($statusCounts[$st])) $statusCounts[$st]++;
    }

    successResponse([
        'leads'         => array_map('leads_view', $leads),
        'total'         => count($leads),
        'status_counts' => $statusCounts,
    ]);
}

// ==============================
// GET: 1件取得
// ==============================
if ($method === 'GET' && $action === 'get') {
    $id = trim($_GET['id'] ?? '');
    if ($id === '') errorResponse('id が必要です', 400);

    $data = getData();
    foreach ($data['leads'] ?? [] as $l) {
        if (($l['id'] ?? '') === $id && empty($l['deleted_at'])) {
            successResponse(['lead' => leads_view($l)]);
        }
    }
    errorResponse('リードが見つかりません', 404);
}

// ==============================
// GET: 名刺一覧 / 1件取得 (Phase 2)
// ==============================
if ($method === 'GET' && $action === 'list_cards') {
    try {
        $items = BusinessCardRepository::listAll([
            'search'          => trim($_GET['search'] ?? '') ?: null,
            'unpromoted_only' => !empty($_GET['unpromoted_only']),
            'promoted_only'   => !empty($_GET['promoted_only']),
            'limit'           => (int)($_GET['limit'] ?? 500),
        ]);
        successResponse(['items' => $items]);
    } catch (\Throwable $e) {
        errorResponse('取得失敗: ' . $e->getMessage(), 500);
    }
}

if ($method === 'GET' && $action === 'get_card') {
    $cardId = trim($_GET['id'] ?? '');
    if ($cardId === '') errorResponse('id が必要です', 400);
    $card = BusinessCardRepository::get($cardId);
    if (!$card) errorResponse('名刺が見つかりません', 404);
    successResponse(['card' => $card]);
}

// ==============================
// GET: タイムライン (リード単位)
// ==============================
if ($method === 'GET' && $action === 'list_activities') {
    $leadId = trim($_GET['lead_id'] ?? '');
    if ($leadId === '') errorResponse('lead_id が必要です', 400);
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
    try {
        $items = LeadActivityRepository::listByLead($leadId, $limit);
        successResponse(['items' => $items]);
    } catch (\Throwable $e) {
        errorResponse('取得失敗: ' . $e->getMessage(), 500);
    }
}

// ==============================
// POST: CSRF必須
// ==============================
if ($method === 'POST') {
    verifyCsrfToken();
    $input  = getJsonInput();
    $action = $input['action'] ?? $action;

    // ---- 作成 ----
    if ($action === 'create') {
        if (!hasPermission('sales')) errorResponse('権限がありません', 403);

        $company = leads_s($input['company_name'] ?? '');
        if ($company === '') errorResponse('会社名は必須です', 400);

        $imageDataUrl = leads_s($input['image_data_url'] ?? '');
        $imagePath    = $imageDataUrl !== '' ? leads_save_data_url($imageDataUrl) : '';

        $data = getData();
        $confVal = leads_s($input['confidence'] ?? '');
        $lead = [
            'id'                       => 'lead_' . uniqid('', true),
            'company_name'             => $company,
            'person_name'              => leads_s($input['person_name'] ?? ''),
            'title'                    => leads_s($input['title'] ?? ''),
            'department'               => leads_s($input['department'] ?? ''),
            'phone'                    => leads_s($input['phone'] ?? ''),
            'mobile'                   => leads_s($input['mobile'] ?? ''),
            'fax'                      => leads_s($input['fax'] ?? ''),
            'email'                    => leads_s($input['email'] ?? ''),
            'website'                  => leads_s($input['website'] ?? ''),
            'address'                  => leads_s($input['address'] ?? ''),
            'status'                   => leads_normalize_status($input['status'] ?? '新規'),
            'source'                   => leads_normalize_source($input['source'] ?? 'manual'),
            'business_card_image_path' => $imagePath,
            'am'                       => leads_s($input['am'] ?? ($_SESSION['user_name'] ?? '')),
            'notes'                    => leads_s($input['notes'] ?? ''),
            // v2 フィールド
            'dealer_branch'            => leads_s($input['dealer_branch'] ?? ''),
            'prefecture'               => leads_s($input['prefecture'] ?? ''),
            'end_user_company'         => leads_s($input['end_user_company'] ?? ''),
            'site_name'                => leads_s($input['site_name'] ?? ''),
            'product_name'             => leads_s($input['product_name'] ?? ''),
            'product_size'             => leads_s($input['product_size'] ?? ''),
            'transaction_type'         => leads_s($input['transaction_type'] ?? ''),
            'confidence'               => ($confVal !== '' && is_numeric($confVal)) ? (int)$confVal : null,
            'quote_status'             => leads_s($input['quote_status'] ?? ''),
            'expected_close_date'      => leads_s($input['expected_close_date'] ?? '') ?: null,
            'assigned_to'              => $_SESSION['user_email'] ?? '',
            'last_activity_at'         => date('Y-m-d H:i:s'),
            'created_by'               => $_SESSION['user_email'] ?? '',
            'created_at'               => date('Y-m-d H:i:s'),
            'updated_at'               => date('Y-m-d H:i:s'),
            'deleted_at'               => null,
            'deleted_by'               => null,
        ];
        $data['leads'][] = $lead;
        // 同時編集衝突防止: 単一行 UPSERT
        saveEntityRow('leads', $lead);

        if (function_exists('auditCreate')) {
            auditCreate('leads', $lead['id'], 'リード登録: ' . $company);
        }
        if (function_exists('logInfo')) {
            logInfo('lead_created', ['id' => $lead['id'], 'company' => $company]);
        }
        successResponse(['lead' => leads_view($lead)], 'リードを登録しました');
    }

    // ---- 更新 ----
    if ($action === 'update') {
        if (!hasPermission('sales')) errorResponse('権限がありません', 403);

        $id = leads_s($input['id'] ?? '');
        if ($id === '') errorResponse('id が必要です', 400);

        $company = leads_s($input['company_name'] ?? '');
        if ($company === '') errorResponse('会社名は必須です', 400);

        $data  = getData();
        $found = false;
        $updatedLead = null;
        $oldStatus   = null;
        foreach ($data['leads'] as &$l) {
            if (($l['id'] ?? '') === $id && empty($l['deleted_at'])) {
                // 変更前のステータスをキャプチャ (タイムライン自動記録用)
                $oldStatus = $l['status'] ?? null;

                $confVal = leads_s($input['confidence'] ?? '');
                $l['company_name']        = $company;
                $l['person_name']         = leads_s($input['person_name'] ?? '');
                $l['email']               = leads_s($input['email'] ?? ($l['email'] ?? ''));
                $l['status']              = leads_normalize_status($input['status'] ?? '新規');
                $l['am']                  = leads_s($input['am'] ?? ($l['am'] ?? ''));
                $l['notes']               = leads_s($input['notes'] ?? '');
                // v2 フィールド
                $l['dealer_branch']       = leads_s($input['dealer_branch'] ?? '');
                $l['prefecture']          = leads_s($input['prefecture'] ?? '');
                $l['end_user_company']    = leads_s($input['end_user_company'] ?? '');
                $l['site_name']           = leads_s($input['site_name'] ?? '');
                $l['product_name']        = leads_s($input['product_name'] ?? '');
                $l['product_size']        = leads_s($input['product_size'] ?? '');
                $l['transaction_type']    = leads_s($input['transaction_type'] ?? '');
                $l['confidence']          = ($confVal !== '' && is_numeric($confVal)) ? (int)$confVal : null;
                $l['quote_status']        = leads_s($input['quote_status'] ?? '');
                $l['expected_close_date'] = leads_s($input['expected_close_date'] ?? '') ?: null;
                $l['last_activity_at']    = date('Y-m-d H:i:s');
                $l['updated_at']          = date('Y-m-d H:i:s');
                // 旧フィールド (モーダルにないが、既存データ保持のため触らない)
                // title, department, phone, mobile, fax, website, address は変更しない
                $found = true;
                $updatedLead = $l;
                break;
            }
        }
        unset($l);

        if (!$found) errorResponse('リードが見つかりません', 404);
        // 同時編集衝突防止: 単一行 UPSERT
        saveEntityRow('leads', $updatedLead);

        // ステータスが変わったらタイムラインに自動記録 (失敗してもリード更新は成功扱い)
        try {
            $newStatus = $updatedLead['status'] ?? null;
            if ($oldStatus !== $newStatus) {
                LeadActivityRepository::recordStatusChange(
                    $id,
                    $oldStatus,
                    $newStatus,
                    $_SESSION['user_email'] ?? null,
                    $_SESSION['user_name']  ?? null,
                    leads_s($input['change_reason'] ?? '') ?: null
                );
            }
        } catch (\Throwable $e) {
            error_log('[leads-api update] status_change activity skipped: ' . $e->getMessage());
        }

        if (function_exists('auditUpdate')) {
            auditUpdate('leads', $id, 'リード更新: ' . $company);
        }
        if (function_exists('logInfo')) {
            logInfo('lead_updated', ['id' => $id]);
        }
        successResponse([], 'リードを更新しました');
    }

    // ---- タイムライン: 手動追加 ----
    if ($action === 'add_activity') {
        if (!hasPermission('sales')) errorResponse('権限がありません', 403);
        $leadId = leads_s($input['lead_id'] ?? '');
        $body   = leads_s($input['body'] ?? '');
        $type   = leads_s($input['type'] ?? 'manual_note');
        if ($leadId === '' || $body === '') errorResponse('lead_id と body は必須です', 400);
        if (!in_array($type, LeadActivityRepository::ALLOWED_TYPES, true)) {
            errorResponse('type が不正です', 400);
        }
        try {
            $newId = LeadActivityRepository::add([
                'lead_id'         => $leadId,
                'type'            => $type,
                'title'           => leads_s($input['title'] ?? '') ?: null,
                'body'            => $body,
                'occurred_at'     => leads_s($input['occurred_at'] ?? '') ?: null,
                'created_by'      => $_SESSION['user_email'] ?? null,
                'created_by_name' => $_SESSION['user_name']  ?? null,
            ]);
            successResponse(['id' => $newId], 'タイムラインに追加しました');
        } catch (\Throwable $e) {
            errorResponse('追加に失敗: ' . $e->getMessage(), 500);
        }
    }

    // ---- タイムライン: 論理削除 ----
    if ($action === 'delete_activity') {
        if (!canDelete()) errorResponse('権限がありません (管理部のみ)', 403);
        $aid = (int)($input['id'] ?? 0);
        if ($aid <= 0) errorResponse('id が必要です', 400);
        try {
            LeadActivityRepository::delete($aid, $_SESSION['user_email'] ?? null);
            successResponse([], '削除しました');
        } catch (\Throwable $e) {
            errorResponse('削除に失敗: ' . $e->getMessage(), 500);
        }
    }

    // ---- 削除（論理） ----
    if ($action === 'delete') {
        if (!canDelete()) errorResponse('権限がありません', 403);

        $id = leads_s($input['id'] ?? '');
        if ($id === '') errorResponse('id が必要です', 400);

        $data  = getData();
        $found = false;
        $deletedLead = null;
        foreach ($data['leads'] as &$l) {
            if (($l['id'] ?? '') === $id && empty($l['deleted_at'])) {
                $l['deleted_at'] = date('Y-m-d H:i:s');
                $l['deleted_by'] = $_SESSION['user_email'] ?? '';
                $found = true;
                $deletedLead = $l;
                break;
            }
        }
        unset($l);

        if (!$found) errorResponse('リードが見つかりません', 404);
        // 同時編集衝突防止: 単一行 UPSERT
        saveEntityRow('leads', $deletedLead);

        if (function_exists('auditDelete')) {
            auditDelete('leads', $id, 'リード削除');
        }
        if (function_exists('logInfo')) {
            logInfo('lead_deleted', ['id' => $id]);
        }
        successResponse([], 'リードを削除しました');
    }

    // ─── 名刺 (Phase 2) ──────────────────────────────────────────
    if ($action === 'create_card') {
        if (!hasPermission('sales')) errorResponse('権限がありません', 403);
        $company = leads_s($input['company_name'] ?? '');
        if ($company === '') errorResponse('会社名は必須です', 400);

        // OCR スキャン経由の場合は image_data_url が渡される。
        // uploads/business-cards/yyyymm/ に保存して相対パスを得る。
        $imagePath = '';
        if (!empty($input['image_data_url'])) {
            $imagePath = leads_save_data_url($input['image_data_url']);
        }
        // ocr_source: スキャン由来か手動か (デフォルト manual)
        $ocrSource = $imagePath ? 'scan' : (leads_s($input['ocr_source'] ?? '') ?: 'manual');

        try {
            $cardId = BusinessCardRepository::create([
                'company_name'             => $company,
                'person_name'              => leads_s($input['person_name']  ?? ''),
                'title'                    => leads_s($input['title']        ?? ''),
                'department'               => leads_s($input['department']   ?? ''),
                'phone'                    => leads_s($input['phone']        ?? ''),
                'mobile'                   => leads_s($input['mobile']       ?? ''),
                'email'                    => leads_s($input['email']        ?? ''),
                'fax'                      => leads_s($input['fax']          ?? ''),
                'website'                  => leads_s($input['website']      ?? ''),
                'address'                  => leads_s($input['address']      ?? ''),
                'exchanged_at'             => leads_s($input['exchanged_at'] ?? '') ?: null,
                'ocr_source'               => $ocrSource,
                'business_card_image_path' => $imagePath ?: null,
                'registered_by'            => $_SESSION['user_email'] ?? null,
                'notes'                    => leads_s($input['notes']        ?? ''),
            ]);
            successResponse(['id' => $cardId], $imagePath ? '名刺をスキャンして登録しました' : '名刺を登録しました');
        } catch (\Throwable $e) {
            errorResponse('登録失敗: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'update_card') {
        if (!hasPermission('sales')) errorResponse('権限がありません', 403);
        $cardId  = leads_s($input['id']           ?? '');
        $company = leads_s($input['company_name'] ?? '');
        if ($cardId === '' || $company === '') errorResponse('id と会社名は必須です', 400);
        try {
            BusinessCardRepository::update($cardId, [
                'company_name' => $company,
                'person_name'  => leads_s($input['person_name']  ?? ''),
                'title'        => leads_s($input['title']        ?? ''),
                'department'   => leads_s($input['department']   ?? ''),
                'phone'        => leads_s($input['phone']        ?? ''),
                'mobile'       => leads_s($input['mobile']       ?? ''),
                'email'        => leads_s($input['email']        ?? ''),
                'fax'          => leads_s($input['fax']          ?? ''),
                'website'      => leads_s($input['website']      ?? ''),
                'address'      => leads_s($input['address']      ?? ''),
                'exchanged_at' => leads_s($input['exchanged_at'] ?? '') ?: null,
                'notes'        => leads_s($input['notes']        ?? ''),
            ]);
            successResponse(['id' => $cardId], '名刺を更新しました');
        } catch (\Throwable $e) {
            errorResponse('更新失敗: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'delete_card') {
        if (!canDelete()) errorResponse('権限がありません (管理部のみ)', 403);
        $cardId = leads_s($input['id'] ?? '');
        if ($cardId === '') errorResponse('id が必要です', 400);
        try {
            BusinessCardRepository::delete($cardId, $_SESSION['user_email'] ?? null);
            successResponse([], '名刺を削除しました');
        } catch (\Throwable $e) {
            errorResponse('削除失敗: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'promote_card_to_lead') {
        if (!hasPermission('sales')) errorResponse('権限がありません', 403);
        $cardId = leads_s($input['id'] ?? '');
        if ($cardId === '') errorResponse('id が必要です', 400);
        // overrides は省略可。指定があれば leads 作成時の初期値を上書き。
        $overrides = [];
        foreach (['company_name','person_name','title','department','phone','mobile','email','website','address','status','am','notes'] as $k) {
            if (isset($input[$k]) && $input[$k] !== '') $overrides[$k] = leads_s($input[$k]);
        }
        try {
            $leadId = BusinessCardRepository::promoteToLead(
                $cardId,
                $overrides,
                $_SESSION['user_email'] ?? null,
                $_SESSION['user_name']  ?? null
            );
            successResponse(['lead_id' => $leadId], 'リードに昇格しました');
        } catch (\Throwable $e) {
            errorResponse('昇格失敗: ' . $e->getMessage(), 500);
        }
    }

    errorResponse('不明なアクションです', 400);
}

errorResponse('無効なリクエストです', 405);
