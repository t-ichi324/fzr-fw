<?php

namespace Fzr;

/**
 * Authentication & Role Management — secure user session handling.
 *
 * Use to authenticate users, manage roles, and verify permissions via Attributes.
 * Typical uses: login/logout flows, role-based access control, "remember me" functionality.
 *
 * - Stores authenticated user data in the session (via {@see Session}).
 * - Supports custom "remember me" token resolution via `resolveRemember()`.
 * - Fires `onLogin` events for post-authentication logic (e.g., updating last login time).
 * - Integrates with `#[Roles]` and `#[Auth]` attributes for declarative security.
 */
class Auth extends Store
{
    private static ?array $roles = null;

    // Remember Me 用の復元コールバックを保持
    private static $rememberResolver = null;
    // イベントフック
    private static $onLogin = null;
    private static ?string $authKey = null;

    /** 認証用セッションキーを取得する */
    public static function sessionKey(): string
    {
        if (self::$authKey === null) {
            self::$authKey = Env::get('session.auth_key', 'auth_key@' . Env::get('app.key', md5(ABSPATH)));
        }
        return self::$authKey;
    }

    /** 自動ログインの解決ロジックを登録する */
    public static function resolveRemember(callable $resolver): void
    {
        self::$rememberResolver = $resolver;
    }

    /** ログイン時イベントを登録する */
    public static function onLogin(callable $callback): void
    {
        self::$onLogin = $callback;
    }

    /** ログイン */
    public static function login(object $user, bool $regenerate = true, bool $remember = false): void
    {
        $key = self::sessionKey();
        self::replace($user);
        self::$roles = null; // キャッシュをクリア
        $data = [
            'user' => $user,
        ];
        Session::set($key, $data);
        if ($regenerate) Session::regenerate();

        $token = null;
        if ($remember) {
            // トークンを生成してCookieに焼き、ユーザーオブジェクトにも持たせる等の処理
            $token = Security::randomToken(64);
            Cookie::set(self::rememberTokenName(), $token, 60 * 60 * 24 * 30); // 30日
            // ※ トークンをDBに保存する処理は $onLogin フック側に任せる
        }

        if (self::$onLogin) {
            call_user_func(self::$onLogin, $user, $token);
        }
    }


    /** ログアウト */
    public static function logout(): void
    {
        $key = self::sessionKey();
        self::clear();
        self::$roles = null;
        Session::remove($key);
        Cookie::remove(self::rememberTokenName());
        Session::regenerate();
    }

    /** 認証状態チェック */
    public static function check(): bool
    {
        if (!self::isEmpty()) return true;
        $key = self::sessionKey();
        $auth = Session::get($key);
        if (is_array($auth) && isset($auth['user'])) {
            self::fill($auth['user']);
            self::$roles = null; // getRolesで再取得させる
            return true;
        }

        // セッション切れ ＆ Remember Cookie がある場合の自動復元
        $cookieName = self::rememberTokenName();
        if (self::$rememberResolver && Cookie::has($cookieName)) {
            $token = Cookie::get($cookieName);
            $restoredUser = call_user_func(self::$rememberResolver, $token);

            if ($restoredUser) {
                // 復元成功
                self::login($restoredUser, false);
                return true;
            } else {
                // 不正なトークンなら消す
                Cookie::remove($cookieName);
            }
        }

        return false;
    }

    /**
     * 現在ログインしているユーザーオブジェクトを取得します。
     * 
     * @return object|null
     */
    public static function userObject(): ?object
    {
        self::check();
        $data = self::data();
        return is_object($data) ? $data : (is_array($data) ? (object)$data : null);
    }


    /** userObject() のエイリアス */
    public static function user(): ?object
    {
        return self::userObject();
    }


    /** ゲスト確認 */
    public static function isGuest(): bool
    {
        return !self::check();
    }

    /**
     * 現在ログインしているユーザーのIDを取得します。
     * @return int ユーザーID
     */
    public static function getId(): int
    {
        if (!self::check()) return 0;
        return self::getInt(Env::get("auth.user_id_name", "id"), 0);
    }
    public static function id(): int
    {
        return self::getId();
    }
    public static function userid(): int
    {
        return self::getId();
    }

    public static function getUsername(): string
    {
        if (!self::check()) return "";
        return self::getString(Env::get("auth.user_name_name", "name"), "");
    }
    public static function username(): string
    {
        return self::getUsername();
    }

    /**
     * 現在ログインしているユーザーのメールアドレスを取得します。
     * @return string|null メールアドレス
     */
    public static function getEmail(): string|null
    {
        if (!self::check()) return null;
        return self::getString(Env::get("auth.user_email_name", "email"), null);
    }
    public static function mail(): string|null
    {
        return self::getEmail();
    }

    public static function getRoles(): array
    {
        if (!self::check()) return [];

        if (self::$roles !== null) return self::$roles;

        $role_data = self::get(Env::get("auth.user_roles_name", "roles"), "");
        if (is_array($role_data)) {
            self::$roles = $role_data;
            return self::$roles;
        }

        $role_text = (string)$role_data;
        $role_format = Env::get("auth.user_roles_format", "csv");
        $roles = [];

        if (empty($role_format)) {
            $roles = [$role_text];
        } else if ($role_format === "csv") {
            $roles = array_filter(array_map('trim', explode(',', $role_text)));
        } elseif ($role_format === "json") {
            $roles = json_decode($role_text, true) ?: [];
        } elseif (strlen($role_format) === 1) {
            $roles = array_filter(array_map('trim', explode($role_format, $role_text)));
        }

        self::$roles = array_values($roles);
        return self::$roles;
    }
    public static function roles(): array
    {
        return self::getRoles();
    }

    /** ロール保持確認 */
    public static function hasRole(string|array $roles): bool
    {
        $userRoles = self::roles();
        if (empty($userRoles)) return false;
        $required = is_array($roles) ? $roles : [$roles];
        foreach ($required as $r) {
            if (in_array($r, $userRoles, true)) return true;
        }
        return false;
    }

    /** hasRole() の短いエイリアス */
    public static function is(string|array $role): bool
    {
        return self::hasRole($role);
    }

    /** 管理者権限の簡易確認 */
    public static function isAdmin(): bool
    {
        return self::is(Env::get('auth.admin_role', 'admin'));
    }

    /** ユーザーに紐づくトークンを取得 */
    public static function token(): ?string
    {
        if (!self::check()) return null;
        return self::getString(Env::get("auth.user_token_name", "token"), null);
    }

    /** Remember Me 用の Cookie 名を取得する */
    private static function rememberTokenName(): string
    {
        return defined('REMEMBER_TOKEN') ? REMEMBER_TOKEN : 'rem';
    }
}
