<?php
/**
 * lead_activities リポジトリ (リード管理 v2 Phase 1)
 *
 * 設計: docs/lead-management-design.md
 *
 * 重要:
 * - saveEntity() を経由しない (DB_SAVE_MODE への影響ゼロ)
 * - 直接 PDO + prepared statement
 * - 論理削除 (deleted_at)
 * - 各 INSERT 時に leads.last_activity_at も同期更新 (一覧の鮮度判定で使う)
 */

require_once __DIR__ . '/../config/database.php';

class LeadActivityRepository
{
    /** 許可される type */
    public const ALLOWED_TYPES = ['status_change','manual_note','promotion','meeting','quote','system'];

    private static function db(): PDO
    {
        return Database::connect();
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * リードの履歴を新しい順で取得 (deleted_at IS NULL)
     */
    public static function listByLead(string $leadId, int $limit = 100): array
    {
        $sql = "SELECT * FROM lead_activities
                WHERE lead_id = ? AND deleted_at IS NULL
                ORDER BY occurred_at DESC, id DESC
                LIMIT " . (int)$limit;
        $stmt = self::db()->prepare($sql);
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }

    /**
     * 1件 INSERT。lead.last_activity_at も同時更新。
     *
     * @param array $data [
     *   'lead_id'         => required,
     *   'type'            => default 'manual_note',
     *   'from_status'     => optional,
     *   'to_status'       => optional,
     *   'title'           => optional,
     *   'body'            => optional,
     *   'occurred_at'     => optional (default now),
     *   'created_by'      => optional,
     *   'created_by_name' => optional,
     * ]
     * @return int 新規 activity id
     */
    public static function add(array $data): int
    {
        if (empty($data['lead_id'])) {
            throw new InvalidArgumentException('lead_id is required');
        }
        $type = $data['type'] ?? 'manual_note';
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('invalid type: ' . $type);
        }

        $occurredAt = $data['occurred_at'] ?? self::now();
        $createdAt  = self::now();

        $sql = "INSERT INTO lead_activities
                (lead_id, type, from_status, to_status, title, body,
                 occurred_at, created_by, created_by_name, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        self::db()->prepare($sql)->execute([
            $data['lead_id'],
            $type,
            $data['from_status']     ?? null,
            $data['to_status']       ?? null,
            $data['title']           ?? null,
            $data['body']            ?? null,
            $occurredAt,
            $data['created_by']      ?? null,
            $data['created_by_name'] ?? null,
            $createdAt,
        ]);
        $id = (int)self::db()->lastInsertId();

        // leads.last_activity_at を最新の occurred_at に追従
        // (これにより営業会議のフォロー対象抽出 = 14日以上動きなしリスト が機能する)
        try {
            $upd = self::db()->prepare(
                "UPDATE leads SET last_activity_at = ? WHERE id = ?"
            );
            $upd->execute([$occurredAt, $data['lead_id']]);
        } catch (\Throwable $e) {
            // last_activity_at カラムがまだ無いケースは無視 (マイグレーション前のフォールバック)
            error_log('[LeadActivityRepository] last_activity_at update skipped: ' . $e->getMessage());
        }

        return $id;
    }

    /**
     * ステータス変更を自動記録 (leads-api.php の update から呼ばれる想定)
     *
     * @return int|null 作成された activity id。変化がなければ null
     */
    public static function recordStatusChange(
        string $leadId,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $userEmail = null,
        ?string $userName  = null,
        ?string $reason    = null
    ): ?int {
        if ($fromStatus === $toStatus) return null;
        return self::add([
            'lead_id'         => $leadId,
            'type'            => 'status_change',
            'from_status'     => $fromStatus,
            'to_status'       => $toStatus,
            'title'           => sprintf('%s → %s', $fromStatus ?? '未設定', $toStatus ?? '未設定'),
            'body'            => $reason,
            'created_by'      => $userEmail,
            'created_by_name' => $userName,
        ]);
    }

    /**
     * 論理削除
     */
    public static function delete(int $id, ?string $deletedBy = null): void
    {
        $now = self::now();
        self::db()->prepare(
            "UPDATE lead_activities SET deleted_at = ?, deleted_by = ?
             WHERE id = ? AND deleted_at IS NULL"
        )->execute([$now, $deletedBy, $id]);
    }
}
