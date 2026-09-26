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

$setting = array(
	'bill.rematch_time'=>array('type'=>SET_T_INT,'default'=>0,'desc'=>app::get('base')->_('对账单重新匹配时间')),
	'bill.sync_download_time'=>array('type'=>SET_T_INT,'default'=>0,'desc'=>app::get('base')->_('同步下载对账单时间')),
	'bill.logs.is_open'=>array('type'=>SET_T_BOOL,'default'=>'false','desc'=>app::get('base')->_('对账单日志开启设置')),
	'bill.match_fail_notify.enabled'=>array('type'=>SET_T_BOOL,'default'=>'false','desc'=>app::get('base')->_('店铺收支明细匹配失败日报')),
	'bill.match_fail_notify.cron_time'=>array('type'=>SET_T_STR,'default'=>'10:00','desc'=>app::get('base')->_('匹配失败日报发送时间')),
);
?>