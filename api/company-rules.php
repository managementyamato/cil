<?php
/**
 * 社内規則 CRUD API
 *
 * GET    action=list          → 全章一覧（全ユーザー）
 * GET    action=get&id=xxx    → 1章取得（全ユーザー）
 * POST   action=save          → 章の新規作成 or 更新（admin のみ）
 * POST   action=delete        → 論理削除（admin のみ）
 */
require_once '../config/config.php';
require_once '../functions/api-middleware.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['GET', 'POST'],
]);

$action = $_GET['action'] ?? ($_POST['action'] ?? (getJsonInput()['action'] ?? 'list'));

// ========== GET: 一覧取得 ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $data  = getData();
    $rules = $data['company_rules'] ?? [];

    // 削除済み除外 → 章番号昇順
    $items = array_values(array_filter($rules, fn($r) => empty($r['deleted_at'])));
    usort($items, fn($a, $b) => ($a['chapter_number'] ?? 0) <=> ($b['chapter_number'] ?? 0));

    successResponse($items);
}

// ========== GET: 1章取得 ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    $id   = sanitizeInput($_GET['id'] ?? '', 'string');
    $data = getData();

    $found = null;
    foreach ($data['company_rules'] ?? [] as $r) {
        if ($r['id'] === $id && empty($r['deleted_at'])) {
            $found = $r;
            break;
        }
    }

    if (!$found) errorResponse('章が見つかりません', 404);
    successResponse($found);
}

// ========== POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $action = $action ?: ($input['action'] ?? '');

    // 以降は admin 専用
    if (!isAdmin()) {
        errorResponse('管理者権限が必要です', 403);
    }

    // --- 保存（新規作成 or 更新） ---
    if ($action === 'save') {
        requireParams($input, ['chapter_number', 'chapter_title']);

        $chapterNumber = (int)($input['chapter_number'] ?? 0);
        if ($chapterNumber < 1 || $chapterNumber > 12) {
            errorResponse('章番号は1〜12の範囲で指定してください', 400);
        }

        $chapterTitle = mb_substr(sanitizeInput($input['chapter_title'], 'string'), 0, 50);
        $content      = mb_substr(sanitizeInput($input['content'] ?? '', 'string'), 0, 100000);
        $id           = sanitizeInput($input['id'] ?? '', 'string');

        $data  = getData();
        if (!isset($data['company_rules'])) $data['company_rules'] = [];

        $now  = date('Y-m-d H:i:s');
        $found = false;

        // 更新
        if ($id !== '') {
            foreach ($data['company_rules'] as &$r) {
                if ($r['id'] !== $id || !empty($r['deleted_at'])) continue;
                $r['chapter_number'] = $chapterNumber;
                $r['chapter_title']  = $chapterTitle;
                $r['content']        = $content;
                $r['updated_at']     = $now;
                $found       = true;
                $savedItem   = $r;
                break;
            }
            unset($r);
            if (!$found) errorResponse('章が見つかりません', 404);
        } else {
            // 同じ章番号が既に存在する場合は上書き更新
            foreach ($data['company_rules'] as &$r) {
                if ($r['chapter_number'] === $chapterNumber && empty($r['deleted_at'])) {
                    $r['chapter_title'] = $chapterTitle;
                    $r['content']       = $content;
                    $r['updated_at']    = $now;
                    $found     = true;
                    $savedItem = $r;
                    break;
                }
            }
            unset($r);

            // 新規作成
            if (!$found) {
                $savedItem = [
                    'id'             => uniqid('rule_'),
                    'chapter_number' => $chapterNumber,
                    'chapter_title'  => $chapterTitle,
                    'content'        => $content,
                    'created_by'     => $_SESSION['user_email'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                    'deleted_at'     => null,
                    'deleted_by'     => null,
                ];
                $data['company_rules'][] = $savedItem;
            }
        }

        saveData($data);
        writeAuditLog('save', 'company_rules', '社内規則保存: 第' . $chapterNumber . '章', $savedItem);
        successResponse($savedItem, '第' . $chapterNumber . '章を保存しました');
    }

    // --- 削除（論理削除） ---
    if ($action === 'delete') {
        requireParams($input, ['id']);

        $id   = sanitizeInput($input['id'], 'string');
        $data = getData();
        $found = false;

        foreach ($data['company_rules'] as &$r) {
            if ($r['id'] !== $id || !empty($r['deleted_at'])) continue;
            $deletedItem       = $r;
            $r['deleted_at']   = date('Y-m-d H:i:s');
            $r['deleted_by']   = $_SESSION['user_email'];
            $found = true;
            break;
        }
        unset($r);

        if (!$found) errorResponse('章が見つかりません', 404);

        saveData($data);
        writeAuditLog('delete', 'company_rules', '社内規則削除: 第' . ($deletedItem['chapter_number'] ?? '') . '章', $deletedItem);
        successResponse(null, '章を削除しました');
    }

    errorResponse('不明なアクションです', 400);
}
