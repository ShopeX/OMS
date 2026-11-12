<?php
class wap_ctl_api_wap extends desktop_ctl_api_base
{
    /**
     * WAP管理
     */
    public function index()
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
        if ($action === 'list') {
            $this->respond(['list' => [], 'total' => 0, 'page' => 1, 'pageSize' => 20]);
        } elseif ($action === 'columns') {
            $this->respond([]);
        } else {
            $this->respond([]);
        }
    }
}
