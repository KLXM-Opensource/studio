<?php
declare(strict_types=1);

namespace Core\AI;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP-Client für KI-Anbieter mit Positivliste: nur die Hosts des konfigurierten Anbieters, externe Hosts nur per https,
 * keine Weiterleitungen, kurze Zeitlimits. (Core\Proxy::http kann nur GET über https – Embeddings brauchen POST,
 * lokale Anbieter wie Ollama laufen über http://127.0.0.1.)
 */
final class GuardedHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    /** @param list<string> $hosts erlaubte Hosts (klein geschrieben) */
    public function __construct(HttpClientInterface $client, private array $hosts)
    {
        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $target = parse_url($url);
        if (empty($target['host']) && !empty($options['base_uri'])) {
            $target = parse_url((string) $options['base_uri']);
        }
        $host = strtolower((string) ($target['host'] ?? ''));
        $scheme = strtolower((string) ($target['scheme'] ?? 'https'));
        if ($host === '' || !in_array($host, $this->hosts, true)) {
            throw new TransportException(sprintf('KI-Anbieter: Host „%s“ ist nicht freigegeben.', $host));
        }
        if ($scheme !== 'https' && !Ai::isLocalHost($host)) {
            throw new TransportException('KI-Anbieter: externe Anbieter nur über https.');
        }
        $options['max_redirects'] = 0;
        return $this->client->request($method, $url, $options);
    }
}
