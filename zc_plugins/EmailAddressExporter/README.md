# Email Address Exporter

Zen Cart admin tool for exporting customer email addresses and contact details.

- **Version:** v3.0.0
- **Zen Cart:** v1.5.8 through v3.0.0 (one codebase)
- **PHP:** 7.4 through 8.x

## Credits

- **DrByte** — original author, and the long-time maintainer who carried it
  through successive Zen Cart releases and converted it to the encapsulated
  plugin structure.
- **That Software Guy (swguy)** — later revisions.
- **Swifty8078** — later revisions.
- **Ooba_Scott** — later revisions.
- **The Zen Cart Development Team** — the cart itself, and the audience /
  Query Builder functions this plugin builds on.
- **My Zen Cart Host (dbltoe)** — v3.0.0.

## Documentation

Full documentation ships with the plugin:

- [`v3.0.0/readme.html`](v3.0.0/readme.html) — usage guide, also linked from the
  Plugin Manager panel once installed.
- [`v3.0.0/CHANGELOG.md`](v3.0.0/CHANGELOG.md) — what changed, including the
  bugs fixed in v3.0.0.

## Install

This plugin installs into your store's **root** — the folder that also contains
`YOUR_ADMIN`, `includes/`, `email/`, `logs/` and a `zc_plugins/` folder of its
own. Nothing is copied into or edited inside `YOUR_ADMIN`.

Upload the `zc_plugins/` folder from the download into your store's root,
**merging** it into the `zc_plugins/` folder already there, so that you end up
with:

```
<your store root>/zc_plugins/EmailAddressExporter/v3.0.0/manifest.php
```

Then install it from **Modules > Plugin Manager**. The page appears under
**Tools > Export Email Addresses**.

> If you end up with `zc_plugins/zc_plugins/`, the folder was dropped one level
> too deep. Move `EmailAddressExporter` up one level and delete the now-empty
> inner `zc_plugins`. Plugin Manager will not list the plugin until this is
> right.

Already running an earlier release? Uninstall it first. No data is affected —
this plugin creates no tables and modifies no records.

## Where to get it

- Zen Cart Plugins Library: https://www.zen-cart.com/downloads.php?do=file&id=6
- Source and releases: https://github.com/dbltoe/email-address-exporter

Zen Cart checks that library listing for you — if a newer version is published,
Plugin Manager flags it on the plugin's row.
