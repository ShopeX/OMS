<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
class desktop_system_backup {
    private $header = "<?php exit; ?>";  //追加头文件内容
    private $tar_ext = '.zip';  //下载扩展名
    private $backdir; //备份目录
    private $prefilename = 'multibak_';
    
    
    
    
    public function __construct() {
        $this->backdir = DATA_DIR.'/backup';
    }
    
    public function get($var='') {
        if(!$var) return false;
        return $this->$var;
    }
    
    public function getList(){

        $dir = $this->backdir;
        if(!is_dir($dir))return false;
        $handle=opendir($dir);

        if ($handle = opendir($dir)) {
            $return = array();
            while (false !== ($file = readdir($handle))) {
                if($file[0]=='.') continue;
                if( is_file($dir.'/'.$file) ){
                    //备份时间取文件名字
                    $array = explode( '.',$file );
                    $temp = array();
                    if( count($array)==3 ) {
                        $temp['app']  = $array[0];
                        $nfilename = $array[1];
                    } else {
                        $nfilename = $array[0];
                        $temp['app'] = '全局备份';
                    }
                    $datetime = ltrim($nfilename,$this->prefilename);
                    
                    if( strlen($datetime)!=14 ) continue;
                    $datetime = mktime( substr($datetime,8,2) , 
                                        substr($datetime,10,2) , 
                                        substr($datetime,12,2) , 
                                        substr($datetime,4,2) , 
                                        substr($datetime,6,2) , 
                                        substr($datetime,0,4) 
                                    );
                    
                    $temp['name'] = str_replace('.php', $this->tar_ext, $file);
                    $temp['size'] = filesize($dir .'/'. $file); 
                    $temp['time'] = $datetime;
                    
                    
                    
                    if( end($array)==trim($this->tar_ext,'.') ) {
                        $this->convertFile( $dir,$file );
                        @unlink($dir.'/'.$file);
                    }
                    $return[] = $temp;
                }
            }
            krsort($return);
            closedir($handle);
        }
        return $return;
    }
    
    public function uninstall_backup( $app ) {
        $oMysqlDump = kernel::single("desktop_system_mysqldumper");
        $backdir = $this->backdir;
        $dirname = date("Ymdhis");
        is_dir($backdir) or mkdir($backdir, 0755, true);
        $oMysqlDump->multi_dump_sdf( $app,$backdir .'/' . $dirname );
        $this->create_tar( $backdir,$dirname,$app );
    }
    
    
    public function start_backup_sdf(&$params,&$nexturl){ 
        set_time_limit(0);
        header("Content-type:text/html;charset=utf-8");
        $app = $params['appname'];
        $dirname = $params['dirname'];
        #$cols = $params['cols'];
        #$model = $params['model'];
        #$startid = $params['startid'];
        
        $oMysqlDump = kernel::single("desktop_system_mysqldumper");
        $backdir = $this->backdir;
        $oMysqlDump->tableid = $tableid;
        $oMysqlDump->startid = $startid;
        is_dir($backdir) or mkdir($backdir, 0755, true);
        is_dir($backdir .'/' . $dirname) or mkdir($backdir .'/' . $dirname, 0755, true);
        $app = $oMysqlDump->multi_dump_sdf( $app,$backdir .'/' . $dirname );

        if($app){
            $nexturl = "index.php?app=desktop&ctl=backup&act=backup_sdf&appname=$app&dirname=$dirname";
			$params['app'] = $app;
        } else {
            return $this->create_tar( $backdir,$dirname );
        }
        
        return false;
    }
    
    
    
    /**
     * 生成tar包
     */
    private function create_tar( $backdir,$dirname,$app='' ) {
        $tar = kernel::single("base_tar");
        $dir = $backdir. '/' . $dirname;
        chdir($dir);
        $size = $this->add_tar( $dir,$tar );
        $size = ceil($size/1024/1024) + 128;
        ini_set("memory_limit", "{$size}M");
        
        $tar->filename = ($app?$app.'.':''). $this->prefilename.$dirname.$this->tar_ext;
        $tar->saveTar();
        @copy($dir . '/' . $tar->filename, $backdir.'/'.$tar->filename);
        
        

        $this->convertFile($backdir, $tar->filename, '.php');
        if(is_resource($tar->tar_file)) {
            fclose($tar->tar_file);
        }
        @unlink($tar->filename);
        @unlink($backdir.'/'.$tar->filename);
        $dir = $backdir. '/' . $dirname;
        chdir($dir);
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if(is_file($file)) {
                    @unlink($file);
                }
            }
            closedir($handle);
        }
        utils::remove_p($dir);
        
        return true;
    }
    
    private function add_tar( $dir,&$tar,$parentdir='' ) {
        $size = 0;
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if($file[0]!='.') {
                    if( is_file($dir.'/'.$file) ) {
                        $size += filesize($parentdir.$file);
                        $tar->addFile($parentdir.$file);
                    } else {
                        $tar->addDirectory($parentdir.$file);
                        $size += $this->add_tar( $dir.'/'.$file, $tar, $parentdir.$file.'/' );
                    }
                }
            }
            closedir($handle);
        }
        return $size;
    }
    
    

    public function download($file) {
        $dir = DATA_DIR.'/backup/';
        $file = str_replace($this->tar_ext, '.php', $file);
        if(!file_exists($dir . $file)){
            return false;
        }
        
        $etag = md5_file($dir . $file);
        header('Etag: '.$etag);
        if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag){
            header('HTTP/1.1 304 Not Modified',true,304);
            exit(0);
        }else{
            set_time_limit(0);
            ob_end_clean();
            $content_size = filesize($dir . $file) - strlen($this->header);
            $filename = substr($file, 0, strrpos($file, '.')) . $this->tar_ext;
            header("Content-Disposition: attachment; filename=\"$filename\"");        
            header("Content-Length: " . $content_size);

            $handle = fopen($dir . $file, "r");
            $flag = false;
            while($buffer = fread($handle,102400)){
                if(!$flag) $buffer = str_replace($this->header, '', $buffer);
                echo $buffer;
                $flag = true;
                flush();
            }
            fclose($handle);
            
            
        }
    }
    
    
    
    
    
    
    
    
    /**
     * Whether a backup file name is confined to DATA_DIR/backup (CNVD-2026-43974).
     *
     * @param string $name
     * @return bool
     */
    public function isSafeBackupName($name){
        if(!is_string($name) || $name === ''){
            return false;
        }
        if(strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, '\\') !== false){
            return false;
        }
        if($name !== basename($name)){
            return false;
        }
        return (bool) preg_match('/^[A-Za-z0-9_.-]+$/', $name);
    }

    /**
     * Resolve a path under the backup directory; reject traversal.
     *
     * @param string $relativeName
     * @return string|false absolute path when safe
     */
    private function resolveBackupPath($relativeName){
        if(!$this->isSafeBackupName($relativeName)){
            return false;
        }
        $base = realpath($this->backdir);
        if($base === false){
            if(!is_dir($this->backdir)){
                @mkdir($this->backdir, 0755, true);
            }
            $base = realpath($this->backdir);
        }
        if($base === false){
            return false;
        }
        $candidate = $this->backdir . '/' . $relativeName;
        $real = realpath($candidate);
        // File may not exist yet for convert output; validate dirname + basename.
        if($real === false){
            $parent = realpath(dirname($candidate));
            if($parent === false || strpos($parent, $base) !== 0){
                return false;
            }
            if(basename($candidate) !== $relativeName && basename($candidate) !== basename($relativeName)){
                // still require leaf name has no traversal
                if(!$this->isSafeBackupName(basename($candidate))){
                    return false;
                }
            }
            return $candidate;
        }
        if(strpos($real, $base) !== 0){
            return false;
        }
        return $real;
    }

    function recover($sTgz,&$vols,$fileid,&$pre_app){
        if(!$this->isSafeBackupName($sTgz)){
            return false;
        }
        $prefix = substr($sTgz,0,23);
        $sTmpDir = DATA_DIR.'/tmp/'.md5($sTgz).'/';
        $sTgz = str_replace($this->tar_ext, '.php', $sTgz);
        if(!$this->isSafeBackupName($sTgz)){
            return false;
        }
        
        
        if($fileid==1){
            $vols = 0;
            $rTar = kernel::single("base_tar");
            is_dir($sTmpDir) or mkdir($sTmpDir, 0755, true);
            $file = $this->resolveBackupPath($sTgz);
            if($file === false || !is_file($file)){
                return false;
            }
            
            $size = filesize($file);
            $size = ceil($size/1024/1024) + 128;
            ini_set("memory_limit", "{$size}M");
            
            $newFile = $this->convertFile(DATA_DIR.'/backup/', $sTgz, $this->tar_ext);
            if($newFile === false){
                return false;
            }

            if($rTar->openTAR($newFile, null)){
                
                if(!$rTar->files) return false;
                foreach($rTar->files as $id => $aFile) {
                    if( substr($aFile['name'],0,4)=='sdf/') $vols++;
                    $sPath=$sTmpDir.$aFile['name'];
                    is_dir( dirname($sPath) ) or mkdir( dirname($sPath),0755,true );
                    file_put_contents($sPath,$rTar->getContents($aFile));
                    chmod($sPath,0755);
                }
            }

            $rTar->closeTAR();
            @unlink($newFile);
            return $this->comeback($sTmpDir, 1, $vols, $pre_app);
        }else{
            return $this->comeback($sTmpDir, $fileid, $vols, $pre_app);
        }
        
    }


    function comeback($sDir, $fileid=1, $vols, &$pre_app){
        $dir = $sDir;
        $sDir = $sDir .'/sdf';
        if(!is_dir($sDir)) return false;
        chdir($sDir);
        if($rHandle=opendir($sDir)){
            while(false!==($sFile=readdir($rHandle))){
                if($sFile[0]=='.')continue;
                $handle = fopen($sFile,'r');
                $app = substr($sFile, 0, strpos($sFile, '.'));

                $model = substr($sFile, strpos($sFile, '.')+1, -(strpos(strrev($sFile), '.') + 1));
                $model = strpos($model, '.') ? substr($model, 0, strpos($model, '.')) : $model;
                if(!$app || !$model) return false;
                if( app::get($app)->is_actived() ) { //APP安装了继续跑
                    $o = app::get($app)->model($model);

                    $this->app_update( $dir,$app,$pre_app,$model,$o );
                    
                    
                    $str = null;
                    while($sdf=$this->fgetline($handle)){
                        if(!is_array(@unserialize($sdf))){
                            $buffer .= $sdf;
                            $sdf = @unserialize($buffer);
                            if(!is_array($sdf)) continue;
                        }else{
                            $sdf = @unserialize($sdf);
                        }
                        if( $app=='base' && $model=='app_content' ) {
                            if( !$sdf['app_id'] ) continue;
                            if( !isset($arr_app_status[$sdf['app_id']]) )
                                $arr_app_status[$sdf['app_id']] = app::get($sdf['app_id'])->is_actived();
                            if( !$arr_app_status[$sdf['app_id']] ) continue;
                        }
                        
                        //删除kvstore主键 避免冲突 session
                        if( $app=='base' && $model=='kvstore' ) unset( $sdf['id'] );
                        
                        $return = @$o->insert($sdf);
                        
                        $buffer = null;
                    }
                    
                } else {
                    $this->show_message .= 'app: '.$app .' 没有安装!数据无法还原！<BR />';
                }
                fclose($handle);
                @unlink($sFile);
                $pre_app = $app;
                break;
            }
            
            closedir($rHandle);
            if( !$sFile ) {
                $this->app_update( $dir,$app,$pre_app );
                utils::remove_p($sDir);
            }
            if( $fileid==$vols ) {
                $this->post_comeback($dir);
                utils::remove_p($dir);
            }
        }
        
    }
    
    
    //////////////////////////////////////////////////////////////////////////
    //数据还原之后
    ///////////////////////////////////////////////////////////////////////////
    private function post_comeback($dir) {
        if($rHandle=opendir($dir.'/dbschema/')){
            while(false!==($app=readdir($rHandle))){
                if( $app[0]=='.' ) continue;
                if( !is_dir($dir.'/dbschema/'.$app) ) continue;
                #if( $handle=opendir($dir.'/dbschema/'.$app) ) {
                #    while(false!==($sFile=readdir($handle))){
                #        if( $sFile[0]=='.' ) continue;
                #        $tmp_model = substr($sFile,0,strpos($sFile,'.'));
                        //最后会修复表结构到最新  以下没有意义
                        #$dbschema_file = $dir.'/dbschema/'.$app.'/'.$sFile;
                        #if( $db[$tmp_model]['unbackup'] ) continue;
                        #$this->create_table( $pre_app,$tmp_model,$o,$dbschema_file );
                #    }
                #}
                if( !$this->dbtable )
                    $this->dbtable = kernel::single('base_application_dbtable');
                $this->dbtable->update($app);
            }
        }
    }
    private function app_update( $dir,$app,$pre_app,$model='',$o='' ) {
        if( !$app ) return false;
        $dbschema_file = $dir.'/dbschema/'.$app.'/'.$model.'.php';
        $dbschema_file_bak = $dir.'/dbschema/'.$app.'/'.$model.'.bak.php';;
        if( is_file($dbschema_file) ) {
            $this->create_table( $app,$model,$o,$dbschema_file );
            #unlink( $dbschema_file );
            rename($dbschema_file,$dbschema_file_bak);
        } else if(is_file($dbschema_file_bak)) {
            $this->create_table( $app,$model,$o,$dbschema_file_bak,false );
        }
    }
    
    /*
     * 创建数据表
     */
    private function create_table( $app,$model,$o,$dbschema_file,$createtable=true ) {
        if( !$app ) return false;
        if( !$model ) return false;
        require( $dbschema_file );
        if( !$this->dbtable )
            $this->dbtable = kernel::single('base_application_dbtable');
        
        $this->dbtable->target_app = app::get($app);
        
        //是否默认使用innodb
        $this->dbtable->_enable_innodb = 'YES';
        
        $this->dbtable->key = $model;
        $real_table_name = $this->dbtable->real_table_name();
        $define = $db[$model];
        $this->get_defined_dbsdf( $define );
        $this->dbtable->_define[$real_table_name] = $define;
        if( $createtable ) {
            $sql = $this->dbtable->get_sql("sdb_{$app}_{$model}");

            $o->db->exec("DROP TABLE IF EXISTS sdb_{$app}_{$model}");
            $o->db->exec($sql);
        } else {
        }
    }
    
    private function get_defined_dbsdf( &$define ) {
        foreach($define['columns'] as $k=>$v){
            if($v['pkey'])
                $define['idColumn'][$k] = $k;

            if($v['is_title'])
                $define['textColumn'][$k] = $k;

            if($v['in_list']){
                $define['in_list'][] = $k;
                if($v['default_in_list']){
                    $define['default_in_list'][] = $k;
                }
            }

            $define['columns'][$k] = $this->dbtable->_prepare_column($k, $v);
            if(isset($v['pkey']) && $v['pkey']){
                $define['pkeys'][$k] = $k;
            }

        }

        if(!$define['idColumn']){
            $define['idColumn'] = key($define['columns']);
        }elseif(count($define['idColumn'])==1){
            $define['idColumn'] = current($define['idColumn']);
        }

        if(!$define['textColumn']){
            $keys = array_keys($define['columns']);
            $define['textColumn'] = $keys[1];
        }elseif(count($define['idColumn'])==1){
            $define['textColumn'] = current($define['textColumn']);
        }
    }


   /**
    * 文件类型转换
    */
    private function convertFile($dir, $file, $type='.php') {
        // CNVD-2026-43974: only convert files inside the backup directory.
        if(!$this->isSafeBackupName($file)){
            return false;
        }
        $dir = rtrim($dir, '/') . '/';
        $backupRoot = realpath($this->backdir);
        $dirReal = realpath(rtrim($dir, '/'));
        if($backupRoot === false || $dirReal === false || strpos($dirReal, $backupRoot) !== 0){
            return false;
        }
        $new_file_name = substr($file, 0, -(strpos(strrev($file), '.')+1)) . $type;
        if(!$this->isSafeBackupName($new_file_name)){
            return false;
        }
        $newFile = $dir . $new_file_name;
        
        $flag = true;
        
        if($type==$this->tar_ext) {  //还原时从php转换成tar包
            $flag = false;
            //is_dir($dir .'/tmptar/') or mkdir($dir .'/tmptar/');
            //$newFile = $dir .'/tmptar/'. md5($newFile);
            if(file_exists($newFile)) { //如果是tar文件转换成php形式
                $this->convertFile( $dir,$new_file_name );
            }
        }
        
        
        if(file_exists($newFile)) {
            return $newFile;
        }

        $srcPath = $dir . $file;
        $srcReal = realpath($srcPath);
        if($srcReal === false || strpos($srcReal, $backupRoot) !== 0){
            return false;
        }

        $handle = fopen($newFile, 'x');
        if($handle === false){
            return false;
        }
        
        if($flag) {
            fwrite($handle, $this->header);
        }
        $src = fopen($srcPath, 'r');
        if($src === false){
            fclose($handle);
            @unlink($newFile);
            return false;
        }

        while (!feof($src)) {
            $contents = fgets($src);
            if(!$flag && strpos($contents, $this->header)!==false) $contents = substr($contents, strlen($this->header));
            if($contents) {
                fwrite($handle, $contents);
            }
            $flag = true;
        }
        fclose($src);
        fclose($handle);
        return $newFile;
    }









    function fgetline($handle){
        $buffer = fgets($handle, 4096);
        if (!$buffer){
            return false;
        }
        if(( 4095 > strlen($buffer)) || ( 4095 == strlen($buffer) && "\n" == $buffer[4094] )){
            $line = $buffer;
        }else{
            $line = $buffer;
            while( 4095 == strlen($buffer) && "\n" != $buffer[4094] ){
                $buffer = fgets($handle,4096);
                $line.=$buffer;
            }
        }
        return $line;
    }

    function removeTgz($sFile){
        #foreach($aTgz as $sFile){
            $pathinfo = pathinfo($sFile);
            @unlink(DATA_DIR.'/backup/'.$pathinfo['filename'].'.php');
        #}
        return true;
    }
    function __finish($sDir){
        $this->__removeDir($sDir);
        return $sDir;
    }
    function __removeDir($sDir){
        if($rHandle=opendir($sDir)){
            while(false!==($sItem=readdir($rHandle))){
                if ($sItem!='.' && $sItem!='..'){
                    if(is_dir($sDir.'/'.$sItem)){
                        $this->__removeDir($sDir.'/'.$sItem);
                    }else{
                        @unlink($sDir.'/'.$sItem);
                    }
                }
            }
            closedir($rHandle);
            utils::remove_p($sDir);
        }
    }
    //*/
}
