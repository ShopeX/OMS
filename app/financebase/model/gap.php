<?php
/**
 * 差异类型模型类
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_mdl_gap extends dbeav_model
{
    public function isExist($filter,$id = 0)
    {
        $sql = "SELECT id FROM ".$this->table_name(true)." WHERE ".$this->filter($filter);
        $id and $sql.=" and id <> ".$id;
        return $this->db->selectrow($sql) ? true : false;
    }
}
