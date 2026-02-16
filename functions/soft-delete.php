<?php
/**
 * 論理削除（ソフトデリート）共通関数
 *
 * 物理削除の代わりに deleted_at フィールドを付与して非表示にする。
 * 管理者がゴミ箱ページから復元可能。90日経過で自動物理削除。
 */

/**
 * アイテムを論理削除する
 *
 * @param array &$items エンティティ配列（参照渡し）
 * @param string $id 削除対象のID
 * @param string $idField IDフィールド名（デフォルト: 'id'）
 * @return array|null 削除されたアイテム（見つからない場合はnull）
 */
function softDelete(&$items, $id, $idField = 'id') {
    $deletedItem = null;
    foreach ($items as &$item) {
        if (isset($item[$idField]) && $item[$idField] === $id) {
            $item['deleted_at'] = date('Y-m-d H:i:s');
            $item['deleted_by'] = $_SESSION['user_email'] ?? 'system';
            $deletedItem = $item;
            break;
        }
    }
    unset($item);
    return $deletedItem;
}

/**
 * 削除済みアイテムを復元する
 *
 * @param array &$items エンティティ配列（参照渡し）
 * @param string $id 復元対象のID
 * @param string $idField IDフィールド名（デフォルト: 'id'）
 * @return array|null 復元されたアイテム（見つからない場合はnull）
 */
function restoreItem(&$items, $id, $idField = 'id') {
    $restoredItem = null;
    foreach ($items as &$item) {
        if (isset($item[$idField]) && $item[$idField] === $id && !empty($item['deleted_at'])) {
            unset($item['deleted_at']);
            unset($item['deleted_by']);
            $item['updated_at'] = date('Y-m-d H:i:s');
            $restoredItem = $item;
            break;
        }
    }
    unset($item);
    return $restoredItem;
}

/**
 * 削除済みアイテムを除外して返す（表示用）
 *
 * @param array $items エンティティ配列
 * @return array 削除済みを除外した配列
 */
function filterDeleted($items) {
    if (!is_array($items)) {
        return $items;
    }
    return array_values(array_filter($items, function($item) {
        return empty($item['deleted_at']);
    }));
}

/**
 * 削除済みアイテムのみ取得（ゴミ箱表示用）
 *
 * @param array $items エンティティ配列
 * @return array 削除済みのみの配列
 */
function getDeletedItems($items) {
    if (!is_array($items)) {
        return [];
    }
    return array_values(array_filter($items, function($item) {
        return !empty($item['deleted_at']);
    }));
}

/**
 * 指定日数経過した削除済みアイテムを物理削除する
 *
 * @param array &$items エンティティ配列（参照渡し）
 * @param int $daysOld 経過日数（デフォルト: 90日）
 * @return int 物理削除された件数
 */
function purgeDeleted(&$items, $daysOld = 90) {
    if (!is_array($items)) {
        return 0;
    }

    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
    $originalCount = count($items);

    $items = array_values(array_filter($items, function($item) use ($cutoffDate) {
        // 削除済みでない → 残す
        if (empty($item['deleted_at'])) {
            return true;
        }
        // 削除日がカットオフより新しい → 残す
        return $item['deleted_at'] > $cutoffDate;
    }));

    return $originalCount - count($items);
}

/**
 * 全エンティティの古い削除済みアイテムを物理削除する
 *
 * @param array &$data データ全体（参照渡し）
 * @param int $daysOld 経過日数（デフォルト: 90日）
 * @return array エンティティごとの削除件数
 */
function purgeAllDeleted(&$data, $daysOld = 90) {
    $entities = ['projects', 'customers', 'employees', 'troubles', 'partners'];
    $result = [];

    foreach ($entities as $entity) {
        if (isset($data[$entity]) && is_array($data[$entity])) {
            $purged = purgeDeleted($data[$entity], $daysOld);
            if ($purged > 0) {
                $result[$entity] = $purged;
            }
        }
    }

    return $result;
}

/**
 * 全エンティティの削除済みアイテム数を取得
 *
 * @param array $data データ全体
 * @return array エンティティごとの削除済み件数
 */
function countAllDeleted($data) {
    $entities = ['projects', 'customers', 'employees', 'troubles', 'partners'];
    $result = [];

    foreach ($entities as $entity) {
        if (isset($data[$entity]) && is_array($data[$entity])) {
            $count = count(getDeletedItems($data[$entity]));
            if ($count > 0) {
                $result[$entity] = $count;
            }
        }
    }

    return $result;
}
