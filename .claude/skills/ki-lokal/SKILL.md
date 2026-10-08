---
name: ki-lokal
description: KI-Funktionen von KLXM Studio (semantische Suche, KI-Antwort in der Suche, Besucher-Chat, Assistent) lokal mit Ollama einrichten und testen – Konfiguration, empfohlene kleine Modelle, Testbefehle, Fehlersuche.
---

# KI lokal mit Ollama

## Modelle

```bash
ollama pull qwen3:4b-instruct    # Texte/Chat: gutes Deutsch, 1–3 s je Antwort auf einem Laptop
ollama pull nomic-embed-text     # Embeddings für die Suche
```

Nicht verwenden: reine „Denk“-Modelle (z. B. `qwen3:4b` in neueren Ollama-Versionen – schreiben Überlegungen trotz
`think: false` in die Antwort) und alte Kleinmodelle (z. B. `qwen` 1.5 – folgen den Anweisungen mit Quellen nicht).

## Einrichten (Website `<site>`)

In `config/sites/<site>.php` (nicht versioniert) oder unter Grundeinstellungen → KI:

```php
'ai' => ['provider' => 'ollama', 'base_url' => 'http://127.0.0.1:11434',
         'models' => ['text' => 'qwen3:4b-instruct', 'embed' => 'nomic-embed-text'], 'timeout' => 5],
```

Dann in der Verwaltung: Grundeinstellungen → KI „KI-Funktionen einschalten“ (`sys.ai_enabled`); → Suche „Semantische Suche“
und „KI-Antwort über den Treffern“ (Standard aus, empfohlen „automatisch bei Fragen“); Chat-Knopf: Funktion `chat.visitor`
und `sys.chat_enabled`.

## Testen

```bash
php bin/console ai:test --cap=text --site=<site>
php bin/console search:index --all --site=<site>      # Index + Vektoren
php bin/console search:status --site=<site>
php bin/console search:query "Frage mit anderen Worten" --site=<site>
php bin/console ai:selftest
```

Browser: `/suche?q=<Frage>` (Kasten „Antwort“), Chat-Knopf „Fragen?“. Gute Tests: Fragen, deren Wörter nicht wörtlich im
Text stehen; Preise/Termine am Seitenende; eine themenfremde Frage (muss „weiß ich nicht“ mit Kontakt liefern).

## Fehlersuche

- Antwort leer oder Unsinn → dieselbe Anfrage direkt an Ollama (`curl localhost:11434/api/chat …`): liegt es am Modell?
- Quellen ansehen: `Core\AI\VisitorChat::sources($frage)`; Textstellen wählt `passage()` (IDF-gewichtete Abschnitte).
- Rechtsseiten als Beifang in der Suche: `Core\Search\Ranker::legalLast`.
