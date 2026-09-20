/* AQM Mortgage Calculator 1.3.0 - Copyright (c) 2026 A. Q. Mufti. All rights reserved.
   Rules and sources are listed in aqm-mortgage-calculator.php */
(function () {
	'use strict';

	var M0 = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD', maximumFractionDigits: 0 });
	var M2 = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD', minimumFractionDigits: 2, maximumFractionDigits: 2 });
	var N0 = new Intl.NumberFormat('en-CA', { maximumFractionDigits: 0 });
	var COLORS = ['#A62021', '#2E6DB4', '#A87615'], NAMES = ['Scenario A', 'Scenario B', 'Scenario C'];
	var NS = 'http://www.w3.org/2000/svg';
	/* Other costs of buying: typical Ontario amounts, editable per scenario in the table. */
	var COSTS = [
		{ key: 'legal', name: 'Lawyer\u2019s fee (incl. HST)', amt: 1800, when: 'at' },
		{ key: 'disb', name: 'Disbursements and registration', amt: 600, when: 'at' },
		{ key: 'title', name: 'Title insurance', amt: 400, when: 'at' },
		{ key: 'adjust', name: 'Property tax and utility adjustments', amt: 1000, when: 'at' },
		{ key: 'insure', name: 'Home insurance (first year)', amt: 1500, when: 'at' },
		{ key: 'moving', name: 'Moving', amt: 1500, when: 'at' },
		{ key: 'misc1', name: 'Miscellaneous 1', suggest: 'Survey', amt: 0, when: 'at', misc: true },
		{ key: 'misc2', name: 'Miscellaneous 2', suggest: 'Utility hook-ups', amt: 0, when: 'at', misc: true },
		{ key: 'inspect', name: 'Home inspection', amt: 500, when: 'before' },
		{ key: 'appraise', name: 'Appraisal', amt: 400, when: 'before' },
		{ key: 'status', name: 'Condo status certificate (max $100)', amt: 0, when: 'before' }
	];

	var C = (typeof AQMMC !== 'undefined') ? AQMMC : (typeof require === 'function' ? require('./aqm-mc-core.js') : null);
	if (!C) { return; }
	var R = C.R, JUR = C.JUR, JORDER = C.JORDER, jur = C.jur, brackets = C.brackets, taper = C.taper,
		hstFor = C.hstFor, hstFromAllIn = C.hstFromAllIn, periodic = C.periodic, pmt = C.pmt;


	/* ---------------------------------------------------------- number input */
	function parseNum(s) { var v = parseFloat(String(s).replace(/[^0-9.]/g, '')); return isFinite(v) ? v : 0; }
	function cleanPct(s) {
		s = String(s).replace(/[^0-9.]/g, '');
		var i = s.indexOf('.');
		if (i >= 0) { s = s.slice(0, i + 1) + s.slice(i + 1).replace(/\./g, '').slice(0, 2); }
		return s;
	}
	function fmtMoneyText(s) { var d = String(s).replace(/[^0-9]/g, '').replace(/^0+(?=\d)/, ''); return d === '' ? '' : N0.format(+d); }
	/* Reformat while typing and keep the caret after the same digit. */
	function liveFormat(el) {
		var kind = el.getAttribute('data-fmt'), v = el.value, pos = el.selectionStart == null ? v.length : el.selectionStart;
		var before = v.slice(0, pos).replace(kind === 'money' ? /[^0-9]/g : /[^0-9.]/g, '').length;
		var out = kind === 'money' ? fmtMoneyText(v) : cleanPct(v);
		if (out === v) { return; }
		el.value = out;
		var n = 0, p = 0;
		while (p < out.length && n < before) { if (/[0-9.]/.test(out[p])) { n++; } p++; }
		try { el.setSelectionRange(p, p); } catch (e) { /* not focusable */ }
	}
	function setField(el, v, kind) {
		if (document.activeElement === el) { return; } // never fight the person typing
		el.value = kind === 'money' ? N0.format(Math.round(v)) : String(Math.round(v * 100) / 100);
	}
	function fl(x) { return (Math.round(x * 100) / 100).toFixed(2).replace(/\.?0+$/, ''); }
	/* Lender names and labels come from scraped pages and from the settings screen, so they are
	   treated as text, never as markup, wherever they are written into innerHTML. */
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	/* --------------------------------------------------------------- instance */
	function init(root) {
		var cfg = {};
		try { cfg = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { cfg = {}; }
		var q = function (s, c) { return (c || root).querySelector(s); };
		var qa = function (s, c) { return Array.prototype.slice.call((c || root).querySelectorAll(s)); };
		var scs = qa('.aqm-mc__sc');
		var dollarLock = [false, false, false], state = { scen: 0, view: 'year' }, results = [], ctx = {}, ctxs = [];

		/* Lender rates, when the site has any: a drop-down above each scenario's rate box. */
		var RATES = (cfg.rates || []).filter(function (r) { return r && +r.rate > 0; })
			.sort(function (a, b) { return (+a.rate) - (+b.rate); }); // lowest rate first
		/* A native <select> shows the chosen option inside a fixed-width box and simply cuts off what
		   does not fit. "4.09% - True North Mortgage - 5-year fixed, insured" needs about 410px and a
		   phone gives it roughly 300, so the lender and the product - the two things that make a rate
		   mean anything - were the parts being cut. The option text cannot wrap and cannot be made
		   responsive, so the fix is not in the select: the full choice is echoed underneath it, where
		   it is free to wrap. That line was a fixed hint before and is now worth reading. */
		function echoPick(i) {
			var sel = q('[data-pick="' + i + '"]'), hint = sel && sel.parentNode.querySelector('.aqm-mc__hint');
			if (!hint) { return; }
			var r = sel.value === '' ? null : RATES[+sel.value];
			if (!r) {
				hint.innerHTML = '…or just type a rate in the Interest rate box below.';
				return;
			}
			hint.innerHTML = '<b>' + (+r.rate).toFixed(2) + '%</b> &middot; ' + esc(r.lender) + ' &middot; ' + esc(r.label)
				/* A date, never a verdict. Lenders hold a posted rate for weeks, so an old reading is
				   usually the same rate still standing; the day it was read lets the reader judge
				   that, instead of being told what to think. NB: comments in this file are served to
				   the browser, so the phrase this replaced must not reappear even in one. */
				+ (r.date ? ' &middot; read ' + esc(r.date) : '');
		}

		if (RATES.length) {
			var opts = '<option value="">Type my own rate in the box below</option>' + RATES.map(function (r, i) {
				/* Where the old caveat sat, the option now carries the date it was read - the same
				   space, a fact instead of a judgement. Only on the older ones: adding a date to
				   every option would push an already-overlong option further past what a phone shows;
				   the echo line below dates all of them, where there is room to wrap. */
				return '<option value="' + i + '">' + (+r.rate).toFixed(2) + '% \u2013 ' + esc(r.lender) + ' \u2013 ' + esc(r.label) + (r.stale ? ' (read ' + esc(r.date) + ')' : '') + '</option>';
			}).join('');
			scs.forEach(function (el, i) {
				var box = document.createElement('div');
				box.className = 'aqm-mc__pick';
				box.innerHTML = '<label for="' + root.id + '-pick' + i + '">Interest rate: pick a lender&rsquo;s rate\u2026</label>'
					+ '<select id="' + root.id + '-pick' + i + '" data-pick="' + i + '">' + opts + '</select>'
					+ '<span class="aqm-mc__hint"></span>';
				// Sit the picker directly above the Interest rate box, not above Down payment, so the
				// two rate controls read as one thing and "the box below" means the box below.
				var rateRow = el.querySelector('[data-k=rate]').closest('.aqm-mc__two');
				el.insertBefore(box, rateRow || el.querySelector('.aqm-mc__two'));
				echoPick(i);
			});
			var note = document.createElement('p');
			note.className = 'aqm-mc__ratenote';
			var srcs = [], seen = {};
			RATES.forEach(function (r) { if (r.url && !seen[r.url]) { seen[r.url] = 1; srcs.push('<a href="' + esc(r.url) + '" target="_blank" rel="noopener nofollow">' + esc(r.lender) + '</a>'); } });
			note.innerHTML = 'The rates in the drop-downs are the lenders\u2019 own published rates, read on the dates listed, and rates typed in by A. Q. Mufti. They are not offers and nobody is approved at them: your rate depends on the lender, the property and you. Confirm any rate with the lender or a licensed mortgage agent.' + (srcs.length ? ' Sources: ' + srcs.join(', ') + '.' : '');
			root.querySelector('.aqm-mc__scen').parentNode.appendChild(note);
		}

		/* ------------------------------------------------- one property, or three
		   AQ, 20 Sep 2026: "bring the property tabs down to each scenario so it'll give flexibility
		   to either have one property with 3 rate, DP scenarios or 3 different properties with same
		   or different rates etc."

		   So every scenario owns a whole property - price, place, municipality, frequency, term,
		   first-time buyer, new build. B and C FOLLOW A until told otherwise, which keeps the common
		   case exactly as cheap as it was: type the price once and compare down payments.

		   While a scenario follows A its own property block is hidden and never read - the figures
		   come from A - so the two can never quietly disagree. Pressing "Use a different property"
		   copies A's values across and hands that scenario the wheel; there is always a way back. */
		var linked = [false, true, true];
		function propEl(i) { return linked[i] ? scs[0] : scs[i]; }

		var PROP_KEYS = ['price', 'loc', 'freq', 'term', 'hstmode'];
		var PROP_CHKS = ['ftb', 'newbuild'];

		scs.forEach(function (el, i) {
			var locSel = q('[data-k=loc]', el);
			locSel.innerHTML = JORDER.map(function (k) { return '<option value="' + k + '">' + JUR[k].name + '</option>'; }).join('');
			locSel.value = JUR[cfg.loc] ? cfg.loc : 'on';

			/* Quebec and Nova Scotia set their transfer tax municipally, so those two need a second
			   choice. One per scenario now, since two scenarios can sit in different provinces. */
			var subWrap = document.createElement('div');
			subWrap.className = 'aqm-mc__sub';
			subWrap.innerHTML = '<label for="' + root.id + '-sub' + i + '"></label><select id="' + root.id + '-sub' + i + '" data-k="sub"></select>';
			locSel.parentNode.parentNode.insertBefore(subWrap, locSel.parentNode.nextSibling);

			q('[data-k=price]', el).value = N0.format(cfg.price || 850000);
			q('[data-k=dpct]', el).value = String((cfg.downs || [10, 15, 20])[i]);
			q('[data-k=rate]', el).value = String(cfg.rate || 4.19);
			q('[data-k=amort]', el).value = String(cfg.amort || 25);
		});

		function fillSub(el) {
			var J = jur(q('[data-k=loc]', el).value), sel = q('[data-k=sub]', el), wrap = el.querySelector('.aqm-mc__sub');
			if (!J.subs) { wrap.style.display = 'none'; sel.innerHTML = ''; return; }
			var keys = Object.keys(J.subs), keep = sel.value;
			wrap.style.display = '';
			wrap.querySelector('label').innerHTML = J.subLabel || 'Which municipality';
			sel.innerHTML = keys.map(function (k) { return '<option value="' + k + '">' + J.subs[k].name + '</option>'; }).join('');
			sel.value = ( keys.indexOf(keep) > -1 ) ? keep : keys[0];
		}
		scs.forEach(function (el) { fillSub(el); });

		/** Copies A's whole property onto another scenario, so unlinking starts from what was shown. */
		function copyProp(from, to) {
			PROP_KEYS.forEach(function (k) { q('[data-k=' + k + ']', to).value = q('[data-k=' + k + ']', from).value; });
			PROP_CHKS.forEach(function (k) { q('[data-k=' + k + ']', to).checked = q('[data-k=' + k + ']', from).checked; });
			fillSub(to);
			q('[data-k=sub]', to).value = q('[data-k=sub]', from).value;
			q('[data-k=sub]', to).setAttribute('data-for', q('[data-k=loc]', to).value);
		}

		/** Draws each scenario's "same as A" bar and shows or hides its property block. */
		function syncLinks() {
			scs.forEach(function (el, i) {
				if (i === 0) { return; }
				var bar = q('[data-k=linkbar]', el), btn = q('[data-k=linktoggle]', el), sum = q('[data-k=linksum]', el);
				var prop = q('[data-k=prop]', el);
				prop.style.display = linked[i] ? 'none' : '';
				bar.className = 'aqm-mc__linkbar' + (linked[i] ? ' is-linked' : '');
				if (linked[i]) {
					var a = scs[0], J = jur(q('[data-k=loc]', a).value);
					sum.innerHTML = 'Same property as <b>Scenario A</b><small>' + M0.format(parseNum(q('[data-k=price]', a).value)) + ' &middot; ' + J.name + '</small>';
					btn.textContent = 'Use a different property';
				} else {
					sum.innerHTML = '<b>Its own property</b>';
					btn.textContent = 'Same as Scenario A';
				}
			});
		}

		scs.forEach(function (el, i) {
			if (i === 0) { return; }
			q('[data-k=linktoggle]', el).addEventListener('click', function () {
				if (linked[i]) { copyProp(scs[0], el); linked[i] = false; }
				else { linked[i] = true; }
				syncLinks(); compute();
			});
		});

		function costRows(when) {
			return COSTS.filter(function (c) { return c.when === when; }).map(function (c) {
				var label = c.misc ? '<input type="text" list="' + root.id + '-misc-list" data-misc-name="' + c.key + '" value="' + c.suggest + '" placeholder="' + c.name + ' (describe)" title="Type your own or pick a suggestion" aria-label="' + c.name + ' description">' : c.name;
				var cells = [0, 1, 2].map(function (i) {
					return '<td><div class="aqm-mc__pfx"><span>$</span><input type="text" inputmode="numeric" autocomplete="off" data-cost="' + c.key + '" data-col="' + i + '" data-fmt="money" value="' + N0.format(c.amt) + '" aria-label="' + c.name + ', ' + NAMES[i] + '"></div></td>';
				}).join('');
				return '<tr class="aqm-mc__cost"><td>' + label + '</td>' + cells + '</tr>';
			}).join('');
		}
		var MISC = ['Survey', 'Utility hook-ups', 'Home warranty', 'Water or septic inspection', 'Condo move-in fee', 'Furniture and appliances', 'Repairs or renovations', 'Mortgage broker or lender fee', 'Locksmith and security', 'Cleaning'];
		var dl = document.createElement('datalist'); dl.id = root.id + '-misc-list';
		dl.innerHTML = MISC.map(function (m) { return '<option value="' + m + '">'; }).join('');
		root.appendChild(dl);
		q('[data-part=at]').innerHTML = costRows('at');
		q('[data-part=before]').innerHTML = costRows('before');

		/**
		 * Everything one property implies. Read from whichever scenario owns it - a linked scenario
		 * reads A's, so the figures cannot drift from what the screen shows.
		 */
		function readProp(i) {
			var el = propEl(i);
			var entered = parseNum(q('[data-k=price]', el).value), loc = q('[data-k=loc]', el).value, fv = q('[data-k=freq]', el).value;
			var term = +q('[data-k=term]', el).value, ftb = q('[data-k=ftb]', el).checked, nb = q('[data-k=newbuild]', el).checked, hm = q('[data-k=hstmode]', el).value;
			q('[data-k=hstwrap]', el).classList.toggle('is-on', nb);
			var J = jur(loc);
			var sub = q('[data-k=sub]', el);
			if (sub.getAttribute('data-for') !== loc) { fillSub(el); sub.setAttribute('data-for', loc); }
			var S = ( J.subs && J.subs[ sub.value ] ) ? J.subs[ sub.value ] : null;
			var hst = null, price = entered;
			if (nb && entered > 0) {
				if (hm === 'plus') { hst = hstFor(entered, ftb, J); price = entered + hst.net; } else { hst = hstFromAllIn(entered, ftb, J); }
			}
			var ltt = ( S ? S.tax : J.tax )( price );
			/* B.C.'s two exemptions are mutually exclusive, so take whichever is worth more. */
			var ontRebate = Math.max(
				(ftb && J.ftb) ? J.ftb(price, ltt) : 0,
				(nb && J.nbEx) ? J.nbEx(price, ltt) : 0
			);
			var usedNbEx = J.nbEx && nb && ontRebate > ((ftb && J.ftb) ? J.ftb(price, ltt) : 0);
			var mltt = J.muni ? J.muni(price) : 0;
			var torRebate = (ftb && J.muniFtb) ? J.muniFtb(price, mltt) : 0;
			return {
				entered: entered, price: price, loc: loc, J: J, S: S, fv: fv, term: term, ftb: ftb, nb: nb, hm: hm,
				hst: hst, ltt: ltt, ontRebate: ontRebate, usedNbEx: usedNbEx, mltt: mltt, torRebate: torRebate,
				perYear: parseInt(fv, 10), accel: /a$/.test(fv), own: !linked[i]
			};
		}

		/** True when every scenario is looking at the same property, which is the usual case. */
		function onePlace() { return !linked.some(function (l, i) { return i > 0 && !l; }); }

		function compute() {
			ctxs = [0, 1, 2].map(readProp);
			ctx = ctxs[0];
			var colCost = [0, 1, 2].map(function (i) {
				var at = 0, before = 0;
				qa('[data-cost][data-col="' + i + '"]').forEach(function (el) {
					var c = COSTS.filter(function (x) { return x.key === el.getAttribute('data-cost'); })[0];
					if (c && c.when === 'before') { before += parseNum(el.value); } else { at += parseNum(el.value); }
				});
				return { at: at, before: before };
			});

			results = scs.map(function (el, i) {
				var c = ctxs[i], price = c.price, J = c.J, ftb = c.ftb, nb = c.nb;
				var perYear = c.perYear, accel = c.accel, term = c.term;
				var ltt = c.ltt, ontRebate = c.ontRebate, mltt = c.mltt, torRebate = c.torRebate;
				var dp = q('[data-k=dpct]', el), dd = q('[data-k=ddol]', el);
				var down;
				if (dollarLock[i]) { down = Math.min(parseNum(dd.value), price); setField(dp, price > 0 ? down / price * 100 : 0, 'pct'); }
				else { down = price * Math.min(parseNum(dp.value), 100) / 100; setField(dd, down, 'money'); }
				var rate = parseNum(q('[data-k=rate]', el).value) / 100, amort = +q('[data-k=amort]', el).value, warn = [];
				var min = R.minDown(price), base = Math.max(price - down, 0), ltv = price > 0 ? base / price : 0, pr = 0, insured = ltv > 0.8;
				if (price > 0 && down + 0.5 < min) { warn.push('Below the minimum down payment of ' + M0.format(Math.ceil(min)) + '.'); }
				if (insured) {
					if (price >= R.cap) { warn.push('At ' + M0.format(R.cap) + ' or more, at least 20% down is required.'); }
					pr = R.premRate(ltv) || 0;
					if (amort > 25) {
						if (ftb || nb) { pr += 0.002; }
						else { warn.push('With under 20% down, a 30-year amortization is only for first-time buyers or new builds. Tick one above or choose 25 years.'); }
					}
				}
				if (rate <= 0) { warn.push('Enter an interest rate.'); }
				var prem = base * pr, loan = base + prem, pst = prem * (J.pst || 0);
				var mtgFee = J.mtgFee ? J.mtgFee(loan) : 0;
				var monthly = pmt(loan, periodic(rate, 12), 12 * amort);
				var r = periodic(rate, perYear), pay = accel ? (perYear === 26 ? monthly / 2 : monthly / 4) : pmt(loan, r, perYear * amort);
				var rows = [], bal = loan, cumI = 0, k = 0, guard = perYear * 40;
				while (bal > 0.005 && k < guard && pay > 0) {
					k++;
					var it = bal * r, pa = Math.min(pay, bal + it), pp = pa - it;
					bal = Math.max(bal - pp, 0); cumI += it;
					rows.push({ n: k, year: Math.ceil(k / perYear), pay: pa, prin: pp, int: it, bal: bal, cumI: cumI });
				}
				var termRows = rows.slice(0, term * perYear), tI = 0, tP = 0, tPay = 0;
				termRows.forEach(function (x) { tI += x.int; tP += x.prin; tPay += x.pay; });
				var w = q('.aqm-mc__warn', el); w.textContent = warn.join(' '); w.style.display = warn.length ? 'block' : 'none';
				return {
					down: down, pct: price > 0 ? down / price * 100 : 0, min: min, pr: pr, prem: prem, pst: pst, loan: loan, pay: pay, monthly: monthly, rows: rows, perYear: perYear,
					tI: tI, tP: tP, tPay: tPay, balTerm: termRows.length ? termRows[termRows.length - 1].bal : loan, totI: cumI, totPaid: cumI + loan,
					payoff: rows.length / perYear, years: Math.max(1, Math.ceil(rows.length / perYear)), amort: amort, rate: rate, insured: insured,
					atCost: colCost[i].at, beforeCost: colCost[i].before, mtgFee: mtgFee,
					closing: down + ltt - ontRebate + mltt - torRebate + pst + mtgFee + colCost[i].at,
					cash: down + ltt - ontRebate + mltt - torRebate + pst + mtgFee + colCost[i].at + colCost[i].before
				};
			});
			renderKpis(); renderCompare(); renderPrograms(); renderChart(); renderSched();
		}

		var FREQ = { '12': 'Monthly', '24': 'Semi-monthly', '26': 'Bi-weekly', '26a': 'Accelerated bi-weekly', '52': 'Weekly', '52a': 'Accelerated weekly' };

		function renderKpis() {
			q('[data-k=kpis]').innerHTML = results.map(function (x, i) {
				var c = ctxs[i];
				return '<div class="aqm-mc__kpi" style="--c:' + COLORS[i] + '"><small>' + NAMES[i] + ' &middot; ' + fl(x.pct) + '% down'
					+ (onePlace() ? '' : ' &middot; ' + (c.J.short || c.J.name) + ' ' + M0.format(c.price))
					+ '</small><strong>' + M2.format(x.pay) + '</strong><span>' + FREQ[c.fv].toLowerCase() + ' &middot; mortgage ' + M0.format(x.loan) + ' &middot; cash needed ' + M0.format(x.cash) + '</span></div>';
			}).join('');
		}

		function renderCompare() {
			var row = function (label, fn, cls) { return '<tr' + (cls ? ' class="' + cls + '"' : '') + '><td>' + label + '</td>' + results.map(function (x, i) { return '<td>' + fn(x, i) + '</td>'; }).join('') + '</tr>'; };
			/* A property row. Each column reads its OWN property, so three different places show
			   three different taxes; when they are all the same property every cell matches, which
			   is exactly what this table showed before any of this existed. */
			var prow = function (label, fn, cls) { return '<tr' + (cls ? ' class="' + cls + '"' : '') + '><td>' + label + '</td>' + ctxs.map(function (c, i) { return '<td>' + fn(c, i) + '</td>'; }).join('') + '</tr>'; };
			var same = function (label, v, cls) { return row(label, function () { return v; }, cls); };
			var group = function (label) { return '<tr class="aqm-mc__group"><td colspan="4">' + label + '</td></tr>'; };
			/* A label can only name one thing. Where the three properties agree it says what they
			   are; where they differ it falls back to a general name rather than lying about two
			   of the columns. */
			var agree = function (fn) { var a = fn(ctxs[0]); return ctxs.every(function (c) { return fn(c) === a; }) ? a : null; };
			var anyHst = ctxs.some(function (c) { return !!c.hst; });

			var out = group('Property');
			if (!onePlace()) {
				out += prow('Which property', function (c, i) { return c.own ? '<b>Its own</b>' : 'Same as A'; }, 'aqm-mc__notes')
					+ prow('Where', function (c) { return (c.J.short || c.J.name) + (c.S ? '<small><br>' + c.S.name + '</small>' : ''); });
			}
			out += prow('Purchase price' + (anyHst ? '' : ''), function (c) {
				return M0.format(c.price) + (c.hst ? '<small><br>' + (c.hm === 'plus' ? 'incl. net HST' : 'all-in') + '</small>' : '');
			});
			if (anyHst) {
				var hl = agree(function (c) { return c.J.nhName; }) || 'sales tax';
				out += prow('Price before ' + hl, function (c) { return c.hst ? M0.format(c.hst.base) : '&mdash;'; })
					+ prow(hl.toUpperCase() === hl ? hl : hl, function (c) { return c.hst ? M0.format(c.hst.fed + c.hst.prov) + '<small><br>' + c.J.nhRate + '</small>' : '&mdash;'; })
					+ prow('Federal portion relief', function (c) { return c.hst ? '-' + M0.format(c.hst.fedRelief) + (c.hst.fthb >= c.hst.fedRelief && c.hst.fthb > 0 ? '<small><br>first-time buyer</small>' : '') : '&mdash;'; })
					+ prow('Provincial portion relief', function (c) { return c.hst ? '-' + M0.format(c.hst.provRelief) : '&mdash;'; })
					+ prow(hl + ' you pay after relief', function (c) { return c.hst ? M0.format(c.hst.net) : '&mdash;'; }, 'aqm-mc__em');
			}
			out += group('Mortgage')
				+ row('Down payment', function (x) { return M0.format(x.down) + ' <small>(' + fl(x.pct) + '%)</small>'; })
				+ row('Minimum down payment', function (x) { return M0.format(Math.ceil(x.min)); })
				+ row('CMHC insurance premium', function (x) { return x.pr ? M0.format(x.prem) + ' <small>(' + fl(x.pr * 100) + '%)</small>' : 'Not needed'; })
				+ row('Total mortgage', function (x) { return M0.format(x.loan); }, 'aqm-mc__em')
				+ row('Interest rate / amortization', function (x) { return fl(x.rate * 100) + '% / ' + x.amort + ' yrs'; })
				+ row((agree(function (c) { return c.fv; }) ? FREQ[ctx.fv] + ' payment' : 'Payment'), function (x, i) { return M2.format(x.pay) + (agree(function (c) { return c.fv; }) ? '' : '<small><br>' + FREQ[ctxs[i].fv].toLowerCase() + '</small>'); }, 'aqm-mc__em')
				+ row('Paid off in', function (x) { return (Math.round(x.payoff * 10) / 10) + ' years'; })
				+ group(agree(function (c) { return c.term; }) ? 'Over the ' + ctx.term + '-year term' : 'Over each scenario&rsquo;s term')
				+ row('Total payments', function (x) { return M0.format(x.tPay); })
				+ row('Interest paid', function (x) { return M0.format(x.tI); })
				+ row('Principal paid', function (x) { return M0.format(x.tP); })
				+ row('Share of mortgage repaid', function (x) { return x.loan > 0 ? fl(x.tP / x.loan * 100) + '%' : '0%'; })
				+ row('Balance at end of term', function (x) { return M0.format(x.balTerm); }, 'aqm-mc__em')
				+ group('Over the full amortization')
				+ row('Total interest', function (x) { return M0.format(x.totI); })
				+ row('Total of all payments', function (x) { return M0.format(x.totPaid); })
				+ group('Cash needed at closing')
				+ '<tr class="aqm-mc__notes"><td colspan="4">Typical amounts are filled in below the taxes; type over any of them, per scenario, to match your quotes.</td></tr>'
				+ row('Down payment', function (x) { return M0.format(x.down); })
				+ prow(agree(function (c) { return c.J.taxName + (c.S ? ' &mdash; ' + c.S.name : ''); }) || 'Land transfer tax',
					function (c) { return M0.format(c.ltt) + (agree(function (z) { return z.J.taxName; }) ? '' : '<small><br>' + c.J.taxName + '</small>'); })
				+ (ctxs.some(function (c) { return c.ontRebate > 0; })
					? prow(agree(function (c) { return c.usedNbEx ? c.J.nbExName : c.J.ftbName; }) || 'First-time buyer / new-build relief',
						function (c) { return c.ontRebate > 0 ? '-' + M0.format(c.ontRebate) : '&mdash;'; }) : '')
				+ (ctxs.some(function (c) { return c.J.muni; })
					? prow(agree(function (c) { return c.J.muniName; }) || 'Municipal land transfer tax',
						function (c) { return c.J.muni ? M0.format(c.mltt) : '&mdash;'; }) : '')
				+ (ctxs.some(function (c) { return c.J.muni && c.torRebate > 0; })
					? prow(agree(function (c) { return c.J.muniFtbName; }) || 'Municipal first-time buyer rebate',
						function (c) { return c.torRebate > 0 ? '-' + M0.format(c.torRebate) : '&mdash;'; }) : '')
				+ (ctxs.some(function (c) { return c.J.pst; })
					? row(agree(function (c) { return c.J.pstName; }) || 'Tax on the insurance premium',
						function (x, i) { return ctxs[i].J.pst ? M0.format(x.pst) : '&mdash;'; }) : '')
				+ (ctxs.some(function (c) { return c.J.mtgFee; })
					? row('Mortgage registration fee', function (x, i) { return ctxs[i].J.mtgFee ? M0.format(x.mtgFee) : '&mdash;'; }) : '');
			q('[data-part=top]').innerHTML = out;
			q('[data-part=mid]').innerHTML = row('Total due at closing', function (x) { return M0.format(x.closing); }, 'aqm-mc__em')
				+ group('Paid before closing')
				+ '<tr class="aqm-mc__notes"><td colspan="4">Usually paid while the offer is still conditional, before closing day.</td></tr>';
			q('[data-part=bottom]').innerHTML = row('Total paid before closing', function (x) { return M0.format(x.beforeCost); }, 'aqm-mc__em')
				+ group('All cash you need')
				+ row('Total cash needed to buy', function (x) { return M0.format(x.cash); }, 'aqm-mc__em');
		}

		/**
		 * The programs are a property's, not a mortgage's - first-time buyer relief, new-home tax
		 * relief, the provincial rebate all follow the price and the place. With three properties in
		 * play there is no single right answer, so this follows the scenario selected for the
		 * schedule below and says which one it is describing.
		 */
		function renderPrograms() {
			var pick = onePlace() ? 0 : state.scen;
			var c = ctxs[pick], items = [], anyInsured = results.some(function (x) { return x.insured; });
			var li = function (cls, title, body) { items.push('<li class="is-' + cls + '"><b>' + title + '</b>' + body + '</li>'); };
			if (!onePlace()) {
				items.push('<li class="is-note aqm-mc__forwhich"><b>These apply to ' + NAMES[pick] + '</b>'
					+ M0.format(c.price) + ' in ' + c.J.name + (c.S ? ', ' + c.S.name : '')
					+ '. The scenarios hold different properties, so pick another above to see its programs.</li>');
			}
			li(c.price >= R.cap && anyInsured ? 'warn' : 'yes', 'Down payment and the $1.5 million limit',
				'Minimum for this price: <em>' + M0.format(Math.ceil(R.minDown(c.price))) + '</em> (5% of the first $500,000, 10% of the rest up to $1.5 million). Mortgage insurance, and so less than 20% down, is available up to <em>$1,499,999</em>.');
			li(c.ftb || c.nb ? 'yes' : 'no', '30-year amortization with less than 20% down',
				c.ftb || c.nb ? 'Available: ' + (c.ftb && c.nb ? 'first-time buyer and new build' : (c.ftb ? 'first-time buyer' : 'new build')) + '. Insurance premium is 0.20% higher than at 25 years.' : 'Only for first-time buyers or new builds. With 20% or more down, any buyer can choose 30 years.');
			var J = c.J;
			if (J.ftb) {
				li(c.ftb ? 'yes' : 'no', J.ftbName + ' (' + J.short + ')',
					c.ftb ? (J.ftbMax.charAt(0).toUpperCase() + J.ftbMax.slice(1)) + ': <em>' + M0.format((J.ftb(c.price, c.ltt))) + '</em> for this price.'
						  : 'First-time buyers get ' + J.ftbMax + '.');
			} else if (!J.noTax) {
				li('no', 'First-time buyer relief (' + J.short + ')', J.credit || (J.short + ' gives first-time buyers no rebate on the ' + J.taxName.toLowerCase() + '.'));
			}
			if (J.nbEx) {
				li(c.nb ? 'yes' : 'no', J.nbExName + ' (' + J.short + ')',
					(c.nb ? 'Worth <em>' + M0.format(J.nbEx(c.price, c.ltt)) + '</em> at this price: ' : 'Tick &ldquo;Newly built home&rdquo; above. ') + J.nbExMax + '. Open to any buyer, not just first-time buyers, and you take this or the first-time buyer exemption &mdash; whichever is worth more.');
			}
			if (J.muni) { li(c.ftb ? 'yes' : 'no', J.muniFtbName, c.ftb ? J.muniFtbMax.charAt(0).toUpperCase() + J.muniFtbMax.slice(1) + ': <em>' + M0.format(c.torRebate) + '</em> for this price.' : 'First-time buyers get ' + J.muniFtbMax + ' back.'); }
			if (J.credit && J.ftb) { li('note', 'Also in ' + J.short, J.credit); }
			if (J.noTax) { li('yes', 'No land transfer tax in ' + J.short, 'You pay a land titles fee instead, which is far smaller. ' + (J.taxNote || '')); }
			if (J.taxNote && !J.noTax) { li('note', 'Set by your municipality', (c.S && c.S.note ? c.S.note + ' ' : '') + J.taxNote); }
			if (J.pstNote) { li('note', 'Tax on the insurance premium', J.pstNote); }
			if (c.nb && c.hst) {
				li(c.hst.fedRelief + c.hst.provRelief > 0 ? 'yes' : 'no', 'New-home ' + J.nhName + ' relief',
					'Estimated <em>' + M0.format(c.hst.fedRelief + c.hst.provRelief) + '</em> of <em>' + M0.format(c.hst.fed + c.hst.prov) + '</em> ' + J.nhName + ' (' + J.nhRate + ') on ' + M0.format(c.hst.base) + ' before tax. Federally, the New Housing Rebate gives back 36% of the 5% GST to a maximum of $6,300, gone at $450,000; a first-time buyer also gets up to $50,000 more, in full to $1 million and nothing at $1.5 million. ' + (J.nhReliefNote || (J.short + ' adds nothing of its own.')) + ' For a home you will live in; builders usually credit it in the price.');
			} else {
				li('no', 'New-home ' + J.nhName + ' relief', 'Buying new from a builder? Tick &ldquo;Newly built home&rdquo; above to see the ' + J.nhName + ' and what comes back.');
			}
			li(c.ftb ? 'yes' : 'no', 'Home Buyers’ Amount (tax credit)', c.ftb ? 'Claim <em>$10,000</em> on your tax return for the year you buy. It is a credit at the lowest federal rate, so it is worth about <em>$1,400</em> off your 2026 federal tax (14% of $10,000).' : 'First-time buyers can claim $10,000, worth about $1,400 off their federal tax.');
			li(c.ftb ? 'yes' : 'no', 'FHSA and RRSP Home Buyers’ Plan', c.ftb ? 'FHSA: save up to $8,000 a year, $40,000 lifetime, tax-free for the down payment. RRSP: withdraw up to $60,000 each and repay it over 15 years &mdash; if you withdraw between 2026 and 2028, repayments do not start until the fifth year after the year you withdraw.' : 'Available to first-time buyers: FHSA savings up to $40,000 and RRSP withdrawals up to $60,000 each.');
			q('[data-k=programs]').innerHTML = items.join('');
		}

		function renderChart() {
			var svg = q('[data-k=chart]'), W = 900, H = 340, ml = 78, mr = 24, mt = 14, mb = 36;
			while (svg.firstChild) { svg.removeChild(svg.firstChild); }
			var maxY = Math.max.apply(null, results.map(function (x) { return x.loan; })) || 1;
			var maxX = Math.max.apply(null, results.map(function (x) { return x.years; })) || 1;
			var nice = Math.pow(10, Math.floor(Math.log10(maxY))), top = Math.ceil(maxY / nice) * nice;
			if (top / nice <= 3) { nice /= 2; top = Math.ceil(maxY / nice) * nice; }
			var X = function (y) { return ml + (W - ml - mr) * y / maxX; }, Y = function (v) { return mt + (H - mt - mb) * (1 - v / top); };
			var add = function (tag, at, txt) { var e = document.createElementNS(NS, tag); for (var k in at) { e.setAttribute(k, at[k]); } if (txt != null) { e.textContent = txt; } svg.appendChild(e); return e; };
			for (var v = 0; v <= top + 1; v += nice) {
				add('line', { x1: ml, x2: W - mr, y1: Y(v), y2: Y(v), stroke: '#ececec', 'stroke-width': 1 });
				add('text', { x: ml - 8, y: Y(v) + 4, 'text-anchor': 'end', 'font-size': 12, fill: '#6b6b6b' }, v >= 1e6 ? ('$' + (v / 1e6).toFixed(v % 1e6 ? 1 : 0) + 'M') : ('$' + Math.round(v / 1000) + 'K'));
			}
			var step = maxX > 20 ? 5 : (maxX > 10 ? 2 : 1);
			for (var yr = 0; yr <= maxX; yr += step) { add('text', { x: X(yr), y: H - mb + 20, 'text-anchor': 'middle', 'font-size': 12, fill: '#6b6b6b' }, yr); }
			add('text', { x: (ml + W - mr) / 2, y: H - 2, 'text-anchor': 'middle', 'font-size': 12, fill: '#6b6b6b' }, 'Years');
			add('line', { x1: ml, x2: W - mr, y1: Y(0), y2: Y(0), stroke: '#bdbdbd', 'stroke-width': 1 });
			var balAt = function (x, y) { if (y <= 0) { return x.loan; } if (y * x.perYear > x.rows.length) { return 0; } return x.rows[y * x.perYear - 1].bal; };
			results.forEach(function (x, i) {
				var pts = [];
				for (var y = 0; y <= x.years; y++) { pts.push([y, balAt(x, y)]); }
				add('polyline', { points: pts.map(function (p) { return X(p[0]).toFixed(1) + ',' + Y(p[1]).toFixed(1); }).join(' '), fill: 'none', stroke: COLORS[i], 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' });
			});
			q('[data-k=legend]').innerHTML = results.map(function (x, i) { return '<span style="--c:' + COLORS[i] + '"><b></b>' + NAMES[i] + ' - ' + M0.format(x.loan) + '</span>'; }).join('');
			var cross = add('line', { x1: 0, x2: 0, y1: mt, y2: H - mb, stroke: '#9a9a9a', 'stroke-width': 1, visibility: 'hidden' });
			var dots = results.map(function (x, i) { return add('circle', { r: 5, fill: COLORS[i], stroke: '#fff', 'stroke-width': 2, visibility: 'hidden' }); });
			var hit = add('rect', { x: ml, y: mt, width: W - ml - mr, height: H - mt - mb, fill: 'transparent' });
			var tip = q('[data-k=tip]');
			function move(ev) {
				var p = ev.touches ? ev.touches[0] : ev, rect = svg.getBoundingClientRect(), sx = (p.clientX - rect.left) * W / rect.width;
				var y = Math.max(0, Math.min(maxX, Math.round((sx - ml) / (W - ml - mr) * maxX)));
				cross.setAttribute('x1', X(y)); cross.setAttribute('x2', X(y)); cross.setAttribute('visibility', 'visible');
				var html = '<strong>Year ' + y + '</strong>';
				results.forEach(function (x, i) {
					var b = balAt(x, y);
					dots[i].setAttribute('cx', X(y)); dots[i].setAttribute('cy', Y(b)); dots[i].setAttribute('visibility', 'visible');
					html += '<div><span><span style="color:' + COLORS[i] + '">&#9679;</span> ' + NAMES[i] + '</span><span>' + M0.format(b) + '</span></div>';
				});
				tip.innerHTML = html; tip.style.display = 'block';
				tip.style.left = Math.max(0, Math.min(X(y) / W * rect.width + 12, rect.width - 200)) + 'px'; tip.style.top = '10px';
			}
			function leave() { tip.style.display = 'none'; cross.setAttribute('visibility', 'hidden'); dots.forEach(function (d) { d.setAttribute('visibility', 'hidden'); }); }
			hit.addEventListener('mousemove', move); hit.addEventListener('touchmove', move, { passive: true });
			hit.addEventListener('mouseleave', leave); hit.addEventListener('touchend', leave);
		}

		function schedRows(x) {
			if (state.view === 'pay') { return x.rows.map(function (r) { return [r.n, r.year, r.pay, r.prin, r.int, r.cumI, r.bal]; }); }
			var out = [], yr = null;
			x.rows.forEach(function (r) { if (!yr || yr[0] !== r.year) { yr = [r.year, 0, 0, 0, 0, 0, 0]; out.push(yr); } yr[1]++; yr[2] += r.pay; yr[3] += r.prin; yr[4] += r.int; yr[5] = r.cumI; yr[6] = r.bal; });
			return out;
		}
		function renderSched() {
			var x = results[state.scen], t = q('[data-k=sched]');
			if (!x) { return; }
			var head = state.view === 'pay' ? ['Payment #', 'Year', 'Payment', 'Principal', 'Interest', 'Total interest', 'Balance'] : ['Year', 'Payments', 'Paid', 'Principal', 'Interest', 'Total interest', 'Balance'];
			t.querySelector('thead').innerHTML = '<tr>' + head.map(function (h) { return '<th scope="col">' + h + '</th>'; }).join('') + '</tr>';
			t.querySelector('tbody').innerHTML = schedRows(x).map(function (r) { return '<tr><td>' + r[0] + '</td><td>' + r[1] + '</td><td>' + M2.format(r[2]) + '</td><td>' + M2.format(r[3]) + '</td><td>' + M2.format(r[4]) + '</td><td>' + M2.format(r[5]) + '</td><td>' + M2.format(r[6]) + '</td></tr>'; }).join('');
		}
		function csv() {
			var x = results[state.scen];
			var head = state.view === 'pay' ? ['payment_number', 'year', 'payment', 'principal', 'interest', 'total_interest', 'balance'] : ['year', 'payments', 'paid', 'principal', 'interest', 'total_interest', 'balance'];
			var lines = [head.join(',')].concat(schedRows(x).map(function (r) { return r.map(function (v, i) { return i < 2 ? v : v.toFixed(2); }).join(','); }));
			var blob = new Blob([lines.join('\n')], { type: 'text/csv' }), a = document.createElement('a');
			a.href = URL.createObjectURL(blob); a.download = 'amortization-scenario-' + 'ABC'[state.scen] + '.csv';
			document.body.appendChild(a); a.click();
			setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
		}

		root.addEventListener('input', function (e) {
			var t = e.target, k = t.getAttribute('data-k');
			if (t.hasAttribute('data-fmt')) { liveFormat(t); }
			var sc = t.closest('.aqm-mc__sc');
			if (sc) { var i = +sc.getAttribute('data-s'); if (k === 'ddol') { dollarLock[i] = true; } else if (k === 'dpct') { dollarLock[i] = false; } }
			if (sc && k === 'rate') { var pk = q('[data-pick]', sc); if (pk) { pk.value = ''; echoPick(+pk.getAttribute('data-pick')); } }
			compute();
		});
		root.addEventListener('change', function (e) {
			var p = e.target.getAttribute && e.target.getAttribute('data-pick');
			if (p !== null && p !== undefined) {
				var r = RATES[+e.target.value];
				if (r) { q('[data-k=rate]', scs[+p]).value = (+r.rate).toFixed(2); }
				echoPick(+p);
			}
			compute();
		});
		root.addEventListener('focusout', function (e) {
			var t = e.target;
			if (!t.hasAttribute || !t.hasAttribute('data-fmt')) { return; }
			setTimeout(function () {
				if (t.getAttribute('data-fmt') === 'pct') { t.value = t.value === '' ? '0' : String(parseNum(t.value)); }
				compute();
			}, 0);
		});
		q('[data-k=tabs]').addEventListener('click', function (e) {
			var b = e.target.closest('button');
			if (!b) { return; }
			if (b.hasAttribute('data-csv')) { csv(); return; }
			if (b.hasAttribute('data-scen')) {
				state.scen = +b.getAttribute('data-scen');
				qa('[data-scen]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
				/* The programs belong to a property, so when the scenarios hold different ones they
				   follow this selection too - otherwise picking Scenario C would show C's schedule
				   beside B's rebates. */
				if (!onePlace()) { renderPrograms(); }
			}
			if (b.hasAttribute('data-view')) { state.view = b.getAttribute('data-view'); qa('[data-view]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); }
			renderSched();
		});
		syncLinks();
		compute();
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.aqm-mc'), function (el) { if (!el.getAttribute('data-ready')) { el.setAttribute('data-ready', '1'); init(el); } }); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
