<?php
declare(strict_types=1);

namespace Core\Blocks;

/**
 * Bereits sicheres HTML in der Vorlagensprache eigener Blöcke – entsteht nur durch Filter des Kerns
 * (rich/inline → Sanitizer-Whitelist, image/zoom → Media::picture, icon → Icons, nl2br/paragraphs → escaped).
 */
final class SafeHtml implements \Stringable
{
    public function __construct(public readonly string $html) {}

    public function __toString(): string
    {
        return $this->html;
    }
}
