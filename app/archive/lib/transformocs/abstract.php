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

abstract class archive_transformocs_abstract
{
  
        
    static $productocs = array();
    static $memberocs = array();
    public function __construct($config){
                                                         
        $this->db = kernel::database();
       
        $this->conn = mysql_pconnect($config[0], $config[1], $config[2])
        or die ('Not connected : ' . mysql_error());
        mysql_select_db($config[3], $this->conn) or die ('Can\'t use foo : ' . mysql_error());

        mysql_query("set names 'utf8'");
    }
        
        /**
         * 转换货品编号
         * @param  
         * @return  
         * @access  public
         * @author sunjing@shopex.cn
         */
        function transGoods($bn)
        {
            //转换货品
            //
            $productObj = app::get('ome')->model('products');
            $product = $productObj->dump(array('bn'=>$bn),'product_id,goods_id,title,bn,name,spec_info,price,cost,mktprice,weight,barcode,unit,spec_desc,uptime,last_modified,disabled,marketable,sku_property,alert_store,limit_day,real_store_lastmodify,max_store_lastmodify,taobao_sku_id,picurl,type');

            if ($product) {
                return $product;
            }else{
                
                $productquery = mysql_query("SELECT product_id,goods_id,title,bn,name,spec_info,price,cost,mktprice,weight,barcode,unit,spec_desc,uptime,last_modified,disabled,marketable,sku_property,alert_store,limit_day,real_store_lastmodify,max_store_lastmodify,taobao_sku_id,picurl,type FROM sdb_ome_products WHERE bn='".$bn."'",$this->conn);
                $product = mysql_fetch_assoc($productquery);

                if ($product) {
                    $goods_id = $product['goods_id'];
                    $keys = 'bn,name,goods_type,brief,intro,barcode,mktprice,cost,price,marketable,weight,unit,score_setting,score,spec_desc,params,uptime,downtime,last_modify,disabled,p_order,d_order';
                    $goodsquery = mysql_query("SELECT ".$keys." FROM sdb_ome_goods WHERE goods_id=".$goods_id,$this->conn);
                    $goods = mysql_fetch_assoc($goodsquery);
                    //转换成存储格式
                    //查看goods是否存在
                    $goods_bn = $goods['bn'];
                    $goods_detail = $this->db->selectrow("SELECT goods_id FROM sdb_ome_goods WHERE bn='".$goods_bn."'");
                    if ($goods_detail) {
                        $erp_goods_id = $goods_detail['goods_id'];
                    }else{
                        
                        $insertkey = explode(',',$keys);
                        $values = array();
                        foreach ($insertkey as $insertvalue ) {
                            
                            $values[] = "'".$goods[$insertvalue]."'";
                        }
                        $values = "(".implode(',',$values).")";
                        $insertsql = "INSERT INTO sdb_ome_goods(".$keys.") VALUE".$values;

                        $this->db->exec($insertsql);
                        $erp_goods_id = $this->db->lastinsertid();
                    }
                    $product_keys = 'goods_id,title,bn,name,spec_info,price,cost,mktprice,weight,barcode,unit,spec_desc,uptime,last_modified,disabled,marketable,sku_property,alert_store,limit_day,real_store_lastmodify,max_store_lastmodify,taobao_sku_id,picurl,type';
                    $insertproductkeys = explode(',',$product_keys);
                    $productvalues = array();
                    foreach ($insertproductkeys as $product_value ) {
                        if ($product_value == 'goods_id') {
                            $productvalues[] = '\''.$erp_goods_id.'\'';
                        }else{
                            $productvalues[] = "'".$product[$product_value]."'";
                        }
                        
                    }
                    $productvalues = "(".implode(',',$productvalues).")";
                    $insertprodutsql = "INSERT INTO sdb_ome_products(".$product_keys.") VALUE".$productvalues;

                    $this->db->exec($insertprodutsql);
                    $product_id= $this->db->lastinsertid();
                    $products = array('product_id'=>$product_id,'goods_id'=>$erp_goods_id);
                }else{
                    $products = array('product_id'=>0,'goods_id'=>0); //找不到的货品当0处理?
                }
                
                return $products;
            }
            
            
        }

         /**
         *根据货号获取ID.
         * @param  
         * @return  
         * @access  public
         * @author sunjing@shopex.cn
         */
        function getProduct($bn)
        {
            if (!self::$productocs[$bn]) {
                $products = $this->transGoods($bn);
                
                self::$productocs[$bn] = $products;
            }
            return self::$productocs[$bn] ? self::$productocs[$bn] : 0;

        }

        /**
         * 转换会员
         * @param   
         * @return  
         * @access  public
         * @author sunjing@shopex.cn
         */
        function transMember($member_id)
        {
           
            $memberObj = app::get('ome')->model('members');
            $member = $memberObj->dump(array('unreadmsg'=>$member_id),'*');
            $erpmember_id = 0;
            if (!$member) {
                $query = mysql_query("SELECT * FROM sdb_ome_members WHERE member_id=".$member_id,$this->conn);
                if ($query) {
                    $members = mysql_fetch_assoc($query);
                    if ($members) {
                        $keys = 'uname,name,lastname,firstname,password,area,addr,mobile,tel,email,zip,order_num,b_year,b_month,b_day,sex,addon,wedlock,education,vocation,interest,regtime,state,pay_time,pw_answer,pw_question,custom,cur,lang,unreadmsg,disabled,remark,is_offical_member,is_customer';
                        $insertkey = explode(',',$keys);
                        $values = array();
                        foreach ($insertkey as $insertvalue ) {
                            if ($insertvalue == 'unreadmsg') {
                                $insertvalue='member_id';
                            }
                            $values[] = "'".$members[$insertvalue]."'";
                        }
                        $values = "(".implode(',',$values).")";
                        $insertsql = "INSERT INTO sdb_ome_members(".$keys.") VALUE".$values;
                        $this->db->exec($insertsql);
                        $erpmember_id = $this->db->lastinsertid();
                    }
                }
                
            }else{
                $erpmember_id = $member['member_id'];
            }
            return $erpmember_id;
        }

        
        
    public function getMemberid($member_id)
    {
        
        if (!self::$memberocs[$member_id]) {
            self::$memberocs[$member_id] = $this->transMember($member_id);
        }
        return self::$memberocs[$member_id];
    }

    function copyTables($keys,$datarow)
    {
        $order_key = explode(',',$keys);
        $values = array();
        foreach ($order_key as $ordervalue ) {
            $ordervalue = str_replace('`','',$ordervalue);
             $datavalue = $datarow[$ordervalue];
            if (is_string($datavalue)) {
                $datavalue = addslashes($datavalue);
            }
            $values[] = "'".$datavalue."'";
        }
        $values = "(".implode(',',$values).")";
        return $values;
    }


} 


?>