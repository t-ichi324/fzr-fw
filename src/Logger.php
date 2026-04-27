<?php

namespace Fzr;

/**
 * Application Logger — centralized logging with multi-channel and multi-driver support.
 *
 * Use to record application events, errors, database queries, and security alerts.
 * Typical uses: error tracking, performance auditing, debugging complex flows.
 *
 * - Supports multiple levels (info, warning, error, debug, etc.) based on PSR-3.
 * - Handles structured logging (JSON) for cloud environments (Cloud Run).
 * - Provides specialized channels for Database (`db()`) and Trace (`trace()`) logs.
 * - Automatically masks sensitive information (passwords, tokens) in logs.
 * - Can output to local files, `stderr`, or custom storage drivers.
 */
class Logger
{
    /** @var callable[] */
    private static array $handlers = [];

    /** 出力先: 'file', 'stderr', または null (初期値: 自動判定) */
    private static ?string $output = null;

    /** 出力先設定（明示的に上書きする場合のみ） */
    public static function setOutput(string $mode): void
    {
        self::$output = $mode;
    }

    private static function getOutputMode(): string
    {
        if (self::$output !== null) return self::$output;
        return Context::isStateless() ? 'stderr' : 'file';
    }

    /** 外部ログハンドラ追加 */
    public static function addHandler(callable $handler): void
    {
        self::$handlers[] = $handler;
    }

    protected static function canLog(string $type): bool
    {
        if (!Env::getBool('log.output', true)) return false;

        return match ($type) {
            'debug'     => Env::getBool('log.debug', Context::isDebug()),
            'db-select' => Env::getBool('log.db_sel', Context::isDebug()),
            'db-exec'   => Env::getBool('log.db_exe', true),
            default     => true,
        };
    }

    protected static function encode(mixed $data): string
    {
        if ($data === null) return '';
        if (is_scalar($data)) return (string)$data;

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false
            ? $json
            : '[json_encode_failed:' . json_last_error_msg() . ']';
    }

    protected static function ensureLogDir(): ?string
    {
        try {
            $dir = Path::log(date('Y-m-d'));
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                    return null;
                }
            }
            return $dir;
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function write(string $type, ?string $message, mixed $data = null): void
    {
        $rid = Context::requestId();
        $uid = '-';
        if (class_exists(__NAMESPACE__ . '\\Auth', false)) {
            $uid = Auth::id() ?: '-';
        }
        $time = date('Y-m-d H:i:s');

        if (class_exists(__NAMESPACE__ . '\\Tracer', false) && Tracer::isEnabled()) {
            Tracer::add('log', $message ?? '', Context::elapsed(), [
                'level' => $type,
                'data' => $data,
                'time' => $time
            ]);
        }

        if (!self::canLog($type)) return;

        $line = "$time [$rid;$uid]\t" . ($message ?? '');

        $dataStr = self::encode($data);
        if ($dataStr !== '') {
            $line .= "\t|\t" . $dataStr;
        }

        if (self::getOutputMode() === 'stderr') {
            // Cloud Logging: stderr に JSON 構造化ログ出力
            $logEntry = [
                'severity' => self::typeToSeverity($type),
                'message' => ($message ?? ''),
                'time' => $time,
                'requestId' => $rid,
                'userId' => $uid,
            ];
            if ($data !== null) {
                $logEntry['data'] = $data;
            }
            $out = fopen('php://stderr', 'w');
            if ($out) {
                fwrite($out, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                fclose($out);
            }
        } else {
            // ファイル出力（従来）
            $line .= "\n";
            $dir = self::ensureLogDir();
            if ($dir) {
                $file = $dir . DIRECTORY_SEPARATOR . self::sanitizeType($type) . '.log';
                error_log($line, 3, $file);
            } else {
                error_log($line);
            }
        }

        foreach (self::$handlers as $fn) {
            try {
                $fn($type, $message, $data);
            } catch (\Throwable) {
            }
        }
    }

    /** ログタイプ → Cloud Logging severity 変換 */
    protected static function typeToSeverity(string $type): string
    {
        return match ($type) {
            'error', 'db-error', 'exception' => 'ERROR',
            'warning' => 'WARNING',
            'info' => 'INFO',
            'debug' => 'DEBUG',
            'access' => 'INFO',
            default => 'DEFAULT',
        };
    }

    protected static function sanitizeType(string $type): string
    {
        return preg_replace('/[^a-z0-9_\-]/i', '', $type) ?: 'unknown';
    }

    /** 詳細ログ出力 */
    public static function writeDetail(string $type, ?string $message, mixed $data = null): void
    {
        $uri = class_exists(__NAMESPACE__ . '\\Request', false) ? Request::uri() : '-';
        $message = "($uri) " . ($message ?? '');

        $detail = ($data !== null) ? ['data' => $data] : [];

        if (str_contains($type, 'error') || $type === 'exception') {
            $detail['_ip'] = class_exists(__NAMESPACE__ . '\\Request', false) ? Request::ipAddress() : '-';
        }

        if (Context::isDebug()) {
            if (!empty($_POST)) {
                $detail['post'] = self::mask($_POST);
            }
            if (!empty($_SESSION)) {
                $detail['session'] = self::mask($_SESSION);
            }
        }

        self::write($type, $message, $detail);
    }

    /** アクセスログ記録 */
    public static function access(): void
    {
        $code = class_exists(__NAMESPACE__ . '\\Response', false) ? Response::getStatusCode() : 200;
        $emoji = match (true) {
            $code >= 500 => '🔴',
            $code >= 400 => '🟡',
            $code >= 200 => '🟢',
            default      => '⚪',
        };

        $method = class_exists(__NAMESPACE__ . '\\Request', false) ? Request::method() : '-';
        $uri    = class_exists(__NAMESPACE__ . '\\Request', false) ? Request::uri() : '-';
        $ip     = class_exists(__NAMESPACE__ . '\\Request', false) ? Request::ipAddress() : '-';

        $msg = sprintf(
            "%s %-4s %d | %7.2fms | %-30s | ip=%s",
            $emoji,
            $method,
            $code,
            round(Context::elapsed() * 1000, 2),
            $uri,
            $ip
        );

        self::write('access', $msg);
    }

    /** 情報ログ出力 */
    public static function info(string $message, mixed $data = null): void
    {
        self::write('info', $message, $data ? ['data' => $data] : null);
    }

    /** 警告ログ出力 */
    public static function warning(string $message, mixed $data = null): void
    {
        self::writeDetail('warning', $message, $data ? ['data' => $data] : null);
    }

    /** デバッグログ出力 */
    public static function debug(string $message, mixed $data = null): void
    {
        self::writeDetail('debug', $message, $data);
    }

    /** エラーログ出力 */
    public static function error(string $message, mixed $data = null): void
    {
        self::writeDetail('error', $message, $data);
    }

    /** 例外ログ出力 */
    public static function exception(string $message, \Throwable $ex, mixed $data = null, string $type = 'error'): void
    {
        $detail = [
            'error' => [
                'type'    => get_class($ex),
                'message' => $ex->getMessage(),
                'file'    => $ex->getFile(),
                'line'    => $ex->getLine(),
            ]
        ];

        if (Context::isDebug()) {
            $detail['error']['trace'] = $ex->getTraceAsString();
        }

        if ($data !== null) {
            $detail['data'] = $data;
        }

        self::writeDetail($type, $message, $detail);
    }

    /** DBクエリログ出力 */
    public static function db(string $connKey, int $level, ?string $sql, mixed $params = null, ?\Throwable $ex = null): void
    {
        $tag = "DB:$connKey";

        $ctx = ['sql' => $sql];
        if ($params !== null) {
            $ctx['params'] = self::mask(is_array($params) ? $params : [$params]);
        }

        if ($ex) {
            self::exception($tag, $ex, $ctx, 'db-error');
            return;
        }

        match ($level) {
            1       => self::write('db-error', $tag, $ctx),
            2       => self::write('db-exec',  $tag, $ctx),
            default => self::write('db-select', $tag, $ctx),
        };
    }

    protected static function mask(array $data): array
    {
        static $keywords = ['pass', 'pw', 'token', 'secret', 'authorization', 'cookie', 'apikey'];

        $out = [];
        foreach ($data as $k => $v) {
            $key = strtolower((string)$k);

            foreach ($keywords as $w) {
                if (strpos($key, $w) !== false) {
                    $out[$k] = '***';
                    continue 2;
                }
            }

            if (is_array($v)) {
                $out[$k] = self::mask($v);
            } elseif (is_string($v) && mb_strlen($v) > 150) {
                $out[$k] = mb_substr($v, 0, 150) . '...';
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
