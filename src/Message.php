<?php
namespace Fzr;

/**
 * User Message Helper — manages status messages (Success/Error/Warning) with
 * intelligent lifecycle management.
 *
 * Messages live in request-scoped memory by default and are only persisted to
 * the session when an HTTP redirect is actually emitted (lazy flashing).
 * This avoids stale-session re-display on refresh and is unaffected by the
 * early {@see session_write_close()} that {@see Response::sendHeaders()} performs.
 *
 * Lifecycle:
 *  1. {@see set()} stores the message in static memory only.
 *  2. {@see Session::start()} invokes {@see snapshot()}, which moves any
 *     prior-request message from `$_SESSION['_flash']` into memory and clears
 *     the session-side copy immediately (while the session is still writable).
 *  3. {@see get()} / {@see has()} read non-destructively from memory; the same
 *     message can be referenced multiple times within one request.
 *  4. {@see Response::emitRedirect()} calls {@see toFlash()} just before
 *     headers are sent, persisting the in-memory message back to the session
 *     for the next request. If no redirect occurs, nothing is persisted.
 *
 * Special case: if you bypass {@see Response::redirect()} (e.g. raw
 * `header('Location: ...')` + `exit`), call {@see toFlash()} explicitly first.
 */
class Message {
    const SUCCESS = 'success';
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    const SESSION_KEY = '_msg_flash';

    private static ?array $current = null;
    private static bool $snapshotted = false;

    public static function set(string $type, string $message): void {
        // Ensure prior-request flash is absorbed before we overwrite memory,
        // even when set() is called before any other session-touching code.
        Session::start();
        self::$current = ['type' => $type, 'message' => $message];
    }

    public static function get(): ?array {
        Session::start();
        return self::$current;
    }

    public static function has(): bool {
        Session::start();
        return self::$current !== null;
    }

    /**
     * Persist the in-memory message to the session for the next request.
     *
     * Called automatically by {@see Response::emitRedirect()}. Call manually
     * only when emitting a redirect outside the framework's response pipeline
     * (e.g. raw `header('Location: ...')` + `exit`). Must be invoked while the
     * session is still writable (i.e. before {@see Response::sendHeaders()}).
     */
    public static function toFlash(): void {
        if (self::$current === null) return;
        Session::flash(self::SESSION_KEY, self::$current);
    }

    /**
     * @internal Invoked by {@see Session::start()} immediately after
     * `session_start()` to lift prior-request flash data into memory and
     * clear the session-side copy.
     */
    public static function snapshot(): void {
        if (self::$snapshotted) return;
        self::$snapshotted = true;
        if (isset($_SESSION['_flash'][self::SESSION_KEY])) {
            self::$current = $_SESSION['_flash'][self::SESSION_KEY];
            unset($_SESSION['_flash'][self::SESSION_KEY]);
            if (empty($_SESSION['_flash'])) {
                unset($_SESSION['_flash']);
            }
        }
    }

    public static function success(string $message): void { self::set(self::SUCCESS, $message); }
    public static function error(string $message): void { self::set(self::ERROR, $message); }
    public static function warning(string $message): void { self::set(self::WARNING, $message); }
    public static function info(string $message): void { self::set(self::INFO, $message); }
}
