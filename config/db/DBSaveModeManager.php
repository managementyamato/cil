<?php
/**
 * DBSaveModeManager
 *
 * `DB_SAVE_MODE` (full_replace / upsert) の読み取りを集約する。
 *
 * ⚠️ 重要 (CLAUDE.md 最重要 #0):
 *   - デフォルト値は **絶対に "full_replace" のまま** にすること
 *   - "upsert" に変更すると過去 2 回 (2026-05-11, 2026-05-12) と同じ regression を起こす
 *   - 本クラスは「読み取り」しか提供しない。書き込み・自動切替・推測は禁止。
 *
 * 公開 API:
 *   - getMode(): string             // 現在の保存モード ("full_replace" or "upsert")
 *   - isUpsertMode(): bool          // upsert モードか
 *   - DEFAULT_MODE: string          // デフォルト値 (定数)
 *
 * 抽出元: config/database.php saveEntityInternal() の中の env() 呼び出し (Sprint 1, 2026-05-18)
 */

class DBSaveModeManager
{
    /**
     * デフォルト保存モード（安全側）
     *
     * 変更厳禁: 本値を 'upsert' に変えると CLAUDE.md 最重要 #0 違反。
     * 過去 regression:
     *   - 2026-05-11: UPSERT 化 → employees テーブル破損 → 全員ログイン不可
     *   - 2026-05-12: UPSERT 再投入 → weekly_reports 保存失敗 → 権限消失
     */
    public const DEFAULT_MODE = 'full_replace';

    /**
     * 現在の DB_SAVE_MODE を取得
     *
     * 環境変数 DB_SAVE_MODE が未設定の場合は DEFAULT_MODE ('full_replace') を返す。
     * config/database.php の saveEntityInternal() で使われていた
     *   env('DB_SAVE_MODE', 'full_replace')
     * と完全に等価。
     */
    public static function getMode(): string
    {
        return env('DB_SAVE_MODE', self::DEFAULT_MODE);
    }

    public static function isUpsertMode(): bool
    {
        return self::getMode() === 'upsert';
    }
}
