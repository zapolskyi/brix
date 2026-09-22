# BRIX 22°

A concept specialty-coffee store built on WordPress and WooCommerce: a custom
theme, a domain plugin, and no page builder. Every product carries a *lot
passport* — altitude, processing, sugar content of the cherry at harvest
(°Bx), and the price the farmer was paid.

This is a portfolio project. The storefront is fictional; the engineering is not.

---

## The constraint that shaped everything

**The entire site works with JavaScript disabled.** Filters are links, the
quiz is a form, the cart is a plain submit, and an order can be placed end to
end without a single script. JavaScript, added later, only makes the same
things faster.

That rule is not decoration. It forced a specific architecture: **every piece
of markup exists exactly once, in PHP.** When the catalogue filters over AJAX,
the browser does not build the grid — it asks the server for the same template
partial the page itself renders, and swaps it in. There is no second
implementation to drift out of sync.

The same pattern repeats for the product page (weight and grind), the quiz, and
the Gutenberg blocks, whose editor preview is rendered by the same PHP as the
front end.

A verification script checks this directly: for twelve filter combinations,
every fragment the REST endpoint returns must appear **verbatim** in the
server-rendered page. Zero divergences.

---

## Architecture

```
wp-content/
├── themes/brix/          presentation — templates, styles, REST endpoints that return markup
│   ├── inc/              modules loaded by functions.php
│   ├── template-parts/   partials shared by pages and endpoints
│   ├── woocommerce/      WooCommerce template overrides
│   ├── blocks/           six dynamic Gutenberg blocks
│   └── assets/           SCSS, vanilla JS, 16 subsetted woff2 files
└── plugins/brix-core/    domain — data, business rules, integrations
    └── src/
        ├── Product/      lot passport, freshness window, Product schema
        ├── Club/         subscriptions: plan, renewals, customer actions
        ├── Payments/     monobank acquiring, cash-on-delivery threshold
        ├── Shipping/     Nova Poshta directories with caching
        ├── Wholesale/    B2B applications, role, pricing
        ├── Quiz/         questions and the recommendation engine
        └── Cli/          `wp brix setup`, `wp brix demo`
```

**The split is strict.** The plugin owns data and rules and never renders a
page. The theme owns markup — which is why its REST endpoints return HTML
rather than JSON: they exist to re-render what the page already renders. The
reasoning is written up as decision 19.

Deactivating the plugin degrades the store; it does not break the theme.

---

## What it does

| | |
|---|---|
| **Lot passport** | 25 fields per lot — altitude, variety, processing, °Bx, farm-gate price, roast date. Registered in code via Secure Custom Fields, not clicked together in an admin screen. |
| **Freshness window** | Degassing, peak and drinkable range computed from the roast date and drawn as a scale. |
| **Catalogue** | Filters, sorting, pagination — links first, AJAX second. Mobile filters are a bottom sheet that counts matches as you choose. |
| **Search** | Suggestions under the header field. Searches taxonomies too: the lots are named in English, so "ефіоп" has to find *Ethiopia Guji Hambela* through the country term. |
| **Quiz** | Six questions, a taste profile, three matched lots. Saved to the account so it is not repeated. |
| **BRIX Club** | Own subscription implementation — pause, skip, cancel. Renewal orders are created by a daily Action Scheduler sweep. |
| **B2B** | Application → moderation → wholesale role with −25% pricing and a 5 kg minimum. |
| **Payments** | monobank acquiring; card data never touches the server. Cash on delivery above a configurable threshold is withdrawn — unless it is the only option left. |
| **Blocks** | Six dynamic Gutenberg blocks. The home page is assembled from them, not hard-coded. |

---

## Running it locally

Requires PHP 8.2+, MySQL, WordPress 6.6+, WooCommerce, Node 20+.

```bash
# 1. Put the theme and plugin in place
cp -r themes/brix        /path/to/wp-content/themes/
cp -r plugins/brix-core  /path/to/wp-content/plugins/

# 2. Activate and configure the store — locale, currency, pages,
#    menus, product lines, shipping zones, payment methods
wp theme activate brix
wp plugin activate woocommerce secure-custom-fields brix-core
wp brix setup

# 3. Fill it with content: 14 lots, 90 variations, 4 farms, 5 guides
wp brix demo
```

Both commands are idempotent — running them twice changes nothing.

### Working on the front end

```bash
cd themes/brix
npm install
npm run css:watch   # SCSS → CSS while you work
npm run build       # minified CSS + critical CSS
npm run zip         # deployable archives for theme and plugin
npm run shot -- http://localhost/shop/ --w=390   # screenshot + overflow check
```

There is no bundler. Sass is compiled by the `sass` CLI, JavaScript is written
as plain ES5-compatible modules, and the compiled CSS is committed — CI checks
that it still matches the source.

---

## Engineering notes

The decisions that are not obvious from the code are written down — nineteen of
them, each with the alternatives that were rejected and why:
[`themes/brix/docs/DECISIONS.md`](themes/brix/docs/DECISIONS.md).

A few that shaped the result:

- **Classic cart and checkout, not blocks.** WooCommerce's block checkout
  renders nothing without JavaScript, which contradicts the project's core
  constraint.
- **Dynamic blocks instead of ACF Blocks.** The front end must not depend on a
  plugin for its home page.
- **Subscriptions built in-house.** WooCommerce Subscriptions is paid, and the
  store needs exactly four actions: pause, skip, change lot, cancel.
- **Wholesale prices computed, not stored.** Ninety variations with a second
  price field would diverge within a week.

The development log, including the bugs worth remembering, is in
[`themes/brix/docs/PROGRESS.md`](themes/brix/docs/PROGRESS.md).

---

## Status

| Area | State |
|---|---|
| Design system, all 21 screens | done |
| Data model, lot passport, demo content | done |
| Catalogue, search, product page, cart, checkout | done |
| Shipping zones, cash on delivery, order flow | done |
| Quiz, BRIX Club, B2B, reminders | done |
| monobank, Nova Poshta autocomplete | code complete, awaiting credentials |
| Deployed demo, Lighthouse figures | not yet |

Roughly 17 000 lines of PHP, 4 300 of SCSS, 1 200 of vanilla JavaScript.
PHPCS runs clean against WordPress Coding Standards across the whole codebase.

Metrics are deliberately absent from this README: Lighthouse numbers measured
on `localhost` mean nothing, and the staging domain does not exist yet.

---

## Licence

MIT — see [LICENSE](LICENSE). The brand, copy and visual identity are part of
the portfolio piece; the code is free to reuse.
