<?php

/**
 * Fzr Loader (Manual Loader)
 *
 * Composerを使わずにフレームワークを読み込むための起動ファイルです。
 * 適切な順序でコアファイルを読み込み、オートローダーを自動的にセットアップします。
 */

namespace Fzr;

// 1. 基本クラスを手動で読み込む（順序が重要）
require_once __DIR__ . '/src/Loader.php';
require_once __DIR__ . '/src/Engine.php';
require_once __DIR__ . '/inc/helpers.php';

// 2. オートローダーを有効化し、srcフォルダを登録
Loader::register();
Loader::add(__DIR__ . '/src');

// 3. エイリアス（\Request 等）を読み込む
if (file_exists(__DIR__ . '/inc/aliases.php')) {
    require_once __DIR__ . '/inc/aliases.php';
}
