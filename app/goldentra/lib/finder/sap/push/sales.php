<?php

class goldentra_finder_sap_push_sales
{
    var $detail_payload = '上传报文';
    var $detail_items = '行项目';

    public function detail_payload($push_id)
    {
        $row = app::get('goldentra')->model('sap_push_sales')->dump($push_id, 'payload_json,sync_status,sync_msg');
        $render = app::get('goldentra')->render();
        $render->pagedata['payload'] = $this->prettyJson(isset($row['payload_json']) ? $row['payload_json'] : '');
        $render->pagedata['sync_status'] = isset($row['sync_status']) ? $row['sync_status'] : '';
        $render->pagedata['sync_msg'] = isset($row['sync_msg']) ? $row['sync_msg'] : '';
        return $render->fetch('admin/pushsales/detail_payload.html');
    }

    public function detail_items($push_id)
    {
        $items = app::get('goldentra')->model('sap_push_sales_item')->getList(
            '*',
            array('push_id' => $push_id),
            0,
            -1,
            'item_pk ASC'
        );
        $render = app::get('goldentra')->render();
        $render->pagedata['items'] = $items;
        return $render->fetch('admin/pushsales/detail_items.html');
    }

    private function prettyJson($text)
    {
        if ($text === '' || $text === null) {
            return '';
        }
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        return $text;
    }
}
