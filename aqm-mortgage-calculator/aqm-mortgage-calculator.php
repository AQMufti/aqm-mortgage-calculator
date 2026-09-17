<?php
/**
 * Plugin Name: AQM Mortgage Calculator
 * Description: Canadian mortgage calculator for Ontario and Toronto buyers: three live side-by-side scenarios (default 10%, 15%, 20% down, all editable), semi-annual compounding, CMHC insurance and its Ontario sales tax, minimum down payment and $1.5M insured-price rules, 30-year amortization eligibility, new-home HST relief, Ontario and Toronto land transfer tax with first-time buyer rebates, a balance chart and a full amortization schedule with CSV download. Shortcode: [aqm_mortgage_calculator]. No external scripts.
 * Version:     1.5.0
 * Author:      A. Q. Mufti
 * Plugin URI:  https://github.com/AQMufti/aqm-mortgage-calculator
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 *
 * Copyright (c) 2026 A. Q. Mufti. All rights reserved.
 *
 * 1.5.0 (17 Sep 2026): ten more lenders - Tangerine and National Bank (read as data, shown as read),
 * and DUCA, Alterna Savings, FirstOntario, True North Mortgage, Manulife Bank, Neo Financial, Vancity
 * and Coast Capital (read from their pages, so they wait to be ticked). Sixteen sources in all.
 * 1.4.0 (17 Sep 2026): the big banks are in. TD, CIBC, BMO and Scotiabank publish the rates their own
 * pages show as data files, so those are read straight from the bank and shown without a tick. RBC and
 * nesto are read from the page itself and still have to be ticked in Settings -> AQM Mortgage Calculator.
 * 1.3.1 (17 Sep 2026): a rate read from a lender's page is held back until it is ticked in
 * Settings -> AQM Mortgage Calculator; only the Bank of Canada data feed publishes itself. Reading a
 * page can go wrong - nesto's variable rate was read as its fixed rate - so nothing goes to visitors
 * unchecked. Reading a rate also now ignores "Prime -1.00%" discounts and looks above the label as
 * well as below it.
 * 1.3.0 (17 Sep 2026): a rate drop-down in each scenario, filled once a day from the Bank of Canada's
 * open data and from lenders' own published rate pages (never from a rate-comparison site), plus rates
 * typed in by hand. Every rate shows the lender, the date read and a link. Settings -> AQM Mortgage Calculator.
 * 1.2.2 (17 Sep 2026): copyright notice and a full disclaimer under the calculator.
 * 1.2.1 (17 Sep 2026): the other costs are edited right in the side-by-side table, one amount box per
 * scenario, filled with typical amounts; both Miscellaneous rows always show, with an editable name (suggested: Survey, Utility hook-ups;
 * a drop-down offers more).
 * 1.2.0 (17 Sep 2026): "Other costs of buying" - lawyer, disbursements, title insurance, adjustments,
 * home insurance, moving, inspection, appraisal, status certificate and two miscellaneous items,
 * all editable, added to cash needed (items paid before closing shown separately).
 * 1.1.1 (17 Sep 2026): "Share of mortgage repaid" row under the term figures, so a smaller
 * principal-paid dollar amount on a smaller mortgage is not misread.
 * 1.1.0 (17 Sep 2026): script and styles moved to assets/ files. As inline page script, WordPress
 * turned every "&&" into "&#038;&#038;", which stopped the whole calculator. Amounts are formatted
 * as you type, % and $ down payment fill each other, everything recalculates on every keystroke,
 * and new-home HST relief plus first-time buyer programs are built in.
 *
 * RULES BUILT IN (assets/aqm-mc.js) - checked against official sources 17 Sep 2026:
 *  - Minimum down payment (FCAC): 5% of the first $500,000; 10% of the part from $500,000 to
 *    $1.5M; 20% at $1.5M or more (insured price cap $1.5M since 15 Dec 2024).
 *  - 30-year insured amortization: first-time buyers and buyers of new builds (since 15 Dec 2024).
 *  - CMHC premium by loan-to-value: <=80% none; <=85% 2.80%; <=90% 3.10%; <=95% 4.00%;
 *    +0.20% when amortization is over 25 years. Ontario 8% PST on the premium, paid in cash.
 *  - Canadian fixed-rate mortgages compound semi-annually.
 *  - Ontario LTT: 0.5% to $55K, 1% to $250K, 1.5% to $400K, 2% to $2M, 2.5% above.
 *    First-time buyer refund up to $4,000.
 *  - Toronto MLTT (rates from 1 Apr 2026): 0.5/1/1.5/2% as Ontario to $2M, 2.5% to $3M, 4.40% to $4M,
 *    5.45% to $5M, 6.50% to $10M, 7.55% to $20M, 8.60% above. First-time purchaser rebate up to $4,475.
 *  - New-home HST relief, agreements 1 Apr 2026 - 31 Mar 2027 (Ontario 2026 Budget, all buyers of a new
 *    home as their primary residence): provincial 8% rebated in full up to $80,000 for homes to $1.5M,
 *    tapering to $24,000 at $1.85M; federal 5% covered up to $50,000 to $1.5M (Ontario New Home
 *    Affordability Payment), tapering to $0 at $1.85M. Federal First-Time Home Buyers' GST rebate:
 *    100% up to $50,000 to $1M, tapering to $0 at $1.5M.
 *  - First-time buyer programs shown for information: Home Buyers' Amount ($10,000 x 15% = $1,500 tax
 *    credit), FHSA ($8,000 a year, $40,000 lifetime), RRSP Home Buyers' Plan ($60,000 per person).
 */
defined( 'ABSPATH' ) || exit;

define( 'AQM_MC_VERSION', '1.5.0' );
define( 'AQM_MC_FILE', __FILE__ );
require_once __DIR__ . '/aqm-rates.php';
AQM_MC_Rates::boot();

if ( file_exists( __DIR__ . '/aqm-updater.php' ) ) {
	require_once __DIR__ . '/aqm-updater.php';
	new AQM_Updater( __FILE__, AQM_MC_VERSION, 'AQMufti/aqm-mortgage-calculator', 'AQM Mortgage Calculator', 'Canadian mortgage calculator for Ontario and Toronto buyers.' );
}

add_action( 'wp_enqueue_scripts', function () {
	$v = function ( $f ) { $p = __DIR__ . '/assets/' . $f; return AQM_MC_VERSION . '.' . ( file_exists( $p ) ? filemtime( $p ) : 0 ); };
	wp_register_style( 'aqm-mc', plugins_url( 'assets/aqm-mc.css', __FILE__ ), array(), $v( 'aqm-mc.css' ) );
	wp_register_script( 'aqm-mc', plugins_url( 'assets/aqm-mc.js', __FILE__ ), array(), $v( 'aqm-mc.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	global $post;
	if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'aqm_mortgage_calculator' ) ) {
		wp_enqueue_style( 'aqm-mc' );
		wp_enqueue_script( 'aqm-mc' );
	}
} );

add_shortcode( 'aqm_mortgage_calculator', function ( $atts ) {
	$a = shortcode_atts( array( 'price' => '850000', 'rate' => '4.19', 'amortization' => '25', 'down1' => '10', 'down2' => '15', 'down3' => '20' ), $atts );
	wp_enqueue_style( 'aqm-mc' );
	wp_enqueue_script( 'aqm-mc' );
	static $n = 0; $n++;
	$id  = 'aqm-mc-' . $n;
	$rates = class_exists( 'AQM_MC_Rates' ) ? AQM_MC_Rates::listing() : array();
	$cfg = array( 'price' => (float) $a['price'], 'rate' => (float) $a['rate'], 'amort' => (int) $a['amortization'], 'downs' => array( (float) $a['down1'], (float) $a['down2'], (float) $a['down3'] ), 'rates' => array_values( $rates ) );
	ob_start();
	?>
<div class="aqm-mc" id="<?php echo esc_attr( $id ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
<noscript><p>This calculator needs JavaScript switched on.</p></noscript>

<div class="aqm-mc__card">
	<h3>The property</h3>
	<div class="aqm-mc__shared">
		<div><label for="<?php echo $id; ?>-price">Purchase price</label><div class="aqm-mc__pfx"><span>$</span><input type="text" inputmode="numeric" autocomplete="off" id="<?php echo $id; ?>-price" data-k="price" data-fmt="money"></div></div>
		<div><label for="<?php echo $id; ?>-loc">Location</label><select id="<?php echo $id; ?>-loc" data-k="loc"><option value="on">Ontario (outside Toronto)</option><option value="to">City of Toronto</option></select></div>
		<div><label for="<?php echo $id; ?>-freq">Payment frequency</label><select id="<?php echo $id; ?>-freq" data-k="freq"><option value="12">Monthly</option><option value="24">Semi-monthly</option><option value="26">Bi-weekly</option><option value="26a">Accelerated bi-weekly</option><option value="52">Weekly</option><option value="52a">Accelerated weekly</option></select></div>
		<div><label for="<?php echo $id; ?>-term">Mortgage term</label><select id="<?php echo $id; ?>-term" data-k="term"><?php foreach ( array( 1, 2, 3, 4, 5, 7, 10 ) as $t ) { echo '<option value="' . $t . '"' . ( 5 === $t ? ' selected' : '' ) . '>' . $t . ( 1 === $t ? ' year' : ' years' ) . '</option>'; } ?></select></div>
		<label class="aqm-mc__chk"><input type="checkbox" data-k="ftb"> First-time home buyer</label>
		<label class="aqm-mc__chk"><input type="checkbox" data-k="newbuild"> Newly built home (from a builder)</label>
		<div class="aqm-mc__hst" data-k="hstwrap"><label for="<?php echo $id; ?>-hst">The price above is</label><select id="<?php echo $id; ?>-hst" data-k="hstmode"><option value="incl">Builder's all-in price (HST included)</option><option value="plus">Before HST</option></select></div>
	</div>
</div>

<div class="aqm-mc__card">
	<h3>Compare three scenarios</h3>
	<div class="aqm-mc__scen">
	<?php foreach ( array( 'A', 'B', 'C' ) as $i => $L ) : ?>
		<div class="aqm-mc__sc" style="--c:var(--s<?php echo $i; ?>)" data-s="<?php echo $i; ?>">
			<h4><i aria-hidden="true"></i>Scenario <?php echo $L; ?></h4>
			<div class="aqm-mc__two">
				<div><label for="<?php echo "$id-dp$i"; ?>">Down payment (%)</label><div class="aqm-mc__sfx"><input type="text" inputmode="decimal" autocomplete="off" id="<?php echo "$id-dp$i"; ?>" data-k="dpct" data-fmt="pct"><span>%</span></div></div>
				<div><label for="<?php echo "$id-dd$i"; ?>">Down payment ($)</label><div class="aqm-mc__pfx"><span>$</span><input type="text" inputmode="numeric" autocomplete="off" id="<?php echo "$id-dd$i"; ?>" data-k="ddol" data-fmt="money"></div></div>
			</div>
			<div class="aqm-mc__two">
				<div><label for="<?php echo "$id-r$i"; ?>">Interest rate</label><div class="aqm-mc__sfx"><input type="text" inputmode="decimal" autocomplete="off" id="<?php echo "$id-r$i"; ?>" data-k="rate" data-fmt="pct"><span>%</span></div></div>
				<div><label for="<?php echo "$id-a$i"; ?>">Amortization</label><select id="<?php echo "$id-a$i"; ?>" data-k="amort"><?php foreach ( array( 10, 15, 20, 25, 30 ) as $y ) { echo '<option value="' . $y . '">' . $y . ' years</option>'; } ?></select></div>
			</div>
			<p class="aqm-mc__warn" role="status"></p>
		</div>
	<?php endforeach; ?>
	</div>
</div>

<div class="aqm-mc__card">
	<h3>Payments at a glance</h3>
	<div class="aqm-mc__kpis" data-k="kpis"></div>
	<h3>Side by side</h3>
	<div class="aqm-mc__tablewrap"><table data-k="compare"><thead><tr><th scope="col"></th><th scope="col">Scenario A</th><th scope="col">Scenario B</th><th scope="col">Scenario C</th></tr></thead><tbody data-part="top"></tbody><tbody data-part="at"></tbody><tbody data-part="mid"></tbody><tbody data-part="before"></tbody><tbody data-part="bottom"></tbody></table></div>
</div>

<div class="aqm-mc__card">
	<h3>Government rules and incentives that apply</h3>
	<ul class="aqm-mc__prog" data-k="programs"></ul>
</div>

<div class="aqm-mc__card">
	<h3>Mortgage balance over time</h3>
	<div class="aqm-mc__legend" data-k="legend"></div>
	<div class="aqm-mc__chart"><svg data-k="chart" viewBox="0 0 900 340" role="img" aria-label="Remaining mortgage balance by year for each scenario"></svg><div class="aqm-mc__tip" data-k="tip"></div></div>
</div>

<div class="aqm-mc__card">
	<h3>Amortization schedule</h3>
	<div class="aqm-mc__tabs" data-k="tabs">
		<button type="button" data-scen="0" aria-pressed="true">Scenario A</button><button type="button" data-scen="1" aria-pressed="false">Scenario B</button><button type="button" data-scen="2" aria-pressed="false">Scenario C</button>
		<span style="flex:1"></span>
		<button type="button" data-view="year" aria-pressed="true">By year</button><button type="button" data-view="pay" aria-pressed="false">Every payment</button>
		<button type="button" data-csv="1">Download CSV</button>
	</div>
	<div class="aqm-mc__sched"><table data-k="sched"><thead></thead><tbody></tbody></table></div>
</div>

<div class="aqm-mc__disclaimer" role="note">
	<strong>Disclaimer</strong>
	<p>This calculator is a planning aid only. Its results are estimates based on the figures you enter and on rules, rates, rebates and taxes as published in September 2026, which can change without notice. It is not financial, mortgage, legal, tax or insurance advice, and it is not a mortgage approval, rate offer or quote.</p>
	<p>Your actual payments, insurance premium, taxes, rebates and closing costs depend on your lender, your mortgage insurer, your own circumstances and the final terms of your purchase. Canadian fixed-rate mortgages compound semi-annually and lenders may round differently. Land transfer tax is shown for a property with one or two single-family homes; the new-home HST relief applies to agreements signed from 1 April 2026 to 31 March 2027 for a home you will live in. Other costs use typical amounts that you can change.</p>
	<p><strong>Before you make any decision, verify every figure with qualified professionals</strong>: a licensed mortgage agent or your lender, a real estate lawyer, and an accountant or tax advisor. A. Q. Mufti and RE/MAX Real Estate Centre Inc., Brokerage accept no liability for decisions made using this calculator.</p>
</div>
<p class="aqm-mc__copy">&copy; <?php echo 2026 < (int) gmdate( 'Y' ) ? '2026&ndash;' . (int) gmdate( 'Y' ) : '2026'; ?> A. Q. Mufti. All rights reserved. AQM Mortgage Calculator.</p>
</div>
	<?php
	return ob_get_clean();
} );
