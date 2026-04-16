<?php
/**
 * 日付フォーマット統一ヘルパー
 *
 * 既存コードは date('Y-m-d H:i:s'), date('Y/m/d') などが混在している。
 * 新規実装では以下のヘルパーを使って出力フォーマットを統一すること。
 *
 * 保存フォーマット:  Y-m-d H:i:s（ISO準拠、ソート可能）
 * 表示フォーマット:  Y/m/d  および  Y/m/d H:i（日本式、UIで読みやすい）
 */

if (!function_exists('formatDate')) {
    /**
     * 日付のみを表示用にフォーマット（Y/m/d）
     *
     * @param mixed $value DateTime | 文字列 | UNIXタイムスタンプ | null
     * @param string $fallback null/不正値のときの表示（デフォルト: 空文字）
     * @return string
     */
    function formatDate($value, string $fallback = ''): string {
        $ts = normalizeDateInput($value);
        return $ts === null ? $fallback : date('Y/m/d', $ts);
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * 日時を表示用にフォーマット（Y/m/d H:i）
     *
     * @param mixed $value DateTime | 文字列 | UNIXタイムスタンプ | null
     * @param string $fallback null/不正値のときの表示
     * @return string
     */
    function formatDateTime($value, string $fallback = ''): string {
        $ts = normalizeDateInput($value);
        return $ts === null ? $fallback : date('Y/m/d H:i', $ts);
    }
}

if (!function_exists('formatDateIso')) {
    /**
     * 保存用のISOフォーマット（Y-m-d H:i:s）
     * data.json / DB 保存時はこれを使う
     */
    function formatDateIso($value = null): string {
        $ts = $value === null ? time() : normalizeDateInput($value);
        return $ts === null ? '' : date('Y-m-d H:i:s', $ts);
    }
}

if (!function_exists('formatDateRelative')) {
    /**
     * 相対時刻表示（例: 3分前, 2時間前, 昨日, 3日前）
     * それ以前は Y/m/d を返す
     */
    function formatDateRelative($value): string {
        $ts = normalizeDateInput($value);
        if ($ts === null) return '';

        $diff = time() - $ts;
        if ($diff < 60)       return 'たった今';
        if ($diff < 3600)     return floor($diff / 60) . '分前';
        if ($diff < 86400)    return floor($diff / 3600) . '時間前';
        if ($diff < 172800)   return '昨日';
        if ($diff < 604800)   return floor($diff / 86400) . '日前';
        return date('Y/m/d', $ts);
    }
}

if (!function_exists('normalizeDateInput')) {
    /**
     * 様々な日付入力をUNIXタイムスタンプに正規化（内部用）
     *
     * @param mixed $value
     * @return int|null 正規化できない場合は null
     */
    function normalizeDateInput($value): ?int {
        if ($value === null || $value === '') return null;
        if ($value instanceof DateTimeInterface) return $value->getTimestamp();
        if (is_int($value)) return $value;
        if (is_string($value)) {
            $ts = strtotime($value);
            return $ts === false ? null : $ts;
        }
        return null;
    }
}
