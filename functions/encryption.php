<?php
/**
 * データ暗号化モジュール
 *
 * AES-256-GCM を使用して個人情報を暗号化・復号する。
 * 暗号化済みデータは「enc:」プレフィックスで識別し、
 * 平文データとの混在（マイグレーション中）にも対応する。
 *
 * 対象フィールド:
 *   - customers[]: phone, email, address
 *   - customers[].branches[]: phone, address
 *   - assignees[]: phone, email
 *   - partners[]: phone, email, address
 */

// 暗号化プレフィックス
define('ENCRYPTION_PREFIX', 'enc:');

// 暗号化アルゴリズム
define('ENCRYPTION_ALGO', 'aes-256-gcm');

// IV長（バイト）
define('ENCRYPTION_IV_LENGTH', 12);

// タグ長（バイト）
define('ENCRYPTION_TAG_LENGTH', 16);

/**
 * 暗号化鍵を取得する
 *
 * 優先順位:
 * 1. 環境変数 ENCRYPTION_KEY（Base64エンコード）
 * 2. config/encryption.key ファイル
 *
 * @return string 32バイトの暗号化鍵
 * @throws Exception 鍵が見つからない・無効な場合
 */
function getEncryptionKey() {
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    // 環境変数から取得
    $envKey = function_exists('env') ? env('ENCRYPTION_KEY', '') : ($_ENV['ENCRYPTION_KEY'] ?? '');
    if (!empty($envKey)) {
        $decoded = base64_decode($envKey, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            $key = $decoded;
            return $key;
        }
    }

    // ファイルから取得
    $keyFile = dirname(__DIR__) . '/config/encryption.key';
    if (file_exists($keyFile)) {
        $content = trim(file_get_contents($keyFile));
        $decoded = base64_decode($content, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            $key = $decoded;
            return $key;
        }
    }

    throw new Exception('暗号化鍵が設定されていません。config/encryption.key を作成するか、環境変数 ENCRYPTION_KEY を設定してください。');
}

/**
 * 新しい暗号化鍵を生成する
 *
 * @return string Base64エンコードされた鍵（ファイル保存用）
 */
function generateEncryptionKey() {
    $key = random_bytes(32);
    return base64_encode($key);
}

/**
 * 単一の値を暗号化する
 *
 * @param mixed $plaintext 暗号化する値
 * @return string 暗号化された値（enc:プレフィックス付き）、または元の値（空の場合）
 */
function encryptValue($plaintext) {
    // 空文字列・null・既に暗号化済みの場合はそのまま返す
    if ($plaintext === null || $plaintext === '') {
        return $plaintext;
    }
    if (is_string($plaintext) && str_starts_with($plaintext, ENCRYPTION_PREFIX)) {
        return $plaintext; // 二重暗号化防止
    }

    $key = getEncryptionKey();
    $iv = random_bytes(ENCRYPTION_IV_LENGTH);
    $tag = '';

    $ciphertext = openssl_encrypt(
        (string)$plaintext,
        ENCRYPTION_ALGO,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        ENCRYPTION_TAG_LENGTH
    );

    if ($ciphertext === false) {
        throw new Exception('暗号化に失敗しました: ' . openssl_error_string());
    }

    // iv(12) + tag(16) + ciphertext を結合してBase64エンコード
    return ENCRYPTION_PREFIX . base64_encode($iv . $tag . $ciphertext);
}

/**
 * 単一の値を復号する
 *
 * enc: プレフィックスがない場合は平文としてそのまま返す（後方互換）。
 *
 * @param mixed $ciphertext 復号する値
 * @return string 復号された値、または元の値（暗号化されていない場合）
 */
function decryptValue($ciphertext) {
    // null・空文字列・暗号化されていないデータはそのまま返す
    if ($ciphertext === null || $ciphertext === '') {
        return $ciphertext;
    }
    if (!is_string($ciphertext) || !str_starts_with($ciphertext, ENCRYPTION_PREFIX)) {
        return $ciphertext; // 平文データ（後方互換）
    }

    $key = getEncryptionKey();

    // プレフィックスを除去してBase64デコード
    $encoded = substr($ciphertext, strlen(ENCRYPTION_PREFIX));
    $decoded = base64_decode($encoded, true);

    if ($decoded === false) {
        // デコード失敗時はエラーログを記録し元の値を返す
        if (function_exists('logError')) {
            logError('暗号データのBase64デコードに失敗しました');
        }
        return $ciphertext;
    }

    $minLength = ENCRYPTION_IV_LENGTH + ENCRYPTION_TAG_LENGTH + 1;
    if (strlen($decoded) < $minLength) {
        if (function_exists('logError')) {
            logError('暗号データの長さが不正です');
        }
        return $ciphertext;
    }

    // iv(12) + tag(16) + ciphertext に分解
    $iv = substr($decoded, 0, ENCRYPTION_IV_LENGTH);
    $tag = substr($decoded, ENCRYPTION_IV_LENGTH, ENCRYPTION_TAG_LENGTH);
    $encrypted = substr($decoded, ENCRYPTION_IV_LENGTH + ENCRYPTION_TAG_LENGTH);

    $plaintext = openssl_decrypt(
        $encrypted,
        ENCRYPTION_ALGO,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plaintext === false) {
        // 復号失敗（鍵の不一致や改竄の可能性）
        if (function_exists('logError')) {
            logError('データの復号に失敗しました（鍵の不一致または改竄の可能性）');
        }
        return $ciphertext;
    }

    return $plaintext;
}

/**
 * レコード内の指定フィールドを暗号化する
 *
 * @param array $record レコード配列
 * @param array $fields 暗号化対象のフィールド名配列
 * @return array 暗号化後のレコード
 */
function encryptFields($record, $fields) {
    foreach ($fields as $field) {
        if (isset($record[$field]) && $record[$field] !== '') {
            $record[$field] = encryptValue($record[$field]);
        }
    }
    return $record;
}

/**
 * レコード内の指定フィールドを復号する
 *
 * @param array $record レコード配列
 * @param array $fields 復号対象のフィールド名配列
 * @return array 復号後のレコード
 */
function decryptFields($record, $fields) {
    foreach ($fields as $field) {
        if (isset($record[$field]) && $record[$field] !== '') {
            $record[$field] = decryptValue($record[$field]);
        }
    }
    return $record;
}

// ========================================
// エンティティ別の暗号化/復号対象フィールド定義
// ========================================

/** 顧客の暗号化対象フィールド */
define('CUSTOMER_ENCRYPT_FIELDS', ['phone', 'email', 'address']);

/** 営業所の暗号化対象フィールド */
define('BRANCH_ENCRYPT_FIELDS', ['phone', 'address']);

/** 担当者の暗号化対象フィールド */
define('ASSIGNEE_ENCRYPT_FIELDS', ['phone', 'email']);

/** パートナーの暗号化対象フィールド */
define('PARTNER_ENCRYPT_FIELDS', ['phone', 'email', 'address']);

/**
 * data配列内の全顧客関連データを暗号化する（保存前に使用）
 *
 * @param array &$data data.json全体の配列（参照渡し）
 */
function encryptCustomerData(&$data) {
    // 顧客
    if (isset($data['customers']) && is_array($data['customers'])) {
        foreach ($data['customers'] as &$customer) {
            $customer = encryptFields($customer, CUSTOMER_ENCRYPT_FIELDS);

            // 営業所
            if (isset($customer['branches']) && is_array($customer['branches'])) {
                foreach ($customer['branches'] as &$branch) {
                    $branch = encryptFields($branch, BRANCH_ENCRYPT_FIELDS);
                }
                unset($branch);
            }
        }
        unset($customer);
    }

    // 担当者
    if (isset($data['assignees']) && is_array($data['assignees'])) {
        foreach ($data['assignees'] as &$assignee) {
            $assignee = encryptFields($assignee, ASSIGNEE_ENCRYPT_FIELDS);
        }
        unset($assignee);
    }

    // パートナー
    if (isset($data['partners']) && is_array($data['partners'])) {
        foreach ($data['partners'] as &$partner) {
            $partner = encryptFields($partner, PARTNER_ENCRYPT_FIELDS);
        }
        unset($partner);
    }
}

/**
 * data配列内の全顧客関連データを復号する（getData後に使用）
 *
 * @param array &$data data.json全体の配列（参照渡し）
 */
function decryptCustomerData(&$data) {
    // 顧客
    if (isset($data['customers']) && is_array($data['customers'])) {
        foreach ($data['customers'] as &$customer) {
            $customer = decryptFields($customer, CUSTOMER_ENCRYPT_FIELDS);

            // 営業所
            if (isset($customer['branches']) && is_array($customer['branches'])) {
                foreach ($customer['branches'] as &$branch) {
                    $branch = decryptFields($branch, BRANCH_ENCRYPT_FIELDS);
                }
                unset($branch);
            }
        }
        unset($customer);
    }

    // 担当者
    if (isset($data['assignees']) && is_array($data['assignees'])) {
        foreach ($data['assignees'] as &$assignee) {
            $assignee = decryptFields($assignee, ASSIGNEE_ENCRYPT_FIELDS);
        }
        unset($assignee);
    }

    // パートナー
    if (isset($data['partners']) && is_array($data['partners'])) {
        foreach ($data['partners'] as &$partner) {
            $partner = decryptFields($partner, PARTNER_ENCRYPT_FIELDS);
        }
        unset($partner);
    }
}

/**
 * 電話番号をマスク表示する
 *
 * @param string $phone 電話番号
 * @return string マスクされた電話番号（例: 03-****-5678）
 */
function maskPhone($phone) {
    if (empty($phone)) return '';

    // ハイフン付き電話番号の場合
    if (preg_match('/^(\d{2,4})-(\d{2,4})-(\d{4})$/', $phone, $matches)) {
        return $matches[1] . '-' . str_repeat('*', strlen($matches[2])) . '-' . $matches[3];
    }

    // ハイフンなしの場合
    $len = mb_strlen($phone);
    if ($len <= 4) return $phone;
    return substr($phone, 0, 2) . str_repeat('*', $len - 4) . substr($phone, -2);
}

/**
 * メールアドレスをマスク表示する
 *
 * @param string $email メールアドレス
 * @return string マスクされたメールアドレス（例: u**r@example.com）
 */
function maskEmail($email) {
    if (empty($email)) return '';

    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;

    $local = $parts[0];
    $domain = $parts[1];

    if (strlen($local) <= 2) {
        return $local[0] . '*@' . $domain;
    }

    return $local[0] . str_repeat('*', strlen($local) - 2) . substr($local, -1) . '@' . $domain;
}
