<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use app\desktop\lib\annotation\RequirePermission;

class archive_ctl_api_order extends desktop_ctl_api_base
{
    protected static $objectName = 'archive_mdl_orders';

    /**
     * 归档订单查询（菜单 act=search）
     */
    #[RequirePermission('archive_search')]
    public function search(): void
    {
        $this->finderDispatch([
            'objectName' => static::$objectName,
            'orderBy' => 'createtime',
            'orderType' => 'desc',

            'actions' => [],
        ]);
    }

    /**
     * 数据归档设置页（菜单 act=index）
     *
     * 页面为纯静态表单（归档内容/归档时间/开始归档），无服务端引导数据。
     */
    #[RequirePermission('archive_set')]
    public function index(): void
    {
        $this->succ([]);
    }

    /**
     * 格式化归档时间
     *
     * 对齐 legacy archive_ctl_order::format_archivetime()
     * POST archive_time=6|9|12 ⇒ 返回 "Y-m-d H:i:s"
     */
    #[RequirePermission('archive_set')]
    public function formatArchivetime(): void
    {
        $input = $this->getRequestInput();

        $archivelib = kernel::single('archive_order');

        $this->succ([
            'date' => date('Y-m-d H:i:s', $archivelib->archivetimeFilter($input['archive_time'] ?? '')),
        ]);
    }

    /**
     * 获取本次待归档数据量
     *
     * 对齐 legacy archive_ctl_order::ajaxGetArchiveData()
     * GET status[]=fail|unpayed&archive_documents[]=sales_aftersale&archive_time=6|9|12
     */
    #[RequirePermission('archive_set')]
    public function getArchiveData(): void
    {
        $input = $this->getRequestInput();

        $orderfilter = [
            'status' => (array)($input['status'] ?? []),
            'archive_documents' => (array)($input['archive_documents'] ?? []),
            'archive_time' => $input['archive_time'] ?? '',
        ];

        $archivelib = kernel::single('archive_order');
        $total = (int)$archivelib->get_total($orderfilter);

        // legacy 副作用：写入归档操作日志
        $operObj = app::get('archive')->model('operation_log');
        $operObj->write_log('开始归档:共计:' . $total . '条', $archivelib->archivetimeFilter($orderfilter['archive_time']));

        $pagenum = ceil($total / 500);

        // legacy 时间窗：9:00-20:00 期间禁止归档
        $activehouse = date('H');
        if ($activehouse < 21 && $activehouse > 9) {
            $this->succ([
                'blocked' => true,
                'message' => '当前时间不可以操作归档',
            ]);
        }

        $this->succ([
            'blocked' => false,
            'total' => $total,
            'pagenum' => $pagenum,
            'params' => http_build_query($orderfilter),
        ]);
    }

    /**
     * 执行归档（单批 500 条）
     *
     * 对齐 legacy archive_ctl_order::ajaxDoAuto()
     * POST status[]/archive_documents[]/archive_time（即 getArchiveData 返回的 params）
     */
    #[RequirePermission('archive_set')]
    public function doAuto(): void
    {
        set_time_limit(0);

        $input = $this->getRequestInput();

        $orderfilter = [
            'status' => (array)($input['status'] ?? []),
            'archive_documents' => (array)($input['archive_documents'] ?? []),
            'archive_time' => $input['archive_time'] ?? '',
        ];

        $archivelib = kernel::single('archive_order');
        list($res, $msg) = $archivelib->process($orderfilter);

        $this->succ([
            'result' => $res ? 1 : 0,
            'msg' => $msg,
        ]);
    }
}
