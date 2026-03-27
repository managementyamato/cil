<?php
/**
 * スライド（社内マニュアル）API
 *
 * GET    /api/slides.php?action=list        スライド一覧（確認状況付き）
 * POST   /api/slides.php  action=create     スライド登録（admin）
 * POST   /api/slides.php  action=update     スライド更新（admin）
 * POST   /api/slides.php  action=delete     スライド削除（admin）
 * POST   /api/slides.php  action=confirm    確認済み登録（全員）
 * GET    /api/slides.php?action=confirmations&slide_id=xxx  確認者一覧（admin）
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth'  => true,
    'requireCsrf'  => false, // GETは不要、POST内でチェック
    'rateLimit'    => false,
]);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ==============================
// GET: スライド一覧
// ==============================
if ($method === 'GET' && $action === 'list') {
    $data          = getData();
    $slides        = $data['slides'] ?? [];
    $confirmations = $data['slide_confirmations'] ?? [];
    $userEmail     = $_SESSION['user_email'];
    $userRole      = $_SESSION['user_role'];

    // 削除済み除外
    $slides = array_values(array_filter($slides, fn($s) => empty($s['deleted_at'])));

    // 自分の確認済みスライドID一覧
    $confirmedIds = [];
    foreach ($confirmations as $c) {
        if ($c['user_email'] === $userEmail) {
            $confirmedIds[$c['slide_id']] = $c['confirmed_at'];
        }
    }

    // 各スライドに confirmed / confirmed_at を付与
    foreach ($slides as &$slide) {
        $sid = $slide['id'];
        $slide['confirmed']    = isset($confirmedIds[$sid]);
        $slide['confirmed_at'] = $confirmedIds[$sid] ?? null;
        // 自分が対象かどうか
        $req = $slide['required_for'] ?? [];
        $slide['is_required_for_me'] = empty($req) || in_array($userRole, $req, true);
    }
    unset($slide);

    // 作成日時の降順にソート
    usort($slides, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

    successResponse(['slides' => $slides]);
}

// ==============================
// GET: 確認者一覧（admin）
// ==============================
if ($method === 'GET' && $action === 'confirmations') {
    if (!isAdmin()) {
        errorResponse('権限がありません', 403);
    }
    $slideId = $_GET['slide_id'] ?? '';
    if (!$slideId) errorResponse('slide_id が必要です', 400);

    $data          = getData();
    $confirmations = $data['slide_confirmations'] ?? [];
    $employees     = $data['employees'] ?? [];

    // このスライドの確認一覧
    $list = array_values(array_filter($confirmations, fn($c) => $c['slide_id'] === $slideId));

    // 従業員名をマップ
    $empMap = [];
    foreach ($employees as $e) {
        if (!empty($e['email'])) {
            $empMap[$e['email']] = $e['name'] ?? $e['email'];
        }
    }
    foreach ($list as &$c) {
        $c['user_name'] = $empMap[$c['user_email']] ?? $c['user_email'];
    }
    unset($c);

    successResponse(['confirmations' => $list]);
}

// ==============================
// POST系: CSRF必須
// ==============================
if ($method === 'POST') {
    verifyCsrfToken();
    $input = getJsonInput();
    $action = $input['action'] ?? $action;

    // ---- スライド登録（admin） ----
    if ($action === 'create') {
        if (!isAdmin()) errorResponse('権限がありません', 403);

        $title   = trim($input['title'] ?? '');
        $url     = trim($input['google_docs_url'] ?? '');
        if (!$title) errorResponse('タイトルは必須です', 400);
        if (!$url)   errorResponse('Google Docs URL は必須です', 400);

        // URLバリデーション（Google Docs のみ許可）
        if (!preg_match('#^https://docs\.google\.com/#', $url)) {
            errorResponse('Google Docs の URL を入力してください', 400);
        }

        $data = getData();
        $slide = [
            'id'             => 'slide_' . uniqid(),
            'title'          => $title,
            'google_docs_url'=> $url,
            'description'    => trim($input['description'] ?? ''),
            'required_for'   => $input['required_for'] ?? [],
            'due_date'       => $input['due_date'] ?? null,
            'created_by'     => $_SESSION['user_email'],
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
            'deleted_at'     => null,
            'deleted_by'     => null,
        ];
        $data['slides'][] = $slide;
        saveData($data);
        logInfo('slide_created', ['id' => $slide['id'], 'title' => $title]);
        successResponse(['slide' => $slide], 'スライドを登録しました');
    }

    // ---- スライド更新（admin） ----
    if ($action === 'update') {
        if (!isAdmin()) errorResponse('権限がありません', 403);

        $id    = $input['id'] ?? '';
        $title = trim($input['title'] ?? '');
        $url   = trim($input['google_docs_url'] ?? '');
        if (!$id)    errorResponse('id が必要です', 400);
        if (!$title) errorResponse('タイトルは必須です', 400);
        if (!$url)   errorResponse('Google Docs URL は必須です', 400);

        if (!preg_match('#^https://docs\.google\.com/#', $url)) {
            errorResponse('Google Docs の URL を入力してください', 400);
        }

        $data   = getData();
        $slides = &$data['slides'];
        $found  = false;
        foreach ($slides as &$s) {
            if ($s['id'] === $id && empty($s['deleted_at'])) {
                $s['title']           = $title;
                $s['google_docs_url'] = $url;
                $s['description']     = trim($input['description'] ?? '');
                $s['required_for']    = $input['required_for'] ?? [];
                $s['due_date']        = $input['due_date'] ?? null;
                $s['updated_at']      = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($s);
        if (!$found) errorResponse('スライドが見つかりません', 404);
        saveData($data);
        logInfo('slide_updated', ['id' => $id]);
        successResponse([], 'スライドを更新しました');
    }

    // ---- スライド削除（admin） ----
    if ($action === 'delete') {
        if (!canDelete()) errorResponse('権限がありません', 403);

        $id   = $input['id'] ?? '';
        if (!$id) errorResponse('id が必要です', 400);

        $data   = getData();
        $slides = &$data['slides'];
        $found  = false;
        foreach ($slides as &$s) {
            if ($s['id'] === $id && empty($s['deleted_at'])) {
                $s['deleted_at'] = date('Y-m-d H:i:s');
                $s['deleted_by'] = $_SESSION['user_email'];
                $found = true;
                break;
            }
        }
        unset($s);
        if (!$found) errorResponse('スライドが見つかりません', 404);
        saveData($data);
        logInfo('slide_deleted', ['id' => $id]);
        successResponse([], 'スライドを削除しました');
    }

    // ---- 確認済み登録（全員） ----
    if ($action === 'confirm') {
        $slideId   = $input['slide_id'] ?? '';
        $userEmail = $_SESSION['user_email'];
        if (!$slideId) errorResponse('slide_id が必要です', 400);

        $data = getData();

        // スライド存在チェック
        $slideExists = false;
        foreach ($data['slides'] ?? [] as $s) {
            if ($s['id'] === $slideId && empty($s['deleted_at'])) {
                $slideExists = true;
                break;
            }
        }
        if (!$slideExists) errorResponse('スライドが見つかりません', 404);

        // 二重確認チェック
        foreach ($data['slide_confirmations'] ?? [] as $c) {
            if ($c['slide_id'] === $slideId && $c['user_email'] === $userEmail) {
                successResponse([], '既に確認済みです');
            }
        }

        $confirmation = [
            'id'           => 'conf_' . uniqid(),
            'slide_id'     => $slideId,
            'user_email'   => $userEmail,
            'confirmed_at' => date('Y-m-d H:i:s'),
        ];
        $data['slide_confirmations'][] = $confirmation;
        saveData($data);
        logInfo('slide_confirmed', ['slide_id' => $slideId, 'user' => $userEmail]);
        successResponse(['confirmation' => $confirmation], '確認済みとして記録しました');
    }

    errorResponse('不明なアクションです', 400);
}

errorResponse('無効なリクエストです', 405);
