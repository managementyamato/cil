<?php
/**
 * business_cards リポジトリ (リード管理 v2 Phase 2)
 *
 * 設計: docs/lead-management-design.md
 *
 * 重要:
 * - saveEntity() 非経由 (DB_SAVE_MODE への影響ゼロ)
 * - 直接 PDO + prepared statement
 * - 論理削除 (deleted_at)
 * - promoteToLead(): 名刺→リード 昇格時は leads INSERT + 名刺の promoted_lead_id セット
 *                    + タイムラインに type='promotion' で記録
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/lead-activity-repository.php';

class BusinessCardRepository
{
    private static function db(): PDO
    {
        return Database::connect();
    }

    private static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    // ================================================================
    // 一覧・取得
    // ================================================================

    /**
     * 一覧 (deleted_at IS NULL)
     * @param array $opts ['promoted_only' => bool, 'unpromoted_only' => bool, 'search' => string, 'limit' => int]
     */
    public static function listAll(array $opts = []): array
    {
        $sql = "SELECT * FROM business_cards WHERE deleted_at IS NULL";
        $args = [];

        if (!empty($opts['promoted_only'])) {
            $sql .= " AND promoted_lead_id IS NOT NULL AND promoted_lead_id != ''";
        } elseif (!empty($opts['unpromoted_only'])) {
            $sql .= " AND (promoted_lead_id IS NULL OR promoted_lead_id = '')";
        }

        if (!empty($opts['search'])) {
            $sql .= " AND (company_name LIKE ? OR person_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $like = '%' . $opts['search'] . '%';
            $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
        }

        $sql .= " ORDER BY exchanged_at DESC, created_at DESC";
        $limit = isset($opts['limit']) ? max(1, min(2000, (int)$opts['limit'])) : 500;
        $sql .= " LIMIT " . $limit;

        $stmt = self::db()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    public static function get(string $id): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM business_cards WHERE id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ================================================================
    // 作成・更新・削除
    // ================================================================

    public static function create(array $data): string
    {
        $now = self::now();
        $id  = $data['id'] ?? self::generateUuid();
        $sql = "INSERT INTO business_cards (
                    id, company_name, person_name, title, department,
                    phone, mobile, email, fax, website, address,
                    business_card_image_path, exchanged_at, ocr_source, ocr_confidence,
                    registered_by, promoted_lead_id, notes,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?
                )";
        self::db()->prepare($sql)->execute([
            $id,
            $data['company_name']             ?? null,
            $data['person_name']              ?? null,
            $data['title']                    ?? null,
            $data['department']               ?? null,
            $data['phone']                    ?? null,
            $data['mobile']                   ?? null,
            $data['email']                    ?? null,
            $data['fax']                      ?? null,
            $data['website']                  ?? null,
            $data['address']                  ?? null,
            $data['business_card_image_path'] ?? null,
            $data['exchanged_at']             ?? null,
            $data['ocr_source']               ?? 'manual',
            isset($data['ocr_confidence'])    ? (int)$data['ocr_confidence'] : null,
            $data['registered_by']            ?? null,
            $data['promoted_lead_id']         ?? null,
            $data['notes']                    ?? null,
            $now,
            $now,
        ]);
        return $id;
    }

    public static function update(string $id, array $data): void
    {
        $now = self::now();
        $sql = "UPDATE business_cards SET
                    company_name = ?, person_name = ?, title = ?, department = ?,
                    phone = ?, mobile = ?, email = ?, fax = ?, website = ?, address = ?,
                    exchanged_at = ?, notes = ?,
                    updated_at = ?
                WHERE id = ? AND deleted_at IS NULL";
        self::db()->prepare($sql)->execute([
            $data['company_name'] ?? null,
            $data['person_name']  ?? null,
            $data['title']        ?? null,
            $data['department']   ?? null,
            $data['phone']        ?? null,
            $data['mobile']       ?? null,
            $data['email']        ?? null,
            $data['fax']          ?? null,
            $data['website']      ?? null,
            $data['address']      ?? null,
            $data['exchanged_at'] ?? null,
            $data['notes']        ?? null,
            $now,
            $id,
        ]);
    }

    public static function delete(string $id, ?string $deletedBy = null): void
    {
        $now = self::now();
        self::db()->prepare(
            "UPDATE business_cards SET deleted_at = ?, deleted_by = ?
             WHERE id = ? AND deleted_at IS NULL"
        )->execute([$now, $deletedBy, $id]);
    }

    // ================================================================
    // 名刺 → リード 昇格
    // ================================================================

    /**
     * 名刺をリードに昇格する。
     *
     * 1. business_card を取得 (存在 + 未昇格を確認)
     * 2. leads に新規 INSERT (名刺情報を初期値、$overrides で上書き)
     * 3. business_cards.promoted_lead_id をセット
     * 4. lead_activities に type='promotion' で記録
     *
     * @return string 新規 lead_id
     */
    public static function promoteToLead(
        string $cardId,
        array $overrides = [],
        ?string $userEmail = null,
        ?string $userName  = null
    ): string {
        $card = self::get($cardId);
        if (!$card) {
            throw new RuntimeException('名刺が見つかりません: ' . $cardId);
        }
        if (!empty($card['promoted_lead_id'])) {
            // 既に昇格済み
            throw new RuntimeException('この名刺は既にリードに昇格済みです: ' . $card['promoted_lead_id']);
        }

        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $now    = self::now();
            $leadId = $overrides['id'] ?? self::generateUuid();

            // leads INSERT (既存テーブル + v2 ALTER カラム対応)
            $stmt = $pdo->prepare("INSERT INTO leads (
                id, company_name, person_name, title, department,
                phone, mobile, fax, email, website, address,
                status, source, business_card_image_path,
                am, notes,
                business_card_id, assigned_to, last_activity_at,
                created_by, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )");
            $stmt->execute([
                $leadId,
                $overrides['company_name'] ?? $card['company_name'] ?? '',
                $overrides['person_name']  ?? $card['person_name']  ?? '',
                $overrides['title']        ?? $card['title']        ?? '',
                $overrides['department']   ?? $card['department']   ?? '',
                $overrides['phone']        ?? $card['phone']        ?? '',
                $overrides['mobile']       ?? $card['mobile']       ?? '',
                $overrides['fax']          ?? $card['fax']          ?? '',
                $overrides['email']        ?? $card['email']        ?? '',
                $overrides['website']      ?? $card['website']      ?? '',
                $overrides['address']      ?? $card['address']      ?? '',
                $overrides['status']       ?? '新規',
                'business_card',
                $card['business_card_image_path'] ?? null,
                $overrides['am']           ?? $userName ?? '',
                $overrides['notes']        ?? $card['notes']        ?? '',
                $cardId,
                $userEmail,
                $now,
                $userEmail,
                $now,
                $now,
            ]);

            // 名刺側に紐付け
            $pdo->prepare(
                "UPDATE business_cards SET promoted_lead_id = ?, updated_at = ? WHERE id = ?"
            )->execute([$leadId, $now, $cardId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // タイムラインに記録 (失敗しても昇格は完了扱い)
        try {
            $cardLabel = trim(($card['company_name'] ?? '') . ' / ' . ($card['person_name'] ?? ''), ' /');
            LeadActivityRepository::add([
                'lead_id'         => $leadId,
                'type'            => 'promotion',
                'title'           => '名刺から昇格',
                'body'            => $cardLabel !== '' ? ('名刺「' . $cardLabel . '」からリードを作成') : '名刺からリードを作成',
                'created_by'      => $userEmail,
                'created_by_name' => $userName,
            ]);
        } catch (\Throwable $e) {
            error_log('[BusinessCardRepository::promoteToLead] activity insert skipped: ' . $e->getMessage());
        }

        return $leadId;
    }

    // ================================================================
    // ヘルパー
    // ================================================================

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
