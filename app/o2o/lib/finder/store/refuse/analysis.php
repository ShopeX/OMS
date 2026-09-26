<?php

class o2o_finder_store_refuse_analysis
{
    public function disabled_export_cols(&$cols)
    {
        if (isset($cols['column_reason'])) {
            unset($cols['column_reason']);
        }
    }
}
