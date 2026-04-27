<?php
namespace Fzr;

/**
 * User Message Helper — manages status messages (Success/Error/Warning) via Session Flash.
 *
 * Use to pass feedback messages from controllers to views across redirects.
 * Typical uses: "Record saved successfully" alerts, "Login failed" notifications.
 *
 * - Stores messages in session flash data (auto-cleared after being read).
 * - Provides semantic shortcuts (`success()`, `error()`, `warning()`, `info()`).
 */
class Message {
    const SUCCESS = 'success';
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    const SESSION_KEY = '_msg_flash';

    public static function set(string $type, string $message): void {
        Session::flash(self::SESSION_KEY, ['type' => $type, 'message' => $message]);
    }

    public static function get(): ?array {
        return Session::getFlash(self::SESSION_KEY);
    }

    public static function has(): bool {
        return Session::hasFlash(self::SESSION_KEY);
    }

    public static function success(string $message): void { self::set(self::SUCCESS, $message); }
    public static function error(string $message): void { self::set(self::ERROR, $message); }
    public static function warning(string $message): void { self::set(self::WARNING, $message); }
    public static function info(string $message): void { self::set(self::INFO, $message); }
}
