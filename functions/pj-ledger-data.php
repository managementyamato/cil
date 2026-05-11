<?php
/**
 * PJ管理台帳 専用データアクセス層
 *
 * 【現状】 MySQL `projects` テーブル一本（2026-05-11 移行完了）
 *   - pj-ledger.json は廃止・削除済み
 *   - pj-ledger.php / api/pj-ledger.php / api/pj-ledger-sync.php も削除済み
 *
 * 【提供する関数】 finance.php 等の既存呼び出し互換のための薄いラッパー
 *   - getPjLedgerData()  : MySQL projects を pj-ledger 形式（旧フィールド名互換）で返す
 *   - filterPjDeleted()  : 削除済みを除外（既存コード互換）
 *
 * 【保存機能】 savePjLedgerData は廃止。書き込みは master.php 経由（projects テーブル直接）。
 */

/**
 * pj-ledger 形式に必要なフィールド名へマッピング（MySQL projects → pj-ledger 互換）
 * 旧 pj-ledger.json と finance.php の互換性維持用。
 */
function mapProjectRowToPjLedger(array $p): array {
    // MySQL projects テーブルは id が PJ番号として使われている
    if (!isset($p['pj_number']) || $p['pj_number'] === '') {
        $p['pj_number'] = $p['id'] ?? '';
    }
    if (!isset($p['project_name']) || $p['project_name'] === '') {
        $p['project_name'] = $p['name'] ?? '';
    }
    if (!isset($p['dealer']) || $p['dealer'] === '') {
        $p['dealer'] = $p['dealer_name'] ?? '';
    }
    if (!isset($p['type']) || $p['type'] === '') {
        $p['type'] = $p['transaction_type'] ?? '';
    }
    if (!isset($p['manufacturer']) || $p['manufacturer'] === '') {
        $p['manufacturer'] = $p['maker'] ?? '';
    }
    return $p;
}

/**
 * PJ台帳データ読み込み（MySQL `projects` テーブル経由）
 * 同一リクエスト内キャッシュ付き。
 *
 * @return array ['projects' => [...], 'monthly_profits' => [...]]
 */
function getPjLedgerData($forceReload = false) {
    static $cache = null;
    if ($cache !== null && !$forceReload) {
        return $cache;
    }

    try {
        $allData = getData();
        $rawProjects = $allData['projects'] ?? [];
        $projects = [];
        foreach ($rawProjects as $p) {
            $projects[] = mapProjectRowToPjLedger($p);
        }
        $cache = [
            'projects'        => $projects,
            'monthly_profits' => $allData['monthly_profits'] ?? [],
            'last_sync'       => null,
            '_source'         => 'mysql',
        ];
        return $cache;
    } catch (Exception $e) {
        error_log('getPjLedgerData: MySQL読み込み失敗: ' . $e->getMessage());
        $cache = [
            'projects'        => [],
            'monthly_profits' => [],
            'last_sync'       => null,
            '_source'         => 'mysql_error',
            '_error'          => $e->getMessage(),
        ];
        return $cache;
    }
}

/**
 * 削除済み除外（filterDeleted と同等・互換維持）
 */
function filterPjDeleted($items) {
    return array_values(array_filter($items, function($item) {
        return empty($item['deleted_at']);
    }));
}
