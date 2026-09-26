<?php
declare(strict_types=1);

namespace Core\AI;

use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Bilder für Ollama: Die Ollama-Chat-API erwartet „content“ als Text und Bilder separat als Base64-Liste „images“
 * (die Ollama-Bridge von Symfony AI 0.14 sendet Inhalte als Liste – das lehnt Ollama ab). Nur für Nachrichten mit Bild.
 */
final class OllamaImageNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!$data instanceof UserMessage) return false;
        foreach ($data->getContent() as $c) if ($c instanceof Image) return true;
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [UserMessage::class => false];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $text = [];
        $images = [];
        foreach ($data->getContent() as $c) {
            if ($c instanceof Text) $text[] = $c->getText();
            elseif ($c instanceof Image) $images[] = $c->asBase64();
        }
        return ['role' => $data->getRole()->value, 'content' => implode("\n", $text), 'images' => $images];
    }
}
