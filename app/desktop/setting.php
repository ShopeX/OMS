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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
$setting = array(
'banner'=>array('type'=>SET_T_STR,'default'=>'ECOS System'),

'format.date'=>array('type'=>SET_T_STR,'default'=>'Y-m-d','desc'=>app::get('desktop')->_('日期格式')),
'format.time'=>array('type'=>SET_T_STR,'default'=>'Y-m-d H:i:s','desc'=>app::get('desktop')->_('日期时间格式')),

'finder.thead.default.width' =>array('type'=>SET_T_STR,'default'=>'105','desc'=>app::get('desktop')->_('finder默认表头的宽度')),

);
