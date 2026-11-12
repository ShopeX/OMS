<?php
/**
 * Copyright 2012-2026 ShopeX
 * Licensed under the Apache License, Version 2.0
 *
 * 京东店铺会员相关请求（pin 转 xid）
 */
class erpapi_shop_matrix_360buy_request_member extends erpapi_shop_request_member
{
    /**
     * 京东 pin 转 xid / ouid
     *
     * @param array $sdf
     *   - pinType int 必填，默认 0：PIN类型 0:userPin(明文) 1:mixPin(密文) 2:openId 3:oldXid
     *   - pinInfoList array 必填，待转换参数列表；也可单条传 convertPin + 可选 orderId
     *   - convertType int 必填，默认 0：转换类型 0:pin转XID 1:pin转ouid
     *   - convertOrderType int 必填，默认 1：订单类型 1:POP订单 2:自营订单
     * @return array
     */
    public function pin2xid(array $sdf)
    {
        if (empty($sdf['pinInfoList']) && empty($sdf['convertPin'])) {
            return $this->error('convertPin或pinInfoList不能为空', '', array());
        }

        // request: object 必填 — Matrix 业务体
        $request = array(
            // pinType: integer 必填 — PIN类型
            // 0:userPin(明文pin，默认) 1:mixPin(密文pin) 2:openId 3:oldXid
            'pinType' => (int) (isset($sdf['pinType']) ? $sdf['pinType'] : 0),

            // pinInfoList: array 必填 — 待转换参数列表
            'pinInfoList' => array(),

            // convertType: integer 必填 — 转换类型 0:pin转XID(默认) 1:pin转ouid
            'convertType' => (int) (isset($sdf['convertType']) ? $sdf['convertType'] : 0),

            // convertOrderType: integer 必填 — 订单类型 1:POP订单 2:自营订单
            'convertOrderType' => (int) (isset($sdf['convertOrderType']) ? $sdf['convertOrderType'] : 1),
        );

        // 批量：显式传入 pinInfoList 则整表使用
        if (!empty($sdf['pinInfoList']) && is_array($sdf['pinInfoList'])) {
            $request['pinInfoList'] = $sdf['pinInfoList'];
        } else {
            $pinItem = array(
                // convertPin: string 必填 — 待转换的 pin（支持 mixPin/openId/xid）
                'convertPin' => $sdf['convertPin'],
            );
            // orderId: integer(int64) 可选 — 订单Id（须为近一年订单）；有则带上
            if (isset($sdf['orderId']) && $sdf['orderId'] !== '' && $sdf['orderId'] !== null) {
                $pinItem['orderId'] = $sdf['orderId'];
            }
            $request['pinInfoList'] = array($pinItem);
        }

        $params = array(
            // 业务参数仅 request（JSON 字符串），与 Matrix store.user.pin2xid 一致
            'request' => json_encode($request, JSON_UNESCAPED_UNICODE),
        );

        $originalBn = '';
        if (isset($sdf['orderId']) && $sdf['orderId'] !== '' && $sdf['orderId'] !== null) {
            $originalBn = (string) $sdf['orderId'];
        } elseif (!empty($sdf['convertPin'])) {
            $originalBn = (string) $sdf['convertPin'];
        } elseif (!empty($request['pinInfoList'][0]['convertPin'])) {
            $originalBn = (string) $request['pinInfoList'][0]['convertPin'];
        }

        $rsp = $this->__caller->call(
            SHOP_USER_PIN2XID,
            $params,
            array(),
            '京东pin转xid',
            10,
            $originalBn
        );

        return $this->_parsePin2xidResponse($rsp);
    }

    /**
     * 解析 Matrix pin2xid 响应，归一化为单层 data（成功/失败结构一致）
     *
     * Matrix 原始结构：
     * { rsp:succ, data: { success:true, data: { successCount, pinConversionsDTOList } } }
     *
     * 归一化后 data：
     * { successCount, pinConversionsDTOList, convertPin, xid, xidBuyer, pinToXid, message }
     *
     * @param array $rsp
     * @return array
     */
    private function _parsePin2xidResponse($rsp)
    {
        if (!is_array($rsp)) {
            return $this->error('接口返回异常', '', array());
        }

        $msgId = isset($rsp['msg_id']) ? $rsp['msg_id'] : '';
        if (isset($rsp['rsp']) && $rsp['rsp'] != 'succ') {
            $errorMsg = isset($rsp['msg']) && $rsp['msg'] ? $rsp['msg'] : (isset($rsp['err_msg']) ? $rsp['err_msg'] : '京东pin转xid失败');
            return $this->error($errorMsg, $msgId, array());
        }

        $rawData = isset($rsp['data']) ? $rsp['data'] : null;
        $payload = $rawData;
        if (is_string($rawData) && $rawData !== '') {
            $decoded = json_decode($rawData, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (!is_array($payload)) {
            return $this->error('京东pin转xid返回数据异常', $msgId, array());
        }

        // 解开 Matrix 双层 data：payload.data.pinConversionsDTOList
        $inner = (isset($payload['data']) && is_array($payload['data'])) ? $payload['data'] : $payload;
        $list = array();
        if (!empty($inner['pinConversionsDTOList']) && is_array($inner['pinConversionsDTOList'])) {
            $list = $inner['pinConversionsDTOList'];
        }

        $first = isset($list[0]) && is_array($list[0]) ? $list[0] : array();
        $xid = '';
        if (!empty($first['pinToXid'])) {
            $xid = $first['pinToXid'];
        } elseif (!empty($first['xidBuyer'])) {
            $xid = $first['xidBuyer'];
        }

        $data = array(
            'successCount' => isset($inner['successCount']) ? (int) $inner['successCount'] : count($list),
            'pinConversionsDTOList' => $list,
            'convertPin' => isset($first['convertPin']) ? $first['convertPin'] : '',
            'xid' => $xid,
            'xidBuyer' => isset($first['xidBuyer']) ? $first['xidBuyer'] : '',
            'pinToXid' => isset($first['pinToXid']) ? $first['pinToXid'] : '',
            'message' => isset($first['message']) ? $first['message'] : '',
        );

        // 外层 success=false，或列表项 success=false / successCount=0
        $itemFailed = $first && array_key_exists('success', $first) && !$first['success'];
        $outerFailed = array_key_exists('success', $payload) && !$payload['success'];
        $countFailed = isset($inner['successCount']) && (int) $inner['successCount'] <= 0 && !empty($list);

        if ($outerFailed || $itemFailed || $countFailed) {
            $errorMsg = '京东pin转xid失败';
            if (!empty($data['message'])) {
                $errorMsg = $data['message'];
            } elseif (!empty($payload['message'])) {
                $errorMsg = $payload['message'];
            }
            return $this->error($errorMsg, $msgId, $data);
        }

        return $this->succ('京东pin转xid成功', $msgId, $data);
    }
}
