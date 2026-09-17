<?php
/**
 * AQM Mortgage Calculator - lender rates.
 * Copyright (c) 2026 A. Q. Mufti. All rights reserved.
 *
 * Collects published mortgage rates once a day on this server and offers them in the calculator as a
 * drop-down. Nothing is taken from a rate-comparison site. Each rate comes from the lender itself:
 *
 *   Bank of Canada  its Valet open-data service (prime, posted chartered-bank averages)
 *   TD              the rate service its own rates page reads (POST, ratesType resl)
 *   CIBC            the rate file its own rates page reads (productRatesLegacy)
 *   BMO             the JSON file its own rates page reads (bmo-ca-mortgages-rates.json)
 *   Scotiabank      its dated daily posted-rate feed (dmtsms api)
 *   Tangerine       the dated JSON file its own rates page reads
 *   National Bank   the figures its own page carries, keyed by name
 *   RBC, nesto, DUCA, Alterna Savings, FirstOntario, True North Mortgage, Manulife Bank,
 *   Neo Financial, Vancity, Coast Capital - read from the wording of the rates page itself
 *
 * Not included, and why: Desjardins asks not to be read automatically (robots.txt), and Meridian,
 * EQ Bank, B2B Bank and Community Trust block automated visits. Their rates can still be typed in by
 * hand in the settings page.
 *
 * Every entry carries the lender's name, the date it was read and a link to that lender's page.
 * The first five arrive as data, so the figure is unambiguous and is shown as read. The last two are
 * read from a page written for people, so they are held until ticked (see below).
 *
 * Rates you are quoted personally are not published anywhere, so AQ can also type rates from his
 * mortgage agent into Settings -> AQM Mortgage Calculator; those are shown exactly as typed.
 *
 * A lender page is written for people, not for programs: its wording can change at any time, and a
 * rate can then be read wrongly - that happened with nesto on 17 Sep 2026, where the variable rate was
 * picked up as the fixed one. So a rate read from a lender page is NEVER shown to visitors until it has
 * been ticked in Settings -> AQM Mortgage Calculator, and a changed figure has to be ticked again. Only
 * the Bank of Canada figures, which arrive as data rather than as a web page, publish themselves. That is why nothing here is load-bearing - the calculator works
 * without it, the last good figures are kept, every rate shows its date, and anything older than
 * 14 days is marked as out of date.
 */
defined( 'ABSPATH' ) || exit;

class AQM_MC_Rates {

	const OPTION   = 'aqm_mc_rates';
	const SETTINGS = 'aqm_mc_rates_settings';
	const CRON     = 'aqm_mc_rates_cron';
	const STALE    = 14; // days before a rate is marked out of date

	/** Where the rates come from. Each source returns a list of rate rows. */
	public static function sources() {
		return array(
			'boc' => array(
				'name' => 'Bank of Canada',
				'url'  => 'https://www.bankofcanada.ca/rates/banking-and-financial-statistics/posted-interest-rates-offered-by-chartered-banks/',
				'fn'   => 'parse_boc',
				'api'  => 'https://www.bankofcanada.ca/valet/observations/V80691311,V80691334,V80691335/json?recent=1',
			),
			'td' => array(
				'name' => 'TD Canada Trust',
				'url'  => 'https://www.td.com/ca/en/personal-banking/products/mortgages/mortgage-rates',
				'fn'   => 'parse_td',
				'api'  => 'https://psservice.td.com/ca/en/carate/getRates',
				'post' => '{"errorText":"Unable to get the rate","ratesType":"resl"}',
			),
			'cibc' => array(
				'name' => 'CIBC',
				'url'  => 'https://www.cibc.com/en/personal-banking/mortgages/mortgage-rates.html',
				'fn'   => 'parse_cibc',
				'api'  => 'https://www.cibconline.cibc.com/ebm-pno/api/v1/json/productRatesLegacy?lobId=5&sourceProductCode=FRCM%2C5YRVARCLO%2C',
			),
			'bmo' => array(
				'name' => 'BMO',
				'url'  => 'https://www.bmo.com/main/personal/mortgages/mortgage-rates/',
				'fn'   => 'parse_bmo',
				'api'  => 'https://www.bmo.com/public-data/api/v2.0/bmo-ca-mortgages-rates.json',
			),
			'scotia' => array(
				'name' => 'Scotiabank',
				'url'  => 'https://www.scotiabank.com/ca/en/personal/rates-prices/mortgages-rates.html',
				'fn'   => 'parse_scotia',
				'api'  => 'https://dmtsms.scotiabank.com/api/rates/daily/nonspecialmortgage',
			),
			'tangerine' => array(
				'name' => 'Tangerine',
				'url'  => 'https://www.tangerine.ca/en/rates/mortgage-rates',
				'fn'   => 'parse_tangerine',
				'api'  => 'https://www.tangerine.ca/content/dam/tangerine-shared/product-rates/currentRates.json',
			),
			'nbc' => array(
				'name' => 'National Bank',
				'url'  => 'https://www.nbc.ca/personal/mortgages/rates.html',
				'fn'   => 'parse_nbc',
				'api'  => 'https://www.nbc.ca/personal/mortgages/rates.html',
			),
			'rbc' => array(
				'name' => 'RBC Royal Bank',
				'url'  => 'https://www.rbcroyalbank.com/mortgages/mortgage-rates.html',
				'note' => 'RBC special offers',
				'fn'   => 'parse_terms',
			),
			'duca' => array(
				'name' => 'DUCA Credit Union',
				'url'  => 'https://www.duca.com/rates/',
				'note' => 'published rates',
				'fn'   => 'parse_terms',
			),
			'alterna' => array(
				'name' => 'Alterna Savings',
				'url'  => 'https://www.alterna.ca/en/rates/mortgages',
				'note' => 'published rates',
				'fn'   => 'parse_terms',
			),
			'firstontario' => array(
				'name' => 'FirstOntario Credit Union',
				'url'  => 'https://www.firstontario.com/rates',
				'note' => 'published rates',
				'fn'   => 'parse_terms',
			),
			'truenorth' => array(
				'name' => 'True North Mortgage',
				'url'  => 'https://www.truenorthmortgage.ca/rates',
				'note' => 'published rates',
				'fn'   => 'parse_terms',
			),
			'manulife' => array(
				'name' => 'Manulife Bank',
				'url'  => 'https://www.manulifebank.ca/current-rates.html',
				'note' => 'posted rates',
				'fn'   => 'parse_terms',
			),
			'neo' => array(
				'name' => 'Neo Financial',
				'url'  => 'https://www.neofinancial.com/mortgage',
				'note' => 'published rates',
				'fn'   => 'parse_terms',
			),
			'vancity' => array(
				'name' => 'Vancity',
				'url'  => 'https://www.vancity.com/rates/',
				'note' => 'published rates',
				'fn'   => 'parse_terms',
			),
			'coastcapital' => array(
				'name' => 'Coast Capital',
				'url'  => 'https://www.coastcapitalsavings.com/rates/mortgages',
				'note' => 'published rates',
				'fn'   => 'parse_terms',
			),
			'nesto' => array(
				'name' => 'nesto',
				'url'  => 'https://www.nesto.ca/mortgage-rates/',
				'note' => 'lowest rates nesto publishes',
				'fn'   => 'parse_terms',
			),
		);
	}

	public static function settings() {
		$s = get_option( self::SETTINGS, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), array( 'enabled' => 1, 'manual' => '', 'approved' => array() ) );
	}

	public static function boot() {
		add_action( self::CRON, array( __CLASS__, 'refresh' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_aqm_mc_rates_save', array( __CLASS__, 'save' ) );
		add_action( 'plugins_loaded', function () {
			if ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + 120, 'daily', self::CRON ); }
		} );
		register_deactivation_hook( AQM_MC_FILE, function () { wp_clear_scheduled_hook( self::CRON ); } );
	}

	/* ------------------------------------------------------------------ read */

	/** The rates the calculator should offer: fetched ones first, then any typed by hand. */
	public static function listing() {
		$set = self::settings();
		if ( empty( $set['enabled'] ) ) { return array(); }
		$out = array();
		foreach ( self::fetched() as $row ) {
			if ( empty( $row['auto'] ) && ! self::is_approved( $row, $set ) ) { continue; } // waiting to be checked
			$out[] = $row;
		}
		foreach ( self::manual_rows( $set['manual'] ) as $row ) { $out[] = $row; }
		return self::decorate( $out );
	}

	/** Everything read from the sources, ticked or not. */
	public static function fetched() {
		$store = get_option( self::OPTION, array() );
		$rows  = ( isset( $store['items'] ) && is_array( $store['items'] ) ) ? $store['items'] : array();
		$all   = self::sources();
		foreach ( $rows as $i => $r ) {
			$src = isset( $r['src'] ) ? $r['src'] : '';
			$rows[ $i ]['auto'] = isset( $all[ $src ]['api'] ) ? 1 : 0; // a data feed, not a web page
			$rows[ $i ]['key']  = $src . '|' . ( isset( $r['label'] ) ? $r['label'] : '' );
		}
		return $rows;
	}

	public static function is_approved( $row, $set = null ) {
		$set = $set ? $set : self::settings();
		$key = isset( $row['key'] ) ? $row['key'] : ( ( isset( $row['src'] ) ? $row['src'] : '' ) . '|' . ( isset( $row['label'] ) ? $row['label'] : '' ) );
		$ok  = isset( $set['approved'][ $key ] ) ? (float) $set['approved'][ $key ] : null;
		return ( null !== $ok && abs( $ok - (float) $row['rate'] ) < 0.005 );
	}

	/** Adds the date each rate was read and marks anything too old. */
	public static function decorate( $rows ) {
		$cut = time() - self::STALE * DAY_IN_SECONDS;
		foreach ( $rows as $i => $row ) {
			$rows[ $i ]['stale'] = ( ! empty( $row['time'] ) && $row['time'] < $cut ) ? 1 : 0;
			$rows[ $i ]['date']  = ! empty( $row['time'] ) ? date_i18n( 'j M Y', (int) $row['time'] ) : '';
		}
		return $rows;
	}

	/** "Lender | what it is | 4.29" per line, typed in the settings page. */
	public static function manual_rows( $text ) {
		$rows = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( $line === '' || strpos( $line, '|' ) === false ) { continue; }
			$p = array_map( 'trim', explode( '|', $line ) );
			$rate = isset( $p[2] ) ? (float) str_replace( '%', '', $p[2] ) : 0;
			if ( $rate < 1 || $rate > 20 || $p[0] === '' ) { continue; }
			$rows[] = array( 'lender' => sanitize_text_field( $p[0] ), 'label' => sanitize_text_field( isset( $p[1] ) ? $p[1] : '' ), 'rate' => round( $rate, 2 ), 'url' => '', 'time' => time(), 'own' => 1 );
		}
		return $rows;
	}

	/* --------------------------------------------------------------- refresh */

	/** Reads every source. Keeps the previous figures for a source that cannot be read. */
	public static function refresh() {
		$store = get_option( self::OPTION, array() );
		$old   = ( isset( $store['items'] ) && is_array( $store['items'] ) ) ? $store['items'] : array();
		$items = array();
		$log   = array();
		foreach ( self::sources() as $key => $src ) {
			$url  = isset( $src['api'] ) ? $src['api'] : $src['url'];
			$args = array( 'timeout' => 25, 'redirection' => 3, 'user-agent' => 'AQM Mortgage Calculator/' . AQM_MC_VERSION . ' (+' . home_url() . ')' );
			if ( isset( $src['post'] ) ) {
				$args['body']    = $src['post'];
				$args['headers'] = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
				$res = wp_remote_post( $url, $args );
			} else {
				$res = wp_remote_get( $url, $args );
			}
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( 200 !== $code ) {
				$log[ $key ] = is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . $code;
			} else {
				$fn   = array( __CLASS__, $src['fn'] );
				$rows = call_user_func( $fn, (string) wp_remote_retrieve_body( $res ), $key, $src );
				if ( $rows ) {
					$log[ $key ] = 'read ' . count( $rows ) . ' rate(s)';
					foreach ( $rows as $r ) { $items[] = $r; }
				} else {
					$log[ $key ] = 'page read, but no rate recognised - the page wording has probably changed';
				}
			}
			if ( 'read' !== substr( (string) ( isset( $log[ $key ] ) ? $log[ $key ] : '' ), 0, 4 ) ) {
				foreach ( $old as $r ) { if ( isset( $r['src'] ) && $r['src'] === $key ) { $items[] = $r; } } // keep what we had
			}
		}
		update_option( self::OPTION, array( 'time' => time(), 'items' => $items, 'log' => $log ), false );
		return $log;
	}

	/**
	 * A fixed rate below the variable rate of the same term almost always means the page was read
	 * wrongly (the two were swapped). Mark both so the settings page can say so.
	 */
	public static function flag_doubt( $rows ) {
		$by = array();
		foreach ( $rows as $i => $r ) { if ( preg_match( '/^(\d+)-year (fixed|variable)/', $r['label'], $m ) ) { $by[ $m[1] ][ $m[2] ] = $i; } }
		foreach ( $by as $pair ) {
			if ( isset( $pair['fixed'], $pair['variable'] ) && $rows[ $pair['fixed'] ]['rate'] < $rows[ $pair['variable'] ]['rate'] ) {
				$rows[ $pair['fixed'] ]['doubt'] = 1;
				$rows[ $pair['variable'] ]['doubt'] = 1;
			}
		}
		return $rows;
	}

	/** Bank of Canada Valet API: prime rate and posted conventional mortgage rates. */
	public static function parse_boc( $body, $key, $src ) {
		$data = json_decode( $body, true );
		if ( ! isset( $data['observations'][0] ) ) { return array(); }
		$o = $data['observations'][0];
		$when = isset( $o['d'] ) ? strtotime( $o['d'] ) : time();
		$map = array(
			'V80691311' => 'Prime rate (all chartered banks)',
			'V80691334' => 'Posted 3-year fixed (chartered bank average)',
			'V80691335' => 'Posted 5-year fixed (chartered bank average)',
		);
		$rows = array();
		foreach ( $map as $series => $label ) {
			$v = isset( $o[ $series ]['v'] ) ? (float) $o[ $series ]['v'] : 0;
			if ( $v >= 0.5 && $v <= 20 ) {
				$rows[] = array( 'lender' => $src['name'], 'label' => $label, 'rate' => round( $v, 2 ), 'url' => $src['url'], 'time' => $when, 'src' => $key );
			}
		}
		return $rows;
	}

	/**
	 * A lender's own rates page: takes the FIRST rate it publishes for each term and type, which is
	 * the rate at the top of the page (the one being offered), not the posted rates further down.
	 */
	/**
	 * TD publishes its rates as data for its own page: a map of product codes to
	 * [posted, discount, offered rate, APR, flag]. MTGF036C is the 3-year fixed closed,
	 * MTGF060C the 5-year fixed closed and MTGV060C the 5-year variable closed.
	 */
	public static function parse_td( $body, $key, $src ) {
		$d = json_decode( $body, true );
		if ( ! is_array( $d ) ) { return array(); }
		$want = array(
			'MTGF036C' => array( '3-year fixed (special offer)', 2 ),
			'MTGF060C' => array( '5-year fixed (special offer)', 2 ),
			'MTGV060C' => array( '5-year variable (special offer)', 2 ),
			'MTGF060C_posted' => array( '5-year fixed (posted)', 0 ),
		);
		$rows = array();
		foreach ( $want as $code => $x ) {
			$real = str_replace( '_posted', '', $code );
			$vals = isset( $d[ $real ]['nonHighRatio'] ) ? $d[ $real ]['nonHighRatio'] : null;
			if ( ! is_array( $vals ) || ! isset( $vals[ $x[1] ] ) ) { continue; }
			$rows[] = self::row( $src, $key, $x[0], $vals[ $x[1] ] );
		}
		return array_filter( $rows );
	}

	/**
	 * CIBC serves its page rates as JavaScript variables: rows of
	 * [term, null, rate code, rate]. Code 1 is the posted rate, code 18 the special offer.
	 */
	public static function parse_cibc( $body, $key, $src ) {
		$i = strpos( $body, 'var FRCM' );
		if ( false === $i ) { return array(); }
		$j    = strpos( $body, 'var ', $i + 4 );
		$block = ( false === $j ) ? substr( $body, $i ) : substr( $body, $i, $j - $i );
		if ( ! preg_match_all( "/\\['(\\d+)_[^']*',\\s*null,\\s*(\\d+),\\s*'([\\-\\d.]+)'/", $block, $m, PREG_SET_ORDER ) ) { return array(); }
		$want = array( '3|18' => '3-year fixed (special offer)', '5|18' => '5-year fixed (special offer)', '5|1' => '5-year fixed (posted)' );
		$rows = array();
		foreach ( $m as $x ) {
			$id = $x[1] . '|' . $x[2];
			if ( isset( $want[ $id ] ) ) { $rows[] = self::row( $src, $key, $want[ $id ], $x[3] ); }
		}
		return array_filter( $rows );
	}

	/** BMO publishes the rates on its page as a plain JSON file. */
	public static function parse_bmo( $body, $key, $src ) {
		$d = json_decode( $body, true );
		if ( ! is_array( $d ) ) { return array(); }
		$want = array(
			'fixed3YearClosedSpecial'     => '3-year fixed (special offer)',
			'smartFixed5YearClosedSpecial' => '5-year fixed (special offer)',
			'variable5YearClosedSpecial'  => '5-year variable (special offer)',
		);
		$rows = array();
		foreach ( $want as $field => $label ) {
			if ( isset( $d[ $field ] ) ) { $rows[] = self::row( $src, $key, $label, $d[ $field ] ); }
		}
		return array_filter( $rows );
	}

	/** Scotiabank publishes its posted mortgage rates as dated JSON. */
	public static function parse_scotia( $body, $key, $src ) {
		$d = json_decode( $body, true );
		if ( ! isset( $d['data'] ) || ! is_array( $d['data'] ) ) { return array(); }
		$when = isset( $d['update_time'] ) ? strtotime( $d['update_time'] ) : time();
		$rows = array();
		foreach ( $d['data'] as $product ) {
			if ( empty( $product['TERMS'] ) || false === stripos( (string) $product['PRODUCT'], 'N.H.A' ) ) { continue; }
			foreach ( $product['TERMS'] as $t ) {
				if ( 'Y' !== ( isset( $t['TERM_UNIT'] ) ? $t['TERM_UNIT'] : '' ) ) { continue; }
				$term = (int) $t['TERM_VALUE'];
				if ( 3 !== $term && 5 !== $term ) { continue; }
				$rows[] = self::row( $src, $key, $term . '-year fixed (posted)', isset( $t['RATE'] ) ? $t['RATE'] : 0, $when );
			}
		}
		return array_filter( $rows );
	}

	/** One rate row, or null when the figure is not a believable mortgage rate. */
	public static function row( $src, $key, $label, $rate, $when = null ) {
		$rate = round( (float) $rate, 2 );
		if ( $rate < 1 || $rate > 15 ) { return null; }
		return array(
			'lender' => $src['name'],
			'label'  => $label,
			'rate'   => $rate,
			'url'    => $src['url'],
			'time'   => $when ? $when : time(),
			'src'    => $key,
		);
	}

	/** Tangerine publishes every rate its pages show in one dated JSON file. */
	public static function parse_tangerine( $body, $key, $src ) {
		$d = json_decode( $body, true );
		if ( ! isset( $d['rates'] ) || ! is_array( $d['rates'] ) ) { return array(); }
		$rows = array();
		foreach ( $d['rates'] as $r ) {
			if ( ! isset( $r['group'] ) || 'mortgage' !== $r['group'] ) { continue; }
			$term = isset( $r['term'] ) ? (int) $r['term'] : 0;
			if ( 3 !== $term && 5 !== $term ) { continue; }
			$var  = ( false !== stripos( (string) ( isset( $r['product_description'] ) ? $r['product_description'] : '' ), 'variable' ) )
				|| ( false !== stripos( (string) ( isset( $r['account_term'] ) ? $r['account_term'] : '' ), 'variable' ) );
			$when = isset( $r['date'] ) ? strtotime( str_replace( '-', '/', $r['date'] ) ) : time(); // MM-DD-YYYY
			$rows[] = self::row( $src, $key, $term . '-year ' . ( $var ? 'variable' : 'fixed' ), isset( $r['interest_rate'] ) ? $r['interest_rate'] : 0, $when ? $when : time() );
		}
		return array_filter( $rows );
	}

	/** National Bank builds its table from figures that sit in the page itself, keyed by name. */
	public static function parse_nbc( $body, $key, $src ) {
		$want = array( 'tauxPromo3ansF' => '3-year fixed (special offer)', 'tauxPromo5ansF' => '5-year fixed (special offer)' );
		$rows = array();
		foreach ( $want as $field => $label ) {
			if ( preg_match( '/' . $field . '[^0-9]{0,40}(\d{1,2})[.,](\d{1,3})/', $body, $m ) ) {
				$rows[] = self::row( $src, $key, $label, $m[1] . '.' . $m[2] );
			}
		}
		return array_filter( $rows );
	}

	public static function parse_terms( $body, $key, $src ) {
		$text = self::text( $body );
		$rx = '/(\d{1,2})\s*[-\x{2010}-\x{2015}\s]?\s*(?:year|yr)s?\b[^%\d]{0,28}?\b(fixed|variable)/iu';
		if ( ! preg_match_all( $rx, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) { return array(); }
		$want = array( '3 fixed', '5 fixed', '5 variable' );
		$seen = array();
		$rows = array();
		foreach ( $m as $x ) {
			$term = (int) $x[1][0];
			$type = strtolower( $x[2][0] );
			$id   = $term . ' ' . $type;
			if ( ! in_array( $id, $want, true ) || isset( $seen[ $id ] ) ) { continue; }
			$at   = $x[0][1];
			$after = substr( $text, $at + strlen( $x[0][0] ), 120 );
			$stop  = preg_split( '/\d{1,2}\s*[-\x{2010}-\x{2015}\s]?\s*year\s+(?:fixed|variable)/iu', $after );
			$rate  = self::rate_near( $stop[0], 'after' );
			if ( null === $rate ) { $rate = self::rate_near( substr( $text, max( 0, $at - 90 ), min( 90, $at ) ), 'before' ); }
			if ( null === $rate ) { continue; }
			$seen[ $id ] = 1;
			$rows[] = array(
				'lender' => $src['name'],
				'label'  => $term . '-year ' . $type . ( isset( $src['note'] ) ? ' (' . $src['note'] . ')' : '' ),
				'rate'   => round( $rate, 2 ),
				'url'    => $src['url'],
				'time'   => time(),
				'src'    => $key,
			);
		}
		return $rows;
	}

	/**
	 * The first believable mortgage rate in a piece of text. A variable rate is usually written
	 * "Prime -1.00% (3.45%)", so a figure right after "prime" or a plus or minus sign is a discount
	 * off prime rather than a rate, and anything outside 2% to 12% is not a mortgage rate either.
	 */
	public static function rate_near( $text, $dir ) {
		if ( ! preg_match_all( '/(\d{1,2}\.\d{1,3})\s*%/', $text, $p, PREG_OFFSET_CAPTURE ) ) { return null; }
		$hits = $p[1];
		if ( 'before' === $dir ) { $hits = array_reverse( $hits ); }
		foreach ( $hits as $hit ) {
			$rate = (float) $hit[0];
			$lead = strtolower( substr( $text, max( 0, $hit[1] - 24 ), min( 24, $hit[1] ) ) );
			if ( preg_match( '/(prime|[-+]\s*)$/', $lead ) ) { continue; }
			if ( $rate < 2 || $rate > 12 ) { continue; }
			return $rate;
		}
		return null;
	}

	/** Page HTML to plain readable text. */
	public static function text( $html ) {
		$html = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = preg_replace( '/<[^>]+>/', ' ', $html );
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $html ) );
	}

	/* ----------------------------------------------------------------- admin */

	public static function menu() {
		add_options_page( 'AQM Mortgage Calculator', 'AQM Mortgage Calculator', 'manage_options', 'aqm-mc', array( __CLASS__, 'page' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'aqm_mc_rates_save' );
		$approved = array();
		if ( ! empty( $_POST['approve'] ) && is_array( $_POST['approve'] ) ) {
			foreach ( wp_unslash( $_POST['approve'] ) as $key => $rate ) { $approved[ sanitize_text_field( $key ) ] = round( (float) $rate, 2 ); }
		}
		update_option( self::SETTINGS, array(
			'enabled'  => empty( $_POST['enabled'] ) ? 0 : 1,
			'manual'   => isset( $_POST['manual'] ) ? sanitize_textarea_field( wp_unslash( $_POST['manual'] ) ) : '',
			'approved' => $approved,
		), false );
		$msg = 'saved';
		if ( ! empty( $_POST['refresh'] ) ) { self::refresh(); $msg = 'refreshed'; }
		wp_safe_redirect( admin_url( 'options-general.php?page=aqm-mc&msg=' . $msg ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$set = self::settings();
		$store = get_option( self::OPTION, array() );
		echo '<div class="wrap"><h1>AQM Mortgage Calculator</h1>';
		if ( isset( $_GET['msg'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . ( 'refreshed' === $_GET['msg'] ? 'Settings saved and rates read again.' : 'Settings saved.' ) . '</p></div>'; }
		echo '<form id="aqm-mc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'aqm_mc_rates_save' );
		echo '<input type="hidden" name="action" value="aqm_mc_rates_save">';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">Rate drop-down</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( $set['enabled'], 1, false ) . '> Offer published lender rates in the calculator</label><p class="description">Turn this off and every scenario simply starts at the calculator&rsquo;s own default rate.</p></td></tr>';
		echo '<tr><th scope="row"><label for="aqm-mc-manual">Your own rates</label></th><td><textarea id="aqm-mc-manual" name="manual" rows="5" class="large-text code" placeholder="Broker name | 5-year fixed, insured | 4.09">' . esc_textarea( $set['manual'] ) . '</textarea><p class="description">One per line: <code>who quoted it | what it is | rate</code>. These show exactly as typed, above the ones read from lender pages. Use this for rates your mortgage agent gives you.</p></td></tr>';
		echo '</table>';
		echo '</form><hr>';

		echo '<h2>Rates read from lender pages</h2>';
		echo '<p class="description">A lender&rsquo;s page is written for people, so a rate can be read wrongly. Nothing here reaches the calculator until you tick it, and a rate that changes has to be ticked again. Open the source page, check the figure, then tick it and save.</p>';
		$fetched = self::decorate( self::fetched() );
		$pending = 0;
		echo '<table class="widefat striped"><thead><tr><th style="width:110px">Show it</th><th>Lender</th><th>What it is</th><th>Rate read</th><th>Read on</th><th>Check against</th></tr></thead><tbody>';
		foreach ( $fetched as $r ) {
			if ( ! empty( $r['auto'] ) ) { continue; }
			$ok = self::is_approved( $r, $set );
			if ( ! $ok ) { $pending++; }
			echo '<tr><td><label><input type="checkbox" form="aqm-mc-form" name="approve[' . esc_attr( $r['key'] ) . ']" value="' . esc_attr( $r['rate'] ) . '" ' . checked( $ok, true, false ) . '> ' . ( $ok ? 'shown' : '<strong>check</strong>' ) . '</label></td>'
				. '<td>' . esc_html( $r['lender'] ) . '</td><td>' . esc_html( $r['label'] ) . '</td><td><strong>' . esc_html( number_format( (float) $r['rate'], 2 ) ) . '%</strong></td>'
				. '<td>' . esc_html( $r['date'] ) . ( empty( $r['stale'] ) ? '' : ' <span style="color:#b32d2e">out of date</span>' ) . '</td>'
				. '<td><a href="' . esc_url( $r['url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $r['url'], PHP_URL_HOST ) ) . '</a></td></tr>';
		}
		if ( ! $fetched ) { echo '<tr><td colspan="6">Nothing read yet. Use &ldquo;Save and read rates now&rdquo;.</td></tr>'; }
		echo '</tbody></table>';
		if ( $pending ) { echo '<p><strong>' . (int) $pending . ' rate(s) are waiting to be checked.</strong> Tick the ones that match the lender&rsquo;s own page, then save.</p>'; }

		echo '<h2>What the calculator is showing now</h2>';
		$items = self::listing();
		if ( empty( $set['enabled'] ) ) { echo '<p><em>The drop-down is switched off, so the calculator is not showing any of these.</em></p>'; }
		echo '<table class="widefat striped"><thead><tr><th>Lender</th><th>What it is</th><th>Rate</th><th>Read on</th><th>Source</th></tr></thead><tbody>';
		if ( ! $items ) { echo '<tr><td colspan="5">Nothing yet. Bank of Canada figures appear on their own; lender rates appear once ticked above.</td></tr>'; }
		foreach ( $items as $r ) {
			echo '<tr><td><strong>' . esc_html( $r['lender'] ) . '</strong>' . ( empty( $r['own'] ) ? '' : ' <em>(yours)</em>' ) . '</td><td>' . esc_html( $r['label'] ) . '</td><td>' . esc_html( number_format( (float) $r['rate'], 2 ) ) . '%</td>'
				. '<td>' . esc_html( $r['date'] ) . ( empty( $r['stale'] ) ? '' : ' <span style="color:#b32d2e">out of date</span>' ) . '</td>'
				. '<td>' . ( $r['url'] ? '<a href="' . esc_url( $r['url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $r['url'], PHP_URL_HOST ) ) . '</a>' : '&mdash;' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Where they come from</h2><ul class="ul-disc">';
		foreach ( self::sources() as $key => $src ) {
			$state = isset( $store['log'][ $key ] ) ? $store['log'][ $key ] : 'not read yet';
			echo '<li><strong>' . esc_html( $src['name'] ) . '</strong> &mdash; <a href="' . esc_url( $src['url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $src['url'], PHP_URL_HOST ) ) . '</a>: ' . esc_html( $state ) . '</li>';
		}
		echo '</ul>';
		echo '<p class="description">Desjardins asks not to be read automatically, and Meridian, EQ Bank, B2B Bank and Community Trust block automated visits, so they are not listed. Type their rates in above if you want them.</p>';
		echo '<p class="description">Read once a day. A rate is only ever a starting point for the calculator: it is not an offer, and it is not a rate you or a visitor has been approved for. Rates quoted to a buyer depend on the lender, the property and the buyer.</p>';
		echo '<p><button type="submit" form="aqm-mc-form" class="button button-primary">Save settings</button> <button type="submit" form="aqm-mc-form" name="refresh" value="1" class="button">Save and read rates now</button></p>';
		echo '<p class="description">Last read: ' . ( isset( $store['time'] ) ? esc_html( date_i18n( 'j M Y, g:i a', (int) $store['time'] ) ) : 'never' ) . '. Next: ' . ( wp_next_scheduled( self::CRON ) ? esc_html( date_i18n( 'j M Y, g:i a', wp_next_scheduled( self::CRON ) ) ) : 'not scheduled' ) . '.</p>';
		echo '</div>';
	}
}
