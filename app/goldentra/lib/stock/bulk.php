<?php

/**
 * 大批量 stock 写入
 */
class goldentra_stock_bulk
{
    public function insertRows(array $rows, $chunk_size = 3000)
    {
        if (empty($rows)) {
            return 0;
        }
        $mdl = app::get('goldentra')->model('stock');
        $inserted = 0;
        foreach (array_chunk($rows, $chunk_size) as $chunk) {
            $sql = ome_func::get_insert_sql($mdl, $chunk);
            if ($sql) {
                $mdl->db->exec($sql);
                $inserted += count($chunk);
            }
        }
        return $inserted;
    }
}
