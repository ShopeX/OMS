<?php

class goldentra_stock_operation_log
{
    const OBJ_TYPE = 'stock@goldentra';

    public static function write($stock_id, $operation, $memo)
    {
        if (!$stock_id) {
            return false;
        }
        return app::get('ome')->model('operation_log')->write_log(
            $operation . '@goldentra',
            $stock_id,
            $memo
        );
    }
}
