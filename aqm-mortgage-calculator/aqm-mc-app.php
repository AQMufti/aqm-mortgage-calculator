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
 * The shell, the rules and the styles are cached and served from cache - they only change when the
 * plugin version changes, which changes their URLs. The RATES are fetched from the network first
 * and only fall back to the cached copy when there is no signal, because a stale rate shown as
 * current is the one failure this whole rate system exists to prevent. Every rate carries the date
 * it was read, so an offline copy says how old it is rather than pretending.
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
} );

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
@media (max-width:760px){.aqm-app__wrap{padding:10px}.aqm-mc__card{padding:14px}}
</style>
</head>
<body>
<div class="aqm-app__bar">AQM Mortgage Calculator<a href="<?php echo $site; ?>">aqmuftirealty.com</a></div>
<div class="aqm-app__off" id="aqm-app-off">You are offline. The rates below are the last ones this app downloaded &mdash; each shows the date it was read.</div>
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
			/* Offline and never seen: the shell is the one thing worth substituting. */
			return req.mode === 'navigate' ? caches.match(PRECACHE[0]) : Response.error();
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
