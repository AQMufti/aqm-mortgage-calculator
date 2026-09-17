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

	/* ---------------------------------------------------------------- rules */
	var R = {
		cap: 1500000,
		minDown: function (p) { if (p >= 1500000) { return p * 0.2; } if (p <= 500000) { return p * 0.05; } return 25000 + (p - 500000) * 0.1; },
		premRate: function (ltv) { if (ltv <= 0.8) { return 0; } if (ltv <= 0.85) { return 0.028; } if (ltv <= 0.9) { return 0.031; } if (ltv <= 0.95) { return 0.04; } return null; },
		ON: [[55000, 0.005], [250000, 0.01], [400000, 0.015], [2000000, 0.02], [Infinity, 0.025]],
		TO: [[55000, 0.005], [250000, 0.01], [400000, 0.015], [2000000, 0.02], [3000000, 0.025], [4000000, 0.044], [5000000, 0.0545], [10000000, 0.065], [20000000, 0.0755], [Infinity, 0.086]],
		onRefund: 4000, toRebate: 4475
	};
	function brackets(p, b) { var t = 0, prev = 0; for (var i = 0; i < b.length; i++) { if (p > prev) { t += (Math.min(p, b[i][0]) - prev) * b[i][1]; } prev = b[i][0]; } return t; }
	function taper(p, from, to, hi, lo) { if (p <= from) { return hi; } if (p >= to) { return lo; } return hi - (hi - lo) * (p - from) / (to - from); }

	/* New-home HST on a price before HST (b). Returns the tax, the relief and the net. */
	function hstFor(b, ftb) {
		var fed = b * 0.05, prov = b * 0.08;
		var onFed = Math.min(fed, taper(b, 1500000, 1850000, 50000, 0));
		var fthb = ftb ? Math.min(fed, taper(b, 1000000, 1500000, 50000, 0)) : 0;
		var fedRelief = Math.max(onFed, fthb);
		var provRelief = Math.min(prov, taper(b, 1500000, 1850000, 80000, 24000));
		return { base: b, fed: fed, prov: prov, fedRelief: fedRelief, provRelief: provRelief, fthb: fthb, net: fed + prov - fedRelief - provRelief };
	}
	/* The builder's all-in price (a) = base + net HST. Solve for the base. */
	function hstFromAllIn(a, ftb) {
		var lo = a / 1.13, hi = a, mid, h;
		for (var i = 0; i < 60; i++) { mid = (lo + hi) / 2; h = hstFor(mid, ftb); if (mid + h.net > a) { hi = mid; } else { lo = mid; } }
		return hstFor(lo, ftb);
	}
	function periodic(annual, perYear) { return Math.pow(1 + annual / 2, 2 / perYear) - 1; }
	function pmt(L, r, n) { if (L <= 0 || n <= 0) { return 0; } if (r === 0) { return L / n; } return L * r / (1 - Math.pow(1 + r, -n)); }

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

	/* --------------------------------------------------------------- instance */
	function init(root) {
		var cfg = {};
		try { cfg = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { cfg = {}; }
		var q = function (s, c) { return (c || root).querySelector(s); };
		var qa = function (s, c) { return Array.prototype.slice.call((c || root).querySelectorAll(s)); };
		var scs = qa('.aqm-mc__sc');
		var dollarLock = [false, false, false], state = { scen: 0, view: 'year' }, results = [], ctx = {};

		/* Lender rates, when the site has any: a drop-down above each scenario's rate box. */
		var RATES = (cfg.rates || []).filter(function (r) { return r && +r.rate > 0; });
		if (RATES.length) {
			var opts = '<option value="">Your own rate</option>' + RATES.map(function (r, i) {
				return '<option value="' + i + '">' + r.lender + ' \u2013 ' + r.label + ': ' + (+r.rate).toFixed(2) + '%' + (r.stale ? ' (out of date)' : '') + '</option>';
			}).join('');
			scs.forEach(function (el, i) {
				var box = document.createElement('div');
				box.className = 'aqm-mc__pick';
				box.innerHTML = '<label for="' + root.id + '-pick' + i + '">Use a published rate</label><select id="' + root.id + '-pick' + i + '" data-pick="' + i + '">' + opts + '</select>';
				el.insertBefore(box, el.querySelector('.aqm-mc__two'));
			});
			var note = document.createElement('p');
			note.className = 'aqm-mc__ratenote';
			var srcs = [], seen = {};
			RATES.forEach(function (r) { if (r.url && !seen[r.url]) { seen[r.url] = 1; srcs.push('<a href="' + r.url + '" target="_blank" rel="noopener nofollow">' + r.lender + '</a>'); } });
			note.innerHTML = 'Rates shown are the lenders\u2019 own published rates, read on the dates listed, and rates typed in by A. Q. Mufti. They are not offers and nobody is approved at them: your rate depends on the lender, the property and you. Confirm any rate with the lender or a licensed mortgage agent.' + (srcs.length ? ' Sources: ' + srcs.join(', ') + '.' : '');
			root.querySelector('.aqm-mc__scen').parentNode.appendChild(note);
		}

		q('[data-k=price]').value = N0.format(cfg.price || 850000);
		scs.forEach(function (el, i) {
			q('[data-k=dpct]', el).value = String((cfg.downs || [10, 15, 20])[i]);
			q('[data-k=rate]', el).value = String(cfg.rate || 4.19);
			q('[data-k=amort]', el).value = String(cfg.amort || 25);
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

		function compute() {
			var entered = parseNum(q('[data-k=price]').value), loc = q('[data-k=loc]').value, fv = q('[data-k=freq]').value;
			var term = +q('[data-k=term]').value, ftb = q('[data-k=ftb]').checked, nb = q('[data-k=newbuild]').checked, hm = q('[data-k=hstmode]').value;
			q('[data-k=hstwrap]').classList.toggle('is-on', nb);
			var hst = null, price = entered;
			if (nb && entered > 0) {
				if (hm === 'plus') { hst = hstFor(entered, ftb); price = entered + hst.net; } else { hst = hstFromAllIn(entered, ftb); }
			}
			var perYear = parseInt(fv, 10), accel = /a$/.test(fv);
			var ltt = brackets(price, R.ON), ontRebate = ftb ? Math.min(R.onRefund, ltt) : 0;
			var mltt = loc === 'to' ? brackets(price, R.TO) : 0, torRebate = (ftb && loc === 'to') ? Math.min(R.toRebate, mltt) : 0;
			var colCost = [0, 1, 2].map(function (i) {
				var at = 0, before = 0;
				qa('[data-cost][data-col="' + i + '"]').forEach(function (el) {
					var c = COSTS.filter(function (x) { return x.key === el.getAttribute('data-cost'); })[0];
					if (c && c.when === 'before') { before += parseNum(el.value); } else { at += parseNum(el.value); }
				});
				return { at: at, before: before };
			});
			ctx = { entered: entered, price: price, loc: loc, fv: fv, term: term, ftb: ftb, nb: nb, hm: hm, hst: hst, ltt: ltt, ontRebate: ontRebate, mltt: mltt, torRebate: torRebate };

			results = scs.map(function (el, i) {
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
				var prem = base * pr, loan = base + prem, pst = prem * 0.08;
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
					atCost: colCost[i].at, beforeCost: colCost[i].before,
					closing: down + ltt - ontRebate + mltt - torRebate + pst + colCost[i].at,
					cash: down + ltt - ontRebate + mltt - torRebate + pst + colCost[i].at + colCost[i].before
				};
			});
			renderKpis(); renderCompare(); renderPrograms(); renderChart(); renderSched();
		}

		var FREQ = { '12': 'Monthly', '24': 'Semi-monthly', '26': 'Bi-weekly', '26a': 'Accelerated bi-weekly', '52': 'Weekly', '52a': 'Accelerated weekly' };

		function renderKpis() {
			q('[data-k=kpis]').innerHTML = results.map(function (x, i) {
				return '<div class="aqm-mc__kpi" style="--c:' + COLORS[i] + '"><small>' + NAMES[i] + ' &middot; ' + fl(x.pct) + '% down</small><strong>' + M2.format(x.pay) + '</strong><span>' + FREQ[ctx.fv].toLowerCase() + ' &middot; mortgage ' + M0.format(x.loan) + ' &middot; cash needed ' + M0.format(x.cash) + '</span></div>';
			}).join('');
		}

		function renderCompare() {
			var row = function (label, fn, cls) { return '<tr' + (cls ? ' class="' + cls + '"' : '') + '><td>' + label + '</td>' + results.map(function (x) { return '<td>' + fn(x) + '</td>'; }).join('') + '</tr>'; };
			var same = function (label, v, cls) { return row(label, function () { return v; }, cls); };
			var group = function (label) { return '<tr class="aqm-mc__group"><td colspan="4">' + label + '</td></tr>'; };
			var h = ctx.hst;
			var out = group('Property')
				+ same('Purchase price' + (h ? (ctx.hm === 'plus' ? ' incl. net HST' : ' (all-in)') : ''), M0.format(ctx.price));
			if (h) {
				out += same('Price before HST', M0.format(h.base))
					+ same('HST at 13%', M0.format(h.fed + h.prov))
					+ same('Federal portion relief' + (h.fthb >= h.fedRelief && h.fthb > 0 ? ' (first-time buyer rebate)' : ''), '-' + M0.format(h.fedRelief))
					+ same('Ontario portion relief', '-' + M0.format(h.provRelief))
					+ same('HST you pay after relief', M0.format(h.net), 'aqm-mc__em');
			}
			out += group('Mortgage')
				+ row('Down payment', function (x) { return M0.format(x.down) + ' <small>(' + fl(x.pct) + '%)</small>'; })
				+ row('Minimum down payment', function (x) { return M0.format(Math.ceil(x.min)); })
				+ row('CMHC insurance premium', function (x) { return x.pr ? M0.format(x.prem) + ' <small>(' + fl(x.pr * 100) + '%)</small>' : 'Not needed'; })
				+ row('Total mortgage', function (x) { return M0.format(x.loan); }, 'aqm-mc__em')
				+ row('Interest rate / amortization', function (x) { return fl(x.rate * 100) + '% / ' + x.amort + ' yrs'; })
				+ row(FREQ[ctx.fv] + ' payment', function (x) { return M2.format(x.pay); }, 'aqm-mc__em')
				+ row('Paid off in', function (x) { return (Math.round(x.payoff * 10) / 10) + ' years'; })
				+ group('Over the ' + ctx.term + '-year term')
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
				+ same('Ontario land transfer tax', M0.format(ctx.ltt))
				+ (ctx.ftb ? same('Ontario first-time buyer refund', '-' + M0.format(ctx.ontRebate)) : '')
				+ (ctx.loc === 'to' ? same('Toronto land transfer tax', M0.format(ctx.mltt)) : '')
				+ (ctx.loc === 'to' && ctx.ftb ? same('Toronto first-time buyer rebate', '-' + M0.format(ctx.torRebate)) : '')
				+ row('PST on CMHC premium (8%)', function (x) { return M0.format(x.pst); });
			q('[data-part=top]').innerHTML = out;
			q('[data-part=mid]').innerHTML = row('Total due at closing', function (x) { return M0.format(x.closing); }, 'aqm-mc__em')
				+ group('Paid before closing')
				+ '<tr class="aqm-mc__notes"><td colspan="4">Usually paid while the offer is still conditional, before closing day.</td></tr>';
			q('[data-part=bottom]').innerHTML = row('Total paid before closing', function (x) { return M0.format(x.beforeCost); }, 'aqm-mc__em')
				+ group('All cash you need')
				+ row('Total cash needed to buy', function (x) { return M0.format(x.cash); }, 'aqm-mc__em');
		}

		function renderPrograms() {
			var c = ctx, items = [], anyInsured = results.some(function (x) { return x.insured; });
			var li = function (cls, title, body) { items.push('<li class="is-' + cls + '"><b>' + title + '</b>' + body + '</li>'); };
			li(c.price >= R.cap && anyInsured ? 'warn' : 'yes', 'Down payment and the $1.5 million limit',
				'Minimum for this price: <em>' + M0.format(Math.ceil(R.minDown(c.price))) + '</em> (5% of the first $500,000, 10% of the rest up to $1.5 million). Mortgage insurance, and so less than 20% down, is available up to <em>$1,499,999</em>.');
			li(c.ftb || c.nb ? 'yes' : 'no', '30-year amortization with less than 20% down',
				c.ftb || c.nb ? 'Available: ' + (c.ftb && c.nb ? 'first-time buyer and new build' : (c.ftb ? 'first-time buyer' : 'new build')) + '. Insurance premium is 0.20% higher than at 25 years.' : 'Only for first-time buyers or new builds. With 20% or more down, any buyer can choose 30 years.');
			li(c.ftb ? 'yes' : 'no', 'Ontario land transfer tax refund', c.ftb ? 'Up to $4,000: <em>' + M0.format(c.ontRebate) + '</em> for this price.' : 'First-time buyers get up to $4,000 back.');
			if (c.loc === 'to') { li(c.ftb ? 'yes' : 'no', 'Toronto land transfer tax rebate', c.ftb ? 'Up to $4,475: <em>' + M0.format(c.torRebate) + '</em> for this price.' : 'First-time buyers get up to $4,475 back.'); }
			if (c.nb && c.hst) {
				li(c.hst.fedRelief + c.hst.provRelief > 0 ? 'yes' : 'no', 'New-home HST relief (agreements 1 Apr 2026 to 31 Mar 2027)',
					'Estimated <em>' + M0.format(c.hst.fedRelief + c.hst.provRelief) + '</em> of <em>' + M0.format(c.hst.fed + c.hst.prov) + '</em> HST on ' + M0.format(c.hst.base) + ' before tax: all 13% up to $1 million, up to $130,000 to $1.5 million, reducing to $24,000 at $1.85 million. For a home you will live in; builders usually credit it in the price.');
			} else {
				li('no', 'New-home HST relief', 'Buying new from a builder? Tick "Newly built home": Ontario and federal relief can remove up to $130,000 of HST.');
			}
			li(c.ftb ? 'yes' : 'no', 'Home Buyers’ Amount (tax credit)', c.ftb ? 'Claim <em>$1,500</em> on your tax return for the year you buy.' : 'First-time buyers can claim a $1,500 federal tax credit.');
			li(c.ftb ? 'yes' : 'no', 'FHSA and RRSP Home Buyers’ Plan', c.ftb ? 'FHSA: save up to $8,000 a year, $40,000 lifetime, tax-free for the down payment. RRSP: withdraw up to $60,000 each, repay over 15 years.' : 'Available to first-time buyers: FHSA savings up to $40,000 and RRSP withdrawals up to $60,000 each.');
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
			if (sc && k === 'rate') { var pk = q('[data-pick]', sc); if (pk) { pk.value = ''; } }
			compute();
		});
		root.addEventListener('change', function (e) {
			var p = e.target.getAttribute && e.target.getAttribute('data-pick');
			if (p !== null && p !== undefined) {
				var r = RATES[+e.target.value];
				if (r) { q('[data-k=rate]', scs[+p]).value = (+r.rate).toFixed(2); }
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
			if (b.hasAttribute('data-scen')) { state.scen = +b.getAttribute('data-scen'); qa('[data-scen]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); }
			if (b.hasAttribute('data-view')) { state.view = b.getAttribute('data-view'); qa('[data-view]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); }
			renderSched();
		});
		compute();
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.aqm-mc'), function (el) { if (!el.getAttribute('data-ready')) { el.setAttribute('data-ready', '1'); init(el); } }); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
