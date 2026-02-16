<?php
/**
 * Google Drive API エンドポイント
 * フォルダ内容の遅延読み込み用
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/google-drive.php';

header('Content-Type: application/json; charset=utf-8');

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 編集者以上のみアクセス可能
if (!canEdit()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$driveClient = new GoogleDriveClient();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// POST時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

try {
    if (!$driveClient->isConfigured()) {
        throw new Exception('Google Drive is not configured');
    }

    switch ($action) {
        case 'list_folder':
            // フォルダ内容を取得
            $folderId = $_GET['folder_id'] ?? '';
            if (empty($folderId)) {
                throw new Exception('folder_id is required');
            }
            $contents = $driveClient->listFolderContents($folderId);
            echo json_encode([
                'success' => true,
                'folders' => $contents['folders'] ?? [],
                'files' => $contents['files'] ?? []
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'list_periods':
            // 期フォルダ一覧を取得
            $syncFolder = $driveClient->getSyncFolder();
            if (!$syncFolder || empty($syncFolder['id'])) {
                throw new Exception('Sync folder is not configured');
            }
            $contents = $driveClient->listFolderContents($syncFolder['id']);
            $periodFolders = [];
            foreach ($contents['folders'] as $folder) {
                if (preg_match('/^\d+期_/', $folder['name'])) {
                    $periodFolders[] = $folder;
                }
            }
            usort($periodFolders, fn($a, $b) => strcmp($b['name'], $a['name']));
            echo json_encode([
                'success' => true,
                'periods' => $periodFolders
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'list_periods_with_months':
            // 期フォルダ一覧と最新期の月次フォルダを一度に取得（初期表示高速化用）
            $syncFolder = $driveClient->getSyncFolder();
            if (!$syncFolder || empty($syncFolder['id'])) {
                throw new Exception('Sync folder is not configured');
            }
            $contents = $driveClient->listFolderContents($syncFolder['id']);
            $periodFolders = [];
            foreach ($contents['folders'] as $folder) {
                if (preg_match('/^\d+期_/', $folder['name'])) {
                    $periodFolders[] = $folder;
                }
            }
            usort($periodFolders, fn($a, $b) => strcmp($b['name'], $a['name']));

            // 最新期の月次フォルダも取得
            $monthlyFolders = [];
            if (!empty($periodFolders)) {
                $latestPeriodId = $periodFolders[0]['id'];
                $periodContents = $driveClient->listFolderContents($latestPeriodId);
                foreach ($periodContents['folders'] as $folder) {
                    if (preg_match('/^\d{4}_月次資料$/', $folder['name'])) {
                        $monthlyFolders[] = $folder;
                    }
                }
                usort($monthlyFolders, fn($a, $b) => strcmp($b['name'], $a['name']));
            }

            echo json_encode([
                'success' => true,
                'periods' => $periodFolders,
                'months' => $monthlyFolders,
                'latestPeriodId' => $periodFolders[0]['id'] ?? null
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'list_months':
            // 月次フォルダ一覧を取得
            $periodId = $_GET['period_id'] ?? '';
            if (empty($periodId)) {
                throw new Exception('period_id is required');
            }
            $contents = $driveClient->listFolderContents($periodId);
            $monthlyFolders = [];
            foreach ($contents['folders'] as $folder) {
                if (preg_match('/^\d{4}_月次資料$/', $folder['name'])) {
                    $monthlyFolders[] = $folder;
                }
            }
            usort($monthlyFolders, fn($a, $b) => strcmp($b['name'], $a['name']));
            echo json_encode([
                'success' => true,
                'months' => $monthlyFolders
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'file_info':
            // ファイル情報を取得
            $fileId = $_GET['file_id'] ?? '';
            if (empty($fileId)) {
                throw new Exception('file_id is required');
            }
            $info = $driveClient->getFileInfo($fileId);
            echo json_encode([
                'success' => true,
                'file' => $info
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'sync_folder':
            // 連携フォルダ情報を取得
            $syncFolder = $driveClient->getSyncFolder();
            echo json_encode([
                'success' => true,
                'sync_folder' => $syncFolder
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'rename_file':
            // ファイル名を変更
            $fileId = $_POST['file_id'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            if (empty($fileId) || empty($newName)) {
                throw new Exception('file_id and new_name are required');
            }
            $result = $driveClient->renameFile($fileId, $newName);
            echo json_encode([
                'success' => true,
                'file' => $result
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'rename_bulk':
            // 一括リネーム
            $renames = json_decode($_POST['renames'] ?? '[]', true);
            if (empty($renames)) {
                throw new Exception('renames array is required');
            }
            $results = [];
            $errors = [];
            foreach ($renames as $item) {
                try {
                    $fileId = $item['file_id'] ?? '';
                    $newName = $item['new_name'] ?? '';
                    if (!empty($fileId) && !empty($newName)) {
                        $result = $driveClient->renameFile($fileId, $newName);
                        $results[] = ['file_id' => $fileId, 'new_name' => $newName, 'success' => true];
                    }
                } catch (Exception $e) {
                    $errors[] = ['file_id' => $fileId, 'error' => $e->getMessage()];
                }
            }
            echo json_encode([
                'success' => true,
                'renamed' => count($results),
                'errors' => $errors
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
