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

class modifiers{

    static function tag(&$rows){
        foreach($rows as $r){
            $rows[$r] = null;
            if(is_array($this->tags[$r])){
                foreach($this->tags[$r] as $t){
                    $rows[$r] .= '<b class="tag">'.$t.'</b>';
                }
            }
        }
        unset($this->tags);
    }

    static function gender(&$rows){
        $gender = array(
            '0'=>app::get('base')->_('女'),
            '1'=>app::get('base')->_('男') );
        foreach($rows as $i => $v){
            $rows[$i] = $gender[$v];
        }
    }

    static function region(&$rows){
        foreach($rows as $i=>$r){
            list($pkg,$regions,$region_id) = explode(':',$r);
            if(is_numeric($region_id)){
                $rows[$i] = str_replace('/','-',$regions);
            }
        }
    }

    static function date(&$rows,$options=null){
        foreach($rows as $i=>$date){
            if($date){
                $rows[$i] = ( is_numeric($date) ? date('Y-m-d',$date) : $date);
            }
        }
    }

    static function time(&$rows,$options=null){
        foreach($rows as $i=>$date){
            if($date){
                $rows[$i] = ( is_numeric($date) ? date('Y-m-d H:i:s',$date) : $date);
            }
        }
    }

    static function last_modify(&$rows,$options=null)
    {
        foreach ($rows as $i=>$date)
        {
            if($date)
            {
                $rows[$i] = ($date ? date('Y-m-d H:i:s',$date) : '');
            }
        }
    }

    static function money(&$rows,$options=null){
        $oCur = app::get('eccommon')->model('currency');
        $oMath = kernel::single('eccommon_math');
        foreach($rows as $i=>$money){
            $rows[$i] = $oCur->changer($oMath->getOperationNumber($money));
        }
    }

    static function intbool(&$rows,$options=null){
        $aBool = array(
            '0'=>app::get('base')->_('否'),
            '1'=>app::get('base')->_('是') );
        foreach($rows as $i => $v){
            $rows[$i] = $aBool[$v];
        }
    }

    static function tinybool(&$rows,$options=null){
        $aBool = array(
            'N'=>app::get('base')->_('否'),
            'Y'=>app::get('base')->_('是') );
        foreach($rows as $i => $v){
            $rows[$i] = $aBool[$v];
        }
    }

    static function bool(&$rows,$options=null){
        $aBool = array(
            'false'=>app::get('base')->_('否'),
            'true'=>app::get('base')->_('是') );
        foreach($rows as $i => $v){
            $rows[$i] = $aBool[$v];
        }
    }

    static function enum(&$rows,$options=null){
        $options = $options['options'];
        foreach($rows as $i => $v){
            $rows[$i] = $options[$v];
        }
    }

}//End Class
