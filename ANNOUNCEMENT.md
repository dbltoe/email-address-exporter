# Forum posts — maintainer notes

This file lives at the repository root, outside `zc_plugins/`, so none of it is
part of the distributed plugin.

The post text itself is **not** in this file. The Zen Cart forum runs vBulletin,
which takes **BBCode**, not Markdown — Markdown would post as literal `**` and
`###`. The posts are therefore kept as ready-to-paste BBCode:

| File | What it is |
|---|---|
| `forum-post-1.bbcode.txt` | Opening post — establishes the thread, announces the rebuild |
| `forum-post-2.bbcode.txt` | Follow-up — posted once the release is tagged and submitted |

Paste either file's contents straight into the forum editor with BBCode enabled.
Check that the editor is not in WYSIWYG mode, or it may escape the tags.

## Order, and where we are

1. ~~**Post 1 first, with no links.**~~ **DONE** — thread 231172:
   https://www.zen-cart.com/showthread.php/231172-Email-Address-Exporter
2. ~~**Put the URL into `manifest.php`, then submit.**~~ **URL is in the
   manifest.** Done in the right order, which mattered: on Zen Cart v1.5.8,
   v2.0 and v2.1 the Plugin Manager writes `plugin_control.description` only on
   the INSERT that first creates the row, so a forum URL added after people had
   installed would have reached nobody on those carts. Submission still to come.
3. **Post 2 once the GitHub release is tagged and the submission is in**, with
   GitHub as the download source until approval comes through. Text is ready in
   `forum-post-2.bbcode.txt`.

See `PRE-SUBMISSION.md` for the full checklist.

## BBCode used

Kept deliberately plain, so it renders on any vBulletin configuration:
`[B]`, `[SIZE=4]`, `[LIST]` / `[*]`, `[CODE]`, `[URL]`. No `[COLOR]`, no tables,
no nested formatting.

Two things to watch if you edit the text:

- **Ampersands.** `&` is written as `&amp;` in post 1, in the line about a filter
  containing an ampersand. Some vBulletin installs mangle a bare `&`. If it
  renders as literal `&amp;`, replace it with a plain `&`.
- **Arrows.** `→` is used in post 2 for menu paths. If your forum's charset
  garbles it, use `-&gt;` instead.
