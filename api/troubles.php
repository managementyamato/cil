<?php
/**
 * トラブル対応 書き込みAPI
 * pages/troubles.php のPOSTハンドラを切り出したもの
 */
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

switch ($action) {

    // ─────────────────────────────────────────
    // 一括削除（管理者のみ）
    // ─────────────────────────────────────────
    case 'bulk_delete':
        if (!isAdmin()) errorResponse('権限がありません', 403);

        $ids = $_POST['trouble_ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            errorResponse('削除対象が指定されていません', 400);
        }

        $deleted = 0;
        $deletedIds = [];
        foreach ($ids as $tid) {
            $deletedItem = softDelete($data['troubles'], $tid);
            if ($deletedItem) {
                $deleted++;
                $deletedIds[] = $tid;
            }
        }

        if ($deleted > 0) {
            try {
                saveData($data);
                writeAuditLog('bulk_delete', 'trouble', "トラブル一括削除: {$deleted}件", [
                    'deleted_ids' => $deletedIds
                ]);
            } catch (Exception $e) {
                errorResponse('データの保存に失敗しました', 500);
            }
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
            }
        }
        unset($trouble);

        try {
            saveData($data);
            writeAuditLog('bulk_update', 'trouble', "トラブル一括変更: {$changed}件", [
                'ids'           => $ids,
                'new_status'    => ($newStatus !== '__no_change__') ? $newStatus : null,
                'new_responder' => ($newResponder !== '__no_change__') ? $newResponder : null,
            ]);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
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
        foreach ($data['troubles'] as &$trouble) {
            if ($trouble['id'] == $troubleId) {
                $trouble['responder']   = $newResponder;
                $trouble['updated_at']  = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($trouble);

        if (!$found) errorResponse('対象データが見つかりません', 404);

        try {
            saveData($data);
            writeAuditLog('update', 'trouble', "トラブル対応者変更: ID {$troubleId} → {$newResponder}");
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
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
        foreach ($data['troubles'] as &$trouble) {
            if ($trouble['id'] == $troubleId) {
                $oldStatus          = $trouble['status'] ?? '';
                $trouble['status']  = $newStatus;
                $trouble['updated_at'] = date('Y-m-d H:i:s');
                if ($oldStatus !== $newStatus) {
                    notifyStatusChange($trouble, $oldStatus, $newStatus);
                }
                $found = true;
                break;
            }
        }
        unset($trouble);

        if (!$found) errorResponse('対象データが見つかりません', 404);

        try {
            saveData($data);
            writeAuditLog('update', 'trouble', "トラブルステータス変更: ID {$troubleId} {$oldStatus}→{$newStatus}");
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
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
                break;
            }
        }
        unset($trouble);

        if (!$found) errorResponse('対象データが見つかりません', 404);

        try {
            saveData($data);
            writeAuditLog('update', 'trouble', "トラブル編集（モーダル）: ID {$troubleId}");
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        successResponse(['id' => $troubleId], '更新しました');
        break;

    default:
        errorResponse('不正なアクションです', 400);
}
