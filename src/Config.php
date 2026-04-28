<?php

namespace Fzr;

/**
 * Framework Constants — central repository for framework-wide default settings.
 *
 * Defines default directory paths, class suffixes, and file names used by the {@see Engine}.
 *
 * - Centralizes internal naming conventions (e.g., `Controller` suffix).
 * - Defines the default structure of the `app/` directory.
 * - Used as the fallback when specific `app.ini` settings are missing.
 */
class Config
{
    const VERSION = '1.0.0';
    const DEFAULT_INI = "app.ini";
    const DEFAULT_ROUTE = "index";
    const DEFAULT_VIEW = "index";
    const DIR_PUBLIC = "public";
    const DIR_APP   = "app";
    const DIR_CTRL  = "app/controllers";
    const DIR_VIEW  = "app/views";
    const DIR_MODELS  = "app/models";
    const DIR_DB    = "db";
    const DIR_STORAGE = "storage";
    const DIR_LOG   = "storage/log";
    const DIR_TEMP  = "storage/temp";
    const CTRL_PFX = "";
    const CTRL_SFX = "Controller";
    const CTRL_EXT = ".php";
    const ERR_VIEW_PFX = "";
    const ERR_VIEW_DEFAULT = "error";
    const ERR_VIEW_SFX = "";
    const ERR_VIEW_FILE = "error.php";
    const CLI_FILE = "core-cli.php";
}
