<?php

class goldentra_finder_invest_goldprice
{
    var $detail_basic = '明细';

    function detail_basic($id)
    {
        $render = app::get('goldentra')->render();
        $row = app::get('goldentra')->model('invest_goldprice')->dump($id, '*');
        $render->pagedata['row'] = $row;
        if (!empty($row['ext_field'])) {
            $extDecoded = json_decode($row['ext_field'], true);
            $render->pagedata['ext_pretty'] = $extDecoded
                ? json_encode($extDecoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : $row['ext_field'];
        } else {
            $render->pagedata['ext_pretty'] = '';
        }
        if (!empty($row['payload_json'])) {
            $decoded = json_decode($row['payload_json'], true);
            $render->pagedata['payload_pretty'] = $decoded
                ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : $row['payload_json'];
        } else {
            $render->pagedata['payload_pretty'] = '';
        }
        return $render->fetch('admin/invest_goldprice/detail.html');
    }
}
