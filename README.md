# Email Address Exporter plugin for Zen Cart

**Add-On:** Email Address Exporter Admin Tool
**Designed for:** Zen Cart v1.5.8 through v3.0.0 &mdash; one codebase, no version-specific builds
**PHP:** 7.4 through 8.x

**Current version:** v3.0.0

## Credits

This plugin is the work of a good many people over about twenty years:

- **DrByte** &mdash; original author, and the long-time maintainer who carried it
  through successive Zen Cart releases and converted it to the encapsulated
  plugin structure.
- **That Software Guy (swguy)** &mdash; later revisions.
- **Swifty8078** &mdash; later revisions.
- **Ooba_Scott** &mdash; later revisions.
- **The Zen Cart Development Team** &mdash; the cart itself, and the audience /
  Query Builder functions this plugin builds on.
- **My Zen Cart Host (dbltoe)** &mdash; v3.0.0.

Please support the Zen Cart project:
https://docs.zen-cart.com/user/about_us/donate/ &mdash; thank you!

(PayPal one-off or monthly, Zelle, or GitHub Sponsors. That page lives in the
Zen Cart documentation site, which is worth a look in its own right if you have
not come across it.)

---

## What it does

Exports customer email addresses and contact details from your Zen Cart store to a
file you can import into a mailing tool, spreadsheet, or address book. Useful when
you manage mailing lists or send newsletters from something other than the cart.

Export formats: **CSV**, **TXT** (tab-delimited), **XML**, **JSON**, and **HTML**.

### Choosing who to export

Two modes:

- **Filters** (default) &mdash; build a list from the controls on the page:
  - country, state/zone, and city
  - registration date range
  - purchase date range, order status, category (optionally including
    subcategories), and product ID
  - has ordered / has never ordered
- **Saved audience** &mdash; run any audience defined in Tools &gt; Query Builder,
  exactly as written.

You also pick which columns end up in the file. The email address is always
included; the other 15 (name, telephone, fax, company, full address, newsletter
flag, email format, account dates) are up to you.

### Consent

Two filters are on by default: **only newsletter subscribers**, and **exclude
customers whose email format is `NONE`**. They are what keeps an export limited
to people who agreed to be emailed. You can turn them off, but make sure you
have a lawful basis (GDPR, CAN-SPAM, CASL, etc.) before you do.

---

## Where to get it

- Zen Cart Plugins Library: https://www.zen-cart.com/downloads.php?do=file&id=6
- Source and releases: https://github.com/dbltoe/email-address-exporter

Zen Cart checks that library listing for you — if a newer version is published,
Plugin Manager flags it on the plugin's row.

## Installation

This plugin is packaged as a Zen Cart encapsulated plugin. It installs into your
store's **root** — the folder that also contains `YOUR_ADMIN`, `includes/`,
`email/`, `logs/`, and a `zc_plugins/` folder of its own. Nothing is copied into
or edited inside `YOUR_ADMIN`. Back up your database and files first, as with
any change.

1. This download's own root contains a `zc_plugins/` folder, with this plugin's
   files inside it at `zc_plugins/EmailAddressExporter/v3.0.0/`. Upload that
   entire `zc_plugins/` folder into your store's root, **merging** it into the
   `zc_plugins/` folder that is already there.
2. In admin, go to **Modules > Plugin Manager**, find *Export Email Addresses*,
   and click **Install**.
3. Grant access to the appropriate users in **Admin Access Management**.

**How to confirm it landed correctly:** your store's `zc_plugins/` directory
should now contain an `EmailAddressExporter` folder alongside whatever other
plugins you already have, so that this file exists:

```
<your store root>/zc_plugins/EmailAddressExporter/v3.0.0/manifest.php
```

> **The one mistake worth checking for:** if you end up with
> `zc_plugins/zc_plugins/`, the folder was dropped one level too deep. Move the
> `EmailAddressExporter` folder up one level and delete the now-empty inner
> `zc_plugins`. Plugin Manager will not list the plugin at all until this is
> right.

Already running an earlier release? Uninstall it in Plugin Manager first, then
install v3.0.0. No data is affected &mdash; this plugin stores nothing of its own.

## Use

Log into admin, click **Tools**, then **Export Email Addresses**.

Full documentation is in
[`zc_plugins/EmailAddressExporter/v3.0.0/readme.html`](zc_plugins/EmailAddressExporter/v3.0.0/readme.html),
which is also linked from the Plugin Manager panel.

---

## v3.0.0

Added filtering by location, dates, and purchase history; selectable columns;
JSON output. Fixed several long-standing bugs: choosing CSV silently produced a
TXT file, the company and telephone columns were always empty, the "All
Customers" audience never appeared, XML output was not well-formed, and
free-text filters containing `&` matched nothing. Added RFC 4180 CSV quoting,
CSV-injection protection, and safe filename handling.

See [CHANGELOG.md](zc_plugins/EmailAddressExporter/v3.0.0/CHANGELOG.md) for the
full list.
