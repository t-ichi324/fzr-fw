<?php
namespace Fzr;

/**
 * User Message — unified status messages for views (flash-aware).
 *
 * 1 メッセージ = {type, text, field, title, options, time}。
 * field 付きはフォームフィールド向け、field なしはグローバルメッセージ（画面上部アラート等）。
 *
 * ライフサイクル:
 * - 同一リクエスト内の view 再表示 → static 保持のまま取得できる
 * - redirect 時 → Response 側で toFlash() がセッションへ退避し、
 *   次リクエストの Session::start() → snapshot() で復元される
 * 追加側・表示側はこの違いを意識しなくてよい。
 */
class Message {
    const SUCCESS = 'success';
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';

    const KEY_FLASH = '_messages';

    private static array $messages = [];
    private static bool $snapshotted = false;

    /**
     * メッセージを追加
     *
     * @param string      $type  種別（SUCCESS / ERROR / WARNING / INFO）
     * @param string      $text  本文
     * @param string|null $field フィールド名（フォームエラー用。null ならグローバル）
     */
    public static function add(string $type, string $text, ?string $field = null, string $title = '', array $options = []): void {
        Session::start();
        self::$messages[] = [
            'type'    => $type,
            'text'    => $text,
            'field'   => $field,
            'title'   => $title,
            'options' => $options,
            'time'    => time(),
        ];
    }

    // ショートカット
    public static function success(string $text, ?string $field = null, string $title = ''): void { self::add(self::SUCCESS, $text, $field, $title); }
    public static function error(string $text, ?string $field = null, string $title = ''): void { self::add(self::ERROR, $text, $field, $title); }
    public static function warning(string $text, ?string $field = null, string $title = ''): void { self::add(self::WARNING, $text, $field, $title); }
    public static function info(string $text, ?string $field = null, string $title = ''): void { self::add(self::INFO, $text, $field, $title); }

    // =============================
    // 取得
    // =============================

    /** 全メッセージ */
    public static function all(): array {
        Session::start();
        return self::$messages;
    }

    /** 種別を指定して取得 */
    public static function byType(string $type): array {
        return array_values(array_filter(self::all(), fn($m) => $m['type'] === $type));
    }

    /** エラーのみ取得 */
    public static function errors(): array {
        return self::byType(self::ERROR);
    }

    /** グローバルメッセージのみ取得（field なし。画面上部アラート表示用） */
    public static function globals(): array {
        return array_values(array_filter(self::all(), fn($m) => $m['field'] === null));
    }

    /** メッセージがあるか（$type 指定で種別を絞る） */
    public static function has(?string $type = null): bool {
        if ($type === null) return self::all() !== [];
        return self::byType($type) !== [];
    }

    public static function hasError() : bool {
        return self::has(self::ERROR);
    }

    /** フィールド向けメッセージ本文の一覧 */
    public static function fieldAll(string $field): array {
        $texts = [];
        foreach (self::all() as $m) {
            if ($m['field'] === $field) $texts[] = $m['text'];
        }
        return $texts;
    }

    /** フィールド向けメッセージの先頭1件の本文（無ければ null） */
    public static function field(string $field): ?string {
        return self::fieldAll($field)[0] ?? null;
    }

    /** フィールド向けメッセージがあるか */
    public static function hasField(string $field): bool {
        return self::field($field) !== null;
    }

    /** すべて破棄 */
    public static function clear(): void {
        self::$messages = [];
    }

    /**
     * @deprecated 旧API互換。先頭のグローバルメッセージを返す（旧 'message' キー併載）。
     *             新規コードは globals() / errors() を使うこと。
     */
    public static function get(): ?array {
        $m = self::globals()[0] ?? null;
        if ($m === null) return null;
        $m['message'] = $m['text'];
        return $m;
    }

    // =============================
    // ライフサイクル
    // =============================

    /** リダイレクト用にフラッシュ保存（Response::redirect() から呼ばれる） */
    public static function toFlash(): void {
        if (!empty(self::$messages)) Session::flash(self::KEY_FLASH, self::$messages);
    }

    /** セッションから復元（Session::start() から呼ばれる） */
    public static function snapshot(): void {
        if (self::$snapshotted) return;
        self::$snapshotted = true;

        if (isset($_SESSION['_flash'][self::KEY_FLASH])) {
            $flashed = $_SESSION['_flash'][self::KEY_FLASH];
            self::$messages = is_array($flashed) ? $flashed : [];
            unset($_SESSION['_flash'][self::KEY_FLASH]);
        }

        if (isset($_SESSION['_flash']) && empty($_SESSION['_flash'])) {
            unset($_SESSION['_flash']);
        }
    }
}
