<?php

class goldentra_finder_sap_push_voucher
{
    var $detail_payload = '上传报文';

    public function detail_payload($push_id)
    {
        $row = app::get('goldentra')->model('sap_push_voucher')->dump($push_id, 'payload_json,sync_status,sync_msg');
        $render = app::get('goldentra')->render();
        $render->pagedata['payload'] = $this->prettyJson(isset($row['payload_json']) ? $row['payload_json'] : '');
        $render->pagedata['sync_status'] = isset($row['sync_status']) ? $row['sync_status'] : '';
        $render->pagedata['sync_msg'] = isset($row['sync_msg']) ? $row['sync_msg'] : '';
        return $render->fetch('admin/pushvoucher/detail_payload.html');
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
