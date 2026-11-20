// Teil von ncss-count-up.js: data-nv-count / -prefix / -suffix → --ncss-count-to / -prefix / -suffix (CSSOM statt style-Attribut)
const quote = (s) => '"' + s.replace(/["\\]/g, '\\$&') + '"';
document.querySelectorAll('.ncss-count-up[data-nv-count]').forEach((el) => {
  el.style.setProperty('--ncss-count-to', el.dataset.nvCount);
  if (el.dataset.nvPrefix) el.style.setProperty('--ncss-count-prefix', quote(el.dataset.nvPrefix));
  if (el.dataset.nvSuffix) el.style.setProperty('--ncss-count-suffix', quote(el.dataset.nvSuffix));
});
