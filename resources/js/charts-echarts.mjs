/**
 * Engine „echarts“ für Core\Charts – die einzige Datei, die ECharts kennt (Apache ECharts, Apache-2.0, gebündelt und selbst
 * gehostet, nur Linie/Balken/Torte, Canvas). Vertrag jeder Engine:
 *   export function render(el, spec, theme) → { update(theme), resize(), dispose(), image()? }
 * spec = Core\Charts::normalize() (type, labels, series[{name, data}], title, description, format, unit, stacked, height, locale)
 * theme = { colors[], ink, muted, grid, bg, font, dark } aus CSS-Variablen (resources/js/charts.mjs).
 * Tooltip: eigenes HTML nur mit Klassen (keine style-Attribute – strenge CSP der Website), Farbpunkt über .cms-chart-c{0…7}.
 */
import { init, use } from 'echarts/core';
import { LineChart, BarChart, PieChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

use([LineChart, BarChart, PieChart, GridComponent, TooltipComponent, LegendComponent, CanvasRenderer]);

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function formatter(spec) {
  const nf = (d) => new Intl.NumberFormat(spec.locale || 'de', { maximumFractionDigits: d });
  const unit = spec.unit ? ' ' + spec.unit : '';
  return (v) => {
    if (v === null || v === undefined || v === '-') return '–';
    switch (spec.format) {
      case 'percent': return nf(Math.abs(v) < 10 ? 1 : 0).format(v) + ' %' + unit;
      case 'bytes': { const u = ['B', 'KB', 'MB', 'GB', 'TB']; let i = 0, x = v; while (Math.abs(x) >= 1024 && i < u.length - 1) { x /= 1024; i++; } return nf(i ? 1 : 0).format(x) + ' ' + u[i]; }
      case 'duration': return v >= 60 ? `${Math.floor(v / 60)}:${String(Math.round(v % 60)).padStart(2, '0')} min` : nf(0).format(v) + ' s';
      default: return nf(Number.isInteger(v) ? 0 : 1).format(v) + unit;
    }
  };
}

function option(spec, t) {
  const fmt = formatter(spec);
  const pie = spec.type === 'pie' || spec.type === 'donut';
  const horizontal = spec.type === 'hbar';
  const many = spec.series.length > 1 || pie;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const text = { color: t.muted, fontFamily: t.font, fontSize: 12 };
  const tip = {
    trigger: pie ? 'item' : 'axis', confine: true, className: 'cms-chart-tip', backgroundColor: t.bg, borderColor: t.grid, borderWidth: 1, padding: [8, 10],
    textStyle: { color: t.ink, fontFamily: t.font, fontSize: 12 },
    axisPointer: { type: spec.type === 'line' || spec.type === 'area' ? 'line' : 'shadow', lineStyle: { color: t.muted }, shadowStyle: { color: 'rgba(127,127,127,.08)' } },
    formatter: (p) => {
      const list = Array.isArray(p) ? p : [p];
      const head = pie ? '' : `<div class="cms-chart-tip__h">${esc(list[0]?.axisValueLabel ?? list[0]?.name)}</div>`;
      return head + list.map((x) => `<div class="cms-chart-tip__row"><i class="cms-chart-tip__dot cms-chart-c${(pie ? x.dataIndex : x.seriesIndex) % 8}"></i>`
        + `<span>${esc(pie ? x.name : x.seriesName)}</span><b>${esc(fmt(x.value))}${pie ? ` · ${esc(x.percent)} %` : ''}</b></div>`).join('');
    },
  };
  const base = { color: t.colors, animation: !reduce, animationDuration: 500, textStyle: { fontFamily: t.font, color: t.ink }, tooltip: tip,
    legend: many ? { show: true, type: 'scroll', top: 0, left: 0, right: 0, pageIconColor: t.muted, pageIconInactiveColor: t.grid, pageTextStyle: text, icon: 'roundRect', itemWidth: 10, itemHeight: 10, itemGap: 16, textStyle: { ...text, color: t.ink } } : { show: false } };

  if (pie) {
    return { ...base, series: [{ type: 'pie', radius: spec.type === 'donut' ? ['50%', '78%'] : ['0%', '78%'], center: ['50%', '57%'],
      itemStyle: { borderColor: t.bg, borderWidth: 2, borderRadius: 4 }, label: { show: false }, labelLine: { show: false },   // Zuordnung über Legende + Tooltip (keine abgeschnittenen Beschriftungen)
      data: spec.labels.map((l, i) => ({ name: l, value: spec.series[0]?.data[i] ?? 0 })) }] };
  }
  const cat = { type: 'category', data: spec.labels, axisLine: { lineStyle: { color: t.grid } }, axisTick: { show: false }, axisLabel: { ...text, hideOverlap: true } };
  const whole = spec.series.every((x) => x.data.every((v) => v === null || Number.isInteger(v)));   // nur ganze Zahlen → keine 0,2-Schritte
  const val = { type: 'value', minInterval: whole && spec.format !== 'percent' ? 1 : undefined, splitLine: { lineStyle: { color: t.grid } }, axisLabel: { ...text, formatter: (v) => fmt(v) } };
  const dots = spec.labels.length <= 31;
  return {
    ...base,
    grid: { left: 4, right: 12, top: many ? 34 : 12, bottom: 4, containLabel: true },
    xAxis: horizontal ? val : cat, yAxis: horizontal ? { ...cat, inverse: true } : val,
    series: spec.series.map((s) => {
      if (spec.type === 'bar' || spec.type === 'hbar') {
        return { type: 'bar', name: s.name, data: s.data, stack: spec.stacked ? 'all' : undefined, barMaxWidth: 28, barGap: '12%',
          itemStyle: { borderRadius: horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0], borderColor: t.bg, borderWidth: spec.stacked ? 1 : 0 } };
      }
      return { type: 'line', name: s.name, data: s.data, stack: spec.stacked ? 'all' : undefined, showSymbol: dots, symbol: 'circle', symbolSize: 8,
        lineStyle: { width: 2 }, itemStyle: { borderColor: t.bg, borderWidth: 2 }, areaStyle: spec.type === 'area' ? { opacity: spec.stacked ? 0.5 : 0.14 } : undefined,
        emphasis: { focus: 'series' } };
    }),
  };
}

export function render(el, spec, t) {
  const chart = init(el, null, { renderer: 'canvas' });
  chart.setOption(option(spec, t));
  return {
    update: (nt) => chart.setOption(option(spec, nt), true),
    resize: () => chart.resize(),
    image: () => chart.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: 'transparent' }),   // Druck (Papier statt Kartenfarbe)
    dispose: () => chart.dispose(),
  };
}
