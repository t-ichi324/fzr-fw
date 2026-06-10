<?php
namespace Bin;

use ValidationErrorsException;
use ViewData;

class Handler{
    private static $dir_path;
    private static $class_name;
    private static $func_name;
    private static $file_path;
    private static $invoke_class;
    private static $invoke_func;

    public static $OB;
    private static $ERR_LOG = "err.log";
    private static $ACCESS_LOG = "access.log";

    public static function run(){
        try{
            $sa = \Request::$scriptArray;

            ob_start();
            try{
                $r = self::run_innner($sa);
                \RunTrace::info("invoke return", $r);
            }catch(\Error|\Exception $ex){
                \RunTrace::error("invoke return", $ex);
                \Logger::write(self::$ERR_LOG, [$ex::class, $ex->getMessage()]);
                $sa = explode('/', \Conf::ERR_ROUTE);
                $r = self::err_inner($sa, $ex);
            }
            
            self::$OB = ob_get_clean();
            if($r === null){ $r = \Response::html(""); }

            try{
                self::resultProc($r);
            }catch(\Error|\Exception $ex){
                //rend error
                \RunTrace::error("resultProc", $ex);
                \Logger::write(self::$ERR_LOG, [$ex::class, $ex->getMessage()]);
                $sa = explode('/', \Conf::ERR_ROUTE);
                $r = self::err_inner($sa, $ex);

                //Err view call
                self::resultProc($r);
            }
        }catch(\Error|\Exception $ex){
            \RunTrace::error("run", $ex);
            \Logger::write(self::$ERR_LOG, [$ex::class, $ex->getMessage()]);
            http_response_code(500);
            echo "<h1>500 - Internal Server Error</h1>";
            echo $ex->getMessage();
        }
        
        if(\Logger::$ACCESS_LOG){
            \Logger::write(self::$ACCESS_LOG, http_response_code());
        }
    }

    private static function preparation(array $sa){
        \RunTrace::info("invoke preparation", $sa);
        \ViewData::clear();
        \Response::clear();

        self::$invoke_class = null;
        //特定dirにコントローラークラスがあるか scriptArrayから取得する
        //scriptArrayは $_SERVER["SCRIPT_NAME"]をSplitしたもの
        for($i=0; $i<4; $i++){
            \Response::clear();
            self::$invoke_class = self::findCls($sa, $i);
            $check = array(
                "lvl"=>$i,
                "class"=>self::$class_name,
                "func"=>self::$invoke_func,
                "file"=>self::$file_path,
            );
            \RunTrace::info("class check level(".$i.")", $check);
            if(self::$invoke_class !== null){
                break;
            }
        }

        //クラス有無
        if(self::$invoke_class === null){
            throw new \NotFoundException("invoke: not found class");
        }
        if(!(self::$invoke_class instanceof \Base\Controller)){
            throw new \NotFoundException("invoke: is not extends controller");
        }

        self::$invoke_func = self::findFunc();
        if(self::$invoke_func === null){
            throw new \NotFoundException("invoke: not found function");
        }

        \RunTrace::info("invoke found", array(
            "class"=>self::$class_name,
            "func"=>self::$invoke_func,
            "file"=>self::$file_path,
            )
        );
    }
    private static function run_innner(array $sa){
        self::preparation($sa);
        $fn = self::$invoke_func;
        $ret = null;
        
        if(self::$invoke_class instanceof \Base\AnonymousController){
            //認証チェックを行わない
        }else{
            //認証チェック
            $a = \Auth::check();
        }

        //権原チェック
        if(self::$invoke_class instanceof \Base\AuthController){
            if(!$a){ throw new \ForbiddenException(); }
            $roles = self::$invoke_class::$AUHT_ROLES;
            if(!empty($roles)){
                if(!is_array($roles)){
                    $roles = explode(",", $roles);
                }
                if(! \Auth::hasAnyRole(... $roles)){
                    throw new \ForbiddenException();
                }
            }
        }

        try{
            //実行前
            try{
                $r = self::$invoke_class->__pre_invoke_handler(self::$invoke_func, self::$func_name);
                if(isset($r)){ return $r; }
            }catch(\Exception $ex){
                \RunTrace::error("__pre_invoke_handler", $ex);
                throw $ex;
            }
            try{
                $ret = self::$invoke_class->$fn();
                \RunTrace::info("invoke result", $ret);
            }catch(\Exception $ex){
                \RunTrace::error("invoke error", $ex);
                throw $ex;
            }

            //実行後
            try{
                $r = self::$invoke_class->__after_invoke_handler(self::$invoke_func, self::$func_name);
                if(isset($r)){ return $r; }
            }catch(\Exception $ex){
                \RunTrace::error("__after_invoke_handler", $ex);
                throw $ex;
            }
        }catch(\Exception $ex2){
            if($ex2 instanceof ValidationErrorsException){
                if($ex2->handler_result !== null){
                    \RunTrace::warring("ValidationErrorsException has reulst", $ex2);
                    return $ret;
                }
            }

            $r = self::$invoke_class->__exception_invoke_handler(self::$invoke_func, self::$func_name, $ex2);
            if(!isset($r) || $r === null){ throw $ex2; }
            \RunTrace::info("exception_invoke_handler", $r);
            $ret = $r;
        }

        return $ret;
    }

    private static function err_inner(array $sa, \Error|\Exception|\IException $ex){
        $code = 500;
        if($ex instanceof \IException){ $code = $ex->statusCode(); }
        if($ex instanceof \Error){ $ex = new \Exception($ex->getMessage()); }
        self::preparation($sa);
        $fn = self::$invoke_func;
        $ret = null;
        try{
            if(self::$invoke_class instanceof \Base\ErrorController){
                $ret = self::$invoke_class->code($ex, $code);
            }else{
                $ret = self::$invoke_class->$fn();
            }
        }catch(\Error|\Exception $ex2){
            throw new \InternalServerException($ex2);
        }
        return $ret;
    }

    private static function findCls(array $sa, int $lv){
        // 例: user/mypage/edit/
        self::$class_name = null;
        self::$func_name = null;
        self::$dir_path = null;
        self::$file_path = null;

        if($lv === 0){
            // all dir
            // user/mypage/edit/IndexController.php : index()
            self::$class_name = SysConf::CTRL_CLASS_DEFAULT;
            self::$func_name = SysConf::CTRL_FUNC_DEFAULT;
            self::$dir_path = \Path::APP_CONTROLLER(... $sa);
        }elseif($lv === 1){
            // class
            // user/mypage/EditController.php : index()
            self::$class_name = array_pop($sa);
            self::$func_name = SysConf::CTRL_FUNC_DEFAULT;
            self::$dir_path = \Path::APP_CONTROLLER(... $sa);
        }elseif($lv === 2){
            // func
            // user/mypage/IndexController.php : edit()
            self::$class_name = SysConf::CTRL_CLASS_DEFAULT;
            self::$func_name = array_pop($sa);
            self::$dir_path = \Path::APP_CONTROLLER(... $sa);
        }elseif($lv === 3){
            // class func
            // user/MypageController.php : edit()
            self::$func_name = array_pop($sa);
            self::$class_name = array_pop($sa);
            self::$dir_path = \Path::APP_CONTROLLER(... $sa);
        }else{
            return null;
        }

        self::$class_name = self::getClassName();
        self::$file_path = \Path::combine(self::$dir_path, self::$class_name.SysConf::CTRL_CLASS_EXT);
        if(file_exists(self::$file_path) && is_file(self::$file_path)){
            require_once self::$file_path;
            if(class_exists(self::$class_name)){
                return new self::$class_name;
            }
        }

        return null;
    }
    private static function findFunc(){
        $funcname = self::getFuncName();
        $method = "_".strtolower(\Request::$method)."_";

        if(\Request::isAjax()){
            // _ajax_post_funcname() or _ajax_get_funcname()
            $tmp = "_ajax".$method.$funcname;
            if(is_callable(array(self::$invoke_class, $tmp))){ return $tmp; }
            // _ajax_funcname()
            $tmp = "_ajax_".$funcname;
            if(is_callable(array(self::$invoke_class, $tmp))){ return $tmp; }
        }
        
        // _post_funcname() or _get_funcname()
        $tmp = $method.$funcname;
        if(is_callable(array(self::$invoke_class, $tmp))){ return $tmp; }

        // funcname()
        $tmp = $funcname;
        if(is_callable(array(self::$invoke_class, $tmp))){ return $tmp; }

        return null;
    }
    private static function camelCase(string $str, bool $ucfirst = false):string{ $v = strtr(ucwords(strtr($str, ['_' => ' '])), [' ' => '']); if($ucfirst){ return ucfirst($v);}else{  return lcfirst($v); }}
    private static function getClassName(){
        $r = empty(self::$class_name) ? SysConf::CTRL_CLASS_DEFAULT : self::$class_name;
        $r = str_replace('.', '_', str_replace('-', '_',  $r)) ;
        return self::camelCase($r, true).SysConf::CTRL_CLASS_SFX;
    }
    private static function getFuncName(){
        $r = empty(self::$class_name) ? SysConf::CTRL_FUNC_DEFAULT : self::$func_name;
        $r = str_replace('.', '_', str_replace('-', '_',  $r)) ;
        $func = self::camelCase($r, false);
        return $func;

    }

    //
    public static function resultProc($result){
        $view = null;
        $status = \Response::getStatusCode();
        http_response_code($status);

        \RunTrace::info("result-type", $result);
        if(is_array($result)){

            $key = $result[SysResKEY::R_KEY];
            $head = $result[SysResKEY::R_HEAD];
            $cont = $result[SysResKEY::R_CONTENT];

            //HEAD
            self::r_head($head);

            //HEAD
            if(\Request::$method === "HRAD"){ return; }

            if($key === SysResKEY::K_NO_CONTENT){ return; }
            
            if($key === SysResKEY::K_REDIRECT){ 
                if(class_exists("ViewData")){
                    ViewData::_REVOCER_SET();
                }
                return;
            }
            
            if($key === SysResKEY::K_RAW){ echo $cont; return; }
            
            if($key === SysResKEY::K_FILE){
                if(file_exists($cont)){
                    $sd = new \StreamDownload(SysConf::STREAM_CHUNK, SysConf::STREAM_DELAY);
                    $sd->start($cont);
                }else{
                    http_response_code(404);
                }
                return;
            }
            $view = $cont;
        }else if(is_string($result) ){
            self::r_head(null);
            $view = $result;
        }else{
            throw new \InternalServerException("result-type error");
        }
        if(\Request::$method === "HEAD"){ return; }

        $cd = \ViewData::getViewDir();
        $view = (($cd === null || $cd === '') ? '' : $cd.DIRECTORY_SEPARATOR).$view;

        if(empty($view)){
            \RunTrace::error("view is empty");
            throw new \InternalServerException("view empty");
        }else{
            \RunTrace::info("render run", $view);
            include_once SysConf::FILE_RENDER;
            include_once SysConf::FILE_RENDER_FUNC;
            Render::run($view);
        }
    }

    private static function r_head($head){
        $expires = \Response::getCacheExpires();
        header_remove('X-Powered-By');
        if($expires > 0){
            header('Expires: ' . gmdate('D, d M Y H:i:s T', time() + $expires));
            header('Cache-Control: private, max-age=' . $expires);
            header('Pragma: ');
        }else{
            header('Expires: -1');
            header('Cache-Control:');
            header('Pragma: no-cache');
        }
        $xorign = \Response::getCrossOrign();
        if($xorign){
            $orign = "*";
            if(isset($_SERVER["HTTP_ORIGIN"]) && !empty($_SERVER["HTTP_ORIGIN"])){ $orign = $_SERVER["HTTP_ORIGIN"]; }
            header('Access-Control-Allow-Origin: '.$orign);
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Methods: *");
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        }
        $xframe = \Response::getXFrame();
        if($xframe === false){
            header('X-Frame-Options: DENY');
        }

        // クリックジャッキング対策(あまり意味ない)
        //header('X-FRAME-OPTIONS', 'SAMEORIGIN');
        // XSS攻撃を検知させる（検知したら実行させない）
        header("X-XSS-Protection: 1; mode=block");
        if(isset($head)){ foreach($head as $h){ header($h);} }

        echo Handler::$OB;
        Handler::$OB = null;
    }
}
