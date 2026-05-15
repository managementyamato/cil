<?php
/**
 * 営業ツール用マスター（暫定）
 *
 * 商品マスター / 顧客マスターのデモデータを返す。
 * 本来は DB マスターから取得すべきだが、価格表機能（M3予定）が
 * 実装されるまでの暫定として PHP / JS 両方から参照できる単一ソースに
 * 集約している。
 *
 * 将来:
 *  - getDemoProductMaster() → products テーブル + ランク別単価マスタへ
 *  - getDemoCustomerMaster() → customers テーブル + ランクフィールドへ
 */

/**
 * 商品マスター（暫定デモ）
 * @return array<int, array{id:string,name:string,price:int,category:string}>
 */
function getDemoProductMaster(): array {
    return [
        ['id' => 'monitarou', 'name' => 'モニたろう (LEDビジョン)',           'price' => 250000, 'category' => 'LEDビジョン'],
        ['id' => 'monisuke',  'name' => 'モニすけ (屋外用液晶ディスプレイ)', 'price' => 180000, 'category' => 'ディスプレイ'],
        ['id' => 'monimaru',  'name' => 'モニまる (電子黒板)',                'price' => 320000, 'category' => '電子黒板'],
    ];
}

/**
 * 顧客マスター（暫定デモ）
 * @return array<int, array{name:string,rank:string,am:string}>
 */
function getDemoCustomerMaster(): array {
    return [
        ['name' => 'ヤマト商事(株)', 'rank' => 'S', 'am' => '佐藤 太郎'],
        ['name' => 'ヤマトロジ(株)', 'rank' => 'A', 'am' => '佐藤 太郎'],
        ['name' => 'ヤマト食品',     'rank' => 'B', 'am' => '鈴木 花子'],
        ['name' => 'ヤマト工業',     'rank' => 'C', 'am' => '高橋 次郎'],
        ['name' => 'ニッケン(株)',   'rank' => 'A', 'am' => '西井'],
    ];
}

/**
 * ランク別単価補正（暫定）
 * 価格表機能が確定するまでのプレースホルダ。
 * @return array<string, float>
 */
function getDemoRankMultipliers(): array {
    return [
        'S' => 1.00,
        'A' => 0.97,
        'B' => 0.94,
        'C' => 0.90,
        'D' => 0.85,
    ];
}

/**
 * 同期済みの価格表（data/product-prices.json）を読み込んで返す。
 * 未同期の場合は null。
 *
 * @return array|null
 */
function loadProductPrices(): ?array {
    $path = __DIR__ . '/../data/product-prices.json';
    if (!file_exists($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * AIプロンプト用の価格表テキスト表現を返す。
 * シートデータを「### シート名」+ パイプ区切り行 でまとめる。
 * 同期されていない場合は null。
 *
 * @param int $maxRowsPerSheet シートごとの最大行数（プロンプト肥大化防止）
 * @return string|null
 */
function getPriceListAsPromptText(int $maxRowsPerSheet = 80): ?string {
    $data = loadProductPrices();
    if (!$data) return null;
    $out = [];
    $out[] = '同期日時: ' . ($data['synced_at'] ?? '?');
    foreach (($data['sheets'] ?? []) as $sheet) {
        $title = $sheet['title'] ?? '';
        $values = $sheet['values'] ?? [];
        if (!is_array($values) || empty($values)) continue;

        $out[] = '';
        $out[] = '### ' . $title;
        $rows = array_slice($values, 0, $maxRowsPerSheet);
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            // 空セルでも区切りを残すが、長すぎる行は省略
            $cells = array_map(function($c){
                $s = (string)$c;
                return mb_strlen($s) > 60 ? mb_substr($s, 0, 60) . '…' : $s;
            }, $row);
            $out[] = '| ' . implode(' | ', $cells) . ' |';
        }
        if (count($values) > $maxRowsPerSheet) {
            $out[] = '… (以下省略 / 残 ' . (count($values) - $maxRowsPerSheet) . ' 行)';
        }
    }
    return implode("\n", $out);
}
