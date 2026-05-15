<?php
/**
 * 顧客ランク定義 CRUD API
 *
 * - 価格表の顧客ランク（A/B/C/D 層）をアプリ内で直接編集する。
 * - 既存の Google 同期データ（data/product-prices.json の「顧客定義」）が
 *   data.json の customer_ranks に未投入の場合、初回 list 時に自動シード。
 *
 * GET  ?action=list           ランク一覧（sort_order昇順）
 * POST action=upsert          作成・更新（id 指定で更新、未指定で作成）
 * POST action=delete          削除（admin のみ）
 * POST action=reseed          価格表から再投入（admin、既存はクリア）
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/sales-master.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'rateLimit'   => false,
]);

if (!hasPermission('sales')) errorResponse('権限がありません', 403);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/**
 * 同期済み価格表データから「顧客定義」をパースして customer_ranks 構造を返す。
 * @return array<int, array>  ランク行の配列
 */
function cr_parse_from_pricelist(): array {
    $pl = loadProductPrices();
    if (!$pl) return [];
    $sheet = null;
    foreach (($pl['sheets'] ?? []) as $s) {
        if (($s['title'] ?? '') === '顧客定義') { $sheet = $s; break; }
    }
    if (!$sheet) return [];

    $ranks = [];
    $order = 10;
    foreach (($sheet['values'] ?? []) as $row) {
        if (!is_array($row) || count($row) < 2) continue;
        $rankCell = (string)($row[1] ?? '');
        if (!preg_match('/([A-Z])層/u', $rankCell, $m)) continue;
        $rank      = $m[1];
        $dealType  = trim((string)($row[0] ?? ''));
        $condition = trim((string)($row[2] ?? ''));
        $companies = [];
        $notes     = [];
        for ($i = 3; $i < count($row); $i++) {
            $cell = trim((string)($row[$i] ?? ''));
            if ($cell === '') continue;
            foreach (preg_split('/[\r\n]+/u', $cell) as $part) {
                $p = trim($part);
                if ($p === '') continue;
                if (mb_substr($p, 0, 1) === '※') {
                    $notes[] = $p;
                } else {
                    $companies[] = $p;
                }
            }
        }
        $ranks[] = [
            'id'         => 'cr_' . uniqid('', true),
            'rank'       => $rank,
            'deal_type'  => $dealType,
            'condition'  => $condition,
            'companies'  => $companies,
            'note'       => implode("\n", $notes),
            'sort_order' => $order,
            'created_by' => 'seed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $order += 10;
    }
    return $ranks;
}

/** id を発番 */
function cr_new_id(): string {
    return 'cr_' . uniqid('', true);
}

/** 1行を正規化（バリデーション + 既定値） */
function cr_normalize(array $in, array $existing = []): array {
    $rank = strtoupper(trim((string)($in['rank'] ?? '')));
    if (!preg_match('/^[A-Z]$/', $rank)) {
        // 「A層」「a」等の入力を吸収
        if (preg_match('/([A-Za-z])/', (string)($in['rank'] ?? ''), $m)) {
            $rank = strtoupper($m[1]);
        } else {
            $rank = 'A';
        }
    }
    $companies = $in['companies'] ?? ($existing['companies'] ?? []);
    if (!is_array($companies)) $companies = [];
    $companies = array_values(array_filter(array_map(function($c){
        return is_string($c) ? trim($c) : '';
    }, $companies), fn($v) => $v !== ''));

    return array_replace($existing, [
        'rank'       => $rank,
        'deal_type'  => trim((string)($in['deal_type']  ?? ($existing['deal_type']  ?? ''))),
        'condition'  => trim((string)($in['condition']  ?? ($existing['condition']  ?? ''))),
        'companies'  => $companies,
        'note'       => trim((string)($in['note']       ?? ($existing['note']       ?? ''))),
        'sort_order' => isset($in['sort_order']) ? (int)$in['sort_order'] : ($existing['sort_order'] ?? 9999),
    ]);
}

// ===== GET: list（初回は価格表からシード） =====
if ($method === 'GET' && $action === 'list') {
    $data = getData();
    $ranks = $data['customer_ranks'] ?? [];

    if (empty($ranks)) {
        $seeded = cr_parse_from_pricelist();
        if (!empty($seeded)) {
            $data['customer_ranks'] = $seeded;
            saveData($data, ['customer_ranks']);
            $ranks = $seeded;
        }
    }

    usort($ranks, function($a, $b){
        $aR = $a['rank'] ?? '';
        $bR = $b['rank'] ?? '';
        if ($aR === $bR) return ($a['sort_order'] ?? 0) - ($b['sort_order'] ?? 0);
        return strcmp($aR, $bR);
    });

    successResponse([
        'ranks' => $ranks,
        'total' => count($ranks),
    ]);
}

// ===== POST: 編集系（CSRF必須） =====
if ($method === 'POST') {
    verifyCsrfToken();
    $input  = getJsonInput();
    $action = $input['action'] ?? $action;

    // ---- upsert（作成 or 更新） ----
    if ($action === 'upsert') {
        $id = trim((string)($input['id'] ?? ''));
        $data = getData();
        if (!isset($data['customer_ranks']) || !is_array($data['customer_ranks'])) {
            $data['customer_ranks'] = [];
        }

        if ($id === '') {
            // 新規
            $row = cr_normalize($input, []);
            $row['id']         = cr_new_id();
            $row['created_by'] = $_SESSION['user_email'] ?? '';
            $row['created_at'] = date('Y-m-d H:i:s');
            $row['updated_at'] = date('Y-m-d H:i:s');
            $data['customer_ranks'][] = $row;

            saveData($data, ['customer_ranks']);
            if (function_exists('auditCreate')) auditCreate('customer_ranks', $row['id'], 'ランク追加: ' . $row['rank'] . '層');
            successResponse(['rank' => $row], '追加しました');
        } else {
            // 更新
            $found = false;
            foreach ($data['customer_ranks'] as &$r) {
                if (($r['id'] ?? '') === $id) {
                    $r = cr_normalize($input, $r);
                    $r['id'] = $id;
                    $r['updated_at'] = date('Y-m-d H:i:s');
                    $found = $r;
                    break;
                }
            }
            unset($r);
            if (!$found) errorResponse('対象が見つかりません', 404);
            saveData($data, ['customer_ranks']);
            if (function_exists('auditUpdate')) auditUpdate('customer_ranks', $id, 'ランク更新: ' . $found['rank'] . '層');
            successResponse(['rank' => $found], '更新しました');
        }
    }

    // ---- 削除 ----
    if ($action === 'delete') {
        if (!canDelete()) errorResponse('削除は管理者のみ可能です', 403);
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') errorResponse('id が必要です', 400);

        $data = getData();
        $orig = $data['customer_ranks'] ?? [];
        $data['customer_ranks'] = array_values(array_filter($orig, function($r) use ($id){
            return ($r['id'] ?? '') !== $id;
        }));
        if (count($data['customer_ranks']) === count($orig)) {
            errorResponse('対象が見つかりません', 404);
        }
        saveData($data, ['customer_ranks']);
        if (function_exists('auditDelete')) auditDelete('customer_ranks', $id, 'ランク削除');
        successResponse([], '削除しました');
    }

    // ---- 価格表から再投入（admin） ----
    if ($action === 'reseed') {
        if (!isAdmin()) errorResponse('管理者のみ実行可能', 403);
        $seeded = cr_parse_from_pricelist();
        if (empty($seeded)) errorResponse('価格表データが見つかりません。先に Google から同期してください。', 400);

        $data = getData();
        $data['customer_ranks'] = $seeded;
        saveData($data, ['customer_ranks']);
        if (function_exists('auditUpdate')) auditUpdate('customer_ranks', 'reseed', '価格表から再投入: ' . count($seeded) . '件');
        successResponse(['count' => count($seeded)], '価格表から ' . count($seeded) . '件を再投入しました');
    }

    errorResponse('不明なアクションです', 400);
}

errorResponse('無効なリクエストです', 405);
