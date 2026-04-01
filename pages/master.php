<?php
require_once '../api/auth.php';
require_once '../functions/validation.php';
require_once '../functions/api-middleware.php';
// api-middleware.phpのエラーハンドラはAPIファイル専用のため、ページファイルではリセット
set_error_handler(null);
set_exception_handler(null);
$data = getData();

// 案件ステータス定義（一元管理 - ここだけを編集すれば全箇所に反映される）
$PROJECT_STATUSES = ['案件発生', '成約', '製品手配中', '設置予定', '設置済', '完了'];

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

/**
 * 次のP番号を自動生成
 * 既存のP番号から最大値を取得し、+1した番号を返す
 * 形式: P1, P2, P3, ...
 */
function generateNextPjNumber($projects) {
    $maxNumber = 0;

    foreach ($projects as $pj) {
        $id = $pj['id'] ?? '';
        // P + 数字 形式の場合（P1, P2, P123 など）
        if (preg_match('/^P(\d+)$/i', $id, $matches)) {
            $maxNumber = max($maxNumber, (int)$matches[1]);
        }
    }

    // 次の番号を生成（P + 連番形式）
    return 'P' . ($maxNumber + 1);
}

/**
 * P番号が既に存在するかチェック
 */
function pjNumberExists($projects, $pjNumber) {
    foreach ($projects as $pj) {
        if (($pj['id'] ?? '') === $pjNumber) {
            return true;
        }
    }
    return false;
}

/**
 * 保存時に使用する確定P番号を取得
 * 重複があれば自動的に次の番号を割り当て
 */
function getConfirmedPjNumber($projects, $requestedNumber) {
    // 入力値が空、または既に存在する場合は自動採番
    if (empty($requestedNumber) || pjNumberExists($projects, $requestedNumber)) {
        return generateNextPjNumber($projects);
    }
    return $requestedNumber;
}

/**
 * 現場名を一覧表示用に整形
 * ・【〇】形式のプレフィックスを除去（例: 【レ】【売】【販】【レ終】など）
 * ・アンダーバー以降を除去
 * 例: "【レ】千葉_三菱マテリアル" → "千葉"
 */
function trimSiteName($name) {
    // 【〇】形式のプレフィックスを全て除去（複数連続も対応）
    // \x{3010}=【 \x{3011}=】 Unicode指定でエンコーディング問題を回避
    $name = preg_replace('/^(\x{3010}[^\x{3011}]*\x{3011})+/u', '', $name);
    $name = trim($name);
    // アンダーバー以降を除去
    $pos = strpos($name, '_');
    return $pos !== false ? substr($name, 0, $pos) : $name;
}

$message = '';
$messageType = '';

// 表示モード（テーブルのみ）
$viewMode = 'table';
// ソート
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'id';
$sortOrder = isset($_GET['order']) ? trim($_GET['order']) : 'asc';

// ソートURLを生成（フィルタパラメータを保持）
function buildSortUrl(string $col, string $currentSortBy, string $currentSortOrder): string {
    $keepParams = ['search_pj', 'search_site', 'tag', 'filter_status', 'filter_assignee'];
    $params = [
        'sort'  => $col,
        'order' => ($currentSortBy === $col && $currentSortOrder === 'asc') ? 'desc' : 'asc',
    ];
    foreach ($keepParams as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') {
            $params[$k] = $_GET[$k];
        }
    }
    return '?' . http_build_query($params);
}

// トラブル対応から来た場合のP番号を取得
$suggestedPjNumber = isset($_GET['new_from_trouble']) ? trim($_GET['new_from_trouble']) : '';

// PJ更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pj'])) {
    if (!canEditCurrentPage()) {
        header('Location: master.php?error=no_edit_permission');
        exit;
    }
    $updateId = $_POST['update_pj'];

    // 日付フィールドのバリデーション
    $dateFields = [
        'occurrence_date' => '発生日',
    ];

    $errors = [];
    foreach ($dateFields as $field => $label) {
        $value = trim($_POST[$field] ?? '');
        if (!empty($value) && !validateDate($value)) {
            $errors[] = "{$label}はYYYY-MM-DD形式で入力してください（入力値: {$value}）";
        }
    }

    // 郵便番号バリデーション
    $postalCode = trim($_POST['postal_code'] ?? '');
    if (!empty($postalCode) && !validatePostalCode($postalCode)) {
        $errors[] = '郵便番号はXXX-XXXX または XXXXXXX 形式で入力してください';
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('、', $errors);
        header('Location: master.php');
        exit;
    }

    foreach ($data['projects'] as &$pj) {
        if ($pj['id'] === $updateId) {
            // 基本情報
            $pj['occurrence_date'] = trim($_POST['occurrence_date'] ?? '');
            $pj['transaction_type'] = sanitizeInput(trim($_POST['transaction_type'] ?? ''), 'string');
            // 担当・取引先
            $pj['sales_assignee'] = sanitizeInput(trim($_POST['sales_assignee'] ?? ''), 'string');
            $pj['customer_name'] = sanitizeInput(trim($_POST['customer_name'] ?? ''), 'string');
            $pj['dealer_name'] = sanitizeInput(trim($_POST['dealer_name'] ?? ''), 'string');
            $pj['general_contractor'] = sanitizeInput(trim($_POST['general_contractor'] ?? ''), 'string');
            // 現場情報
            $pj['name'] = sanitizeInput(trim($_POST['site_name'] ?? ''), 'string');
            $pj['postal_code'] = $postalCode;
            $pj['prefecture'] = sanitizeInput(trim($_POST['prefecture'] ?? ''), 'string');
            $pj['address'] = sanitizeInput(trim($_POST['address'] ?? ''), 'string');
            $pj['shipping_address'] = sanitizeInput(trim($_POST['shipping_address'] ?? ''), 'string');
            // 商品情報
            $pj['maker'] = sanitizeInput(trim($_POST['maker'] ?? ''), 'string');
            $pj['product_category'] = sanitizeInput(trim($_POST['product_category'] ?? ''), 'string');
            $pj['product_series'] = sanitizeInput(trim($_POST['product_series'] ?? ''), 'string');
            $pj['product_name'] = sanitizeInput(trim($_POST['product_name'] ?? ''), 'string');
            $pj['product_spec'] = sanitizeInput(trim($_POST['product_spec'] ?? ''), 'string');
            $pj['led_size'] = sanitizeInput(trim($_POST['led_size'] ?? ''), 'string');
            $pj['lcd_size'] = sanitizeInput(trim($_POST['lcd_size'] ?? ''), 'string');
            $pj['cms_player'] = sanitizeInput(trim($_POST['cms_player'] ?? ''), 'string');
            // メモ
            $pj['memo'] = sanitizeInput(trim($_POST['memo'] ?? ''), 'string');
            // ステータス
            $pj['status'] = trim($_POST['status'] ?? '');

            // Google Chatスペース連携
            $editChatSpaceId = trim($_POST['edit_chat_space_id'] ?? '');
            $editPendingChatSpace = trim($_POST['edit_pending_chat_space'] ?? '');

            // __auto__の場合はPJ番号+現場名からスペース名を自動生成
            if ($editPendingChatSpace === '__auto__') {
                $siteName = trim($_POST['site_name'] ?? '');
                $editPendingChatSpace = $updateId . ($siteName ? ' ' . $siteName : '');
            }

            // 紐づけ解除の場合
            if ($editChatSpaceId === '__unlink__') {
                $pj['chat_space_id'] = '';
                $pj['pending_chat_space'] = '';
            }
            // 既存スペース選択の場合
            elseif (!empty($editChatSpaceId) && $editChatSpaceId !== ($pj['chat_space_id'] ?? '')) {
                $pj['chat_space_id'] = $editChatSpaceId;
                $pj['pending_chat_space'] = '';
            }
            // 新規作成の場合（pending_chat_spaceに値があれば非同期処理）
            elseif (!empty($editPendingChatSpace) && empty($pj['chat_space_id'])) {
                $pj['pending_chat_space'] = $editPendingChatSpace;
            }

            break;
        }
    }
    unset($pj);
    saveData($data);
    writeAuditLog('update', 'project', "プロジェクト更新: {$updateId}");

    // 非同期Chat作成が必要な場合
    $redirectParams = 'updated=1';
    $editPendingChatSpace = trim($_POST['edit_pending_chat_space'] ?? '');
    $editChatSpaceId = trim($_POST['edit_chat_space_id'] ?? '');

    // __auto__の場合はPJ番号+現場名からスペース名を自動生成
    if ($editPendingChatSpace === '__auto__') {
        $siteName = trim($_POST['site_name'] ?? '');
        $editPendingChatSpace = $updateId . ($siteName ? ' ' . $siteName : '');
    }

    if (!empty($editPendingChatSpace)) {
        $redirectParams .= '&async_chat=' . urlencode($updateId) . '&space_name=' . urlencode($editPendingChatSpace);
    } elseif (!empty($editChatSpaceId) && $editChatSpaceId !== '__unlink__') {
        // 既存スペースにメンバー追加（解除の場合は除外）
        $redirectParams .= '&async_chat=' . urlencode($updateId) . '&existing_space=' . urlencode($editChatSpaceId);
    }
    header('Location: master.php?' . $redirectParams);
    exit;
}

// PJ追加（詳細情報対応）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pj'])) {
    if (!canEditCurrentPage()) {
        header('Location: master.php?error=no_edit_permission');
        exit;
    }
    // 基本情報
    $occurrenceDate = trim($_POST['occurrence_date'] ?? '');
    $transactionType = trim($_POST['transaction_type'] ?? '');
    $customPjNumber = trim($_POST['custom_pj_number'] ?? '');

    // 担当・取引先情報
    $salesAssignee = trim($_POST['sales_assignee'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $dealerName = trim($_POST['dealer_name'] ?? '');
    $generalContractor = trim($_POST['general_contractor'] ?? '');

    // 現場情報
    $siteName = trim($_POST['site_name'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $prefecture = trim($_POST['prefecture'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');

    // 商品情報
    $maker = trim($_POST['maker'] ?? '');
    $productCategory = trim($_POST['product_category'] ?? '');
    $productSeries = trim($_POST['product_series'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $productSpec = trim($_POST['product_spec'] ?? '');
    $ledSize = trim($_POST['led_size'] ?? '');
    $lcdSize = trim($_POST['lcd_size'] ?? '');
    $cmsPlayer = trim($_POST['cms_player'] ?? '');

    // ステータス
    $status = trim($_POST['status'] ?? '案件発生');

    // メモ
    $memo = trim($_POST['memo'] ?? '');

    // Google Chatスペース
    $chatSpaceId = trim($_POST['chat_space_id'] ?? '');

    // 必須項目チェックなし（全て任意）
    if (true) {
        // 最新データを再読み込み（同時書き込み対策）
        $data = getData();

        // P番号を確定（削除済みを除いたアクティブ案件のみで採番・重複チェック）
        $pjNumber = getConfirmedPjNumber(filterDeleted($data['projects']), $customPjNumber);

        // 取引形態の略称を生成（非同期Chat作成用）
        $typeAbbrev = '';
        switch ($transactionType) {
            case 'レンタル': $typeAbbrev = '【レ】'; break;
            case '販売': $typeAbbrev = '【売】'; break;
            case '保守': $typeAbbrev = '【保】'; break;
            default: $typeAbbrev = ''; break;
        }
        $chatSpaceName = "{$pjNumber}{$typeAbbrev}{$siteName}";

        // 内部チャットルームID（pj_P番号）
        $internalChatRoomId = 'pj_' . $pjNumber;

        $newProject = array(
            'id' => $pjNumber,
            'name' => $siteName,
            // 基本情報
            'occurrence_date' => $occurrenceDate,
            'transaction_type' => $transactionType,
            // 担当・取引先
            'sales_assignee' => $salesAssignee,
            'customer_name' => $customerName,
            'dealer_name' => $dealerName,
            'general_contractor' => $generalContractor,
            // 現場情報
            'postal_code' => $postalCode,
            'prefecture' => $prefecture,
            'address' => $address,
            'shipping_address' => $shippingAddress,
            // 商品情報
            'maker' => $maker,
            'product_category' => $productCategory,
            'product_series' => $productSeries,
            'product_name' => $productName,
            'product_spec' => $productSpec,
            'led_size' => $ledSize,
            'lcd_size' => $lcdSize,
            'cms_player' => $cmsPlayer,
            // ステータス
            'status' => $status,
            // メモ
            'memo' => $memo,
            'chat_space_id' => $chatSpaceId,
            // 非同期Chat処理用
            'pending_chat_space' => empty($chatSpaceId) ? $chatSpaceName : '',
            // 内部チャット
            'internal_chat_room_id' => $internalChatRoomId,
            'created_at' => date('Y-m-d H:i:s')
        );

        // Google Chatスペース処理は非同期で行う（ページ読み込み後にAJAXで実行）

        // 内部チャットルームを自動作成（全員アクセス可）
        $chatRoomName = $pjNumber . ($siteName ? ' ' . $siteName : '');
        $data['chat_rooms'][] = [
            'id'          => $internalChatRoomId,
            'type'        => 'group',
            'name'        => $chatRoomName,
            'description' => '案件 ' . $pjNumber . ' のチャットルーム',
            'members'     => [],
            'is_default'  => false,
            'created_by'  => 'system',
            'created_at'  => date('Y-m-d H:i:s'),
            'deleted_at'  => null,
        ];

        $data['projects'][] = $newProject;
        saveData($data);

        writeAuditLog('create', 'project', "プロジェクト追加: {$newProject['id']} {$siteName}");

        // 非同期Chat作成が必要な場合はパラメータを追加
        $redirectParams = 'added=1';
        if (!empty($newProject['pending_chat_space'])) {
            $redirectParams .= '&async_chat=' . urlencode($pjNumber) . '&space_name=' . urlencode($newProject['pending_chat_space']);
        } elseif (!empty($chatSpaceId)) {
            $redirectParams .= '&async_chat=' . urlencode($pjNumber) . '&existing_space=' . urlencode($chatSpaceId);
        }

        header('Location: master.php?' . $redirectParams);
        exit;
    }
}

// PJ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pj'])) {
    if (!canDelete()) {
        header('Location: master.php?error=no_delete_permission');
        exit;
    }
    $deleteId = $_POST['delete_pj'];

    // 論理削除
    $deletedProject = softDelete($data['projects'], $deleteId);

    if ($deletedProject) {
        saveData($data);
        auditDelete('projects', $deleteId, '案件を削除: ' . ($deletedProject['name'] ?? ''), $deletedProject);
    }

    header('Location: master.php?deleted=1');
    exit;
}

// PJ一括削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!canDelete()) {
        header('Location: master.php?error=no_delete_permission');
        exit;
    }
    $deleteIds = $_POST['project_ids'] ?? array();

    if (empty($deleteIds) || !is_array($deleteIds)) {
        header('Location: master.php?error=no_selection');
        exit;
    }

    // 論理削除
    $deletedCount = 0;
    $deletedNames = [];
    foreach ($deleteIds as $did) {
        $deletedProject = softDelete($data['projects'], $did);
        if ($deletedProject) {
            $deletedCount++;
            $deletedNames[] = $deletedProject['name'] ?? '';
        }
    }

    if ($deletedCount > 0) {
        saveData($data);
        writeAuditLog('bulk_delete', 'projects', "案件を一括削除 ({$deletedCount}件)", [
            'deleted_count' => $deletedCount,
            'deleted_names' => $deletedNames
        ]);
    }

    header("Location: master.php?bulk_deleted=$deletedCount");
    exit;
}

// PJ一括ステータス変更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_status_change'])) {
    if (!canEditCurrentPage()) {
        header('Location: master.php?error=no_edit_permission');
        exit;
    }
    $changeIds = $_POST['project_ids'] ?? array();
    $newStatus = trim($_POST['new_status'] ?? '');

    if (empty($changeIds) || !is_array($changeIds) || empty($newStatus)) {
        header('Location: master.php?error=no_selection');
        exit;
    }

    if (!in_array($newStatus, $PROJECT_STATUSES)) {
        header('Location: master.php?error=invalid_status');
        exit;
    }

    $changedCount = 0;
    foreach ($data['projects'] as &$pj) {
        if (in_array($pj['id'], $changeIds) && empty($pj['deleted_at'])) {
            $oldStatus = $pj['status'] ?? '';
            $pj['status'] = $newStatus;
            $pj['updated_at'] = date('Y-m-d H:i:s');
            $changedCount++;
        }
    }
    unset($pj);

    if ($changedCount > 0) {
        saveData($data);
        writeAuditLog('bulk_update', 'projects', "案件を一括ステータス変更 ({$changedCount}件 → {$newStatus})", [
            'changed_count' => $changedCount,
            'new_status' => $newStatus
        ]);
    }

    header("Location: master.php?bulk_changed=$changedCount&to_status=" . urlencode($newStatus));
    exit;
}

// 担当者追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignee'])) {
    $assigneeName = trim($_POST['assignee_name'] ?? '');

    if ($assigneeName) {
        // 重複チェック
        $exists = false;
        foreach ($data['assignees'] as $a) {
            if ($a['name'] === $assigneeName) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $message = 'この担当者は既に登録されています';
            $messageType = 'danger';
        } else {
            $maxId = 0;
            foreach ($data['assignees'] as $a) {
                if ($a['id'] > $maxId) $maxId = $a['id'];
            }
            $data['assignees'][] = ['id' => $maxId + 1, 'name' => $assigneeName];
            saveData($data);
            header('Location: master.php?added_assignee=1');
            exit;
        }
    }
}

// 担当者削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignee'])) {
    if (!canDelete()) {
        header('Location: master.php?error=no_delete_permission');
        exit;
    }
    $deleteId = (int)$_POST['delete_assignee'];

    // 削除対象を記録
    $deletedAssignee = null;
    foreach ($data['assignees'] as $a) {
        if ($a['id'] === $deleteId) {
            $deletedAssignee = $a;
            break;
        }
    }

    $data['assignees'] = array_values(array_filter($data['assignees'], function($a) use ($deleteId) {
        return $a['id'] !== $deleteId;
    }));
    saveData($data);

    if ($deletedAssignee) {
        auditDelete('assignees', (string)$deleteId, '担当者を削除: ' . ($deletedAssignee['name'] ?? ''), $deletedAssignee);
    }

    header('Location: master.php?deleted_assignee=1');
    exit;
}

// 検索処理とタグフィルタ
$searchPjNumber = isset($_GET['search_pj']) ? trim($_GET['search_pj']) : '';
$searchSiteName = isset($_GET['search_site']) ? trim($_GET['search_site']) : '';
$filterTag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$filterStatus = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
$filterAssignee = isset($_GET['filter_assignee']) ? trim($_GET['filter_assignee']) : '';
$filteredProjects = filterDeleted($data['projects']);

// フィルター適用前の総件数を保存
$totalProjectsCount = count($filteredProjects);

// タグ別の件数を計算
$tagCounts = array('レンタル' => 0, '販売' => 0, 'その他' => 0);
foreach ($filteredProjects as $p) {
    // tagフィールドを優先、なければ現場名から判定
    $tag = $p['tag'] ?? '';
    if (empty($tag)) {
        $siteName = $p['name'] ?? '';
        if (strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false) {
            $tag = 'レンタル';
        } elseif (strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false) {
            $tag = '販売';
        }
    }

    if ($tag === 'レンタル') {
        $tagCounts['レンタル']++;
    } elseif ($tag === '販売') {
        $tagCounts['販売']++;
    } else {
        $tagCounts['その他']++;
    }
}

if (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterTag) || !empty($filterStatus) || !empty($filterAssignee)) {
    $filteredProjects = array_filter($filteredProjects, function($p) use ($searchPjNumber, $searchSiteName, $filterTag, $filterStatus, $filterAssignee) {
        $matchesPj = empty($searchPjNumber) || stripos($p['id'], $searchPjNumber) !== false;
        $matchesSite = empty($searchSiteName) || stripos($p['name'] ?? '', $searchSiteName) !== false;

        // タグフィルタ
        $matchesTag = true;
        if (!empty($filterTag)) {
            // tagフィールドを優先、なければ現場名から判定
            $tag = $p['tag'] ?? '';
            if (empty($tag)) {
                $siteName = $p['name'] ?? '';
                if (strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false) {
                    $tag = 'レンタル';
                } elseif (strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false) {
                    $tag = '販売';
                }
            }

            if ($filterTag === 'レンタル') {
                $matchesTag = ($tag === 'レンタル');
            } elseif ($filterTag === '販売') {
                $matchesTag = ($tag === '販売');
            } elseif ($filterTag === 'その他') {
                $matchesTag = ($tag !== 'レンタル' && $tag !== '販売');
            }
        }

        // ステータスフィルタ
        $matchesStatus = empty($filterStatus) || ($p['status'] ?? '') === $filterStatus;

        // 担当者フィルタ
        $matchesAssignee = empty($filterAssignee) || ($p['sales_assignee'] ?? '') === $filterAssignee;

        return $matchesPj && $matchesSite && $matchesTag && $matchesStatus && $matchesAssignee;
    });
}

// ソート処理
usort($filteredProjects, function($a, $b) use ($sortBy, $sortOrder) {
    $valA = '';
    $valB = '';

    switch ($sortBy) {
        case 'id':
            // 案件番号から数値を抽出してソート（例: "1", "2", "10" → 数値比較）
            $valA = $a['id'] ?? '';
            $valB = $b['id'] ?? '';
            // 数値のみ抽出
            preg_match('/(\d+)/', $valA, $matchA);
            preg_match('/(\d+)/', $valB, $matchB);
            $numA = isset($matchA[1]) ? (int)$matchA[1] : 0;
            $numB = isset($matchB[1]) ? (int)$matchB[1] : 0;
            if ($sortOrder === 'asc') {
                return $numA - $numB;
            } else {
                return $numB - $numA;
            }
        case 'name':
            $valA = $a['name'] ?? '';
            $valB = $b['name'] ?? '';
            break;
        case 'customer':
            $valA = $a['customer_name'] ?? '';
            $valB = $b['customer_name'] ?? '';
            break;
        case 'product_category':
            $valA = $a['product_category'] ?? '';
            $valB = $b['product_category'] ?? '';
            break;
        default:
            // デフォルトも数値ソート
            $valA = $a['id'] ?? '';
            $valB = $b['id'] ?? '';
            preg_match('/(\d+)/', $valA, $matchA);
            preg_match('/(\d+)/', $valB, $matchB);
            $numA = isset($matchA[1]) ? (int)$matchA[1] : 0;
            $numB = isset($matchB[1]) ? (int)$matchB[1] : 0;
            if ($sortOrder === 'asc') {
                return $numA - $numB;
            } else {
                return $numB - $numA;
            }
    }

    if ($sortOrder === 'asc') {
        return strcmp($valA, $valB);
    } else {
        return strcmp($valB, $valA);
    }
});
$filteredProjects = array_values($filteredProjects);

// LEDパネル枚数 → インチ変換（表示用）
$panelToInch = [
    '4x3' => '59',  '4×3' => '59',
    '6x4' => '90',  '6×4' => '90',
    '7x4' => '100', '7×4' => '100',
    '9x6' => '140', '9×6' => '140',
    '7x10' => '150', '7×10' => '150',
    '10x7' => '150', '10×7' => '150',
];
function convertPanelToInch(string $size, array $map): string {
    if (isset($map[$size])) return $map[$size];
    if (preg_match('/^(\d+)\s*[×x×]\s*(\d+)$/u', $size, $pm)) {
        return $map[$pm[1].'x'.$pm[2]] ?? $map[$pm[1].'×'.$pm[2]] ?? $size;
    }
    return $size;
}

// カテゴリID → カテゴリ名のマップを作成（表示用）
// 名前が直接入っているデータにも対応するため、名前→名前のマップも追加
$categoryMap = [];
foreach ($data['productCategories'] ?? [] as $cat) {
    if (!empty($cat['id'])) {
        $categoryMap[$cat['id']] = $cat['name'] ?? '';
    }
    if (!empty($cat['name'])) {
        $categoryMap[$cat['name']] = $cat['name']; // 名前が直接入っている場合もそのまま表示
    }
}

require_once '../functions/header.php';
?>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">案件を登録しました</div>
<?php endif; ?>

<?php if (isset($_GET['synced'])): ?>
    <div class="alert alert-success">スプレッドシートと同期しました（追加: <?= (int)($_GET['added_count'] ?? 0) ?>件, 更新: <?= (int)($_GET['updated_count'] ?? 0) ?>件）</div>
<?php endif; ?>

<?php if (isset($_GET['sync_error'])): ?>
    <div class="alert alert-danger">同期エラー: <?= htmlspecialchars($_GET['sync_error']) ?></div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">案件を更新しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">案件を削除しました</div>
<?php endif; ?>

<?php if (isset($_GET['bulk_deleted'])): ?>
    <div class="alert alert-success"><?= (int)$_GET['bulk_deleted'] ?>件の案件を削除しました</div>
<?php endif; ?>
<?php if (isset($_GET['bulk_changed'])): ?>
    <div class="alert alert-success"><?= (int)$_GET['bulk_changed'] ?>件の案件のステータスを「<?= htmlspecialchars($_GET['to_status'] ?? '') ?>」に変更しました</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'no_selection'): ?>
    <div class="alert alert-danger">削除する案件を選択してください</div>
    <?php elseif ($_GET['error'] === 'no_delete_permission'): ?>
    <div class="alert alert-danger">削除権限がありません</div>
    <?php elseif ($_GET['error'] === 'no_edit_permission'): ?>
    <div class="alert alert-danger">編集権限がありません</div>
    <?php elseif ($_GET['error'] === 'invalid_status'): ?>
    <div class="alert alert-danger">無効なステータスです</div>
    <?php else: ?>
    <div class="alert alert-danger"><?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['added_assignee'])): ?>
    <div class="alert alert-success">担当者を追加しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted_assignee'])): ?>
    <div class="alert alert-success">担当者を削除しました</div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
<?php endif; ?>

<style<?= nonceAttr() ?>>
/* 案件マスタ専用スタイル */
.view-toggle {
    display: flex;
    gap: 0.25rem;
    background: var(--gray-100);
    padding: 0.25rem;
    border-radius: 8px;
}
.view-toggle-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 6px;
    font-size: 0.875rem;
    color: var(--gray-700);
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.view-toggle-btn.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.view-toggle-btn:hover:not(.active) {
    background: var(--gray-200);
}

.sort-header {
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
}
.sort-header:hover {
    background: var(--gray-200);
}
.sort-icon {
    opacity: 0.3;
    margin-left: 0.25rem;
}
.sort-header.active .sort-icon {
    opacity: 1;
}
.sort-link {
    color: inherit;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.project-chat-link {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.project-chat-link:hover strong {
    text-decoration: underline;
}
.project-chat-link .chat-icon {
    color: #1a73e8;
    flex-shrink: 0;
    vertical-align: middle;
}
.project-internal-chat-link {
    color: var(--primary, #4f46e5);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    margin-left: 4px;
    opacity: 0.7;
    transition: opacity 0.15s;
}
.project-internal-chat-link:hover {
    opacity: 1;
}
.project-internal-chat-link .internal-chat-icon {
    flex-shrink: 0;
    vertical-align: middle;
}

.project-row {
    cursor: pointer;
    transition: all 0.2s;
}
.project-row td {
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    font-size: 0.9em;
}
.project-row td:first-child {
    max-width: 40px;
}
.project-row td:nth-child(2) {
    max-width: 60px;
}
.project-row td:nth-child(3) {
    max-width: 250px;
}
.project-row:hover {
    background: var(--primary-light) !important;
}
.project-row.expanded {
    background: var(--gray-50);
}

.project-detail-row {
    display: none;
}
.project-detail-row.show {
    display: table-row;
}
.project-detail-cell {
    padding: 0 !important;
    background: var(--gray-50);
}
.project-detail-content {
    padding: 1.5rem;
    border-top: 2px solid var(--primary);
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}
.detail-section {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.detail-section-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary-light);
}
.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.375rem 0;
    font-size: 0.875rem;
    border-bottom: 1px solid var(--gray-100);
}
.detail-row:last-child {
    border-bottom: none;
}
.detail-label {
    color: var(--gray-500);
}
.detail-value {
    color: var(--gray-900);
    font-weight: 500;
    text-align: right;
}
.detail-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

/* カード表示 */
.project-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}
.project-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.2s;
    cursor: pointer;
}
.project-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.project-card-header {
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--gray-100);
}
.project-card-id {
    font-weight: 700;
    font-size: 1rem;
    color: var(--gray-900);
}
.project-card-body {
    padding: 1rem;
}
.project-card-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
}
.project-card-customer {
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
}
.project-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.8125rem;
    color: var(--gray-500);
}
.project-card-meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.project-card-footer {
    padding: 0.75rem 1rem;
    background: var(--gray-50);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.project-card-troubles {
    font-size: 0.8125rem;
    color: var(--gray-600);
}
.project-card-troubles.has-troubles {
    color: var(--danger);
    font-weight: 600;
}

/* カード詳細モーダル */
.card-detail-modal {
    display: none;
    position: fixed;
    z-index: 1001;
    right: 0;
    top: 0;
    width: 500px;
    max-width: 100%;
    height: 100%;
    background: white;
    box-shadow: -4px 0 24px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}
@keyframes slideIn {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
.card-detail-modal.show {
    display: block;
}
.card-detail-overlay {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.3);
}
.card-detail-overlay.show {
    display: block;
}
.card-detail-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-detail-body {
    padding: 1.5rem;
    height: calc(100% - 140px);
    overflow-y: auto;
}
.card-detail-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 0.5rem;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<div class="page-container">

<!-- ページヘッダー -->
<div class="page-header">
    <h2>プロジェクト管理 <span    class="font-normal text-14 text-gray-500">（<?= count($filteredProjects) ?>件<?= (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterTag) || !empty($filterStatus) || !empty($filterAssignee)) ? ' / ' . count($data['projects']) . '件中' : '' ?>）</span></h2>
    <div class="page-header-actions">
        <!-- 一括操作バー -->
        <div id="bulkActionBar" style="display: none;" class="align-center gap-1">
            <span id="bulkSelectedCount"   class="text-14 text-gray-600"></span>
            <?php if (canEditCurrentPage()): ?>
            <select id="bulkStatusSelect"         class="form-select w-auto text-14 py-04 px-075">
                <option value="">ステータス変更...</option>
                <?php foreach ($PROJECT_STATUSES as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button"  data-action="bulk-status-change"        class="btn btn-primary text-14 py-04 px-2" id="bulkStatusBtn">変更</button>
            <?php endif; ?>
            <?php if (canDelete()): ?>
            <button type="button"  data-action="bulk-delete"        class="btn btn-danger text-14 py-04 px-2" id="bulkDeleteBtn">削除</button>
            <?php endif; ?>
        </div>
        <div id="normalActionBar" class="d-flex align-center gap-1">
            <?php if (isAdmin()): ?>
            <div   class="dropdown position-relative d-inline-block">
                <button type="button" class="btn btn-outline" data-action="toggle-sync-menu" title="スプレッドシート連携">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"    class="align-v-minus2"><path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 0 1 9-9"/></svg>
                    スプシ連携 ▼
                </button>
                <div id="syncMenu"         class="dropdown-menu dropdown-menu-right-top d-none position-absolute rounded-lg bg-white border-gray">
                    <button type="button" data-action="sync-from-spreadsheet"        class="d-block w-full text-left cursor-pointer text-14 p-pad-none">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"      class="mr-1 align-v-minus2"><path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3"/></svg>
                        同期する
                    </button>
                    <button type="button" data-action="clear-synced-data"        class="d-block w-full text-left cursor-pointer text-14 text-danger p-pad-none">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"      class="mr-1 align-v-minus2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        同期データを削除
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <?php if (canEditCurrentPage()): ?>
            <button type="button" class="btn btn-primary" data-action="show-add-modal">新規登録</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 案件一覧 -->
<div class="card">
    <div class="card-body">
        <!-- 検索フォーム -->
        <form method="GET"  class="mb-2">
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
            <input type="hidden" name="tag" value="<?= htmlspecialchars($filterTag) ?>">
            <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filterStatus) ?>">
            <input type="hidden" name="filter_assignee" value="<?= htmlspecialchars($filterAssignee) ?>">
            <div  class="d-flex gap-1 align-center flex-wrap">
                <div      class="min-w-150 flex-1">
                    <input type="text"
                           name="search_pj"
                           value="<?= htmlspecialchars($searchPjNumber) ?>"
                           placeholder="P番号で検索..."
                           class="full-input">
                </div>
                <div      class="min-w-150 flex-1">
                    <input type="text"
                           name="search_site"
                           value="<?= htmlspecialchars($searchSiteName) ?>"
                           placeholder="現場名で検索..."
                           class="full-input">
                </div>
                <button type="submit"         class="btn btn-primary whitespace-nowrap text-14 btn-pad-05-15">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle mr-05">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    検索
                </button>
                <?php if (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterTag) || !empty($filterStatus) || !empty($filterAssignee)): ?>
                    <a href="master.php?view=<?= $viewMode ?>" class="btn btn-secondary btn-pad-05-10 text-087 text-no-underline whitespace-nowrap">クリア</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- タグフィルタ -->
        <div  class="mb-2 d-flex gap-1 flex-wrap">
            <?php
            $tagFilterParams = '';
            if (!empty($filterStatus)) $tagFilterParams .= '&filter_status=' . urlencode($filterStatus);
            if (!empty($filterAssignee)) $tagFilterParams .= '&filter_assignee=' . urlencode($filterAssignee);
            ?>
            <a href="master.php?view=<?= $viewMode ?><?= $tagFilterParams ?>" class="btn <?= empty($filterTag) ? 'btn-primary' : 'btn-secondary' ?> btn-link">
                全種別 (<?= $totalProjectsCount ?>)
            </a>
            <a href="master.php?view=<?= $viewMode ?>&tag=レンタル<?= $tagFilterParams ?>" class="btn <?= $filterTag === 'レンタル' ? 'btn-primary' : 'btn-secondary' ?> btn-link">
                レンタル (<?= $tagCounts['レンタル'] ?>)
            </a>
            <a href="master.php?view=<?= $viewMode ?>&tag=販売<?= $tagFilterParams ?>" class="btn <?= $filterTag === '販売' ? 'btn-primary' : 'btn-secondary' ?> btn-link">
                販売 (<?= $tagCounts['販売'] ?>)
            </a>
            <a href="master.php?view=<?= $viewMode ?>&tag=その他<?= $tagFilterParams ?>" class="btn <?= $filterTag === 'その他' ? 'btn-primary' : 'btn-secondary' ?> btn-link">
                その他 (<?= $tagCounts['その他'] ?>)
            </a>
            <select data-action="filter-by-status"        class="rounded text-14 p-pad-border">
                <option value="">全ステータス</option>
                <?php foreach ($PROJECT_STATUSES as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= ($filterStatus ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select data-action="filter-by-assignee"        class="rounded text-14 p-pad-border">
                <option value="">全担当者</option>
                <?php foreach ($data['assignees'] ?? [] as $a): ?>
                    <option value="<?= htmlspecialchars($a['name']) ?>" <?= $filterAssignee === $a['name'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($viewMode === 'table'): ?>
        <!-- テーブル表示 -->
        <form id="bulkStatusForm" method="POST"  class="d-none">
            <?= csrfTokenField() ?>
            <input type="hidden" name="bulk_status_change" value="1">
            <input type="hidden" name="new_status" id="bulkStatusValue" value="">
        </form>
        <form id="bulkDeleteForm" method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="bulk_delete" value="1">
            <div class="table-wrapper">
                <table class="table" id="projectTable">
                    <thead>
                        <tr>
                            <th      class="whitespace-nowrap w-40"><input type="checkbox" id="selectAll" data-action="toggle-select-all"></th>
                            <th class="sort-header <?= $sortBy === 'id' ? 'active' : '' ?> whitespace-nowrap">
                                <a href="<?= htmlspecialchars(buildSortUrl('id', $sortBy, $sortOrder)) ?>" class="sort-link">
                                    案件番号<span class="sort-icon"><?= $sortBy === 'id' ? ($sortOrder === 'asc' ? '▲' : '▼') : '↕' ?></span>
                                </a>
                            </th>
                            <th class="whitespace-nowrap"></th>
                            <th class="sort-header <?= $sortBy === 'name' ? 'active' : '' ?> whitespace-nowrap">
                                <a href="<?= htmlspecialchars(buildSortUrl('name', $sortBy, $sortOrder)) ?>" class="sort-link">
                                    現場名<span class="sort-icon"><?= $sortBy === 'name' ? ($sortOrder === 'asc' ? '▲' : '▼') : '↕' ?></span>
                                </a>
                            </th>
                            <th  class="whitespace-nowrap">営業担当</th>
                            <th  class="whitespace-nowrap">ディーラー</th>
                            <th  class="whitespace-nowrap">営業所</th>
                            <th class="sort-header <?= $sortBy === 'product_category' ? 'active' : '' ?> whitespace-nowrap">
                                <a href="<?= htmlspecialchars(buildSortUrl('product_category', $sortBy, $sortOrder)) ?>" class="sort-link">
                                    製品名<span class="sort-icon"><?= $sortBy === 'product_category' ? ($sortOrder === 'asc' ? '▲' : '▼') : '↕' ?></span>
                                </a>
                            </th>
                            <th  class="whitespace-nowrap">サイズ</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php foreach ($filteredProjects as $idx => $pj): ?>
                        <?php
                        // トラブル件数をカウント
                        $troubleCount = 0;
                        foreach ($data['troubles'] ?? [] as $t) {
                            $tPj = $t['pj_number'] ?? $t['project_name'] ?? '';
                            if (!empty($tPj) && strcasecmp($tPj, $pj['id']) === 0) {
                                $troubleCount++;
                            }
                        }

                        // タグを判定（tagフィールド優先、なければ現場名プレフィックスから判定）
                        $siteName = $pj['name'] ?? '';
                        $tag = $pj['tag'] ?? '';
                        if (empty($tag)) {
                            if (strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false) {
                                $tag = 'レンタル';
                            } elseif (strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false) {
                                $tag = '販売';
                            }
                        }
                        $tagStyle = ['bg' => '', 'text' => ''];
                        if ($tag === 'レンタル') {
                            $tagStyle = ['bg' => '#dbeafe', 'text' => '#1d4ed8'];
                        } elseif ($tag === '販売') {
                            $tagStyle = ['bg' => '#d1fae5', 'text' => '#065f46'];
                        }
                        ?>
                        <tr class="project-row" data-idx="<?= $idx ?>" data-group="pj-<?= $idx ?>" data-action="toggle-detail">
                            <td><input type="checkbox" class="project-checkbox" name="project_ids[]" value="<?= htmlspecialchars($pj['id']) ?>" data-action="stop-propagation"></td>
                            <td class="whitespace-nowrap">
                                <?php if (!empty($pj['chat_space_id'])): ?>
                                    <?php $chatSpaceUrl = 'https://chat.google.com/room/' . preg_replace('/^spaces\//', '', $pj['chat_space_id']); ?>
                                    <a href="<?= htmlspecialchars($chatSpaceUrl) ?>" target="_blank" rel="noopener" class="project-chat-link" data-action="stop-propagation" title="Chatスペースを開く">
                                        <strong><?= htmlspecialchars($pj['id']) ?></strong>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="chat-icon"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    </a>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars($pj['id']) ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($pj['internal_chat_room_id'])): ?>
                                    <a href="/pages/chat.php#<?= htmlspecialchars($pj['internal_chat_room_id']) ?>" class="project-internal-chat-link" data-action="stop-propagation" title="社内チャットを開く">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" class="internal-chat-icon"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                                    </a>
                                <?php elseif (canEditCurrentPage()): ?>
                                    <button class="project-internal-chat-link project-chat-create-btn" data-action="create-pj-room" data-pj-id="<?= htmlspecialchars($pj['id']) ?>" title="社内チャットルームを作成" style="background:none;border:none;cursor:pointer;padding:0;">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="internal-chat-icon"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if ($tag): ?>
                                    <span class="d-inline-block rounded text-xs font-semibold tag-xs" style="background: <?= htmlspecialchars($tagStyle['bg'], ENT_QUOTES) ?>; color: <?= htmlspecialchars($tagStyle['text'], ENT_QUOTES) ?>"><?= $tag ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(trimSiteName($pj['name'])) ?></td>
                            <td>
                                <?php
                                $assignee = $pj['sales_assignee'] ?? '';
                                if (!empty($assignee)):
                                    $assigneeColor = getAssigneeColor($assignee);
                                ?>
                                <span        class="d-inline-block rounded text-xs font-medium tag-xs" style="background: <?= htmlspecialchars($assigneeColor['bg'], ENT_QUOTES) ?>; color: <?= htmlspecialchars($assigneeColor['text'], ENT_QUOTES) ?>"><?= htmlspecialchars($assignee) ?></span>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($pj['dealer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($pj['office_name'] ?? '-') ?></td>
                            <td><?php
                                $catId = $pj['product_category'] ?? '';
                                echo htmlspecialchars(!empty($catId) ? ($categoryMap[$catId] ?? '-') : '-');
                            ?></td>
                            <td>
                                <?php
                                $ledSize = convertPanelToInch($pj['led_size'] ?? '', $panelToInch);
                                $lcdSize = $pj['lcd_size'] ?? '';
                                if (!empty($ledSize) && $ledSize !== '-'):
                                ?>
                                <span style="color: #d32f2f; font-weight: bold;">LED <?= htmlspecialchars($ledSize) ?>インチ</span>
                                <?php elseif (!empty($lcdSize) && $lcdSize !== '-'): ?>
                                <span style="color: #1976d2; font-weight: bold;">LCD <?= htmlspecialchars($lcdSize) ?>インチ</span>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <!-- 詳細行 -->
                        <tr class="project-detail-row" id="detail-<?= $idx ?>" data-group="pj-<?= $idx ?>">
                            <td colspan="9" class="project-detail-cell">
                                <div class="project-detail-content">
                                    <div class="detail-grid">
                                        <!-- 基本情報 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">基本情報</div>
                                            <div class="detail-row"><span class="detail-label">案件番号</span><span class="detail-value"><?= htmlspecialchars($pj['id']) ?></span></div>
                                            <div class="detail-row"><span class="detail-label">発生日</span><span class="detail-value"><?= !empty($pj['occurrence_date']) ? date('Y/m/d', strtotime($pj['occurrence_date'])) : '-' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">取引形態</span><span class="detail-value"><?= htmlspecialchars($pj['transaction_type'] ?? '-') ?></span></div>
                                        </div>
                                        <!-- 担当・取引先 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">担当・取引先</div>
                                            <div class="detail-row"><span class="detail-label">営業担当</span><span class="detail-value"><?= htmlspecialchars($pj['sales_assignee'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">顧客名</span><span class="detail-value"><?= htmlspecialchars($pj['customer_name'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">ディーラー</span><span class="detail-value"><?= htmlspecialchars($pj['dealer_name'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">営業所</span><span class="detail-value"><?= htmlspecialchars($pj['office_name'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">ゼネコン</span><span class="detail-value"><?= htmlspecialchars($pj['general_contractor'] ?? '-') ?></span></div>
                                        </div>
                                        <!-- 現場情報 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">現場情報</div>
                                            <div class="detail-row"><span class="detail-label">現場名</span><span class="detail-value"><?= htmlspecialchars($pj['name'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">都道府県</span><span class="detail-value"><?= htmlspecialchars($pj['prefecture'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">住所</span><span class="detail-value"><?= htmlspecialchars($pj['address'] ?? '-') ?></span></div>
                                        </div>
                                        <!-- 商品情報 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">商品情報</div>
                                            <?php
                                            $catId = $pj['product_category'] ?? '';
                                            $catName = !empty($catId) ? ($categoryMap[$catId] ?? $catId) : '-';
                                            ?>
                                            <div class="detail-row"><span class="detail-label">製品名</span><span class="detail-value"><?= htmlspecialchars($catName) ?></span></div>
                                            <div class="detail-row"><span class="detail-label">メーカー</span><span class="detail-value"><?= htmlspecialchars($pj['maker'] ?? '-') ?></span></div>
                                            <?php
                                            // LEDサイズとLCDサイズを統合表示（色で区別）
                                            $ledSize = convertPanelToInch($pj['led_size'] ?? '', $panelToInch);
                                            $lcdSize = $pj['lcd_size'] ?? '';
                                            if (!empty($ledSize) && $ledSize !== '-') {
                                                echo '<div class="detail-row"><span class="detail-label">ディスプレイサイズ</span><span class="detail-value" style="color: #d32f2f; font-weight: bold;">LED ' . htmlspecialchars($ledSize) . 'インチ</span></div>';
                                            } elseif (!empty($lcdSize) && $lcdSize !== '-') {
                                                echo '<div class="detail-row"><span class="detail-label">ディスプレイサイズ</span><span class="detail-value" style="color: #1976d2; font-weight: bold;">LCD ' . htmlspecialchars($lcdSize) . 'インチ</span></div>';
                                            }
                                            ?>
                                            <div class="detail-row"><span class="detail-label">CMS/プレイヤー</span><span class="detail-value"><?= htmlspecialchars($pj['cms_player'] ?? '-') ?></span></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($pj['memo'])): ?>
                                    <div        class="mt-2 p-2 rounded-lg bg-warning-light">
                                        <strong     class="text-warning">メモ:</strong>
                                        <p     class="text-gray-700 mt-1"><?= nl2br(htmlspecialchars($pj['memo'])) ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detail-actions">
                                        <?php if (canEditCurrentPage()): ?>
                                        <button type="button" class="btn btn-primary btn-sm show-edit-modal-btn" data-pj-id="<?= htmlspecialchars($pj['id']) ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            編集
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm copy-project-btn" data-pj-id="<?= htmlspecialchars($pj['id']) ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            コピー
                                        </button>
                                        <?php endif; ?>
                                        <a href="troubles.php?pj=<?= urlencode($pj['id']) ?>" class="btn btn-secondary btn-sm">
                                            トラブル履歴 (<?= $troubleCount ?>)
                                        </a>
                                        <?php if (canDelete()): ?>
                                        <button type="button" class="btn btn-danger btn-sm delete-single-project-btn" data-pj-id="<?= htmlspecialchars($pj['id']) ?>">削除</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($filteredProjects)): ?>
                        <tr>
                            <td colspan="8"      class="text-center text-gray-500 p-3rem">
                                <?= (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterStatus) || !empty($filterAssignee)) ? '検索結果が見つかりませんでした' : 'データがありません' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="projectPagination"></div>
        </div>
        </form>

        <!-- 個別削除用フォーム（フォームネスト回避） -->
        <form id="singleDeleteForm" method="POST"  class="d-none">
            <?= csrfTokenField() ?>
            <input type="hidden" name="delete_pj" id="singleDeleteId" value="">
        </form>

        <?php else: ?>
        <!-- カード表示 -->
        <div class="project-cards-grid">
            <?php foreach ($filteredProjects as $idx => $pj): ?>
                <?php
                // タグを判定（tagフィールド優先、なければ現場名プレフィックスから判定）
                $siteName = $pj['name'] ?? '';
                $tag = $pj['tag'] ?? '';
                if (empty($tag)) {
                    if (strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false) {
                        $tag = 'レンタル';
                    } elseif (strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false) {
                        $tag = '販売';
                    }
                }
                $tagColor = '';
                $tagTextColor = '';
                if ($tag === 'レンタル') {
                    $tagColor = '#dbeafe';
                    $tagTextColor = '#1d4ed8';
                } elseif ($tag === '販売') {
                    $tagColor = '#d1fae5';
                    $tagTextColor = '#065f46';
                }
                ?>
                <div class="project-card show-card-detail-btn" data-pj-id="<?= htmlspecialchars($pj['id']) ?>">
                    <div class="project-card-header">
                        <div class="project-card-id"><?= htmlspecialchars($pj['id']) ?></div>
                        <?php if ($tag): ?>
                            <span        class="d-inline-block rounded text-xs font-semibold tag-sm" style="background: <?= htmlspecialchars($tagColor, ENT_QUOTES) ?>; color: <?= htmlspecialchars($tagTextColor, ENT_QUOTES) ?>"><?= $tag ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="project-card-body">
                        <div class="project-card-name">
                            <?= htmlspecialchars(trimSiteName($pj['name'])) ?>
                        </div>
                        <div class="project-card-customer"><?= htmlspecialchars($pj['customer_name'] ?? '-') ?></div>
                        <div class="project-card-meta">
                            <span class="project-card-meta-item">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?= htmlspecialchars($pj['sales_assignee'] ?? '-') ?>
                            </span>
                        </div>
                    </div>
                    <div class="project-card-footer">
                        <span       class="text-gray-500 text-085">
                            取引: <?= htmlspecialchars($pj['transaction_type'] ?? '-') ?>
                        </span>
                        <span        class="text-xs text-gray-400">クリックで詳細</span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($filteredProjects)): ?>
                <div        class="text-center text-gray-500 p-3rem" style="grid-column: 1 / -1;">
                    <?= (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterStatus) || !empty($filterAssignee)) ? '検索結果が見つかりませんでした' : 'データがありません' ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- カード詳細サイドパネル -->
<div class="card-detail-overlay" id="cardDetailOverlay" data-action="close-card-detail"></div>
<div class="card-detail-modal" id="cardDetailModal">
    <div class="card-detail-header">
        <h3 id="cardDetailTitle">案件詳細</h3>
        <button type="button" class="modal-close" data-action="close-card-detail">&times;</button>
    </div>
    <div class="card-detail-body" id="cardDetailBody">
        <!-- 動的に内容が入る -->
    </div>
    <div class="card-detail-footer" id="cardDetailFooter">
        <!-- 動的にボタンが入る -->
    </div>
</div>


<!-- 案件登録モーダル（詳細版） -->
<div id="addModal" class="modal">
    <div         class="modal-content overflow-y-auto modal-max">
        <div class="modal-header">
            <h3>案件登録</h3>
            <button type="button" class="modal-close" data-action="close-modal" data-modal-id="addModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="add_pj" value="1">
            <div class="modal-body">

                <!-- 基本情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">基本情報</h4>
                    <div    class="gap-2 grid grid-cols-2">
                        <div class="form-group">
                            <label for="custom_pj_number">P番号</label>
                            <input type="text" class="form-input" id="custom_pj_number" name="custom_pj_number"
                                   value="<?= htmlspecialchars($suggestedPjNumber ?: generateNextPjNumber(filterDeleted($data['projects']))) ?>"
                                   placeholder="自動生成">
                            <small   class="text-gray-666">P1, P2, P3... の形式で自動採番（変更可）</small>
                        </div>
                        <div class="form-group">
                            <label for="occurrence_date">案件発生日</label>
                            <input type="date" class="form-input" id="occurrence_date" name="occurrence_date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="transaction_type">取引形態</label>
                        <select class="form-select" id="transaction_type" name="transaction_type">
                            <option value="">選択してください</option>
                            <option value="販売">販売</option>
                            <option value="レンタル">レンタル</option>
                            <option value="保守">保守</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ステータス</label>
                        <select class="form-select" name="status">
                            <?php foreach ($PROJECT_STATUSES as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 担当・取引先情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">担当・取引先情報</h4>
                    <div    class="gap-2 grid grid-cols-2">
                        <div class="form-group">
                            <label for="sales_assignee">営業担当</label>
                            <?php if (empty($data['assignees'])): ?>
                                <input type="text" class="form-input" id="sales_assignee" name="sales_assignee" placeholder="担当者名を入力">
                                <small    class="text-orange">※ <a href="/pages/masters.php#assignees">マスタ管理</a>で担当者を登録すると選択式になります</small>
                            <?php else: ?>
                            <select class="form-select" id="sales_assignee" name="sales_assignee">
                                <option value="">選択してください</option>
                                <?php foreach ($data['assignees'] as $a): ?>
                                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="customer_name">顧客名</label>
                            <?php if (empty($data['customers'])): ?>
                                <input type="text" class="form-input" id="customer_name" name="customer_name" placeholder="顧客名を入力">
                                <small    class="text-orange">※ <a href="/pages/customers.php">顧客管理</a>で顧客を登録すると選択式になります</small>
                            <?php else: ?>
                            <select class="form-select" id="customer_name" name="customer_name">
                                <option value="">選択してください</option>
                                <?php foreach ($data['customers'] as $c): ?>
                                    <option value="<?= htmlspecialchars($c['companyName']) ?>"><?= htmlspecialchars($c['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="dealer_name">ディーラー担当者名</label>
                            <input type="text" class="form-input" id="dealer_name" name="dealer_name">
                        </div>
                        <div class="form-group">
                            <label for="general_contractor">ゼネコン名</label>
                            <input type="text" class="form-input" id="general_contractor" name="general_contractor">
                        </div>
                    </div>
                </div>

                <!-- 現場情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">現場情報</h4>
                    <div class="form-group">
                        <label for="site_name">現場名</label>
                        <input type="text" class="form-input" id="site_name" name="site_name">
                    </div>
                    <div        class="gap-2 grid grid-150-1fr">
                        <div class="form-group">
                            <label for="postal_code">郵便番号</label>
                            <input type="text" class="form-input" id="postal_code" name="postal_code" placeholder="例: 1000001">
                        </div>
                        <div class="form-group">
                            <label for="prefecture">設置場所（都道府県）</label>
                            <input type="text" class="form-input" id="prefecture" name="prefecture">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">設置場所住所</label>
                        <input type="text" class="form-input" id="address" name="address">
                    </div>
                    <div class="form-group">
                        <label for="shipping_address">発送先住所</label>
                        <input type="text" class="form-input" id="shipping_address" name="shipping_address">
                    </div>
                </div>

                <!-- 商品情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4        class="mb-2 text-256">商品情報</h4>
                    <div    class="gap-2 grid grid-cols-2">
                        <div class="form-group">
                            <label for="product_category">製品名</label>
                            <?php if (empty($data['productCategories'])): ?>
                                <input type="text" class="form-input" id="product_category" name="product_category" placeholder="製品名を入力">
                                <small    class="text-orange">※ <a href="/pages/masters.php?tab=categories">マスタ管理</a>で製品名を登録すると選択式になります</small>
                            <?php else: ?>
                            <select class="form-select js-product-category" id="product_category" name="product_category">
                                <option value="">製品名を選択</option>
                                <?php foreach ($data['productCategories'] as $cat):
                                    $makerIdsValue = $cat['maker_ids'] ?? null;
                                    if ($makerIdsValue === null) {
                                        $makerIdsValue = $cat['maker_id'] ? [$cat['maker_id']] : [];
                                    }
                                    $makerIdsJson = json_encode($makerIdsValue, JSON_UNESCAPED_UNICODE);
                                    if ($makerIdsJson === false) { $makerIdsJson = '[]'; }
                                ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>" data-maker-ids="<?= htmlspecialchars($makerIdsJson) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="maker">メーカー</label>
                            <?php
                            $manufacturers = filterDeleted($data['manufacturers'] ?? []);
                            if (empty($manufacturers)): ?>
                                <input type="text" class="form-input" id="maker" name="maker" placeholder="メーカー名を入力">
                                <small    class="text-orange">※ <a href="/pages/masters.php?tab=manufacturers">マスタ管理</a>でメーカーを登録すると選択式になります</small>
                            <?php else: ?>
                            <select class="form-select js-maker-select" id="maker" name="maker">
                                <option value="">メーカーを選択</option>
                                <?php foreach ($manufacturers as $m): ?>
                                    <option value="<?= htmlspecialchars($m['name']) ?>" data-maker-id="<?= htmlspecialchars($m['id']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div    class="gap-2 grid grid-cols-2">
                        <div class="form-group">
                            <label for="product_spec">製品仕様（自由記述）</label>
                            <input type="text" class="form-input" id="product_spec" name="product_spec" placeholder="自由に入力してください">
                        </div>
                        <div class="form-group">
                            <label for="led_size">LEDサイズ</label>
                            <input type="text" class="form-input" id="led_size" name="led_size" placeholder="例: 65">
                        </div>
                        <div class="form-group">
                            <label for="lcd_size">LCDサイズ</label>
                            <input type="text" class="form-input" id="lcd_size" name="lcd_size" placeholder="例: 55">
                        </div>
                        <div class="form-group">
                            <label for="cms_player">CMS/プレイヤー</label>
                            <input type="text" class="form-input" id="cms_player" name="cms_player">
                        </div>
                    </div>
                </div>

                <!-- メモ -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">メモ</h4>
                    <div class="form-group">
                        <textarea class="form-input" id="memo" name="memo" rows="4" placeholder="特記事項など"></textarea>
                    </div>
                </div>

                <!-- Google Chatスペース連携 -->
                <?php
                require_once __DIR__ . '/../api/google-chat.php';
                $gchat = new GoogleChatClient();
                if ($gchat->isConfigured()):
                ?>
                <div        class="mt-2 comment-section">
                    <h4    class="mb-2 text-gray-900">Google Chatスペース連携</h4>
                    <div class="form-group">
                        <label for="chat_space_id">紐付けるスペース</label>
                        <select class="form-select" id="chat_space_id" name="chat_space_id">
                            <option value="">読み込み中...</option>
                        </select>
                        <small   class="text-gray-666">
                            <strong>未選択の場合:</strong> 新しいスペースが自動作成されます<br>
                            <strong>選択した場合:</strong> 既存スペースに固定メンバーを追加します
                        </small>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal-id="addModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">確認画面へ</button>
            </div>
        </form>
    </div>
</div>

<!-- 担当者追加モーダル -->
<div id="assigneeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>担当者登録</h3>
            <button type="button" class="modal-close" data-action="close-modal" data-modal-id="assigneeModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="add_assignee" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label for="assignee_name">担当者名 *</label>
                    <input type="text" class="form-input" id="assignee_name" name="assignee_name" placeholder="担当者名を入力" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal-id="assigneeModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">登録</button>
            </div>
        </form>
    </div>
</div>

<!-- 案件編集モーダル -->
<div id="editModal" class="modal">
    <div         class="modal-content overflow-y-auto modal-max">
        <div class="modal-header">
            <h3>案件編集</h3>
            <button type="button" class="modal-close" data-action="close-modal" data-modal-id="editModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="update_pj" value="">
            <div class="modal-body">

                <!-- 基本情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">基本情報</h4>
                    <div    class="gap-2 grid grid-cols-2">
                        <div class="form-group">
                            <label>案件発生日</label>
                            <input type="date" class="form-input" name="occurrence_date">
                        </div>
                        <div class="form-group">
                            <label>取引形態</label>
                            <select class="form-select" name="transaction_type">
                                <option value="">選択してください</option>
                                <option value="販売">販売</option>
                                <option value="レンタル">レンタル</option>
                                <option value="保守">保守</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>ステータス</label>
                        <select class="form-select" name="status">
                            <?php foreach ($PROJECT_STATUSES as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 担当・取引先情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">担当・取引先情報</h4>
                    <div    class="gap-2 grid grid-cols-2">
                        <div class="form-group">
                            <label>営業担当</label>
                            <?php if (empty($data['assignees'])): ?>
                                <input type="text" class="form-input" name="sales_assignee" placeholder="担当者名を入力">
                            <?php else: ?>
                            <select class="form-select" name="sales_assignee">
                                <option value="">選択してください</option>
                                <?php foreach ($data['assignees'] as $a): ?>
                                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>顧客名</label>
                            <?php if (empty($data['customers'])): ?>
                                <input type="text" class="form-input" name="customer_name" placeholder="顧客名を入力">
                            <?php else: ?>
                            <select class="form-select" name="customer_name">
                                <option value="">選択してください</option>
                                <?php foreach ($data['customers'] as $c): ?>
                                    <option value="<?= htmlspecialchars($c['companyName']) ?>"><?= htmlspecialchars($c['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>ディーラー担当者名</label>
                            <input type="text" class="form-input" name="dealer_name">
                        </div>
                        <div class="form-group">
                            <label>ゼネコン名</label>
                            <input type="text" class="form-input" name="general_contractor">
                        </div>
                    </div>
                </div>

                <!-- 現場情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">現場情報</h4>
                    <div class="form-group">
                        <label>現場名 *</label>
                        <input type="text" class="form-input" name="site_name" required>
                    </div>
                    <div        class="gap-2 grid grid-150-1fr">
                        <div class="form-group">
                            <label>郵便番号</label>
                            <input type="text" class="form-input" name="postal_code" placeholder="例: 1000001">
                        </div>
                        <div class="form-group">
                            <label>都道府県</label>
                            <input type="text" class="form-input" name="prefecture">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>住所</label>
                        <input type="text" class="form-input" name="address">
                    </div>
                    <div class="form-group">
                        <label>発送先住所</label>
                        <input type="text" class="form-input" name="shipping_address">
                    </div>
                </div>

                <!-- 商品情報 -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4        class="mb-2 text-256">商品情報</h4>
                    <div    class="gap-2 grid grid-cols-2">
                        <div class="form-group">
                            <label>製品名</label>
                            <?php if (empty($data['productCategories'])): ?>
                                <input type="text" class="form-input" name="product_category" placeholder="製品名を入力">
                            <?php else: ?>
                            <select class="form-select js-product-category" name="product_category">
                                <option value="">製品名を選択</option>
                                <?php foreach ($data['productCategories'] as $cat):
                                    $makerIdsValue = $cat['maker_ids'] ?? null;
                                    if ($makerIdsValue === null) {
                                        $makerIdsValue = $cat['maker_id'] ? [$cat['maker_id']] : [];
                                    }
                                    $makerIdsJson = json_encode($makerIdsValue, JSON_UNESCAPED_UNICODE);
                                    if ($makerIdsJson === false) { $makerIdsJson = '[]'; }
                                ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>" data-maker-ids="<?= htmlspecialchars($makerIdsJson) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>メーカー</label>
                            <?php
                            $manufacturers = filterDeleted($data['manufacturers'] ?? []);
                            if (empty($manufacturers)): ?>
                                <input type="text" class="form-input" name="maker" placeholder="メーカー名を入力">
                            <?php else: ?>
                            <select class="form-select js-maker-select" name="maker">
                                <option value="">メーカーを選択</option>
                                <?php foreach ($manufacturers as $m): ?>
                                    <option value="<?= htmlspecialchars($m['name']) ?>" data-maker-id="<?= htmlspecialchars($m['id']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>製品仕様</label>
                            <input type="text" class="form-input" name="product_spec">
                        </div>
                        <div class="form-group">
                            <label>LEDサイズ</label>
                            <input type="text" class="form-input" name="led_size" placeholder="例: 65">
                        </div>
                        <div class="form-group">
                            <label>LCDサイズ</label>
                            <input type="text" class="form-input" name="lcd_size" placeholder="例: 55">
                        </div>
                        <div class="form-group">
                            <label>CMS/プレイヤー</label>
                            <input type="text" class="form-input" name="cms_player">
                        </div>
                    </div>
                </div>

                <!-- メモ -->
                <div    class="mb-3 border-b-2 pb-2">
                    <h4    class="mb-2 text-gray-900">メモ</h4>
                    <div class="form-group">
                        <textarea class="form-input" name="memo" rows="4" placeholder="特記事項など"></textarea>
                    </div>
                </div>

                <!-- Google Chatスペース連携（編集時） -->
                <?php if ($gchat->isConfigured()): ?>
                <div>
                    <h4    class="mb-2 text-gray-900">Google Chatスペース連携</h4>
                    <input type="hidden" name="edit_chat_space_id" id="edit_chat_space_id" value="">
                    <input type="hidden" name="edit_pending_chat_space" id="edit_pending_chat_space" value="">
                    <div class="form-group">
                        <label>紐付けるスペース</label>
                        <select class="form-select" id="edit_chat_space_select" data-action="edit-space-change">
                            <option value="">読み込み中...</option>
                        </select>
                        <small   class="text-gray-666" id="edit_space_status"></small>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-action="close-modal" data-modal-id="editModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<script<?= nonceAttr() ?>>
// ページネーション初期化
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('projectTable');
    if (table && table.querySelector('tbody tr.project-row')) {
        new Paginator({
            container: '#projectTable',
            itemSelector: 'tbody tr.project-row',
            perPage: 50,
            paginationTarget: '#projectPagination',
            groupAttribute: 'data-group'
        });
    }

    // イベントデリゲーション: すべてのdata-actionボタンにイベントリスナーを追加
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.getAttribute('data-action');

        // 各アクションに応じて処理
        switch(action) {
            case 'show-add-modal':
                showAddModal();
                break;
            case 'bulk-status-change':
                bulkStatusChange();
                break;
            case 'bulk-delete':
                bulkDelete();
                break;
            case 'toggle-sync-menu':
                toggleSyncMenu();
                break;
            case 'sync-from-spreadsheet':
                syncFromSpreadsheet();
                break;
            case 'clear-synced-data':
                clearSyncedData();
                break;
            case 'create-pj-room':
                e.stopPropagation();
                createPjRoom(target.getAttribute('data-pj-id'), target);
                break;
            case 'sort-table':
                sortTable(target.getAttribute('data-column'));
                break;
            case 'toggle-detail':
                const idx = target.closest('tr').getAttribute('data-idx');
                toggleDetail(parseInt(idx), e);
                break;
            case 'stop-propagation':
                e.stopPropagation();
                break;
            case 'show-card-detail':
                const cardPjId = target.getAttribute('data-pj-id');
                showCardDetail(cardPjId);
                break;
            case 'close-card-detail':
                closeCardDetail();
                break;
            case 'show-assignee-modal':
                showAssigneeModal();
                break;
            case 'close-modal':
                const modalId = target.getAttribute('data-modal-id');
                closeModal(modalId);
                break;
            case 'show-edit-and-close-card':
                const editClosePjId = target.getAttribute('data-pj-id');
                showEditModal(editClosePjId);
                closeCardDetail();
                break;
        }
    });

    // クラスベースのイベントリスナー（テーブル内の動的要素）
    document.addEventListener('click', function(e) {
        // 編集モーダル表示
        if (e.target.closest('.show-edit-modal-btn')) {
            e.stopPropagation();
            const btn = e.target.closest('.show-edit-modal-btn');
            const pjId = btn.getAttribute('data-pj-id');
            showEditModal(pjId);
            return;
        }

        // プロジェクトコピー
        if (e.target.closest('.copy-project-btn')) {
            e.stopPropagation();
            const btn = e.target.closest('.copy-project-btn');
            const pjId = btn.getAttribute('data-pj-id');
            copyProject(pjId);
            return;
        }

        // プロジェクト削除
        if (e.target.closest('.delete-single-project-btn')) {
            e.stopPropagation();
            const btn = e.target.closest('.delete-single-project-btn');
            const pjId = btn.getAttribute('data-pj-id');
            deleteSingleProject(pjId);
            return;
        }

        // カード詳細表示
        if (e.target.closest('.show-card-detail-btn')) {
            const card = e.target.closest('.show-card-detail-btn');
            const pjId = card.getAttribute('data-pj-id');
            showCardDetail(pjId);
            return;
        }
    });

    // changeイベント
    document.addEventListener('change', function(e) {
        const target = e.target;
        const action = target.getAttribute('data-action');

        if (action === 'filter-by-status') {
            filterByStatus(target.value);
        } else if (action === 'filter-by-assignee') {
            filterByAssignee(target.value);
        } else if (action === 'toggle-select-all') {
            toggleSelectAll(target);
        } else if (action === 'update-bulk-delete-btn') {
            updateBulkDeleteBtn();
        } else if (action === 'edit-space-change') {
            onEditSpaceChange();
        }
    });

    // submitイベント
    document.addEventListener('submit', function(e) {
        const target = e.target;
        const action = target.getAttribute('data-action');

        if (action === 'confirm-submit') {
            const message = target.getAttribute('data-message');
            if (!confirm(message)) {
                e.preventDefault();
            }
        }
    });

    // チェックボックスの変更イベント
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('project-checkbox')) {
            updateBulkDeleteBtn();
        }
    });
});

// escapeHtml は js/common-utils.js で定義済み

// プロジェクトデータをJSで保持
const projectsData = <?= json_encode($filteredProjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// カテゴリID → カテゴリ名のマップ（製品名表示用）
const categoryMap = <?= json_encode($categoryMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// 現場名を一覧表示用に整形（【〇】除去 + アンダーバー以降除去）
function trimSiteName(name) {
    if (!name) return '';
    // 【〇】形式のプレフィックスを全て除去（複数連続も対応）
    name = name.replace(/^(【[^】]*】)+/, '').trim();
    // アンダーバー以降を除去
    const idx = name.indexOf('_');
    return idx !== -1 ? name.substring(0, idx) : name;
}

// カテゴリIDを名前に変換するヘルパー
function getCategoryName(catId) {
    if (!catId) return '';
    return categoryMap[catId] || catId;
}

function showAddModal() {
    openModal('addModal');
    // モーダル表示時にスペース一覧を読み込み（遅延読み込み）
    loadChatSpacesForProject();
}

// スプレッドシートから同期
async function syncFromSpreadsheet() {
    if (!confirm('スプレッドシートから案件情報を同期しますか？\n\n・新規案件は追加されます\n・既存案件の現場名は更新されます')) {
        return;
    }

    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span        class="align-center gap-05 d-inline-flex"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"     class="spin"><path d="M21 12a9 9 0 1 1-6.22-8.57"/></svg>同期中...</span>';

    try {
        const result = await (await fetch('../api/spreadsheet-projects.php?action=sync&mode=merge')).json();
        if (result.success) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            window.location.href = 'master.php?synced=1&added_count=' + result.added + '&updated_count=' + result.updated;
        } else {
            alert('同期エラー: ' + result.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        alert('通信エラー: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function showAssigneeModal() {
    openModal('assigneeModal');
}

// closeModal は common-utils.js を使用

// スプシ連携メニューの表示/非表示
function toggleSyncMenu() {
    const menu = document.getElementById('syncMenu');
    if (menu.classList.contains('d-none')) {
        menu.classList.remove('d-none');
    } else {
        menu.classList.add('d-none');
    }
}

// 同期データを削除
async function clearSyncedData() {
    document.getElementById('syncMenu').classList.add('d-none');

    if (!confirm('スプレッドシートから同期した案件データを削除しますか？\n\n※ 同期前から存在していた案件は削除されません')) {
        return;
    }

    try {
        const result = await (await fetch('../api/spreadsheet-projects.php?action=clear')).json();
        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('削除エラー: ' + result.message);
        }
    } catch (error) {
        alert('通信エラー: ' + error.message);
    }
}

// PJチャットルーム作成
async function createPjRoom(pjId, btn) {
    if (!pjId || btn.disabled) return;
    btn.disabled = true;
    btn.style.opacity = '0.4';
    try {
        // CSRFトークンはページ内フォームのhidden inputから取得
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        const res = await fetch('../api/master.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ action: 'create_pj_room', pj_id: pjId }),
        });
        const data = await res.json();
        if (data.success) {
            const roomId = data.data?.room_id;
            // ボタンをチャットリンクに差し替え
            const link = document.createElement('a');
            link.href = '/pages/chat.php#' + encodeURIComponent(roomId);
            link.className = 'project-internal-chat-link';
            link.setAttribute('data-action', 'stop-propagation');
            link.title = '社内チャットを開く';
            link.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" class="internal-chat-icon"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>';
            btn.replaceWith(link);
            if (typeof showToast === 'function') showToast('チャットルームを作成しました', 'success');
        } else {
            alert('エラー: ' + (data.message || data.error || '不明なエラー'));
            btn.disabled = false;
            btn.style.opacity = '';
        }
    } catch (e) {
        alert('通信エラー: ' + e.message);
        btn.disabled = false;
        btn.style.opacity = '';
    }
}

// ドロップダウンメニューを閉じる（クリック外）
document.addEventListener('click', function(event) {
    const syncMenu = document.getElementById('syncMenu');
    if (syncMenu && !event.target.closest('.dropdown')) {
        syncMenu.classList.add('d-none');
    }
});

// トラブル対応から来た場合は自動でモーダルを開く
<?php if (!empty($suggestedPjNumber)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showAddModal();
});
<?php endif; ?>

// ソート機能（PHPのデフォルト値と同期）
const _phpSortColumn = <?= json_encode($sortBy) ?>;
const _phpSortOrder  = <?= json_encode($sortOrder) ?>;

function sortTable(column) {
    const urlParams = new URLSearchParams(window.location.search);
    // URLパラメータがない場合はPHP側のデフォルト値を使用
    const currentSort  = urlParams.get('sort')  || _phpSortColumn;
    const currentOrder = urlParams.get('order') || _phpSortOrder;

    let newOrder;
    if (currentSort === column) {
        // 同じ列をクリック → 昇順/降順を反転
        newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
    } else {
        // 別の列をクリック → 昇順から開始
        newOrder = 'asc';
    }

    urlParams.set('sort', column);
    urlParams.set('order', newOrder);
    window.location.search = urlParams.toString();
}

// 詳細行の展開/折りたたみ
function toggleDetail(idx, event) {
    if (event.target.type === 'checkbox') return;

    const row = document.querySelector(`.project-row[data-idx="${idx}"]`);
    const detailRow = document.getElementById(`detail-${idx}`);

    // 他の展開中の詳細を閉じる
    document.querySelectorAll('.project-detail-row.show').forEach(el => {
        if (el.id !== `detail-${idx}`) {
            el.classList.remove('show');
        }
    });
    document.querySelectorAll('.project-row.expanded').forEach(el => {
        if (el.dataset.idx !== String(idx)) {
            el.classList.remove('expanded');
        }
    });

    // 現在の行をトグル
    row.classList.toggle('expanded');
    detailRow.classList.toggle('show');

}

// 編集モーダル表示
function showEditModal(pjId) {
    event.stopPropagation();
    const pj = projectsData.find(p => p.id === pjId);
    if (!pj) return;

    // 編集モーダルのフォームに値をセット
    const modal = document.getElementById('editModal');
    modal.querySelector('[name="update_pj"]').value = pj.id;
    modal.querySelector('[name="occurrence_date"]').value = pj.occurrence_date || '';
    modal.querySelector('[name="transaction_type"]').value = pj.transaction_type || '';
    modal.querySelector('[name="status"]').value = pj.status || '案件発生';
    modal.querySelector('[name="sales_assignee"]').value = pj.sales_assignee || '';
    modal.querySelector('[name="customer_name"]').value = pj.customer_name || '';
    modal.querySelector('[name="dealer_name"]').value = pj.dealer_name || '';
    modal.querySelector('[name="general_contractor"]').value = pj.general_contractor || '';
    modal.querySelector('[name="site_name"]').value = pj.name || '';
    modal.querySelector('[name="postal_code"]').value = pj.postal_code || '';
    modal.querySelector('[name="prefecture"]').value = pj.prefecture || '';
    modal.querySelector('[name="address"]').value = pj.address || '';
    modal.querySelector('[name="shipping_address"]').value = pj.shipping_address || '';
    modal.querySelector('[name="product_category"]').value = pj.product_category || '';
    modal.querySelector('[name="maker"]').value = pj.maker || '';
    modal.querySelector('[name="product_spec"]').value = pj.product_spec || '';
    modal.querySelector('[name="led_size"]').value = pj.led_size || '';
    modal.querySelector('[name="lcd_size"]').value = pj.lcd_size || '';
    modal.querySelector('[name="cms_player"]').value = pj.cms_player || '';
    modal.querySelector('[name="memo"]').value = pj.memo || '';

    // Google Chatスペース連携の読み込み
    loadChatSpacesForEdit(pj.chat_space_id || '');

    openModal('editModal');
}

// カード詳細表示
function showCardDetail(pjId) {
    const pj = projectsData.find(p => p.id === pjId);
    if (!pj) return;

    document.getElementById('cardDetailTitle').textContent = pj.id + ' - ' + trimSiteName(pj.name);

    // 基本情報セクション
    let basicInfoRows = `<div class="detail-row"><span class="detail-label">案件番号</span><span class="detail-value">${escapeHtml(pj.id)}</span></div>`;
    if (pj.occurrence_date) {
        basicInfoRows += `<div class="detail-row"><span class="detail-label">発生日</span><span class="detail-value">${formatDate(pj.occurrence_date)}</span></div>`;
    }
    if (pj.transaction_type) {
        basicInfoRows += `<div class="detail-row"><span class="detail-label">取引形態</span><span class="detail-value">${escapeHtml(pj.transaction_type)}</span></div>`;
    }

    // 担当・取引先セクション
    let assigneeRows = '';
    if (pj.sales_assignee) {
        assigneeRows += `<div class="detail-row"><span class="detail-label">営業担当</span><span class="detail-value">${escapeHtml(pj.sales_assignee)}</span></div>`;
    }
    if (pj.customer_name) {
        assigneeRows += `<div class="detail-row"><span class="detail-label">顧客名</span><span class="detail-value">${escapeHtml(pj.customer_name)}</span></div>`;
    }
    if (pj.dealer_name) {
        assigneeRows += `<div class="detail-row"><span class="detail-label">ディーラー</span><span class="detail-value">${escapeHtml(pj.dealer_name)}</span></div>`;
    }
    if (pj.office_name) {
        assigneeRows += `<div class="detail-row"><span class="detail-label">営業所</span><span class="detail-value">${escapeHtml(pj.office_name)}</span></div>`;
    }
    if (pj.general_contractor) {
        assigneeRows += `<div class="detail-row"><span class="detail-label">ゼネコン</span><span class="detail-value">${escapeHtml(pj.general_contractor)}</span></div>`;
    }

    // 現場情報セクション
    let siteRows = '';
    if (pj.name) {
        siteRows += `<div class="detail-row"><span class="detail-label">現場名</span><span class="detail-value">${escapeHtml(pj.name)}</span></div>`;
    }
    if (pj.prefecture) {
        siteRows += `<div class="detail-row"><span class="detail-label">都道府県</span><span class="detail-value">${escapeHtml(pj.prefecture)}</span></div>`;
    }
    if (pj.address) {
        siteRows += `<div class="detail-row"><span class="detail-label">住所</span><span class="detail-value">${escapeHtml(pj.address)}</span></div>`;
    }

    // 商品情報セクション
    let productRows = '';
    const catName = getCategoryName(pj.product_category);
    if (catName) {
        productRows += `<div class="detail-row"><span class="detail-label">製品名</span><span class="detail-value">${escapeHtml(catName)}</span></div>`;
    }
    if (pj.maker) {
        productRows += `<div class="detail-row"><span class="detail-label">メーカー</span><span class="detail-value">${escapeHtml(pj.maker)}</span></div>`;
    }
    // LEDサイズとLCDサイズを統合表示（色で区別）
    if (pj.led_size && pj.led_size !== '-') {
        productRows += `<div class="detail-row"><span class="detail-label">ディスプレイサイズ</span><span class="detail-value" style="color: #d32f2f; font-weight: bold;">LED ${escapeHtml(pj.led_size)}インチ</span></div>`;
    } else if (pj.lcd_size && pj.lcd_size !== '-') {
        productRows += `<div class="detail-row"><span class="detail-label">ディスプレイサイズ</span><span class="detail-value" style="color: #1976d2; font-weight: bold;">LCD ${escapeHtml(pj.lcd_size)}インチ</span></div>`;
    }
    if (pj.cms_player) {
        productRows += `<div class="detail-row"><span class="detail-label">CMS/プレイヤー</span><span class="detail-value">${escapeHtml(pj.cms_player)}</span></div>`;
    }

    let html = `
        <div class="detail-section mb-2">
            <div class="detail-section-title">基本情報</div>
            ${basicInfoRows}
        </div>
        ${assigneeRows ? `<div class="detail-section mb-2">
            <div class="detail-section-title">担当・取引先</div>
            ${assigneeRows}
        </div>` : ''}
        ${siteRows ? `<div class="detail-section mb-2">
            <div class="detail-section-title">現場情報</div>
            ${siteRows}
        </div>` : ''}
        ${productRows ? `<div class="detail-section mb-2">
            <div class="detail-section-title">商品情報</div>
            ${productRows}
        </div>` : ''}
        ${pj.memo ? `
        <div class="detail-section bg-warning-light">
            <div class="detail-section-title text-warning">メモ</div>
            <p class="m-0 whitespace-pre-wrap">${escapeHtml(pj.memo)}</p>
        </div>
        ` : ''}
    `;

    document.getElementById('cardDetailBody').innerHTML = html;

    let footerHtml = '';
    <?php if (canEditCurrentPage()): ?>
    footerHtml += `<button type="button" class="btn btn-primary btn-sm" data-action="show-edit-and-close-card" data-pj-id="${escapeHtml(pj.id)}">編集</button>`;
    <?php endif; ?>
    document.getElementById('cardDetailFooter').innerHTML = footerHtml;

    document.getElementById('cardDetailOverlay').classList.add('show');
    document.getElementById('cardDetailModal').classList.add('show');
}

function closeCardDetail() {
    document.getElementById('cardDetailOverlay').classList.remove('show');
    document.getElementById('cardDetailModal').classList.remove('show');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
}

// escapeHtml は js/common-utils.js で定義済み

// 一括削除関連
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.project-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    const checkboxes = document.querySelectorAll('.project-checkbox:checked');
    const bulkActionBar = document.getElementById('bulkActionBar');
    const normalActionBar = document.getElementById('normalActionBar');
    const bulkSelectedCount = document.getElementById('bulkSelectedCount');

    if (checkboxes.length > 0) {
        bulkActionBar.style.display = 'flex';
        normalActionBar.style.display = 'none';
        bulkSelectedCount.textContent = `${checkboxes.length}件選択中`;
    } else {
        bulkActionBar.style.display = 'none';
        normalActionBar.style.display = 'flex';
    }

    // 全選択チェックボックスの状態を更新
    const allCheckboxes = document.querySelectorAll('.project-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        selectAllCheckbox.checked = checkboxes.length === allCheckboxes.length;
    }
}

function bulkStatusChange() {
    const status = document.getElementById('bulkStatusSelect').value;
    if (!status) {
        alert('変更先のステータスを選択してください');
        return;
    }
    const checkboxes = document.querySelectorAll('.project-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('案件を選択してください');
        return;
    }
    if (!confirm(`選択した${checkboxes.length}件のステータスを「${status}」に変更しますか？`)) {
        return;
    }
    const form = document.getElementById('bulkStatusForm');
    document.getElementById('bulkStatusValue').value = status;

    // 既存のproject_ids[]を削除（重複防止）
    form.querySelectorAll('input[name="project_ids[]"]').forEach(input => input.remove());

    // チェックされた案件IDをフォームに追加
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'project_ids[]';
        input.value = cb.value;
        form.appendChild(input);
    });
    form.submit();
}

function bulkDelete() {
    const checkboxes = document.querySelectorAll('.project-checkbox:checked');

    if (checkboxes.length === 0) {
        alert('削除する案件を選択してください');
        return;
    }

    const count = checkboxes.length;
    if (confirm(`選択した${count}件の案件を削除しますか？\n\nこの操作は取り消せません。`)) {
        const form = document.getElementById('bulkDeleteForm');
        // 既存のproject_ids[]を削除（重複防止）
        form.querySelectorAll('input[name="project_ids[]"]').forEach(input => input.remove());

        // チェックされた案件IDをフォームに追加
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'project_ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });
        form.submit();
    }
}

// 個別削除
function deleteSingleProject(pjId) {
    if (confirm('この案件を削除しますか？')) {
        document.getElementById('singleDeleteId').value = pjId;
        document.getElementById('singleDeleteForm').submit();
    }
}

// ステータスフィルタ
function filterByStatus(status) {
    const urlParams = new URLSearchParams(window.location.search);
    if (status) {
        urlParams.set('filter_status', status);
    } else {
        urlParams.delete('filter_status');
    }
    window.location.search = urlParams.toString();
}

// 担当者フィルタ
function filterByAssignee(assignee) {
    const urlParams = new URLSearchParams(window.location.search);
    if (assignee) {
        urlParams.set('filter_assignee', assignee);
    } else {
        urlParams.delete('filter_assignee');
    }
    window.location.search = urlParams.toString();
}

// 案件コピー
function copyProject(pjId) {
    event.stopPropagation();
    const pj = projectsData.find(p => p.id === pjId);
    if (!pj) return;

    const modal = document.getElementById('addModal');
    modal.querySelector('[name="custom_pj_number"]').value = '';
    modal.querySelector('[name="occurrence_date"]').value = new Date().toISOString().split('T')[0];
    modal.querySelector('[name="transaction_type"]').value = pj.transaction_type || '';
    modal.querySelector('[name="status"]').value = pj.status || '案件発生';
    modal.querySelector('[name="sales_assignee"]').value = pj.sales_assignee || '';
    modal.querySelector('[name="customer_name"]').value = pj.customer_name || '';
    modal.querySelector('[name="dealer_name"]').value = pj.dealer_name || '';
    modal.querySelector('[name="general_contractor"]').value = pj.general_contractor || '';
    modal.querySelector('[name="site_name"]').value = (pj.name || '') + ' (コピー)';
    modal.querySelector('[name="postal_code"]').value = pj.postal_code || '';
    modal.querySelector('[name="prefecture"]').value = pj.prefecture || '';
    modal.querySelector('[name="address"]').value = pj.address || '';
    modal.querySelector('[name="shipping_address"]').value = pj.shipping_address || '';
    modal.querySelector('[name="product_category"]').value = pj.product_category || '';
    modal.querySelector('[name="maker"]').value = pj.maker || '';
    modal.querySelector('[name="product_spec"]').value = pj.product_spec || '';
    modal.querySelector('[name="memo"]').value = pj.memo || '';

    openModal('addModal');
    loadChatSpacesForProject();
}

// 製品名選択時にメーカーselectを絞り込み＋自動セット
document.querySelectorAll('.js-product-category').forEach(function(select) {
    select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        let makerIds = [];
        try {
            makerIds = JSON.parse(selectedOption.dataset.makerIds || '[]');
        } catch(e) { makerIds = []; }

        const form = this.closest('form');
        if (!form) return;
        const makerSelect = form.querySelector('.js-maker-select');
        if (!makerSelect) return;

        // 全optionの表示/非表示を切り替え
        let firstVisibleIdx = -1;
        Array.from(makerSelect.options).forEach(function(opt, i) {
            if (opt.value === '') return; // 「選択してください」は常に表示
            if (makerIds.length === 0 || makerIds.includes(opt.dataset.makerId)) {
                opt.style.display = '';
                if (firstVisibleIdx === -1) firstVisibleIdx = i;
            } else {
                opt.style.display = 'none';
            }
        });

        // 紐づきがあれば最初の1件を自動選択、なければ空に戻す
        if (makerIds.length > 0 && firstVisibleIdx !== -1) {
            makerSelect.selectedIndex = firstVisibleIdx;
        } else if (makerIds.length === 0) {
            makerSelect.selectedIndex = 0;
        }
    });
});

// ESCキーでモーダル/サイドパネルを閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCardDetail();
        closeModal('addModal');
        closeModal('editModal');
        closeModal('assigneeModal');
    }
});

// ===== Google Chatスペース選択 =====
let chatSpacesLoaded = false;
let chatSpacesCache = null;

// スペース一覧を読み込み（案件登録用）- モーダル表示時に呼び出し
async function loadChatSpacesForProject() {
    if (chatSpacesLoaded) return; // 既に読み込み済みなら何もしない

    const select = document.getElementById('chat_space_id');
    if (!select) return;

    select.innerHTML = '<option value="">読み込み中...</option>';

    try {
        const data = await (await fetch(location.origin + '/api/alcohol-chat-sync.php?action=get_spaces')).json();
        if (data.error) {
            select.innerHTML = '<option value="">エラー: ' + escapeHtml(data.error) + '</option>';
            return;
        }

        const spaces = data.spaces || [];
        chatSpacesCache = spaces; // キャッシュに保存

        select.innerHTML = '<option value="">🆕 新しいスペースを自動作成</option>';
        spaces.forEach(space => {
            const opt = document.createElement('option');
            opt.value = space.name;
            opt.textContent = space.displayName;
            select.appendChild(opt);
        });
        chatSpacesLoaded = true; // 読み込み完了
    } catch (err) {
        select.innerHTML = '<option value="">読み込みエラー</option>';
    }
}

// 編集モーダル用：スペース一覧を読み込み
async function loadChatSpacesForEdit(currentSpaceId) {
    const select = document.getElementById('edit_chat_space_select');
    const statusEl = document.getElementById('edit_space_status');
    if (!select) return;

    // キャッシュがあれば使用
    if (chatSpacesCache) {
        populateEditSpaceSelect(chatSpacesCache, currentSpaceId);
        return;
    }

    select.innerHTML = '<option value="">読み込み中...</option>';

    try {
        const data = await (await fetch(location.origin + '/api/alcohol-chat-sync.php?action=get_spaces')).json();
        if (data.error) {
            select.innerHTML = '<option value="">エラー: ' + escapeHtml(data.error) + '</option>';
            return;
        }

        const spaces = data.spaces || [];
        chatSpacesCache = spaces;
        populateEditSpaceSelect(spaces, currentSpaceId);
    } catch (err) {
        select.innerHTML = '<option value="">読み込みエラー</option>';
    }
}

// 編集モーダル用：セレクトボックスを構築
function populateEditSpaceSelect(spaces, currentSpaceId) {
    const select = document.getElementById('edit_chat_space_select');
    const statusEl = document.getElementById('edit_space_status');
    const hiddenSpaceId = document.getElementById('edit_chat_space_id');
    const hiddenPending = document.getElementById('edit_pending_chat_space');

    if (!select) return;

    select.innerHTML = '';

    // 現在紐付けられているスペースがある場合
    if (currentSpaceId) {
        const currentSpace = spaces.find(s => s.name === currentSpaceId);
        const currentOpt = document.createElement('option');
        currentOpt.value = '__current__';
        currentOpt.textContent = '✓ ' + (currentSpace ? currentSpace.displayName : currentSpaceId) + ' （現在の紐付け）';
        select.appendChild(currentOpt);
        statusEl.innerHTML = '<strong     class="text-green">紐付け済み</strong>';
        hiddenSpaceId.value = currentSpaceId;
        hiddenPending.value = '';

        // 紐づけ解除オプション
        const unlinkOpt = document.createElement('option');
        unlinkOpt.value = '__unlink__';
        unlinkOpt.textContent = '❌ 紐づけを解除';
        select.appendChild(unlinkOpt);
    } else {
        // 未紐付けの場合
        const noLinkOpt = document.createElement('option');
        noLinkOpt.value = '__none__';
        noLinkOpt.textContent = '紐づけなし';
        select.appendChild(noLinkOpt);
        statusEl.innerHTML = '<strong     class="text-gray">未紐付け</strong>';
        hiddenSpaceId.value = '';
        hiddenPending.value = '';

        // 新規作成オプション
        const newOpt = document.createElement('option');
        newOpt.value = '__new__';
        newOpt.textContent = '🆕 新しいスペースを自動作成';
        select.appendChild(newOpt);
    }

    // 区切り線
    const separator = document.createElement('option');
    separator.disabled = true;
    separator.textContent = '──────────';
    select.appendChild(separator);

    // 既存スペース一覧
    spaces.forEach(space => {
        if (space.name !== currentSpaceId) {
            const opt = document.createElement('option');
            opt.value = space.name;
            opt.textContent = space.displayName;
            select.appendChild(opt);
        }
    });

    // 新規作成オプション（既に紐付けがある場合のみ追加）
    if (currentSpaceId) {
        const newOpt = document.createElement('option');
        newOpt.value = '__new__';
        newOpt.textContent = '🆕 新しいスペースを自動作成';
        select.appendChild(newOpt);
    }
}

// 編集モーダル：スペース選択変更時
function onEditSpaceChange() {
    const select = document.getElementById('edit_chat_space_select');
    const statusEl = document.getElementById('edit_space_status');
    const hiddenSpaceId = document.getElementById('edit_chat_space_id');
    const hiddenPending = document.getElementById('edit_pending_chat_space');

    if (!select) return;

    const value = select.value;

    if (value === '__current__') {
        // 現在の紐付けを維持
        statusEl.innerHTML = '<strong     class="text-green">紐付け済み</strong>';
        // hiddenSpaceIdは既に設定されているのでそのまま
        hiddenPending.value = '';
    } else if (value === '__unlink__') {
        // 紐づけ解除
        statusEl.innerHTML = '<strong     class="text-danger">解除</strong> - 保存時に紐づけが解除されます';
        hiddenSpaceId.value = '__unlink__';
        hiddenPending.value = '';
    } else if (value === '__none__') {
        // 紐づけなし（現状維持）
        statusEl.innerHTML = '<strong     class="text-gray">未紐付け</strong>';
        hiddenSpaceId.value = '';
        hiddenPending.value = '';
    } else if (value === '__new__') {
        // 新規作成
        statusEl.innerHTML = '<strong    class="text-blue">新規作成</strong> - 保存時に新しいスペースが作成されます';
        hiddenSpaceId.value = '';
        hiddenPending.value = '__auto__';
    } else if (value) {
        // 既存スペースを選択
        statusEl.innerHTML = '<strong    class="text-blue">変更</strong> - 保存時に選択したスペースに紐付けます';
        hiddenSpaceId.value = value;
        hiddenPending.value = '';
    }
}
</script>

<?php
// 非同期Chat作成処理
$asyncChatProjectId = $_GET['async_chat'] ?? '';
$asyncSpaceName = $_GET['space_name'] ?? '';
$asyncExistingSpace = $_GET['existing_space'] ?? '';

if (!empty($asyncChatProjectId)):
?>
<script<?= nonceAttr() ?>>
// 非同期でGoogle Chatスペースを作成/メンバー追加
(function() {
    const projectId = <?= json_encode($asyncChatProjectId) ?>;
    const spaceName = <?= json_encode($asyncSpaceName) ?>;
    const existingSpaceId = <?= json_encode($asyncExistingSpace) ?>;
    const csrfToken = <?= json_encode(generateCsrfToken()) ?>;

    // 非同期処理を実行
    (async () => {
        try {
            await (await fetch('/api/async-chat-space.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    project_id: projectId,
                    space_name: spaceName,
                    existing_space_id: existingSpaceId
                })
            })).json();
        } catch (e) {
            // バックグラウンド処理のため、エラーは無視
        }
    })();
})();
</script>
<?php endif; ?>

</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
