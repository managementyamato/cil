<?php
/**
 * MF請求書の自動マッピングユーティリティ
 * タグやメモからPJ番号と担当者名を抽出して自動マッピング
 */

class MFAutoMapper {

    /**
     * タグからPJ番号を抽出
     * 対応形式: p1, p123, P456, PJ001, pj-789 など
     */
    public static function extractProjectId($tags, $memo = '', $note = '', $title = '') {
        $searchText = '';

        // タグを検索対象に追加
        if (is_array($tags)) {
            $searchText .= ' ' . implode(' ', $tags);
        } elseif (is_string($tags)) {
            $searchText .= ' ' . $tags;
        }

        // メモ、ノート、タイトルも検索対象に追加
        $searchText .= ' ' . $memo . ' ' . $note . ' ' . $title;

        // PJ番号のパターンマッチング
        // パターン1: p1, p123 など（小文字p + 数字）
        if (preg_match('/\bp(\d+)\b/i', $searchText, $matches)) {
            return 'p' . $matches[1];
        }

        // パターン2: PJ001, PJ-123, pj_456 など
        if (preg_match('/\bpj[\-_]?(\d+)\b/i', $searchText, $matches)) {
            return 'p' . $matches[1];
        }

        // パターン3: 【p123】のように括弧で囲まれている
        if (preg_match('/【p(\d+)】/u', $searchText, $matches)) {
            return 'p' . $matches[1];
        }

        // パターン4: [p123]のように角括弧で囲まれている
        if (preg_match('/\[p(\d+)\]/i', $searchText, $matches)) {
            return 'p' . $matches[1];
        }

        return null;
    }

    /**
     * タグから担当者名を抽出
     */
    public static function extractAssigneeName($tags, $memo = '', $note = '') {
        $searchText = '';

        // タグを検索対象に追加
        if (is_array($tags)) {
            $searchText .= ' ' . implode(' ', $tags);
        } elseif (is_string($tags)) {
            $searchText .= ' ' . $tags;
        }

        // メモ、ノートも検索対象に追加
        $searchText .= ' ' . $memo . ' ' . $note;

        // 担当者名のパターンマッチング
        // パターン1: 担当:東田、担当：小黒 など
        if (preg_match('/担当[：:]\s*([^\s、。,\.]+)/u', $searchText, $matches)) {
            return trim($matches[1]);
        }

        // パターン2: 【東田】【小黒】のように括弧で囲まれている
        if (preg_match('/【([^】p\d]+)】/u', $searchText, $matches)) {
            $name = trim($matches[1]);
            // PJ番号ではないことを確認
            if (!preg_match('/^p\d+$/i', $name) && !preg_match('/^pj/i', $name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * MF請求書を自動マッピング
     */
    public static function autoMapInvoices($invoices, $projects) {
        $mappings = array();
        $unmapped = array();

        foreach ($invoices as $invoice) {
            $invoiceId = $invoice['id'];
            $tags = $invoice['tags'] ?? array();
            $memo = $invoice['memo'] ?? '';
            $note = $invoice['note'] ?? '';
            $title = $invoice['title'] ?? '';

            // PJ番号を抽出
            $projectId = self::extractProjectId($tags, $memo, $note, $title);

            // 担当者名を抽出
            $assigneeName = self::extractAssigneeName($tags, $memo, $note);

            if ($projectId) {
                // プロジェクトが存在するか確認
                $projectExists = false;
                foreach ($projects as $project) {
                    if ($project['id'] === $projectId) {
                        $projectExists = true;
                        break;
                    }
                }

                if ($projectExists) {
                    $mappings[$invoiceId] = array(
                        'project_id' => $projectId,
                        'assignee_name' => $assigneeName,
                        'confidence' => 'high',
                        'method' => 'tag_extraction'
                    );
                } else {
                    $unmapped[] = array(
                        'invoice_id' => $invoiceId,
                        'extracted_project_id' => $projectId,
                        'assignee_name' => $assigneeName,
                        'reason' => 'project_not_found'
                    );
                }
            } else {
                $unmapped[] = array(
                    'invoice_id' => $invoiceId,
                    'assignee_name' => $assigneeName,
                    'reason' => 'no_project_id_found'
                );
            }
        }

        return array(
            'mappings' => $mappings,
            'unmapped' => $unmapped,
            'mapped_count' => count($mappings),
            'unmapped_count' => count($unmapped)
        );
    }

    /**
     * 自動マッピングを実行して財務データを更新
     */
    public static function applyAutoMapping($data) {
        if (!isset($data['mf_invoices']) || empty($data['mf_invoices'])) {
            return array(
                'success' => false,
                'message' => 'MF請求書データがありません',
                'mapped_count' => 0
            );
        }

        $result = self::autoMapInvoices($data['mf_invoices'], $data['projects']);
        $mappings = $result['mappings'];

        if (empty($mappings)) {
            return array(
                'success' => false,
                'message' => '自動マッピングできる請求書がありませんでした',
                'mapped_count' => 0,
                'unmapped_count' => $result['unmapped_count'],
                'unmapped' => $result['unmapped']
            );
        }

        // 財務データを更新
        $updatedCount = 0;
        foreach ($data['mf_invoices'] as $invoice) {
            $invoiceId = $invoice['id'];

            if (isset($mappings[$invoiceId])) {
                $mapping = $mappings[$invoiceId];
                $projectId = $mapping['project_id'];

                // 既存の財務データがあれば保持
                $existingFinance = isset($data['finance'][$projectId]) ? $data['finance'][$projectId] : array();

                // 財務データを更新
                $data['finance'][$projectId] = array(
                    'revenue' => $invoice['total_price'],
                    'cost' => $existingFinance['cost'] ?? 0,
                    'labor_cost' => $existingFinance['labor_cost'] ?? 0,
                    'material_cost' => $existingFinance['material_cost'] ?? 0,
                    'other_cost' => $existingFinance['other_cost'] ?? 0,
                    'gross_profit' => $invoice['total_price'] - ($existingFinance['cost'] ?? 0),
                    'net_profit' => $invoice['total_price'] - (($existingFinance['cost'] ?? 0) + ($existingFinance['labor_cost'] ?? 0) + ($existingFinance['material_cost'] ?? 0) + ($existingFinance['other_cost'] ?? 0)),
                    'notes' => ($existingFinance['notes'] ?? '') . "\n[MF自動マッピング] " . $invoice['billing_date'] . ' - ' . $invoice['partner_name'],
                    'mf_billing_id' => $invoiceId,
                    'mf_billing_number' => $invoice['billing_number'],
                    'assignee_name' => $mapping['assignee_name'],
                    'updated_at' => date('Y-m-d H:i:s'),
                    'mf_auto_mapped' => true
                );

                $updatedCount++;
            }
        }

        return array(
            'success' => true,
            'data' => $data,
            'mapped_count' => $updatedCount,
            'unmapped_count' => $result['unmapped_count'],
            'unmapped' => $result['unmapped']
        );
    }
}
