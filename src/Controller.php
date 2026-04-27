<?php
namespace Fzr;

/**
 * Base Controller — provides common utilities for application controllers.
 *
 * Use as the parent class for all web and API controllers.
 * Typical uses: handling HTTP requests, interacting with models, returning responses.
 *
 * - Provides lifecycle hooks (`__before`, `__after`, `__finally`) for cross-cutting concerns.
 * - Managed by {@see Engine} for instantiation and method invocation.
 * - Supports declarative routing and access control via Attributes.
 */
abstract class Controller {
    /** デフォルトアクション */
    public function index() {
        return Response::error(404);
    }

    /** アクション前フック */
    public function __before(string $routeAction, string $dispatchMethod) {
    }

    /** アクション後フック */
    public function __after(string $routeAction, string $dispatchMethod) {
    }

    /** アクション最終処理フック */
    public function __finally(string $routeAction, string $dispatchMethod) {
    }

    /** プロパティ安全取得 */
    public function __getProp($key, $default = null) {
        return property_exists($this, $key) ? $this->$key : $default;
    }
}
