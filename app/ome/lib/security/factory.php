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
 * 加解密工厂 淘宝安全-数据加密
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */

class ome_security_factory
{
    function __construct()
    {
    
    }

    /**
     * 加密
     *
     * @param string $val 值
     * @param string $type 字段 
     * @return void
     * @author 
     **/
    public function encrypt($val,$type)
    {
        return $val;
    }

    /**
     * 解密
     *
     * @param string $val 值
     * @param string $type 字段
     * @param string $node_id 节点
     * @return void
     * @author 
     **/
    public function decrypt($val,$type,$node_id)
    {
        return $val;
    }

    /**
     * 判断字段是否加密
     *
     * @param string $val 值
     * @param string $type 字段
     * @return void
     * @author 
     **/
    public function isEncryptData($val,$type)
    {
        try {
            return $this->isLocalEncryptData($val,$type);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 加密单条数据
     *
     * @param string $val 值
     * @param string $type 字段类型（例如：phone、mobile、address、ship_name）
     * @return string
     **/
    public function encryptPublic($val, $type, $isMust=false)
    {
        // 检测数据如果是平台加密数据,则返回原数据
        if(!$isMust && kernel::single('ome_security_hash')->get_code() == substr($val, -5)) {
            return $val;
        }
        
        try {
            return $this->localEncryptPublic($val, $type);
        } catch (Exception $e) {
            return $val;
        }
    }
    
    /**
     * 单条数据解密
     *
     * @param string $val 值
     * @param string $type 字段类型（例如：phone、mobile、address、ship_name）
     * @return string
     **/
    public function decryptPublic($val, $type)
    {
        try {
            if ($this->isLocalEncryptData($val, $type)) {
                return $this->localDecryptPublic($val, $type);
            }
            return $val;
        } catch (Exception $e) {
            return $val;
        }
    }
    
    /**
     * 查询单条加密数据
     *
     * @param $val
     * @param $type
     * @param $node_id
     * @return mixed|string|null
     */
    public function search($val,$type, $node_id=null)
    {
        // 兼容搜索：同一个字段可能存在“明文/密文”两种存储形态。
        // 调用方会用 (字段 LIKE/IN 明文) OR (字段 LIKE/IN 密文) 的方式组合查询。
        if ($val === null || $val === '') {
            return $val;
        }

        // 若输入本身已是本地密文，直接返回用于匹配
        if ($this->isLocalEncryptData($val, $type)) {
            return $val;
        }

        // 输入为明文：生成对应密文用于匹配已加密数据
        return $this->localEncryptPublic($val, $type);
    }
    
    /**
     * 定义本地hash code
     *
     * @return string
     */
    public function get_local_code()
    {
        return '@local_hash';
    }
    
    /**
     * 返回原始数据
     *
     * @return void
     **/
    public function getLocalOriginText($text)
    {
        if ($this->get_local_code() == substr($text, -11)) {
            $text = substr($text, 0, -11);
        }
        return $text;
    }
    
    /**
     * 本地加密单条数据
     *
     * @param $val 需要被加密的数据
     * @param $type 加密数据的类型（例如：phone、mobile、address、ship_name）
     * @return string
     */
    public function localEncryptPublic($val, $type='')
    {
        // 加密密钥
        //@todo：使用系统config/目录下certi.php证书文件中的：token
        $encryption_key = base_certificate::get('token');
        
        // 初始化向量：为支持“对同一明文进行可重复加密并用于搜索”，这里使用确定性IV。
        // 注意：这会让相同明文得到相同密文（可搜索），但会泄露“是否相等”的信息。
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $seed = $encryption_key . '|' . (string)$type;
        $iv = substr(hash('sha256', $seed, true), 0, $iv_length);
        
        // 使用AES-256-CBC加密算法加密手机号
        $encrypted_phone_number = openssl_encrypt($val, 'aes-256-cbc', $encryption_key, 0, $iv);
        
        // 返回IV和加密后的数据，以便后续解密
        return base64_encode($iv . $encrypted_phone_number) . $this->get_local_code();
    }
    
    /**
     * 本地单条数据解密
     *
     * @param $encrypted_data 已经被加密的数据
     * @param $type 加密数据的类型（例如：phone、mobile、address、ship_name）
     * @return string
     */
    public function localDecryptPublic($encrypted_data, $type='')
    {
        // 加密密钥
        //@todo：使用系统config/目录下certi.php证书文件中的：token
        $encryption_key = base_certificate::get('token');
        
        // check
        if(empty($encrypted_data) || !is_string($encrypted_data)){
            return $encrypted_data;
        }
        
        // 去除本地hashcode
        $encrypted_data = (string)$this->getLocalOriginText($encrypted_data);
        
        // 将base64编码的数据解码
        $ciphertext = base64_decode($encrypted_data, true);
        if ($ciphertext === false) {
            return $encrypted_data;
        }
        
        // 获取IV（初始化向量），对于AES-256-CBC来说，IV长度为16字节
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($ciphertext, 0, $iv_length);
        
        // 获取加密信息
        $get_encrypted_data = substr($ciphertext, $iv_length);
        
        // 使用AES-256-CBC解密算法解密
        return openssl_decrypt($get_encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
    }
    
    /**
     * 判断数据是否被加密
     *
     * @param $data 需要判断的数据
     * @param $type 加密数据的类型（例如：phone、mobile、address、ship_name）
     * @return bool 返回true表示已加密，false表示未加密
     */
    public function isLocalEncryptData($data, $type='')
    {
        if(empty($data) || !is_string($data)){
            return false;
        }
        
        if($this->get_local_code() == substr($data, -11)) {
            return true;
        }
        
        return false;
    }
}