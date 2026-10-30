<?php

namespace App\Support;

/**
 * CI's escape_like_str(), which the query builder's like() applies: `!` is the escape
 * character of its LIKE clauses (`... LIKE ? ESCAPE '!'`).
 */
final class LegacyLike
{
    public static function escape(string $keyword): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
    }
}
