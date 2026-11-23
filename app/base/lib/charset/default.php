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


class base_charset_default implements base_charset_interface 
{
    function local2utf($strFrom,$charset='zh') {
        return $this->utfconvert($strFrom,$charset,false);
    }

    function utf2local($strFrom,$charset='zh') {
        return $this->utfconvert($strFrom,$charset,true);
    }

    function utfconvert($strFrom,$charset,$isfromUtf=false){
        if (!trim($strFrom)) return $strFrom;
        $fileGBU = fopen(dirname(__FILE__).'/default/'.($isfromUtf?'utf2'.$charset:$charset.'2utf').'.dat', "rb");
        $strBuf = fread($fileGBU, 2);
        $intCount = ord($strBuf[0]) + 256 * ord($strBuf[1]);
        $strRet = "";
        $intLen = strlen($strFrom);
        for ($i = 0; $i < $intLen; $i++) {
            if (ord($strFrom[$i]) > 127) {
                $strCurr = substr($strFrom, $i, $isfromUtf?3:2);
                if($isfromUtf){
                    $intGB = $this->utf82u($strCurr);
                }else{
                    $intGB = hexdec(bin2hex($strCurr));
                }
                $intStart = 1;
                $intEnd = $intCount;
                while ($intStart < $intEnd - 1) {
                    $intMid = floor(($intStart + $intEnd) / 2);
                    $intOffset = 2 + 4 * ($intMid - 1);
                    fseek($fileGBU, $intOffset);
                    $strBuf = fread($fileGBU, 2);
                    $intCode = ord($strBuf[0]) + 256 * ord($strBuf[1]);
                    if ($intGB == $intCode) {
                        $intStart = $intMid;
                        break;
                    }
                    if ($intGB > $intCode) $intStart = $intMid;
                    else $intEnd = $intMid;
                }
                $intOffset = 2 + 4 * ($intStart - 1);
                fseek($fileGBU, $intOffset);
                $strBuf = fread($fileGBU, 2);
                $intCode = ord($strBuf[0]) + 256 * ord($strBuf[1]);
                if ($intGB == $intCode) {
                    $strBuf = fread($fileGBU, 2);
                    if($isfromUtf){
                        $strRet .= $strBuf[1].$strBuf[0];
                    }else{
                        $intCodeU = ord($strBuf[0]) + 256 * ord($strBuf[1]);
                        $strRet .= $this->u2utf8($intCodeU);
                    }
                } else {
                    $strRet .= "??";
                }
                $i+=$isfromUtf?2:1;
            } else {
                $strRet .= $strFrom[$i];
            }
        }
        fclose($fileGBU);
        return $strRet;
    }

    function u2utf8($c) {
        $str='';
        if ($c < 0x80) {
            $str.=$c;
        }
        else if ($c < 0x800) {
            $str.=chr(0xC0 | $c>>6);
            $str.=chr(0x80 | $c & 0x3F);
        }
        else if ($c < 0x10000) {
            $str.=chr(0xE0 | $c>>12);
            $str.=chr(0x80 | $c>>6 & 0x3F);
            $str.=chr(0x80 | $c & 0x3F);
        }
        else if ($c < 0x200000) {
            $str.=chr(0xF0 | $c>>18);
            $str.=chr(0x80 | $c>>12 & 0x3F);
            $str.=chr(0x80 | $c>>6 & 0x3F);
            $str.=chr(0x80 | $c & 0x3F);
        }
        return $str;
    }
    
    function utf82u($Char){
        switch(strlen($Char)){
            case 1:
                return ord($Char);
            case 2:
                $OutStr=(ord($Char[0])&0x3f)<<6;
                $OutStr+=ord($Char[1])&0x3f;
                return $OutStr;
            case 3:
                $OutStr=(ord($Char[0])&0x1f)<<12;
                $OutStr+=(ord($Char[1])&0x3f)<<6;
                $OutStr+=ord($Char[2])&0x3f;
                return $OutStr;
            case 4:
                $OutStr=(ord($Char[0])&0x0f)<<18;
                $OutStr+=(ord($Char[1])&0x3f)<<12;
                $OutStr+=(ord($Char[2])&0x3f)<<6;
                $OutStr+=ord($Char[3])&0x3f;
                return $OutStr;
        }
    }
    
    public function replace_utf8bom($str)  
    {  
        if(substr($str,0,3)=="\xEF\xBB\xBF"){
            $str = substr($str,3);
            return $str;
        }else{
            return false;  
        }
    }
    
    public function is_utf8($word)
    {
        if(substr($word,0,3)=="\xEF\xBB\xBF"){
            return true;
        }elseif(mb_detect_encoding($word) == 'UTF-8'){
            return true;
        }else{
            return false;
        }
    }
}