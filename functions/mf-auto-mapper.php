<?php
/**
 * MF請求書の自動マッピングユーティリティ
 * タグやメモからPJ番号と担当者名を抽出して自動マッピング
 *
 * 使用方法:
 *   require_once __DIR__ . '/../functions/mf-auto-mapper.php';
 *   $result = MFAutoMapper::autoMapInvoices($invoices, $projects);
 */

class MFAutoMapper
{
    /**
     * タグからPJ番号を抽出
     * 対応形式: p1, p123, P456, PJ001, pj-789 など
     *
     * @param mixed $tags タグ配列または文字列
     * @param string $memo メモ
     * @param string $note ノート
     * @param string $title タイトル
     * @return string|null 抽出されたPJ番号（p+数字形式）
     */
    public static function extractProjectId($tags, string $memo = '', string $note = '', string $title = ''): ?string
    {
        $searchText = '';

        // タグを検索対象に追加
        if (is_array($tags)) {
            $searchText .= ' ' . implode(' ', $tags);
        } elseif (is_string($tags)) {
            $searchText .= ' ' . $tags;
        }

        // メモ、ノート、タイトルも検索対象に追加
        $searchText .= ' ' . $memo . ' ' . $note . ' ' . $title;

        // パターン1: P1, P123, p456 など（P + 数字）
        if (preg_match('/\bP(\d+)\b/i', $searchText, $matches)) {
            return 'P' . $matches[1];
        }

        // パターン2: PJ001, PJ-123, pj_456 など
        if (preg_match('/\bPJ[\-_]?(\d+)\b/i', $searchText, $matches)) {
            return 'P' . $matches[1];
        }

        return null;
    }

    /**
     * タグから担当者名を抽出
     *
     * @param mixed $tags タグ配列または文字列
     * @param string $memo メモ
     * @param string $note ノート
     * @return string|null 抽出された担当者名
     */
    public static function extractAssigneeName($tags, string $memo = '', string $note = ''): ?string
    {
        $searchText = '';

        if (is_array($tags)) {
            $searchText .= ' ' . implode(' ', $tags);
        } elseif (is_string($tags)) {
            $searchText .= ' ' . $tags;
        }

        $searchText .= ' ' . $memo . ' ' . $note;

        // パターン1: 担当:東田、担当：小黒 など
        if (preg_match('/担当[：:]\s*([^\s、。,\.]+)/u', $searchText, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * MF請求書をプロジェクトに自動マッピング
     *
     * @param array $invoices MF請求書配列
     * @param array $projects プロジェクト配列
     * @return array マッピング結果
     */
    public static function autoMapInvoices(array $invoices, array $projects): array
    {
        // プロジェクトIDマップを作成
        $projectMap = [];
        foreach ($projects as $project) {
            $projectMap[$project['id']] = $project;
        }

        $mappings = [];
        $unmapped = [];

        foreach ($invoices as $invoice) {
            $invoiceId = $invoice['id'];
            $tags = $invoice['tag_names'] ?? [];
            $memo = $invoice['memo'] ?? '';
            $note = $invoice['note'] ?? '';
            $title = $invoice['title'] ?? '';

            // PJ番号を抽出
            $projectId = self::extractProjectId($tags, $memo, $note, $title);

            // 担当者名を抽出
            $assigneeName = self::extractAssigneeName($tags, $memo, $note);

            if ($projectId && isset($projectMap[$projectId])) {
                $mappings[$invoiceId] = [
                    'project_id' => $projectId,
                    'project_name' => $projectMap[$projectId]['name'] ?? '',
                    'assignee_name' => $assigneeName,
                    'confidence' => 'high',
                    'method' => 'tag_extraction',
                ];
            } else {
                $unmapped[] = [
                    'invoice_id' => $invoiceId,
                    'extracted_project_id' => $projectId,
                    'assignee_name' => $assigneeName,
                    'reason' => $projectId ? 'project_not_found' : 'no_project_id_found',
                ];
            }
        }

        return [
            'mappings' => $mappings,
            'unmapped' => $unmapped,
            'mapped_count' => count($mappings),
            'unmapped_count' => count($unmapped),
        ];
    }

    /**
     * 自動マッピングを実行してfinanceデータを更新
     *
     * @param array $data getData()で取得したデータ
     * @return array 結果
     */
    public static function applyAutoMapping(array $data): array
    {
        if (empty($data['mf_invoices'])) {
            return [
                'success' => false,
                'message' => 'MF請求書データがありません',
                'mapped_count' => 0,
            ];
        }

        $projects = $data['projects'] ?? [];
        if (empty($projects)) {
            return [
                'success' => false,
                'message' => 'プロジェクトデータがありません',
                'mapped_count' => 0,
            ];
        }

        $result = self::autoMapInvoices($data['mf_invoices'], $projects);

        if (empty($result['mappings'])) {
            return [
                'success' => false,
                'message' => '自動マッピングできる請求書がありませんでした',
                'mapped_count' => 0,
                'unmapped_count' => $result['unmapped_count'],
                'unmapped' => $result['unmapped'],
            ];
        }

        // mf_invoice_mappingsに保存（finance連動データとは分離）
        if (!isset($data['mf_invoice_mappings'])) {
            $data['mf_invoice_mappings'] = [];
        }

        $newMappingCount = 0;
        foreach ($result['mappings'] as $invoiceId => $mapping) {
            // 既にマッピング済みならスキップ
            if (isset($data['mf_invoice_mappings'][$invoiceId])) {
                continue;
            }

            $data['mf_invoice_mappings'][$invoiceId] = [
                'project_id' => $mapping['project_id'],
                'project_name' => $mapping['project_name'],
                'assignee_name' => $mapping['assignee_name'],
                'method' => 'auto',
                'mapped_at' => date('Y-m-d H:i:s'),
            ];
            $newMappingCount++;
        }

        return [
            'success' => true,
            'data' => $data,
            'mapped_count' => $newMappingCount,
            'total_mappings' => count($data['mf_invoice_mappings']),
            'unmapped_count' => $result['unmapped_count'],
            'unmapped' => $result['unmapped'],
        ];
    }
}
