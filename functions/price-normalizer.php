<?php
/**
 * 価格表シートの正規化
 *
 * Google スプレッドシートから取得した 2D 配列を、
 * 統一スキーマ（rank-pricing rows）に変換する。
 *
 * 統一スキーマ:
 * [
 *   'type'       => 'rank-pricing' | 'flat-list' | 'unknown',
 *   'rank_order' => ['S','A','B',...],   // rank-pricing の場合のみ
 *   'rows'       => [
 *     [
 *       'key'          => 'UTM-P4_81',          // 行内ユニーク（同シート内）
 *       'display_name' => 'UTM-P4 (81インチ)',  // カード見出し
 *       'attributes' => [
 *         ['label'=>'型式','value'=>'UTM-P4'],
 *         ['label'=>'インチ数','value'=>'81'],
 *       ],
 *       'prices' => [
 *         ['group'=>'S','label'=>'①月額','amount'=>74600],
 *         ['group'=>'A','label'=>'①月額','amount'=>89500],
 *       ],
 *     ],
 *   ],
 * ]
 */

/**
 * シート 1 つを正規化。失敗時は null を返す（呼び出し側は raw 表示へフォールバック）
 */
function normalizePriceSheet(array $values): ?array {
    if (!$values) return null;

    // 末尾空行・全空セル行を除去
    $rows = [];
    foreach ($values as $r) {
        if (!is_array($r)) continue;
        $nonEmpty = false;
        foreach ($r as $c) {
            if (trim((string)($c ?? '')) !== '') { $nonEmpty = true; break; }
        }
        if ($nonEmpty) $rows[] = $r;
    }
    if (count($rows) < 2) return null;

    // 全行の最大列数に揃える
    $maxCols = 0;
    foreach ($rows as $r) { if (count($r) > $maxCols) $maxCols = count($r); }
    $rows = array_map(fn($r) => array_pad($r, $maxCols, ''), $rows);

    // ヘッダ検出: 「S層」「A層」「B層」「C層」「D層」を含む行
    $rankPattern = '/^([SABCD])層/u';

    // 1行目に層ラベルがあるか
    $r0HasRank = _np_hasRank($rows[0], $rankPattern);
    $r1HasRank = isset($rows[1]) ? _np_hasRank($rows[1], $rankPattern) : false;

    // 2行ヘッダ判定: 1行目に層ラベル + 2行目にも文字列ヘッダ
    $isTwoRowHeader = false;
    if ($r0HasRank && !$r1HasRank && isset($rows[1])) {
        // 1行目の層列が空セルで挟まれており、2行目に実際の列名が並ぶ
        $r1NonEmptyCount = 0;
        $r1NumericCount = 0;
        foreach ($rows[1] as $c) {
            $s = trim((string)($c ?? ''));
            if ($s === '') continue;
            $r1NonEmptyCount++;
            if (preg_match('/^[¥￥]?[\d,，.]+$/u', $s)) $r1NumericCount++;
        }
        // 2行目がほぼ文字列なら 2行ヘッダ
        if ($r1NonEmptyCount > 0 && $r1NumericCount < $r1NonEmptyCount / 2) {
            $isTwoRowHeader = true;
        }
    }

    if (!$r0HasRank && !$isTwoRowHeader) {
        // 層なし → 平坦リストとして扱う（attributes のみ）
        return _np_normalizeFlat($rows);
    }

    if ($isTwoRowHeader) {
        return _np_normalizeTwoRowHeader($rows, $rankPattern);
    }

    return _np_normalizeOneRowHeader($rows, $rankPattern);
}

function _np_hasRank(array $row, string $pattern): bool {
    foreach ($row as $c) {
        $s = trim((string)($c ?? ''));
        if ($s !== '' && preg_match($pattern, $s)) return true;
    }
    return false;
}

/**
 * 1行ヘッダ: 列名に "S層\n①月額" のような形で層と価格種が同居
 *   例: モニたろうUTM・FA・RCM
 */
function _np_normalizeOneRowHeader(array $rows, string $rankPattern): array {
    $header = $rows[0];
    $dataRows = array_slice($rows, 1);

    // 列タイプ判定: 各列が attribute / price どちらか
    // 列ヘッダに「S層/A層/B層/...」を含めば price 列、それ以外は attribute 列
    $colInfo = [];
    foreach ($header as $i => $h) {
        $s = trim((string)($h ?? ''));
        if ($s === '') { $colInfo[$i] = ['type' => 'skip']; continue; }
        if (preg_match($rankPattern, $s, $m)) {
            $rank = $m[1];
            $label = trim(preg_replace('/^[SABCD]層[\s\r\n]*/u', '', $s));
            if ($label === '') $label = '価格';
            $colInfo[$i] = ['type' => 'price', 'group' => $rank, 'label' => $label];
        } else {
            $colInfo[$i] = ['type' => 'attr', 'label' => $s];
        }
    }

    $rankOrder = _np_collectRankOrder($colInfo);
    return _np_buildRows($dataRows, $colInfo, $rankOrder);
}

/**
 * 2行ヘッダ: 1行目=層ラベル（空セル混じり）、2行目=列名（型番/サイズ/販売/月額…）
 *   例: モニすけOBLITE
 */
function _np_normalizeTwoRowHeader(array $rows, string $rankPattern): array {
    $r0 = $rows[0]; // 層ヘッダ
    $r1 = $rows[1]; // 列名ヘッダ
    $dataRows = array_slice($rows, 2);
    $maxCols = max(count($r0), count($r1));

    // 列ごとの "現在の層" を決定: r0 でラベルが現れたらそこから後続の列に伝播
    // 2行ヘッダ時は「層配下の全列」を価格列として扱う（"6m未満" 等の期間ラベルも価格）
    $currentRank = null;
    $colInfo = [];
    for ($i = 0; $i < $maxCols; $i++) {
        $rankCell = trim((string)($r0[$i] ?? ''));
        if ($rankCell !== '' && preg_match($rankPattern, $rankCell, $m)) {
            $currentRank = $m[1];
        } elseif ($rankCell !== '' && !empty($currentRank)) {
            // r0 に層ではない他の値（"基本情報" 等）が現れたら層スコープを終了
            // ただし空セルは伝播継続
            $currentRank = null;
        }
        $name = trim((string)($r1[$i] ?? ''));
        if ($name === '') { $colInfo[$i] = ['type' => 'skip']; continue; }
        if ($currentRank !== null) {
            // 層スコープ内: 期間ラベル等も含めすべて価格列扱い
            $colInfo[$i] = ['type' => 'price', 'group' => $currentRank, 'label' => $name];
        } else {
            $colInfo[$i] = ['type' => 'attr', 'label' => $name];
        }
    }

    $rankOrder = _np_collectRankOrder($colInfo);
    return _np_buildRows($dataRows, $colInfo, $rankOrder);
}

/**
 * 層なしの平坦リスト
 *   例: 屋外用プロジェクター, 中古在庫価格表
 */
function _np_normalizeFlat(array $rows): array {
    // 1行目をヘッダとする。先頭行が「タイトル + 計算レート」みたいな単独セル行なら 2 行目をヘッダに
    $headerIdx = 0;
    $first = $rows[0];
    $nonEmpty0 = array_filter($first, fn($c) => trim((string)($c ?? '')) !== '');
    if (count($nonEmpty0) === 1 && isset($rows[1])) {
        $second = $rows[1];
        $nonEmpty1 = array_filter($second, fn($c) => trim((string)($c ?? '')) !== '');
        if (count($nonEmpty1) >= 2) $headerIdx = 1;
    }

    $header = $rows[$headerIdx];
    $dataRows = array_slice($rows, $headerIdx + 1);

    $colInfo = [];
    foreach ($header as $i => $h) {
        $s = trim((string)($h ?? ''));
        if ($s === '') { $colInfo[$i] = ['type' => 'skip']; continue; }
        if (_np_looksLikePrice($s)) {
            $colInfo[$i] = ['type' => 'price', 'group' => '', 'label' => $s];
        } else {
            $colInfo[$i] = ['type' => 'attr', 'label' => $s];
        }
    }

    $result = _np_buildRows($dataRows, $colInfo, []);
    $result['type'] = 'flat-list';
    return $result;
}

function _np_looksLikePrice(string $label): bool {
    // 「価格 / 月額 / 仕入 / 売価 / 定価」等を価格列とみなす
    return (bool)preg_match('/(価格|月額|単価|料金|定価|販売|仕入|原価|売価|請求|送料|キャンセル)/u', $label);
}

function _np_collectRankOrder(array $colInfo): array {
    $seen = [];
    foreach ($colInfo as $info) {
        if (($info['type'] ?? '') === 'price' && !empty($info['group'])) {
            if (!in_array($info['group'], $seen, true)) $seen[] = $info['group'];
        }
    }
    // 一般的な順番に正規化
    $canonical = ['S','A','B','C','D'];
    $ordered = array_values(array_intersect($canonical, $seen));
    // 想定外の rank があれば末尾に追加
    foreach ($seen as $r) { if (!in_array($r, $ordered, true)) $ordered[] = $r; }
    return $ordered;
}

/** データ行から正規化された rows[] を構築 */
function _np_buildRows(array $dataRows, array $colInfo, array $rankOrder): array {
    $out = [];
    foreach ($dataRows as $rIdx => $row) {
        if (!is_array($row)) continue;
        $attrs  = [];
        $prices = [];
        foreach ($colInfo as $i => $info) {
            if (($info['type'] ?? '') === 'skip') continue;
            $rawVal = trim((string)($row[$i] ?? ''));
            if ($rawVal === '' || $rawVal === '#REF!' || $rawVal === '#N/A' || $rawVal === '#VALUE!') continue;
            if ($info['type'] === 'price') {
                $amount = _np_parsePrice($rawVal);
                if ($amount !== null) {
                    $prices[] = [
                        'group'  => $info['group'] ?? '',
                        'label'  => $info['label'],
                        'amount' => $amount,
                    ];
                } else {
                    // 数値化できないなら attribute 扱い
                    $attrs[] = ['label' => $info['label'], 'value' => $rawVal];
                }
            } else {
                $attrs[] = ['label' => $info['label'], 'value' => $rawVal];
            }
        }
        if (count($attrs) === 0 && count($prices) === 0) continue;

        // 表示名: 最初の attribute の value、なければ「行 N」
        $displayName = $attrs[0]['value'] ?? ('行 ' . ($rIdx + 1));
        // サブ識別子としていくつかのキー attribute を結合
        $extraIdent = [];
        foreach ($attrs as $a) {
            if (preg_match('/(インチ|サイズ|型式|型番)/u', $a['label']) && $a['value'] !== $displayName) {
                $extraIdent[] = $a['value'];
                if (count($extraIdent) >= 2) break;
            }
        }
        if ($extraIdent) $displayName .= ' (' . implode(' / ', $extraIdent) . ')';

        // ユニークキー（同シート内で行を識別）
        $keyBase = preg_replace('/\s+/u', '_', $displayName);
        $key = $keyBase . '_' . $rIdx;

        $out[] = [
            'key'          => $key,
            'display_name' => $displayName,
            'attributes'   => $attrs,
            'prices'       => $prices,
        ];
    }

    return [
        'type'       => $rankOrder ? 'rank-pricing' : 'flat-list',
        'rank_order' => $rankOrder,
        'rows'       => $out,
    ];
}

/** 価格文字列 → 整数。失敗時は null */
function _np_parsePrice(string $s): ?int {
    // ¥ や , を除去して数値化
    $clean = preg_replace('/[¥￥,，円\s]/u', '', $s);
    if ($clean === '' || $clean === '-') return null;
    if (!preg_match('/^[\d.]+$/', $clean)) return null;
    $n = (int)floatval($clean);
    return $n > 0 ? $n : null;
}
