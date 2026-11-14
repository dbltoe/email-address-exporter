# Email Address Exporter

Version 3.0.2 · Zen Cart 1.5.8 through 3.0.0 · PHP 7.4 through 8.5 · GPL v2 · by My Zen Cart Host (dbltoe)

Exports customer email addresses and contact details from your Zen Cart store to a file you can import into a mailing tool, spreadsheet, or address book.

## What it does

Email Address Exporter adds one page to your admin, **Tools > Export Email Addresses**. You choose who to export, either with the filters on the page (location, registration date, and what the customer has bought) or by running a saved audience from Query Builder, and you choose which columns land in the file. The email address is always included; the other 15 columns (name, telephone, fax, company, full address, newsletter flag, email format, account dates) are up to you.

Export formats: **CSV**, **TXT** (tab-delimited), **XML**, **JSON** and **HTML**.

Two consent filters are on by default: only newsletter subscribers, and exclude customers whose email format is `NONE`. See Filters.

## Requirements

| Requirement | Details |
| --- | --- |
| Zen Cart | v1.5.8 through v3.0.0 (a single codebase covers the whole range) |
| PHP | 7.4 through 8.5 |
| Admin permission | The page registers under **Tools** and obeys normal admin access control. |

## Where to get it

The official listing is in the Zen Cart Plugins Library: https://www.zen-cart.com/downloads.php?do=file&id=6

Source and releases are on GitHub: https://github.com/dbltoe/email-address-exporter

> Zen Cart checks that library listing for you. If a newer version is published, Plugin Manager flags it on the plugin's row, so there's no need to watch that page.

## Installing

1. Back up your store's database and files, as with any change.
2. This download's own root contains a `zc_plugins/` folder, with this plugin's files inside it at `zc_plugins/EmailAddressExporter/v3.0.2/`. Upload that entire `zc_plugins/` folder into **your store's root** (the folder that also holds `YOUR_ADMIN`, `includes/`, `email/` and `logs/`), **merging** it into the `zc_plugins/` folder already there.
3. In admin, go to **Modules > Plugin Manager**.
4. Find *Export Email Addresses* and click **Install**.
5. The new page appears at **Tools > Export Email Addresses**.
6. Grant access to the appropriate users in **Admin Access Management**.

**How to confirm it landed correctly:** your store's `zc_plugins/` directory should now contain an `EmailAddressExporter` folder alongside whatever other plugins you already have, so that this file exists:

```
<your store root>/zc_plugins/EmailAddressExporter/v3.0.2/manifest.php
```

> **The one mistake worth checking for:** if you end up with `zc_plugins/zc_plugins/`, the folder was dropped one level too deep. Move the `EmailAddressExporter` folder up one level and delete the now-empty inner `zc_plugins`. Plugin Manager will not list the plugin at all until this is right.

Nothing is installed outside that one directory. No files are copied into or edited inside `YOUR_ADMIN`, `includes/` or your template, which is what makes the plugin safe to remove again later.

Once it's installed, a superuser can open this guide from the **ReadMe** button in Plugin Manager.

> **Already running an earlier release?** Uninstall it in Plugin Manager first, then install v3.0.2. Your data is unaffected: this plugin stores nothing of its own.

## Using the page

Log into admin, click **Tools**, then **Export Email Addresses**.

### 1. Choose who to export

There are two modes:

| Mode | What it does |
| --- | --- |
| Build a list using the filters | The default. The plugin builds its own query from the filter controls, and you choose exactly which columns land in the file. |
| Use a saved audience | Runs one of the saved queries from **Tools > Query Builder**. The exported columns are whatever that query returns, so the column checkboxes do not apply. Use this if you have already built a custom segment there. |

### 2. Filters

> **Consent matters.** The two checkboxes at the top, *only newsletter subscribers* and *exclude email format "NONE"*, are what keep an export limited to people who agreed to be emailed. Both are on by default. Turning them off exports addresses regardless of consent; make sure you have a lawful basis (GDPR, CAN-SPAM, CASL, and so on) before you do.

| Filter | What it does |
| --- | --- |
| Country | Matches the customer's default address. Choosing a country reloads the page so the State/Zone list can be populated for it. |
| State / Zone | If the chosen country has zones defined, this becomes a dropdown. Otherwise it is a free-text box that partially matches the address's state field. |
| City | Partial match, so "york" finds both York and New York. |
| Registered From / To | Filters on the account creation date. Leave one box blank for an open-ended range: blank *From* with *To* set means "everything up to that date", reaching back to your very first customer. **One caveat:** a customer whose registration date was never recorded (usually one added by an admin, or brought in by an import) cannot satisfy a date test, so filling in either box leaves them out. The page tells you how many that affects; see Customers a filter leaves out. Leave both blank if you need them included. |
| Has / has never ordered | Mutually exclusive; selecting both is rejected. |
| Ordered between | Filters on order date. Combined with the fields below, all purchase conditions must be satisfied *by the same order*. |
| Order status | Restrict to orders in a particular status, e.g. only Delivered. |
| Purchased from category | Optionally includes every subcategory beneath the one you pick. |
| Purchased product ID | The numeric product ID, as shown in Catalog > Products. |

Filters combine with AND. For example: *Country = United States*, *State = Florida*, *Ordered between 2025-01-01 and 2025-06-30* and *category = Widgets* gives you Florida customers who bought something from Widgets in the first half of 2025.

> A customer who placed several qualifying orders still appears **once**. The purchase conditions are evaluated with an `EXISTS` test rather than a join, so no duplicate rows.

### Customers a filter leaves out

Two of the filters can drop a customer because the information they test is *missing*, rather than because it failed the test:

- a **registration date** bound leaves out anyone with no recorded registration date, usually a customer added by an admin, or brought in by an import;
- a **location** filter leaves out anyone with no default address on file.

Rather than let that happen quietly, the page counts them and says so next to the filter responsible: *"12 customer(s) match everything else you have chosen but have no recorded registration date"*. The count respects every other filter you have set, so it is exactly the number that would otherwise have been in your file. Clear the filter in question to include them.

> The count is worked out when the page is drawn, so set your filters and then click **Refresh Filter Options** to see it before you export. Nothing is counted unless the filter concerned is actually in use.

### 3. Columns

Tick whichever fields you want. The email address is always included and cannot be unticked. Available columns include name, telephone, fax, company, full address (street, suburb, city, state, postcode, country), newsletter flag, email format, registration date and last logon.

Address columns come from the customer's **default address**. A customer with no address book entry still exports, with those columns empty.

### 4. Output

| Format | What you get |
| --- | --- |
| CSV | Downloads. RFC 4180 quoting, so commas, quotes and line breaks inside a value are safe. |
| TXT | Downloads. Tab-separated. |
| XML | Downloads. One `<address_book>` root containing one `<contact>` per customer. |
| JSON | Downloads. An array of objects, pretty-printed. |
| HTML | Displayed on screen instead of downloading, so you can copy and paste it. |

The file extension is set automatically to match the format you picked. Whatever you type in the filename box, the result is always correct and safe.

### Telephone numbers

Customers type phone numbers however they like, so most stores accumulate `(555) 123-4567`, `555.123.4567` and `5551234567` side by side. Set **Telephone Format:** to *Digits only* and the punctuation is stripped on the way out, so every number in the file is comparable.

A leading `+` is kept, because it is the only thing distinguishing an international number from a local one, and an extension written as `x123` or `ext. 123` is preserved rather than merged into the number. Letters are dropped, so a vanity number like `1-800-FLOWERS` becomes `1800`; leave such numbers *As stored* if you have them.

> The option only changes the exported file. Your stored customer data is never modified. Going the other way, *adding* consistent formatting, is not offered, because it would require knowing each number's dialling plan, which Zen Cart does not record. Guessing it would mangle every international number in the file.

### Opening CSV in Excel

Leave **Add a UTF-8 BOM** ticked (the default). Without it, Excel on Windows assumes the system codepage and mangles accented characters: *Zoé* arrives as *ZoÃ©*. Untick it only if the tool you are importing into dislikes a BOM.

> Values that begin with `=`, `+`, `-` or `@` are prefixed with an apostrophe so spreadsheets treat them as text rather than executing them as formulas. This is deliberate protection against CSV injection, and the apostrophe is not part of the data.

## Notes and limitations

- Filters apply to the *Build a list using the filters* mode only. A saved audience runs exactly as written in Query Builder.
- Very large exports run with a 10-minute time limit. If your host caps memory below that, export in date-range slices.
- This plugin only ever reads your data. It creates no tables and modifies no records.
- The *state* column falls back to the zone name when the free-text state field is empty.

## What v3.0 brings

**v3.0.2** replaces readme.html with this guide: the ReadMe button in Plugin Manager now opens it inside the admin, for superusers, in its own window. Nothing changes on the export page or in the files it produces.

**v3.0.1** is hardening only, with no functional change: the admin page now refuses to run unless the admin has been bootstrapped, so a direct request to the file on a host that does not honour the shipped `zc_plugins/.htaccess` is turned away rather than emitting a path-disclosing error.

**v3.0.0** was the rebuild. The headlines:

- New filtering by country, state, city, registration date, purchase date, order status, category and product.
- You now choose which columns are exported.
- Added JSON output; XML output is now well-formed (it previously was not).
- **Fixed:** choosing CSV silently produced a TXT file.
- **Fixed:** company and telephone columns always came out empty.
- **Fixed:** the "All Customers" audience never appeared in the list.
- **Fixed:** the page opened with a spurious "please select an audience" error.
- Proper CSV quoting, CSV-injection protection, and safe filename handling.

## Support

Source, issues and releases are on GitHub (https://github.com/dbltoe/email-address-exporter). Questions and problem reports go to the Zen Cart forum support thread (https://www.zen-cart.com/showthread.php/231172-Email-Address-Exporter), which is also linked from the plugin's Plugin Manager panel.

This plugin is free, and so is the cart it runs on. If it saves you some work, please consider supporting the Zen Cart project: https://docs.zen-cart.com/user/about_us/donate/

## Changelog

See `CHANGELOG.md`. Version 3.0.2 replaces readme.html with this guide, opened from the ReadMe button in Plugin Manager.

## License

Email Address Exporter is free software under the GNU General Public License, version 2 (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html); the full text is in `LICENSE`. Copyright © 2026 My Zen Cart Host (dbltoe).
