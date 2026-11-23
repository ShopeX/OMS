<?php
require_once(dirname(__FILE__) .'/config.php');
cachemgr::init(false);
echo "regulation-apply begin(".date('Y-m-d H:i:s',time()).")...\n";
kernel::single('inventorydepth_logic_stock')->start();

echo "regulation-apply end(".date('Y-m-d H:i:s',time()).")...\n";
