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
/**
 * @author ykm 2015-12-15
 * @describe 淘宝请求电子面单类
 */
class erpapi_logistics_matrix_aikucun_request_electron extends erpapi_logistics_request_electron
{
    protected $directNum = 1;

    /**
     * bufferRequest
     * @return mixed 返回值
     */

    public function bufferRequest(){
        return $this->directNum;
    }

    /**
     * 直连取号：向矩阵申请电子面单物流单号。
     *
     * 说明：
     * - 该方法只负责“取号”，不负责打印数据签名；
     * - 返回结果会回填到 waybill 相关链路，供后续打印/补打使用。
     *
     * @param array $sdf 发货与面单业务参数
     * @return array|false
     */
    public function directRequest($sdf)
    {
        $this->title     = '爱库存-' . $this->__channelObj->channel['logistics_code'] . '获取物流单号';
        $this->timeOut   = 20;
        $this->primaryBn = $sdf['primary_bn'];

        $sdf['order_bns'] = array_column($sdf['order'], 'order_bn');

        $prt_tmpl_id = $sdf['dly_corp']['prt_tmpl_id'];

        $templateMdl  = app::get('logisticsmanager')->model('express_template');
        $templateInfo = $templateMdl->db_dump(['template_id' => $prt_tmpl_id]);

        $custom_mark = $mark_text = [];
        foreach ($sdf['order'] as $k => $v) {
            if ($v['custom_mark']) {
                $tmp           = unserialize($v['custom_mark']);
                $custom_mark[] = str_replace(["\t", "\r\n", "\r", "\n", "'", "\"", "\\"], '', $tmp['op_content']);
            }
            if ($v['mark_text']) {
                $tmp         = unserialize($v['mark_text']);
                $mark_text[] = str_replace(["\t", "\r\n", "\r", "\n", "'", "\"", "\\"], '', $tmp['op_content']);
            }
        }

        $params           = [];
        $params['cpCode'] = $this->__channelObj->channel['logistics_code'];
        $params['version'] = '2.0';

        $params['sender'] = json_encode([
            'address' => [
                'city'     => $sdf['shop']['city'],
                'detail'   => $sdf['shop']['address_detail'],
                'district' => $sdf['shop']['area'],
                'province' => $sdf['shop']['province'],
                'town'     => $sdf['shop']['street'],
            ],
            'mobile'  => $sdf['shop']['mobile'],
            'name'    => $sdf['shop']['default_sender'],
            'phone'   => $sdf['shop']['tel'],
        ]);

        $serviceCode = json_decode($this->__channelObj->channel['service_code'], 1);

        $product_type = $customer_code = [];
        foreach ($serviceCode as $k => $v) {
            // waybill 配置里顺丰/京东等使用 productType，与 PRODUCT-TYPE 等价
            if ($k == 'PRODUCT-TYPE' || $k == 'productType') {
                $product_type = is_array($v) && isset($v['value']) ? $v['value'] : $v;
                continue;
            }
            if ($k == 'customerCode') {
                $customer_code = is_array($v) && isset($v['value']) ? $v['value'] : $v;
                continue;
            }
        }

        $orderInfo = [
            'orderChannelsType' => kernel::single('wms_event_trigger_logistics_data_electron_aikucun')->orderChannelsType(),
            'tradeOrderList'    => is_array($sdf['order_bns']) ? $sdf['order_bns'] : [$sdf['order_bns']],
        ];
        $custom_mark && $orderInfo['buyerMemo'] = $custom_mark;
        $mark_text && $orderInfo['sellerMemo']  = $mark_text;

        // 爱库存按 packageInfo.id 识别包裹；若复用历史已取消的 packId，会报
        // “单号已取消，更改packId重新取号”。
        // 仅使用 delivery_id + templateId 在“切走再切回同模板”时仍会复用，因此这里增加时间种子，
        // 保证每次取号请求的 packId 都唯一。
        // 同时按接口约束控制 packId 长度：小于 12 位（这里固定为 11 位）。
        $templateId = isset($templateInfo['out_template_id']) ? $templateInfo['out_template_id'] : '0';
        $packSeed = $sdf['delivery']['delivery_id'] . '_' . $templateId . '_' . microtime(true);
        $packId = substr(md5($packSeed), 0, 11);

        $params['tradeOrderInfoList']    = [];
        $params['tradeOrderInfoList'][0] = [
            'objectId'    => $sdf['primary_bn'],
            'orderInfo'   => $orderInfo,
            'packageInfo' => [
                'id'                   => $packId,
                'items'                => [],
                'volume'               => 0,
                'weight'               => 0,
                'length'               => 0,
                'width'                => 0,
                'height'               => 0,
                'totalPackagesCount'   => 0,
                'packagingDescription' => '',
                'goodsDescription'     => '',
                'goodValue'            => 0,
            ],
            'templateId'  => $templateInfo['out_template_id'],
        ];

        $params['tradeOrderInfoList'][0]['recipient'] = [
            'address' => [
                'city'     => $sdf['delivery']['ship_city'],
                'detail'   => $sdf['delivery']['ship_addr'],
                'district' => $sdf['delivery']['ship_district'],
                'province' => $sdf['delivery']['ship_province'],
                'town'     => '',
            ],
            'mobile'  => $sdf['delivery']['ship_mobile'],
            'name'    => $sdf['delivery']['ship_name'],
            'phone'   => $sdf['delivery']['ship_tel'],
        ];

        foreach ($sdf['delivery_item'] as $k => $v) {
            $params['tradeOrderInfoList'][0]['packageInfo']['items'][] = [
                'count' => $v['number'],
                'name'  => $v['product_name'],
            ];
        }
        $params['tradeOrderInfoList'] = json_encode($params['tradeOrderInfoList']);

        if ($customer_code) {
            $params['customerCode'] = $customer_code;
        }
        if ($product_type) {
            $params['productCode'] = $product_type;
        }
        $params['callDoorPickUp'] = 'false';
        $params['sellerName']     = $sdf['shop']['shop_name'];

        $result = $this->requestCall(STORE_WAYBILL_DY_GET, $params, array());

        $returnResult = $this->backToResult($result, $sdf['delivery']);

        return $returnResult;
    }

    private function backToResult($ret, $delivery)
    {
        $waybill = empty($ret['data']) ? array() : json_decode($ret['data'], true);
        if (empty($waybill) || $ret['rsp'] == 'fail') {
            return $ret['msg'] ? $ret['msg'] : false;
        }
        $result = array();
        $logisticsNoInfoList = isset($waybill['data']['logisticsNoInfoList']) && is_array($waybill['data']['logisticsNoInfoList'])
            ? $waybill['data']['logisticsNoInfoList']
            : [];
        foreach ($logisticsNoInfoList as $val) {
            $deliveryBn = isset($val['orderId']) ? trim($val['orderId']) : '';

            $result[] = array(
                'succ'           => !empty($val['logisticsNo']),
                'msg'            => '',
                'delivery_id'    => $delivery['delivery_id'],
                'delivery_bn'    => $deliveryBn,
                'logi_no'        => isset($val['logisticsNo']) ? $val['logisticsNo'] : '',
                'mailno_barcode' => '',
                'qrcode'         => '',
                'position'       => '',
                'position_no'    => '',
                'package_wdjc'   => '',
                'package_wd'     => '',
                'print_config'   => '',
                'json_packet'    => is_array($val) ? json_encode($val) : $val,
            );
        }
        $errorInfoList = isset($waybill['data']['errorInfoList']) && is_array($waybill['data']['errorInfoList'])
            ? $waybill['data']['errorInfoList']
            : [];
        foreach ($errorInfoList as $error) {
            $result[] = array(
                'succ'        => false,
                'msg'         => isset($error['message']) ? $error['message'] : (isset($error['errorMsg']) ? $error['errorMsg'] : ''),
                'delivery_id' => $delivery['delivery_id'],
                'delivery_bn' => isset($error['orderId']) ? $error['orderId'] : $delivery['delivery_bn'],
            );
        }
        if (empty($result) && ($ret['err_msg'] || $ret['rsp'] == 'fail')) {
            $result[] = array(
                'succ'        => false,
                'msg'         => $ret['err_msg'],
                'delivery_id' => $delivery['delivery_id'],
                'delivery_bn' => $delivery['delivery_bn'],
            );
        }
        $this->directDataProcess($result);
        return $result;
    }

    public function recycleWaybill($waybillNumber, $delivery_bn = '')
    {
        app::get('logisticsmanager')->model('waybill')->update(array('status' => 2, 'create_time' => time()), array('waybill_number' => $waybillNumber));

        $this->title     = '爱库存_' . $this->__channelObj->channel['logistics_code'] . '取消电子面单';
        $this->primaryBn = $waybillNumber;

        $params = array(
            'waybillCode' => $waybillNumber,
            'cpCode'      => $this->__channelObj->channel['logistics_code'],
            'version'     => '2.0',
        );

        $callback = array(
            'class'  => get_class($this),
            'method' => 'callback',
        );
        $this->requestCall(STORE_WAYBILL_CANCEL, $params, $callback);
    }

    /**
     * 获取爱库存打印数据（两阶段）：
     * - prepare：仅取 printdata（含 mxEctData 等基础数据）
     * - sign：基于 prepare_data + print_name 生成最终 params 和 printData
     *
     * 出库打印 jsondata 传入的 delivery_id 为 sdb_wms_delivery.delivery_id，只查 WMS 发货单，避免与 OME 主键撞号误判。
     *
     * @param array $sdf
     * @return array
     */
    public function getEncryptPrintData($sdf)
    {
        $deliveryId = isset($sdf['delivery_id']) ? intval($sdf['delivery_id']) : 0;
        if ($deliveryId <= 0) {
            return array(
                'rsp'     => 'fail',
                'msg'     => '缺少发货单 delivery_id',
                'err_msg' => '缺少发货单 delivery_id',
            );
        }

        $delivery = app::get('wms')->model('delivery')->db_dump($deliveryId, '*');
        if (empty($delivery)) {
            return array(
                'rsp'     => 'fail',
                'msg'     => '发货单不存在',
                'err_msg' => '发货单不存在',
            );
        }

        $mode = isset($sdf['mode']) ? trim((string)$sdf['mode']) : '';
        if ($mode === '') {
            $mode = 'prepare';
        }
        if (!in_array($mode, array('prepare', 'sign'), true)) {
            return array(
                'rsp'     => 'fail',
                'msg'     => '不支持的打印数据模式',
                'err_msg' => '不支持的打印数据模式',
            );
        }

        // prepare：只取基础打印数据（不签名），避免页面加载阶段重复调用签名接口。
        if ($mode === 'prepare') {
            return $this->getAikucunPrepareData($sdf);
        }

        // sign：用户已选打印机后再签名，输出最终给打印控件的 printData + params。
        return $this->getAikucunSignData($sdf, $delivery);
    }

    /**
     * prepare 阶段：调用 store.waybill.printdata，返回前端可缓存的 prepareData。
     *
     * @param array $sdf
     * @return array
     */
    private function getAikucunPrepareData($sdf)
    {
        $params = array(
            'waybillCode' => $sdf['logi_no'],
            'cpCode'      => $this->__channelObj->channel['logistics_code'],
            'version'     => '2.0',
        );
        $title = '获取打印数据';
        $result = $this->__caller->call(STORE_WAYBILL_PRINTDATA, $params, array(), $title, 10, $sdf['logi_no']);
        if ($result['rsp'] != 'succ' || empty($result['data'])) {
            $result['msg'] = isset($result['err_msg']) ? $result['err_msg'] : '获取打印数据失败';
            return $result;
        }

        $payload = json_decode($result['data'], true);
        if (
            empty($payload)
            || !isset($payload['success']) || $payload['success'] !== true
            || !isset($payload['code']) || $payload['code'] !== '00000'
        ) {
            $result['rsp'] = 'fail';
            $result['msg'] = isset($payload['message']) && $payload['message'] ? $payload['message'] : '获取打印数据失败';
            $result['err_msg'] = $result['msg'];
            return $result;
        }

        $printInfoList = isset($payload['data']['printInfoList']) && is_array($payload['data']['printInfoList'])
            ? $payload['data']['printInfoList']
            : array();
        $printInfo = array();
        foreach ($printInfoList as $row) {
            if (!empty($sdf['logi_no']) && isset($row['logisticsNo']) && $row['logisticsNo'] == $sdf['logi_no']) {
                $printInfo = $row;
                break;
            }
        }
        if (empty($printInfo) && !empty($printInfoList[0])) {
            $printInfo = $printInfoList[0];
        }
        if (empty($printInfo)) {
            $result['rsp'] = 'fail';
            $result['msg'] = '未获取到打印数据';
            $result['err_msg'] = $result['msg'];
            return $result;
        }

        $result['data'] = array(
            'orderId'     => isset($printInfo['orderId']) ? $printInfo['orderId'] : '',
            'logisticsNo' => isset($printInfo['logisticsNo']) ? $printInfo['logisticsNo'] : '',
            'prepareData' => $this->buildAikucunPrepareData($sdf['logi_no'], $printInfo),
        );

        return $result;
    }

    /**
     * sign 阶段：消费前端回传的 prepare_data，调用 store.api.sign.get 生成控件 params。
     *
     * @param array $sdf
     * @param array $delivery sdb_wms_delivery 行
     * @return array
     */
    private function getAikucunSignData($sdf, $delivery)
    {
        $prepareData = $this->parseAikucunPrepareData(isset($sdf['prepare_data']) ? $sdf['prepare_data'] : '');
        if (empty($prepareData)) {
            return $this->buildAikucunFailResult('缺少预处理打印数据 prepare_data');
        }
        if (empty($prepareData['mxEctData'])) {
            return $this->buildAikucunFailResult('预处理打印数据缺少 mxEctData');
        }

        $logiNo = !empty($prepareData['logisticsNo']) ? $prepareData['logisticsNo'] : (isset($sdf['logi_no']) ? $sdf['logi_no'] : '');
        if (empty($logiNo)) {
            return $this->buildAikucunFailResult('缺少运单号 logisticsNo');
        }

        $signBody = $this->buildAikucunSignRequestBody(
            $delivery,
            $logiNo,
            $prepareData,
            isset($sdf['print_name']) ? trim((string)$sdf['print_name']) : ''
        );
        $signTitle = '获取打印控件params';
        $signBodyJson = json_encode($signBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signParams = array(
            'version'     => '2.0',
            'api_method'  => 'mengxiang.logistics.lews.getPrintInfoPdf',
            'body'        => $signBodyJson,
            'query'       => '',
        );
        $signRet = $this->__caller->call(STORE_API_SIGN_GET, $signParams, array(), $signTitle, 10, $logiNo);
        $printParams = $this->parseStoreApiSignGetParams($signRet);
        if ($printParams === false) {
            $msg = !empty($signRet['err_msg']) ? $signRet['err_msg'] : (!empty($signRet['msg']) ? $signRet['msg'] : '获取打印控件params失败');
            return $this->buildAikucunFailResult($msg);
        }
        $printParams = $this->normalizeAikucunControlParams($printParams);
        if ($printParams === false) {
            return $this->buildAikucunFailResult('打印控件params格式异常');
        }

        $printData = $signBody;
        $printData['esubrc'] = 'SendPdfToPrint';
        $printData['params'] = $printParams;

        return array(
            'rsp'  => 'succ',
            'msg'  => '',
            'data' => array(
                'orderId'     => isset($prepareData['orderId']) ? $prepareData['orderId'] : '',
                'logisticsNo' => isset($prepareData['logisticsNo']) ? $prepareData['logisticsNo'] : $logiNo,
                'printData'   => $printData,
            ),
        );
    }

    /**
     * 构建前端缓存用的 prepareData（最小必要字段）。
     *
     * @param string $logiNo
     * @param array $printInfo
     * @return array
     */
    private function buildAikucunPrepareData($logiNo, $printInfo)
    {
        return array(
            'orderId'     => isset($printInfo['orderId']) ? (string)$printInfo['orderId'] : '',
            'logisticsNo' => isset($printInfo['logisticsNo']) ? (string)$printInfo['logisticsNo'] : (string)$logiNo,
            'mxEctData'   => isset($printInfo['mxEctData']) ? (string)$printInfo['mxEctData'] : '',
        );
    }

    /**
     * 解析前端回传的 prepare_data，兼容数组/JSON 字符串两种输入。
     *
     * @param array|string $prepareData
     * @return array
     */
    private function parseAikucunPrepareData($prepareData)
    {
        if (is_array($prepareData)) {
            return $prepareData;
        }
        if (is_string($prepareData) && $prepareData !== '') {
            $decoded = json_decode($prepareData, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array();
    }

    /**
     * 组装 store.api.sign.get 的 body（与打印控件 mengxiang.logistics.lews.getPrintInfoPdf 业务体一致，不含 params）
     *
     * @param array $delivery sdb_wms_delivery 行（与 getEncryptPrintData 入参一致）
     * @param string $logiNo 运单号
     * @param array $printInfo 矩阵 printInfoList 单项
     * @param string $printName 打印机名称（由前端选择后回传，需参与签名）
     * @return array
     */
    private function buildAikucunSignRequestBody($delivery, $logiNo, $printInfo, $printName = '')
    {
        $requestId = date('YmdHis') . mt_rand(1000, 9999);
        $taskId    = 'oms_' . $delivery['delivery_bn'] . '_' . mt_rand(10000, 99999);

        $body = array(
            'logisticsNo' => (string)$logiNo,
            'requestId'   => (string)$requestId,
            'taskId'      => (string)$taskId,
            'printName'   => $printName,
            'mxEctData'   => isset($printInfo['mxEctData']) ? (string)$printInfo['mxEctData'] : '',
        );
        // PDF：非必填字段为空则不传，避免签名串含空值导致控件验签不一致
        foreach ($body as $k => $v) {
            if ($v === '' && !in_array($k, array('esubrc', 'logisticsNo', 'requestId', 'taskId', 'mxEctData'), true)) {
                unset($body[$k]);
            }
        }

        $templateData = $this->loadAikucunTemplateData($delivery);
        $templateUrl = $this->resolveAikucunTemplateUrl($templateData);
        if ($templateUrl !== '') {
            $body['templateUrl'] = $templateUrl;
        }
        $customData = $this->buildAikucunCustomData($delivery, $printInfo, $templateData);
        if (!empty($customData)) {
            $body['customData'] = $customData;
        }

        $shop = $this->loadShopForAikucunPrint($delivery);
        $senderInfo = $this->buildAikucunSenderInfo($shop);
        if (!empty($senderInfo)) {
            $body['senderInfo'] = $senderInfo;
        }

        return $body;
    }

    /**
     * 解析矩阵 store.api.sign.get 返回中的 params（矩阵常见为 JSON 字符串；解析后为对象时 params 为数组）
     * 失败返回 false
     *
     * @param array $signRet erpapi_caller 返回
     * @return string|array|false
     */
    private function parseStoreApiSignGetParams($signRet)
    {
        if (empty($signRet) || $signRet['rsp'] != 'succ' || !isset($signRet['data']) || $signRet['data'] === '') {
            return false;
        }
        $payload = $signRet['data'];
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload)) {
            return false;
        }
        $p = $this->extractAikucunParamsValue(isset($payload['data']['params']) ? $payload['data']['params'] : null);
        if ($p !== false) {
            return $p;
        }
        $p = $this->extractAikucunParamsValue(isset($payload['params']) ? $payload['params'] : null);
        if ($p !== false) {
            return $p;
        }

        return false;
    }

    /**
     * 梦饷打印控件文档：params 为「公共参数 query 串」（如 appid=...&sign=...&format=json&...）。
     * 矩阵常返回 JSON 对象数组，需转成 query 字符串；**不要用 json_encode**（那会得到 JSON 文本，与文档不一致）。
     * 已是非空字符串时原样返回（兼容矩阵直接返回 query 串）。
     *
     * @param string|array $params
     * @return string|false
     */
    private function normalizeAikucunControlParams($params)
    {
        if (is_string($params)) {
            $params = trim($params);
            if ($params === '') {
                return false;
            }
            $tmp = array();
            parse_str($params, $tmp);
            if (!is_array($tmp) || empty($tmp)) {
                return false;
            }
            $params = $tmp;
        }
        if (!is_array($params) || empty($params)) {
            return false;
        }
        $flat = array();
        foreach ($params as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $flat[$k] = $v;
            }
        }
        if (empty($flat)) {
            return false;
        }
        // 按文档规则：先对参数名做 ASCII 升序，再将 sign 作为 query 末尾参数附加
        $sign = null;
        if (array_key_exists('sign', $flat)) {
            $sign = $flat['sign'];
            unset($flat['sign']);
        }
        ksort($flat, SORT_STRING);
        if ($sign !== null) {
            $flat['sign'] = $sign;
        }

        return http_build_query($flat, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 解析打印主模板 URL。
     *
     * 规则：
     * - 标准模板：使用 templateUrl；
     * - 自定义模板：主模板改为 parentTemplateUrl（customTemplateUrl 在 customData 里传）。
     *
     * @param array $templateData
     * @return string
     */
    private function resolveAikucunTemplateUrl($templateData)
    {
        if (!is_array($templateData) || empty($templateData)) {
            return '';
        }
        // 标准模板
        if (!empty($templateData['templateUrl'])) {
            return (string)$templateData['templateUrl'];
        }
        // 自定义模板
        if (!empty($templateData['parentTemplateUrl'])) {
            return (string)$templateData['parentTemplateUrl'];
        }
        return '';
    }

    /**
     * 按发货单当前物流公司加载模板配置（express_template.template_data）。
     *
     * @param array $delivery sdb_wms_delivery 行（含 logi_id→dly_corp）
     * @return array
     */
    private function loadAikucunTemplateData($delivery)
    {
        $logiId = isset($delivery['logi_id']) ? $delivery['logi_id'] : 0;
        if (!$logiId) {
            return array();
        }
        $corpRows = app::get('ome')->model('dly_corp')->getList(
            'prt_tmpl_id,channel_id',
            array('corp_id' => $logiId),
            0,
            1
        );
        if (empty($corpRows) || empty($corpRows[0]['prt_tmpl_id'])) {
            return array();
        }
        $corp = $corpRows[0];
        if (!empty($corp['channel_id']) && $corp['channel_id'] != $this->__channelObj->channel['channel_id']) {
            $prtTmpl = app::get('ome')->model('dly_corp_channel')->db_dump(
                array('channel_id' => $this->__channelObj->channel['channel_id'], 'corp_id' => $logiId),
                'prt_tmpl_id'
            );
            if ($prtTmpl) {
                $corp['prt_tmpl_id'] = $prtTmpl['prt_tmpl_id'];
            }
        }
        $templateObj = app::get('logisticsmanager')->model('express_template');
        $printTplRows = $templateObj->getList('*', array('template_id' => $corp['prt_tmpl_id']), 0, 1);
        $printTpl = !empty($printTplRows[0]) ? $printTplRows[0] : array();
        if (empty($printTpl) || empty($printTpl['template_data'])) {
            return array();
        }
        $templateData = @json_decode($printTpl['template_data'], 1);
        return is_array($templateData) ? $templateData : array();
    }

    /**
     * 构建自定义模板节点 customData。
     *
     * @param array $delivery
     * @param array $printInfo
     * @param array $templateData
     * @return array
     */
    private function buildAikucunCustomData($delivery, $printInfo, $templateData)
    {
        if (!is_array($templateData) || empty($templateData['customTemplateUrl'])) {
            return array();
        }
        $customBase = $this->buildAikucunCustomBaseData($delivery);
        $orderBn = $customBase['orderBn'];
        $productInfo = $customBase['productInfo'];
        $itemBn = $customBase['itemBn'];
        $barcode = $customBase['barcode'];
        $valueMap = $this->buildAikucunCustomValueMap($orderBn, $productInfo, $itemBn, $barcode, $delivery);

        $payload = array();
        $customParams = (isset($templateData['customParams']) && is_array($templateData['customParams']))
            ? $templateData['customParams']
            : array();

        // 关键说明：
        // 打印控件在转发时会对 customData.data 的 key 做重排。
        // 若后端签名时 key 顺序与控件转发顺序不一致，会触发 A0002（签名验证失败）。
        // 因此此处使用固定顺序组装 data，并仅保留 customParams 允许的字段。
        $fieldOrder = array(
            'orderId', 'productInfo', 'remark',
            'data1', 'data2', 'data3', 'data4', 'data5', 'data6',
            'customeBarCode', 'merStyleNo', 'styleNo', 'barcode',
        );
        $allowMap = $this->buildAikucunCustomAllowMap($customParams);
        foreach ($fieldOrder as $field) {
            if (!empty($allowMap) && empty($allowMap[$field])) {
                continue;
            }
            if (isset($valueMap[$field]) && $valueMap[$field] !== '') {
                $payload[$field] = $valueMap[$field];
            }
        }

        // 控件要求 customData.data 不能全空，至少保留一个可用字段。
        if (empty($payload) && (empty($allowMap) || !empty($allowMap['orderId'])) && $orderBn !== '') {
            $payload['orderId'] = $orderBn;
        }
        if (empty($payload)) {
            return array();
        }

        return array(
            array(
                'customTemplateUrl' => (string)$templateData['customTemplateUrl'],
                'data'              => $payload,
            ),
        );
    }

    /**
     * 构建 customData.data 的字段值映射。
     *
     * @param string $orderBn
     * @param string $productInfo
     * @param string $itemBn
     * @param string $barcode
     * @param array $delivery
     * @return array
     */
    private function buildAikucunCustomValueMap($orderBn, $productInfo, $itemBn, $barcode, $delivery)
    {
        $remark = '';
        if (isset($delivery['memo']) && $delivery['memo'] !== '') {
            $remark = (string)$delivery['memo'];
        } elseif (isset($delivery['remark']) && $delivery['remark'] !== '') {
            $remark = (string)$delivery['remark'];
        }

        return array(
            'orderId'     => $orderBn,      // 订单号（内部变量名使用 orderBn，避免和系统 order_id 混淆）
            'productInfo' => $productInfo,  // 商品名称/规格/数量
            'remark'      => $remark,       // 备注
            'data1'       => '',            // 自定义字段1
            'data2'       => '',            // 自定义字段2
            'data3'       => '',            // 自定义字段3
            'data4'       => '',            // 自定义字段4
            'data5'       => '',            // 自定义字段5
            'data6'       => '',            // 自定义字段6
            'customeBarCode' => $barcode,   // 条形码（文档字段名即 customeBarCode）
            'merStyleNo'  => $itemBn,       // 款号（当前无独立字段，先映射 delivery_items.bn）
            'styleNo'     => $itemBn,       // 货号（delivery_items.bn）
            'barcode'     => $barcode,      // 条码
        );
    }

    /**
     * 将模板 customParams 转成快速判断的 allowMap。
     *
     * @param array $customParams
     * @return array
     */
    private function buildAikucunCustomAllowMap($customParams)
    {
        $allowMap = array();
        if (empty($customParams)) {
            return $allowMap;
        }
        foreach ($customParams as $field) {
            if (is_string($field) && $field !== '') {
                $allowMap[$field] = true;
            }
        }
        return $allowMap;
    }

    /**
     * 聚合 customData 的基础业务字段（订单号、商品信息、款号、条码）。
     * $delivery 为 sdb_wms_delivery 行（与打印页 delivery_id 一致）。
     *
     * @param array $delivery
     * @return array
     */
    private function buildAikucunCustomBaseData($delivery)
    {
        $deliveryId = isset($delivery['delivery_id']) ? intval($delivery['delivery_id']) : 0;
        $defaultOrderBn = isset($delivery['delivery_bn']) ? (string)$delivery['delivery_bn'] : '';
        $result = array(
            'orderBn'     => $defaultOrderBn,
            'productInfo' => '',
            'itemBn'      => '',
            'barcode'     => '',
        );
        if ($deliveryId <= 0) {
            return $result;
        }
        // 订单号：delivery_order 挂在 OME 发货单上，用 WMS 已有的 outer→OME 映射，禁止把 WMS 主键当 OME 主键查
        $omeDlyId = app::get('wms')->model('delivery')->getOuterIdById($deliveryId);
        if ($omeDlyId) {
            $orderRow = app::get('ome')->model('delivery')->getOrderBnbyDeliveryId($omeDlyId);
            if (!empty($orderRow['order_bn'])) {
                $result['orderBn'] = (string)$orderRow['order_bn'];
            }
        }

        $rows = app::get('wms')->model('delivery_items')->getList('product_name,number,product_id,bn', array('delivery_id' => $deliveryId));
        if (empty($rows) || !is_array($rows)) {
            return $result;
        }

        $productIds = array();
        foreach ($rows as $row) {
            if (!empty($row['product_id'])) {
                $productIds[] = intval($row['product_id']);
            }
        }
        $productIds = array_values(array_unique(array_filter($productIds)));
        $productMap = array();
        if (!empty($productIds)) {
            $productRows = app::get('ome')->model('products')->getList('product_id,spec_info,barcode', array('product_id' => $productIds));
            foreach ((array)$productRows as $product) {
                $pid = intval($product['product_id']);
                if ($pid > 0) {
                    $productMap[$pid] = $product;
                }
            }
        }

        $parts = array();
        foreach ($rows as $row) {
            $name = isset($row['product_name']) ? trim((string)$row['product_name']) : '';
            if ($name === '') {
                continue;
            }
            $pid = isset($row['product_id']) ? intval($row['product_id']) : 0;
            $spec = ($pid > 0 && !empty($productMap[$pid]['spec_info'])) ? trim((string)$productMap[$pid]['spec_info']) : '';
            $num = isset($row['number']) ? (string)$row['number'] : '';
            $title = $name . ($spec !== '' ? '【' . $spec . '】' : '');
            $parts[] = $title . ($num !== '' ? '/' . $num : '');

            if ($result['itemBn'] === '' && !empty($row['bn'])) {
                $result['itemBn'] = (string)$row['bn'];
            }
            if ($result['barcode'] === '' && $pid > 0 && !empty($productMap[$pid]['barcode'])) {
                $result['barcode'] = (string)$productMap[$pid]['barcode'];
            }
        }

        $result['productInfo'] = implode(',', $parts);
        return $result;
    }

    /**
     * 发件人信息：与 common 一致优先 channel_extend，否则按 aikucun getDirectSdf 用仓库
     *
     * @param array $delivery
     * @return array
     */
    private function loadShopForAikucunPrint($delivery)
    {
        $shop = app::get('logisticsmanager')->model('channel_extend')->dump(
            array('channel_id' => $this->__channelObj->channel['channel_id'])
        );
        if (empty($shop) || (empty($shop['province']) && empty($shop['default_sender']) && empty($shop['mobile']))) {
            $shop = array();
            $branch = app::get('ome')->model('branch')->db_dump($delivery['branch_id']);
            if (!empty($branch)) {
                $mainland = '';
                if (strpos($branch['area'], ':') !== false) {
                    list(, $mainland) = explode(':', $branch['area'], 2);
                }
                $province = $city = $area = '';
                if ($mainland !== '') {
                    $parts = explode('/', $mainland);
                    $province = isset($parts[0]) ? $parts[0] : '';
                    $city     = isset($parts[1]) ? $parts[1] : '';
                    $area     = isset($parts[2]) ? $parts[2] : '';
                }
                $shop['province']       = $province;
                $shop['city']           = $city;
                $shop['area']           = $area;
                $shop['street']         = '';
                $shop['address_detail'] = $branch['address'];
                $shop['default_sender'] = $branch['uname'];
                $shop['mobile']         = $branch['mobile'];
            }
        }

        return is_array($shop) ? $shop : array();
    }

    /**
     * PDF senderInfo 结构
     *
     * @param array $shop
     * @return array
     */
    private function buildAikucunSenderInfo($shop)
    {
        if (empty($shop)) {
            return array();
        }
        $province = isset($shop['province']) ? (string)$shop['province'] : '';
        $city     = isset($shop['city']) ? (string)$shop['city'] : '';
        $district = isset($shop['area']) ? (string)$shop['area'] : '';
        $street   = isset($shop['street']) ? (string)$shop['street'] : '';
        $detail   = isset($shop['address_detail']) ? (string)$shop['address_detail'] : '';
        $name     = isset($shop['default_sender']) ? (string)$shop['default_sender'] : '';
        $mobile   = isset($shop['mobile']) ? (string)$shop['mobile'] : '';

        if ($province === '' && $city === '' && $district === '' && $detail === '' && $name === '' && $mobile === '') {
            return array();
        }

        $address = array(
            'senderProvince'  => $province,
            'senderCity'      => $city,
            'senderDistrict'  => $district,
            'senderAddress'   => $detail,
        );
        if ($street !== '') {
            $address['senderStreet'] = $street;
        }

        return array(
            array(
                'address' => $address,
                'contact' => array(
                    'senderName'     => $name,
                    'sendermobile'   => $mobile,
                ),
            ),
        );
    }

    /**
     * 统一构造失败返回，减少重复分支代码。
     *
     * @param string $msg
     * @return array
     */
    private function buildAikucunFailResult($msg)
    {
        return array(
            'rsp'     => 'fail',
            'msg'     => $msg,
            'err_msg' => $msg,
        );
    }

    /**
     * 提取矩阵返回中的 params 值（仅接受非空字符串或非空数组）。
     *
     * @param mixed $value
     * @return string|array|false
     */
    private function extractAikucunParamsValue($value)
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_array($value) && !empty($value)) {
            return $value;
        }
        return false;
    }

    public function getWaybillISearch($sdf = array())
    {
        $params = [
            'cpCode' => $this->__channelObj->channel['logistics_code'],
            'version'=>'2.0',
        ];

        $title = '查询已开通的快递服务';

        $result = $this->__caller->call(STORE_KS_USER_TEMPLATE, $params, array(), $title, 6, $this->__channelObj->channel['logistics_code']);

        if ($result['rsp'] == 'succ' && $result['data']) {
            $data           = json_decode($result['data'], 1);
            $result['data'] = $data;
        } else {
            $result['msg'] = $result['err_msg'];
        }
        $result['request_logistics_code'] = $this->__channelObj->channel['logistics_code'];
        $result['channel_type']           = $this->__channelObj->channel['channel_type'];

        $_tmp = [];
        $accountId = isset($result['data']['data']['accountId']) ? $result['data']['data']['accountId'] : '';
        if (!empty($result['data']['data']) && is_array($result['data']['data'])) {
            foreach ($result['data']['data'] as $row) {
                if (empty($row['netSiteList']) || !is_array($row['netSiteList'])) {
                    continue;
                }
                foreach ($row['netSiteList'] as $site) {
                    $_tmp[] = [
                        'acct_id'        => $accountId,
                        'delivery_id'    => isset($row['logisticsCode']) ? $row['logisticsCode'] : '',
                        'site_code'      => isset($site['netSiteCode']) ? $site['netSiteCode'] : (isset($row['logisticsCode']) ? $row['logisticsCode'] : ''),
                        'site_name'      => isset($site['netSiteName']) ? $site['netSiteName'] : '',
                        'mobile'         => isset($site['mobile']) ? $site['mobile'] : '',
                        'phone'          => isset($site['phone']) ? $site['phone'] : '',
                        'name'           => isset($site['name']) ? $site['name'] : '',
                        'province_name'  => isset($site['senderProvince']) ? $site['senderProvince'] : '',
                        'city_name'      => isset($site['senderCity']) ? $site['senderCity'] : '',
                        'district_name'  => isset($site['senderDistrict']) ? $site['senderDistrict'] : '',
                        'street_name'    => isset($site['senderStreet']) ? $site['senderStreet'] : '',
                        'detail_address' => isset($site['senderAddress']) ? $site['senderAddress'] : '',
                        'customer_code'  => isset($site['customerCode']) ? $site['customerCode'] : '',
                    ];
                }
            }
        }
        $result['data']['account_list'] = $_tmp;

        return $result;
    }
}
