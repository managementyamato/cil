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
//
// --- Sprint 1 (2026-05-18) リファクタ ---
// エンティティ定義・型変換・モード判定は責任ごとに分離した:
//   - config/db/JsonColumnHandler.php  : JSON/bool/date カラム定義と型変換
//   - config/db/DualModeAdapter.php    : DB_MODE 判定とエンティティ分類
//   - config/db/DBSaveModeManager.php  : DB_SAVE_MODE 読み取り (CLAUDE.md 最重要 #0 配下)
// Database クラスはこれらに delegate するファサード。公開 API は変更しない。
require_once __DIR__ . '/db/JsonColumnHandler.php';
require_once __DIR__ . '/db/DualModeAdapter.php';
require_once __DIR__ . '/db/DBSaveModeManager.php';

class Database
{
    private static ?PDO $pdo = null;

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

        // 共通の PDO オプション
        $options = [
            PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES     => false,
        ];

        // XServer の MySQL は wait_timeout=60秒（短い）。長時間処理中の "MySQL server has gone away" を防ぐため
        // セッションレベルで延長。本番側設定は変更不要。
        // ※ MYSQL_ATTR_INIT_COMMAND は pdo_mysql 拡張が読み込まれていないと未定義 (ローカル dev環境等)。
        //   defined() でガードしてクラスロード時に Fatal error にならないようにする。
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[constant('PDO::MYSQL_ATTR_INIT_COMMAND')] = "SET SESSION wait_timeout=600, interactive_timeout=600";
        }

        // "Connection refused" 等の一時的な接続失敗に対するリトライ (短い指数バックオフ)
        // XServer の MySQL は混雑時に瞬間的に接続を拒否することがある
        $maxAttempts = 3;
        $lastErr = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
                if ($attempt > 1) {
                    error_log("[Database::connect] {$attempt}回目の試行で接続成功");
                }
                return self::$pdo;
            } catch (\PDOException $e) {
                $lastErr = $e;
                $msg = strtolower($e->getMessage());
                $isTransient = (strpos($msg, 'connection refused') !== false
                    || strpos($msg, "can't connect") !== false
                    || strpos($msg, 'too many connections') !== false);
                if (!$isTransient || $attempt >= $maxAttempts) {
                    break;
                }
                error_log("[Database::connect] 接続失敗 (試行{$attempt}回目): " . $e->getMessage() . " → リトライ");
                usleep(200000 * $attempt); // 0.2s, 0.4s
            }
        }
        throw $lastErr;
    }

    /**
     * DB モードが有効かチェック
     * (DualModeAdapter に delegate / 公開 API 維持)
     */
    public static function isEnabled(): bool
    {
        return DualModeAdapter::isEnabled();
    }

    /**
     * 現在のモードを取得
     * (DualModeAdapter に delegate / 公開 API 維持)
     */
    public static function getMode(): string
    {
        return DualModeAdapter::getMode();
    }

    // ================================================================
    // エンティティ判定 (DualModeAdapter に delegate)
    // ================================================================

    private static function isMetaEntity(string $entity): bool
    {
        return DualModeAdapter::isMetaEntity($entity);
    }

    private static function isTableEntity(string $entity): bool
    {
        return DualModeAdapter::isTableEntity($entity);
    }

    // ================================================================
    // 行変換（PHP配列 ↔ DB行） - JsonColumnHandler に delegate
    // ================================================================

    /**
     * PHP配列 → DB行（JSON/boolカラムをエンコード）
     * (JsonColumnHandler::rowToDb に delegate / 公開 API 維持)
     */
    public static function rowToDb(string $entity, array $row): array
    {
        return JsonColumnHandler::rowToDb($entity, $row);
    }

    /**
     * DB行 → PHP配列（JSON/boolカラムをデコード）
     * (JsonColumnHandler::dbToRow に delegate)
     */
    private static function dbToRow(string $entity, array $row): array
    {
        return JsonColumnHandler::dbToRow($entity, $row);
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
     *
     * 自動再接続: "MySQL server has gone away" を検知した時、1回だけ再接続して再試行する。
     * 単発の saveData($data, ['entity']) 経路でも接続断に強くなる。
     */
    public static function saveEntity(string $entity, $data): void
    {
        $attempts = 0;
        while (true) {
            $attempts++;
            try {
                self::saveEntityInternal($entity, $data);
                return;
            } catch (\Throwable $e) {
                $msg = strtolower($e->getMessage());
                $isConnLost = (strpos($msg, 'gone away') !== false
                    || strpos($msg, 'lost connection') !== false
                    || strpos($msg, 'server has gone') !== false);
                if ($isConnLost && $attempts < 2) {
                    error_log("[Database::saveEntity] {$entity} 接続切れ検知 → 再接続して再試行");
                    self::$pdo = null;
                    try {
                        self::connect();
                    } catch (\Throwable $rcErr) {
                        throw new \Exception("再接続失敗: " . $rcErr->getMessage(), 0, $e);
                    }
                    continue; // 再試行
                }
                throw $e;
            }
        }
    }

    /**
     * saveEntity の内部実装 (再接続ループから呼ばれる)
     */
    private static function saveEntityInternal(string $entity, $data): void
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

        // ========================================================
        // ⚠️ 重要: 保存モードのデフォルトは "full_replace"（安全側）
        // ========================================================
        // UPSERT モードは過去 2 回（2026-05-11, 2026-05-12）に regression を起こしている:
        //   - employees テーブル破損 → ログイン障害
        //   - weekly_reports 保存失敗 → 500エラー → 権限消失
        //
        // UPSERT を有効化する場合は **必ずステージング環境で検証してから**
        // 本番 .env に `DB_SAVE_MODE=upsert` を明示的に設定すること。
        //
        // デフォルトは "full_replace"（旧式・DELETE-ALL + INSERT-ALL・確実）。
        // ※ DBSaveModeManager::getMode() は env('DB_SAVE_MODE', 'full_replace') と完全等価。
        //   CLAUDE.md 最重要 #0: デフォルト値 'full_replace' を変更しないこと。
        $mode = DBSaveModeManager::getMode();
        if ($mode === 'upsert') {
            try {
                self::saveEntityUpsert($pdo, $entity, $data);
            } catch (\Throwable $e) {
                // UPSERT 失敗 → 自動で full_replace にフォールバック（権限消失を防ぐ）
                error_log("[Database] UPSERT failed for {$entity}: " . $e->getMessage()
                    . " → falling back to full_replace");
                self::saveEntityFullReplace($pdo, $entity, $data);
            }
        } else {
            // full_replace（デフォルト・推奨）
            self::saveEntityFullReplace($pdo, $entity, $data);
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
    /**
     * 単一行を UPSERT する（INSERT ... ON DUPLICATE KEY UPDATE）
     *
     * 用途: 同時編集が起きうるテーブル（weekly_reports など）で、
     *      全件 DELETE-INSERT による「他人の変更が消える」問題を防ぐ。
     *
     * 特徴:
     * - DELETE を一切しないため他行を巻き込まない
     * - PRIMARY KEY (id) の衝突で UPDATE
     * - 既存行が無ければ INSERT
     *
     * @param string $entity テーブル名
     * @param array  $row    保存する行（id 必須）
     * @throws Exception id が無い場合 / テーブルが存在しない場合 / SQL実行失敗
     */
    public static function saveEntityRow(string $entity, array $row): void
    {
        if (empty($row['id']) && $row['id'] !== 0 && $row['id'] !== '0') {
            throw new \Exception("saveEntityRow: '{$entity}' の保存には id が必要です");
        }
        if (!self::isTableEntity($entity)) {
            throw new \Exception("saveEntityRow: '{$entity}' は登録されたテーブルではありません");
        }
        $pdo = self::connect();
        if (!self::tableExists($entity)) {
            throw new \Exception("saveEntityRow: テーブル '{$entity}' が存在しません");
        }

        // 接続切れ対策で 1 回だけリトライ
        $attempts = 0;
        while (true) {
            $attempts++;
            try {
                self::saveEntityRowInternal($pdo, $entity, $row);
                return;
            } catch (\Throwable $e) {
                $msg = strtolower($e->getMessage());
                $isConnLost = (strpos($msg, 'gone away') !== false
                    || strpos($msg, 'lost connection') !== false
                    || strpos($msg, 'server has gone') !== false);
                if ($isConnLost && $attempts < 2) {
                    error_log("[Database::saveEntityRow] {$entity} 接続切れ → 再接続");
                    self::$pdo = null;
                    $pdo = self::connect();
                    continue;
                }
                throw $e;
            }
        }
    }

    private static function saveEntityRowInternal(PDO $pdo, string $entity, array $row): void
    {
        $tableColumns = self::getTableColumns($entity);
        $dbRow = self::rowToDb($entity, $row);

        // 実テーブルに存在するカラムだけに絞る
        $columns = array_keys($dbRow);
        if (!empty($tableColumns)) {
            $columns = array_values(array_filter($columns, fn($c) => in_array($c, $tableColumns, true)));
        }
        if (empty($columns)) {
            throw new \Exception("saveEntityRow: '{$entity}' に有効なカラムがありません");
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $updateClause = implode(', ', array_map(
            fn($c) => "`{$c}` = VALUES(`{$c}`)",
            array_filter($columns, fn($c) => $c !== 'id')  // id は更新対象から除外
        ));

        $sql = "INSERT INTO `{$entity}` ({$columnList}) VALUES ({$placeholders})"
             . " ON DUPLICATE KEY UPDATE {$updateClause}";
        $stmt = $pdo->prepare($sql);
        $values = array_map(fn($c) => $dbRow[$c] ?? null, $columns);
        $stmt->execute($values);
    }

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

        // テーブルエンティティ (DualModeAdapter に集約)
        foreach (DualModeAdapter::tableEntities() as $entity) {
            $data[$entity] = self::getEntity($entity);
        }

        // メタエンティティ (DualModeAdapter に集約)
        foreach (DualModeAdapter::metaEntities() as $entity) {
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
     * 重要: エンティティごとに独立トランザクション + 差分検出（ハッシュ永続化）
     * - 変更がないエンティティは保存しない（mf_invoices 1万件等を無駄に書き込まない）
     * - 1エンティティの失敗が他をブロックしない
     * - 接続切れ時は自動再接続
     */
    public static function saveAllData(array $data): void
    {
        $pdo = self::connect();

        // 永続ハッシュキャッシュ（リクエスト跨ぎで保持）
        $hashFile = dirname(__DIR__) . '/config/.saved-hashes.json';
        $savedHashes = [];
        if (file_exists($hashFile)) {
            $raw = @file_get_contents($hashFile);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded)) $savedHashes = $decoded;
        }

        $failures = [];
        $skipped  = [];
        $saved    = [];
        foreach ($data as $entity => $value) {
            // 変更検出（前回保存時のハッシュと比較）
            $hash = md5(json_encode($value, JSON_UNESCAPED_UNICODE));
            if (($savedHashes[$entity] ?? null) === $hash) {
                $skipped[] = $entity;
                continue; // 変更なし → スキップ
            }

            // エンティティごとに独立トランザクション
            $inTx = false;
            try {
                $pdo->beginTransaction();
                $inTx = true;
                self::saveEntity($entity, $value);
                $pdo->commit();
                $inTx = false;
                $savedHashes[$entity] = $hash;
                $saved[] = $entity;
            } catch (\Exception $e) {
                if ($inTx) {
                    try { $pdo->rollBack(); } catch (\Exception $rb) { /* dead connection */ }
                }
                $failures[$entity] = $e->getMessage();
                error_log("[saveAllData] entity={$entity} 失敗: " . $e->getMessage());

                // 接続切れ → 再接続
                $msgLower = strtolower($e->getMessage());
                if (strpos($msgLower, 'gone away') !== false || strpos($msgLower, 'lost connection') !== false) {
                    self::$pdo = null;
                    try {
                        $pdo = self::connect();
                    } catch (\Exception $rcErr) {
                        $failures['__connection_lost__'] = $rcErr->getMessage();
                        break;
                    }
                }
            }
        }

        // 成功した分のハッシュを永続化
        if (!empty($saved)) {
            @file_put_contents($hashFile, json_encode($savedHashes, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }

        if (!empty($failures)) {
            $first = array_key_first($failures);
            $failedList = implode(',', array_keys($failures));
            throw new \Exception("DB保存エラー [失敗={$failedList}]: " . $failures[$first]);
        }
    }
}
