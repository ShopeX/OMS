<?php
/**
 * 标签价
 * @author sunjing@shopex.cn
 * @version 1.0
 */
class goldentra_crontab_pricetag{



    public function process($params, &$error_msg=''){

        $this->updateDjPrice();

        //取计价类有变化的

        //取天猫店铺
        $filter = array(
            'shop_type' =>'taobao',
            'filter_sql' =>'{table}node_id is not null and {table}node_id !=""',
        );
        $shops = app::get('ome')->model('shop')->getList('shop_id,shop_bn,node_type',$filter);

        foreach($shops as $shop){
            $shop_bn = $shop['shop_bn'];
            $shop_id = $shop['shop_id'];
            $products = $this->getChgProducts($shop_bn);

            if ($products){
                foreach($products as $gold_price_data){

                    $pricedimension_code = $gold_price_data['pricedimension_code'];
                    $gold_price = $gold_price_data['gold_price'];
                    $price_date = $gold_price_data['price_date'];
                    $this->updatePricetag($shop_id, $pricedimension_code, $gold_price, $price_date);
                }
            }
        }

        $date = date('Y-m-d H:i:s');
        base_kvstore::instance('material_shop_goods')->store('refresh_price_flag', 0);
        base_kvstore::instance('material_shop_goods')->store('refresh_price_last_time',$date);
    }

    public function getChgProducts($shop_bn)
    {
        $time = strtotime(date('Y-m-d'));

        //更新店铺商品】取最新的一条「每日金价」进行计算商品价格
        $products = kernel::single('goldentra_daily_goldprice')->getDailyGoldPrice($shop_bn, $time);

        if ($products) {
            return $products;
        }

        return false;
    }


    private function updatePricetag($shop_id, $pricedimension_code, $price, $price_date)
    {
        $materialObj = app::get('material')->model('basic_material');
        $logObj =app::get('ome')->model('operation_log');
        $material_detail = $materialObj->getlist('bm_id,process_cost,weight',array('pricedimension_code'=>$pricedimension_code,'price_method'=>'1'));
        $price_date = date('Y-m-d H:i:s', $price_date);

        $goodsObj = app::get('material')->model('shop_goods');
        foreach($material_detail as $material){
            $bm_ids = $material['bm_id'];
            $today_price = $material['weight']*$price;

            $price_tag = kernel::single('eccommon_math')->number_plus(array($today_price,$material['process_cost']));//标签价=克重*当日金价+工费

            //取销售物料
            $sales_material = $materialObj->db->select("SELECT s.sales_material_bn FROM sdb_material_sales_basic_material as bs LEFT JOIN sdb_material_sales_material as s ON bs.sm_id=s.sm_id WHERE bs.bm_id in(".$bm_ids.")");

            $sales_material_bns = array_map('current',$sales_material);

            $goods_sku_list = $materialObj->db->select("SELECT id,price,shop_goods_id,price,sku_bn FROM sdb_material_shop_goods_sku WHERE shop_id='".$shop_id."' AND sku_bn in('".implode('\',\'',$sales_material_bns)."')");


            foreach($goods_sku_list as $v){

                $materialObj->db->exec("UPDATE sdb_material_shop_goods_sku SET price=".$price_tag.",price_state='2' WHERE id=".$v['id']);
                $shop_goods = $goodsObj->dump(array('id'=>$v['shop_goods_id']),'goods_id');
                //
                $goods_detail = $materialObj->db->selectrow("SELECT min(price) as min_price FROM  sdb_material_shop_goods_sku WHERE shop_goods_id=".$v['shop_goods_id']);

                $min_price = $goods_detail['min_price'];

                //加日志

                //bn shop_goods_sku 里的goods_id 是shop_goods里的goods_id
                //$materialObj->db->exec("UPDATE sdb_material_products SET price=".$price_tag." WHERE sku_bn='".$v['sku_bn']."' AND goods_id=".$shop_goods['goods_id']);
                //$materialObj->db->exec("UPDATE sdb_material_goods SET price=".$min_price." WHERE  goods_id=".$shop_goods['goods_id']);
                $materialObj->db->exec("UPDATE sdb_material_shop_goods SET upload_state='2',price_state='2',price=".$min_price." WHERE id=".$v['shop_goods_id']);
                $msg = $v['sku_bn'].'商品价格'.$v['price'].'更新为:'.$price_tag."(重量:".$material['weight'].",金价:".$price.",加工费:".$material['process_cost'].")";
                $msg .= sprintf('(每日金价日期:%s)', $price_date);
                $logObj->write_log('goods_upload_price@ome', $v['shop_goods_id'], $msg);
            }


        }
    }

    const limit = 100;

    private function updateDjPrice()
    {
        $count_sql = <<<EOF
SELECT
	count( * ) AS count 
FROM
	sdb_material_shop_goods_sku AS msgs
	LEFT JOIN sdb_material_basic_material AS mbm ON mbm.material_bn = msgs.sku_bn 
WHERE
	mbm.price_method = 2
EOF;

        $data = kernel::database()->selectrow($count_sql);

        $count = $data['count'];
        $cursor = 0;

        $sql_tmpl = <<<EOF
SELECT
	msgs.*,
	mbme.retail_price
FROM
	sdb_material_shop_goods_sku AS msgs
	LEFT JOIN sdb_material_basic_material AS mbm ON mbm.material_bn = msgs.sku_bn 
	LEFT JOIN sdb_material_basic_material_ext AS mbme ON mbme.bm_id = mbm.bm_id 
WHERE
	mbm.price_method = 2
EOF;


        while ($cursor < $count) {

            $sql = sprintf('%s LIMIT %s, %s', $sql_tmpl, $cursor, self::limit);
            $items = kernel::database()->select($sql);
            $this->runUpdateDjPrice($items);
            $cursor += self::limit;
        }
    }


    private function runUpdateDjPrice($items)
    {
        foreach ($items as $item) {

            $id = $item['id'];
            $sku_bn = $item['sku_bn'];
            $shop_goods_id = $item['shop_goods_id'];

            //需要更新
            if ($item['price'] != $item['retail_price']) {

                $update_shop_goods_sku_data = array(
                    'price' => $item['retail_price'],
                    'price_state' => '2'
                );

                $where_shop_goods_sku_data = array(
                    'id' => $id
                );
                app::get('material')->model('shop_goods_sku')->update($update_shop_goods_sku_data, $where_shop_goods_sku_data);

                //找出价格最低的sku
                $find_min_price_sql = <<<EOF
SELECT
	min( price ) AS min_price 
FROM
	sdb_material_shop_goods_sku 
WHERE
	shop_goods_id = "$shop_goods_id"
EOF;
                $find_min_price_data = kernel::database()->selectrow($find_min_price_sql);
                $min_price = $find_min_price_data['min_price'];


                $update_shop_goods_data = array(
                    'price' => $min_price,
                    'price_state' => '2',
                    'upload_state' => '2'
                );

                $where_shop_goods_data = array(
                    'id'=> $shop_goods_id
                );
                app::get('material')->model('shop_goods')->update($update_shop_goods_data, $where_shop_goods_data);


                $msg = sprintf('货号:%s价格更新,%s->%s', $sku_bn, $item['price'], $item['retail_price']);
                app::get('ome')->model('operation_log')->write_log('goods_upload_price@ome', $shop_goods_id, $msg);
            }
        }
    }
}
