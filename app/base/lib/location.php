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
 
class base_location{

    static function timezone_list(){
        return array(
            '-12'=>app::get('base')->_('(标准时-12) 日界线西'),
            '-11'=>app::get('base')->_('(标准时-11) 中途岛、萨摩亚群岛'),
            '-10'=>app::get('base')->_('(标准时-10) 夏威夷'),
            '-9'=>app::get('base')->_('(标准时-9) 阿拉斯加'),
            '-8'=>app::get('base')->_('(标准时-8) 太平洋时间(美国和加拿大)'),
            '-7'=>app::get('base')->_('(标准时-7) 山地时间(美国和加拿大)'),
            '-6'=>app::get('base')->_('(标准时-6) 中部时间(美国和加拿大)、墨西哥城'),
            '-5'=>app::get('base')->_('(标准时-5) 东部时间(美国和加拿大)、波哥大'),
            '-4'=>app::get('base')->_('(标准时-4) 大西洋时间(加拿大)、加拉加斯'),
            '-3'=>app::get('base')->_('(标准时-3) 巴西、布宜诺斯艾利斯、乔治敦'),
            '-2'=>app::get('base')->_('(标准时-2) 中大西洋'),
            '-1'=>app::get('base')->_('(标准时-1) 亚速尔群岛、佛得角群岛'),
            '0'=>app::get('base')->_('(格林尼治标准时) 西欧时间、伦敦、卡萨布兰卡'),
            '1'=>app::get('base')->_('(标准时+1) 中欧时间、安哥拉、利比亚'),
            '2'=>app::get('base')->_('(标准时+2) 东欧时间、开罗，雅典'),
            '3'=>app::get('base')->_('(标准时+3) 巴格达、科威特、莫斯科'),
            '4'=>app::get('base')->_('(标准时+4) 阿布扎比、马斯喀特、巴库'),
            '5'=>app::get('base')->_('(标准时+5) 叶卡捷琳堡、伊斯兰堡、卡拉奇'),
            '6'=>app::get('base')->_('(标准时+6) 阿拉木图、 达卡、新亚伯利亚'),
            '7'=>app::get('base')->_('(标准时+7) 曼谷、河内、雅加达'),
            '8'=>app::get('base')->_('(北京时间) 北京、重庆、香港、新加坡'),
            '9'=>app::get('base')->_('(标准时+9) 东京、汉城、大阪、雅库茨克'),
            '10'=>app::get('base')->_('(标准时+10) 悉尼、关岛'),
            '11'=>app::get('base')->_('(标准时+11) 马加丹、索罗门群岛'),
            '12'=>app::get('base')->_('(标准时+12) 奥克兰、惠灵顿、堪察加半岛'),
        );
    }
    
}
