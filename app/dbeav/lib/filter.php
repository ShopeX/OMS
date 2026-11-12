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
class dbeav_filter{

    /**
     * Allowed operator suffixes for col|op filter keys.
     *
     * @var string[]
     */
    protected static $allowedFilterTypes = array(
        'than',
        'lthan',
        'nequal',
        'noequal',
        'tequal',
        'sthan',
        'bthan',
        'has',
        'head',
        'foot',
        'nohas',
        'between',
        'betweenstr',
        'in',
        'notin',
        'findinset',
    );

    /**
     * Whether a filter key fragment is a safe SQL identifier / column name.
     *
     * @param mixed $name
     * @return bool
     */
    protected function isSafeColumnName($name){
        return is_string($name) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1;
    }

    /**
     * Normalize values for unquoted compare operators (than/lthan/sthan/bthan/between).
     *
     * - Numbers are coerced (CNVD-2026-42947: block expression injection like "0 AND sleep(1)").
     * - Legacy TIMESTAMP filters pass already-quoted datetime literals into these operators;
     *   allow only a strict `'YYYY-MM-DD[ HH:MM:SS]'` shape so those filters keep working.
     * - Any other string is rejected (returns null → condition skipped).
     *
     * @param mixed $var
     * @return int|float|string|null
     */
    protected function normalizeUnquotedCompareValue($var){
        if(is_array($var) || $var === '' || $var === null || is_bool($var)){
            return null;
        }
        if(is_int($var) || is_float($var)){
            return $var + 0;
        }
        if(!is_string($var)){
            return null;
        }
        if(is_numeric($var)){
            return $var + 0;
        }
        // Quoted datetime literal produced by TIMESTAMP branch (not user-raw SQL).
        if(preg_match("/^'[0-9]{4}-[0-9]{2}-[0-9]{2}( [0-9]{2}:[0-9]{2}:[0-9]{2})?'$/", $var) === 1){
            return $var;
        }
        return null;
    }

    function dbeav_filter_parser($filter,$tableAlias=null,$baseWhere=null,&$object){
        $this->use_like = $object->filter_use_like;
        $nullKeys = array();
        if(is_array($filter)){
            foreach($filter as $nk=>$nv){
                if(is_null($nv)){
                    $nullKeys[$nk] = true;
                    unset($filter[$nk]);
                }
            }
        }
        $filter = utils::addslashes_array($filter);
        $schema = $object->get_schema();
        $idColumn = $schema['idColumn'];
        $tPre = ($tableAlias?$tableAlias:'`'.$object->table_name(true).'`').'.';
        $where = $baseWhere?$baseWhere:array(1);

        if(isset($filter['tag']) && $tag = $filter['tag']){
            if(is_array($filter['tag'])){
                foreach($filter['tag'] as $tk=>$tv){
                    if($tv == '_ANY_')
                        unset($filter['tag'][$tk]);
                }
            }
            if(isset($filter['tag']))
                unset($filter['tag']);
            if(is_array($tag)){
                if(count($tag) == 0){
                    unset($tag);
                }
            }else{
                $tag = array($tag);
            }
            if( $tag == '_ANY_' || $tag == array('_ANY_') ){
                unset($tag);
            }
            if($tag){
                $a = array();
                if(is_array($tag) && count($tag)>0){
                    foreach($object->db->select("select rel_id from sdb_desktop_tag_rel where tag_id in (".implode(',',$tag).")") as $r){
                        $a[] = $r['rel_id'];
                    }
                }
                if(count($a)>0){
                    $where[] = "{$tPre}{$idColumn} in ('".implode("','",$a)."')";
                }else{
                    $where[] = ' 0';
                }
            }
        }
        if(isset($filter['final_amount']) && !is_array($filter['final_amount']) && !is_numeric($filter['final_amount'])){
            unset($filter['final_amount']);
        }
        if(isset($filter['advance']) && !is_array($filter['advance']) && !is_numeric($filter['advance'])){
            unset($filter['advance']);
        }

        $cols = array_merge($object->searchOptions(),$object->_columns());

        foreach($nullKeys as $nk=>$unused){
            if($this->isSafeColumnName($nk) && isset($cols[$nk])){
                $where[] = $tPre.$nk.' is NULL ';
            }
        }

       //idColumn为数组时单独处理
        if(!is_array($idColumn) && ((isset($filter[$idColumn]) && $filter[$idColumn]=='_ALL_')|| (isset($filter[$idColumn]) && $filter[$idColumn]==array('_ALL_')))){
            unset($filter[$idColumn]);
        }elseif(!is_array($idColumn) && (isset($filter[$idColumn]) && is_array($filter[$idColumn]))){
            $where[] = " {$tPre}{$idColumn} in ('".implode("','",(array)$filter[$idColumn])."') ";
            unset($filter[$idColumn]);
        }
        if(is_array($filter)){
            foreach($filter as $k=>$v){
                if($k=='filter_sql'){
                    // Trusted PHP-side fragments only; HTTP requests strip filter_sql in utils::_filter_input.
                    $where[] = str_replace('{table}',$tPre,stripslashes($v));
                    continue;
                }
                if(isset($cols[$k])||strpos($k,'|')!==false){
                    if(strpos($k,'|')!==false){
                        $parts = explode('|',$k,2);
                        if(count($parts) !== 2){
                            continue;
                        }
                        list($col,$type) = $parts;
                        // Schema column defs are arrays; searchOptions() merges string labels — skip those.
                        if(!$this->isSafeColumnName($col) || !isset($cols[$col]) || !is_array($cols[$col]) || !in_array($type, self::$allowedFilterTypes, true)){
                            continue;
                        }
                        $k = $col;
                        unset($filter[$k]);
                        $_str = $this->_inner_getFilterType($type,$v,false);
                        if($_str === null || $_str === ''){
                            continue;
                        }
                        if( strpos($_str,'{field}')!==false )
                            $where[] = str_replace('{field}',$tPre.$k,$_str);
                        else
                            $where[] = $tPre.$k.$_str;
                        $_str = null;
                        continue;
                    }
                    // searchOptions()-only keys are label strings, not column schema (avoids PHP 8 TypeError on ['type']).
                    if(!is_array($cols[$k])){
                        continue;
                    }
                    if(is_array($v)){
                        $where[] = $tPre.$k.$this->_inner_getFilterType('in',$v,false);
                        continue;
                    }
                     $ac = array();
                        if($cols[$k]['type']=='time'||$cols[$k]['type']=='last_modify'){
                                if($filter['_'.$k.'_search']=='between'){
                                    $a_v = array();
                                    $a_v[] = strtotime($filter[$k.'_from'].' '.$filter['_DTIME_']['H'][$k.'_from'].':'.$filter['_DTIME_']['M'][$k.'_from'].':00');
                                    $a_v[] = strtotime($filter[$k.'_to'].' '.$filter['_DTIME_']['H'][$k.'_to'].':'.$filter['_DTIME_']['M'][$k.'_to'].':00');
                                    if(!empty($filter[$k.'_from'])&&empty($filter[$k.'_to'])){
                                        $where[] = $tPre.$k.$this->_inner_getFilterType('bthan',$a_v[0]);
                                    }elseif(empty($filter[$k.'_from'])&&!empty($filter[$k.'_to'])){
                                        $where[] = $tPre.$k.$this->_inner_getFilterType('lthan',$a_v[1]);
                                    }elseif(!empty($filter[$k.'_from'])&&!empty($filter[$k.'_to'])){
                                        $where[] = str_replace('{field}',$tPre.$k,$this->_inner_getFilterType($filter['_'.$k.'_search'],$a_v));
                                    }
                                }else{
                                    $a_v = strtotime($filter[$k].' '.$filter['_DTIME_']['H'][$k].':'.$filter['_DTIME_']['M'][$k].':00');
                                    $where[] = $tPre.$k.$this->_inner_getFilterType($filter['_'.$k.'_search'],$a_v);
                                }
                        }elseif($cols[$k]['type']=='TIMESTAMP'){
                                if($filter['_'.$k.'_search']=='between'){
                                    $a_v = array();
                                    $a_v[] = "'".$filter[$k.'_from'].' '.$filter['_DTIME_']['H'][$k.'_from'].':'.$filter['_DTIME_']['M'][$k.'_from'].':00'."'";
                                    $a_v[] = "'".$filter[$k.'_to'].' '.$filter['_DTIME_']['H'][$k.'_to'].':'.$filter['_DTIME_']['M'][$k.'_to'].':00'."'";
                                    if(!empty($filter[$k.'_from'])&&empty($filter[$k.'_to'])){
                                        $where[] = $tPre.$k.$this->_inner_getFilterType('bthan',$a_v[0]);
                                    }elseif(empty($filter[$k.'_from'])&&!empty($filter[$k.'_to'])){
                                        $where[] = $tPre.$k.$this->_inner_getFilterType('lthan',$a_v[1]);
                                    }elseif(!empty($filter[$k.'_from'])&&!empty($filter[$k.'_to'])){
                                        $where[] = str_replace('{field}',$tPre.$k,$this->_inner_getFilterType($filter['_'.$k.'_search'],$a_v));
                                    }
                                }else{
                                    $a_v = "'".$filter[$k].' '.$filter['_DTIME_']['H'][$k].':'.$filter['_DTIME_']['M'][$k].':00'."'";
                                    $where[] = $tPre.$k.$this->_inner_getFilterType($filter['_'.$k.'_search'],$a_v);
                                }
                        }elseif(!is_array($cols[$k]['type']) && ($cols[$k]['type']=='money'||$cols[$k]['type']=='number'||$cols[$k]['type']=='float'||strpos($cols[$k]['type'],'decimal')!==false) && $filter['_'.$k.'_search']){
                           if($filter['_'.$k.'_search']=='between'){
                                $a_v = array($filter[$k.'_from'],$filter[$k.'_to']);
                                if(!empty($filter[$k.'_from'])&&empty($filter[$k.'_to'])){
                                    $_str = $this->_inner_getFilterType('bthan',$a_v[0]);
                                    if($_str !== null && $_str !== ''){ $where[] = $tPre.$k.$_str; }
                                }elseif(empty($filter[$k.'_from'])&&!empty($filter[$k.'_to'])){
                                    $_str = $this->_inner_getFilterType('lthan',$a_v[1]);
                                    if($_str !== null && $_str !== ''){ $where[] = $tPre.$k.$_str; }
                                }elseif(!empty($filter[$k.'_from'])&&!empty($filter[$k.'_to'])){
                                    $_str = $this->_inner_getFilterType($filter['_'.$k.'_search'],$a_v);
                                    if($_str !== null && $_str !== ''){ $where[] = str_replace('{field}',$tPre.$k,$_str); }
                                }
                            }else{
                                $_str = $this->_inner_getFilterType($filter['_'.$k.'_search'],$v);
                                if($_str !== null && $_str !== ''){ $where[] = $tPre.$k.$_str; }
                            }
                        }else if(isset($cols[$k]['filtertype'])&&isset($filter['_'.$k.'_search'])){
                                $_str = $this->_inner_getFilterType($filter['_'.$k.'_search'],$v);
                                if($_str !== null && $_str !== ''){ $where[] = $tPre.$k.$_str; }
                        }else if(isset($cols[$k]['searchtype'])&&!isset($filter['object_filter'])){
                                $_str = $this->_inner_getFilterType($cols[$k]['searchtype'],$v);
                                if($_str !== null && $_str !== ''){ $where[] = $tPre.$k.$_str; }
                        }else if(substr($k,0,1)!='_'){
                            if($k!='object_filter'){
                                if($cols[$k]['type']=='region'){
                                    if(isset($v))
                                    $v=explode(':',$v);
                                    unset($v[2]);
                                    $v=implode(':',$v);
                                    $where[] = $tPre.$k.' like \''.$v.'%\'';
                                }
                                elseif(is_array($v)){
                                    foreach($v as $m){
                                        if($m!=='_ANY_' && $m!=='' && $m!='_ALL_'){
                                            $ac[] = $cols[$k]['fuzzySearch']?($tPre.$k.' like \'%'.$m.'%\''):($tPre.$k.'=\''.$m.'\'');
                                        }else{
                                            $ac = array();
                                            break;
                                        }
                                    }
                                    if(count($ac)>0){
                                        $where[] = '('.implode($ac,' or ').')';
                                    }
                                }elseif(isset($v)){
                                    $where[] = $tPre.$k.'=\''.$v.'\'';
                                }
                            }
                        }
                    }
            }
        }
        return implode(' AND ', $where);
    }


    function _inner_getFilterType($type,$var,$force=true){
        if(!$this->use_like && !is_array($var) && $force){
            $type = 'nequal';
        }
        if(!in_array($type, self::$allowedFilterTypes, true)){
            return null;
        }
        // Unquoted compare ops: numbers or strict quoted datetime only (CNVD-2026-42947; keep TIMESTAMP filters).
        $numericTypes = array('than','lthan','sthan','bthan','between');
        if(in_array($type, $numericTypes, true)){
            if($type === 'between'){
                if(!is_array($var) || !isset($var[0]) || !isset($var[1])){
                    return null;
                }
                $left = $this->normalizeUnquotedCompareValue($var[0]);
                $right = $this->normalizeUnquotedCompareValue($var[1]);
                if($left === null || $right === null){
                    return null;
                }
                $var = array($left, $right);
            }else{
                $normalized = $this->normalizeUnquotedCompareValue($var);
                if($normalized === null){
                    return null;
                }
                $var = $normalized;
            }
        }
        $FilterArray= array('than'=>' > '.$var,
                            'lthan'=>' < '.$var,
                            'nequal'=>' = \''.$var.'\'',
                            'noequal'=>' <> \''.$var.'\'',
                            'tequal'=>' = \''.$var.'\'',
                            'sthan'=>' <= '.$var,
                            'bthan'=>' >= '.$var,
                            'has'=>' like \'%'.$var.'%\'',
                            'head'=>' like \''.$var.'%\'',
                            'foot'=>' like \'%'.$var.'\'',
                            'nohas'=>' not like \'%'.$var.'%\'',
                            'between'=>' {field}>='.$var[0].' and '.' {field}<'.$var[1],
                            'betweenstr'=>' {field}>="'.$var[0].'" and '.' {field}<"'.$var[1].'"',
                            'in' =>" in ('".implode("','",(array)$var)."') ",
                            'notin' =>" not in ('".implode("','",(array)$var)."') ",
                            'findinset' =>" FIND_IN_SET( '". $var."',{field}) ",
                            );
        return $FilterArray[$type];

    }
}
