<?php

namespace Fzr;

use ReflectionMethod, ReflectionClass, Throwable;

/**
 * Core Dispatcher — handles the request-response lifecycle and routing.
 *
 * Use to initialize the framework and dispatch incoming HTTP requests to controllers.
 * Typical uses: booting the app, registering custom routes, handling global exceptions.
 *
 * - Implements convention-based routing (IndexController::index, etc.).
 * - Resolves and executes Attributes (#[Auth], #[Roles], etc.) with inheritance.
 * - Manages controller instantiation and method invocation with parameter resolution.
 * - Provides global error/exception handling hooks.
 */
class Engine
{
    private static $_initialized = false;
    private static array $_shutdownHandlers = [];
    private static ?\Closure $_successHandler = null;

    private static array $onBeforeAction = [];
    private static $onError = null;
    private static array $routes = [];
    private static ?self $instance = null;

    /** ReflectionClass / ReflectionMethod キャッシュ（FPMワーカー内で使い回す） */
    private static array $refClassCache = [];
    private static array $refMethodCache = [];

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /** エンジン初期化 */
    public static function init(?string $rootPath = null, ?string $envFile = null): void
    {
        if (self::$_initialized === false) {

            if (!defined('ABSPATH')) {
                define('ABSPATH', $rootPath ?? realpath(__DIR__ . '/..'));
            }

            // .envファイルを探す（ルート直下を優先、なければapp/内）
            $rootEnv = ABSPATH . DIRECTORY_SEPARATOR . '.env';
            $appEnv  = ABSPATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . '.env';
            if (file_exists($rootEnv)) {
                Env::loadDotEnv($rootEnv);
            } elseif (file_exists($appEnv)) {
                Env::loadDotEnv($appEnv);
            }

            if ($envFile === null) {
                // デフォルトのapp.iniを探す（app/内固定）
                $appIni = ABSPATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . Config::DEFAULT_INI;
                if (file_exists($appIni)) {
                    $envFile = $appIni;
                }
            } else {
                // 明示指定された場合
                if (!file_exists($envFile)) {
                    // 相対パスを app/ 内として探すフォールバック
                    $appFile = ABSPATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $envFile;
                    if (file_exists($appFile)) {
                        $envFile = $appFile;
                    }
                }
            }

            // INIファイルがあれば設定、なければ環境変数のみで動作
            if ($envFile !== null && file_exists($envFile)) {
                Env::configure($envFile);
            }

            if (!defined('APP_START_TIME')) {
                define('APP_START_TIME', microtime(true));
            }

            $is_debug = Env::getBool('app.debug', false);
            Context::init($is_debug);
            Tracer::init($is_debug);
            if (php_sapi_name() === 'cli') {
                Context::setMode(Context::MODE_CLI);
            }

            if ($is_debug) {
                error_reporting(E_ALL);
                ini_set('display_errors', '1');
                ini_set('display_startup_errors', '1');
            } else {
                error_reporting(0);
                ini_set('display_errors', '0');
            }

            ini_set('log_errors', '1');
            if (php_sapi_name() !== 'cli') {
                // HTTPS強制リダイレクト
                if (Env::getBool('app.force_https', false) && !Request::isHttps()) {
                    header('Location: https://' . Request::server('HTTP_HOST') . Request::server('REQUEST_URI'), true, 301);
                    if (Response::isExitOnSend()) exit;
                    return;
                }

                // デフォルトのセキュリティヘッダ
                Response::setHeader('X-Frame-Options', 'SAMEORIGIN');
                Response::setHeader('X-Content-Type-Options', 'nosniff');
                Response::setHeader('X-XSS-Protection', '1; mode=block');
                Response::setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            }

            define('APP_CHARSET', Env::get('app.charset', 'UTF-8'));
            define('APP_LANG', Env::get('app.lang', 'ja'));
            define('APP_NAME', Env::get('app.name', 'MyApp'));
            define('LOGIN_PAGE', Env::get('app.login_page', 'login'));
            define('DELIMITER', Env::get('app.delimiter', '-'));
            define("REMEMBER_TOKEN", Env::get("session.remember_token", "rem"));
            define("CSRF_TOKEN_NAME", Env::get('security.csrf_name', "csrf_token"));
            define("CSRF_HEADER_NAME", Env::get('security.csrf_header', "X-CSRF-TOKEN"));
            date_default_timezone_set(Env::get('app.timezone', 'UTC'));
            define('VIEW_TEMPLATE_BASE', Env::get("view.base_template", "@layouts/base.php"));

            if (function_exists('mb_internal_encoding')) {
                mb_internal_encoding(APP_CHARSET);
            }
            if (function_exists('mb_http_output')) {
                mb_http_output(APP_CHARSET);
            }
            register_shutdown_function([self::class, '__handleShutdown']);


            self::$_initialized = true;
        }
    }

    public static function __handleShutdown(): void
    {
        $app = self::getInstance();
        foreach (self::$_shutdownHandlers as $handler) {
            try {
                $handler($app);
            } catch (\Throwable) {
            }
        }
        if (Env::getBool('log.access', true)) {
            Logger::access();
        }
    }

    public static function onShutdown(callable $callback): void
    {
        self::$_shutdownHandlers[] = $callback;
    }

    /** 致命的エラー出力 */
    public static function criticalError(string $title, string $message): void
    {
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            echo json_encode(['error' => $title, 'message' => strip_tags($message)]);
            exit;
        }
        http_response_code(500);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo "<!DOCTYPE html><html><head><title>Critical Error</title></head><body><h1>{$safeTitle}</h1><p>{$safeMessage}</p></body></html>";
        exit;
    }

    public static function autoload(...$dirs): void
    {
        Loader::add($dirs);
    }

    public static function bootstrap(...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    public static function onSuccess(callable $callback): void
    {
        self::$_successHandler = $callback;
    }
    public static function onError(?callable $callback): void
    {
        self::$onError = $callback;
    }
    public static function onFinally(callable $callback): void
    {
        self::onShutdown($callback);
    }
    public static function onBeforeAction(callable $callback): void
    {
        self::$onBeforeAction[] = $callback;
    }

    /** ディスパッチ */
    public static function dispatch(?callable $callback = null): void
    {
        if (Context::isCli()) {
            return;
        }

        $cb = $callback ?? self::$_successHandler;
        $app = self::$instance = new self();
        $startObLevel = ob_get_level();
        try {
            if (Request::isApiRoute()) Context::setMode(Context::MODE_API);
            if (Tracer::isEnabled()) Tracer::add('framework', 'Dispatch started: ' . Request::uri());
            ob_start();
            $app->run();
            if (Tracer::isEnabled()) Tracer::add('framework', 'Dispatch finished');
            ob_end_flush();
        } catch (\Throwable $ex) {
            while (ob_get_level() > $startObLevel) ob_end_clean();
            Logger::exception("Dispatch error", $ex);
            Response::setStatusCode(500);
            if (is_callable(self::$onError)) call_user_func(self::$onError, $ex, $app);
            else $app->error(500, Context::isDebug() ? null : "Internal error has occurred.", $ex);
        }
        if (is_callable($cb)) $cb($app);
    }

    /** ルーティング実行 */
    private function run(): void
    {
        if (!empty(self::$routes) && ($matched = self::matchRoute())) {
            if (Tracer::isEnabled()) Tracer::add('framework', "Route matched (explicit): " . $matched['class'] . "@" . $matched['method']);
            $this->dispatchMatched($matched['class'], $matched['method'], $matched['params']);
            return;
        }
        $pathParts = Request::routeParts();
        $max = count($pathParts);
        $found = false;
        $routeAction = '';
        if ($max === 0) {
            $class = Config::CTRL_PFX . 'Index' . Config::CTRL_SFX;
            $path = Path::ctrl($class . Config::CTRL_EXT);
            $routeAction = 'index';
            $params = [];
            $found = file_exists($path);
        } else {
            for ($i = $max; $i > 0; $i--) {
                $ctrlParts = array_slice($pathParts, 0, $i);
                $dir = implode(DIRECTORY_SEPARATOR, array_slice($ctrlParts, 0, -1));
                $ctrlName = $this->toClassCase($ctrlParts[$i - 1]);
                $class = Config::CTRL_PFX . $ctrlName . Config::CTRL_SFX;
                $path = Path::ctrl($dir, $class . Config::CTRL_EXT);
                if (file_exists($path)) {
                    $found = true;
                    $methodAndParams = array_slice($pathParts, $i);
                    $rawAction = $methodAndParams[0] ?? 'index';
                    $routeAction = $this->toMethodCase($rawAction);
                    $params = array_slice($methodAndParams, 1);
                    break;
                }
            }
            if (!$found) {
                $class = Config::CTRL_PFX . 'Index' . Config::CTRL_SFX;
                $path = Path::ctrl($class . Config::CTRL_EXT);
                if (file_exists($path)) {
                    $found = true;
                    $rawAction = $pathParts[0];
                    $routeAction = $this->toMethodCase($rawAction);
                    $params = array_slice($pathParts, 1);
                }
            }
        }
        if ($found) {
            if (Tracer::isEnabled()) Tracer::add('framework', "Route matched (auto): $class@$routeAction");
            include_once $path;
            if (!class_exists($class) || !(($controller = new $class()) instanceof Controller)) {
                $this->error(404);
                return;
            }
            $method = strtolower(Request::method());
            $isAjax = Request::isAjax();
            $tryList = [];
            if ($isAjax) {
                $tryList[] = "_ajax_{$method}_{$routeAction}";
                $tryList[] = "_ajax_{$routeAction}";
            }
            $tryList[] = "_{$method}_{$routeAction}";
            $tryList[] = $routeAction;
            if (isset($rawAction) && $rawAction !== $routeAction) {
                $tryList[] = "_{$method}_{$rawAction}";
                $tryList[] = $rawAction;
            }
            foreach ($tryList as $dispatchMethod) {
                if (is_callable([$controller, $dispatchMethod])) {
                    try {
                        if (!$this->isRoutable($controller, $routeAction, $dispatchMethod)) {
                            $this->error(404);
                            return;
                        }
                        if (!$this->verifyAccess($controller, $routeAction, $dispatchMethod)) return;
                        if (!$this->invokeBeforeAction($class, $routeAction, $dispatchMethod)) return;
                        $ret = $this->invokeInner($controller, $routeAction, $dispatchMethod, $params);
                        if (is_array($ret)) Response::handle($ret);
                        elseif (is_string($ret)) Response::handle(Response::view($ret));
                    } catch (HttpException $ex) {
                        if ($ex->getCode() === 401 && Context::isWeb()) {
                            Response::handle(Response::redirect(LOGIN_PAGE));
                            return;
                        }
                        $this->error($ex);
                    }
                    return;
                }
            }
            if (method_exists($controller, '__id')) {
                $dispatchMethod = '__id';
                $params = array_merge([$routeAction], $params);
                try {
                    if (!$this->verifyAccess($controller, $routeAction, $dispatchMethod)) return;
                    if (!$this->invokeBeforeAction($class, $routeAction, $dispatchMethod)) return;
                    $ret = $this->invokeInner($controller, $routeAction, $dispatchMethod, $params);
                    if (is_array($ret)) Response::handle($ret);
                    elseif (is_string($ret)) Response::handle(Response::view($ret));
                    return;
                } catch (HttpException $ex) {
                    $this->error($ex);
                    return;
                }
            }
            $this->error(404);
            return;
        }
        $this->error(404);
    }

    private function invokeBeforeAction(string $className, string $routeAction, string $dispatchMethod): bool
    {
        foreach (self::$onBeforeAction as $cb) {
            if (call_user_func($cb, $className, $routeAction, $dispatchMethod) === false) return false;
        }
        return true;
    }

    private function toClassCase(string $str): string
    {
        return implode('', array_map('ucfirst', preg_split('/[\.\-_ 　]+/', $str, -1, PREG_SPLIT_NO_EMPTY)));
    }

    private function toMethodCase(string $str): string
    {
        $parts = preg_split('/[\.\-_ 　]+/', $str, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) return $str;
        $res = array_map('ucfirst', $parts);
        $res[0] = lcfirst($res[0]);
        return implode('', $res);
    }

    private function error(int|HttpException $code_or_ex, $error = null, ?\Throwable $debugEx = null)
    {
        if ($code_or_ex instanceof HttpException) {
            $code = $code_or_ex->getHttpCode();
            $error = $error ?? $code_or_ex->getMessage();
            $debugEx = $debugEx ?? $code_or_ex->getPrevious() ?? $code_or_ex;
        } else {
            $code = $code_or_ex;
            $error = $error ?? HttpException::getErrorTitle($code);
        }

        if ($code >= 400 && $code !== 404) {
            if ($code >= 500) Logger::error("HTTP $code: $error");
            else Logger::warning("HTTP $code: $error");
        }

        if (class_exists(__NAMESPACE__ . '\\Tracer', false) && Tracer::isEnabled()) {
            Tracer::add('error', "HTTP $code: $error", null, ['code' => $code]);
        }

        Breadcrumb::clear();
        Response::setStatusCode($code);
        $defaultTitle = $code . " " . HttpException::getErrorTitle($code);
        Render::setData("error", $error);
        if (Context::isDebug() && $debugEx !== null) {
            Render::setData("debug_exception", $debugEx);
        }
        Auth::check();
        if (Context::isApi()) {
            header('Content-Type: application/json; charset=UTF-8');
            $payload = ["status" => "error", "code" => $code, "title" => $defaultTitle, "message" => $error];
            if (Context::isDebug() && $debugEx !== null) {
                $payload['debug'] = [
                    'exception' => get_class($debugEx),
                    'message'   => $debugEx->getMessage(),
                    'file'      => $debugEx->getFile(),
                    'line'      => $debugEx->getLine(),
                    'trace'     => explode("\n", $debugEx->getTraceAsString()),
                ];
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (Render::isPartial()) {
            Render::setTitle($defaultTitle);
            $content = "<h1>{$defaultTitle}</h1>";
            if ($error) $content .= "<p>" . h($error) . "</p>";
            echo $content;
        } else {
            Render::setTitle($defaultTitle);

            $e_pfx = Env::get("view.error_prefix", Config::ERR_VIEW_PFX);
            $e_sfx = Env::get("view.error_suffix", Config::ERR_VIEW_SFX);
            $e_def = Env::get("view.error_default", Config::ERR_VIEW_DEFAULT);
            $e_search = [
                $e_pfx . $code . $e_sfx,
                $e_pfx . $e_def . $e_sfx,
                $e_def,
            ];
            $err_view = null;
            foreach ($e_search as $v) {
                if (Render::hasTemplate($v)) {
                    $err_view = $v;
                    break;
                }
            }

            if ($err_view !== null) {
                Render::setContent(Render::getTemplate($err_view));
            } else {
                $content = "<style>body{font-family:sans-serif;background:#fff1f2;color:#9f1239;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}.c{text-align:left;padding:3rem 2rem;background:#fff;border-radius:1rem;border:2px solid #fecdd3;width:90%;max-width:600px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1)}h1{font-size:2rem;margin:0;border-bottom:2px solid #fecdd3;padding-bottom:10px}p{font-size:1.1rem;color:#be123c;margin:1.5rem 0;line-height:1.6;word-break:break-all}.dbg{margin-top:1.5rem;padding:1rem;background:#fff8f8;border:1px solid #fecdd3;border-radius:.5rem;font-size:.8rem;color:#555;font-family:monospace;white-space:pre-wrap;word-break:break-all;max-height:60vh;overflow:auto}</style>";
                $content .= "<div class='c'><h1>{$defaultTitle}</h1>";
                if ($error) $content .= "<p>" . h($error) . "</p>";
                if (Context::isDebug() && $debugEx !== null) {
                    $traceText  = h(get_class($debugEx)) . ': ' . h($debugEx->getMessage()) . "\n";
                    $traceText .= h($debugEx->getFile()) . ':' . $debugEx->getLine() . "\n\n";
                    $traceText .= h($debugEx->getTraceAsString());
                    $content .= "<div class='dbg'>" . $traceText . "</div>";
                }
                $content .= "</div>";
                Render::setContent($content);
                echo $content;
                return;
            }
            $vfile = (defined('VIEW_TEMPLATE_BASE') && VIEW_TEMPLATE_BASE) ? Path::view(VIEW_TEMPLATE_BASE) : '';
            if (file_exists($vfile)) include $vfile;
            else echo Render::getContent();
        }
    }

    protected function invokeInner(Controller $controller, string $routeAction, string $dispatchMethod, array $params = [])
    {
        $class = get_class($controller);
        try {
            Logger::debug("Dispatching: $class::$dispatchMethod()");
            if ($this->isMethodOverridden($controller, '__before')) {
                if (($ret_bef = $controller->__before($routeAction, $dispatchMethod)) !== null) return $ret_bef;
            }
            $refMethod = $this->refMethod($controller, $dispatchMethod);
            $methodParams = $refMethod->getParameters();
            $args = [];
            foreach ($methodParams as $i => $param) {
                if (array_key_exists($i, $params)) $args[] = $params[$i];
                elseif ($param->isDefaultValueAvailable()) $args[] = $param->getDefaultValue();
                else $args[] = null;
            }
            $ret = $refMethod->invokeArgs($controller, $args);
            if ($this->isMethodOverridden($controller, '__after')) {
                if (($ret_aft = $controller->__after($routeAction, $dispatchMethod)) !== null) $ret = $ret_aft;
            }
        } catch (\Throwable $ex) {
            Logger::exception("Exception in $class::$dispatchMethod()", $ex);
            throw $ex;
        } finally {
            try {
                if ($this->isMethodOverridden($controller, '__finally')) {
                    if (($ret_fin = $controller->__finally($routeAction, $dispatchMethod)) !== null) {
                        $ret = $ret_fin;
                    }
                }
            } catch (\Throwable $ex) {
                Logger::exception("Finally-block failed in $class::$dispatchMethod()", $ex);
            }
        }
        return $ret;
    }

    private function refClass(object $obj): ReflectionClass
    {
        $class = get_class($obj);
        return self::$refClassCache[$class] ??= new ReflectionClass($obj);
    }

    private function refMethod(object $obj, string $method): ReflectionMethod
    {
        $key = get_class($obj) . '::' . $method;
        return self::$refMethodCache[$key] ??= new ReflectionMethod($obj, $method);
    }

    protected function isMethodOverridden($controller, $method): bool
    {
        $refClass = $this->refClass($controller);
        return $refClass->hasMethod($method) && $refClass->getMethod($method)->getDeclaringClass()->getName() !== Controller::class;
    }

    protected function isRoutable($controller, string $routeAction, string $dispatchMethod): bool
    {
        if (strpos($routeAction, '_') === 0 || strpos($routeAction, '__') === 0) return false;
        if (!empty($deny = $controller->__getProp("denyMethods")) && (in_array($routeAction, $deny, true) || in_array($dispatchMethod, $deny, true))) return false;
        return method_exists($controller, $dispatchMethod) && $this->refMethod($controller, $dispatchMethod)->isPublic();
    }

    protected function verifyAccess($controller, string $action, string $dispatchMethod): bool
    {
        $refClass  = $this->refClass($controller);
        $refAction = $refClass->hasMethod($action) ? $refClass->getMethod($action) : null;
        $refDispatch = ($dispatchMethod !== $action && $refClass->hasMethod($dispatchMethod)) ? $refClass->getMethod($dispatchMethod) : null;

        if ($this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\Api::class)) {
            Context::setMode(Context::MODE_API);
        }
        if ($ipAttr = $this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\IpWhitelist::class)) {
            Security::checkIP($ipAttr->ips);
        }
        // CORS 判定
        $cors = $this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\AllowCors::class);
        $globalOrigin = Env::get('app.cors_origin');
        if ($cors || $globalOrigin) {
            if (!$cors) {
                $cors = new \Fzr\Attr\Http\AllowCors(
                    $globalOrigin ?: '*',
                    Env::get('app.cors_methods', 'GET,POST,PUT,DELETE,OPTIONS'),
                    Env::get('app.cors_headers', 'Content-Type,Authorization,X-Requested-With,X-CSRF-TOKEN')
                );
            }
            $requestOrigin = Request::header('Origin');
            $allowOrigin = null;
            if (in_array('*', $cors->origins)) {
                $allowOrigin = $requestOrigin ?: '*';
            } elseif (in_array($requestOrigin, $cors->origins)) {
                $allowOrigin = $requestOrigin;
            }
            if ($allowOrigin) {
                Response::setHeader('Access-Control-Allow-Origin', $allowOrigin);
                Response::setHeader('Access-Control-Allow-Methods', $cors->methods);
                Response::setHeader('Access-Control-Allow-Headers', $cors->headers);
                if ($cors->credentials) {
                    Response::setHeader('Access-Control-Allow-Credentials', 'true');
                }
                if (Request::method() === 'OPTIONS') {
                    Response::handle(Response::ok());
                    return false;
                }
            }
        }
        if ($this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\IsReadOnly::class)) {
            if (Request::isPost() || Request::isMethod('DELETE') || Request::isMethod('PUT')) {
                throw HttpException::forbidden("This action is read-only.");
            }
        }
        if ($this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\Csrf::class)) {
            if (Request::isPost() || Request::isMethod('DELETE') || Request::isMethod('PUT')) {
                Security::verifyCsrf();
            } else {
                Security::getCsrfToken();
            }
        }
        $guestAttr = $this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\Guest::class);
        $authAttr = $this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\Auth::class);
        $roleAttr = $this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\Roles::class);

        if ($guestAttr && Auth::check()) {
            Response::handle(Response::redirect($guestAttr->redirect ?: '/'));
            return false;
        }

        if ($authAttr || $roleAttr) {
            if (!Auth::check()) {
                $target = ($authAttr?->redirect) ?: LOGIN_PAGE;
                Response::handle(Response::redirect($target));
                return false;
            }
            if ($roleAttr && !empty($roleAttr->roles) && !Auth::hasRole($roleAttr->roles)) {
                throw HttpException::forbidden();
            }
        }
        if ($cache = $this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\AllowCache::class)) {
            Response::setHeader('Cache-Control', "public, max-age={$cache->maxAge}");
        }
        if ($this->getAttr($refClass, $refAction, $refDispatch, \Fzr\Attr\Http\AllowIframe::class)) {
            Response::setHeader('X-Frame-Options', 'ALLOWALL');
            Response::setHeader('Content-Security-Policy', "frame-ancestors *");
        }
        return true;
    }

    private function getAttr(ReflectionClass $refClass, ?ReflectionMethod $refAction, ?ReflectionMethod $refDispatch, string $className): ?object
    {
        // 1. 実際に呼ばれるメソッド（例: _post_save）を最優先
        if ($refDispatch) {
            $attrs = $refDispatch->getAttributes($className, \ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($attrs)) return $attrs[0]->newInstance();
        }
        // 2. ベースのアクションメソッド（例: save）を次にチェック
        if ($refAction) {
            $attrs = $refAction->getAttributes($className, \ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($attrs)) return $attrs[0]->newInstance();
        }
        // 3. クラス全体を最後にチェック
        $attrs = $refClass->getAttributes($className, \ReflectionAttribute::IS_INSTANCEOF);
        return !empty($attrs) ? $attrs[0]->newInstance() : null;
    }

    /** ルート明示登録 */
    public static function addRoute(string $httpMethod, string $pattern, string $handler): void
    {
        self::$routes[] = ['httpMethod' => strtoupper($httpMethod), 'pattern' => '/' . trim($pattern, '/'), 'handler' => $handler];
    }

    private static function matchRoute(): ?array
    {
        $requestPath = '/' . implode('/', Request::routeParts());
        $requestMethod = strtoupper(Request::method());
        foreach (self::$routes as $route) {
            if ($route['httpMethod'] !== 'ANY' && $route['httpMethod'] !== $requestMethod) continue;
            $pattern = $route['pattern'];
            if (strpos($pattern, '{') === false) {
                if ($pattern === $requestPath) return self::parseHandler($route['handler'], []);
                continue;
            }
            if (preg_match('#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^\/]+)', $pattern) . '$#', $requestPath, $matches)) {
                return self::parseHandler($route['handler'], array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)));
            }
        }
        return null;
    }

    private static function parseHandler(string $handler, array $params): array
    {
        $parts = explode('@', $handler, 2);
        return ['class' => $parts[0], 'method' => $parts[1] ?? 'index', 'params' => $params];
    }

    private function dispatchMatched(string $className, string $methodName, array $params): void
    {
        if (!file_exists($path = Path::ctrl($className . Config::CTRL_EXT))) {
            $this->error(404);
            return;
        }
        include_once $path;
        if (!class_exists($className) || !(($controller = new $className()) instanceof Controller)) {
            $this->error(404);
            return;
        }
        try {
            if (!$this->verifyAccess($controller, $methodName, $methodName)) return;
            if (!$this->invokeBeforeAction($className, $methodName, $methodName)) return;
            $ret = $this->invokeInner($controller, $methodName, $methodName, $params);
            if (is_array($ret)) Response::handle($ret);
            elseif (is_string($ret)) Response::handle(Response::view($ret));
        } catch (HttpException $ex) {
            if ($ex->getCode() === 401 && Context::isWeb()) {
                Response::handle(Response::redirect(LOGIN_PAGE));
                return;
            }
            $this->error($ex);
        }
    }
}
