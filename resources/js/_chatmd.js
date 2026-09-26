/*
 * Antworten der KI-Chats darstellen (Besucher-Chat chat-widget.mjs, Redaktions-Assistent assistant.mjs):
 * Text wird immer escaped; erlaubt sind nur **fett**, `Code`, Listen („- “, „1. “), Absätze und Belege [n],
 * die zu Links auf die Quellen werden. Links, die die KI selbst schreibt, bleiben reiner Text.
 */
export const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/** $sources: [{n, title, url}] · label(n, title): Text für aria-label des Belegs */
export function renderMd(text, sources = [], label = (n, t) => `${n}: ${t}`) {
  const by = new Map((sources || []).map(s => [Number(s.n), s]));
  const inline = s => esc(s)
    .replace(/\*\*([^*]+?)\*\*/g, '<b>$1</b>')
    .replace(/`([^`]+?)`/g, '<code>$1</code>')
    .replace(/\[(\d{1,2})\]/g, (m, n) => {
      const s = by.get(Number(n));
      return s ? `<a class="cite" href="${esc(s.url)}" aria-label="${esc(label(n, s.title))}">${n}</a>` : '';
    });
  const out = [];
  let list = null, para = [];
  const flushPara = () => { if (para.length) out.push('<p>' + para.map(inline).join('<br>') + '</p>'); para = []; };
  const flushList = () => { if (list) out.push(`<${list.tag}>` + list.items.map(i => '<li>' + inline(i) + '</li>').join('') + `</${list.tag}>`); list = null; };
  for (const raw of String(text || '').split('\n')) {
    const line = raw.trim();
    const ul = line.match(/^[-*•]\s+(.*)$/), ol = line.match(/^\d{1,2}[.)]\s+(.*)$/), h = line.match(/^#{1,4}\s+(.*)$/);
    if (!line) { flushPara(); flushList(); continue; }
    if (ul || ol) {
      flushPara();
      const tag = ul ? 'ul' : 'ol';
      if (list && list.tag !== tag) flushList();
      list ||= { tag, items: [] };
      list.items.push((ul || ol)[1]);
      continue;
    }
    flushList();
    if (h) { flushPara(); out.push('<p><b>' + inline(h[1]) + '</b></p>'); continue; }
    para.push(line);
  }
  flushPara(); flushList();
  return out.join('');
}
