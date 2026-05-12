<?php
/**
 * データベース接続・操作アダプター
 *
 * data.json → MySQL 移行用。
 * getData()/saveData() の内部実装を切り替えるだけで、
 * 呼び出し側のコードは一切変更不要。
 *
 * 切替モード（.env の DB_MODE で制御）:
 *   json  = 従来通り data.json（デフォルト）
 *   db    = MySQL のみ
 *   dual  = 両方に書き込み、読み込みは MySQL（移行期間用）
 */

// config.php から require される。循環参照を避けるため config.php は読み込まない。
// env() 関数は config.php で定義済みの前提。

class Database
{
    private static ?PDO $pdo = null;

    // --- JSON カラムを持つエンティティの定義 ---
    private static array $jsonColumns = [
        'projects'           => ['invoice_ids'],
        'customers'          => ['aliases', 'branches'],
        'tasks'              => ['subtasks', 'mentions'],
        'announcements'      => ['read_by'],
        'memos'              => ['tags'],
        'slides'             => ['required_for'],
        'weekly_reports'     => ['private_recipients'],
        'workflow_requests'  => ['approvers'],
        'mf_invoices'        => ['tag_names', 'items'],
        'invoices'           => ['tag_names'],
        'invoice_requests'   => ['items'],
    ];

    // --- bool カラムを持つエンティティの定義 ---
    private static array $boolColumns = [
        'announcements' => ['pinned'],
        'memos'         => ['pinned'],
        'employees'     => ['chat_member'],
    ];

    // --- system_meta に格納するエンティティ ---
    private static array $metaEntities = [
        'assignees',
        'productCategories',
        'settings',
        'mf_sync_timestamp',
        'customers_sync_timestamp',
        'mf_sync_history',
        'finance',
        'troubleResponders',
        'areas',
        'mf_partners_sync_timestamp',
        'comments',
        'report_comments',
        'admin_messages',
        'email_logs',
        'contact_masters',
    ];

    // --- テーブルとして存在するエンティティ一覧 ---
    private static array $tableEntities = [
        'projects', 'troubles', 'customers', 'partners', 'employees',
        'manufacturers', 'invoices', 'mf_invoices', 'loans', 'repayments',
        'invoice_templates', 'invoice_excel_templates', 'scheduled_invoices',
        'tasks', 'announcements', 'memos',
        'slides', 'company_rules', 'contacts', 'leads',
        'morning_todos', 'weekly_reports', 'discount_approvals',
        'slide_confirmations',
        'workflow_requests', 'reminders', 'deals',
        'price_tiers', 'price_products', 'price_list',
        'invoice_confirmations',
        'invoice_requests', 'monthly_profits',
    ];

    // ================================================================
    // 接続
    // ================================================================

    /**
     * PDO接続を取得（シングルトン）
     */
    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host   = env('DB_HOST', 'localhost');
        $port   = env('DB_PORT', '3306');
        $dbname = env('DB_NAME', 'yamato_mgt');
        $user   = env('DB_USER', 'root');
        $pass   = env('DB_PASS', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    /**
     * DB モードが有効かチェック
     */
    public static function isEnabled(): bool
    {
        return in_array(self::getMode(), ['db', 'dual'], true);
    }

    /**
     * 現在のモードを取得
     */
    public static function getMode(): string
    {
        return env('DB_MODE', 'json');
    }

    // ================================================================
    // エンティティ判定
    // ================================================================

    private static function isMetaEntity(string $entity): bool
    {
        return in_array($entity, self::$metaEntities, true);
    }

    private static function isTableEntity(string $entity): bool
    {
        return in_array($entity, self::$tableEntities, true);
    }

    // ================================================================
    // 行変換（PHP配列 ↔ DB行）
    // ================================================================

    /**
     * PHP配列 → DB行（JSON/boolカラムをエンコード）
     */
    // --- DATE/DATETIME カラム定義 ---
    private static array $dateColumns = [
        'created_at', 'updated_at', 'deleted_at', 'confirmed_at', 'submitted_at',
        'reviewed_at', 'synced_at', 'last_read_at', 'email_token_expires_at', 'email_token_used_at',
        'due_date', 'deadline', 'occurrence_date', 'start_date', 'end_date',
        'billing_date', 'issue_date', 'sales_date', 'closing_date', 'payment_date',
        'join_date', 'leave_date', 'expires_at', 'meeting_date', 'week_start', 'week_end',
    ];

    public static function rowToDb(string $entity, array $row): array
    {
        $jsonCols = self::$jsonColumns[$entity] ?? [];
        $boolCols = self::$boolColumns[$entity] ?? [];

        $dbRow = [];
        foreach ($row as $key => $value) {
            if (in_array($key, $jsonCols, true)) {
                $dbRow[$key] = $value !== null ? json_encode($value, JSON_UNESCAPED_UNICODE) : null;
            } elseif (in_array($key, $boolCols, true)) {
                $dbRow[$key] = $value ? 1 : 0;
            } elseif (in_array($key, self::$dateColumns, true)) {
                // 空文字はNULLに変換（MySQL DATE型が0000-00-00になるのを防止）
                $dbRow[$key] = ($value === '' || $value === null) ? null : $value;
            } elseif (is_array($value)) {
                // $jsonColumnsに未登録でも配列はJSON変換（Array to string conversion防止）
                $dbRow[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $dbRow[$key] = $value;
            }
        }
        return $dbRow;
    }

    /**
     * DB行 → PHP配列（JSON/boolカラムをデコード）
     */
    private static function dbToRow(string $entity, array $row): array
    {
        // Note: カラム名の大文字/小文字はCREATE TABLE定義のまま返される
        // array_change_key_case は companyName 等の camelCase を破壊するため使用しない

        $jsonCols = self::$jsonColumns[$entity] ?? [];
        $boolCols = self::$boolColumns[$entity] ?? [];

        foreach ($row as $key => $value) {
            if (in_array($key, $jsonCols, true) && $value !== null) {
                $decoded = json_decode($value, true);
                $row[$key] = $decoded !== null ? $decoded : [];
            } elseif (in_array($key, $boolCols, true)) {
                $row[$key] = (bool)$value;
            } elseif (in_array($key, self::$dateColumns, true)) {
                // 0000-00-00 は空文字に変換（data.json互換）
                if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                    $row[$key] = '';
                }
            }
        }
        return $row;
    }

    // ================================================================
    // テーブル存在チェック（キャッシュ付き）
    // ================================================================

    private static array $existingTables = [];

    /**
     * テーブルが物理的に存在するかチェック（結果をキャッシュ）
     */
    private static function tableExists(string $table): bool
    {
        if (isset(self::$existingTables[$table])) {
            return self::$existingTables[$table];
        }

        try {
            $pdo = self::connect();
            $dbname = env('DB_NAME', 'yamato_mgt');
            $stmt = $pdo->prepare(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1"
            );
            $stmt->execute([$dbname, $table]);
            $exists = $stmt->fetch() !== false;
            self::$existingTables[$table] = $exists;
            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * テーブルの実カラム名一覧を取得（キャッシュ付き）
     */
    private static array $tableColumnsCache = [];

    private static function getTableColumns(string $table): array
    {
        if (isset(self::$tableColumnsCache[$table])) {
            return self::$tableColumnsCache[$table];
        }

        try {
            $pdo = self::connect();
            $dbname = env('DB_NAME', 'yamato_mgt');
            $stmt = $pdo->prepare(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION"
            );
            $stmt->execute([$dbname, $table]);
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            self::$tableColumnsCache[$table] = $cols;
            return $cols;
        } catch (\Exception $e) {
            return [];
        }
    }

    // ================================================================
    // エンティティ操作（個別）
    // ================================================================

    /**
     * テーブルから全行を取得
     * メタエンティティは文字列やnullを返す場合がある
     */
    public static function getEntity(string $entity)
    {
        $pdo = self::connect();

        if (self::isMetaEntity($entity)) {
            $stmt = $pdo->prepare("SELECT meta_value FROM system_meta WHERE meta_key = ?");
            $stmt->execute([$entity]);
            $row = $stmt->fetch();
            if ($row && $row['meta_value'] !== null) {
                $decoded = json_decode($row['meta_value'], true);
                // タイムスタンプ等の文字列値はそのまま返す
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    return trim($row['meta_value'], '"');
                }
                return $decoded;
            }
            // スキーマからデフォルト値を取得
            if (class_exists('DataSchema')) {
                return DataSchema::getDefault($entity);
            }
            return null;
        }

        if (!self::isTableEntity($entity)) {
            return [];
        }

        // テーブルが存在しない場合はデフォルト値を返す
        if (!self::tableExists($entity)) {
            error_log("DB: テーブル {$entity} が存在しません。スキップします。");
            if (class_exists('DataSchema')) {
                return DataSchema::getDefault($entity) ?? [];
            }
            return [];
        }

        $stmt = $pdo->query("SELECT * FROM `{$entity}`");
        $rows = $stmt->fetchAll();

        return array_map(function ($row) use ($entity) {
            // dbToRow 内で array_change_key_case 済み
            return self::dbToRow($entity, $row);
        }, $rows);
    }

    /**
     * テーブルにデータを保存
     *
     * デフォルト動作: UPSERT 方式（差分検出 + INSERT...ON DUPLICATE KEY UPDATE）
     * 旧動作（DELETE-ALL + INSERT-ALL）に戻すには .env で DB_SAVE_MODE=full_replace を設定
     */
    public static function saveEntity(string $entity, $data): void
    {
        $pdo = self::connect();

        if (self::isMetaEntity($entity)) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare(
                "INSERT INTO system_meta (meta_key, meta_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
            );
            $stmt->execute([$entity, $json]);
            return;
        }

        if (!self::isTableEntity($entity) || !is_array($data)) {
            return;
        }
        if (!self::tableExists($entity)) {
            return;
        }

        $mode = env('DB_SAVE_MODE', 'upsert');
        if ($mode === 'full_replace') {
            self::saveEntityFullReplace($pdo, $entity, $data);
        } else {
            self::saveEntityUpsert($pdo, $entity, $data);
        }
    }

    /**
     * 旧式: テーブル全DELETE + 全INSERT（安全のため保持・緊急時のフォールバック）
     */
    private static function saveEntityFullReplace(PDO $pdo, string $entity, array $data): void
    {
        $pdo->exec("DELETE FROM `{$entity}`");
        if (empty($data)) return;

        $tableColumns = self::getTableColumns($entity);
        $firstRow = self::rowToDb($entity, $data[0]);
        $columns = array_keys($firstRow);
        if (!empty($tableColumns)) {
            $columns = array_values(array_filter($columns, fn($c) => in_array($c, $tableColumns, true)));
        }
        if (empty($columns)) return;

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $stmt = $pdo->prepare("INSERT INTO `{$entity}` ({$columnList}) VALUES ({$placeholders})");
        foreach ($data as $row) {
            $dbRow = self::rowToDb($entity, $row);
            $values = [];
            foreach ($columns as $col) {
                $values[] = $dbRow[$col] ?? null;
            }
            $stmt->execute($values);
        }
    }

    /**
     * 新方式: UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) + 差分削除
     * - 既存行と $data の id を突き合わせ、不在の行のみ DELETE
     * - $data の各行は UPSERT
     * - インデックス更新を最小化し、大規模テーブルでも高速
     */
    private static function saveEntityUpsert(PDO $pdo, string $entity, array $data): void
    {
        $tableColumns = self::getTableColumns($entity);

        // 1. $data が空 → 全削除
        if (empty($data)) {
            $pdo->exec("DELETE FROM `{$entity}`");
            return;
        }

        // 2. 入力データのIDを収集（id 必須前提）
        $incomingIds = [];
        foreach ($data as $row) {
            $id = $row['id'] ?? null;
            if ($id !== null && $id !== '') {
                $incomingIds[] = (string)$id;
            }
        }

        // 3. ID無し行が混ざっている場合は安全のため旧方式にフォールバック
        if (count($incomingIds) !== count($data)) {
            error_log("saveEntityUpsert: {$entity} に id 無しの行を検出。full_replace にフォールバック");
            self::saveEntityFullReplace($pdo, $entity, $data);
            return;
        }

        // 4. DB側にあって $data に無い行を削除（chunkで安全に処理）
        $chunks = array_chunk($incomingIds, 500);
        // 念のため全行削除する前に「DB側のID集合」を取得し、不要IDのみ削除
        $existingStmt = $pdo->query("SELECT id FROM `{$entity}`");
        $existingIds = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
        $incomingSet = array_flip($incomingIds);
        $toDelete = [];
        foreach ($existingIds as $eid) {
            if (!isset($incomingSet[(string)$eid])) {
                $toDelete[] = (string)$eid;
            }
        }
        if (!empty($toDelete)) {
            foreach (array_chunk($toDelete, 500) as $delChunk) {
                $ph = implode(',', array_fill(0, count($delChunk), '?'));
                $stmt = $pdo->prepare("DELETE FROM `{$entity}` WHERE id IN ({$ph})");
                $stmt->execute($delChunk);
            }
        }

        // 5. カラム決定（最初の行から）
        $firstRow = self::rowToDb($entity, $data[0]);
        $columns = array_keys($firstRow);
        if (!empty($tableColumns)) {
            $columns = array_values(array_filter($columns, fn($c) => in_array($c, $tableColumns, true)));
        }
        if (empty($columns)) return;

        // 6. UPSERT 文を準備
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $updateClause = implode(', ', array_map(
            fn($c) => "`{$c}` = VALUES(`{$c}`)",
            // id は更新対象から除外（PRIMARY KEYなので変更不可）
            array_filter($columns, fn($c) => $c !== 'id')
        ));
        $sql = "INSERT INTO `{$entity}` ({$columnList}) VALUES ({$placeholders})"
             . " ON DUPLICATE KEY UPDATE {$updateClause}";
        $stmt = $pdo->prepare($sql);

        // 7. 全行UPSERT
        foreach ($data as $row) {
            $dbRow = self::rowToDb($entity, $row);
            $values = [];
            foreach ($columns as $col) {
                $values[] = $dbRow[$col] ?? null;
            }
            $stmt->execute($values);
        }
    }

    // ================================================================
    // 部分取得API（getEntity の代替・絞り込み付き）
    // ================================================================

    /**
     * テーブルから絞り込み付きで行を取得
     *
     * @param string $entity テーブル名
     * @param array  $options [
     *     'where'    => ['column' => 'value', ...]         // = 比較（複数AND）
     *     'in'       => ['column' => [value1, value2]]     // IN句
     *     'like'     => ['column' => 'keyword']            // %keyword% で部分一致
     *     'date'     => ['column' => ['from' => '...', 'to' => '...']] // 範囲
     *     'not_deleted' => true,                            // deleted_at IS NULL を自動付与
     *     'order_by' => 'column DESC',                      // ORDER BY 文（生のSQL断片）
     *     'limit'    => 100,
     *     'offset'   => 0,
     * ]
     * @return array 行配列（dbToRow 後）
     *
     * 使用例:
     *   Database::queryEntity('mf_invoices', [
     *     'date' => ['billing_date' => ['from' => '2026-04-01', 'to' => '2026-04-30']],
     *     'where' => ['partner_name' => '日建リース工業'],
     *     'not_deleted' => true,
     *     'order_by' => 'billing_date DESC',
     *     'limit' => 100,
     *   ]);
     */
    public static function queryEntity(string $entity, array $options = []): array
    {
        if (!self::isTableEntity($entity)) return [];
        if (!self::tableExists($entity)) return [];

        $pdo = self::connect();
        $tableColumns = self::getTableColumns($entity);
        $colLower = array_map('strtolower', $tableColumns);

        $sql = "SELECT * FROM `{$entity}`";
        $whereParts = [];
        $params = [];

        // where (=)
        foreach (($options['where'] ?? []) as $col => $value) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            $whereParts[] = "`{$col}` = ?";
            $params[] = $value;
        }
        // in
        foreach (($options['in'] ?? []) as $col => $values) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            if (!is_array($values) || empty($values)) continue;
            $ph = implode(',', array_fill(0, count($values), '?'));
            $whereParts[] = "`{$col}` IN ({$ph})";
            foreach ($values as $v) $params[] = $v;
        }
        // like (%keyword%)
        foreach (($options['like'] ?? []) as $col => $keyword) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            $whereParts[] = "`{$col}` LIKE ?";
            $params[] = '%' . $keyword . '%';
        }
        // date range
        foreach (($options['date'] ?? []) as $col => $range) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            if (!is_array($range)) continue;
            if (!empty($range['from'])) {
                $whereParts[] = "`{$col}` >= ?";
                $params[] = $range['from'];
            }
            if (!empty($range['to'])) {
                $whereParts[] = "`{$col}` <= ?";
                $params[] = $range['to'];
            }
        }
        // not_deleted シュガー: deleted_at IS NULL
        if (!empty($options['not_deleted']) && in_array('deleted_at', $colLower, true)) {
            $whereParts[] = "(`deleted_at` IS NULL OR `deleted_at` = '')";
        }

        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        // order by (生SQL断片・サニタイズはコール側責任)
        if (!empty($options['order_by'])) {
            // 簡易バリデーション: 英数 _ , スペース のみ許可（SQLi予防）
            $orderBy = (string)$options['order_by'];
            if (preg_match('/^[\w\s,.`()]+(\s+(ASC|DESC))?(\s*,\s*[\w\s,.`()]+(\s+(ASC|DESC))?)*$/i', $orderBy)) {
                $sql .= ' ORDER BY ' . $orderBy;
            }
        }

        // limit / offset
        $limit = isset($options['limit']) ? (int)$options['limit'] : 0;
        $offset = isset($options['offset']) ? (int)$options['offset'] : 0;
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) $sql .= " OFFSET {$offset}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function ($row) use ($entity) {
            return self::dbToRow($entity, $row);
        }, $rows);
    }

    /**
     * 件数を取得（queryEntity と同じ $options を受ける）
     */
    public static function countEntity(string $entity, array $options = []): int
    {
        if (!self::isTableEntity($entity)) return 0;
        if (!self::tableExists($entity)) return 0;

        $pdo = self::connect();
        $tableColumns = self::getTableColumns($entity);
        $colLower = array_map('strtolower', $tableColumns);

        $sql = "SELECT COUNT(*) FROM `{$entity}`";
        $whereParts = [];
        $params = [];
        foreach (($options['where'] ?? []) as $col => $value) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            $whereParts[] = "`{$col}` = ?";
            $params[] = $value;
        }
        foreach (($options['in'] ?? []) as $col => $values) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            if (!is_array($values) || empty($values)) continue;
            $ph = implode(',', array_fill(0, count($values), '?'));
            $whereParts[] = "`{$col}` IN ({$ph})";
            foreach ($values as $v) $params[] = $v;
        }
        foreach (($options['like'] ?? []) as $col => $keyword) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            $whereParts[] = "`{$col}` LIKE ?";
            $params[] = '%' . $keyword . '%';
        }
        foreach (($options['date'] ?? []) as $col => $range) {
            if (!in_array(strtolower($col), $colLower, true)) continue;
            if (!is_array($range)) continue;
            if (!empty($range['from'])) { $whereParts[] = "`{$col}` >= ?"; $params[] = $range['from']; }
            if (!empty($range['to']))   { $whereParts[] = "`{$col}` <= ?"; $params[] = $range['to']; }
        }
        if (!empty($options['not_deleted']) && in_array('deleted_at', $colLower, true)) {
            $whereParts[] = "(`deleted_at` IS NULL OR `deleted_at` = '')";
        }
        if (!empty($whereParts)) $sql .= ' WHERE ' . implode(' AND ', $whereParts);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 1行取得（id指定）
     */
    public static function findEntityById(string $entity, $id): ?array
    {
        if (!self::isTableEntity($entity)) return null;
        if (!self::tableExists($entity)) return null;

        $pdo = self::connect();
        $stmt = $pdo->prepare("SELECT * FROM `{$entity}` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::dbToRow($entity, $row) : null;
    }

    // ================================================================
    // 一括操作（getData/saveData 互換）
    // ================================================================

    /**
     * 全エンティティをDBから読み込み（getData()互換の配列を返す）
     */
    public static function getAllData(): array
    {
        $data = [];

        // テーブルエンティティ
        foreach (self::$tableEntities as $entity) {
            $data[$entity] = self::getEntity($entity);
        }

        // メタエンティティ
        foreach (self::$metaEntities as $entity) {
            $data[$entity] = self::getEntity($entity);
        }

        // スキーマで補完
        if (class_exists('DataSchema')) {
            $data = DataSchema::ensureSchema($data);
        }

        // 整合性チェック: 必須キーが存在するか検証
        self::validateData($data);

        return $data;
    }

    /**
     * DBから読み取ったデータの整合性を検証
     * 必須キーが欠落している場合は例外をスロー（カラム名不一致を検出）
     */
    private static function validateData(array $data): void
    {
        $criticalEntities = ['employees', 'projects', 'customers', 'partners', 'troubles'];

        foreach ($criticalEntities as $entity) {
            if (!isset($data[$entity]) || !is_array($data[$entity]) || empty($data[$entity])) {
                continue;
            }

            $requiredFields = class_exists('DataSchema')
                ? DataSchema::getRequiredFields($entity)
                : ['id'];

            $firstRow = $data[$entity][0];
            $missingKeys = [];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $firstRow)) {
                    $missingKeys[] = $field;
                }
            }

            if (!empty($missingKeys)) {
                $actualKeys = implode(', ', array_keys($firstRow));
                throw new \Exception(
                    "DB整合性エラー: {$entity} テーブルに必須キーがありません: "
                    . implode(', ', $missingKeys)
                    . " （カラム名の大文字/小文字不一致の可能性）"
                    . " 実際のキー: {$actualKeys}"
                );
            }
        }
    }

    /**
     * 全エンティティをDBに保存（saveData()互換）
     *
     * 変更検出: 各エンティティのハッシュを比較し、変更があったもののみ書き込む
     */
    public static function saveAllData(array $data): void
    {
        $pdo = self::connect();

        // 現在のDBデータのハッシュを取得（差分検出用）
        static $lastHashes = [];

        $pdo->beginTransaction();
        try {
            foreach ($data as $entity => $value) {
                // 変更検出（ハッシュ比較）
                $hash = md5(json_encode($value, JSON_UNESCAPED_UNICODE));
                if (isset($lastHashes[$entity]) && $lastHashes[$entity] === $hash) {
                    continue; // 変更なし → スキップ
                }

                self::saveEntity($entity, $value);
                $lastHashes[$entity] = $hash;
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \Exception("DB保存エラー: " . $e->getMessage());
        }
    }
}
