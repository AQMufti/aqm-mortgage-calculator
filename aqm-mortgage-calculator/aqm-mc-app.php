<?php
/**
 * AQM Mortgage Calculator - the installable app.
 * Copyright (c) 2026 A. Q. Mufti. All rights reserved.
 *
 * WHY A PWA FIRST, BEFORE ANY APP STORE
 *
 * This project measured roughly one material Canadian tax change a month, plus lender rates that
 * move weekly. App-store review takes days. An app that carries its tax tables inside the binary is
 * out of date before it is approved. So the rules stay a file the app FETCHES, and the rates stay
 * an endpoint the app fetches - and both update the moment AQ ships them, with nobody's permission.
 *
 * A PWA is also the cheapest way to find out whether anyone actually wants an app: it installs from
 * the site, uses the files that already exist, and needs no developer account, no review and no
 * separate codebase. If people install it, Capacitor can wrap this same code for the stores later.
 *
 * WHAT IS SERVED, AND FROM WHERE
 *
 *   /mortgage-app/                       the app itself - the same calculator, full screen
 *   /mortgage-app/manifest.webmanifest   what makes it installable
 *   /mortgage-app/sw.js                  the service worker, so it works with no signal
 *   /mortgage-app/icon-192.png, -512.png the home-screen icon
 *   /wp-json/aqm-mc/v1/rates             the rates, as data
 *
 * Everything sits under one path on purpose: a service worker may only control the folder it is
 * served from, so /mortgage-app/sw.js governs /mortgage-app/ and nothing else on the site.
 *
 * WHAT THE APP CACHES, AND WHAT IT REFUSES TO
 *
 * The rules and the styles are cached and served from cache, because their URLs carry the version
 * and the file's timestamp - a new release means a new URL, so a cache hit is current by
 * construction.
 *
 * TWO THINGS ARE DELIBERATELY NOT CACHE-FIRST, and for the same reason: their URLs never change.
 *
 *   - The RATES, because a stale rate shown as a current one is the single failure this whole rate
 *     system exists to prevent. Every rate carries the date it was read, so an offline copy says how
 *     old it is rather than pretending.
 *   - The SHELL. /mortgage-app/ is the same address in every version, so a cache hit said nothing
 *     about whether it was current. Served cache-first, a browser could sit on an old shell for
 *     good - and with it, old ?v= links to old CSS and JS. That is not hypothetical: a desktop
 *     screenshot showed the 1.9.0 shell after 1.9.3 had shipped.
 *
 * Both go to the network first and fall back to the cache, so offline is unchanged and a browser
 * with a connection can never be a version behind.
 */
defined( 'ABSPATH' ) || exit;

const AQM_MC_APP_PATH = 'mortgage-app';

/* ------------------------------------------------------------------ routing */

add_action( 'init', function () {
	add_rewrite_rule( '^' . AQM_MC_APP_PATH . '/?$', 'index.php?aqm_mc_app=shell', 'top' );
	add_rewrite_rule( '^' . AQM_MC_APP_PATH . '/sw\.js$', 'index.php?aqm_mc_app=sw', 'top' );
	add_rewrite_rule( '^' . AQM_MC_APP_PATH . '/manifest\.webmanifest$', 'index.php?aqm_mc_app=manifest', 'top' );
	add_rewrite_rule( '^' . AQM_MC_APP_PATH . '/icon-(192|512)\.png$', 'index.php?aqm_mc_app=icon&aqm_mc_size=$matches[1]', 'top' );
} );

add_filter( 'query_vars', function ( $v ) {
	$v[] = 'aqm_mc_app';
	$v[] = 'aqm_mc_size';
	return $v;
} );

/** Flush once per version, so the rules exist without anyone re-saving permalinks. */
add_action( 'init', function () {
	if ( get_option( 'aqm_mc_app_routes' ) === AQM_MC_VERSION ) { return; }
	flush_rewrite_rules( false );
	update_option( 'aqm_mc_app_routes', AQM_MC_VERSION, false );
}, 99 );

function aqm_mc_asset_url( $file ) {
	$path = __DIR__ . '/assets/' . $file;
	return plugins_url( 'assets/' . $file, __FILE__ ) . '?v=' . AQM_MC_VERSION . '.' . ( file_exists( $path ) ? filemtime( $path ) : 0 );
}

/** One string that changes whenever anything the app caches changes. */
function aqm_mc_app_build() {
	$parts = array( AQM_MC_VERSION );
	foreach ( array( 'aqm-mc-core.js', 'aqm-mc.js', 'aqm-mc.css' ) as $f ) {
		$p = __DIR__ . '/assets/' . $f;
		$parts[] = file_exists( $p ) ? filemtime( $p ) : 0;
	}
	return implode( '.', $parts );
}

/**
 * WordPress adds a trailing slash to anything it does not recognise as a file, so
 * /mortgage-app/sw.js was being 301'd to /mortgage-app/sw.js/ before we ever saw it. The content
 * was served correctly at the slashed address, which is what made it look like it worked.
 *
 * It does not work. A service worker may only control the folder it is served FROM, so one served
 * at /mortgage-app/sw.js/ has that as its scope and controls nothing at all - the app would install
 * and then never work offline. The manifest and icons would each cost a needless redirect too.
 *
 * Two guards, because one of them alone is a race: refuse the canonical redirect for our own
 * addresses, and answer at priority 1, before redirect_canonical runs at 10.
 */
add_filter( 'redirect_canonical', function ( $redirect ) {
	return get_query_var( 'aqm_mc_app' ) ? false : $redirect;
}, 10, 1 );

add_action( 'template_redirect', function () {
	$what = get_query_var( 'aqm_mc_app' );
	if ( ! $what ) { return; }

	if ( 'manifest' === $what ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( array(
			'name'             => 'AQM Mortgage Calculator',
			'short_name'       => 'AQM Mortgage',
			'description'      => 'Canadian mortgage and closing-cost calculator for every province and territory, by A. Q. Mufti.',
			'start_url'        => home_url( '/' . AQM_MC_APP_PATH . '/' ),
			'scope'            => home_url( '/' . AQM_MC_APP_PATH . '/' ),
			'display'          => 'standalone',
			'orientation'      => 'any',
			'background_color' => '#ffffff',
			'theme_color'      => '#A62021',
			'lang'             => 'en-CA',
			'icons'            => array(
				array( 'src' => home_url( '/' . AQM_MC_APP_PATH . '/icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => home_url( '/' . AQM_MC_APP_PATH . '/icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ),
			),
		), JSON_UNESCAPED_SLASHES );
		exit;
	}

	if ( 'icon' === $what ) {
		$size = (int) get_query_var( 'aqm_mc_size' );
		$file = __DIR__ . '/assets/icon-' . ( 512 === $size ? 512 : 192 ) . '.png';
		if ( ! file_exists( $file ) ) { status_header( 404 ); exit; }
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: public, max-age=604800' );
		readfile( $file );
		exit;
	}

	if ( 'sw' === $what ) {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache' );          // the worker itself must never be stale
		header( 'Service-Worker-Allowed: /' . AQM_MC_APP_PATH . '/' );
		echo aqm_mc_service_worker();
		exit;
	}

	/* The app shell. */
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Cache-Control: no-cache' );
	echo aqm_mc_app_shell();
	exit;
}, 1 );

/* ------------------------------------------------------------------ the app */

function aqm_mc_app_shell() {
	$css   = aqm_mc_asset_url( 'aqm-mc.css' );
	$core  = aqm_mc_asset_url( 'aqm-mc-core.js' );
	$ui    = aqm_mc_asset_url( 'aqm-mc.js' );
	$man   = esc_url( home_url( '/' . AQM_MC_APP_PATH . '/manifest.webmanifest' ) );
	$sw    = esc_url( home_url( '/' . AQM_MC_APP_PATH . '/sw.js' ) );
	$scope = esc_url( home_url( '/' . AQM_MC_APP_PATH . '/' ) );
	$body  = aqm_mc_build( array(), false );
	$site  = esc_url( home_url( '/' ) );

	ob_start();
	?><!DOCTYPE html>
<html lang="en-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>AQM Mortgage Calculator</title>
<meta name="description" content="Canadian mortgage and closing-cost calculator for every province and territory.">
<meta name="theme-color" content="#A62021">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="AQM Mortgage">
<meta name="robots" content="noindex">
<link rel="manifest" href="<?php echo $man; ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( home_url( '/' . AQM_MC_APP_PATH . '/icon-192.png' ) ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
<style>
body{margin:0;background:#f4f4f4;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left)}
.aqm-app__bar{background:#A62021;color:#fff;padding:12px 16px;display:flex;align-items:center;gap:10px;font-weight:600}
.aqm-app__bar a{color:#fff;text-decoration:none;font-weight:400;font-size:.85rem;margin-left:auto}
.aqm-app__wrap{padding:16px}
.aqm-app__off{display:none;background:#fdf3d6;border-left:3px solid #8a6d1f;padding:8px 12px;margin:0 16px 12px;font-size:.85rem}
.aqm-app__off.is-on{display:block}
.aqm-app__install{display:none;align-items:center;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #e2e2e2;border-left:3px solid #A62021;border-radius:6px;padding:10px 14px;margin:12px 16px;font-size:.88rem;color:#393939}
.aqm-app__install.is-on{display:flex}
.aqm-app__install b{font-weight:700}
.aqm-app__install button{margin-left:auto;background:#A62021;color:#fff;border:0;border-radius:6px;padding:9px 18px;font-size:.9rem;font-weight:600;cursor:pointer}
.aqm-app__install button:focus-visible{outline:2px solid #393939;outline-offset:2px}
.aqm-app__share{display:inline-block;width:1.05em;height:1.05em;vertical-align:-.18em;padding:1px;border:1px solid #bdbdbd;border-radius:4px;box-sizing:content-box}
.aqm-app__aside{display:block;margin-top:6px;font-size:.82rem;color:#6b6b6b}
@media (max-width:760px){.aqm-app__wrap{padding:10px}.aqm-mc__card{padding:14px}}
</style>
</head>
<body>
<div class="aqm-app__bar">AQM Mortgage Calculator<a href="<?php echo $site; ?>">aqmuftirealty.com</a></div>
<div class="aqm-app__off" id="aqm-app-off">You are offline. The rates below are the last ones this app downloaded &mdash; each shows the date it was read.</div>
<div class="aqm-app__install" id="aqm-app-install" role="note"><span id="aqm-app-installtxt"></span></div>
<div class="aqm-app__wrap"><?php echo $body; ?></div>
<script src="<?php echo esc_url( $core ); ?>"></script>
<script src="<?php echo esc_url( $ui ); ?>"></script>
<script>
(function () {
	var off = document.getElementById('aqm-app-off');
	function net() { off.className = 'aqm-app__off' + (navigator.onLine ? '' : ' is-on'); }
	window.addEventListener('online', net); window.addEventListener('offline', net); net();
	if ('serviceWorker' in navigator) {
		navigator.serviceWorker.register('<?php echo $sw; ?>', { scope: '<?php echo $scope; ?>' })
			.catch(function (e) { /* an app that cannot cache still works online */ });
	}

	/* ---------------------------------------------------------------- installing
	   Everything a browser needs to install this was already in place - HTTPS, a valid manifest, both
	   icons, a service worker with a fetch handler. What was missing was any way to KNOW that.

	   No browser announces it any more. Chrome dropped the install banner years ago and now hides the
	   option behind a small address-bar icon or a submenu; iOS has never shown a prompt at all and
	   never will - Add to Home Screen is the only route there, and it is buried in the Share sheet.
	   So the page has to offer it itself, differently on each platform. */
	var bar = document.getElementById('aqm-app-install');
	var txt = document.getElementById('aqm-app-installtxt');
	var installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
	var iOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
		(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); /* iPadOS reports as a Mac */
	var deferred = null, shown = false;

	function show(html, button) {
		if (installed || shown) { return; }
		shown = true;
		txt.innerHTML = html;
		if (button) {
			var b = document.createElement('button');
			b.type = 'button';
			b.textContent = 'Install';
			b.addEventListener('click', function () {
				if (!deferred) { return; }
				deferred.prompt();
				deferred.userChoice.then(function () { deferred = null; bar.className = 'aqm-app__install'; });
			});
			bar.appendChild(b);
		}
		bar.className = 'aqm-app__install is-on';
	}

	/* Chrome, Edge, Samsung Internet and the rest: the browser tells us it is installable, and we
	   hold on to the event so a real button can trigger the real prompt later. */
	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferred = e;
		show('<b>Install this calculator</b> to keep it on your device and use it with no signal.', true);
	});
	window.addEventListener('appinstalled', function () { bar.className = 'aqm-app__install'; });

	if (!installed) {
		if (iOS) {
			/* No event exists on iOS and none ever will: Apple provides no way for a page to install
			   itself, so a button here would be a lie. Instructions are the only honest option.

			   THE WORDING MATTERS MORE THAN IT LOOKS. 1.9.1 named the Share button and said where on
			   the screen to find it. A screenshot from a current iPhone showed a Safari toolbar with
			   no Share button on it at all - that Safari puts a page menu beside the address instead,
			   and Add to Home Screen lives inside it. Safari also lets the address bar sit at either
			   end of the screen, by a setting, so the position was wrong twice over. This names both
			   routes and claims no position: an instruction that sends someone hunting for a button
			   that is not on their screen is worse than no instruction, because they conclude the
			   feature is missing. Which is exactly what happened.

			   These lines are JavaScript, not PHP - they are served to the browser. The release
			   script asserts the old wording is absent from the rendered page, so a comment quoting
			   it verbatim would fail that check. Describe it; do not repeat it. */
			var menuGlyph = '<svg class="aqm-app__share" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';
			var shareGlyph = '<svg class="aqm-app__share" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 16V3M12 3L8 7M12 3l4 4M5 13v6a2 2 0 002 2h10a2 2 0 002-2v-6"/></svg>';
			show('<b>Add this to your Home Screen.</b> In Safari, tap ' + menuGlyph
				+ ' beside the web address &mdash; or the Share button ' + shareGlyph
				+ ' if your Safari shows one &mdash; then tap <b>Add to Home Screen</b>. '
				+ '<span class="aqm-app__aside">You may need to scroll the menu. There is no App Store download: '
				+ 'iPhones install this straight from Safari, and Apple gives a website no way to do it for you.</span>', false);
		} else {
			/* Anything that never fires the event - Firefox, desktop Safari, or a Chrome that has
			   already been told no once. Said quietly, and only after giving the event its chance. */
			setTimeout(function () {
				/* Naming only Chrome and Edge here was no use to someone sitting in a third browser,
				   which is what happened with Opera: Chromium underneath, but it does not fire the
				   event, so this branch ran and then talked about browsers they were not using. No
				   menu path is claimed for any browser that has not been checked - that is the same
				   mistake the iPhone wording made. */
				show('<b>Install this calculator</b> to use it with no signal. Open your browser&rsquo;s menu '
					+ 'and look for <b>Install</b> &mdash; in Chrome and Edge it is there, or as an icon in the '
					+ 'address bar, and in Safari on a Mac it is <b>File &rsaquo; Add to Dock</b>. '
					+ '<span class="aqm-app__aside">Not every desktop browser can install a web app. If yours '
					+ 'has no such option, Chrome or Edge will.</span>', false);
			}, 2500);
		}
	}
}());
</script>
</body>
</html>
	<?php
	return ob_get_clean();
}

function aqm_mc_service_worker() {
	$build = aqm_mc_app_build();
	$shell = home_url( '/' . AQM_MC_APP_PATH . '/' );
	$rates = home_url( '/wp-json/aqm-mc/v1/rates' );
	$pre   = wp_json_encode( array_values( array_map( 'strval', array(
		$shell,
		aqm_mc_asset_url( 'aqm-mc.css' ),
		aqm_mc_asset_url( 'aqm-mc-core.js' ),
		aqm_mc_asset_url( 'aqm-mc.js' ),
	) ) ), JSON_UNESCAPED_SLASHES );

	ob_start();
	?>
/* AQM Mortgage Calculator - service worker. Built <?php echo esc_js( $build ); ?> */
var CACHE = 'aqm-mc-<?php echo esc_js( $build ); ?>';
var PRECACHE = <?php echo $pre; ?>;
var RATES = '<?php echo esc_js( $rates ); ?>';
var SHELL = '<?php echo esc_js( $shell ); ?>';

self.addEventListener('install', function (e) {
	e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(PRECACHE); }).then(function () { return self.skipWaiting(); }));
});

/* A new build means new URLs, so every older cache is dead weight. */
self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (keys) {
		return Promise.all(keys.map(function (k) { return k === CACHE ? null : caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }

	/* THE SHELL IS THE ONE CACHED THING WHOSE URL NEVER CHANGES.
	   Everything else carries ?v=<version>.<mtime>, so a new release means a new URL and a cache hit
	   is current by construction. /mortgage-app/ does not: it is the same address in every version.
	   Served cache-first, as it was until now, an installed or previously-visited browser could go on
	   showing an OLD shell indefinitely - old markup, and old ?v= links to old CSS and JS with it.

	   That is not hypothetical. A desktop screenshot showed the 1.9.0 shell after 1.9.3 shipped: no
	   install bar, and the six-column layout that only the pre-1.9.3 stylesheet produces.

	   So the shell now goes to the network first and falls back to the cache, exactly like the rates.
	   It is a few KB, it is still precached, and offline is unaffected - the only thing that changes
	   is that a browser with a connection can no longer be a version behind. */
	if (req.mode === 'navigate' || req.url === SHELL || req.url === SHELL + '?') {
		e.respondWith(
			fetch(req).then(function (res) {
				if (res && res.status === 200) {
					var copy = res.clone();
					caches.open(CACHE).then(function (c) { c.put(SHELL, copy); });
				}
				return res;
			}).catch(function () { return caches.match(SHELL) || caches.match(req); })
		);
		return;
	}

	/* Rates: the network decides, and the cache is only the fallback. A rate shown as current when
	   it is not is exactly the failure the whole rate system exists to prevent. */
	if (req.url.indexOf(RATES) === 0) {
		e.respondWith(
			fetch(req).then(function (res) {
				var copy = res.clone();
				caches.open(CACHE).then(function (c) { c.put(req, copy); });
				return res;
			}).catch(function () { return caches.match(req); })
		);
		return;
	}

	/* Everything else carries its version in the URL, so a hit is always the right file. */
	e.respondWith(caches.match(req).then(function (hit) {
		return hit || fetch(req).then(function (res) {
			if (res && res.status === 200 && res.type === 'basic') {
				var copy = res.clone();
				caches.open(CACHE).then(function (c) { c.put(req, copy); });
			}
			return res;
		}).catch(function () {
			/* Navigations are handled above; anything else offline and never seen has no substitute. */
				return Response.error();
		});
	}));
});
	<?php
	return ob_get_clean();
}

/* ------------------------------------------------------- the rates, as data */

add_action( 'rest_api_init', function () {
	register_rest_route( 'aqm-mc/v1', '/rates', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$rows = class_exists( 'AQM_MC_Rates' ) ? AQM_MC_Rates::listing() : array();
			$boc  = array();
			$pick = array();
			foreach ( $rows as $r ) {
				if ( isset( $r['src'] ) && 'boc' === $r['src'] ) { $boc[] = $r; } else { $pick[] = $r; }
			}
			usort( $boc, function ( $x, $y ) {
				$a = ( false !== stripos( $x['label'], 'prime' ) ) ? 0 : 1;
				$b = ( false !== stripos( $y['label'], 'prime' ) ) ? 0 : 1;
				return $a === $b ? ( (float) $x['rate'] <=> (float) $y['rate'] ) : $a - $b;
			} );
			return rest_ensure_response( array(
				'version'    => AQM_MC_VERSION,
				'build'      => aqm_mc_app_build(),
				'generated'  => gmdate( 'c' ),
				/* The app loads the rules from this file, so tax changes ship without an app release. */
				'rules_url'  => aqm_mc_asset_url( 'aqm-mc-core.js' ),
				'rates'      => array_values( $pick ),
				'benchmarks' => array_values( $boc ),
				'notice'     => 'Published rates read from each lender, with the date each was read. Not offers, and nobody is approved at them.',
			) );
		},
	) );
} );
