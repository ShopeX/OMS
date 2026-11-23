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
 * rdkafka对象
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class base_queue_rdkafka {

    /**
     * rakafka生产者连接
     *
     * @var OBJECT
     **/
    private $__rakafka_producer_connect;

    /**
     * 消费者连接
     *
     * @var string
     **/
    private $__rakafka_consumer_connect;

    /**
     * 主题
     *
     * @var ARRAY
     **/
    private $__rakafka_topic;

    /**
     * 服务地址
     *
     * @var ARRAY
     **/
    private $_rakafka_server;

    public function set_server($server)
    {
        // 如果当前的地址与server不同
        if ($server != $this->_rakafka_server) {
            unset($this->__rakafka_producer_connect,$this->__rakafka_consumer_connect,$this->__rakafka_topic);
        }

        $this->_rakafka_server = $server;

        return $this;
    }    

    /**
     * 配置参数验证
     *
     * @return void
     * @author 
     **/
    private function __configValid()
    {
        if (!$this->_rakafka_server) {
            return false;
        }

        return true;
    }

    /**
     * 连接 rdkafka 生产者 
     * KAFKA集群服务器
     *
     * @param $config Array 连接参数 
     */
    private function __connectProducer() {

        if ($this->__configValid()) {
            if ($this->__rakafka_producer_connect) return true;

            $conf = new RdKafka\Conf();
            $conf->set('bootstrap.servers', $this->_rakafka_server);

            $this->__rakafka_producer_connect = new RdKafka\Producer($conf);

            $this->__rakafka_producer_connect->addBrokers($this->_rakafka_server);

            return true;
        }

        return false;
    }


    /**
     * 连接 rdkafka 消费者
     * KAFKA集群服务器
     *
     * @return void
     * @author 
     **/
    private function __connectConsumer()
    {
        if ($this->__configValid()) {
            
            if ($this->__rakafka_consumer_connect) return true;

            $this->__rakafka_consumer_connect = new RdKafka\Consumer();

            $this->__rakafka_consumer_connect->addBrokers($this->_rakafka_server);

            return true;
        }

        return false;
    }

    /**
     * 生产主题
     *
     * @return void
     * @author 
     **/
    private function __createProducerTopic($topic)
    {
        if ($this->__connectProducer()) {
            
            if (!$this->__rakafka_topic['producer'][$topic]){

            $topicConf = new RdKafka\TopicConf();
            $topicConf->set("message.timeout.ms", 3e3);

            $this->__rakafka_topic['producer'][$topic] = $this->__rakafka_producer_connect->newTopic($topic,$topicConf);  
            }

            return $this->__rakafka_topic['producer'][$topic];
        } 

        return false;
    }


    /**
     * 消费主题
     *
     * @return void
     * @author 
     **/
    private function __createConsumerTopic($topic)
    {
        if ($this->__connectConsumer()) {
            
            if (!$this->__rakafka_topic['consumer'][$topic]){
                $this->__rakafka_topic['consumer'][$topic] = $this->__rakafka_consumer_connect->newTopic($topic);  
            }

            return $this->__rakafka_topic['consumer'][$topic];
        } 

        return false;
    }   

    /**
     * 向对列提交信息 
     *  
     * @param $message String 信息内容体 
     * @param $topic String  
     * @return boolean 
     */
    public function publish($message, $topic) {

        if ($topicObj = $this->__createProducerTopic($topic)) {
            
            $topicObj->produce(RD_KAFKA_PARTITION_UA, 0, $message);

            if (defined('API_RAKAFKA_FLUSH') && constant('API_RAKAFKA_FLUSH')) {
                $this->__rakafka_producer_connect->flush(1000);
            }

            return true;
        }

        return false;
    }

    /**
     * 消费
     *
     * @return void
     * @author 
     **/
    public function consume($topic,$callable)
    {
        if ($topicObj = $this->__createConsumerTopic($topic)) {
            
            $topicObj->consumeStart(0, RD_KAFKA_OFFSET_BEGINNING);

            while (true) {
                $message = $topicObj->consume(0, 1000);
                switch ($message->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        
                        call_user_func($callable,$message); 

                        break;
                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        echo "No more messages; will wait for more\n";
                        break;
                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        echo "Timedout\n";
                        break;
                    default:
                        throw new Exception($message->errstr(), $message->err);
                        break;
                }
            }

            return true;
        }

        return false;
    }
}