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
        'projects'         => ['invoice_ids'],
        'customers'        => ['aliases', 'branches'],
        'tasks'            => ['subtasks', 'mentions'],
        'announcements'    => ['read_by'],
        'memos'            => ['tags'],
        'chat_rooms'       => ['members'],
        'chat_messages'    => ['mentions'],
        'slides'           => ['required_for'],
        'weekly_reports'   => ['private_recipients'],
    ];

    // --- bool カラムを持つエンティティの定義 ---
    private static array $boolColumns = [
        'announcements' => ['pinned'],
        'memos'         => ['pinned'],
        'chat_rooms'    => ['is_default'],
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
        'chat_rooms', 'chat_messages', 'chat_read_status',
        'slides', 'company_rules', 'contacts', 'leads',
        'morning_todos', 'weekly_reports', 'discount_approvals',
        'slide_confirmations',
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

        $stmt = $pdo->query("SELECT * FROM `{$entity}`");
        $rows = $stmt->fetchAll();

        return array_map(function ($row) use ($entity) {
            return self::dbToRow($entity, $row);
        }, $rows);
    }

    /**
     * テーブルにデータを保存（トランザクション内でDELETE+INSERT）
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

        // DELETE + INSERT（トランザクションは呼び出し元で管理）
        $pdo->exec("DELETE FROM `{$entity}`");

        if (empty($data)) {
            return;
        }

        // 最初の行からカラム名を取得
        $firstRow = self::rowToDb($entity, $data[0]);
        $columns = array_keys($firstRow);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

        $stmt = $pdo->prepare(
            "INSERT INTO `{$entity}` ({$columnList}) VALUES ({$placeholders})"
        );

        foreach ($data as $row) {
            $dbRow = self::rowToDb($entity, $row);
            // カラム順を揃える（行によってキーが異なる場合に対応）
            $values = [];
            foreach ($columns as $col) {
                $values[] = $dbRow[$col] ?? null;
            }
            $stmt->execute($values);
        }
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

        return $data;
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
