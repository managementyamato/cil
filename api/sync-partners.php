<?php
/**
 * MFクラウド請求書から取引先マスタを同期するAPI (バックグラウンドジョブ版)
 *
 * 設計:
 * - POST ?action=start: getPartners() を呼び出して全取引先を取得 (1〜5秒)、
 *                       それぞれの部門情報取得を pending キューに入れて即時返答
 * - GET  ?action=process: pending から 5 件 pop して getPartnerDepartments() を呼び、
 *                         結果を accumulator に積む (~2〜3秒)
 *                         pending が尽きたら最終マージ (data.customers に統合) + saveData
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/mf-api.php';
require_once __DIR__ . '/../functions/encryption.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['GET', 'POST'],
]);

if (!canEdit()) {
    errorResponse('編集権限が必要です', 403);
}

const SYNC_PARTNERS_CHUNK_SIZE = 5; // 1ポーリングで処理する取引先数

$jobFile = __DIR__ . '/../data/background-jobs.json';

function syncPartLoadJobs() {
    global $jobFile;
    if (!file_exists($jobFile)) return [];
    $fp = @fopen($jobFile, 'r');
    if (!$fp) return [];
    if (flock($fp, LOCK_SH)) {
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return json_decode($content, true) ?: [];
    }
    fclose($fp);
    return [];
}
function syncPartSaveJobs(array $jobs) {
    global $jobFile;
    if (!is_dir(dirname($jobFile))) @mkdir(dirname($jobFile), 0755, true);
    $fp = @fopen($jobFile, 'c');
    if (!$fp) return false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp); flock($fp, LOCK_UN); fclose($fp);
        return true;
    }
    fclose($fp);
    return false;
}
function syncPartUpdateJob($jobId, array $updates) {
    $jobs = syncPartLoadJobs();
    if (!isset($jobs[$jobId])) return false;
    // array_replace_recursive の numeric-array バグ回避: 浅い replace
    $jobs[$jobId] = array_replace($jobs[$jobId], $updates);
    return syncPartSaveJobs($jobs);
}

/**
 * MF 取引先データを既存 data.customers にマージ (旧 sync-partners.php と同じロジックを関数化)
 */
function mergeMfPartnersIntoCustomers(array $partners): array
{
    $data = getData();
    decryptCustomerData($data);
    if (!isset($data['customers'])) $data['customers'] = [];

    $existingNameToIndex = [];
    foreach ($data['customers'] as $idx => $c) {
        $existingNameToIndex[$c['companyName'] ?? ''] = $idx;
    }

    $newCount = $skipCount = $updatedCount = $branchCount = 0;

    // 取引先を会社名でグループ化 (person_dept を営業所として扱う)
    $partnersByCompany = [];
    foreach ($partners as $partner) {
        $partnerName = $partner['name'] ?? '';
        if (empty($partnerName)) continue;
        if (!isset($partnersByCompany[$partnerName])) {
            $partnersByCompany[$partnerName] = ['main' => null, 'branches' => []];
        }
        $personDept = $partner['person_dept'] ?? '';
        if (empty($personDept)) {
            if ($partnersByCompany[$partnerName]['main'] === null) {
                $partnersByCompany[$partnerName]['main'] = $partner;
            }
        } else {
            $partnersByCompany[$partnerName]['branches'][] = ['name' => $personDept, 'partner' => $partner];
            if ($partnersByCompany[$partnerName]['main'] === null) {
                $partnersByCompany[$partnerName]['main'] = $partner;
            }
        }
    }

    foreach ($partnersByCompany as $partnerName => $group) {
        $mainPartner = $group['main'];
        $branchList  = $group['branches'];
        if ($mainPartner === null && !empty($branchList)) $mainPartner = $branchList[0]['partner'];
        if ($mainPartner === null) continue;

        $parentIndex = $existingNameToIndex[$partnerName] ?? null;
        if ($parentIndex === null) {
            $address = '';
            if (!empty($mainPartner['prefecture'])) $address .= $mainPartner['prefecture'];
            if (!empty($mainPartner['address1']))   $address .= $mainPartner['address1'];
            if (!empty($mainPartner['address2']))   $address .= $mainPartner['address2'];

            $data['customers'][] = [
                'id'            => 'c_' . uniqid(),
                'companyName'   => $partnerName,
                'aliases'       => [],
                'branches'      => [],
                'contactPerson' => $mainPartner['person_name'] ?? '',
                'phone'         => $mainPartner['tel'] ?? '',
                'email'         => $mainPartner['email'] ?? '',
                'address'       => $address,
                'zipcode'       => $mainPartner['zip'] ?? '',
                'notes'         => '',
                'mf_partner_id' => $mainPartner['id'] ?? null,
                'created_at'    => date('Y-m-d H:i:s'),
                'source'        => 'mf_partners',
            ];
            $parentIndex = count($data['customers']) - 1;
            $existingNameToIndex[$partnerName] = $parentIndex;
            $newCount++;
        } else {
            $existing = &$data['customers'][$parentIndex];
            if (empty($existing['phone'])         && !empty($mainPartner['tel']))         { $existing['phone'] = $mainPartner['tel']; $updatedCount++; }
            if (empty($existing['email'])         && !empty($mainPartner['email']))       { $existing['email'] = $mainPartner['email']; $updatedCount++; }
            if (empty($existing['contactPerson']) && !empty($mainPartner['person_name'])) { $existing['contactPerson'] = $mainPartner['person_name']; $updatedCount++; }
            if (empty($existing['address'])) {
                $address = '';
                if (!empty($mainPartner['prefecture'])) $address .= $mainPartner['prefecture'];
                if (!empty($mainPartner['address1']))   $address .= $mainPartner['address1'];
                if (!empty($mainPartner['address2']))   $address .= $mainPartner['address2'];
                if (!empty($address)) { $existing['address'] = $address; $updatedCount++; }
            }
            $existing['mf_partner_id'] = $mainPartner['id'] ?? null;
            $existing['updated_at']    = date('Y-m-d H:i:s');
            if (!isset($existing['branches'])) $existing['branches'] = [];
            $skipCount++;
            unset($existing);
        }

        // 営業所
        if (!empty($branchList)) {
            $parent = &$data['customers'][$parentIndex];
            if (!isset($parent['branches'])) $parent['branches'] = [];
            $existingBranchNames = [];
            foreach ($parent['branches'] as $bIdx => $b) {
                $existingBranchNames[$b['name'] ?? ''] = $bIdx;
            }
            foreach ($branchList as $bi) {
                $bn = $bi['name'];
                $bp = $bi['partner'];
                if (empty($bn)) continue;
                $bAddr = '';
                if (!empty($bp['prefecture'])) $bAddr .= $bp['prefecture'];
                if (!empty($bp['address1']))   $bAddr .= $bp['address1'];
                if (!empty($bp['address2']))   $bAddr .= $bp['address2'];

                if (isset($existingBranchNames[$bn])) {
                    $eb = &$parent['branches'][$existingBranchNames[$bn]];
                    if (empty($eb['contact']) && !empty($bp['person_name'])) $eb['contact'] = $bp['person_name'];
                    if (empty($eb['phone'])   && !empty($bp['tel']))         $eb['phone']   = $bp['tel'];
                    if (empty($eb['address']) && !empty($bAddr))             $eb['address'] = $bAddr;
                    $eb['mf_partner_id'] = $bp['id'] ?? null;
                    $eb['updated_at']    = date('Y-m-d H:i:s');
                    unset($eb);
                } else {
                    $parent['branches'][] = [
                        'id'            => 'br_' . uniqid(),
                        'name'          => $bn,
                        'contact'       => $bp['person_name'] ?? '',
                        'phone'         => $bp['tel'] ?? '',
                        'email'         => $bp['email'] ?? '',
                        'address'       => $bAddr,
                        'zipcode'       => $bp['zip'] ?? '',
                        'mf_partner_id' => $bp['id'] ?? null,
                        'created_at'    => date('Y-m-d H:i:s'),
                        'source'        => 'mf_partners',
                    ];
                    $existingBranchNames[$bn] = count($parent['branches']) - 1;
                    $branchCount++;
                }
            }
            $parent['updated_at'] = date('Y-m-d H:i:s');
            unset($parent);
        }
    }

    $data['customers_sync_timestamp']    = date('Y-m-d H:i:s');
    $data['mf_partners_sync_timestamp']  = date('Y-m-d H:i:s');

    encryptCustomerData($data);
    saveData($data, ['customers', 'customers_sync_timestamp', 'mf_partners_sync_timestamp']);

    return [
        'new'     => $newCount,
        'updated' => $updatedCount,
        'skip'    => $skipCount,
        'branch'  => $branchCount,
    ];
}

// ── GET ?action=process ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'process') {
    $jobs = syncPartLoadJobs();
    $processed = false;

    foreach ($jobs as $jobId => $job) {
        if (($job['type'] ?? '') !== 'mf_partners_sync') continue;
        if (($job['status'] ?? '') !== 'running') continue;

        $d = $job['data'] ?? [];
        $pending  = $d['pending_partners'] ?? [];
        $enriched = $d['enriched_partners'] ?? [];
        $total    = $job['total'] ?? (count($pending) + count($enriched));

        // 全 partner の部門情報取得完了 → 最終マージへ
        if (empty($pending)) {
            try {
                $result = mergeMfPartnersIntoCustomers($enriched);
                $msg = "取引先マスタを同期: 新規{$result['new']}件";
                if ($result['branch']  > 0) $msg .= "、営業所{$result['branch']}件";
                if ($result['skip']    > 0) $msg .= "、既存{$result['skip']}件";
                if ($result['updated'] > 0) $msg .= "（{$result['updated']}項目を補完）";
                syncPartUpdateJob($jobId, [
                    'status' => 'completed', 'completed_at' => time(),
                    'progress' => $total, 'message' => $msg, 'result' => $result,
                ]);
                echo json_encode(['success' => true, 'completed' => true, 'job_id' => $jobId, 'result' => $result], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                syncPartUpdateJob($jobId, [
                    'status' => 'failed', 'completed_at' => time(),
                    'message' => 'マージ失敗: ' . $e->getMessage(), 'error' => $e->getMessage(),
                ]);
                echo json_encode(['success' => false, 'completed' => true, 'job_id' => $jobId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        // チャンク処理: 5 partner 分の部門情報を取得
        $chunk = array_splice($pending, 0, SYNC_PARTNERS_CHUNK_SIZE);
        try {
            if (!MFApiClient::isConfigured()) throw new Exception('MFクラウド請求書APIが設定されていません');
            $client = new MFApiClient();

            foreach ($chunk as $partner) {
                try {
                    $partnerId = $partner['id'] ?? null;
                    $partner['departments'] = $partnerId ? $client->getPartnerDepartments($partnerId) : [];
                } catch (Throwable $e) {
                    $partner['departments'] = [];
                }
                $enriched[] = $partner;
            }
        } catch (Throwable $e) {
            syncPartUpdateJob($jobId, [
                'status' => 'failed', 'completed_at' => time(),
                'message' => '部門取得失敗: ' . $e->getMessage(), 'error' => $e->getMessage(),
            ]);
            echo json_encode(['success' => false, 'completed' => true, 'job_id' => $jobId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $progress = count($enriched);
        syncPartUpdateJob($jobId, [
            'progress' => $progress,
            'message'  => "{$progress}/{$total} 社の部門情報を取得中...",
            'data'     => [
                'pending_partners'  => $pending,
                'enriched_partners' => $enriched,
            ],
        ]);

        echo json_encode([
            'success' => true, 'processed' => true,
            'job_id' => $jobId, 'progress' => $progress, 'total' => $total,
        ], JSON_UNESCAPED_UNICODE);
        $processed = true;
        break;
    }

    if (!$processed) {
        echo json_encode(['success' => true, 'processed' => false, 'message' => 'No pending mf_partners_sync jobs'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── POST: ジョブ起動 ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

if (!MFApiClient::isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'MFクラウド請求書APIが設定されていません。設定画面から認証してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 同種 running があれば再利用
$jobs = syncPartLoadJobs();
foreach ($jobs as $existing) {
    if (($existing['type'] ?? '') !== 'mf_partners_sync') continue;
    if (($existing['status'] ?? '') !== 'running') continue;
    echo json_encode([
        'success' => true, 'job_id' => $existing['id'], 'reused' => true,
        'message' => '既に取引先同期が実行中です',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 全取引先リスト取得 (1〜5秒)
try {
    $client   = new MFApiClient();
    $partners = $client->getPartners();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => '取引先一覧の取得に失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($partners)) {
    echo json_encode([
        'success' => true, 'message' => 'MFに取引先が登録されていません',
        'synced' => 0, 'new' => 0, 'skip' => 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cutoff = time() - 86400;
$jobs = array_filter($jobs, fn($j) => ($j['created_at'] ?? 0) > $cutoff);

$total = count($partners);
$jobId = uniqid('mf_partners_sync_', true);
$jobs[$jobId] = [
    'id'          => $jobId,
    'type'        => 'mf_partners_sync',
    'description' => "MF取引先同期 ({$total}社)",
    'status'      => 'running',
    'progress'    => 0,
    'total'       => $total,
    'message'     => "0/{$total} 社の部門情報を取得開始...",
    'process_url' => '/api/sync-partners.php?action=process',
    'created_at'  => time(),
    'data' => [
        'pending_partners'  => $partners,
        'enriched_partners' => [],
    ],
];
syncPartSaveJobs($jobs);

echo json_encode([
    'success' => true,
    'job_id'  => $jobId,
    'total'   => $total,
    'message' => "取引先同期を開始 ({$total}社)。別ページに移動しても処理は続行されます。",
], JSON_UNESCAPED_UNICODE);
