<?php
/**
 * 価格表 v2 リポジトリ
 *
 * 設計: docs/price-list-design.md
 *
 * 重要:
 * - saveEntity() / DualModeAdapter を経由しない (DB_SAVE_MODE に一切影響を与えない)
 * - 直接 PDO で prepared statement を使う
 * - 論理削除 (deleted_at) で統一
 * - 金額は税抜き整数 (円)
 */

require_once __DIR__ . '/../config/database.php';

class PriceListRepository
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
    // 製品 (pl_products)
    // ================================================================

    /**
     * 製品一覧 (アクティブのみ・並び順)
     */
    public static function listProducts(bool $includeInactive = false): array
    {
        $sql = "SELECT * FROM pl_products WHERE deleted_at IS NULL";
        if (!$includeInactive) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY display_order ASC, name ASC";
        return self::db()->query($sql)->fetchAll();
    }

    public static function getProduct(string $id): ?array
    {
        $stmt = self::db()->prepare("SELECT * FROM pl_products WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 製品 INSERT
     * @param array $data ['id','name','category','code','description','display_order','is_active']
     */
    public static function createProduct(array $data): void
    {
        $now = self::now();
        $sql = "INSERT INTO pl_products (id, code, name, category, description, display_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        self::db()->prepare($sql)->execute([
            $data['id'],
            $data['code']          ?? null,
            $data['name'],
            $data['category']      ?? null,
            $data['description']   ?? null,
            (int)($data['display_order'] ?? 0),
            (int)($data['is_active']     ?? 1),
            $now,
            $now,
        ]);
    }

    public static function updateProduct(string $id, array $data): void
    {
        $now = self::now();
        $sql = "UPDATE pl_products SET
                    code = ?, name = ?, category = ?, description = ?,
                    display_order = ?, is_active = ?, updated_at = ?
                WHERE id = ? AND deleted_at IS NULL";
        self::db()->prepare($sql)->execute([
            $data['code']          ?? null,
            $data['name'],
            $data['category']      ?? null,
            $data['description']   ?? null,
            (int)($data['display_order'] ?? 0),
            (int)($data['is_active']     ?? 1),
            $now,
            $id,
        ]);
    }

    public static function deleteProduct(string $id, ?string $deletedBy = null): void
    {
        $now = self::now();
        $stmt = self::db()->prepare(
            "UPDATE pl_products SET deleted_at = ?, deleted_by = ? WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$now, $deletedBy, $id]);
        // バリアントと価格ルールもカスケード論理削除
        self::db()->prepare(
            "UPDATE pl_product_variants SET deleted_at = ?, deleted_by = ?
             WHERE product_id = ? AND deleted_at IS NULL"
        )->execute([$now, $deletedBy, $id]);
        self::db()->prepare(
            "UPDATE pl_price_rules SET deleted_at = ?, deleted_by = ?
             WHERE variant_id IN (SELECT id FROM pl_product_variants WHERE product_id = ?)
               AND deleted_at IS NULL"
        )->execute([$now, $deletedBy, $id]);
    }

    // ================================================================
    // バリアント (pl_product_variants)
    // ================================================================

    public static function listVariants(string $productId, bool $includeInactive = false): array
    {
        $sql = "SELECT * FROM pl_product_variants
                WHERE product_id = ? AND deleted_at IS NULL";
        if (!$includeInactive) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY display_order ASC, size_inch ASC, size_label ASC";
        $stmt = self::db()->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public static function getVariant(string $id): ?array
    {
        $stmt = self::db()->prepare("SELECT * FROM pl_product_variants WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function createVariant(array $data): void
    {
        $now = self::now();
        $sql = "INSERT INTO pl_product_variants
                (id, product_id, size_label, size_inch, resolution, screen_area_m2,
                 attributes_json, display_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        self::db()->prepare($sql)->execute([
            $data['id'],
            $data['product_id'],
            $data['size_label'],
            isset($data['size_inch'])      ? (float)$data['size_inch']      : null,
            $data['resolution']            ?? null,
            isset($data['screen_area_m2']) ? (float)$data['screen_area_m2'] : null,
            isset($data['attributes_json']) ? json_encode($data['attributes_json'], JSON_UNESCAPED_UNICODE) : null,
            (int)($data['display_order']   ?? 0),
            (int)($data['is_active']       ?? 1),
            $now,
            $now,
        ]);
    }

    public static function updateVariant(string $id, array $data): void
    {
        $now = self::now();
        $sql = "UPDATE pl_product_variants SET
                    size_label = ?, size_inch = ?, resolution = ?, screen_area_m2 = ?,
                    attributes_json = ?, display_order = ?, is_active = ?, updated_at = ?
                WHERE id = ? AND deleted_at IS NULL";
        self::db()->prepare($sql)->execute([
            $data['size_label'],
            isset($data['size_inch'])      ? (float)$data['size_inch']      : null,
            $data['resolution']            ?? null,
            isset($data['screen_area_m2']) ? (float)$data['screen_area_m2'] : null,
            isset($data['attributes_json']) ? json_encode($data['attributes_json'], JSON_UNESCAPED_UNICODE) : null,
            (int)($data['display_order']   ?? 0),
            (int)($data['is_active']       ?? 1),
            $now,
            $id,
        ]);
    }

    public static function deleteVariant(string $id, ?string $deletedBy = null): void
    {
        $now = self::now();
        self::db()->prepare(
            "UPDATE pl_product_variants SET deleted_at = ?, deleted_by = ? WHERE id = ? AND deleted_at IS NULL"
        )->execute([$now, $deletedBy, $id]);
        // 関連の価格ルールもカスケード
        self::db()->prepare(
            "UPDATE pl_price_rules SET deleted_at = ?, deleted_by = ? WHERE variant_id = ? AND deleted_at IS NULL"
        )->execute([$now, $deletedBy, $id]);
    }

    // ================================================================
    // 価格ルール (pl_price_rules)
    // ================================================================

    public static function listPriceRules(string $variantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM pl_price_rules
             WHERE variant_id = ? AND deleted_at IS NULL
             ORDER BY transaction_type ASC, customer_rank ASC, display_order ASC, price_label ASC"
        );
        $stmt->execute([$variantId]);
        return $stmt->fetchAll();
    }

    /**
     * variant_id + rank + txn_type + price_label の UPSERT
     */
    public static function upsertPriceRule(array $data): int
    {
        $now = self::now();
        // 既存チェック (論理削除済みも対象に含めて復活させる)
        $sql = "SELECT id FROM pl_price_rules
                WHERE variant_id = ? AND customer_rank = ? AND transaction_type = ? AND price_label = ?
                LIMIT 1";
        $stmt = self::db()->prepare($sql);
        $stmt->execute([
            $data['variant_id'],
            $data['customer_rank'],
            $data['transaction_type'],
            $data['price_label'],
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = self::db()->prepare(
                "UPDATE pl_price_rules
                 SET amount = ?, notes = ?, display_order = ?,
                     deleted_at = NULL, deleted_by = NULL, updated_at = ?
                 WHERE id = ?"
            );
            $upd->execute([
                (int)$data['amount'],
                $data['notes']         ?? null,
                (int)($data['display_order'] ?? 0),
                $now,
                (int)$existing['id'],
            ]);
            return (int)$existing['id'];
        }

        $ins = self::db()->prepare(
            "INSERT INTO pl_price_rules
             (variant_id, customer_rank, transaction_type, price_label, amount, notes, display_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([
            $data['variant_id'],
            $data['customer_rank'],
            $data['transaction_type'],
            $data['price_label'],
            (int)$data['amount'],
            $data['notes']         ?? null,
            (int)($data['display_order'] ?? 0),
            $now,
            $now,
        ]);
        return (int)self::db()->lastInsertId();
    }

    public static function deletePriceRule(int $id, ?string $deletedBy = null): void
    {
        $now = self::now();
        $stmt = self::db()->prepare(
            "UPDATE pl_price_rules SET deleted_at = ?, deleted_by = ? WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$now, $deletedBy, $id]);
    }

    // ================================================================
    // 行列展開 (閲覧モード用)
    // ================================================================

    /**
     * 1製品の価格行列を取得
     * 返却:
     *   [
     *     'product' => [...],
     *     'variants' => [
     *       [
     *         ...variant fields,
     *         'prices' => [
     *           ['customer_rank' => 'S', 'transaction_type' => 'sale', 'price_label' => '販売価格', 'amount' => 1584000, ...],
     *           ...
     *         ],
     *       ],
     *       ...
     *     ],
     *   ]
     */
    public static function getProductMatrix(string $productId): ?array
    {
        $product = self::getProduct($productId);
        if (!$product) return null;

        $variants = self::listVariants($productId, true);
        if (empty($variants)) {
            return ['product' => $product, 'variants' => []];
        }

        // 全バリアントの価格を一括取得
        $variantIds = array_column($variants, 'id');
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $stmt = self::db()->prepare(
            "SELECT * FROM pl_price_rules
             WHERE variant_id IN ($placeholders) AND deleted_at IS NULL
             ORDER BY variant_id ASC, transaction_type ASC, customer_rank ASC, display_order ASC, price_label ASC"
        );
        $stmt->execute($variantIds);
        $allRules = $stmt->fetchAll();

        // variant_id ごとにグルーピング
        $byVariant = [];
        foreach ($allRules as $r) {
            $byVariant[$r['variant_id']][] = $r;
        }

        foreach ($variants as &$v) {
            $v['prices']           = $byVariant[$v['id']] ?? [];
            $v['attributes_json']  = $v['attributes_json'] ? json_decode($v['attributes_json'], true) : null;
        }
        unset($v);

        return ['product' => $product, 'variants' => $variants];
    }
}
