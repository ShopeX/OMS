<?php

class goldentra_finder_metadata_item
{
    var $column_ext = '扩展字段';
    var $column_ext_width = '420';

    public function column_ext($row, $list = null)
    {
        $item_id = intval(isset($row['item_id']) ? $row['item_id'] : 0);
        $bundle = $this->__loadExtBundle($list, $item_id);
        $ext_field = ($item_id && isset($bundle['items'][$item_id]))
            ? $bundle['items'][$item_id]['ext_field']
            : (isset($row['ext_field']) ? $row['ext_field'] : '');
        $metadata_id = ($item_id && isset($bundle['items'][$item_id]))
            ? intval($bundle['items'][$item_id]['metadata_id'])
            : intval(isset($row['metadata_id']) ? $row['metadata_id'] : 0);
        $labels = ($metadata_id && isset($bundle['labels'][$metadata_id]))
            ? $bundle['labels'][$metadata_id]
            : array();

        $rows = goldentra_metadata::decodeExtFieldRows($ext_field);
        if (!$rows) {
            return '—';
        }

        $parts = array();
        foreach ($rows as $ext) {
            $key = $ext['key'];
            $label = !empty($labels[$key]) ? $labels[$key] : $key;
            $parts[] = htmlspecialchars($label . '=' . $ext['value'], ENT_QUOTES, 'UTF-8');
        }
        return implode('<br/>', $parts);
    }

    /**
     * 批量加载明细 ext_field + 字典头字段中文名
     *
     * @param array|null $list
     * @param int $fallback_item_id
     * @return array
     */
    private function __loadExtBundle($list, $fallback_item_id = 0)
    {
        static $cache = null;
        static $loadedIds = array();

        if ($cache === null) {
            $cache = array('items' => array(), 'labels' => array());
        }

        $itemIds = array();
        foreach ((array) $list as $val) {
            if (!empty($val['item_id'])) {
                $itemIds[] = intval($val['item_id']);
            }
        }
        if ($fallback_item_id > 0) {
            $itemIds[] = intval($fallback_item_id);
        }
        $itemIds = array_values(array_unique(array_filter($itemIds)));
        $needIds = array();
        foreach ($itemIds as $id) {
            if (!isset($loadedIds[$id])) {
                $needIds[] = $id;
            }
        }
        if (!$needIds) {
            return $cache;
        }

        $itemRows = app::get('goldentra')->model('metadata_item')->getList(
            'item_id,metadata_id,ext_field',
            array('item_id|in' => $needIds)
        );
        $metadataIds = array();
        foreach ((array) $itemRows as $item) {
            $id = intval($item['item_id']);
            $loadedIds[$id] = true;
            $cache['items'][$id] = array(
                'metadata_id' => intval($item['metadata_id']),
                'ext_field' => $item['ext_field'],
            );
            if (!empty($item['metadata_id'])) {
                $metadataIds[] = intval($item['metadata_id']);
            }
        }
        foreach ($needIds as $id) {
            $loadedIds[$id] = true;
        }

        $metadataIds = array_values(array_unique(array_filter($metadataIds)));
        $needMeta = array();
        foreach ($metadataIds as $mid) {
            if (!isset($cache['labels'][$mid])) {
                $needMeta[] = $mid;
            }
        }
        if (!$needMeta) {
            return $cache;
        }

        $headers = app::get('goldentra')->model('metadata')->getList(
            'metadata_id,ext_field',
            array('metadata_id|in' => $needMeta)
        );
        foreach ((array) $headers as $header) {
            $mid = intval($header['metadata_id']);
            $data = is_array($header['ext_field'])
                ? $header['ext_field']
                : json_decode($header['ext_field'], true);
            $labels = array();
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (strpos($k, 'ext_') === 0 && $v !== '' && $v !== null) {
                        $labels[$k] = is_scalar($v) ? (string) $v : '';
                    }
                }
            }
            $cache['labels'][$mid] = $labels;
        }

        return $cache;
    }
}
