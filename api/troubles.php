<?php
/**
 * トラブル対応 書き込みAPI
 * pages/troubles.php のPOSTハンドラを切り出したもの
 * cache-bust: 2026-05-14-16:15
 */

// OPcache を強制リフレッシュ (このファイルの停滞対策)
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/notification-functions.php';
require_once __DIR__ . '/../functions/validation.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['POST']
]);

// トラブルステータス定義（troubles.php と同じ）
$TROUBLE_STATUSES = ['未対応', '対応中', '保留', '完了'];

$data = getData();
$action = $_POST['action'] ?? '';

// 一時診断: アクション名が想定通りか確認
if (!empty($_POST['_diag'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'action_raw'  => $_POST['action'] ?? null,
        'action_hex'  => bin2hex($_POST['action'] ?? ''),
        'action_len'  => strlen($_POST['action'] ?? ''),
        'post_keys'   => array_keys($_POST),
        'content_type'=> $_SERVER['CONTENT_TYPE'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {

    // ─────────────────────────────────────────
    // 一時マイグレーション: 全テーブルに deleted_at / deleted_by を追加
    // ─────────────────────────────────────────
    case 'migrate_softdelete':
    case '_migrate_softdelete':
        if (!isAdmin()) errorResponse('権限がありません', 403);
        $targetTables = [
            'projects','troubles','customers','partners','employees',
            'manufacturers','invoices','mf_invoices','loans','repayments',
            'tasks','announcements','memos','invoice_requests','leads',
            'weekly_reports','discount_approvals'
        ];
        try {
            $pdo = Database::connect();
            $report = [];
            foreach ($targetTables as $tbl) {
                $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl))->fetch();
                if (!$exists) { $report[$tbl] = 'SKIP (table not exist)'; continue; }
                $cols = $pdo->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_COLUMN);
                $added = [];
                if (!in_array('deleted_at', $cols, true)) {
                    $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL");
                    $added[] = 'deleted_at';
                }
                if (!in_array('deleted_by', $cols, true)) {
                    $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `deleted_by` VARCHAR(255) DEFAULT NULL");
                    $added[] = 'deleted_by';
                }
                $report[$tbl] = $added ? ('ADDED: ' . implode(',', $added)) : 'OK';
            }
            successResponse(['report' => $report]);
        } catch (\Throwable $e) {
            errorResponse('migrate error: ' . $e->getMessage(), 500);
        }
        break;

    // ─────────────────────────────────────────
    // 一括削除（管理者のみ）
    // ─────────────────────────────────────────
    case 'bulk_delete':
        if (!isAdmin()) errorResponse('権限がありません', 403);

        $ids = $_POST['trouble_ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            errorResponse('削除対象が指定されていません', 400);
        }

        // softDelete + saveEntityRow を経由せず直接 SQL で論理削除する。
        // 理由: saveEntityRow は不足カラムを黙って捨てる仕様のため、本番に
        //       deleted_at カラムが無いと削除フラグが保存されない。ここでは
        //       カラム不足を検知したら自動 ALTER して必ず保存する。
        try {
            $pdo = Database::connect();

            // === 一時診断: 接続先 DB / 対象 id の実在を確認 ===
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $totalRows = $pdo->query("SELECT COUNT(*) FROM `troubles`")->fetchColumn();
            $sentId = $ids[0] ?? '';
            $checkStmt = $pdo->prepare("SELECT id, deleted_at, LENGTH(id) AS len FROM `troubles` WHERE id = ? LIMIT 1");
            $checkStmt->execute([(string)$sentId]);
            $exactRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $likeStmt = $pdo->prepare("SELECT id, LENGTH(id) AS len FROM `troubles` WHERE id LIKE ? LIMIT 5");
            $likeStmt->execute(['%' . $sentId . '%']);
            $likeRows = $likeStmt->fetchAll(PDO::FETCH_ASSOC);

            successResponse([
                'diag' => [
                    'connected_db'    => $dbName,
                    'troubles_count'  => $totalRows,
                    'sent_id'         => $sentId,
                    'exact_match'     => $exactRow,
                    'like_match'      => $likeRows,
                    'recent_5_ids'    => $pdo->query("SELECT id, created_at FROM troubles ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC),
                ]
            ], 'DIAGNOSTIC ONLY');
            exit;
            // ↑ 診断モード（実際の削除は実行しない）

            // 1) deleted_at / deleted_by が無ければ自動補完
            $cols = $pdo->query("SHOW COLUMNS FROM `troubles`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('deleted_at', $cols, true)) {
                $pdo->exec("ALTER TABLE `troubles` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL");
            }
            if (!in_array('deleted_by', $cols, true)) {
                $pdo->exec("ALTER TABLE `troubles` ADD COLUMN `deleted_by` VARCHAR(255) DEFAULT NULL");
            }

            // 2) UPDATE で論理削除（既に削除済みの行は対象外）
            $now = date('Y-m-d H:i:s');
            $by  = $_SESSION['user_email'] ?? 'system';
            $stmt = $pdo->prepare(
                "UPDATE `troubles` SET `deleted_at` = ?, `deleted_by` = ? "
              . "WHERE `id` = ? AND (`deleted_at` IS NULL OR `deleted_at` = '')"
            );
            $deleted = 0;
            $deletedIds = [];
            $diag = [];
            foreach ($ids as $tid) {
                $stmt->execute([$now, $by, (string)$tid]);
                $rc = $stmt->rowCount();
                if ($rc > 0) {
                    $deleted++;
                    $deletedIds[] = $tid;
                } else {
                    // 一致しなかった場合、DB に該当 id が存在するか診断
                    $checkStmt = $pdo->prepare("SELECT `id`, `deleted_at` FROM `troubles` WHERE `id` = ? LIMIT 1");
                    $checkStmt->execute([(string)$tid]);
                    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    $diag[] = [
                        'sent_id'  => $tid,
                        'sent_id_type' => gettype($tid),
                        'db_match' => $row ?: null,
                    ];
                }
            }

            // サンプル: 実際の troubles の id 形式を 5 件取得
            $sampleIds = $pdo->query("SELECT `id` FROM `troubles` ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);

            writeAuditLog('bulk_delete', 'trouble', "トラブル一括削除: {$deleted}件", [
                'deleted_ids' => $deletedIds
            ]);

            if ($deleted === 0 && !empty($diag)) {
                // 一件も削除できなかったら診断情報も返す
                successResponse([
                    'deleted'    => 0,
                    'diag'       => $diag,
                    'sample_ids' => $sampleIds,
                ], '0件を削除しました（診断付き）');
                exit;
            }
        } catch (\Throwable $e) {
            error_log('[troubles.bulk_delete] direct SQL failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            errorResponse('削除に失敗しました: ' . $e->getMessage(), 500);
        }

        successResponse(['deleted' => $deleted], "{$deleted}件を削除しました");
        break;

    // ─────────────────────────────────────────
    // 一括変更（編集権限が必要）
    // ─────────────────────────────────────────
    case 'bulk_change':
        if (!canEdit()) errorResponse('権限がありません', 403);

        $ids = $_POST['trouble_ids'] ?? [];
        $newResponder = $_POST['bulk_responder'] ?? null;
        $newStatus    = $_POST['bulk_status'] ?? null;

        if (empty($ids) || !is_array($ids)) {
            errorResponse('変更対象が指定されていません', 400);
        }

        $changed = 0;
        $changedRows = [];
        foreach ($data['troubles'] as &$trouble) {
            if (in_array($trouble['id'], $ids)) {
                if ($newResponder !== null && $newResponder !== '__no_change__') {
                    $trouble['responder'] = $newResponder;
                }
                if ($newStatus !== null && $newStatus !== '__no_change__' && in_array($newStatus, $TROUBLE_STATUSES)) {
                    $oldStatus = $trouble['status'] ?? '';
                    if ($oldStatus !== $newStatus) {
                        $trouble['status'] = $newStatus;
                        notifyStatusChange($trouble, $oldStatus, $newStatus);
                    }
                }
                $trouble['updated_at'] = date('Y-m-d H:i:s');
                $changed++;
                $changedRows[] = $trouble;
            }
        }
        unset($trouble);

        try {
            // 同時編集衝突防止: 各行を UPSERT で個別保存
            foreach ($changedRows as $row) {
                saveEntityRow('troubles', $row);
            }
            writeAuditLog('bulk_update', 'trouble', "トラブル一括変更: {$changed}件", [
                'ids'           => $ids,
                'new_status'    => ($newStatus !== '__no_change__') ? $newStatus : null,
                'new_responder' => ($newResponder !== '__no_change__') ? $newResponder : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[troubles.bulk_change] saveEntityRow failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            errorResponse('データの保存に失敗しました: ' . $e->getMessage(), 500);
        }

        successResponse(['changed' => $changed], "{$changed}件を変更しました");
        break;

    // ─────────────────────────────────────────
    // 対応者変更（編集権限が必要）
    // ─────────────────────────────────────────
    case 'change_responder':
        if (!canEdit()) errorResponse('権限がありません', 403);

        $troubleId    = (int)($_POST['trouble_id'] ?? 0);
        $newResponder = trim($_POST['new_responder'] ?? '');

        if (!$troubleId) errorResponse('トラブルIDが不正です', 400);

        $found = false;
        $changedTrouble = null;
        foreach ($data['troubles'] as &$trouble) {
            if ($trouble['id'] == $troubleId) {
                $trouble['responder']   = $newResponder;
                $trouble['updated_at']  = date('Y-m-d H:i:s');
                $found = true;
                $changedTrouble = $trouble;
                break;
            }
        }
        unset($trouble);

        if (!$found) errorResponse('対象データが見つかりません', 404);

        try {
            // 同時編集衝突防止: 単一行 UPSERT
            saveEntityRow('troubles', $changedTrouble);
            writeAuditLog('update', 'trouble', "トラブル対応者変更: ID {$troubleId} → {$newResponder}");
        } catch (\Throwable $e) {
            error_log('[troubles.change_responder] saveEntityRow failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            errorResponse('データの保存に失敗しました: ' . $e->getMessage(), 500);
        }

        successResponse(['id' => $troubleId, 'responder' => $newResponder], '対応者を変更しました');
        break;

    // ─────────────────────────────────────────
    // ステータス変更（編集権限が必要）
    // ─────────────────────────────────────────
    case 'change_status':
        if (!canEdit()) errorResponse('権限がありません', 403);

        $troubleId = (int)($_POST['trouble_id'] ?? 0);
        $newStatus  = $_POST['new_status'] ?? '';

        if (!$troubleId) errorResponse('トラブルIDが不正です', 400);
        if (!in_array($newStatus, $TROUBLE_STATUSES)) errorResponse('ステータスが不正です', 400);

        $found    = false;
        $oldStatus = '';
        $changedTrouble = null;
        foreach ($data['troubles'] as &$trouble) {
            if ($trouble['id'] == $troubleId) {
                $oldStatus          = $trouble['status'] ?? '';
                $trouble['status']  = $newStatus;
                $trouble['updated_at'] = date('Y-m-d H:i:s');
                if ($oldStatus !== $newStatus) {
                    notifyStatusChange($trouble, $oldStatus, $newStatus);
                }
                $found = true;
                $changedTrouble = $trouble;
                break;
            }
        }
        unset($trouble);

        if (!$found) errorResponse('対象データが見つかりません', 404);

        try {
            // 同時編集衝突防止: 単一行 UPSERT
            saveEntityRow('troubles', $changedTrouble);
            writeAuditLog('update', 'trouble', "トラブルステータス変更: ID {$troubleId} {$oldStatus}→{$newStatus}");
        } catch (\Throwable $e) {
            error_log('[troubles.change_status] saveEntityRow failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            errorResponse('データの保存に失敗しました: ' . $e->getMessage(), 500);
        }

        successResponse(['id' => $troubleId, 'status' => $newStatus], 'ステータスを変更しました');
        break;

    // ─────────────────────────────────────────
    // モーダル編集（編集権限が必要）
    // ─────────────────────────────────────────
    case 'modal_edit':
        if (!canEdit()) errorResponse('権限がありません', 403);

        $troubleId = (int)($_POST['edit_id'] ?? 0);
        $newStatus  = $_POST['edit_status'] ?? '';

        if (!$troubleId) errorResponse('トラブルIDが不正です', 400);
        if (!in_array($newStatus, $TROUBLE_STATUSES)) errorResponse('ステータスが不正です', 400);

        // 入力取得
        $date            = str_replace('/', '-', trim($_POST['edit_date'] ?? ''));
        $deadline        = trim($_POST['edit_deadline'] ?? '');
        $troubleContent  = $_POST['edit_trouble_content'] ?? '';
        $responseContent = $_POST['edit_response_content'] ?? '';
        if (!empty($deadline)) {
            $deadline = str_replace('/', '-', $deadline);
        }

        // バリデーション
        $errors = [];
        if (!validateDate($date)) {
            $errors[] = '日付の形式が正しくありません（YYYY-MM-DD形式で入力してください）';
        }
        if (!empty($deadline) && !validateDate($deadline)) {
            $errors[] = '対応期限の形式が正しくありません（YYYY-MM-DD形式で入力してください）';
        }
        if (mb_strlen($troubleContent) > 5000) {
            $errors[] = 'トラブル内容は5000文字以内で入力してください';
        }
        if (mb_strlen($responseContent) > 5000) {
            $errors[] = '対応内容は5000文字以内で入力してください';
        }
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error'   => implode(' / ', $errors),
                'errors'  => $errors
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $found    = false;
        $oldStatus = '';
        $changedTrouble = null;
        foreach ($data['troubles'] as &$trouble) {
            if ($trouble['id'] == $troubleId) {
                $oldStatus = $trouble['status'] ?? '';

                $trouble['date']              = sanitizeInput($date, 'string');
                $trouble['deadline']          = sanitizeInput($deadline, 'string');
                $trouble['call_no']           = sanitizeInput($_POST['edit_call_no'] ?? '', 'string');
                $trouble['pj_number']         = sanitizeInput($_POST['edit_pj_number'] ?? '', 'string');
                $trouble['trouble_content']   = sanitizeInput($troubleContent, 'string');
                $trouble['response_content']  = sanitizeInput($responseContent, 'string');
                $trouble['prevention_notes']  = sanitizeInput($_POST['edit_prevention_notes'] ?? '', 'string');
                $trouble['reporter']          = sanitizeInput($_POST['edit_reporter'] ?? '', 'string');
                $trouble['responder']         = sanitizeInput($_POST['edit_responder'] ?? '', 'string');
                $trouble['status']            = $newStatus;
                $trouble['case_no']           = sanitizeInput($_POST['edit_case_no'] ?? '', 'string');
                $trouble['company_name']      = sanitizeInput($_POST['edit_company_name'] ?? '', 'string');
                $trouble['customer_name']     = sanitizeInput($_POST['edit_customer_name'] ?? '', 'string');
                $trouble['updated_at']        = date('Y-m-d H:i:s');

                if ($oldStatus !== $newStatus) {
                    notifyStatusChange($trouble, $oldStatus, $newStatus);
                }
                $found = true;
                $changedTrouble = $trouble;
                break;
            }
        }
        unset($trouble);

        if (!$found) errorResponse('対象データが見つかりません', 404);

        try {
            // 同時編集衝突防止: 単一行 UPSERT
            saveEntityRow('troubles', $changedTrouble);
            writeAuditLog('update', 'trouble', "トラブル編集（モーダル）: ID {$troubleId}");
        } catch (\Throwable $e) {
            error_log('[troubles.modal_edit] saveEntityRow failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            errorResponse('データの保存に失敗しました: ' . $e->getMessage(), 500);
        }

        successResponse(['id' => $troubleId], '更新しました');
        break;

    // ─────────────────────────────────────────
    // モーダル新規登録（編集権限が必要）
    // ─────────────────────────────────────────
    case 'modal_create':
        if (!canEdit()) errorResponse('権限がありません', 403);

        $newStatus = $_POST['create_status'] ?? '未対応';
        if (!in_array($newStatus, $TROUBLE_STATUSES)) errorResponse('ステータスが不正です', 400);

        // 入力取得
        $date            = str_replace('/', '-', trim($_POST['create_date'] ?? ''));
        $deadline        = trim($_POST['create_deadline'] ?? '');
        $troubleContent  = $_POST['create_trouble_content'] ?? '';
        $responseContent = $_POST['create_response_content'] ?? '';
        if (!empty($deadline)) {
            $deadline = str_replace('/', '-', $deadline);
        }

        // バリデーション
        $errors = [];
        if (empty($date) || !validateDate($date)) {
            $errors[] = '日付の形式が正しくありません（YYYY-MM-DD形式で入力してください）';
        }
        if (empty(trim($troubleContent))) {
            $errors[] = 'トラブル内容を入力してください';
        }
        if (!empty($deadline) && !validateDate($deadline)) {
            $errors[] = '対応期限の形式が正しくありません（YYYY-MM-DD形式で入力してください）';
        }
        if (mb_strlen($troubleContent) > 5000) {
            $errors[] = 'トラブル内容は5000文字以内で入力してください';
        }
        if (mb_strlen($responseContent) > 5000) {
            $errors[] = '対応内容は5000文字以内で入力してください';
        }
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error'   => implode(' / ', $errors),
                'errors'  => $errors
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 新規 ID 生成
        $pdo = Database::connect();
        $maxId = $pdo->query("SELECT COALESCE(MAX(CAST(id AS UNSIGNED)), 0) FROM troubles")->fetchColumn();
        $newId = (int)$maxId + 1;

        $now = date('Y-m-d H:i:s');
        $newTrouble = [
            'id'                => (string)$newId,
            'date'              => sanitizeInput($date, 'string'),
            'deadline'          => sanitizeInput($deadline, 'string'),
            'call_no'           => sanitizeInput($_POST['create_call_no'] ?? '', 'string'),
            'pj_number'         => sanitizeInput($_POST['create_pj_number'] ?? '', 'string'),
            'trouble_content'   => sanitizeInput($troubleContent, 'string'),
            'response_content'  => sanitizeInput($responseContent, 'string'),
            'prevention_notes'  => sanitizeInput($_POST['create_prevention_notes'] ?? '', 'string'),
            'reporter'          => sanitizeInput($_POST['create_reporter'] ?? '', 'string'),
            'responder'         => sanitizeInput($_POST['create_responder'] ?? '', 'string'),
            'status'            => $newStatus,
            'case_no'           => sanitizeInput($_POST['create_case_no'] ?? '', 'string'),
            'company_name'      => sanitizeInput($_POST['create_company_name'] ?? '', 'string'),
            'customer_name'     => sanitizeInput($_POST['create_customer_name'] ?? '', 'string'),
            'honorific'         => '様',
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        try {
            saveEntityRow('troubles', $newTrouble);
            writeAuditLog('create', 'trouble', "トラブル新規登録（モーダル）: ID {$newId}");
        } catch (\Throwable $e) {
            error_log('[troubles.modal_create] saveEntityRow failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            errorResponse('データの保存に失敗しました: ' . $e->getMessage(), 500);
        }

        successResponse(['id' => $newId], '登録しました');
        break;

    default:
        errorResponse('不正なアクションです', 400);
}
