<?php
/**
 * セキュリティ機能
 *
 * - セキュリティヘッダー設定
 * - レート制限
 * - パスワードポリシー
 * - IPホワイトリスト
 */

// ==================== セキュリティヘッダー ====================

/**
 * CSP nonceを取得（setSecurityHeaders()呼び出し後に使用可能）
 * @return string nonce文字列
 */
function cspNonce() {
    return $GLOBALS['csp_nonce'] ?? '';
}

/**
 * CSP nonce属性を出力（<script nonce="..."> / <style nonce="..."> 用）
 * @return string nonce="xxx" 形式の文字列
 */
function nonceAttr() {
    $nonce = cspNonce();
    return $nonce ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES) . '"' : '';
}

/**
 * セキュリティヘッダーを設定
 * @param array $options カスタムオプション
 */
function setSecurityHeaders($options = []) {
    $defaults = [
        'csp' => true,
        'hsts' => true,
        'xss' => true,
        'nosniff' => true,
        'frame' => true,
        'referrer' => true,
    ];
    $options = array_merge($defaults, $options);

    // X-Content-Type-Options: MIME スニッフィング防止
    if ($options['nosniff']) {
        header('X-Content-Type-Options: nosniff');
    }

    // X-Frame-Options: クリックジャッキング防止
    if ($options['frame']) {
        header('X-Frame-Options: SAMEORIGIN');
    }

    // X-XSS-Protection: XSSフィルター有効化（レガシーブラウザ向け）
    if ($options['xss']) {
        header('X-XSS-Protection: 1; mode=block');
    }

    // Referrer-Policy: リファラー情報の制御
    if ($options['referrer']) {
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    // Content-Security-Policy: コンテンツセキュリティポリシー（nonce方式）
    if ($options['csp']) {
        // リクエストごとにnonceを生成（セッションに保存してテンプレートで使用）
        $nonce = base64_encode(random_bytes(16));
        $GLOBALS['csp_nonce'] = $nonce;

        // 段階的CSP移行:
        // Phase 1 (現在): script-src/style-src に 'unsafe-inline' を使用
        //   - 全ページの onclick 等のインラインイベントハンドラが残存しているため
        //   - nonce属性は既に全 <script>/<style> タグに付与済み
        // Phase 2 (将来): 全ページの onclick をイベントリスナーに移行後、
        //   script-src を 'nonce-{$nonce}' のみに変更（'unsafe-inline' 削除）
        //   style-src も 'nonce-{$nonce}' のみに変更
        // 完全に厳格なCSP（unsafe-inline 完全削除！）
        // script-src: 全221個のインラインイベントハンドラを削除 → unsafe-inline 削除完了
        // style-src: 1173個のうち955個を削除、残り218個は動的（PHP変数）→ unsafe-hashes で許可
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com",  // ✓ unsafe-inline 削除完了
            "style-src 'self' 'nonce-{$nonce}' 'unsafe-hashes'",  // ✓ unsafe-inline 削除、動的style属性のみ unsafe-hashes で許可
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https://lh3.googleusercontent.com",
            "connect-src 'self' https://chat.googleapis.com https://accounts.google.com",
            "frame-ancestors 'self'",
            "form-action 'self' https://accounts.google.com",
            "base-uri 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));

        // 未来の理想的なCSP（unsafe-hashes も不要になった場合）
        // 残り218個の動的スタイル（PHP変数）をすべてCSSクラス化した場合に有効化
        // 現時点では実用性とセキュリティのバランスから unsafe-hashes を許可
        // $perfectCsp = [
        //     "default-src 'self'",
        //     "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com",
        //     "style-src 'self' 'nonce-{$nonce}'",  // unsafe-hashes も削除
        //     "font-src 'self' https://fonts.gstatic.com",
        //     "img-src 'self' data: https://lh3.googleusercontent.com",
        //     "connect-src 'self' https://chat.googleapis.com https://accounts.google.com",
        //     "frame-ancestors 'self'",
        //     "form-action 'self' https://accounts.google.com",
        //     "base-uri 'self'",
        //     "object-src 'none'",
        //     "upgrade-insecure-requests",
        // ];
        // header('Content-Security-Policy-Report-Only: ' . implode('; ', $perfectCsp) . '; report-uri /api/csp-report.php');
    } elseif ($options['frame']) {
        // CSP無効時でもクリックジャッキング対策として frame-ancestors のみ送信
        // （APIエンドポイント等でHTML出力はないがヘッダー保護が必要な場合）
        header("Content-Security-Policy: frame-ancestors 'self'");
    }

    // Strict-Transport-Security: HTTPS強制（本番環境のみ）
    if ($options['hsts'] && isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Permissions-Policy: 機能の制限
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/**
 * HTTPS接続かどうかを判定
 */
function isHttps() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // リバースプロキシ対応
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    return false;
}

// ==================== クライアントIPアドレス取得 ====================

/**
 * 信頼できるプロキシのIPリストを取得
 * @return array 信頼できるプロキシのIPアドレスリスト
 */
function getTrustedProxies() {
    $configFile = dirname(__DIR__) . '/config/security-config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return $config['trusted_proxies'] ?? [];
    }
    return [];
}

/**
 * クライアントの実際のIPアドレスを取得
 *
 * セキュリティ注意:
 * X-Forwarded-Forヘッダーはクライアントが自由に設定可能なため、
 * 信頼できるプロキシからのリクエストの場合のみ使用する
 *
 * @return string クライアントIPアドレス
 */
function getClientIp() {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // 信頼できるプロキシのリストを取得
    $trustedProxies = getTrustedProxies();

    // 信頼できるプロキシからのリクエストでない場合はREMOTE_ADDRを返す
    if (empty($trustedProxies)) {
        return $remoteAddr;
    }

    // REMOTE_ADDRが信頼できるプロキシかチェック
    $isTrustedProxy = false;
    foreach ($trustedProxies as $proxy) {
        if (strpos($proxy, '/') !== false) {
            // CIDR記法
            if (ipInCidr($remoteAddr, $proxy)) {
                $isTrustedProxy = true;
                break;
            }
        } elseif ($remoteAddr === $proxy) {
            $isTrustedProxy = true;
            break;
        }
    }

    // 信頼できるプロキシからのリクエストの場合のみX-Forwarded-Forを使用
    if ($isTrustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X-Forwarded-For: client, proxy1, proxy2 の形式
        // 最初のIPがクライアントIP
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

        // 信頼できないプロキシを除外して最後の信頼できないIPを取得
        foreach ($ips as $ip) {
            // 有効なIPアドレスかチェック
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                // プライベートIPは信頼しない
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    continue;
                }
                return $ip;
            }
        }

        // 有効なパブリックIPが見つからない場合は最初のIPを使用
        $firstIp = trim($ips[0]);
        if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
            return $firstIp;
        }
    }

    return $remoteAddr;
}

// ==================== レート制限 ====================

/**
 * レート制限をチェック
 * @param string $key 識別キー（IPアドレスやユーザーID）
 * @param int $maxRequests 許可するリクエスト数
 * @param int $windowSeconds 時間枠（秒）
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
 */
function checkRateLimit($key, $maxRequests = 60, $windowSeconds = 60) {
    $rateLimitFile = dirname(__DIR__) . '/data/rate-limits.json';
    $rateLimitDir = dirname($rateLimitFile);

    // ディレクトリがなければ作成
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }

    // レート制限データを読み込み
    $limits = [];
    if (file_exists($rateLimitFile)) {
        $fp = fopen($rateLimitFile, 'r');
        if ($fp && flock($fp, LOCK_SH)) {
            $limits = json_decode(stream_get_contents($fp), true) ?: [];
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    $now = time();
    $windowStart = $now - $windowSeconds;

    // 期限切れのエントリを削除
    foreach ($limits as $k => $data) {
        if (($data['reset_at'] ?? 0) < $now) {
            unset($limits[$k]);
        }
    }

    // 現在のキーのデータを取得または初期化
    if (!isset($limits[$key]) || ($limits[$key]['reset_at'] ?? 0) < $now) {
        $limits[$key] = [
            'count' => 0,
            'reset_at' => $now + $windowSeconds
        ];
    }

    $limits[$key]['count']++;
    $remaining = max(0, $maxRequests - $limits[$key]['count']);
    $allowed = $limits[$key]['count'] <= $maxRequests;

    // 保存
    $fp = fopen($rateLimitFile, 'c');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($limits, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    return [
        'allowed' => $allowed,
        'remaining' => $remaining,
        'reset_at' => $limits[$key]['reset_at'],
        'limit' => $maxRequests
    ];
}

/**
 * レート制限ヘッダーを設定
 */
function setRateLimitHeaders($rateLimit) {
    header('X-RateLimit-Limit: ' . $rateLimit['limit']);
    header('X-RateLimit-Remaining: ' . $rateLimit['remaining']);
    header('X-RateLimit-Reset: ' . $rateLimit['reset_at']);
}

/**
 * レート制限超過時のレスポンス
 */
function respondRateLimitExceeded($rateLimit) {
    http_response_code(429);
    setRateLimitHeaders($rateLimit);
    header('Retry-After: ' . ($rateLimit['reset_at'] - time()));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'レート制限を超過しました。しばらく待ってから再試行してください。',
        'retry_after' => $rateLimit['reset_at'] - time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * IPアドレスベースのレート制限
 */
function checkIpRateLimit($maxRequests = 100, $windowSeconds = 60) {
    // セキュリティ強化: getClientIp()を使用して信頼できるプロキシ経由のみX-Forwarded-Forを使用
    $ip = getClientIp();

    $result = checkRateLimit('ip:' . $ip, $maxRequests, $windowSeconds);
    setRateLimitHeaders($result);

    if (!$result['allowed']) {
        respondRateLimitExceeded($result);
    }

    return $result;
}

/**
 * ユーザーベースのレート制限
 */
function checkUserRateLimit($userId, $maxRequests = 60, $windowSeconds = 60) {
    $result = checkRateLimit('user:' . $userId, $maxRequests, $windowSeconds);
    setRateLimitHeaders($result);

    if (!$result['allowed']) {
        respondRateLimitExceeded($result);
    }

    return $result;
}

// ==================== パスワードポリシー ====================

/**
 * パスワードポリシー設定を取得
 */
function getPasswordPolicy() {
    $configFile = dirname(__DIR__) . '/config/security-config.json';
    $defaults = [
        'min_length' => 8,
        'max_length' => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_special' => false,
        'disallow_common' => true,
        'disallow_username' => true,
        'history_count' => 0, // 過去N個のパスワードを禁止（0=無効）
    ];

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['password_policy'])) {
            return array_merge($defaults, $config['password_policy']);
        }
    }

    return $defaults;
}

/**
 * よく使われる危険なパスワードリスト
 */
function getCommonPasswords() {
    return [
        'password', 'password123', '123456', '12345678', 'qwerty',
        'admin', 'letmein', 'welcome', 'monkey', 'dragon',
        'master', 'login', 'princess', 'sunshine', 'passw0rd',
        'Password1', 'password1', 'abc123', 'admin123', 'root',
    ];
}

/**
 * パスワードポリシーに従って検証
 * @param string $password パスワード
 * @param string $username ユーザー名（オプション）
 * @param array $policy カスタムポリシー（オプション）
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePasswordPolicy($password, $username = '', $policy = null) {
    if ($policy === null) {
        $policy = getPasswordPolicy();
    }

    $errors = [];

    // 長さチェック
    $length = mb_strlen($password);
    if ($length < $policy['min_length']) {
        $errors[] = "{$policy['min_length']}文字以上で入力してください";
    }
    if ($length > $policy['max_length']) {
        $errors[] = "{$policy['max_length']}文字以下で入力してください";
    }

    // 大文字チェック
    if ($policy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        $errors[] = '大文字を1文字以上含めてください';
    }

    // 小文字チェック
    if ($policy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
        $errors[] = '小文字を1文字以上含めてください';
    }

    // 数字チェック
    if ($policy['require_number'] && !preg_match('/[0-9]/', $password)) {
        $errors[] = '数字を1文字以上含めてください';
    }

    // 特殊文字チェック
    if ($policy['require_special'] && !preg_match('/[!@#$%^&*(),.?":{}|<>\-_=+\[\]\\\\\/]/', $password)) {
        $errors[] = '特殊文字を1文字以上含めてください';
    }

    // よく使われるパスワードチェック
    if ($policy['disallow_common']) {
        $commonPasswords = getCommonPasswords();
        if (in_array(strtolower($password), array_map('strtolower', $commonPasswords))) {
            $errors[] = 'よく使われるパスワードは使用できません';
        }
    }

    // ユーザー名を含むかチェック
    if ($policy['disallow_username'] && !empty($username)) {
        if (stripos($password, $username) !== false) {
            $errors[] = 'パスワードにユーザー名を含めることはできません';
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * パスワード強度を計算（0-100）
 */
function calculatePasswordStrength($password) {
    $score = 0;
    $length = mb_strlen($password);

    // 長さによるスコア
    $score += min(30, $length * 3);

    // 文字種による加点
    if (preg_match('/[a-z]/', $password)) $score += 10;
    if (preg_match('/[A-Z]/', $password)) $score += 15;
    if (preg_match('/[0-9]/', $password)) $score += 15;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 20;

    // 連続文字の減点
    if (preg_match('/(.)\1{2,}/', $password)) $score -= 10;

    // 数字のみの減点
    if (preg_match('/^\d+$/', $password)) $score -= 20;

    return max(0, min(100, $score));
}

// ==================== IPホワイトリスト ====================

/**
 * IPホワイトリストを取得
 */
function getIpWhitelist() {
    $configFile = dirname(__DIR__) . '/config/security-config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return $config['ip_whitelist'] ?? [];
    }
    return [];
}

/**
 * IPアドレスがホワイトリストに含まれるかチェック
 */
function isIpWhitelisted($ip = null) {
    if ($ip === null) {
        // セキュリティ強化: getClientIp()を使用
        $ip = getClientIp();
    }

    $whitelist = getIpWhitelist();
    if (empty($whitelist)) {
        return true; // ホワイトリストが空の場合は全て許可
    }

    foreach ($whitelist as $allowed) {
        // CIDR記法のサポート
        if (strpos($allowed, '/') !== false) {
            if (ipInCidr($ip, $allowed)) {
                return true;
            }
        } elseif ($ip === $allowed) {
            return true;
        }
    }

    return false;
}

/**
 * IPアドレスがCIDR範囲内かチェック
 */
function ipInCidr($ip, $cidr) {
    list($subnet, $bits) = explode('/', $cidr);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask;
    return ($ip & $mask) === $subnet;
}

// ==================== ログイン保護 ====================

/**
 * ログイン試行回数をチェック
 */
function checkLoginAttempts($identifier, $maxAttempts = 5, $lockoutMinutes = 15) {
    $result = checkRateLimit('login:' . $identifier, $maxAttempts, $lockoutMinutes * 60);

    if (!$result['allowed']) {
        $waitMinutes = ceil(($result['reset_at'] - time()) / 60);
        return [
            'allowed' => false,
            'message' => "ログイン試行回数が上限に達しました。{$waitMinutes}分後に再試行してください。",
            'wait_minutes' => $waitMinutes
        ];
    }

    return [
        'allowed' => true,
        'remaining' => $result['remaining']
    ];
}

/**
 * ログイン試行をリセット（ログイン成功時）
 */
function resetLoginAttempts($identifier) {
    $rateLimitFile = dirname(__DIR__) . '/data/rate-limits.json';
    if (!file_exists($rateLimitFile)) {
        return;
    }

    $fp = fopen($rateLimitFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $content = stream_get_contents($fp);
        $limits = json_decode($content, true) ?: [];

        $key = 'login:' . $identifier;
        if (isset($limits[$key])) {
            unset($limits[$key]);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($limits, JSON_UNESCAPED_UNICODE));
            fflush($fp);
        }

        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
