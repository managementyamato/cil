<?php
/**
 * 統一ログシステム
 *
 * アプリケーション全体で一貫したログ記録を提供
 * - ログレベル（DEBUG, INFO, WARNING, ERROR, CRITICAL）
 * - 構造化ログ（JSON形式）対応
 * - ファイルローテーション
 * - コンテキスト情報の付与
 */

class Logger {
    // ログレベル定数
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_CRITICAL = 4;

    // レベル名マッピング
    private static array $levelNames = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_CRITICAL => 'CRITICAL',
    ];

    // シングルトンインスタンス
    private static ?Logger $instance = null;

    // 設定
    private string $logDirectory;
    private string $logPrefix = 'app';
    private int $minLevel = self::LEVEL_DEBUG;
    private string $format = 'text'; // 'text' or 'json'
    private bool $includeTrace = false;
    private int $maxFileSize = 10485760; // 10MB

    /**
     * シングルトンインスタンスを取得
     */
    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ（privateでシングルトン強制）
     */
    private function __construct() {
        // デフォルトのログディレクトリ
        $this->logDirectory = dirname(__DIR__) . '/logs';
    }

    /**
     * ログディレクトリを設定
     */
    public function setLogDirectory(string $path): self {
        $this->logDirectory = $path;
        return $this;
    }

    /**
     * ログファイルのプレフィックスを設定
     */
    public function setLogPrefix(string $prefix): self {
        $this->logPrefix = $prefix;
        return $this;
    }

    /**
     * 最小ログレベルを設定
     */
    public function setMinLevel(int $level): self {
        $this->minLevel = $level;
        return $this;
    }

    /**
     * 出力フォーマットを設定
     */
    public function setFormat(string $format): self {
        $this->format = $format;
        return $this;
    }

    /**
     * スタックトレースを含めるかどうか
     */
    public function setIncludeTrace(bool $include): self {
        $this->includeTrace = $include;
        return $this;
    }

    /**
     * DEBUGレベルのログ
     */
    public function debug(string $message, array $context = []): bool {
        return $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * INFOレベルのログ
     */
    public function info(string $message, array $context = []): bool {
        return $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * WARNINGレベルのログ
     */
    public function warning(string $message, array $context = []): bool {
        return $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * ERRORレベルのログ
     */
    public function error(string $message, array $context = []): bool {
        return $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * CRITICALレベルのログ
     */
    public function critical(string $message, array $context = []): bool {
        return $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * 例外をログに記録
     */
    public function exception(\Throwable $e, string $message = '', array $context = []): bool {
        $context['exception'] = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        $logMessage = $message ?: $e->getMessage();
        return $this->log(self::LEVEL_ERROR, $logMessage, $context);
    }

    /**
     * メインのログ記録メソッド
     */
    public function log(int $level, string $message, array $context = []): bool {
        // レベルフィルタリング
        if ($level < $this->minLevel) {
            return true;
        }

        // ログディレクトリの作成
        if (!is_dir($this->logDirectory)) {
            if (!@mkdir($this->logDirectory, 0755, true)) {
                // ディレクトリ作成失敗時は静かにfalseを返す（本番環境でエラー表示しない）
                return false;
            }
        }

        // ログファイルパス
        $logFile = $this->logDirectory . '/' . $this->logPrefix . '-' . date('Y-m-d') . '.log';

        // ファイルローテーションチェック
        $this->rotateIfNeeded($logFile);

        // ログエントリを構築
        $entry = $this->buildEntry($level, $message, $context);

        // ファイルに書き込み（エラー抑制して本番で警告表示しない）
        $result = @file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);

        return $result !== false;
    }

    /**
     * ログエントリを構築
     */
    private function buildEntry(int $level, string $message, array $context): string {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::$levelNames[$level] ?? 'UNKNOWN';

        // コンテキストにリクエスト情報を追加
        $context = array_merge($this->getRequestContext(), $context);

        if ($this->includeTrace && $level >= self::LEVEL_ERROR) {
            $context['trace'] = $this->getStackTrace();
        }

        if ($this->format === 'json') {
            return json_encode([
                'timestamp' => $timestamp,
                'level' => $levelName,
                'message' => $message,
                'context' => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // テキスト形式
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        return "[{$timestamp}] [{$levelName}] {$message}{$contextStr}";
    }

    /**
     * リクエストコンテキストを取得
     */
    private function getRequestContext(): array {
        $context = [];

        // リクエストID（トレーシング用）
        if (!defined('REQUEST_ID')) {
            define('REQUEST_ID', substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
        }
        $context['request_id'] = REQUEST_ID;

        // ユーザー情報
        if (isset($_SESSION['user_email'])) {
            $context['user'] = $_SESSION['user_email'];
        }

        // リクエスト情報
        if (isset($_SERVER['REQUEST_URI'])) {
            $context['uri'] = $_SERVER['REQUEST_URI'];
        }
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $context['method'] = $_SERVER['REQUEST_METHOD'];
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $context['ip'] = $_SERVER['REMOTE_ADDR'];
        }

        return $context;
    }

    /**
     * スタックトレースを取得
     */
    private function getStackTrace(): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        // Logger自体の呼び出しを除外
        return array_slice($trace, 3, 10);
    }

    /**
     * ファイルローテーション
     */
    private function rotateIfNeeded(string $logFile): void {
        if (!file_exists($logFile)) {
            return;
        }

        if (filesize($logFile) < $this->maxFileSize) {
            return;
        }

        // 古いログファイルをリネーム
        $rotatedFile = $logFile . '.' . date('His');
        rename($logFile, $rotatedFile);

        // 古いローテーションファイルを削除（7日以上前）
        $this->cleanOldLogs();
    }

    /**
     * 古いログファイルを削除
     */
    private function cleanOldLogs(): void {
        $pattern = $this->logDirectory . '/' . $this->logPrefix . '-*.log*';
        $files = glob($pattern);
        $cutoff = strtotime('-7 days');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

// ==================== グローバルヘルパー関数 ====================

/**
 * ログを記録するショートカット関数
 */
function logMessage(int $level, string $message, array $context = []): bool {
    return Logger::getInstance()->log($level, $message, $context);
}

/**
 * デバッグログ
 */
function logDebug(string $message, array $context = []): bool {
    return Logger::getInstance()->debug($message, $context);
}

/**
 * 情報ログ
 */
function logInfo(string $message, array $context = []): bool {
    return Logger::getInstance()->info($message, $context);
}

/**
 * 警告ログ
 */
function logWarning(string $message, array $context = []): bool {
    return Logger::getInstance()->warning($message, $context);
}

/**
 * エラーログ
 */
function logError(string $message, array $context = []): bool {
    return Logger::getInstance()->error($message, $context);
}

/**
 * 致命的エラーログ
 */
function logCritical(string $message, array $context = []): bool {
    return Logger::getInstance()->critical($message, $context);
}

/**
 * 例外ログ
 */
function logException(\Throwable $e, string $message = '', array $context = []): bool {
    return Logger::getInstance()->exception($e, $message, $context);
}

// ==================== 設定の初期化 ====================

// 本番環境ではWARNING以上のみログ
if (defined('TESTING') && TESTING) {
    Logger::getInstance()->setMinLevel(Logger::LEVEL_DEBUG);
} elseif (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'production') {
    Logger::getInstance()->setMinLevel(Logger::LEVEL_WARNING);
    Logger::getInstance()->setFormat('json');
}
