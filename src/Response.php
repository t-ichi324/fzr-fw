<?php

namespace Fzr;

/**
 * HTTP Response Manager — handles status codes, headers, and content delivery.
 *
 * Use to generate and send various types of HTTP responses to the client.
 * Typical uses: rendering templates, returning JSON for APIs, serving file downloads.
 *
 * - Provides factory methods (`view`, `json`, `redirect`, `file`) for common response types.
 * - Supports custom status codes and headers via a global static registry.
 * - Implements `exit` control (`setExitOnSend`) for compatibility with stateless environments.
 * - Handles automated JSON encoding and appropriate `Content-Type` header setting.
 */
class Response
{
    protected static int $statusCode = 200;
    protected static array $headers = [];
    protected static $before = null;
    protected static $after = null;
    protected static bool $exitOnSend = true;
    protected static bool $sent = false;

    /** exit制御（Cloud環境では false に設定） */
    public static function setExitOnSend(bool $flag): void
    {
        self::$exitOnSend = $flag;
    }

    /** レスポンス送信済み判定 */
    public static function isSent(): bool
    {
        return self::$sent;
    }

    /** exit判定取得 */
    public static function isExitOnSend(): bool
    {
        return self::$exitOnSend;
    }

    /** ヘッダ設定 */
    public static function setHeader(string $name, string $value): void
    {
        self::$headers[$name] = $value;
    }

    /** ヘッダ取得 */
    public static function getHeader(string $name): ?string
    {
        return self::$headers[$name] ?? null;
    }

    /** 全ヘッダ出力 */
    public static function sendHeaders(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        foreach (self::$headers as $name => $value) {
            header("$name: $value");
        }
    }

    // =============================
    // コントローラ用ファクトリ
    // =============================
    /** テンプレート応答生成 */
    public static function view(string $template, array $data = [], ?string $baseTemplate = null): array
    {
        if (!empty($data)) Render::set($data);
        return ['type' => 'view', 'value' => [
            'template' => $template,
            'baseTemplate' => $baseTemplate ?? (defined('VIEW_TEMPLATE_BASE') ? VIEW_TEMPLATE_BASE : ''),
            'is_partial' => false
        ]];
    }

    /** 文字列応答生成 */
    public static function viewRaw(string $content, array $data = [], ?string $baseTemplate = null): array
    {
        if (!empty($data)) Render::set($data);
        return ['type' => 'view-raw', 'value' => [
            'content' => $content,
            'baseTemplate' => $baseTemplate ?? (defined('VIEW_TEMPLATE_BASE') ? VIEW_TEMPLATE_BASE : ''),
            'is_partial' => false
        ]];
    }

    /** パーシャル応答生成 */
    public static function partial(string $template): array
    {
        return ['type' => 'view', 'value' => ['template' => $template, 'baseTemplate' => null, 'is_partial' => true]];
    }

    /** パーシャル文字列応答生成 */
    public static function partialRaw(string $content): array
    {
        return ['type' => 'view-raw', 'value' => ['content' => $content, 'baseTemplate' => null, 'is_partial' => true]];
    }

    /** リダイレクト応答生成 */
    public static function redirect(string $url): array
    {
        return ['type' => 'redirect', 'value' => $url];
    }

    /** JSON応答生成 */
    public static function json(array|object $data, ?int $code = null): array
    {
        if ($code !== null) self::setStatusCode($code);
        return ['type' => 'json', 'value' => $data];
    }

    /** ファイル配信応答生成 */
    public static function file(string $path, ?string $filename = null, bool $autoDelete = false): array
    {
        return ['type' => 'file', 'value' => ['path' => $path, 'filename' => $filename, 'autoDelete' => $autoDelete]];
    }

    /** テキスト配信応答生成 */
    public static function text(string $content, ?string $filename = null): array
    {
        return ['type' => 'text', 'value' => ['content' => $content, 'filename' => $filename]];
    }

    /** 生データ出力応答生成 */
    public static function raw(string $content, string $mimeType = "text/plain"): array
    {
        return ['type' => 'raw', 'value' => ['content' => $content, 'mimeType' => $mimeType]];
    }

    /** 200 OK 応答生成 */
    public static function ok(string $content = ''): array
    {
        return ['type' => 'raw', 'value' => ['content' => $content, 'mimeType' => 'text/plain']];
    }

    /** 204 No Content 応答生成 */
    public static function noContent(): array
    {
        return ['type' => 'no_content', 'value' => null];
    }

    /** エラー応答生成 */
    public static function error(int $code, ?string $message = null): array
    {
        return ['type' => 'error', 'value' => ['code' => $code, 'message' => $message]];
    }

    // =============================
    // Hook
    // =============================
    public static function beforeSend(?callable $callback)
    {
        self::$before = $callback;
    }
    public static function afterSend(?callable $callback)
    {
        self::$after = $callback;
    }

    protected static function callHook(?callable $hook): void
    {
        if (is_callable($hook)) {
            try {
                $hook();
            } catch (\Throwable) {
            }
        }
    }

    /** ステータスコード設定 */
    public static function setStatusCode(int $code)
    {
        self::$statusCode = $code;
        http_response_code($code);
    }

    /** ステータスコード取得 */
    public static function getStatusCode(): int
    {
        return self::$statusCode;
    }

    // =============================
    // 実行処理
    // =============================
    /** レスポンス配列処理実行 */
    public static function handle(array $res): void
    {
        switch ($res['type']) {
            case 'view':
                self::emitView($res['value']['template'], $res['value']['baseTemplate'], $res['value']['is_partial'] ?? false);
                break;
            case 'view-raw':
                self::emitViewRaw($res['value']['content'], $res['value']['baseTemplate'], $res['value']['is_partial'] ?? false);
                break;
            case 'redirect':
                self::emitRedirect($res['value']);
                break;
            case 'json':
                self::emitJson($res['value']);
                break;
            case 'file':
                self::emitFile($res['value']['path'], $res['value']['filename'] ?? null, $res['value']['autoDelete'] ?? false);
                break;
            case 'text':
                self::emitText($res['value']['content'], $res['value']['filename'] ?? null);
                break;
            case 'raw':
                self::emitRaw($res['value']['content'], $res['value']['mimeType']);
                break;
            case 'no_content':
                self::emitNoContent();
                break;
            case 'error':
                self::emitError($res['value']['code'], $res['value']['message'] ?? null);
                break;
            default:
                self::emitError(500, 'Unknown response type');
                break;
        }
    }

    protected static function terminate(): void
    {
        self::$sent = true;
        if (self::$exitOnSend) exit;
    }

    protected static function emitView(string $template, ?string $baseTemplate = null, $is_partial = false): void
    {
        self::callHook(self::$before);
        Render::setPartial($is_partial);
        $content = Render::getTemplate($template);
        Render::setContent($content);
        self::sendHeaders();

        $vfile = $baseTemplate ? Path::view($baseTemplate) : '';
        if ($vfile !== '' && file_exists($vfile)) {
            include $vfile;
        } else {
            echo Render::getContent();
        }
        self::callHook(self::$after);
    }

    protected static function emitViewRaw(string $content, ?string $baseTemplate = null, $is_partial = false): void
    {
        self::callHook(self::$before);
        Render::setPartial($is_partial);
        Render::setContent($content);
        self::sendHeaders();

        $vfile = $baseTemplate ? Path::view($baseTemplate) : '';
        if ($vfile !== '' && file_exists($vfile)) {
            include $vfile;
        } else {
            echo Render::getContent();
        }
        self::callHook(self::$after);
    }

    protected static function emitRedirect(string $url): void
    {
        // 外部URLへのリダイレクトは allow_external_redirect=true の場合のみ許可
        if (preg_match('#^https?://#i', $url)) {
            $appHost = parse_url(Url::root(), PHP_URL_HOST);
            $targetHost = parse_url($url, PHP_URL_HOST);
            if ($targetHost !== $appHost && !Env::getBool('security.allow_external_redirect', false)) {
                $url = '/';
            }
        }
        self::callHook(self::$before);
        self::setHeader("Location", Url::get($url));
        // Persist any in-memory flash messages to the session before
        // sendHeaders() closes the session for writes.
        Message::toFlash();
        self::sendHeaders();
        self::callHook(self::$after);
        self::terminate();
    }

    protected static function emitJson(array|object $data): void
    {
        self::callHook(self::$before);
        self::setHeader("Content-Type", "application/json; charset=utf-8");
        self::sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::callHook(self::$after);
        self::terminate();
    }

    protected static function emitText(string $text, ?string $filename = null): void
    {
        self::callHook(self::$before);
        if ($filename === null) {
            self::setHeader("Content-Type", "text/plain; charset=utf-8");
        } else {
            $mime = self::getMimeTypeByExtension($filename);
            self::setHeader("Content-Type", $mime);
            self::setHeader("Content-Disposition", "attachment; filename*=UTF-8''" . rawurlencode($filename));
        }
        self::sendHeaders();
        echo $text;
        self::callHook(self::$after);
        self::terminate();
    }

    protected static function emitRaw(string $text, string $mimeType): void
    {
        self::setHeader("Content-Type", $mimeType . "; charset=utf-8");
        self::callHook(self::$before);
        self::sendHeaders();
        echo $text;
        self::callHook(self::$after);
        self::terminate();
    }

    protected static function emitNoContent(): void
    {
        self::setStatusCode(204);
        self::callHook(self::$before);
        self::sendHeaders();
        self::callHook(self::$after);
        self::terminate();
    }

    protected static function emitFile(string $path, ?string $filename = null, bool $autoDelete = false): void
    {
        if (!is_file($path)) {
            self::emitError(404, 'File not found');
            return;
        }
        self::callHook(self::$before);
        $filename ??= basename($path);
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
        } else {
            $mime = self::getMimeTypeByExtension($filename);
        }
        self::setHeader("Content-Type", $mime);
        self::setHeader("Content-Length", (string)filesize($path));
        self::setHeader("Content-Disposition", "attachment; filename*=UTF-8''" . rawurlencode($filename));
        self::sendHeaders();
        readfile($path);
        self::callHook(self::$after);
        if ($autoDelete) @unlink($path);
        self::terminate();
    }

    protected static function emitError(int $code, ?string $message): void
    {
        throw new HttpException($message, $code);
    }

    /** エラー画面即時表示 */
    public static function errorView(string $message): void
    {
        throw HttpException::internal($message);
    }

    /** 拡張子からMIMEタイプを簡易推測 */
    protected static function getMimeTypeByExtension(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimes = [
            // テキスト・ドキュメント
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            // 画像
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            // アーカイブ
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'rar' => 'application/vnd.rar',
            // Office (Modern)
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // オーディオ・ビデオ
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'wav' => 'audio/wav',
            'webm' => 'video/webm',
            // フォント
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        return $mimes[$ext] ?? 'application/octet-stream';
    }
}
