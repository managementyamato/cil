# コードレビューチェックリスト

> このファイルは CLAUDE.md の補足ドキュメントです。
> PRを出す前・コードレビュー時に必ず確認してください。

---

## セキュリティ

- [ ] POSTを受けるページで `verifyCsrfToken()` を呼んでいる
- [ ] HTMLに出力する変数は `htmlspecialchars()` でエスケープ
- [ ] JavaScriptの `innerHTML` 使用時は `escapeHtml()` でエスケープ
- [ ] `iconButton()` 関数で onclick 属性を使っていない（data属性+イベントリスナーを使用）
- [ ] 削除処理は `canDelete()` でチェック
- [ ] 削除処理は物理削除ではなく論理削除（ソフトデリート）を使用
- [ ] 新規ページは `$defaultPagePermissions` に追加済み

## データ整合性

- [ ] data.json は `getData()` / `saveData()` 経由でのみアクセス
- [ ] 重要な操作は監査ログ (`auditCreate`, `auditUpdate`, `auditDelete`) を記録

## 保守性

- [ ] マジックナンバーを使っていない（定数化推奨）
- [ ] エラーメッセージは日本語で分かりやすく
- [ ] 複雑なロジックにはコメントを追加
- [ ] テストを実行して全て通過していることを確認
- [ ] 変更履歴を `docs/changelog.md` に記録した
