# Pre-submission checklist

Maintainer notes. This file lives at the repository root, **outside**
`zc_plugins/`, so it is not part of the distributed plugin.

## Three URLs to set before submitting

### 1. Forum support thread — DONE

`$eaeForumUrl` is set to:

    https://www.zen-cart.com/showthread.php/231172-Email-Address-Exporter

Verified live, and the "Forum Support Thread" link now renders in the Plugin
Manager panel below the Read Me and GitHub buttons.

This was the order-dependent one, and it is now settled in the right order —
the URL is in the manifest *before* submission. On Zen Cart v1.5.8, v2.0 and
v2.1 the Plugin Manager writes `plugin_control.description` only on the INSERT
that first creates the row; later scans update just the `infs` column. Had the
link gone in after release, it would have reached nobody on those versions short
of an uninstall and re-install.

**Optional refinement:** Social Contact Footer stores the permalink to the
opening post (`...?p=NNNNNNN#postNNNNNNN`) rather than the bare thread address,
on the reasoning that the opening post is the authoritative description while
the thread around it accumulates whatever a support thread accumulates. With a
one-post thread the two are equivalent; once the thread has pages, the permalink
lands better. Swap it in if you want consistency with the other plugin.

### 2. GitHub release location — `$eaeReleasesUrl` in `manifest.php`

Currently `''`. Set it to the tagged release page, e.g.
`https://github.com/dbltoe/email-address-exporter/releases/tag/v3.0.0`.

Be aware this only feeds the `changelog` manifest key, and **no supported Zen
Cart version reads that key**. Verified against v1.5.8, v2.0.0, v2.2.2 and
v3.0.0-dev: the only manifest keys consumed are `pluginName`,
`pluginDescription`, `pluginAuthor`, `pluginVersion`, `zcVersions` and
`pluginId`. `changelog`, `github_repo` and `pluginGroups` are recorded for the
plugin library and possible future core use, but nothing renders them.

If the release page should be visible to a store owner, it has to go in
`pluginDescription` — that is the only field that reaches the Plugin Manager
panel.

### 3. Plugin library listing — already set

`$eaeDownloadUrl` is `https://www.zen-cart.com/downloads.php?do=file&id=6`, live
once the submission is approved.

**Do not let this drift from `pluginId`.** They are the same number by design:
`pluginId` is stored as `plugin_control.zc_contrib_id`, and
`PluginManager::getPluginsAfterCheckingForNewVersionsOnline()` posts those ids to
zen-cart.com to discover whether a newer release exists. A mismatched id means
store owners are never notified of updates. Both are currently `6`, carried over
from the original mod.

## Before tagging

- [x] `$eaeForumUrl` set — thread 231172, live, renders in Plugin Manager
- [ ] `$eaeReleasesUrl` set, and surfaced in `pluginDescription` if it should be visible
- [ ] `pluginVersion` in `manifest.php` matches the `v3.0.0/` directory name
- [ ] WCAG pass completed (deferred until after operational testing)
- [ ] Operational test on a real cart — the admin page has never been rendered
      inside a live Zen Cart; only its SQL, renderers and input handling are
      covered by tests
