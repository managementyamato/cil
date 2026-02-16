<?php
/**
 * 入力バリデーションライブラリ
 *
 * 日付、メール、電話番号などの検証関数を提供
 * XSS対策のサニタイズ関数も含む
 */

// ==================== 個別バリデーション関数 ====================

/**
 * メールアドレスの形式を検証
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    if (empty($email)) {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 電話番号の形式を検証（日本の電話番号）
 * @param string $phone
 * @return bool
 */
function validatePhone($phone) {
    if (empty($phone)) {
        return false;
    }
    // ハイフンを除去して数字のみにする
    $digits = preg_replace('/[^\d]/', '', $phone);
    // 10桁または11桁の電話番号
    return strlen($digits) >= 10 && strlen($digits) <= 11;
}

/**
 * 日付の形式と妥当性を検証（YYYY-MM-DD形式）
 * @param string $date
 * @param string $format
 * @return bool
 */
function validateDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return false;
    }
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * 必須項目のチェック
 * @param mixed $value
 * @return bool
 */
function validateRequired($value) {
    if ($value === null) {
        return false;
    }
    if (is_string($value)) {
        return trim($value) !== '';
    }
    return true;
}

/**
 * 数値かどうかを検証
 * @param mixed $value
 * @return bool
 */
function validateNumeric($value) {
    if ($value === '' || $value === null) {
        return false;
    }
    return is_numeric($value);
}

/**
 * 整数かどうかを検証
 * @param mixed $value
 * @return bool
 */
function validateInteger($value) {
    if ($value === '' || $value === null) {
        return false;
    }
    if (is_int($value)) {
        return true;
    }
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

/**
 * 数値の範囲を検証
 * @param mixed $value
 * @param int|float $min
 * @param int|float $max
 * @return bool
 */
function validateRange($value, $min, $max) {
    if (!is_numeric($value)) {
        return false;
    }
    return $value >= $min && $value <= $max;
}

/**
 * 文字列の長さを検証
 * @param string $value
 * @param int $min
 * @param int $max
 * @return bool
 */
function validateLength($value, $min, $max) {
    $length = mb_strlen($value);
    return $length >= $min && $length <= $max;
}

/**
 * URLの形式を検証（http/httpsのみ）
 * @param string $url
 * @return bool
 */
function validateUrl($url) {
    if (empty($url)) {
        return false;
    }
    // http/httpsスキームのみ許可
    if (!preg_match('/^https?:\/\//i', $url)) {
        return false;
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * 郵便番号の形式を検証（日本）
 * @param string $postalCode
 * @return bool
 */
function validatePostalCode($postalCode) {
    if (empty($postalCode)) {
        return false;
    }
    // ハイフンありなし両対応
    return preg_match('/^\d{3}-?\d{4}$/', $postalCode) === 1;
}

/**
 * パスワードポリシーの検証
 * @param string $password
 * @param array $options ['min_length' => 8, 'require_uppercase' => true, etc.]
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePassword($password, $options = []) {
    $defaults = [
        'min_length' => 8,
        'max_length' => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_special' => false,
    ];
    $options = array_merge($defaults, $options);
    $errors = [];

    if (mb_strlen($password) < $options['min_length']) {
        $errors[] = "{$options['min_length']}文字以上で入力してください";
    }

    if (mb_strlen($password) > $options['max_length']) {
        $errors[] = "{$options['max_length']}文字以下で入力してください";
    }

    if ($options['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        $errors[] = '大文字を1文字以上含めてください';
    }

    if ($options['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
        $errors[] = '小文字を1文字以上含めてください';
    }

    if ($options['require_number'] && !preg_match('/[0-9]/', $password)) {
        $errors[] = '数字を1文字以上含めてください';
    }

    if ($options['require_special'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = '特殊文字を1文字以上含めてください';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// ==================== サニタイズ関数 ====================

/**
 * HTML特殊文字をエスケープ（XSS対策）
 * @param string $value
 * @return string
 */
function sanitizeHtml($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * SQL文字列をエスケープ（PDOを使用している場合は不要）
 * @param string $value
 * @return string
 * @deprecated PDOのプリペアドステートメントを使用してください
 */
function sanitizeSql($value) {
    return addslashes($value);
}

/**
 * ファイル名をサニタイズ
 * @param string $filename
 * @return string
 */
function sanitizeFilename($filename) {
    // 危険な文字を除去
    $filename = preg_replace('/[\/\\\:\*\?\"\<\>\|]/', '', $filename);
    // 先頭のドットを除去（隠しファイル防止）
    $filename = ltrim($filename, '.');
    // 長すぎる場合はカット
    if (mb_strlen($filename) > 255) {
        $filename = mb_substr($filename, 0, 255);
    }
    return $filename;
}

// ==================== Validatorクラス ====================

/**
 * バリデーションエラーを収集・管理するクラス
 */
class Validator {
    private array $errors = [];

    /**
     * 必須チェック
     */
    public function required(string $field, $value, string $label): self {
        if (!validateRequired($value)) {
            $this->errors[$field] = "{$label}は必須です";
        }
        return $this;
    }

    /**
     * メールアドレスチェック
     */
    public function email(string $field, $value, string $label): self {
        if (!empty($value) && !validateEmail($value)) {
            $this->errors[$field] = "{$label}の形式が正しくありません";
        }
        return $this;
    }

    /**
     * 電話番号チェック
     */
    public function phone(string $field, $value, string $label): self {
        if (!empty($value) && !validatePhone($value)) {
            $this->errors[$field] = "{$label}の形式が正しくありません";
        }
        return $this;
    }

    /**
     * 日付チェック
     */
    public function date(string $field, $value, string $label, string $format = 'Y-m-d'): self {
        if (!empty($value) && !validateDate($value, $format)) {
            $this->errors[$field] = "{$label}の日付形式が正しくありません";
        }
        return $this;
    }

    /**
     * 数値チェック
     */
    public function numeric(string $field, $value, string $label): self {
        if (!empty($value) && !validateNumeric($value)) {
            $this->errors[$field] = "{$label}は数値で入力してください";
        }
        return $this;
    }

    /**
     * 整数チェック
     */
    public function integer(string $field, $value, string $label): self {
        if (!empty($value) && !validateInteger($value)) {
            $this->errors[$field] = "{$label}は整数で入力してください";
        }
        return $this;
    }

    /**
     * 範囲チェック
     */
    public function range(string $field, $value, string $label, $min, $max): self {
        if (!empty($value) && !validateRange($value, $min, $max)) {
            $this->errors[$field] = "{$label}は{$min}から{$max}の範囲で入力してください";
        }
        return $this;
    }

    /**
     * 文字数チェック
     */
    public function length(string $field, $value, string $label, int $min, int $max): self {
        if (!empty($value) && !validateLength($value, $min, $max)) {
            $this->errors[$field] = "{$label}は{$min}文字以上{$max}文字以下で入力してください";
        }
        return $this;
    }

    /**
     * URLチェック
     */
    public function url(string $field, $value, string $label): self {
        if (!empty($value) && !validateUrl($value)) {
            $this->errors[$field] = "{$label}の形式が正しくありません";
        }
        return $this;
    }

    /**
     * 郵便番号チェック
     */
    public function postalCode(string $field, $value, string $label): self {
        if (!empty($value) && !validatePostalCode($value)) {
            $this->errors[$field] = "{$label}の形式が正しくありません（例: 123-4567）";
        }
        return $this;
    }

    /**
     * パスワードポリシーチェック
     */
    public function password(string $field, $value, string $label, array $options = []): self {
        if (!empty($value)) {
            $result = validatePassword($value, $options);
            if (!$result['valid']) {
                $this->errors[$field] = "{$label}: " . implode(', ', $result['errors']);
            }
        }
        return $this;
    }

    /**
     * カスタムバリデーション
     */
    public function custom(string $field, bool $condition, string $message): self {
        if (!$condition) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * エラーがあるかチェック
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    /**
     * エラーメッセージを取得
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * 特定フィールドのエラーを取得
     */
    public function getError(string $field): ?string {
        return $this->errors[$field] ?? null;
    }

    /**
     * エラーをクリア
     */
    public function clear(): self {
        $this->errors = [];
        return $this;
    }

    /**
     * エラーをJSON形式で取得
     */
    public function toJson(): string {
        return json_encode($this->errors, JSON_UNESCAPED_UNICODE);
    }
}

// ==================== API レスポンス用ヘルパー ====================

/**
 * バリデーションエラーをAPIレスポンスとして返す
 * @param Validator $validator
 */
function respondValidationError(Validator $validator): void {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Validation failed',
        'errors' => $validator->getErrors()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
