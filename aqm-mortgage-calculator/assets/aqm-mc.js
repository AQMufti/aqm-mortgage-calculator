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
	/* The mortgage itself is federal and the same everywhere: minimum down payment, the $1.5M
	   insured cap, the CMHC premium bands and the 0.20% surcharge past 25 years. Everything below
	   it is provincial, and the provinces disagree about the SHAPE of the tax, not just the rate:
	   six charge no transfer tax at all but a land titles fee, and Quebec and Nova Scotia set
	   theirs municipally. Every figure was read off the government's own page on 17 Sep 2026. */
	var R = {
		cap: 1500000,
		minDown: function (p) { if (p >= 1500000) { return p * 0.2; } if (p <= 500000) { return p * 0.05; } return 25000 + (p - 500000) * 0.1; },
		premRate: function (ltv) { if (ltv <= 0.8) { return 0; } if (ltv <= 0.85) { return 0.028; } if (ltv <= 0.9) { return 0.031; } if (ltv <= 0.95) { return 0.04; } return null; },
		checked: '17 Sep 2026'
	};
	function brackets(p, b) { var t = 0, prev = 0; for (var i = 0; i < b.length; i++) { if (p > prev) { t += (Math.min(p, b[i][0]) - prev) * b[i][1]; } prev = b[i][0]; } return t; }
	function taper(p, from, to, hi, lo) { if (p <= from) { return hi; } if (p >= to) { return lo; } return hi - (hi - lo) * (p - from) / (to - from); }
	function bracketTax(b) { return function (p) { return brackets(p, b); }; }
	function flatTax(r) { return function (p) { return p * r; }; }
	/* A land titles fee of "$base plus $per for every $step of value, or part of a step". */
	function stepFee(base, per, step) { return function (p) { return p > 0 ? base + Math.ceil(p / step) * per : 0; }; }

	var JUR = {
		on: {
			name: 'Ontario (outside Toronto)', short: 'Ontario',
			taxName: 'Ontario land transfer tax',
			tax: bracketTax([[55000, 0.005], [250000, 0.01], [400000, 0.015], [2000000, 0.02], [Infinity, 0.025]]),
			ftbName: 'Ontario first-time buyer refund', ftbMax: 'up to $4,000',
			ftb: function (p, t) { return Math.min(4000, t); },
			pst: 0.08, pstName: 'Ontario RST on the CMHC premium (8%)',
			nhTax: 0.08, nhName: 'HST', nhRate: '13%',
			nhRelief: function (b, prov) { return Math.min(prov, taper(b, 1500000, 1850000, 80000, 24000)); },
			nhReliefNote: 'Ontario removes the whole 8% provincial part up to $1.5 million, to a maximum of $80,000, reducing to $24,000 at $1.85 million. Agreements 1 Apr 2026 to 31 Mar 2027.'
		},
		to: {
			name: 'City of Toronto', short: 'Toronto',
			taxName: 'Ontario land transfer tax',
			tax: bracketTax([[55000, 0.005], [250000, 0.01], [400000, 0.015], [2000000, 0.02], [Infinity, 0.025]]),
			ftbName: 'Ontario first-time buyer refund', ftbMax: 'up to $4,000',
			ftb: function (p, t) { return Math.min(4000, t); },
			muniName: 'Toronto land transfer tax',
			muni: bracketTax([[55000, 0.005], [250000, 0.01], [400000, 0.015], [2000000, 0.02], [3000000, 0.025], [4000000, 0.044], [5000000, 0.0545], [10000000, 0.065], [20000000, 0.0755], [Infinity, 0.086]]),
			muniFtbName: 'Toronto first-time buyer rebate', muniFtbMax: 'up to $4,475',
			muniFtb: function (p, t) { return Math.min(4475, t); },
			pst: 0.08, pstName: 'Ontario RST on the CMHC premium (8%)',
			nhTax: 0.08, nhName: 'HST', nhRate: '13%',
			nhRelief: function (b, prov) { return Math.min(prov, taper(b, 1500000, 1850000, 80000, 24000)); },
			nhReliefNote: 'Ontario removes the whole 8% provincial part up to $1.5 million, to a maximum of $80,000, reducing to $24,000 at $1.85 million. Agreements 1 Apr 2026 to 31 Mar 2027.'
		},
		bc: {
			name: 'British Columbia', short: 'B.C.',
			taxName: 'Property transfer tax',
			tax: bracketTax([[200000, 0.01], [2000000, 0.02], [3000000, 0.03], [Infinity, 0.05]]),
			ftbName: 'First-time buyer exemption', ftbMax: 'full to $500,000, then $8,000, nil at $860,000',
			ftb: function (p, t) { return p <= 500000 ? t : Math.min(t, taper(p, 835000, 860000, 8000, 0)); },
			nbExName: 'Newly built home exemption', nbExMax: 'full to $1.1 million, nil at $1.15 million',
			nbEx: function (p, t) { return Math.min(t, taper(p, 1100000, 1150000, t, 0)); },
			pst: 0, nhTax: 0, nhName: 'GST', nhRate: '5%'
		},
		ab: {
			name: 'Alberta', short: 'Alberta',
			taxName: 'Land titles registration fee', noTax: true,
			tax: stepFee(50, 5, 5000),
			mtgFee: stepFee(50, 5, 5000),
			pst: 0, nhTax: 0, nhName: 'GST', nhRate: '5%'
		},
		sk: {
			name: 'Saskatchewan', short: 'Saskatchewan',
			taxName: 'Land titles registration fee', noTax: true,
			tax: function (p) { return p <= 500 ? 0 : p <= 6300 ? 25 : p * 0.004; },
			taxNote: 'Saskatchewan has no land transfer tax. This fee is published by ISC, which runs the registry, rather than on a Government of Saskatchewan page.',
			credit: 'Saskatchewan gives first-time buyers a non-refundable income tax credit worth up to $1,575, rather than money off at closing.',
			pst: 0.06, pstName: 'Saskatchewan PST on the CMHC premium (6%)',
			nhTax: 0, nhName: 'GST', nhRate: '5%'
		},
		mb: {
			name: 'Manitoba', short: 'Manitoba',
			taxName: 'Land transfer tax',
			tax: bracketTax([[30000, 0], [90000, 0.005], [150000, 0.01], [200000, 0.015], [Infinity, 0.02]]),
			pst: 0, nhTax: 0, nhName: 'GST', nhRate: '5%',
			credit: 'Manitoba has no first-time buyer land transfer tax rebate. Mortgage insurance premiums are exempt from its 7% sales tax.'
		},
		qc: {
			name: 'Quebec', short: 'Quebec',
			taxName: 'Transfer duties (the &ldquo;welcome tax&rdquo;)',
			tax: bracketTax([[62900, 0.005], [315000, 0.01], [Infinity, 0.015]]),
			taxNote: 'These are the province-wide base rates. The duties are set by each municipality, and any of them may charge more on the part above $500,000 &mdash; up to 3%, and Montreal to 4%. Check your city before relying on this figure.',
			credit: 'Quebec refunds the welcome tax through a refundable tax credit instead: up to $5,875, phasing out from $750,000 and gone at $1 million. Montreal closed its own rebate in July 2026 when this replaced it.',
			pst: 0.09, pstName: 'Quebec tax on the CMHC premium (9%)',
			pstNote: 'Quebec&rsquo;s tax on insurance premiums rises to 9.975% for premiums paid after 31 December 2026.',
			nhTax: 0.09975, nhName: 'GST and QST', nhRate: '5% + 9.975%',
			nhRelief: function (b, prov) { var r = Math.min(prov * 0.5, 9975); return b <= 200000 ? r : taper(b, 200000, 300000, r, 0); },
			nhReliefNote: 'Quebec rebates half the QST, to a maximum of $9,975, in full up to $200,000 and nothing at $300,000.'
		},
		nb: {
			name: 'New Brunswick', short: 'New Brunswick',
			taxName: 'Real property transfer tax', tax: flatTax(0.01),
			pst: 0, nhTax: 0.10, nhName: 'HST', nhRate: '15%'
		},
		ns: {
			name: 'Nova Scotia', short: 'Nova Scotia',
			taxName: 'Deed transfer tax', tax: flatTax(0.015),
			taxNote: 'Nova Scotia&rsquo;s deed transfer tax is set by each municipality and runs from 1.0% to 1.5%. Shown here at 1.5%, which Halifax, Cape Breton and most others charge. Check your municipality.',
			pst: 0, nhTax: 0.09, nhName: 'HST', nhRate: '14%',
			nhRelief: function (b, prov, ftb) { return ftb ? Math.min(prov * 0.1875, 3000) : 0; },
			nhReliefNote: 'Nova Scotia rebates 18.75% of the provincial part of the HST to first-time buyers of a newly built home, to a maximum of $3,000.'
		},
		pe: {
			name: 'Prince Edward Island', short: 'P.E.I.',
			taxName: 'Real property transfer tax',
			tax: function (p) { return p <= 30000 ? 0 : p * 0.01; },
			ftbName: 'First-time buyer exemption', ftbMax: 'the whole tax, at any price',
			ftb: function (p, t) { return t; },
			pst: 0, nhTax: 0.10, nhName: 'HST', nhRate: '15%'
		},
		nl: {
			name: 'Newfoundland and Labrador', short: 'N.L.',
			taxName: 'Registry of Deeds fee', noTax: true,
			tax: function (p) { return p <= 500 ? 0 : 100 + Math.ceil((p - 500) / 100) * 0.4; },
			pst: 0, nhTax: 0.10, nhName: 'HST', nhRate: '15%'
		},
		yt: {
			name: 'Yukon', short: 'Yukon',
			taxName: 'Land titles fee', noTax: true,
			tax: function (p) {
				var reg = p < 100000 ? 50 : p < 500000 ? 150 : p < 3000000 ? 350 : p < 10000000 ? 550 : 750;
				return reg + (p > 0 ? 20 + Math.max(0, Math.ceil(p / 10000) - 1) * 10 : 0);
			},
			taxNote: 'The assurance fund part is charged on the increase in declared value since the last transfer, so it is shown here on the full price &mdash; the real figure is usually lower.',
			pst: 0, nhTax: 0, nhName: 'GST', nhRate: '5%'
		},
		nt: {
			name: 'Northwest Territories', short: 'N.W.T.',
			taxName: 'Land titles fee', noTax: true,
			tax: function (p) { return p <= 0 ? 0 : p <= 1000000 ? Math.max(100, Math.ceil(p / 1000) * 2) : 2000 + Math.ceil((p - 1000000) / 1000) * 1.5; },
			mtgFee: function (L) { return L <= 0 ? 0 : Math.max(80, Math.ceil(L / 1000) * 1.5); },
			pst: 0, nhTax: 0, nhName: 'GST', nhRate: '5%'
		},
		nu: {
			name: 'Nunavut', short: 'Nunavut',
			taxName: 'Land titles fee', noTax: true, tax: function () { return 0; },
			taxNote: 'Nunavut charges no land transfer tax, but its land titles fee could not be read from a Government of Nunavut page and is shown as nil. Ask your lawyer for the figure.',
			pst: 0, nhTax: 0, nhName: 'GST', nhRate: '5%'
		}
	};
	var JORDER = ['on', 'to', 'bc', 'ab', 'sk', 'mb', 'qc', 'nb', 'ns', 'pe', 'nl', 'yt', 'nt', 'nu'];
	function jur(code) { return JUR[code] || JUR.on; }

	/* Sales tax on a new home from a builder, on a price before tax (b).
	   Federal: the New Housing Rebate (36% of the federal part, max $6,300, full to $350,000 and
	   nothing at $450,000) plus, for a first-time buyer, the federal First-Time Home Buyers' GST
	   rebate (up to $50,000, full to $1 million, nothing at $1.5 million). The two stack, but never
	   beyond the federal tax itself. The provincial part is whatever that province gives back. */
	function hstFor(b, ftb, j) {
		j = j || JUR.on;
		var fed = b * 0.05, prov = b * (j.nhTax || 0);
		var nhr = Math.min(fed * 0.36, 6300);
		nhr = taper(b, 350000, 450000, nhr, 0);
		var fthb = ftb ? Math.min(fed, taper(b, 1000000, 1500000, 50000, 0)) : 0;
		var fedRelief = Math.min(fed, nhr + fthb);
		var provRelief = j.nhRelief ? Math.min(prov, j.nhRelief(b, prov, ftb)) : 0;
		return { base: b, fed: fed, prov: prov, fedRelief: fedRelief, provRelief: provRelief, fthb: fthb, net: fed + prov - fedRelief - provRelief };
	}
	/* The builder's all-in price (a) = base + net tax. Solve for the base. */
	function hstFromAllIn(a, ftb, j) {
		j = j || JUR.on;
		var lo = a / (1 + 0.05 + (j.nhTax || 0)), hi = a, mid, h;
		for (var i = 0; i < 60; i++) { mid = (lo + hi) / 2; h = hstFor(mid, ftb, j); if (mid + h.net > a) { hi = mid; } else { lo = mid; } }
		return hstFor(lo, ftb, j);
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
		var RATES = (cfg.rates || []).filter(function (r) { return r && +r.rate > 0; })
			.sort(function (a, b) { return (+a.rate) - (+b.rate); }); // lowest rate first
		if (RATES.length) {
			var opts = '<option value="">Type my own rate in the box below</option>' + RATES.map(function (r, i) {
				return '<option value="' + i + '">' + (+r.rate).toFixed(2) + '% \u2013 ' + r.lender + ' \u2013 ' + r.label + (r.stale ? ' (out of date)' : '') + '</option>';
			}).join('');
			scs.forEach(function (el, i) {
				var box = document.createElement('div');
				box.className = 'aqm-mc__pick';
				box.innerHTML = '<label for="' + root.id + '-pick' + i + '">Interest rate: pick a lender&rsquo;s rate\u2026</label>'
					+ '<select id="' + root.id + '-pick' + i + '" data-pick="' + i + '">' + opts + '</select>'
					+ '<span class="aqm-mc__hint">&hellip;or just type a rate in the Interest rate box below.</span>';
				// Sit the picker directly above the Interest rate box, not above Down payment, so the
				// two rate controls read as one thing and "the box below" means the box below.
				var rateRow = el.querySelector('[data-k=rate]').closest('.aqm-mc__two');
				el.insertBefore(box, rateRow || el.querySelector('.aqm-mc__two'));
			});
			var note = document.createElement('p');
			note.className = 'aqm-mc__ratenote';
			var srcs = [], seen = {};
			RATES.forEach(function (r) { if (r.url && !seen[r.url]) { seen[r.url] = 1; srcs.push('<a href="' + r.url + '" target="_blank" rel="noopener nofollow">' + r.lender + '</a>'); } });
			note.innerHTML = 'The rates in the drop-downs are the lenders\u2019 own published rates, read on the dates listed, and rates typed in by A. Q. Mufti. They are not offers and nobody is approved at them: your rate depends on the lender, the property and you. Confirm any rate with the lender or a licensed mortgage agent.' + (srcs.length ? ' Sources: ' + srcs.join(', ') + '.' : '');
			root.querySelector('.aqm-mc__scen').parentNode.appendChild(note);
		}

		/* Build the places list from the jurisdiction table, so the two can never drift apart. */
		(function () {
			var sel = q('[data-k=loc]');
			sel.innerHTML = JORDER.map(function (k) { return '<option value="' + k + '">' + JUR[k].name + '</option>'; }).join('');
			sel.value = JUR[cfg.loc] ? cfg.loc : 'on';
		}());

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
			var J = jur(loc);
			var hst = null, price = entered;
			if (nb && entered > 0) {
				if (hm === 'plus') { hst = hstFor(entered, ftb, J); price = entered + hst.net; } else { hst = hstFromAllIn(entered, ftb, J); }
			}
			var perYear = parseInt(fv, 10), accel = /a$/.test(fv);
			var ltt = J.tax(price);
			/* B.C.'s two exemptions are mutually exclusive, so take whichever is worth more. */
			var ontRebate = Math.max(
				(ftb && J.ftb) ? J.ftb(price, ltt) : 0,
				(nb && J.nbEx) ? J.nbEx(price, ltt) : 0
			);
			var usedNbEx = J.nbEx && nb && ontRebate > ((ftb && J.ftb) ? J.ftb(price, ltt) : 0);
			var mltt = J.muni ? J.muni(price) : 0;
			var torRebate = (ftb && J.muniFtb) ? J.muniFtb(price, mltt) : 0;
			var colCost = [0, 1, 2].map(function (i) {
				var at = 0, before = 0;
				qa('[data-cost][data-col="' + i + '"]').forEach(function (el) {
					var c = COSTS.filter(function (x) { return x.key === el.getAttribute('data-cost'); })[0];
					if (c && c.when === 'before') { before += parseNum(el.value); } else { at += parseNum(el.value); }
				});
				return { at: at, before: before };
			});
			ctx = { entered: entered, price: price, loc: loc, J: J, fv: fv, term: term, ftb: ftb, nb: nb, hm: hm, hst: hst, ltt: ltt, ontRebate: ontRebate, usedNbEx: usedNbEx, mltt: mltt, torRebate: torRebate };

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
				+ same(ctx.J.taxName, M0.format(ctx.ltt))
				+ (ctx.ontRebate > 0 ? same(ctx.usedNbEx ? ctx.J.nbExName : ctx.J.ftbName, '-' + M0.format(ctx.ontRebate)) : '')
				+ (ctx.J.muni ? same(ctx.J.muniName, M0.format(ctx.mltt)) : '')
				+ (ctx.J.muni && ctx.torRebate > 0 ? same(ctx.J.muniFtbName, '-' + M0.format(ctx.torRebate)) : '')
				+ (ctx.J.pst ? row(ctx.J.pstName, function (x) { return M0.format(x.pst); }) : '')
				+ (ctx.J.mtgFee ? row('Mortgage registration fee', function (x) { return M0.format(x.mtgFee); }) : '');
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
			if (J.taxNote && !J.noTax) { li('note', 'Set by your municipality', J.taxNote); }
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
