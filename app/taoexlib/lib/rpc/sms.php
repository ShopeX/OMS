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
 * 更新短信模板
 * @package     main
 * @subpackage  classes
 * @author cyyr24@sina.cn
 */
class taoexlib_rpc_sms
{
    /**
     * 更新短信审核状态
     * @param   array result
     * @return bool
     * @access  public
     * @author cyyr24@sina.cn
     */

    function sms_callback($result)
    {
        if (!taoexlib_request_sms::verify_sms_callback_code($result)) {
            header('HTTP/1.1 403 Forbidden');
            echo 'invalid code';
            return false;
        }

        $status = isset($_POST['status']) ? strval($_POST['status']) : '';
        if (!in_array($status, array('0', '1'), true)) {
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid status';
            return false;
        }

        $tplid = isset($_POST['tplid']) ? trim(strval($_POST['tplid'])) : '';
        if ($tplid === '' || strlen($tplid) > 25 || !preg_match('/^[a-zA-Z0-9_-]+$/', $tplid)) {
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid tplid';
            return false;
        }

        $reason = isset($_POST['reason']) ? substr(trim(strval($_POST['reason'])), 0, 200) : '';
        $approved = ($status == '0') ? '2' : '1';

        $approved_at = time();
        if (isset($_POST['approved_at']) && preg_match('/^\d+$/', strval($_POST['approved_at']))) {
            $approved_at = intval($_POST['approved_at']);
        }

        $itemsModel = app::get('taoexlib')->model('sms_sample_items');
        $sampleModel = app::get('taoexlib')->model('sms_sample');
        $filter = array('tplid' => $tplid);

        $itemsData = array(
            'approved' => $approved,
            'approvedtime' => $approved_at,
        );
        if ($status == '0') {
            $itemsData['sync_reason'] = $reason;
        }

        $itemsModel->update($itemsData, $filter);
        $sampleModel->update(array('approved' => $approved), $filter);

        echo 'OK';
        return true;
    }
}
