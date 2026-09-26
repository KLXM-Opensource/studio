<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Support\Knowledge;
use Core\Support\Questions;
use Core\Support\Support;
use Core\Support\Tickets;

/**
 * Support & Wissensdatenbank (Core\Support): Meldungen, Fragen & Antworten, Wissensartikel.
 * Alle Seiten bekommen die Bereichsnavigation „Support“ (views/support/_nav.php, Drill-down wie „Daten“).
 */
final class SupportController extends AdminController
{
    protected function view(string $view, array $vars = [], int $status = 200): Response
    {
        $vars['drill'] ??= \Core\Theme::capture(ROOT . '/app/Admin/views/support/_nav.php', ['cur' => $vars['cur'] ?? '']);
        $vars['drillTitle'] ??= __('Support');
        $vars['css'] = array_merge($vars['css'] ?? [], []);
        return parent::view($view, $vars, $status);
    }

    /** Anmeldung + Funktion „support“ + Lese-Recht; registriert Team-Mitglieder */
    private function gate(Request $r, string $need = 'read'): array
    {
        $u = $this->auth($r);
        $ok = match ($need) {
            'report' => Support::canReport(),
            'answer' => Support::canAnswer(),
            'staff' => Support::isStaff(),
            'manager' => Support::isSiteManager(),
            default => Support::canRead(),
        };
        if (!$ok) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        Support::registerStaff();
        return $u;
    }

    private function int(string $id): int
    {
        return ctype_digit($id) ? (int) $id : throw new HttpException(404);
    }

    // ================================================================== Meldungen

    public function mine(Request $r): Response
    {
        $this->gate($r, 'report');
        return $this->issues($r, true);
    }

    public function all(Request $r): Response
    {
        $this->gate($r, 'manager');
        return $this->issues($r, false);
    }

    private function issues(Request $r, bool $mine): Response
    {
        $f = ['mine' => $mine, 'status' => $r->str('status', $mine ? '' : 'offen'), 'category' => $r->str('kategorie'), 'priority' => $r->str('prioritaet'),
            'site' => $r->str('website'), 'assignee' => $r->str('zustaendig'), 'q' => mb_substr($r->str('q'), 0, 80), 'page' => (int) $r->str('seite', '1')];
        return $this->view('support/issues', [
            'cur' => $mine ? 'mine' : 'all', 'mine' => $mine, 'f' => $f, 'list' => Tickets::list($f), 'counts' => Tickets::counts($mine),
            'staff' => Support::isStaff() ? Support::staff() : [], 'sites' => Support::sites(),
            'title' => $mine ? __('Meine Meldungen') : (Support::isStaff() ? __('Alle Meldungen') : __('Meldungen dieser Website')),
        ]);
    }

    public function create(Request $r): Response
    {
        $this->gate($r, 'report');
        $from = $r->str('from');
        return $this->view('support/new', ['cur' => 'new', 'in' => ['title' => $r->str('titel'), 'body' => '', 'category' => $r->str('kategorie', 'frage'), 'priority' => 'normal'],
            'errors' => [], 'from' => $from, 'context' => Tickets::context($r, $from), 'labels' => Tickets::contextLabels()]);
    }

    public function store(Request $r): Response
    {
        $this->gate($r, 'report');
        $in = ['title' => $r->str('title'), 'body' => (string) ($r->post['body'] ?? ''), 'category' => $r->str('category'), 'priority' => $r->str('priority')];
        $errors = [];
        if (mb_strlen($in['title']) < 4) $errors['title'] = __('Bitte beschreiben Sie das Anliegen in einem kurzen Titel (mindestens 4 Zeichen).');
        if (mb_strlen(trim($in['body'])) < 10) $errors['body'] = __('Bitte beschreiben Sie, was passiert ist oder was Sie brauchen (mindestens 10 Zeichen).');
        $from = $r->str('from');
        $context = Tickets::context($r, $from, $r->str('viewport'));
        if ($errors) {
            return $this->view('support/new', ['cur' => 'new', 'in' => $in, 'errors' => $errors, 'from' => $from, 'context' => $context, 'labels' => Tickets::contextLabels()], 422);
        }
        $res = Tickets::create($in, Tickets::normalizeFiles($r->files['shots'] ?? null), $r->str('send_context') === '1' ? $context : []);
        foreach ($res['errors'] as $e) app()->session->flash('error', $e);
        return $this->back('/admin/support/meldung/' . $res['id'], 'success', __('Danke! Ihre Meldung #{id} ist beim Support-Team angekommen.', ['id' => $res['id']]));
    }

    public function issue(Request $r, string $id): Response
    {
        $this->gate($r, 'report');
        $issue = Tickets::find($this->int($id)) ?? throw new HttpException(404);
        Tickets::markSeen((int) $issue['id']);
        return $this->view('support/issue', ['cur' => Tickets::isReporter($issue) ? 'mine' : 'all', 'issue' => $issue, 'posts' => Tickets::posts($issue),
            'files' => Tickets::files($issue), 'staff' => Support::isStaff() ? Support::staff() : [], 'title' => '#' . $issue['id'] . ' ' . $issue['title'],
            'article' => $issue['kb_article_id'] ? Knowledge::find((int) $issue['kb_article_id']) : null]);
    }

    /** Antwort / interne Notiz (+ optional Status) */
    public function reply(Request $r, string $id): Response
    {
        $this->gate($r, 'report');
        $issue = Tickets::find($this->int($id)) ?? throw new HttpException(404);
        $staff = Support::isStaff();
        if ($issue['status'] === 'geschlossen' && !$staff) {
            return $this->back('/admin/support/meldung/' . $issue['id'], 'error', __('Diese Meldung ist geschlossen. Bitte legen Sie eine neue Meldung an.'));
        }
        $body = (string) ($r->post['body'] ?? '');
        $files = Tickets::normalizeFiles($r->files['shots'] ?? null);
        $status = $staff && in_array($r->str('status'), \Core\Support\Support::STATUSES, true) ? $r->str('status') : null;
        if (trim($body) === '' && !$files && ($status === null || $status === $issue['status'])) {
            return $this->back('/admin/support/meldung/' . $issue['id'] . '#antworten', 'error', __('Bitte eine Nachricht eingeben.'));
        }
        foreach (Tickets::reply($issue, $body, $staff && $r->str('internal') === '1', $files, $status) as $e) app()->session->flash('error', $e);
        return $this->back('/admin/support/meldung/' . $issue['id'] . '#verlauf-ende', 'success',
            $staff && $r->str('internal') === '1' ? __('Interne Notiz gespeichert.') : __('Nachricht gesendet.'));
    }

    public function status(Request $r, string $id): Response
    {
        $this->gate($r, 'report');
        $issue = Tickets::find($this->int($id)) ?? throw new HttpException(404);
        $to = $r->str('status');
        // Meldende dürfen ihre Meldung selbst als gelöst markieren oder wieder öffnen; alles andere das Team
        $allowed = Support::isStaff() || (Tickets::isReporter($issue) && (($to === 'geloest' && in_array($issue['status'], Support::OPEN, true))
            || ($to === 'in_arbeit' && $issue['status'] === 'geloest')));
        if (!$allowed || !in_array($to, Support::STATUSES, true)) throw new HttpException(403, __('Keine Berechtigung.'));
        Tickets::setStatus($issue, $to);
        return $this->back('/admin/support/meldung/' . $issue['id'], 'success', __('Status: {status}', ['status' => Support::statusLabel($to)]));
    }

    public function assign(Request $r, string $id): Response
    {
        $this->gate($r, 'staff');
        $issue = Tickets::find($this->int($id)) ?? throw new HttpException(404);
        $key = $r->str('assignee');
        Tickets::assign($issue, $key === 'ich' ? Support::me() : ($key !== '' ? $key : null));
        return $this->back('/admin/support/meldung/' . $issue['id'], 'success', __('Zuständigkeit gespeichert.'));
    }

    public function issueDelete(Request $r, string $id): Response
    {
        $this->gate($r, 'staff');
        $issue = Tickets::find($this->int($id)) ?? throw new HttpException(404);
        Tickets::delete($issue);
        return $this->back('/admin/support/alle', 'success', __('Meldung #{id} gelöscht.', ['id' => $issue['id']]));
    }

    /** Bildschirmfoto – nur für Personen, die die Meldung sehen dürfen */
    public function file(Request $r, string $id): Response
    {
        $this->gate($r, 'report');
        $f = Tickets::file($this->int($id)) ?? throw new HttpException(404);
        $path = Support::dir('files/' . $f['stored']);
        if (!is_file($path) || str_contains((string) $f['stored'], '..')) throw new HttpException(404);
        $name = preg_replace('~[^\w.\- ]+~u', '_', (string) $f['name']) ?: 'bild';
        return (new Response((string) file_get_contents($path), 200, [
            'Content-Type' => (string) $f['mime'],
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => ($r->str('download') === '1' ? 'attachment' : 'inline') . '; filename="' . $name . '"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Robots-Tag' => 'noindex, nofollow',
        ]));
    }

    /** Staff: Benachrichtigungen an/aus */
    public function prefs(Request $r): Response
    {
        $this->gate($r, 'staff');
        Support::db()->update('staff', ['notify' => $r->str('notify') === '1' ? 1 : 0], 'user_key = :k', ['k' => Support::me()]);
        return $this->back('/admin/support/alle', 'success', __('Gespeichert.'));
    }

    /** Vorschläge beim Melden: ähnliche Wissensartikel und beantwortete Fragen (JSON) */
    public function similar(Request $r): Response
    {
        $this->gate($r);
        $q = mb_substr($r->str('q'), 0, 400);
        $out = [];
        if (mb_strlen($q) >= 3) {
            foreach (Knowledge::search($q, 4, true) as $a) {
                $out[] = ['type' => 'article', 'label' => __('Wissensartikel'), 'title' => $a['title'], 'url' => url('/admin/support/wissen/' . $a['id']),
                    'excerpt' => \Core\Support\Markdown::plain($a['body'], 140)];
            }
            foreach (Questions::search($q, 3, true, true) as $x) {
                $out[] = ['type' => 'question', 'label' => $x['accepted_answer_id'] ? __('Gelöste Frage') : __('Frage mit Antworten'), 'title' => $x['title'],
                    'url' => url('/admin/support/fragen/' . $x['id']), 'excerpt' => \Core\Support\Markdown::plain($x['body'], 140)];
            }
        }
        return Response::json(['q' => $q, 'items' => $out]);
    }

    // ================================================================== Wissensdatenbank

    public function kb(Request $r): Response
    {
        $this->gate($r);
        $f = ['q' => mb_substr($r->str('q'), 0, 120), 'tag' => $r->str('tag'), 'sort' => $r->str('sort'), 'page' => (int) $r->str('seite', '1')];
        $browse = $f['q'] === '' && $f['tag'] === '' && $f['sort'] === '';
        return $this->view('support/kb', ['cur' => 'kb', 'f' => $f, 'list' => Knowledge::list($f), 'browse' => $browse,
            'popular' => $browse ? Knowledge::popular(5) : [], 'questions' => $browse ? Questions::list(['filter' => 'geloest', 'sort' => 'beliebt'], 5)['rows'] : [],
            'tags' => array_slice(Knowledge::tagCloud(), 0, 24, true), 'title' => __('Wissensdatenbank')]);
    }

    public function article(Request $r, string $id): Response
    {
        $this->gate($r);
        $a = Knowledge::find($this->int($id)) ?? throw new HttpException(404);
        Knowledge::countView((int) $a['id']);
        $a = Knowledge::find((int) $a['id']) ?? $a;   // Zähler inkl. dieses Aufrufs
        return $this->view('support/article', ['cur' => 'kb', 'a' => $a, 'related' => Knowledge::related($a), 'mine' => Knowledge::myFeedback((int) $a['id']),
            'revisions' => Support::isStaff() ? Knowledge::revisions((int) $a['id']) : [], 'title' => $a['title']]);
    }

    public function helpful(Request $r, string $id): Response
    {
        $this->gate($r);
        $a = Knowledge::find($this->int($id)) ?? throw new HttpException(404);
        Knowledge::feedback((int) $a['id'], $r->str('helpful') === '1');
        if ($r->wantsJson()) return Response::json(['ok' => true]);
        return $this->back('/admin/support/wissen/' . $a['id'] . '#feedback', 'success', __('Danke für Ihre Rückmeldung!'));
    }

    public function articleEdit(Request $r, ?string $id = null): Response
    {
        $this->gate($r, 'staff');
        $a = $id !== null ? (Knowledge::find($this->int($id)) ?? throw new HttpException(404)) : null;
        $in = $a ? ['title' => $a['title'], 'body' => $a['body'], 'tags' => implode(', ', Support::tagList($a['tags'])), 'visibility' => $a['visibility'],
            'sites' => Support::tagList($a['sites']), 'status' => $a['status']] : ['title' => '', 'body' => '', 'tags' => '', 'visibility' => 'all', 'sites' => [], 'status' => 'published'];
        return $this->view('support/article-edit', ['cur' => 'kb', 'a' => $a, 'in' => $in, 'errors' => [], 'issue' => null,
            'revisions' => $a ? Knowledge::revisions((int) $a['id']) : [], 'title' => $a ? __('Artikel bearbeiten') : __('Neuer Wissensartikel')]);
    }

    /** Aus gelöster Meldung: anonymisierter Entwurf zum Bearbeiten */
    public function publishForm(Request $r, string $id): Response
    {
        $this->gate($r, 'staff');
        $issue = Tickets::find($this->int($id)) ?? throw new HttpException(404);
        return $this->view('support/article-edit', ['cur' => 'kb', 'a' => null, 'in' => Knowledge::draftFromIssue($issue), 'errors' => [], 'issue' => $issue,
            'revisions' => [], 'title' => __('Als Wissensartikel veröffentlichen')]);
    }

    public function articleSave(Request $r, ?string $id = null): Response
    {
        $this->gate($r, 'staff');
        $a = $id !== null ? (Knowledge::find($this->int($id)) ?? throw new HttpException(404)) : null;
        $issue = !$a && ctype_digit($r->str('source_issue_id')) ? Tickets::find((int) $r->str('source_issue_id')) : null;
        $in = ['title' => $r->str('title'), 'body' => (string) ($r->post['body'] ?? ''), 'tags' => $r->str('tags'), 'visibility' => $r->str('visibility'),
            'sites' => array_map('strval', (array) ($r->post['sites'] ?? [])), 'status' => $r->str('status'), 'source_issue_id' => $issue['id'] ?? null];
        $errors = [];
        if (mb_strlen($in['title']) < 4) $errors['title'] = __('Bitte einen Titel angeben (mindestens 4 Zeichen).');
        if (mb_strlen(trim($in['body'])) < 20) $errors['body'] = __('Der Artikel ist noch sehr kurz (mindestens 20 Zeichen).');
        if ($in['visibility'] === 'sites' && !$in['sites']) $errors['sites'] = __('Bitte mindestens eine Website wählen.');
        if ($errors) {
            return $this->view('support/article-edit', ['cur' => 'kb', 'a' => $a, 'in' => $in, 'errors' => $errors, 'issue' => $issue,
                'revisions' => $a ? Knowledge::revisions((int) $a['id']) : [], 'title' => $a ? __('Artikel bearbeiten') : __('Neuer Wissensartikel')], 422);
        }
        $newId = Knowledge::save($a ? (int) $a['id'] : null, $in, $r->str('note'));
        if ($issue) {
            Support::db()->update('issues', ['kb_article_id' => $newId], 'id = :id', ['id' => (int) $issue['id']]);
            Support::db()->insert('issue_posts', ['issue_id' => (int) $issue['id'], 'author_key' => Support::me(), 'author_name' => Support::myName(), 'author_staff' => 1,
                'kind' => 'kb', 'internal' => 1, 'body' => '', 'meta' => json_encode(['article' => $newId]), 'created_at' => now()]);
        }
        return $this->back('/admin/support/wissen/' . $newId, 'success', $a ? __('Artikel gespeichert (neue Version).') : __('Wissensartikel angelegt.'));
    }

    public function articleRestore(Request $r, string $id, string $rev): Response
    {
        $this->gate($r, 'staff');
        $a = Knowledge::find($this->int($id)) ?? throw new HttpException(404);
        $v = Support::db()->fetch('SELECT * FROM article_revisions WHERE id = ? AND article_id = ?', [$this->int($rev), (int) $a['id']]) ?? throw new HttpException(404);
        Knowledge::save((int) $a['id'], ['title' => $v['title'], 'body' => $v['body'], 'tags' => Support::tagList((string) $v['tags']), 'visibility' => $v['visibility'],
            'sites' => Support::tagList((string) $v['sites']), 'status' => $a['status']], __('Version vom {date} wiederhergestellt', ['date' => date('d.m.Y H:i', strtotime((string) $v['created_at']))]));
        return $this->back('/admin/support/wissen/' . $a['id'], 'success', __('Version wiederhergestellt.'));
    }

    public function articleDelete(Request $r, string $id): Response
    {
        $this->gate($r, 'staff');
        $a = Knowledge::find($this->int($id)) ?? throw new HttpException(404);
        Knowledge::delete((int) $a['id']);
        return $this->back('/admin/support/wissen', 'success', __('Artikel gelöscht.'));
    }

    public function tags(Request $r): Response
    {
        $this->gate($r);
        return $this->view('support/tags', ['cur' => 'tags', 'tags' => Knowledge::tagCloud(), 'title' => __('Tags')]);
    }

    // ================================================================== Fragen & Antworten

    public function questions(Request $r): Response
    {
        $this->gate($r);
        $f = ['q' => mb_substr($r->str('q'), 0, 120), 'tag' => $r->str('tag'), 'sort' => $r->str('sort'), 'filter' => $r->str('filter'), 'page' => (int) $r->str('seite', '1')];
        return $this->view('support/questions', ['cur' => 'questions', 'f' => $f, 'list' => Questions::list($f), 'title' => __('Fragen & Antworten')]);
    }

    public function question(Request $r, string $id): Response
    {
        $this->gate($r);
        $q = Questions::find($this->int($id)) ?? throw new HttpException(404);
        Questions::countView((int) $q['id']);
        $q = Questions::find((int) $q['id']) ?? $q;
        $answers = Questions::answers($q);
        return $this->view('support/question', ['cur' => 'questions', 'q' => $q, 'answers' => $answers, 'votes' => Questions::myVotes($q, $answers),
            'title' => $q['title'], 'draft' => '', 'error' => null]);
    }

    public function ask(Request $r, ?string $id = null): Response
    {
        $this->gate($r, 'answer');
        $q = $id !== null ? (Questions::find($this->int($id)) ?? throw new HttpException(404)) : null;
        if ($q && !Questions::canEdit($q)) throw new HttpException(403, __('Keine Berechtigung.'));
        $in = $q ? ['title' => $q['title'], 'body' => $q['body'], 'tags' => implode(', ', Support::tagList($q['tags'])), 'visibility' => $q['visibility']]
            : ['title' => $r->str('titel'), 'body' => '', 'tags' => $r->str('tag'), 'visibility' => 'all'];
        return $this->view('support/ask', ['cur' => 'ask', 'q' => $q, 'in' => $in, 'errors' => [], 'title' => $q ? __('Frage bearbeiten') : __('Frage stellen')]);
    }

    public function askSave(Request $r, ?string $id = null): Response
    {
        $this->gate($r, 'answer');
        $q = $id !== null ? (Questions::find($this->int($id)) ?? throw new HttpException(404)) : null;
        if ($q && !Questions::canEdit($q)) throw new HttpException(403, __('Keine Berechtigung.'));
        $in = ['title' => $r->str('title'), 'body' => (string) ($r->post['body'] ?? ''), 'tags' => $r->str('tags'), 'visibility' => $r->str('visibility')];
        $errors = [];
        if (mb_strlen($in['title']) < 8) $errors['title'] = __('Bitte formulieren Sie die Frage als kurzen Satz (mindestens 8 Zeichen).');
        if (mb_strlen(trim($in['body'])) < 10) $errors['body'] = __('Bitte beschreiben Sie die Frage genauer (mindestens 10 Zeichen).');
        if ($errors) return $this->view('support/ask', ['cur' => 'ask', 'q' => $q, 'in' => $in, 'errors' => $errors, 'title' => __('Frage stellen')], 422);
        $newId = Questions::save($q, $in);
        return $this->back('/admin/support/fragen/' . $newId, 'success', $q ? __('Frage gespeichert.') : __('Ihre Frage ist online. Sie sehen Antworten unter „Fragen & Antworten“.'));
    }

    public function answerSave(Request $r, string $id): Response
    {
        $this->gate($r, 'answer');
        $q = Questions::find($this->int($id)) ?? throw new HttpException(404);
        $body = (string) ($r->post['body'] ?? '');
        if (mb_strlen(trim($body)) < 10) {
            $answers = Questions::answers($q);
            return $this->view('support/question', ['cur' => 'questions', 'q' => $q, 'answers' => $answers, 'votes' => Questions::myVotes($q, $answers),
                'title' => $q['title'], 'draft' => $body, 'error' => __('Die Antwort ist noch sehr kurz (mindestens 10 Zeichen).')], 422);
        }
        $aid = Questions::addAnswer($q, $body);
        // Fragesteller:in benachrichtigen, wenn die Frage von dieser Website stammt (E-Mail-Adresse nur dort bekannt)
        if ($q['author_key'] !== Support::me()) {
            [$site, $uid] = explode(':', (string) $q['author_key']) + ['', '0'];
            if ($site === site()->key && ($mail = app()->db->fetchValue('SELECT email FROM users WHERE id = ?', [(int) $uid]))) {
                Support::mail([(string) $mail], __('Neue Antwort auf Ihre Frage: {title}', ['title' => $q['title']]),
                    \Core\Support\Markdown::plain($body, 1200) . "\n\n" . absolute_url('/admin/support/fragen/' . $q['id'] . '#a' . $aid));
            }
        }
        return $this->back('/admin/support/fragen/' . $q['id'] . '#a' . $aid, 'success', __('Danke für Ihre Antwort!'));
    }

    public function answerUpdate(Request $r, string $id): Response
    {
        $this->gate($r, 'answer');
        $a = Questions::answer($this->int($id)) ?? throw new HttpException(404);
        if (!Questions::canEdit($a)) throw new HttpException(403, __('Keine Berechtigung.'));
        if (mb_strlen(trim((string) ($r->post['body'] ?? ''))) >= 10) Questions::updateAnswer($a, (string) $r->post['body']);
        return $this->back('/admin/support/fragen/' . $a['question_id'] . '#a' . $a['id'], 'success', __('Antwort gespeichert.'));
    }

    public function voteQuestion(Request $r, string $id): Response
    {
        $this->gate($r);
        $q = Questions::find($this->int($id)) ?? throw new HttpException(404);
        $on = Questions::toggleVote('q', $q);
        return $this->voted($r, '/admin/support/fragen/' . $q['id'], $on, 'q', (int) $q['id']);
    }

    public function voteAnswer(Request $r, string $id): Response
    {
        $this->gate($r);
        $a = Questions::answer($this->int($id)) ?? throw new HttpException(404);
        $on = Questions::toggleVote('a', $a);
        return $this->voted($r, '/admin/support/fragen/' . $a['question_id'] . '#a' . $a['id'], $on, 'a', (int) $a['id']);
    }

    private function voted(Request $r, string $back, bool $on, string $target, int $id): Response
    {
        $score = (int) Support::db()->fetchValue('SELECT score FROM ' . ($target === 'q' ? 'questions' : 'answers') . ' WHERE id = ?', [$id]);
        if ($r->wantsJson()) return Response::json(['ok' => true, 'voted' => $on, 'score' => $score]);
        return $this->back($back, 'success', $on ? __('Danke – Ihre Stimme zählt.') : __('Stimme zurückgenommen.'));
    }

    public function accept(Request $r, string $id): Response
    {
        $this->gate($r);
        $a = Questions::answer($this->int($id)) ?? throw new HttpException(404);
        $q = Questions::find((int) $a['question_id']) ?? throw new HttpException(404);
        if (!Questions::canAccept($q)) throw new HttpException(403, __('Keine Berechtigung.'));
        Questions::accept($q, $a);
        return $this->back('/admin/support/fragen/' . $q['id'] . '#a' . $a['id'], 'success',
            (int) $q['accepted_answer_id'] === (int) $a['id'] ? __('Markierung entfernt.') : __('Als Lösung markiert.'));
    }

    public function questionDelete(Request $r, string $id): Response
    {
        $this->gate($r, 'answer');
        $q = Questions::find($this->int($id)) ?? throw new HttpException(404);
        if (!Support::isStaff() && !($q['author_key'] === Support::me() && (int) $q['answers'] === 0)) throw new HttpException(403, __('Keine Berechtigung.'));
        Questions::delete($q);
        return $this->back('/admin/support/fragen', 'success', __('Frage gelöscht.'));
    }

    public function answerDelete(Request $r, string $id): Response
    {
        $this->gate($r, 'answer');
        $a = Questions::answer($this->int($id)) ?? throw new HttpException(404);
        if (!Questions::canEdit($a)) throw new HttpException(403, __('Keine Berechtigung.'));
        Questions::deleteAnswer($a);
        return $this->back('/admin/support/fragen/' . $a['question_id'], 'success', __('Antwort gelöscht.'));
    }
}
