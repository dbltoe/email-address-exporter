# Email Address Exporter

Zen Cart admin tool for exporting customer email addresses and contact details.

- **Version:** v3.0.2
- **Zen Cart:** v1.5.8 through v3.0.0 (one codebase)
- **PHP:** 7.4 through 8.5
- **License:** GNU General Public License, version 2

## Documentation

Full documentation ships with the plugin:

- `v3.0.2/README.md` — the usage guide. Once the plugin is installed, a
  superuser can open it from the **ReadMe** button in Plugin Manager. The same
  file is at the root of the download.
- `v3.0.2/CHANGELOG.md` — what changed in each version.

## Install

This plugin installs into your store's **root** — the folder that also contains
`YOUR_ADMIN`, `includes/`, `email/`, `logs/` and a `zc_plugins/` folder of its
own. Nothing is copied into or edited inside `YOUR_ADMIN`.

Upload the `zc_plugins/` folder from the download into your store's root,
**merging** it into the `zc_plugins/` folder already there, so that you end up
with:

```
<your store root>/zc_plugins/EmailAddressExporter/v3.0.2/manifest.php
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
