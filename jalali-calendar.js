/*!
 * NOBA Clinic — self-contained Jalali (Persian) calendar.
 * No dependencies. Used by both the booking form (lumiere-clinic.html) and the
 * admin panel (admin.html). Conversion math is the well-known jalaali algorithm.
 */
(function (global) {
  'use strict';

  function div(a, b) { return ~~(a / b); }
  function mod(a, b) { return a - ~~(a / b) * b; }

  function jalCal(jy) {
    var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
      1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0];
    var jm, jump, leap, leapG, march, n, i;
    for (i = 1; i < bl; i += 1) {
      jm = breaks[i];
      jump = jm - jp;
      if (jy < jm) break;
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
      jp = jm;
    }
    n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
    leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;
    return { leap: leap, gy: gy, march: march };
  }

  function g2d(gy, gm, gd) {
    var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4)
      + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    return d;
  }
  function d2g(jdn) {
    var j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    var i = div(mod(j, 1461), 4) * 5 + 308;
    var gd = div(mod(i, 153), 5) + 1;
    var gm = mod(div(i, 153), 12) + 1;
    var gy = div(j, 1461) - 100100 + div(8 - gm, 6);
    return { gy: gy, gm: gm, gd: gd };
  }
  function j2d(jy, jm, jd) {
    var r = jalCal(jy);
    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
  }
  function d2j(jdn) {
    var gy = d2g(jdn).gy, jy = gy - 621, r = jalCal(jy);
    var jdn1f = g2d(gy, 3, r.march), jd, jm, k;
    k = jdn - jdn1f;
    if (k >= 0) {
      if (k <= 185) { jm = 1 + div(k, 31); jd = mod(k, 31) + 1; return { jy: jy, jm: jm, jd: jd }; }
      k -= 186;
    } else { jy -= 1; k += 179; if (r.leap === 1) k += 1; }
    jm = 7 + div(k, 30); jd = mod(k, 30) + 1;
    return { jy: jy, jm: jm, jd: jd };
  }

  function toJalaali(gy, gm, gd) { return d2j(g2d(gy, gm, gd)); }
  function toGregorian(jy, jm, jd) { return d2g(j2d(jy, jm, jd)); }
  function isLeapJ(jy) { return jalCal(jy).leap === 0; }
  function jDaysInMonth(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isLeapJ(jy) ? 30 : 29;
  }

  var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
  function toFa(s) { return String(s).replace(/[0-9]/g, function (d) { return FA_DIGITS[+d]; }); }
  var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  // headers starting Saturday (Persian week)
  var WDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
  var WDAYS_SHORT = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

  function today() {
    var d = new Date();
    return toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate());
  }
  // serial number for ordering/comparison (real chronological order)
  function serial(jy, jm, jd) { return j2d(jy, jm, jd); }
  function pad2(n) { return n < 10 ? '0' + n : '' + n; }
  function fmt(jy, jm, jd) { return jy + '/' + pad2(jm) + '/' + pad2(jd); }
  // Persian weekday index of a Jalali date, 0=Saturday .. 6=Friday
  function weekdayOf(jy, jm, jd) {
    var g = toGregorian(jy, jm, jd);
    var wd = new Date(g.gy, g.gm - 1, g.gd).getDay(); // 0=Sun..6=Sat
    return (wd + 1) % 7;
  }

  /**
   * Build a calendar into `host`.
   * opts:
   *   mode: 'picker' | 'blocker'
   *   getBlocked: function -> { weekdays:[int], dates:[ 'YYYY/MM/DD' ] }  (live)
   *   disablePast: bool (picker default true)
   *   selected: 'YYYY/MM/DD' | null
   *   onSelect: function(dateStr)         (picker)
   *   onToggleDate: function(dateStr)     (blocker — toggle a specific date)
   * returns { setSelected, refresh }
   */
  function build(host, opts) {
    opts = opts || {};
    var mode = opts.mode || 'picker';
    var t = today();
    var view = { y: t.jy, m: t.jm };
    if (opts.selected && /^\d{3,4}\/\d{1,2}\/\d{1,2}$/.test(opts.selected)) {
      var p = opts.selected.split('/');
      view.y = +p[0]; view.m = +p[1];
    }
    var selected = opts.selected || null;

    host.classList.add('jcal');
    host.innerHTML =
      '<div class="jcal-hd">' +
        '<button type="button" class="jcal-nav" data-dir="-1" aria-label="ماه قبل">‹</button>' +
        '<div class="jcal-title"></div>' +
        '<button type="button" class="jcal-nav" data-dir="1" aria-label="ماه بعد">›</button>' +
      '</div>' +
      '<div class="jcal-wk">' + WDAYS_SHORT.map(function (w) { return '<span>' + w + '</span>'; }).join('') + '</div>' +
      '<div class="jcal-grid"></div>';

    var titleEl = host.querySelector('.jcal-title');
    var gridEl = host.querySelector('.jcal-grid');

    function render() {
      var blocked = (opts.getBlocked && opts.getBlocked()) || { weekdays: [], dates: [] };
      var bWk = blocked.weekdays || [], bDates = blocked.dates || [];
      var tt = today();
      var todaySerial = serial(tt.jy, tt.jm, tt.jd);
      titleEl.textContent = MONTHS[view.m - 1] + ' ' + toFa(view.y);
      var firstWd = weekdayOf(view.y, view.m, 1);
      var dim = jDaysInMonth(view.y, view.m);
      var cells = '';
      for (var i = 0; i < firstWd; i++) cells += '<span class="jcal-cell empty"></span>';
      for (var d = 1; d <= dim; d++) {
        var wd = (firstWd + d - 1) % 7;
        var ds = fmt(view.y, view.m, d);
        var ser = serial(view.y, view.m, d);
        var isPast = (opts.disablePast !== false && mode === 'picker') && ser < todaySerial;
        var isWkBlocked = bWk.indexOf(wd) !== -1;
        var isDateBlocked = bDates.indexOf(ds) !== -1;
        var cls = 'jcal-cell day';
        if (ser === todaySerial) cls += ' today';
        if (mode === 'picker') {
          var off = isPast || isWkBlocked || isDateBlocked;
          if (off) cls += ' off';
          if (selected === ds) cls += ' sel';
        } else { // blocker
          if (isDateBlocked) cls += ' blocked';
          if (isWkBlocked) cls += ' wk-off';
          if (ser < todaySerial) cls += ' dim';
        }
        cells += '<button type="button" class="' + cls + '" data-date="' + ds + '">' + toFa(d) + '</button>';
      }
      gridEl.innerHTML = cells;
    }

    host.querySelectorAll('.jcal-nav').forEach(function (btn) {
      btn.addEventListener('click', function () {
        view.m += (+btn.dataset.dir);
        if (view.m < 1) { view.m = 12; view.y -= 1; }
        else if (view.m > 12) { view.m = 1; view.y += 1; }
        render();
      });
    });

    gridEl.addEventListener('click', function (e) {
      var cell = e.target.closest('.jcal-cell.day');
      if (!cell) return;
      var ds = cell.dataset.date;
      if (mode === 'picker') {
        if (cell.classList.contains('off')) return;
        selected = ds;
        render();
        if (opts.onSelect) opts.onSelect(ds);
      } else {
        if (opts.onToggleDate) opts.onToggleDate(ds);
        render();
      }
    });

    render();
    return {
      refresh: render,
      setSelected: function (ds) { selected = ds || null; if (ds) { var pp = ds.split('/'); view.y = +pp[0]; view.m = +pp[1]; } render(); }
    };
  }

  global.NobaJalali = {
    today: today, toJalaali: toJalaali, toGregorian: toGregorian,
    fmt: fmt, toFa: toFa, weekdayOf: weekdayOf, WDAYS: WDAYS, build: build
  };
})(window);
