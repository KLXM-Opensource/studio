/*
 * Server-Sent Events per fetch lesen (Gegenstück zu Core\Http\Sse): anders als EventSource auch mit POST, eigenen
 * Kopfzeilen (CSRF) und Abbruch (AbortController). Antwortet der Server nicht mit text/event-stream (z. B. Fehler als
 * JSON oder Rückfall ohne Streaming), kommt die JSON-Antwort als Ereignis „json“ an.
 *
 *   await sseFetch(url, { body: {q}, onEvent: (name, data, status) => …, signal });
 */
export async function sseFetch(url, { body = {}, headers = {}, signal, onEvent }) {
  const r = await fetch(url, {
    method: 'POST', credentials: 'same-origin', signal, cache: 'no-store',
    headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream, application/json', ...headers },
    body: JSON.stringify(body),
  });
  const ct = r.headers.get('Content-Type') || '';
  if (!ct.includes('text/event-stream') || !r.body) {
    let j = {};
    try { j = await r.json(); } catch { /* keine JSON-Antwort */ }
    onEvent('json', j || {}, r.status);
    return;
  }
  const rd = r.body.getReader(), dec = new TextDecoder();
  let buf = '';
  const block = (b) => {
    let ev = 'message', data = '';
    for (const line of b.split('\n')) {
      if (line.startsWith('event:')) ev = line.slice(6).trim();
      else if (line.startsWith('data:')) data += (data ? '\n' : '') + line.slice(5).replace(/^ /, '');
    }
    if (!data) return;
    let v = data;
    try { v = JSON.parse(data); } catch { /* Text */ }
    onEvent(ev, v, r.status);
  };
  for (;;) {
    const { value, done } = await rd.read();
    if (done) break;
    buf += dec.decode(value, { stream: true }).replace(/\r\n?/g, '\n');
    let i;
    while ((i = buf.indexOf('\n\n')) >= 0) { block(buf.slice(0, i)); buf = buf.slice(i + 2); }
  }
  if (buf.trim()) block(buf);
}
