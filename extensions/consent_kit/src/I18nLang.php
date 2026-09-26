<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace MyCms\Consent;

/** Sprache der Verwaltung als Code für übersetzbare Vorlagen-Felder */
final class I18nLang
{
    public static function admin(): string
    {
        return str_starts_with(\Core\I18n::locale(), 'en') ? 'en' : 'de';
    }
}
