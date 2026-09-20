/**
 * aqm-mc-admin.js — the rates screen, inside the app.
 *
 * Fetched only when it is wanted: at #admin, or on a device that has already paired. A visitor
 * who never opens the admin never downloads this file.
 *
 * The device token lives in localStorage on this device alone. It never appears in a URL — a URL
 * would put it in the server's access log and in browser history — so every call carries it in an
 * Authorization header instead.
 *
 * Admin needs the network by definition: it writes to the server. Offline, it says so plainly
 * rather than queueing edits that would silently overwrite something typed on another device.
 */
(function () {
	'use strict';

	var KEY  = 'aqm-mc-device';
	var HAND = 'aqm-mc-pair';     // the same-computer hand-off wp-admin leaves behind
	var BASE = '/wp-json/aqm-mc/v1';
	var mount = document.getElementById('aqm-app-admin');
	if (!mount) { return; }

	var token = null;
	try { token = localStorage.getItem(KEY); } catch (e) { token = null; }

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function save(t) { token = t; try { t ? localStorage.setItem(KEY, t) : localStorage.removeItem(KEY); } catch (e) {} }

	/**
	 * A name for the device list, so it does not fill up with "A device". Read off the user agent,
	 * which is enough to tell a phone from the desktop that paired it — the point is recognition,
	 * not fingerprinting, so nothing here is stored server-side beyond this label.
	 */
	function guessName() {
		var ua = navigator.userAgent;
		var os = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android'
		       : /Windows/.test(ua) ? 'Windows' : /Mac/.test(ua) ? 'Mac' : /Linux/.test(ua) ? 'Linux' : 'Device';
		var br = /Edg\//.test(ua) ? 'Edge' : /OPR\/|Opera/.test(ua) ? 'Opera' : /Firefox/.test(ua) ? 'Firefox'
		       : /Chrome\//.test(ua) ? 'Chrome' : /Safari/.test(ua) ? 'Safari' : 'browser';
		return os + ' ' + br;
	}

	/**
	 * wp-admin and the app are the same origin, so on the computer that generated the code the
	 * browser can carry it across instead of the owner reading six digits off one tab and typing
	 * them into another. One shot: it is deleted the moment it is read, whatever happens next.
	 */
	function takeHandoff() {
		var raw = null;
		try { raw = localStorage.getItem(HAND); } catch (e) { return null; }
		if (!raw) { return null; }
		try { localStorage.removeItem(HAND); } catch (e) {}
		try {
			var o = JSON.parse(raw);
			if (o && o.code && o.exp && o.exp > Date.now()) { return String(o.code); }
		} catch (e) {}
		return null;
	}

	function call(path, opts) {
		opts = opts || {};
		var h = { 'Accept': 'application/json' };
		if (token) { h.Authorization = 'Bearer ' + token; h['X-AQM-Token'] = token; }
		if (opts.body) { h['Content-Type'] = 'application/json'; }
		return fetch(BASE + path, {
			method: opts.method || 'GET',
			headers: h,
			body: opts.body ? JSON.stringify(opts.body) : undefined,
			cache: 'no-store'
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) {
					var e = new Error(j && j.message ? j.message : 'That did not work (' + r.status + ').');
					e.status = r.status;
					if ((r.status === 401 || r.status === 403) && token && path.indexOf('/pair') === -1) {
						/* The token is no longer accepted — revoked here or from the website. Drop it
						   and mark the error, so whatever was being attempted returns to the pairing
						   screen instead of leaving a live-looking screen where nothing works. */
						save(null);
						e.unpaired = true;
					}
					throw e;
				}
				return j;
			});
		});
	}

	/* ------------------------------------------------------------------ chrome */

	function shell(title, inner, back) {
		mount.innerHTML =
			'<div class="aqm-adm">' +
				'<div class="aqm-adm__head"><h2>' + esc(title) + '</h2>' +
				(back ? '<button type="button" class="aqm-adm__x" data-close>Close</button>' : '') + '</div>' +
				'<div class="aqm-adm__body">' + inner + '</div>' +
			'</div>';
		var x = mount.querySelector('[data-close]');
		if (x) { x.addEventListener('click', close); }
	}

	/**
	 * One place every failed call lands. A revoked token is not an error to report on a screen that
	 * no longer works — it is a reason to go back to the pairing screen. The first version of this
	 * left every button showing the server's bare refusal, which is the silent failure the design
	 * was meant to avoid.
	 */
	function fail(e, revive) {
		if (e && e.unpaired) { pairScreen('This device is no longer paired. Pair it again.'); return; }
		if (revive) { revive(); }
		note(e && e.message ? e.message : 'That did not work.', 'bad');
	}

	function note(msg, kind) {
		var n = mount.querySelector('.aqm-adm__note');
		if (!n) { return; }
		n.className = 'aqm-adm__note is-on' + (kind ? ' is-' + kind : '');
		n.textContent = msg;
	}

	function open() {
		document.body.classList.add('aqm-adm-open');
		if (location.hash !== '#admin') { location.hash = 'admin'; }
		token ? screen() : pairScreen();
	}
	function close() {
		document.body.classList.remove('aqm-adm-open');
		mount.innerHTML = '';
		if (location.hash === '#admin') {
			history.replaceState(null, '', location.pathname + location.search);
		}
	}

	/* ----------------------------------------------------------------- pairing */

	function pairScreen(msg, noAuto) {
		/* On the computer that generated the code, pair without being asked for anything. If the
		   code has since been used or has expired, this falls through to the six digits with the
		   server's own reason shown — never to a dead end. */
		var handed = noAuto ? null : takeHandoff();
		if (handed) {
			shell('Sign in', '<p class="aqm-adm__lede">Pairing this browser…</p>', true);
			call('/pair', { method: 'POST', body: { code: handed, label: guessName() } })
				.then(function (j) { save(j.token); screen('Paired. This browser can manage the rates now.'); })
				.catch(function (e) { pairScreen(e.message + ' Type the code instead.', true); });
			return;
		}

		shell('Sign in',
			'<p class="aqm-adm__lede">This device is not paired yet.</p>' +
			'<ol class="aqm-adm__steps">' +
				'<li>On a computer, open <b>Settings &rsaquo; AQM Mortgage Calculator</b> on aqmuftirealty.com.</li>' +
				'<li>Press <b>Pair a device</b>. A 6-digit code appears.</li>' +
				'<li>Type it here, within ten minutes.</li>' +
			'</ol>' +
			'<div class="aqm-adm__note' + (msg ? ' is-on is-bad' : '') + '">' + esc(msg || '') + '</div>' +
			'<label for="aqm-adm-code">Pairing code</label>' +
			'<input id="aqm-adm-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" class="aqm-adm__code">' +
			'<label for="aqm-adm-label">Name this device</label>' +
			'<input id="aqm-adm-label" type="text" value="' + esc(guessName()) + '" class="aqm-adm__in">' +
			'<button type="button" class="aqm-adm__go" id="aqm-adm-pair">Pair this device</button>' +
			'<p class="aqm-adm__fine">This pairs the device to the rates only. It does not sign you in to the website, and it cannot reach anything else there.</p>',
		true);

		var code = document.getElementById('aqm-adm-code');
		var name = document.getElementById('aqm-adm-label');
		var go   = document.getElementById('aqm-adm-pair');
		code.focus();
		code.addEventListener('input', function () { code.value = code.value.replace(/\D/g, ''); });
		code.addEventListener('keydown', function (e) { if (e.key === 'Enter') { go.click(); } });

		go.addEventListener('click', function () {
			if (code.value.length !== 6) { note('The code is six digits.', 'bad'); return; }
			go.disabled = true; go.textContent = 'Checking…';
			call('/pair', { method: 'POST', body: { code: code.value, label: name.value || guessName() } })
				.then(function (j) { save(j.token); screen('Paired. This device can manage the rates now.'); })
				.catch(function (e) {
					go.disabled = false; go.textContent = 'Pair this device';
					code.value = '';
					pairScreen(e.message);
				});
		});
	}

	/* ------------------------------------------------------------ the rates UI */

	function screen(flash) {
		shell('Rates', '<div class="aqm-adm__note' + (flash ? ' is-on is-good' : '') + '">' + esc(flash || '') + '</div><p class="aqm-adm__lede">Loading…</p>', true);
		if (!navigator.onLine) {
			shell('Rates', '<div class="aqm-adm__note is-on is-bad">You are offline. The rates screen writes to the server, so it needs a connection. The calculator itself still works.</div>', true);
			return;
		}
		call('/admin/state').then(function (s) { draw(s, flash); }).catch(function (e) {
			if (e && e.unpaired) { pairScreen('This device is no longer paired. Pair it again.'); return; }
			if (!token) { pairScreen('This device is not paired.'); return; }
			shell('Rates', '<div class="aqm-adm__note is-on is-bad">' + esc(e.message) + '</div>', true);
		});
	}

	function money(n) { return (Math.round(n * 100) / 100).toFixed(2); }

	function draw(s, flash) {
		var att = (s.attention || []).map(function (g) {
			var why = { stale: 'no new reading for over ' + s.stale_days + ' days',
			            failing: 'the last read of the source failed',
			            unticked: 'read, but not ticked yet',
			            never: 'never readable from this server' }[g.why] || g.why;
			return '<li><b>' + esc(g.lender) + '</b> &mdash; ' + esc(g.label) + '<br>' +
				'<span class="aqm-adm__muted">' + esc(why) + ' &middot; ' +
				(g.hidden ? '<b>not on the calculator</b>' : 'still showing, read ' + esc(g.date)) + '</span>' +
				(g.rate > 0 ? '<br><button type="button" class="aqm-adm__copy" data-line="' + esc(g.lender + ' | ' + g.label + ' | ' + money(g.rate)) + '">Take this one over by hand</button>' : '') +
				'</li>';
		}).join('');

		var tick = (s.fetched || []).map(function (r) {
			return '<li><label class="aqm-adm__tick">' +
				'<input type="checkbox" data-key="' + esc(r.key) + '" data-rate="' + esc(r.rate) + '"' + (r.approved ? ' checked' : '') + '>' +
				'<span><b>' + money(r.rate) + '%</b> ' + esc(r.lender) + ' &mdash; ' + esc(r.label) +
				'<br><span class="aqm-adm__muted">read ' + esc(r.date) + (r.doubt ? ' &middot; <b>looks wrong</b>' : '') + '</span></span>' +
				'</label></li>';
		}).join('');

		var showing = (s.showing || []).map(function (r) {
			return '<li>' + money(r.rate) + '% &mdash; ' + esc(r.lender) + ' &mdash; ' + esc(r.label) +
				(r.own ? ' <span class="aqm-adm__muted">(yours)</span>' : '') +
				(r.stale ? ' <span class="aqm-adm__muted">read ' + esc(r.date) + '</span>' : '') + '</li>';
		}).join('');

		shell('Rates',
			'<div class="aqm-adm__note' + (flash ? ' is-on is-good' : '') + '">' + esc(flash || '') + '</div>' +

			'<label class="aqm-adm__tick aqm-adm__row"><input type="checkbox" id="aqm-adm-en"' + (s.enabled ? ' checked' : '') + '>' +
			'<span>Offer lender rates in the calculator</span></label>' +

			'<h3>Your own rates</h3>' +
			'<p class="aqm-adm__muted">One per line: <code>who quoted it | what it is | rate</code>. These show exactly as typed, and never go out of date.</p>' +
			'<textarea id="aqm-adm-manual" rows="5" class="aqm-adm__ta" placeholder="Broker name | 5-year fixed, insured | 4.09">' + esc(s.manual) + '</textarea>' +
			'<button type="button" class="aqm-adm__go" id="aqm-adm-save">Save my rates</button>' +

			'<h3>Needs attention' + (s.attention && s.attention.length ? ' <span class="aqm-adm__count">' + s.attention.length + '</span>' : '') + '</h3>' +
			(att ? '<ul class="aqm-adm__list">' + att + '</ul>' : '<p class="aqm-adm__muted">Nothing. Every source is refreshing.</p>') +

			'<h3>Read from lender pages</h3>' +
			'<p class="aqm-adm__muted">Nothing here reaches the calculator until you tick it, and a rate that changes has to be ticked again. Check it against the lender&rsquo;s page first.</p>' +
			(tick ? '<ul class="aqm-adm__list aqm-adm__ticks">' + tick + '</ul>' : '<p class="aqm-adm__muted">Nothing read yet.</p>') +

			'<h3>On the calculator now <span class="aqm-adm__count">' + (s.showing || []).length + '</span></h3>' +
			'<ul class="aqm-adm__list aqm-adm__plain">' + showing + '</ul>' +

			'<h3>Sources</h3>' +
			'<p class="aqm-adm__muted">Last read ' + esc(s.read_at) + '. Next ' + esc(s.next_at) + '.</p>' +
			'<button type="button" class="aqm-adm__go aqm-adm__go--quiet" id="aqm-adm-refresh">Read every source now</button>' +
			'<p class="aqm-adm__fine">Sixteen lender sites, one at a time &mdash; give it a minute.</p>' +

			'<hr>' +
			'<p class="aqm-adm__fine">Signed in on <b>' + esc(s.device || 'this device') + '</b>, paired ' + esc(s.paired_at) + '. Version ' + esc(s.version) + '.</p>' +
			'<button type="button" class="aqm-adm__out" id="aqm-adm-out">Sign this device out</button>',
		true);

		document.getElementById('aqm-adm-en').addEventListener('change', function () {
			var on = this.checked;
			call('/admin/enabled', { method: 'POST', body: { on: on ? 1 : 0 } })
				.then(function (j) { draw(j, on ? 'Rates are on.' : 'Rates are off — every scenario starts at the calculator’s own default.'); })
				.catch(function (e) { var c = document.getElementById('aqm-adm-en'); fail(e, function () { if (c) { c.checked = !on; } }); });
		});

		var man = document.getElementById('aqm-adm-manual');
		document.getElementById('aqm-adm-save').addEventListener('click', function () {
			var b = this; b.disabled = true; b.textContent = 'Saving…';
			call('/admin/manual', { method: 'POST', body: { text: man.value } })
				.then(function (j) { draw(j, 'Saved.'); })
				.catch(function (e) { fail(e, function () { b.disabled = false; b.textContent = 'Save my rates'; }); });
		});

		Array.prototype.forEach.call(mount.querySelectorAll('.aqm-adm__ticks input'), function (c) {
			c.addEventListener('change', function () {
				call('/admin/approve', { method: 'POST', body: { key: c.getAttribute('data-key'), rate: c.getAttribute('data-rate'), on: c.checked ? 1 : 0 } })
					.then(function (j) { draw(j, c.checked ? 'Showing it.' : 'Taken off the calculator.'); })
					.catch(function (e) { fail(e, function () { c.checked = !c.checked; }); });
			});
		});

		Array.prototype.forEach.call(mount.querySelectorAll('.aqm-adm__copy'), function (b) {
			b.addEventListener('click', function () {
				var line = b.getAttribute('data-line');
				man.value = (man.value.replace(/\s*$/, '') + '\n' + line).replace(/^\n/, '');
				man.focus();
				note('Added to your own rates below — correct the figure from the lender’s page, then Save.', 'good');
				man.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		});

		document.getElementById('aqm-adm-refresh').addEventListener('click', function () {
			var b = this; b.disabled = true; b.textContent = 'Reading…';
			call('/admin/refresh', { method: 'POST', body: {} })
				.then(function (j) { draw(j, 'Read them all.'); })
				.catch(function (e) { fail(e, function () { b.disabled = false; b.textContent = 'Read every source now'; }); });
		});

		document.getElementById('aqm-adm-out').addEventListener('click', function () {
			call('/admin/signout', { method: 'POST', body: {} }).catch(function () {})
				.then(function () { save(null); pairScreen('Signed out.'); });
		});
	}

	/* ------------------------------------------------------------------- entry */

	window.addEventListener('hashchange', function () {
		if (location.hash === '#admin') { open(); } else if (document.body.classList.contains('aqm-adm-open')) { close(); }
	});
	if (location.hash === '#admin') { open(); }

	// Once a device is paired the button is worth showing; before that it is clutter for visitors.
	if (token) {
		var bar = document.querySelector('.aqm-app__bar');
		if (bar && !bar.querySelector('.aqm-app__admin')) {
			var b = document.createElement('button');
			b.type = 'button'; b.className = 'aqm-app__admin'; b.textContent = 'Admin';
			b.addEventListener('click', open);
			bar.appendChild(b);
		}
	}
})();
