/* AQM Mortgage Calculator - the rules, with no user interface attached.
   Copyright (c) 2026 A. Q. Mufti. All rights reserved.

   THIS FILE KNOWS NOTHING ABOUT WORDPRESS OR THE BROWSER.

   It is plain JavaScript with no DOM, no jQuery and no dependencies, so the same file can run
   behind the website today and inside a desktop or mobile app later without Canadian tax law
   being written out a second time. Three copies of Ontario's land transfer tax brackets is how
   they quietly drift apart; there is one copy, and it is here.

   Everything the calculator decides lives in this file: the federal mortgage rules, the
   jurisdiction table for all thirteen provinces and territories, and the arithmetic. The file
   beside it, aqm-mc.js, is only the screen - it reads these functions and draws the result.

   Loads as a browser global (AQMMC) or as a CommonJS/ES module, whichever the host provides.
*/
(function (root, factory) {
	'use strict';
	var api = factory();
	if (typeof module === 'object' && module.exports) { module.exports = api; }
	root.AQMMC = api;
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

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
			subLabel: 'Which municipality',
			subs: {
				mtl: {
					name: 'Montr&eacute;al',
					tax: bracketTax([[62900, 0.005], [315000, 0.01], [552300, 0.015], [1104700, 0.02], [2136500, 0.025], [3113000, 0.035], [Infinity, 0.04]]),
					note: 'Montreal&rsquo;s own rates, in effect 1 January 2026. It is the one municipality Quebec does not cap, and it runs to 4%.'
				},
				qcc: {
					name: 'Qu&eacute;bec City',
					tax: bracketTax([[62900, 0.005], [315000, 0.01], [500000, 0.015], [750000, 0.025], [Infinity, 0.03]]),
					note: 'Qu&eacute;bec City&rsquo;s own rates for 2026, topping out at the 3% a municipality is allowed.'
				},
				base: {
					name: 'Anywhere else in Quebec',
					tax: bracketTax([[62900, 0.005], [315000, 0.01], [Infinity, 0.015]]),
					note: 'The province-wide base rates. Your municipality may charge more on the part above $500,000 &mdash; up to 3%. If it does, the real figure is higher than this. Check with your city or your notary.'
				}
			},
			taxNote: 'The duties are municipal. Pick your city above, or take the base rates as a floor.',
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
			subLabel: 'Which municipality',
			subs: {
				hrm:     { name: 'Halifax Regional Municipality', tax: flatTax(0.015) },
				cbrm:    { name: 'Cape Breton Regional Municipality', tax: flatTax(0.015) },
				other15: { name: 'Another municipality at 1.5%', tax: flatTax(0.015), note: '1.5% is the maximum a Nova Scotia municipality may charge, and most of them do.' },
				pictou:  { name: 'Pictou County, New Glasgow, Pictou, Stellarton, Trenton or Westville', tax: flatTax(0.01) },
				yarm:    { name: 'Yarmouth town or district, Argyle, Clare or Digby', tax: flatTax(0.01) },
				anti:    { name: 'Antigonish County, Stewiacke, Guysborough or Clark&rsquo;s Harbour', tax: flatTax(0.01) },
				other10: { name: 'Another municipality at 1.0%', tax: flatTax(0.01), note: '1.0% is the lowest rate charged in Nova Scotia; no municipality charges nothing.' }
			},
			taxNote: 'The deed transfer tax is set by each municipality, from 1.0% to 1.5%. Rates can change without the province being told, so confirm yours before you rely on the figure.',
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
	return {
		R: R, JUR: JUR, JORDER: JORDER, jur: jur,
		brackets: brackets, taper: taper, bracketTax: bracketTax, flatTax: flatTax, stepFee: stepFee,
		hstFor: hstFor, hstFromAllIn: hstFromAllIn, periodic: periodic, pmt: pmt
	};
}));
