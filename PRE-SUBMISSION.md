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

### 2. GitHub release location — DONE

`$eaeReleasesUrl` is set to:

    https://github.com/dbltoe/email-address-exporter/releases/tag/v3.0.0

Because **no supported Zen Cart version reads the `changelog` key** — verified
against v1.5.8, v2.0.0, v2.2.2 and v3.0.0-dev, where the only keys consumed are
`pluginName`, `pluginDescription`, `pluginAuthor`, `pluginVersion`, `zcVersions`
and `pluginId` — the URL is also surfaced as a **Release Notes** link in
`pluginDescription`, which is the only field that reaches the Plugin Manager
panel. Setting it in `changelog` alone would have shown it to nobody.

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
- [x] `$eaeReleasesUrl` set, and surfaced as a Release Notes link in `pluginDescription`
- [x] `pluginVersion` in `manifest.php` matches the `v3.0.0/` directory name
- [x] GitHub: v2.0.0 preserved on the `2.0.0` branch; `main` carries v3.0.0
- [x] GitHub release `v3.0.0` published, with the packaged zip attached
- [ ] **Submit to the Zen Cart Plugins Library** (id 6) — the remaining step
- [ ] Post the follow-up forum reply (`forum-post-2.bbcode.txt`) once submitted
- [ ] WCAG pass completed (deferred until after operational testing)
- [ ] Operational test on a real cart — the admin page has never been rendered
      inside a live Zen Cart; only its SQL, renderers and input handling are
      covered by tests
