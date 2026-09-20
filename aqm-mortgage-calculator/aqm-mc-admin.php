<?php
/**
 * aqm-mc-admin.php — the app's own admin, so the rates can be run without opening the website.
 *
 * AQ, 19 Sep 2026: "If its a standalone App, it should have it's own Admin interface, with an
 * Admin/owner login, not requiring to use any website."
 *
 * WHAT THIS DOES AND DOES NOT CHANGE
 *
 * The rates still live on the server, and they must: one copy is the rule the whole design rests
 * on, so that every installed app and the website page can never disagree about a figure. What
 * changes is who has to visit the website — nobody. The app gets the rates screen.
 *
 * WHY NOT THE WORDPRESS LOGIN
 *
 * An Application Password for user 1 is total control of aqmuftirealty.com. Putting one on a phone
 * makes a lost phone a lost website, to manage a list of interest rates. So this issues its own
 * credential instead: a DEVICE TOKEN that unlocks the six rate endpoints below and nothing else in
 * WordPress. It is not a user, carries no capabilities, and cannot read a post, touch a setting or
 * reach any other plugin. Lose the phone, revoke that one token, and nothing else was ever exposed.
 *
 * HOW A DEVICE GETS ONE
 *
 * Something has to establish trust the first time, and it has to come from somewhere already
 * authenticated — which means wp-admin, once per device:
 *
 *   1. Settings → AQM Mortgage Calculator → "Pair a device" shows a 6-digit code, good for 10
 *      minutes and one use.
 *   2. In the app, Admin → type the code.
 *   3. The server checks it and hands back a long random token, which that device stores.
 *
 * The long token is never typed, never shown on a screen, and never put in a URL — a URL would
 * land it in the server's access log and in browser history. Only its hash is stored here, so a
 * database dump does not yield a working credential. The 6-digit code is only ever a 10-minute
 * door: five wrong guesses burns it, from any IP, and the caller is throttled as well.
 *
 * ⚠ These endpoints run with NO logged-in user, so current_user_can() is meaningless inside them.
 * Everything a token can reach is written out longhand below. Do not add an endpoint here that
 * touches anything outside the rate settings.
 *
 * @package AQM_Mortgage_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AQM_MC_Admin {

	const TOKENS      = 'aqm_mc_app_tokens'; // paired devices — hashes only, never a usable token
	const PAIR        = 'aqm_mc_pair';       // the live pairing code (transient: expiry is free)
	const PAIR_SHOW   = 'aqm_mc_pair_show_'; // + user id: the code to print, so it stays out of the URL
	const PAIR_TTL    = 600;                 // 10 minutes
	const PAIR_TRIES  = 5;                   // wrong guesses before the code is burned
	const THROTTLE    = 900;                 // 15 minutes of lockout for a caller that keeps failing
	const MAX_DEVICES = 8;

	/** The id of the token that authorised the request in flight, so signout knows what to revoke. */
	private static $current = '';

	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_aqm_mc_pair', array( __CLASS__, 'admin_pair' ) );
		add_action( 'admin_post_aqm_mc_revoke', array( __CLASS__, 'admin_revoke' ) );
	}

	/* ------------------------------------------------------------ the devices */

	public static function tokens() {
		$t = get_option( self::TOKENS, array() );
		return is_array( $t ) ? $t : array();
	}

	/**
	 * Issues a token for a device and returns it ONCE. Only the hash is kept, so this string can
	 * never be recovered from the database — if the device loses it, it pairs again.
	 */
	public static function issue( $label ) {
		$tokens = self::tokens();
		if ( count( $tokens ) >= self::MAX_DEVICES ) {
			// Drop the least recently used rather than refusing: a phone that was replaced should
			// not be able to lock its owner out of his own calculator.
			usort( $tokens, function ( $a, $b ) { return (int) $a['last'] <=> (int) $b['last']; } );
			array_shift( $tokens );
		}
		$id     = bin2hex( random_bytes( 4 ) );
		$secret = bin2hex( random_bytes( 24 ) );
		$tokens[] = array(
			'id'      => $id,
			'hash'    => password_hash( $secret, PASSWORD_DEFAULT ),
			'label'   => $label ? $label : 'A device',
			'created' => time(),
			'last'    => time(),
			'ip'      => self::ip(),
		);
		update_option( self::TOKENS, array_values( $tokens ), false );
		return 'aqmmc_' . $id . '_' . $secret;
	}

	/**
	 * Reads the token off the request. Bearer is the standard; X-AQM-Token is the fallback for a
	 * host that strips Authorization before PHP sees it. (aqmuftirealty.com does not — the blog
	 * pipeline's Basic auth proves the header survives — but the fallback costs one line.)
	 */
	public static function bearer() {
		$h = '';
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) { $h = (string) $_SERVER['HTTP_AUTHORIZATION']; }
		elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) { $h = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']; }
		if ( '' !== $h && 0 === stripos( $h, 'bearer ' ) ) { return trim( substr( $h, 7 ) ); }
		if ( ! empty( $_SERVER['HTTP_X_AQM_TOKEN'] ) ) { return trim( (string) $_SERVER['HTTP_X_AQM_TOKEN'] ); }
		return '';
	}

	/** The permission_callback for every admin route. No token, no entry — there is no other way in. */
	public static function verify() {
		$tok = self::bearer();
		if ( '' === $tok ) { return false; }
		$p = explode( '_', $tok );
		if ( 3 !== count( $p ) || 'aqmmc' !== $p[0] || '' === $p[1] || '' === $p[2] ) { return false; }

		$tokens = self::tokens();
		foreach ( $tokens as $i => $t ) {
			if ( ! hash_equals( (string) $t['id'], $p[1] ) ) { continue; }
			if ( ! password_verify( $p[2], (string) $t['hash'] ) ) { return false; }
			self::$current = (string) $t['id'];
			// Record when and where it was last used, so a token being used from somewhere
			// unexpected is visible. Throttled to once a minute: this is a write on every call.
			if ( time() - (int) $t['last'] > 60 ) {
				$tokens[ $i ]['last'] = time();
				$tokens[ $i ]['ip']   = self::ip();
				update_option( self::TOKENS, $tokens, false );
			}
			return true;
		}
		return false;
	}

	public static function current_device() {
		foreach ( self::tokens() as $t ) {
			if ( hash_equals( (string) $t['id'], self::$current ) ) { return $t; }
		}
		return null;
	}

	private static function ip() {
		// REMOTE_ADDR only. An X-Forwarded-For header is caller-supplied and trivially spoofed, so
		// it must never be what a throttle counts.
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/* ------------------------------------------------------------- the pairing */

	/** Called from wp-admin. Returns the 6 digits to read off the screen. */
	public static function new_code() {
		$code = (string) random_int( 100000, 999999 );
		set_transient( self::PAIR, array(
			'hash'  => password_hash( $code, PASSWORD_DEFAULT ),
			'tries' => 0,
			'made'  => time(),
		), self::PAIR_TTL );
		return $code;
	}

	/**
	 * ⚠ The code is handed to the settings page in a transient, NOT in the redirect URL. A code in
	 * the URL lands in browser history and in the server's access log, which is the same objection
	 * that kept the device token out of URLs; there is no reason to hold the code to a lower
	 * standard just because it is shorter-lived.
	 */
	public static function admin_pair() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'aqm_mc_pair' );
		set_transient( self::PAIR_SHOW . get_current_user_id(), self::new_code(), self::PAIR_TTL );
		wp_safe_redirect( admin_url( 'options-general.php?page=aqm-mc&pair=1' ) . '#aqm-mc-devices' );
		exit;
	}

	public static function admin_revoke() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'aqm_mc_revoke' );
		$id   = isset( $_POST['device'] ) ? sanitize_text_field( wp_unslash( $_POST['device'] ) ) : '';
		$keep = array();
		foreach ( self::tokens() as $t ) { if ( ! hash_equals( (string) $t['id'], $id ) ) { $keep[] = $t; } }
		update_option( self::TOKENS, $keep, false );
		wp_safe_redirect( admin_url( 'options-general.php?page=aqm-mc&msg=revoked' ) . '#aqm-mc-devices' );
		exit;
	}

	/* -------------------------------------------------------------- the routes */

	public static function routes() {

		/* Public, because a device with no token has to start somewhere. Everything that makes it
		   safe is inside: a code that only exists for ten minutes, dies on first use, burns after
		   five wrong guesses from anyone, and throttles the caller doing the guessing. */
		register_rest_route( 'aqm-mc/v1', '/pair', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'route_pair' ),
			'args'                => array(
				'code'  => array( 'required' => true ),
				'label' => array( 'required' => false ),
			),
		) );

		$guard = array( __CLASS__, 'verify' );
		$post  = function ( $cb ) use ( $guard ) {
			return array( 'methods' => 'POST', 'permission_callback' => $guard, 'callback' => $cb );
		};

		register_rest_route( 'aqm-mc/v1', '/admin/state', array(
			'methods'             => 'GET',
			'permission_callback' => $guard,
			'callback'            => array( __CLASS__, 'route_state' ),
		) );
		register_rest_route( 'aqm-mc/v1', '/admin/manual',  $post( array( __CLASS__, 'route_manual' ) ) );
		register_rest_route( 'aqm-mc/v1', '/admin/approve', $post( array( __CLASS__, 'route_approve' ) ) );
		register_rest_route( 'aqm-mc/v1', '/admin/enabled', $post( array( __CLASS__, 'route_enabled' ) ) );
		register_rest_route( 'aqm-mc/v1', '/admin/refresh', $post( array( __CLASS__, 'route_refresh' ) ) );
		register_rest_route( 'aqm-mc/v1', '/admin/signout', $post( array( __CLASS__, 'route_signout' ) ) );
	}

	public static function route_pair( $req ) {
		$ip   = self::ip();
		$fkey = 'aqm_mc_pf_' . md5( $ip );
		$fails = (int) get_transient( $fkey );
		if ( $fails >= self::PAIR_TRIES ) {
			return new WP_Error( 'aqm_mc_throttled', 'Too many attempts. Wait fifteen minutes and start a new code.', array( 'status' => 429 ) );
		}

		$pair = get_transient( self::PAIR );
		$code = preg_replace( '/\D/', '', (string) $req->get_param( 'code' ) );

		if ( ! is_array( $pair ) || empty( $pair['hash'] ) ) {
			return new WP_Error( 'aqm_mc_no_code', 'No pairing code is waiting. Start one in Settings, then type it here within ten minutes.', array( 'status' => 400 ) );
		}
		if ( '' === $code || ! password_verify( $code, $pair['hash'] ) ) {
			set_transient( $fkey, $fails + 1, self::THROTTLE );
			$pair['tries'] = (int) $pair['tries'] + 1;
			if ( $pair['tries'] >= self::PAIR_TRIES ) {
				// Burn it. Otherwise an attacker rotating IPs grinds six digits at leisure.
				delete_transient( self::PAIR );
			} else {
				set_transient( self::PAIR, $pair, max( 1, self::PAIR_TTL - ( time() - (int) $pair['made'] ) ) );
			}
			return new WP_Error( 'aqm_mc_bad_code', 'That code is not right.', array( 'status' => 403 ) );
		}

		/* Correct. One use only — delete before issuing, so a repeated request cannot mint a second
		   token off the same code. */
		delete_transient( self::PAIR );
		delete_transient( $fkey );
		$label = sanitize_text_field( (string) $req->get_param( 'label' ) );
		$token = self::issue( $label ? $label : 'A device' );
		return rest_ensure_response( array( 'token' => $token, 'label' => $label ) );
	}

	/** Everything the admin screen draws, in one request. */
	public static function route_state() {
		$set   = AQM_MC_Rates::settings();
		$store = get_option( AQM_MC_Rates::OPTION, array() );
		$dev   = self::current_device();

		$fetched = array();
		foreach ( AQM_MC_Rates::decorate( AQM_MC_Rates::fetched() ) as $r ) {
			if ( ! empty( $r['auto'] ) ) { continue; } // data feeds publish themselves; nothing to tick
			$r['approved'] = AQM_MC_Rates::is_approved( $r, $set );
			$fetched[] = $r;
		}

		return rest_ensure_response( array(
			'version'    => AQM_MC_VERSION,
			'enabled'    => (int) $set['enabled'],
			'manual'     => (string) $set['manual'],
			'showing'    => array_values( AQM_MC_Rates::listing() ),
			'fetched'    => $fetched,
			'attention'  => array_values( AQM_MC_Rates::missing() ),
			'stale_days' => AQM_MC_Rates::STALE,
			'read_at'    => isset( $store['time'] ) ? date_i18n( 'j M Y, g:i a', (int) $store['time'] ) : 'never',
			'next_at'    => wp_next_scheduled( AQM_MC_Rates::CRON ) ? date_i18n( 'j M Y, g:i a', wp_next_scheduled( AQM_MC_Rates::CRON ) ) : 'not scheduled',
			'device'     => $dev ? $dev['label'] : '',
			'paired_at'  => $dev ? date_i18n( 'j M Y', (int) $dev['created'] ) : '',
		) );
	}

	/** Saves one field of the settings without disturbing the others. */
	private static function put( $field, $value ) {
		$set = AQM_MC_Rates::settings();
		$set[ $field ] = $value;
		update_option( AQM_MC_Rates::SETTINGS, array(
			'enabled'  => (int) $set['enabled'],
			'manual'   => (string) $set['manual'],
			'approved' => is_array( $set['approved'] ) ? $set['approved'] : array(),
		), false );
	}

	public static function route_manual( $req ) {
		self::put( 'manual', sanitize_textarea_field( (string) $req->get_param( 'text' ) ) );
		return self::route_state();
	}

	public static function route_enabled( $req ) {
		self::put( 'enabled', $req->get_param( 'on' ) ? 1 : 0 );
		return self::route_state();
	}

	/**
	 * Ticks or unticks one rate. The stored value is the rate itself, not a boolean — that is what
	 * makes a CHANGED figure need ticking again, which is the rule that keeps a misread rate off
	 * the site. Do not simplify it to true/false.
	 */
	public static function route_approve( $req ) {
		$key = sanitize_text_field( (string) $req->get_param( 'key' ) );
		if ( '' === $key ) { return new WP_Error( 'aqm_mc_no_key', 'Which rate?', array( 'status' => 400 ) ); }
		$set      = AQM_MC_Rates::settings();
		$approved = is_array( $set['approved'] ) ? $set['approved'] : array();
		if ( $req->get_param( 'on' ) ) {
			$approved[ $key ] = round( (float) $req->get_param( 'rate' ), 2 );
		} else {
			unset( $approved[ $key ] );
		}
		self::put( 'approved', $approved );
		return self::route_state();
	}

	/** Reads every source now. Slow by nature — sixteen sites, one at a time. */
	public static function route_refresh() {
		AQM_MC_Rates::refresh();
		return self::route_state();
	}

	public static function route_signout() {
		$keep = array();
		foreach ( self::tokens() as $t ) { if ( ! hash_equals( (string) $t['id'], self::$current ) ) { $keep[] = $t; } }
		update_option( self::TOKENS, $keep, false );
		return rest_ensure_response( array( 'signed_out' => true ) );
	}

	/* ------------------------------------------- the wp-admin half: pair, revoke */

	/** Rendered inside AQM_MC_Rates::page(). The only reason left to open wp-admin. */
	public static function panel() {
		$code = get_transient( self::PAIR_SHOW . get_current_user_id() );
		$code = $code ? preg_replace( '/\D/', '', (string) $code ) : '';
		$app  = home_url( '/' . AQM_MC_APP_PATH . '/#admin' );

		echo '<h2 id="aqm-mc-devices">The app&rsquo;s own admin</h2>';
		echo '<p class="description">Everything on this page can be done from the app itself &mdash; type a rate, tick one, see what has stopped updating, read the sources now. Pair a device once and you need not come back here.</p>';

		if ( '' !== $code ) {
			echo '<div class="notice notice-success inline">'
				. '<p style="font-size:1.05rem"><strong>On this computer</strong> &mdash; just open the app; it pairs itself.</p>'
				. '<p><a class="button button-primary" href="' . esc_url( $app ) . '" target="_blank" rel="noopener">Open the app on this computer</a></p>'
				. '<p style="font-size:1.05rem;margin-top:1.2em"><strong>On your phone, or another browser</strong> &mdash; open <code>' . esc_html( home_url( '/' . AQM_MC_APP_PATH . '/#admin' ) ) . '</code>, tap <strong>Admin</strong>, and type this code:</p>'
				. '<p style="font-size:2.6rem;font-weight:700;letter-spacing:.18em;margin:.2em 0;font-family:monospace" id="aqm-mc-code">' . esc_html( $code ) . '</p>'
				. '<p><button type="button" class="button" id="aqm-mc-copy">Copy the code</button></p>'
				. '<p class="description">Good for ten minutes and one device. Five wrong guesses and it stops working &mdash; start another.</p></div>';

			/* ------------------------------------------------- the same-computer hand-off
			   wp-admin and the app are the SAME ORIGIN, so the code can be handed over inside the
			   browser instead of being read off one tab and typed into another - which on a desktop
			   is what pairing otherwise amounts to. Nothing crosses the network and nothing goes in
			   a URL.

			   It expires in ten minutes like the code itself, and the app deletes it the moment it
			   uses it. Anything able to read this key already holds an authenticated admin session
			   in this browser and could do considerably worse directly, so this is not a new
			   exposure - and the phone, being a different device, never sees it and still types the
			   digits. */
			?>
<script>
(function () {
	var code = <?php echo wp_json_encode( $code ); ?>;
	try {
		localStorage.setItem('aqm-mc-pair', JSON.stringify({ code: code, exp: Date.now() + <?php echo (int) self::PAIR_TTL * 1000; ?> }));
	} catch (e) {}
	var b = document.getElementById('aqm-mc-copy');
	if (b) {
		b.addEventListener('click', function () {
			var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy the code'; }, 2000); };
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(code).then(done, function () {}); return; }
			var r = document.createRange(); r.selectNode(document.getElementById('aqm-mc-code'));
			var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
			try { document.execCommand('copy'); done(); } catch (e) {}
			s.removeAllRanges();
		});
	}
})();
</script>
			<?php
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0">';
		wp_nonce_field( 'aqm_mc_pair' );
		echo '<input type="hidden" name="action" value="aqm_mc_pair">';
		echo '<button type="submit" class="button button-primary">Pair a device</button> ';
		echo '<span class="description">Gives you a 6-digit code to type into the app.</span>';
		echo '</form>';

		$tokens = self::tokens();
		if ( $tokens ) {
			echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Device</th><th>Paired</th><th>Last used</th><th>From</th><th></th></tr></thead><tbody>';
			foreach ( $tokens as $t ) {
				echo '<tr><td><strong>' . esc_html( $t['label'] ) . '</strong></td>'
					. '<td>' . esc_html( date_i18n( 'j M Y', (int) $t['created'] ) ) . '</td>'
					. '<td>' . esc_html( date_i18n( 'j M Y, g:i a', (int) $t['last'] ) ) . '</td>'
					. '<td><code>' . esc_html( $t['ip'] ) . '</code></td>'
					. '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'aqm_mc_revoke' );
				echo '<input type="hidden" name="action" value="aqm_mc_revoke">'
					. '<input type="hidden" name="device" value="' . esc_attr( $t['id'] ) . '">'
					. '<button type="submit" class="button button-small">Revoke</button></form></td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">Revoking signs that device out at once. A device token reaches the rates and nothing else on this site &mdash; it cannot read a post, change a setting, or touch another plugin.</p>';
		} else {
			echo '<p class="description">No devices paired yet.</p>';
		}
	}
}
