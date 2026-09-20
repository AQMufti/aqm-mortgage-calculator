<?php
/**
 * AQM Mortgage Calculator - the help, written once and shown in two places.
 * Copyright (c) 2026 A. Q. Mufti. All rights reserved.
 *
 * WHY THIS FILE EXISTS AT ALL
 *
 * The calculator shipped with a disclaimer and nothing else: no word anywhere telling a buyer what
 * "accelerated bi-weekly" changes, why Toronto is listed separately from Ontario, or that every
 * closing-cost row is a box they can type over. Two places needed that writing - a Help panel
 * inside the calculator, and a public page Google can index - and two places is exactly how one
 * explanation becomes two explanations that disagree.
 *
 * So there is ONE array of sections, below, and two renderers that read it. The same sentence is
 * literally the same string in the panel and on the page. This is the same rule the rules file
 * follows: one copy of Ontario's brackets, one copy of the help.
 *
 * WHAT GOES WHERE, AND WHY IT IS NOT SIMPLY EVERYTHING IN BOTH
 *
 *   'panel' => true    operational: what a field does, how to read the table, how to install.
 *                      Shown inside the calculator AND on the public page.
 *   'panel' => false   reference: down payment rules, land transfer tax across Canada, the
 *                      first-time buyer programs. Long-form, and the reason the page is worth
 *                      indexing at all. Public page only.
 *
 * Putting every word in both would hand Google two near-identical URLs and make it choose. The
 * split keeps the panel short enough to actually read on a phone and leaves the page with the
 * substance that earns the search traffic.
 *
 * NOTE ON THE NUMBERS BELOW
 *
 * The reference sections restate headline figures that live properly in assets/aqm-mc-core.js.
 * That is a deliberate second copy of facts - prose cannot call a function - and it is the one
 * thing here that can drift. They are written at the level of shape and headline amount, never a
 * full bracket table, and every one of them ends up pointing at the calculator for the real
 * figure. When the core's rules change, re-read this file. AQM_MC_RULES_CHECKED is the date the
 * rules were last verified against each government's own page.
 *
 * NO JSON-LD IS EMITTED HERE, deliberately. AIOSEO already puts BlogPosting, FAQPage, WebPage and
 * BreadcrumbList on this site's pages; a second set of entities from us would compete with it.
 */
defined( 'ABSPATH' ) || exit;

const AQM_MC_RULES_CHECKED = '17 September 2026';
const AQM_MC_GUIDE_SLUG    = 'mortgage-calculator-guide';

function aqm_mc_calc_url()  { return home_url( '/mortgage-calculator/' ); }
function aqm_mc_guide_url() { return home_url( '/' . AQM_MC_GUIDE_SLUG . '/' ); }
function aqm_mc_app_url()   { return home_url( '/' . AQM_MC_APP_PATH . '/' ); }

/**
 * The one copy. Order here is the order on the public page; the panel takes the same order,
 * skipping the reference sections.
 */
function aqm_mc_help_sections() {
	$calc  = esc_url( aqm_mc_calc_url() );
	$app   = esc_url( aqm_mc_app_url() );
	$check = AQM_MC_RULES_CHECKED;

	return array(

		array(
			'id'    => 'what',
			'title' => 'What this calculator works out',
			'panel' => true,
			'body'  => '
<p>It answers the two questions a buyer actually asks, and it answers them for three different
down payments at once:</p>
<ul>
<li><strong>What will the payment be?</strong> &mdash; and what will you still owe when the term
ends and the rate is up for renewal.</li>
<li><strong>How much cash do I need to get to closing day?</strong> &mdash; down payment, land
transfer tax minus any rebate you qualify for, the lawyer, title insurance, adjustments, moving,
and the things paid earlier while the offer is still conditional.</li>
</ul>
<p>It covers <strong>every province and territory in Canada</strong>, with Ontario as the starting
point. It knows the federal mortgage rules and each province&rsquo;s own transfer tax and
first-time-buyer relief. Every figure was read from the government&rsquo;s own page on ' . $check . '.</p>
<p><strong>Nothing you type is sent anywhere or stored anywhere.</strong> The whole calculation runs
inside your own browser. There is no sign-in, no account and no form attached to it.</p>',
		),

		/* ---------------------------------------------------------- reference, page only */

		array(
			'id'    => 'downpayment',
			'title' => 'How much you have to put down',
			'panel' => false,
			'body'  => '
<p>The minimum down payment in Canada is federal, so it is the same in every province:</p>
<ul>
<li><strong>5%</strong> of the first $500,000.</li>
<li><strong>10%</strong> of the part between $500,000 and $1.5 million.</li>
<li><strong>20%</strong> of the whole price at $1.5 million and above &mdash; because mortgage
insurance is not available at all from that price, and without insurance no lender may go past 80%.</li>
</ul>
<p>So a $850,000 home needs $60,000 down: 5% of the first $500,000, then 10% of the remaining
$350,000. A $1,500,000 home needs $300,000.</p>
<p>Below 20% down the mortgage must be insured, and the premium is added to the mortgage rather
than paid in cash &mdash; which is why the mortgage in the results is larger than the price minus
your down payment. The premium is a percentage of the mortgage that steps up as the down payment
falls, and it is the single clearest argument the calculator makes: put the three scenarios side by
side and the cost of a smaller down payment stops being abstract.</p>
<p><strong>A 30-year amortization with less than 20% down</strong> is only open to first-time buyers
and to newly built homes, and it costs 0.20% more in premium. With 20% or more down, any buyer can
choose 30 years. Tick the boxes in the scenario and the calculator applies the rule rather than
leaving you to remember it.</p>',
		),

		array(
			'id'    => 'ltt',
			'title' => 'Land transfer tax is not the same tax twice',
			'panel' => false,
			'body'  => '
<p>Most closing-cost calculators quietly assume Ontario. Canada does not work that way, and the
provinces disagree about the <em>shape</em> of the tax, not just the rate:</p>
<ul>
<li><strong>Tiered on the price</strong> &mdash; Ontario, British Columbia, Manitoba and Quebec.
Each band of the price is taxed at its own rate.</li>
<li><strong>A flat percentage</strong> &mdash; New Brunswick and Prince Edward Island at 1%, Nova
Scotia between 1.0% and 1.5% depending on the municipality.</li>
<li><strong>A second, municipal tax on top</strong> &mdash; the City of Toronto charges its own land
transfer tax alongside Ontario&rsquo;s, which is why Toronto is a separate choice in the
drop-down rather than part of Ontario.</li>
<li><strong>No transfer tax at all</strong> &mdash; Alberta, Saskatchewan, Newfoundland and
Labrador, Yukon, the Northwest Territories and Nunavut charge a land titles or registry fee
instead, which is measured in hundreds of dollars rather than tens of thousands.</li>
</ul>
<p><strong>Quebec and Nova Scotia set the tax municipally</strong>, so those two ask a second
question. In Quebec, Montr&eacute;al and Qu&eacute;bec City levy their own higher scales &mdash;
Montr&eacute;al is the one municipality the province does not cap, and it runs to 4%. In Nova
Scotia the deed transfer tax runs from 1.0% to 1.5%, and the named lower-rate municipalities are
listed so you are not left guessing.</p>
<p>The difference is large enough to change what you can afford. The same $850,000 purchase attracts
five figures of tax in Toronto and a few hundred dollars in Alberta. Put your price into the
<a href="' . $calc . '">calculator</a> and change the province to see it.</p>',
		),

		array(
			'id'    => 'ftb',
			'title' => 'What first-time buyers actually get',
			'panel' => false,
			'body'  => '
<p>&ldquo;First-time buyer help&rdquo; is five different things wearing one name, and which you get
depends on where you buy:</p>
<ul>
<li><strong>A capped refund of the transfer tax</strong> &mdash; Ontario refunds up to $4,000, and
the City of Toronto up to $4,475 more on its own tax.</li>
<li><strong>A full exemption up to a price, then a taper</strong> &mdash; British Columbia exempts
the whole tax to $500,000, and phases the relief out entirely by $860,000.</li>
<li><strong>A full exemption at any price</strong> &mdash; Prince Edward Island simply does not
charge it.</li>
<li><strong>A tax credit instead of money at closing</strong> &mdash; Quebec refunds the welcome tax
through a refundable credit of up to $5,875, phasing out from $750,000. Saskatchewan gives a
non-refundable credit worth up to $1,575.</li>
<li><strong>Nothing</strong> &mdash; Manitoba has no first-time buyer rebate on its land transfer
tax at all.</li>
</ul>
<p>Two federal programs apply wherever you buy: the <strong>Home Buyers&rsquo; Amount</strong>, a
$10,000 claim on your tax return worth roughly $1,400 off federal tax, and the
<strong>FHSA and the RRSP Home Buyers&rsquo; Plan</strong> &mdash; $40,000 of tax-free saving and up
to $60,000 each withdrawn from an RRSP.</p>
<p>British Columbia also has a <strong>newly built home exemption</strong> open to any buyer, not
just first-timers, and you take that or the first-time buyer exemption &mdash; whichever is worth
more. The calculator picks the better of the two for you and names which one it used.</p>
<p>Tick <em>First-time home buyer</em> and every one of these appears in the
&ldquo;Government rules and incentives that apply&rdquo; list, with the amount worked out for your
price rather than described in the abstract.</p>',
		),

		array(
			'id'    => 'newhomehst',
			'title' => 'Buying new: the HST or GST nobody budgets for',
			'panel' => false,
			'body'  => '
<p>A resale home carries no sales tax. A newly built one does &mdash; 13% HST in Ontario, 15% in
New Brunswick, P.E.I. and Newfoundland and Labrador, 14% in Nova Scotia, 5% GST plus 9.975% QST in
Quebec, and 5% GST in the western provinces and the territories.</p>
<p>Several rebates come back against it, and they stack:</p>
<ul>
<li>The federal <strong>New Housing Rebate</strong> returns 36% of the 5% GST, to a maximum of
$6,300, phasing out entirely by $450,000.</li>
<li>The federal <strong>First-Time Home Buyers&rsquo; GST rebate</strong> adds up to $50,000 more,
in full to $1 million and nothing at $1.5 million.</li>
<li><strong>Ontario</strong> removes the whole 8% provincial part up to $1.5 million, to a maximum
of $80,000, reducing to $24,000 at $1.85 million, for agreements signed between 1 April 2026 and
31 March 2027.</li>
<li><strong>Quebec</strong> rebates half the QST to a maximum of $9,975, and <strong>Nova
Scotia</strong> gives first-time buyers 18.75% of the provincial part back, to $3,000.</li>
</ul>
<p>The practical trap is which price you were quoted. Builders normally advertise an
<strong>all-in price with the tax and the rebate already inside it</strong>, so entering that price
as if it were pre-tax overstates what you owe by tens of thousands. Tick <em>Newly built home</em>
and the calculator asks you which one you have, then shows the price before tax, the tax, each
relief, and what is actually left to pay.</p>',
		),

		array(
			'id'    => 'cash',
			'title' => 'What closing day actually costs',
			'panel' => false,
			'body'  => '
<p>The down payment is the number everyone plans for. It is rarely the number that catches people
out. Alongside it, on or before closing, sit:</p>
<ul>
<li><strong>Land transfer tax</strong>, less any rebate &mdash; usually the largest single item
after the down payment, and in Toronto it is charged twice.</li>
<li><strong>The lawyer&rsquo;s fee, disbursements and registration</strong>, and
<strong>title insurance</strong>.</li>
<li><strong>Adjustments</strong> &mdash; property tax and utilities the seller has already paid past
the closing date, repaid to them.</li>
<li><strong>Home insurance</strong>, which the lender requires in place on closing day, and
<strong>moving</strong>.</li>
<li><strong>Provincial sales tax on the mortgage insurance premium</strong> where it applies &mdash;
Ontario 8%, Quebec 9%, Saskatchewan 6%. This one surprises people, because the premium itself goes
into the mortgage but the tax on it is payable in cash.</li>
<li>Paid <strong>before</strong> closing, usually while the offer is still conditional: the
<strong>home inspection</strong>, the <strong>appraisal</strong>, and a
<strong>condo status certificate</strong>.</li>
</ul>
<p>The calculator fills in typical amounts for all of these and then lets you type over every one of
them, separately for each scenario, as your real quotes come in. The bottom line it works towards is
&ldquo;total cash needed to buy&rdquo; &mdash; the figure worth checking against your account before
an offer, not after.</p>',
		),

		/* ------------------------------------------------- operational, panel and page */

		array(
			'id'    => 'property',
			'title' => 'Filling in the property',
			'panel' => true,
			'body'  => '
<table>
<tbody>
<tr><td><strong>Purchase price</strong></td><td>The price you expect to pay. Buying new from a
builder? See the next section before you type it.</td></tr>
<tr><td><strong>Where you are buying</strong></td><td>Your province &mdash; or <strong>City of
Toronto</strong>, listed separately because Toronto charges a second land transfer tax on top of
Ontario&rsquo;s. Quebec and Nova Scotia add a municipality box underneath, because in those two the
tax is set by the municipality.</td></tr>
<tr><td><strong>Payment frequency</strong></td><td>Monthly through to weekly. The two
<strong>accelerated</strong> options are the ones that pay the mortgage off years early &mdash;
switch between accelerated bi-weekly and monthly and watch &ldquo;paid off in&rdquo;.</td></tr>
<tr><td><strong>Mortgage term</strong></td><td>How long the rate is locked in, usually 5 years. This
is <em>not</em> the amortization. It decides the &ldquo;balance at end of term&rdquo; figure.</td></tr>
<tr><td><strong>First-time home buyer</strong></td><td>Changes the rebates, the programs list and
whether a 30-year amortization is open to you.</td></tr>
<tr><td><strong>Newly built home</strong></td><td>Turns on the HST question and the new-home rebate
figures.</td></tr>
</tbody>
</table>',
		),

		array(
			'id'    => 'newbuild',
			'title' => 'If you ticked &ldquo;newly built home&rdquo;',
			'panel' => true,
			'body'  => '
<p>A box appears asking whether <strong>the price above is</strong>:</p>
<ul>
<li><strong>Builder&rsquo;s all-in price (HST included)</strong> &mdash; the usual case. Builders
normally quote a price with the tax and the rebate already inside it.</li>
<li><strong>Before HST</strong> &mdash; only if your builder quoted a pre-tax price.</li>
</ul>
<p>Choosing the wrong one moves the result by tens of thousands, so check the agreement of purchase
and sale rather than guessing. The results table then shows the price before tax, the tax, the
federal and provincial relief, and what you actually pay.</p>',
		),

		array(
			'id'    => 'boc',
			'title' => 'The Bank of Canada box',
			'panel' => true,
			'body'  => '
<p>Prime comes first, then the Bank of Canada&rsquo;s posted averages.</p>
<p>These are <strong>benchmarks, not offers.</strong> Prime is the rate every variable mortgage is
priced against. The posted averages are what the chartered banks advertise before discounting, so
almost nobody pays them. They sit in their own box, outside the rate drop-down, precisely so they
cannot be mistaken for a rate you could take. Each line carries the date it was read.</p>',
		),

		array(
			'id'    => 'scenarios',
			'title' => 'The three scenarios, and the rates',
			'panel' => true,
			'body'  => '
<p>Scenario A, B and C start as the same purchase with three different down payments &mdash; 10%,
15% and 20%. Change any of them, and change the rate or the amortization independently if you want
to compare a fixed against a variable.</p>
<p><strong>Each scenario can also hold a different property.</strong> B and C follow Scenario A to
begin with, so you type the price once; press <em>Use a different property</em> in either and it
takes its own price, province, municipality, payment frequency, term and first-time-buyer or
new-build status. That lets you put three houses side by side &mdash; or the same house bought in
two provinces, or monthly against accelerated bi-weekly payments. <em>Same as Scenario A</em> puts
it back. Each column then shows its own land transfer tax, relief and closing costs, and the
government programs below follow whichever scenario you have selected.</p>
<table>
<tbody>
<tr><td><strong>Down payment (%) / ($)</strong></td><td>Type into either; the other follows. Typing
dollars locks the dollar figure, so changing the price afterwards moves the percentage rather than
the amount.</td></tr>
<tr><td><strong>Pick a lender&rsquo;s rate&hellip;</strong></td><td>Published lender rates, lowest
first, each naming the lender, the product and the date it was read. Choosing one fills the rate box
below it.</td></tr>
<tr><td><strong>Interest rate</strong></td><td>Or type your own here. Choose <em>&ldquo;Type my own
rate in the box below&rdquo;</em> at the top of the drop-down to leave this box yours.</td></tr>
<tr><td><strong>Amortization</strong></td><td>10 to 30 years. The calculator warns you when a
combination is not permitted rather than quietly calculating something you could not get.</td></tr>
</tbody>
</table>
<p><strong>About those lender rates:</strong> they are the lenders&rsquo; own published rates, read
on the dates shown, plus rates entered by A. Q. Mufti. They are not offers and nobody is approved at
them. Your rate depends on the lender, the property and you. Confirm any rate with the lender or a
licensed mortgage agent.</p>',
		),

		array(
			'id'    => 'results',
			'title' => 'Reading the results',
			'panel' => true,
			'body'  => '
<p><strong>Payments at a glance</strong> gives the headline for each scenario: the payment, the
mortgage, the cash needed.</p>
<p><strong>Side by side</strong> is the full table, in groups:</p>
<ul>
<li><em>Property</em> &mdash; the price, and for a new build the whole tax working.</li>
<li><em>Mortgage</em> &mdash; down payment, the legal minimum, the insurance premium (or &ldquo;not
needed&rdquo; at 20% or more down), the mortgage, the payment, and how long it really takes to pay
off.</li>
<li><em>Over the term</em> &mdash; what you pay before renewal, and the
<strong>balance at end of term</strong>: the number most buyers have never been shown.</li>
<li><em>Over the full amortization</em> &mdash; total interest and total of all payments.</li>
<li><em>Cash needed at closing</em>, <em>paid before closing</em>, and
<strong>all cash you need</strong> at the bottom.</li>
</ul>
<p><strong>Government rules and incentives that apply</strong> lists what your entries qualify you
for, and what you would get if they changed. <strong>Mortgage balance over time</strong> plots the
three against each other &mdash; hover or tap any point. The
<strong>amortization schedule</strong> can be read by year or by every single payment, for any
scenario, and <strong>Download CSV</strong> hands you the whole thing as a spreadsheet.</p>',
		),

		array(
			'id'    => 'costs',
			'title' => 'Every cost row is yours to change',
			'panel' => true,
			'body'  => '
<p>Lawyer&rsquo;s fee, disbursements, title insurance, tax and utility adjustments, home insurance,
moving, inspection, appraisal, status certificate &mdash; each is a box you can type over,
<strong>separately for each scenario</strong>, as your real quotes arrive. Two
&ldquo;miscellaneous&rdquo; rows take anything else; type your own description into the label.</p>
<p>The amounts filled in are typical, not promises. Replace them the moment you have real numbers,
and the cash total at the bottom becomes genuinely yours.</p>',
		),

		array(
			'id'    => 'install',
			'title' => 'Installing it as an app',
			'panel' => true,
			'body'  => '
<p>The app version lives at <a href="' . $app . '">' . esc_html( str_replace( array( 'https://', 'http://' ), '', aqm_mc_app_url() ) ) . '</a>.
It installs straight from the browser &mdash; no app store, no account, nothing to pay.</p>
<ul>
<li><strong>iPhone or iPad (Safari):</strong> open the address, tap <em>Share</em>, scroll down, tap
<em>Add to Home Screen</em>, then <em>Add</em>.</li>
<li><strong>Android (Chrome):</strong> open the address, tap the <em>&#8942;</em> menu, tap
<em>Install app</em> (or <em>Add to Home screen</em>), then <em>Install</em>.</li>
<li><strong>Windows or Mac (Chrome or Edge):</strong> open the address and click the install icon at
the right-hand end of the address bar &mdash; or the <em>&#8942;</em> / <em>&hellip;</em> menu, then
<em>Install AQM Mortgage Calculator</em>. It then opens in its own window and appears in the Start
menu or Launchpad.</li>
</ul>
<p>Once installed it opens full screen with no browser bar, and the icon sits with your other apps.
There is nothing to update: the tax rules and the lender rates are fetched rather than built in, so
it picks up a change the moment it ships.</p>',
		),

		array(
			'id'    => 'offline',
			'title' => 'Using it with no signal',
			'panel' => true,
			'body'  => '
<p>The app keeps a copy of itself, so it opens and calculates with no connection at all &mdash; on a
plane, in a basement, at an open house with no bars.</p>
<p>Rates are treated differently on purpose. The app always tries the network first, because a stale
rate shown as a current one is the single failure worth designing against. When you are offline a
yellow strip appears at the top saying so, and every rate still carries the date it was read &mdash;
so an old figure tells you it is old rather than pretending.</p>',
		),

		array(
			'id'    => 'trouble',
			'title' => 'If something looks wrong',
			'panel' => true,
			'body'  => '
<table>
<tbody>
<tr><td><strong>A red note under a scenario</strong></td><td>That combination is not allowed, or
something is missing: below the minimum down payment for the price, a 30-year amortization with
under 20% down when you are not a first-time buyer or buying new, 20% required because the price is
$1,500,000 or more, or simply no interest rate entered yet.</td></tr>
<tr><td><strong>&ldquo;read&rdquo; and a date beside a rate</strong></td><td>That is the day the
lender&rsquo;s published page was last read. It appears once a reading is over two weeks old.
Lenders hold a posted rate for weeks at a time, so an older reading is usually still the rate on
offer &mdash; but every rate here is indicative, and this one is worth confirming.</td></tr>
<tr><td><strong>A yellow strip at the top of the app</strong></td><td>You are offline. Everything
still calculates; the rates are the last ones downloaded.</td></tr>
<tr><td><strong>It looks nothing like your lender&rsquo;s figure</strong></td><td>Check the payment
frequency and the amortization first &mdash; accelerated payments and 25 against 30 years are the
two settings that move the number most. Canadian fixed-rate mortgages also compound semi-annually
and lenders round differently, so small differences are normal.</td></tr>
<tr><td><strong>&ldquo;This calculator needs JavaScript switched on.&rdquo;</strong></td><td>Exactly
that &mdash; the calculation runs in your browser, so it cannot run without it.</td></tr>
</tbody>
</table>',
		),

		array(
			'id'    => 'limits',
			'title' => 'What it deliberately does not do',
			'panel' => true,
			'body'  => '
<p>It is a planning aid. It is <strong>not</strong> a mortgage approval, a rate offer or a quote,
and it is not financial, mortgage, legal, tax or insurance advice. It does not model rate changes
part-way through a term, prepayments, porting a mortgage, HELOCs, or rental and multi-unit
purchases. Land transfer tax is shown for a property with one or two single-family homes.</p>
<p>Your actual payments, premium, taxes, rebates and closing costs depend on your lender, your
insurer, your own circumstances and the final terms of your purchase.
<strong>Before you decide anything, check every figure with a licensed mortgage agent or your
lender, a real estate lawyer, and an accountant or tax advisor.</strong></p>',
		),

	);
}

/* ---------------------------------------------------------------- the panel */

/**
 * The Help panel shown inside the calculator, on the website page and in the app alike.
 *
 * Built from <details> rather than a scripted drawer, for three reasons that all matter here: it
 * needs no JavaScript, so it cannot fail in the app when there is no signal; the browser gives it
 * correct keyboard and screen-reader behaviour for free; and a closed <details> still holds real
 * text, so a phone screen is not buried under a guide nobody asked for.
 */
function aqm_mc_help_panel() {
	$out  = '<details class="aqm-mc__help"><summary>How to use this calculator</summary><div class="aqm-mc__helpbody">';
	foreach ( aqm_mc_help_sections() as $s ) {
		if ( empty( $s['panel'] ) ) { continue; }
		$out .= '<section id="aqm-help-' . esc_attr( $s['id'] ) . '"><h4>' . $s['title'] . '</h4>' . $s['body'] . '</section>';
	}
	$out .= '<p class="aqm-mc__helpmore">The fuller version, including how much you have to put down, '
		. 'land transfer tax across Canada and what first-time buyers get in each province, is in the '
		. '<a href="' . esc_url( aqm_mc_guide_url() ) . '">complete guide</a>.</p>';
	$out .= '</div></details>';
	return $out;
}

/* ----------------------------------------------------------- the public page */

/**
 * The public guide at /mortgage-calculator-guide/, via [aqm_mortgage_guide].
 *
 * Its job is to be a genuinely useful answer to "what does buying actually cost in <province>",
 * not an instruction manual with a calculator bolted on - which is why the reference sections come
 * first and the how-to sections after. The site's search strategy is to own the long tail the
 * directories cannot reach; this page is that, for the cost-of-buying queries.
 */
function aqm_mc_guide_html() {
	$calc = esc_url( aqm_mc_calc_url() );
	$app  = esc_url( aqm_mc_app_url() );

	$out = '<div class="aqm-mc-guide">';
	$out .= '<p class="aqm-mc-guide__lede">Canada has one set of mortgage rules and thirteen different
answers to what it costs to close. This guide explains both &mdash; the federal rules on how much
you must put down, and what each province charges in land transfer tax, gives back to first-time
buyers, and adds in sales tax on a new build &mdash; and how to put your own numbers through the
<a href="' . $calc . '">mortgage and closing-cost calculator</a> that works it out for you.</p>';

	/* A short table of contents: the page is long, and a reader who wants one section should not
	   have to scroll past five others to find it. */
	$out .= '<nav class="aqm-mc-guide__toc" aria-label="On this page"><p><strong>On this page</strong></p><ul>';
	foreach ( aqm_mc_help_sections() as $s ) {
		$out .= '<li><a href="#' . esc_attr( $s['id'] ) . '">' . $s['title'] . '</a></li>';
	}
	$out .= '</ul></nav>';

	$first = true;
	foreach ( aqm_mc_help_sections() as $s ) {
		$out .= '<section id="' . esc_attr( $s['id'] ) . '"><h2>' . $s['title'] . '</h2>' . $s['body'];
		if ( $first ) {
			$out .= '<p class="aqm-mc-guide__cta"><a class="aqm-mc-guide__btn" href="' . $calc . '">Open the calculator</a>'
				. ' <a class="aqm-mc-guide__btn is-alt" href="' . $app . '">Install it as an app</a></p>';
			$first = false;
		}
		$out .= '</section>';
	}

	$out .= '<section id="aqm-mc-guide-end"><h2>Try it with your own numbers</h2>'
		. '<p>Put your price and your province into the <a href="' . $calc . '">calculator</a>, or
<a href="' . $app . '">install it as an app</a> so it is on your phone at the next showing. Nothing
you type is sent anywhere.</p>'
		. '<p>Questions about any of it &mdash; <strong>A. Q. Mufti</strong>, Sales Representative,
RE/MAX Real Estate Centre Inc., Brokerage. <a href="' . esc_url( home_url( '/contact/' ) ) . '">Get in touch</a>.</p>'
		. '</section>';

	$out .= '</div>';
	return $out;
}

add_shortcode( 'aqm_mortgage_guide', function () {
	wp_enqueue_style( 'aqm-mc' );
	return aqm_mc_guide_html();
} );
