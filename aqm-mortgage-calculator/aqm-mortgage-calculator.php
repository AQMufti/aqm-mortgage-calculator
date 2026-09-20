<?php
/**
 * Plugin Name: AQM Mortgage Calculator
 * Description: Canadian mortgage calculator covering every province and territory: three live side-by-side scenarios (default 10%, 15%, 20% down, all editable), semi-annual compounding, CMHC insurance and the provincial tax on it, minimum down payment and $1.5M insured-price rules, 30-year amortization eligibility, new-home GST/HST relief, land transfer tax (or the land titles fee that replaces it) with first-time buyer relief, a balance chart and a full amortization schedule with CSV download. Shortcodes: [aqm_mortgage_calculator] for the calculator, [aqm_mortgage_guide] for the public guide. No external scripts.
 * Version:     1.11.0
 * Author:      A. Q. Mufti
 * Plugin URI:  https://github.com/AQMufti/aqm-mortgage-calculator
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 *
 * Copyright (c) 2026 A. Q. Mufti. All rights reserved.
 *
 * 1.11.0 (20 Sep 2026): every scenario can hold its own property. AQ: "bring the property tabs down
 * to each scenario so it'll give flexibility to either have one property with 3 rate, DP scenarios or
 * 3 different properties with same or different rates etc."
 *   - The shared "The property" card is gone. Price, province, municipality, payment frequency,
 *     mortgage term, first-time buyer and newly-built now sit INSIDE each scenario, so the three
 *     columns can be three different houses - or the same house bought in two provinces, or monthly
 *     against accelerated bi-weekly, neither of which this calculator could show before.
 *   - B AND C FOLLOW A UNTIL TOLD OTHERWISE, which is what keeps the ordinary case free: type the
 *     price once and compare down payments exactly as always. A following scenario hides its own
 *     property block entirely and reads A's, so the two can never quietly disagree - and three full
 *     property blocks stacked would be unusable on a phone. "Use a different property" copies A's
 *     values across and hands that scenario the wheel; "Same as Scenario A" gives it back.
 *   - The results follow the property, not the page. compute() now builds one context per scenario,
 *     so each column carries its own land transfer tax, municipal tax, first-time-buyer and new-build
 *     relief, sales tax on a new build, premium tax and registration fee. Where the three properties
 *     agree, every figure and label is what it always was; where they differ, a label that can only
 *     name one thing falls back to a general name rather than lying about two of the columns.
 *   - The government programs belong to a property too, so with different properties in play they
 *     follow the scenario selected for the schedule and say which one they are describing.
 *   - Caught by its own test before it shipped: the jurisdiction names in the rules file already
 *     carry HTML entities, so escaping them a second time printed "Montr&eacute;al" and
 *     "&ldquo;welcome tax&rdquo;" on screen. Escape what comes from lenders and the settings box;
 *     never re-escape our own table.
 * 1.10.1 (19 Sep 2026): the owner could not reach his own admin on the one device it was built for.
 *   - 1.10.0 showed the Admin button only once a device had paired, so a visitor would not see it.
 *     That left no way IN on a device that had never paired. In a browser it is merely awkward -
 *     type #admin. On an iPhone it is fatal: an INSTALLED home-screen app runs with no address bar
 *     at all, and iOS gives it storage SEPARATE from Safari, so pairing in Safari does not pair the
 *     installed app either. AQ found it immediately: "Where is the Admin log in on the App?"
 *   - A quiet "Owner sign-in" link now sits at the foot of the app, always, on any device that has
 *     not paired. Hiding it was never what protected anything - the device token is - so there is
 *     nothing to lose by showing it: the screen it opens is useless without a code from Settings.
 *     It disappears the moment this device pairs, because the Admin button in the bar takes over.
 *   - Pairing and signing out now redraw both at once rather than waiting for a reload, so the
 *     link goes and the button arrives the instant the exchange succeeds.
 * 1.10.0 (19 Sep 2026): the app has its own admin, and its own owner login. AQ: "If its a
 * standalone App, it should have it's own Admin interface, with an Admin/owner login, not requiring
 * to use any website."
 *   - The rates screen now lives in the app: type your own rates, tick the ones read from lender
 *     pages, see which sources have stopped updating, read every source now, turn the drop-down on
 *     or off. That is everything wp-admin was being opened for.
 *   - What has NOT changed, deliberately: the rates still live on the server. One copy is the rule
 *     the whole design rests on - it is what stops an installed app and the website page ever
 *     disagreeing about a figure. The app is a client of that data, and should be. What changes is
 *     that nobody has to visit the website to manage it.
 *   - The login is NOT a WordPress login. An Application Password for user 1 is total control of
 *     aqmuftirealty.com; putting one on a phone to manage a list of interest rates makes a lost
 *     phone a lost website. So the plugin issues its own DEVICE TOKEN, which unlocks six rate
 *     endpoints and nothing else in WordPress - it is not a user, holds no capabilities, and cannot
 *     read a post or touch another plugin. Only its hash is stored, so a database dump yields no
 *     working credential.
 *   - Pairing, once per device: Settings shows a 6-digit code, good for ten minutes and one use;
 *     the app exchanges it for the token. The token itself is never typed, never displayed and
 *     never put in a URL - a URL would land it in the access log and in browser history. The CODE
 *     is held to the same standard: it reaches the settings screen in a transient, not in the
 *     redirect URL. Five wrong guesses burn it from any address, and the caller is throttled for
 *     fifteen minutes. Settings lists every paired device with when it was last used and from
 *     where, and revokes any of them in one click; the app can sign itself out too.
 *   - DESKTOP pairs itself. wp-admin and the app are the same origin, so on the computer that
 *     generated the code the browser hands it over directly and the app pairs with one click -
 *     reading six digits off one tab to type them into another tab of the same browser is a
 *     pointless ceremony. Nothing crosses the network and nothing goes in a URL; the hand-off
 *     expires with the code and is deleted the instant it is read, so it cannot be replayed. A
 *     stale or expired one falls through to the six digits rather than to a dead end. The phone is
 *     a different device, never sees it, and types the digits exactly as before.
 *   - Devices name themselves from the user agent ("Windows Chrome", "iPhone Safari"), so the list
 *     in Settings says which machine is which instead of four rows reading "A device".
 *   - The admin endpoints are network-or-nothing in the service worker. They carry no ?v=, so the
 *     cache-first branch would have answered /admin/state from cache for good - tick a rate, watch
 *     the screen redraw with the figures it had an hour ago - and caching an authenticated response
 *     would leave it on the device after the token was revoked.
 *   - Found by its own tests, not in the field: a token revoked from the website while the screen
 *     was open left every button showing the server's bare refusal on a screen where nothing worked.
 *     Every failed call now goes through one handler that returns to the pairing screen.
 *   - The screen is fetched only at #admin or on a device that has already paired, so a visitor
 *     never downloads it, and there is nothing in the public shell worth finding: it is useless
 *     without a token, which only the pairing exchange can mint.
 * 1.9.6 (19 Sep 2026): an old rate keeps its figure and gains its date, and Nunavut stops saying nil.
 *   - A rate that has not been re-read for a fortnight used to be published with an "(out of date)"
 *     label, and a draft of this release went further and dropped it altogether. AQ overruled that,
 *     correctly: lenders hold a posted rate for weeks at a time, so an old reading is usually the
 *     same rate still standing. Dropping it threw away a figure that was almost certainly true and
 *     left the calculator emptier than the market. The figure stays; what changes is the caption.
 *     Where "(out of date)" sat, the drop-down and the echo line under it now carry the day the rate
 *     was read - "5.29% - RBC - 5-year fixed (read 2 Sep 2026)" - which is a fact rather than a
 *     verdict, and lets anyone judge its age themselves. The Bank of Canada benchmark box reads the
 *     same way.
 *   - Publishing an old figure only works if someone knows the source has stopped answering, so the
 *     settings screen gains "Sources that need attention": what each one last said, when it was last
 *     read, whether it is still on the calculator, why the fetch is failing, and the exact line to
 *     paste into "Your own rates" to take it over by hand. That is the route back in for BMO,
 *     Manulife, Vancity and anyone else this server cannot reach, and it is the warning that would
 *     have flagged Tangerine before its three rates all drifted behind. An "On the calculator?"
 *     column keeps a dated-but-showing rate apart from one that is genuinely absent, so the screen
 *     can never contradict what a visitor sees. The 14-day mark now governs this screen alone - it
 *     no longer decides what the public gets.
 *   - Nunavut: the Land Titles Tariff of Fees Regulations set a $100 minimum for the fee under
 *     s.156(2)(a) and $80 under s.156(3), each plus the 10% assurance fund levy (R-058-2003) - so
 *     $110 and $88, and that is what is shown, labelled as a minimum. The Act computes the real fee
 *     from the value of the land (its next section is headed "Valuation of land") and that scale is
 *     not retrievable: the consolidated Act truncates before Part V and the regulations site refuses
 *     automated reading. An honest floor with a note beats a guessed scale, and beats nil.
 * 1.9.5 (18 Sep 2026): the app says when a newer version is ready. From 1.9.4 a reload always lands
 * on the current version, but nothing told anyone to reload: a service worker update installs quietly
 * while the page in front of you goes on being served by the old one, so you can sit a version behind
 * without a hint that you are. A charcoal bar now appears when an update has installed, with a Reload
 * button. It does NOT reload by itself - taking the page out from under someone mid-calculation to
 * save them a click is not a trade worth making. A first-ever install is excluded, since calling that
 * "a newer version" would be false; the controller is captured before registering to tell the two
 * apart. Detection dispatches a plain event that the bar listens for, so the bar can be tested
 * without a real service worker, which no headless page can install.
 * 1.9.4 (18 Sep 2026): the app could serve an old version of itself, indefinitely.
 *   - Everything the service worker cached carried ?v=<version>.<mtime> in its URL, so a cache hit
 *     was current by construction - EXCEPT the shell. /mortgage-app/ is the same address in every
 *     version, and it was served cache-first, so a browser that had ever loaded it could go on
 *     showing that shell for good, along with the old ?v= links to old CSS and JS inside it. A
 *     desktop screenshot showed the 1.9.0 shell after 1.9.3 had shipped: no install bar, and the
 *     six-column layout only the pre-1.9.3 stylesheet produces. The shell is now network-first with
 *     a cache fallback, exactly like the rates; offline is unchanged.
 *   - The install fallback named only Chrome, Edge and Safari-on-Mac, which was no use in a third
 *     browser. Opera is Chromium underneath but does not fire the install event, so that branch ran
 *     and then talked about browsers the reader was not using. It now points at the browser's own
 *     menu, and says plainly that not every desktop browser can install a web app.
 * 1.9.3 (18 Sep 2026): drop-downs stop cutting their own text off. Measured across phone widths in
 * the real app shell rather than reasoned about, which found more than the rate picker:
 *   - The shared grid's 170px floor let it go two-up at about 430px, handing each control ~181px.
 *     "Newfoundland and Labrador" needs ~240px and "Accelerated bi-weekly" ~195px, so a second column
 *     arrived exactly where the options stopped fitting - visible on a real iPhone screenshot. The
 *     floor is now 240px, the widest option any of these selects carries, so two columns wait until
 *     one can hold its content.
 *   - "Builder's all-in price (HST included)" needs ~330px and fits no phone column at all, so that
 *     box now spans the full row wherever it appears.
 *   - The rate picker cannot be fixed this way: its longest option needs ~410px against a phone's
 *     ~300px, and option text can neither wrap nor be made responsive. So the full choice - rate,
 *     lender, product and the date it was read - is echoed on the line underneath, which wraps. That
 *     line was a fixed hint before; it now says what you actually picked.
 *   - While in there: lender names, labels and source URLs come from scraped pages, and were being
 *     written into innerHTML unescaped. They are escaped now.
 * 1.9.2 (18 Sep 2026): the iPhone instructions named a button that is not there. 1.9.1 said "tap the
 * Share button at the bottom of Safari"; a screenshot from a current iPhone showed a toolbar with no
 * Share button on it at all - that Safari puts a page menu beside the address instead, and Add to
 * Home Screen lives inside that. Safari also lets the address bar sit at the top or the bottom by a
 * setting, so naming a position was wrong twice over. The text now names both routes, shows both
 * glyphs, asserts no position, and says plainly that there is no App Store download and that Apple
 * gives a website no way to install itself - which is the honest answer to "where is the link".
 * 1.9.1 (18 Sep 2026): the app offers to install itself. Everything a browser needs was already
 * there - HTTPS, a valid manifest, both icons, a service worker with a fetch handler, all verified
 * active on the live site - but nothing on the page ever said so, and no browser announces it any
 * more. Chrome dropped the install banner years ago and hides the option behind a small address-bar
 * icon or a submenu; iOS has never shown a prompt and never will, because Add to Home Screen inside
 * the Share sheet is the only route Apple provides. So the app now says it itself: it catches
 * beforeinstallprompt and shows a real Install button where that exists, spells out the Share ->
 * Add to Home Screen steps on iPhone and iPad, falls back to naming the menu item everywhere else,
 * and shows nothing at all once the app is installed. Also adds the standard mobile-web-app-capable
 * meta alongside Apple's own.
 * 1.9.0 (18 Sep 2026): the calculator explains itself. Until now it shipped a disclaimer and no help
 * at all - nothing saying what "accelerated bi-weekly" changes, why Toronto is listed apart from
 * Ontario, or that every closing-cost row can be typed over.
 *   - A "How to use this calculator" panel now sits at the top of the calculator, on the website page
 *     AND in the app. It is a plain <details> element: no JavaScript, so it cannot fail offline, it
 *     gets correct keyboard and screen-reader behaviour from the browser, and closed it costs one
 *     line of screen.
 *   - [aqm_mortgage_guide] renders the fuller public guide, at /mortgage-calculator-guide/.
 *   - Both read ONE array of sections in aqm-mc-help.php, so the panel and the page cannot drift
 *     apart. The reference sections - minimum down payment, land transfer tax across Canada,
 *     first-time buyer relief province by province, new-home HST - are page-only: that keeps the
 *     panel short enough to read on a phone and keeps two near-identical URLs out of Google's way.
 * 1.8.1 (17 Sep 2026): the app's own addresses stop being redirected. WordPress adds a trailing
 * slash to anything it does not take for a file, so /mortgage-app/sw.js was 301'd to
 * /mortgage-app/sw.js/ - which still served the right bytes, and so looked fine. It was not: a
 * service worker may only control the folder it is served FROM, so one answering at .../sw.js/
 * controls nothing, and the app would have installed and then never worked offline. Fixed by
 * refusing the canonical redirect for these routes and answering before it runs.
 * 1.8.0 (17 Sep 2026): the calculator installs as an app. /mortgage-app/ serves the same calculator
 * full screen, installable from the browser on a phone or a desktop, and it keeps working with no
 * signal. No app store, no developer account, no second codebase - the same core, the same screen
 * and the same markup as the page, because aqm_mc_build() now renders both.
 *   - Why a PWA before a store app: this project measured about one material Canadian tax change a
 *     month, and store review takes days. An app carrying its tax tables in the binary is stale
 *     before it is approved. So the rules stay a FILE the app fetches (assets/aqm-mc-core.js) and the
 *     rates stay an ENDPOINT it fetches (/wp-json/aqm-mc/v1/rates). Both update the moment they ship.
 *   - The service worker serves the shell, rules and styles from cache, because their URLs carry the
 *     version. Rates are network-first and only fall back to cache offline, since a stale rate shown
 *     as current is the single failure the whole rate system exists to prevent. Offline says so, and
 *     every rate still carries the date it was read.
 * 1.7.0 (17 Sep 2026): two changes, both for the same reason - this is meant to become a desktop
 * and mobile app, and Canadian tax law must never be written out twice.
 *   - The rules move into assets/aqm-mc-core.js, which knows nothing about WordPress or the
 *     browser: no DOM, no jQuery, no dependencies. It loads as a browser global or as a module, so
 *     an app can use the same file unchanged. aqm-mc.js is now only the screen. One copy of the
 *     jurisdiction table, in one file, for every place it is ever shown.
 *   - Quebec and Nova Scotia get the municipality picker they need, because both set their transfer
 *     tax municipally: Montreal (to 4%), Quebec City (to 3%) and the province-wide base for
 *     everywhere else; and for Nova Scotia the 1.0% and 1.5% municipalities by name.
 * 1.6.0 (17 Sep 2026): the calculator is Canadian, not Ontario-only. "Where you are buying" now lists
 * every province and territory plus the City of Toronto, and the closing costs follow it. The mortgage
 * itself was already national and is unchanged. What the province decides now comes from one table:
 *   - Land transfer tax in four shapes: brackets (BC, MB, ON, QC), a flat 1% (NB, PE), a municipal
 *     rate (NS, and QC's duties), and - in AB, SK, NL, YT, NT and NU - no transfer tax at all but a
 *     land titles fee, each with its own formula. Showing nil in those six would have been wrong.
 *   - First-time buyer relief in five shapes: a refund (ON, Toronto), an exemption that phases out
 *     (BC), a full exemption at any price (PE), an income tax credit rather than closing cash (SK, QC),
 *     and nothing at all (AB, MB, NB, NT). BC's newly built exemption is open to every buyer and is
 *     mutually exclusive with its first-time buyer one, so the calculator takes whichever is worth more.
 *   - Tax on the CMHC premium in ON (8%), QC (9%) and SK (6%) only. Manitoba exempted it in 2020.
 *   - New-home sales tax at each province's rate, with the federal New Housing Rebate and the federal
 *     First-Time Home Buyers' GST rebate everywhere, plus ON, QC and NS's own rebates.
 * Quebec and Nova Scotia set their tax municipally: the base rate is shown with a note naming the
 * limits, pending a municipality picker. Nunavut's land titles fee could not be read from a Government
 * of Nunavut page and is shown as nil, said plainly on the page.
 * 1.5.8 (17 Sep 2026): two federal figures corrected. The Home Buyers' Amount is a credit at the
 * lowest federal rate, and that rate was cut to 14% for 2026, so the $10,000 claim is worth $1,400,
 * not the $1,500 shown (15% was right through 2024). And an RRSP Home Buyers' Plan withdrawal made
 * between 2026 and 2028 does not start being repaid until the fifth year after the withdrawal year.
 * 1.5.7 (17 Sep 2026): the three Bank of Canada figures come out of the lender drop-down, where they
 * read as offers, and into a "Bank of Canada benchmark rates" box of their own, prime rate first.
 * 1.5.6 (17 Sep 2026): the rate drop-down sits directly above the Interest rate box instead of above
 * Down payment, so the two rate controls read as one.
 * 1.5.5 (17 Sep 2026): readers written for DUCA's and nesto's own pages, so their figures are right -
 * DUCA is read from its Low Rate Mortgage table alone, nesto from the per-product data its page
 * publishes for search engines. The first drop-down choice now reads "Type my own rate in the box
 * below".
 * 1.5.4 (17 Sep 2026): the drop-down reads rate first - "4.64% - Tangerine - 3-year fixed" - and is
 * sorted lowest rate first.
 * 1.5.3 (17 Sep 2026): clearer wording on the rate controls - the drop-down says "Type my own rate",
 * is labelled "Interest rate: pick a lender's rate..." and carries a line saying a rate can simply be
 * typed in the box below instead.
 * 1.5.2 (17 Sep 2026): tick-all buttons on the rates settings page - all, none, or all except the ones
 * flagged as looking wrong - plus a tick-all box in the table heading.
 * 1.5.1 (17 Sep 2026): requests now look like an ordinary visit (browser user agent, Accept and
 * Referer headers, 45-second limit), which is what TD, Manulife Bank, Vancity and BMO were refusing,
 * and National Bank's figures are read from the escaped data its page carries.
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
 *  - First-time buyer programs shown for information: Home Buyers' Amount ($10,000 claimed, worth
 *    $1,400 at the 2026 lowest federal rate of 14%), FHSA ($8,000 a year, $40,000 lifetime), RRSP
 *    Home Buyers' Plan ($60,000 per person; a withdrawal made 2026-2028 starts repaying in year five).
 *  - Every other province's figures, and their sources, are in the jurisdiction table at the top of
 *    assets/aqm-mc.js. All read from the government's own pages on 17 Sep 2026.
 */
defined( 'ABSPATH' ) || exit;

define( 'AQM_MC_VERSION', '1.11.0' );
define( 'AQM_MC_FILE', __FILE__ );
require_once __DIR__ . '/aqm-rates.php';
AQM_MC_Rates::boot();

if ( file_exists( __DIR__ . '/aqm-updater.php' ) ) {
	require_once __DIR__ . '/aqm-updater.php';
	new AQM_Updater( __FILE__, AQM_MC_VERSION, 'AQMufti/aqm-mortgage-calculator', 'AQM Mortgage Calculator', 'Canadian mortgage calculator for every province and territory.' );
}

add_action( 'wp_enqueue_scripts', function () {
	$v = function ( $f ) { $p = __DIR__ . '/assets/' . $f; return AQM_MC_VERSION . '.' . ( file_exists( $p ) ? filemtime( $p ) : 0 ); };
	wp_register_style( 'aqm-mc', plugins_url( 'assets/aqm-mc.css', __FILE__ ), array(), $v( 'aqm-mc.css' ) );
	wp_register_script( 'aqm-mc-core', plugins_url( 'assets/aqm-mc-core.js', __FILE__ ), array(), $v( 'aqm-mc-core.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_register_script( 'aqm-mc', plugins_url( 'assets/aqm-mc.js', __FILE__ ), array( 'aqm-mc-core' ), $v( 'aqm-mc.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	global $post;
	if ( ! $post instanceof WP_Post ) { return; }
	$content = (string) $post->post_content;
	if ( has_shortcode( $content, 'aqm_mortgage_calculator' ) ) {
		wp_enqueue_style( 'aqm-mc' );
		wp_enqueue_script( 'aqm-mc' );
	} elseif ( has_shortcode( $content, 'aqm_mortgage_guide' ) ) {
		/* The guide is text and needs no script - but it must have the stylesheet in the HEAD.
		   Leaving the shortcode's own late enqueue to do it would print the CSS in the footer and
		   flash the page unstyled first. */
		wp_enqueue_style( 'aqm-mc' );
	}
} );

/**
 * Build the calculator, markup and all.
 *
 * Named rather than inline because TWO things need it now: the WordPress shortcode, and the
 * standalone app at /mortgage-app/ (aqm-mc-app.php). One copy of the markup, for the same reason
 * there is one copy of the rules - a second copy is a second thing to forget to change.
 *
 * $enqueue is false for the app, which loads its own assets rather than going through wp_head.
 */
function aqm_mc_build( $atts = array(), $enqueue = true ) {
	$a = shortcode_atts( array( 'price' => '850000', 'rate' => '4.19', 'amortization' => '25', 'down1' => '10', 'down2' => '15', 'down3' => '20', 'location' => 'on' ), $atts );
	if ( $enqueue ) {
		wp_enqueue_style( 'aqm-mc' );
		wp_enqueue_script( 'aqm-mc' );
	}
	static $n = 0; $n++;
	$id  = 'aqm-mc-' . $n;
	$rates = class_exists( 'AQM_MC_Rates' ) ? AQM_MC_Rates::listing() : array();
	// The Bank of Canada figures are benchmarks, not rates anyone can borrow at, so they are shown in
	// their own box rather than in the lender drop-down, where they read as offers. Prime comes first:
	// it is the rate every variable mortgage on the list is priced against.
	$boc  = array();
	$pick = array();
	foreach ( $rates as $r ) {
		if ( isset( $r['src'] ) && 'boc' === $r['src'] ) { $boc[] = $r; } else { $pick[] = $r; }
	}
	usort( $boc, function ( $x, $y ) {
		$a = ( false !== stripos( $x['label'], 'prime' ) ) ? 0 : 1;
		$b = ( false !== stripos( $y['label'], 'prime' ) ) ? 0 : 1;
		return $a === $b ? ( (float) $x['rate'] <=> (float) $y['rate'] ) : $a - $b;
	} );
	$cfg = array( 'price' => (float) $a['price'], 'rate' => (float) $a['rate'], 'amort' => (int) $a['amortization'], 'downs' => array( (float) $a['down1'], (float) $a['down2'], (float) $a['down3'] ), 'loc' => sanitize_key( $a['location'] ), 'rates' => array_values( $pick ) );
	ob_start();
	?>
<div class="aqm-mc" id="<?php echo esc_attr( $id ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
<noscript><p>This calculator needs JavaScript switched on.</p></noscript>
<?php echo aqm_mc_help_panel(); ?>


<?php if ( $boc ) : ?>
<div class="aqm-mc__card aqm-mc__boc">
	<h3>Bank of Canada benchmark rates</h3>
	<ul>
	<?php foreach ( $boc as $b ) : ?>
		<li>
			<b><?php echo esc_html( number_format( (float) $b['rate'], 2 ) ); ?>%</b>
			<span><?php echo esc_html( preg_replace( '/\s*\((?:all chartered banks|chartered bank average)\)/i', '', $b['label'] ) ); ?></span>
			<i>read <?php echo esc_html( $b['date'] ); ?></i>
		</li>
	<?php endforeach; ?>
	</ul>
	<p>Benchmarks, not offers. Prime is the rate the variable mortgages below are priced against; the posted averages are what the chartered banks advertise before discounting, so almost nobody pays them. Source: <a href="https://www.bankofcanada.ca/rates/interest-rates/" target="_blank" rel="noopener nofollow">Bank of Canada</a>.</p>
</div>
<?php endif; ?>

<div class="aqm-mc__card">
	<h3>Compare three scenarios</h3>
	<div class="aqm-mc__scen">
	<?php foreach ( array( 'A', 'B', 'C' ) as $i => $L ) : ?>
		<div class="aqm-mc__sc" style="--c:var(--s<?php echo $i; ?>)" data-s="<?php echo $i; ?>">
			<h4><i aria-hidden="true"></i>Scenario <?php echo $L; ?></h4>
<?php if ( $i > 0 ) : ?>
			<div class="aqm-mc__linkbar" data-k="linkbar">
				<span data-k="linksum"></span>
				<button type="button" data-k="linktoggle"></button>
			</div>
<?php endif; ?>
			<div class="aqm-mc__prop" data-k="prop">
				<div><label for="<?php echo "$id-price$i"; ?>">Purchase price</label><div class="aqm-mc__pfx"><span>$</span><input type="text" inputmode="numeric" autocomplete="off" id="<?php echo "$id-price$i"; ?>" data-k="price" data-fmt="money"></div></div>
				<div><label for="<?php echo "$id-loc$i"; ?>">Where you are buying</label><select id="<?php echo "$id-loc$i"; ?>" data-k="loc"><option value="on">Ontario (outside Toronto)</option></select></div>
				<div><label for="<?php echo "$id-freq$i"; ?>">Payment frequency</label><select id="<?php echo "$id-freq$i"; ?>" data-k="freq"><option value="12">Monthly</option><option value="24">Semi-monthly</option><option value="26">Bi-weekly</option><option value="26a">Accelerated bi-weekly</option><option value="52">Weekly</option><option value="52a">Accelerated weekly</option></select></div>
				<div><label for="<?php echo "$id-term$i"; ?>">Mortgage term</label><select id="<?php echo "$id-term$i"; ?>" data-k="term"><?php foreach ( array( 1, 2, 3, 4, 5, 7, 10 ) as $t ) { echo '<option value="' . $t . '"' . ( 5 === $t ? ' selected' : '' ) . '>' . $t . ( 1 === $t ? ' year' : ' years' ) . '</option>'; } ?></select></div>
				<label class="aqm-mc__chk"><input type="checkbox" data-k="ftb"> First-time home buyer</label>
				<label class="aqm-mc__chk"><input type="checkbox" data-k="newbuild"> Newly built home (from a builder)</label>
				<div class="aqm-mc__hst" data-k="hstwrap"><label for="<?php echo "$id-hst$i"; ?>">The price above is</label><select id="<?php echo "$id-hst$i"; ?>" data-k="hstmode"><option value="incl">Builder's all-in price (HST included)</option><option value="plus">Before HST</option></select></div>
			</div>
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
}

add_shortcode( 'aqm_mortgage_calculator', function ( $atts ) { return aqm_mc_build( $atts, true ); } );

require_once __DIR__ . '/aqm-mc-app.php';
/* After the app file, which defines AQM_MC_APP_PATH - the help links to the app by that path. */
require_once __DIR__ . '/aqm-mc-help.php';
/* Also after the app file: the pairing panel links to the app's admin screen by that path. */
require_once __DIR__ . '/aqm-mc-admin.php';
AQM_MC_Admin::boot();
