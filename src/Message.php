<?php
namespace Fzr;

/**
 * User Message Helper — manages status messages (Alerts) and Toasts.
 *
 * Separates persistent on-page Alerts from temporary floating Toasts.
 */
class Message {
    const SUCCESS = 'success';
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';

    const KEY_ALERTS = '_msg_alerts';
    const KEY_TOASTS = '_msg_toasts';

    private static array $alerts = [];
    private static array $toasts = [];
    private static bool $snapshotted = false;

    /**
     * アラートを追加（画面内に固定表示される想定）
     */
    public static function add(string $type, string $message, string $title = '', array $options = []): void {
        Session::start();
        self::$alerts[] = self::build($type, $message, $title, $options);
    }

    /**
     * トーストを追加（画面端に浮かぶ想定）
     */
    public static function toast(string $type, string $message, string $title = '', array $options = []): void {
        Session::start();
        self::$toasts[] = self::build($type, $message, $title, $options);
    }

    private static function build(string $type, string $message, string $title, array $options): array {
        return [
            'type'    => $type,
            'message' => $message,
            'title'   => $title,
            'options' => $options,
            'time'    => time(),
        ];
    }

    /**
     * 全てのアラートを取得
     */
    public static function all(): array {
        Session::start();
        return self::$alerts;
    }

    /**
     * 全てのトーストを取得
     */
    public static function getToasts(): array {
        Session::start();
        return self::$toasts;
    }

    /**
     * 下位互換性: 最初の通知を取得
     */
    public static function get(): ?array {
        Session::start();
        return self::$alerts[0] ?? self::$toasts[0] ?? null;
    }

    public static function has(): bool {
        Session::start();
        return !empty(self::$alerts) || !empty(self::$toasts);
    }

    /**
     * リダイレクト用にフラッシュ保存
     */
    public static function toFlash(): void {
        if (!empty(self::$alerts)) Session::flash(self::KEY_ALERTS, self::$alerts);
        if (!empty(self::$toasts)) Session::flash(self::KEY_TOASTS, self::$toasts);
    }

    /**
     * セッションから復元
     */
    public static function snapshot(): void {
        if (self::$snapshotted) return;
        self::$snapshotted = true;

        // Alerts 復元
        if (isset($_SESSION['_flash'][self::KEY_ALERTS])) {
            $flashed = $_SESSION['_flash'][self::KEY_ALERTS];
            self::$alerts = is_array($flashed) ? (isset($flashed['type']) ? [$flashed] : $flashed) : [];
            unset($_SESSION['_flash'][self::KEY_ALERTS]);
        }

        // Toasts 復元
        if (isset($_SESSION['_flash'][self::KEY_TOASTS])) {
            $flashed = $_SESSION['_flash'][self::KEY_TOASTS];
            self::$toasts = is_array($flashed) ? (isset($flashed['type']) ? [$flashed] : $flashed) : [];
            unset($_SESSION['_flash'][self::KEY_TOASTS]);
        }

        // クリーンアップ
        if (isset($_SESSION['_flash']) && empty($_SESSION['_flash'])) {
            unset($_SESSION['_flash']);
        }
    }

    // ショートカット (Alerts 用)
    public static function success(string $message, string $title = ''): void { self::add(self::SUCCESS, $message, $title); }
    public static function error(string $message, string $title = ''): void { self::add(self::ERROR, $message, $title); }
    public static function warning(string $message, string $title = ''): void { self::add(self::WARNING, $message, $title); }
    public static function info(string $message, string $title = ''): void { self::add(self::INFO, $message, $title); }

    // ショートカット (Toasts 用)
    public static function successToast(string $message, string $title = ''): void { self::toast(self::SUCCESS, $message, $title); }
    public static function errorToast(string $message, string $title = ''): void { self::toast(self::ERROR, $message, $title); }
    public static function warningToast(string $message, string $title = ''): void { self::toast(self::WARNING, $message, $title); }
    public static function infoToast(string $message, string $title = ''): void { self::toast(self::INFO, $message, $title); }
}
