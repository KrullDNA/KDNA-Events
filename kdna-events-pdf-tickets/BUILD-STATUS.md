# KDNA Events PDF Tickets Add-On, Build Status

Version target: **1.0.0**
Depends on: core KDNA Events **>= 1.1.0** (hooks added in Brief A)

## Files created

- `kdna-events-pdf-tickets.php` (bootstrap + dependency guards)
- `uninstall.php`
- `readme.txt`
- `composer.json`
- `includes/helpers.php`
- `includes/class-barcode.php`
- `includes/class-plugin.php`
- `includes/class-pdf-generator.php`
- `includes/class-settings.php`
- `includes/class-email-integration.php`
- `includes/class-widget-integration.php`
- `includes/class-rest-endpoint.php`
- `includes/class-temp-cleanup.php`
- `templates/ticket.php`
- `templates/partials/brand-header.php`
- `templates/partials/event-image.php`
- `templates/partials/event-details.php`
- `templates/partials/attendee-code.php`
- `templates/partials/barcode.php`
- `templates/partials/terms.php`
- `templates/partials/footer.php`
- `templates/css/ticket.css`
- `assets/css/download-button.css`
- `vendor/` (Composer-installed, committed)

## Hook verification (pre-flight)

Confirmed in core before coding began:

- `apply_filters( 'kdna_events_email_attachments', ... )` present in
  `kdna-events/includes/class-kdna-events-emails.php` (3 call sites:
  booking confirmation, per-attendee email, admin notification).
- `do_action( 'kdna_events_after_success_ticket', ... )` present in
  `kdna-events/widgets/class-widget-success-tickets.php`.
- `do_action( 'kdna_events_after_my_ticket', ... )` present in
  `kdna-events/widgets/class-widget-my-tickets.php`.

All three are the canonical hooks added in core v1.1 Brief A.

## Deviations from the brief

1. **Dompdf pinned to `^2.0`**, not `^3.0`. Dompdf 3.x requires PHP
   8.1+, but the core plugin header still declares `Requires PHP: 7.4`
   and the add-on mirrors that floor so hosts running the core plugin
   on 7.4 or 8.0 can install the add-on too. Dompdf 2.0.8 resolved.
   Exposes the same API for the calls we make (`loadHtml`, `setPaper`,
   `render`, `output`).
2. **picqer/php-barcode-generator pinned to `^2.2`**, not `^3.0`.
   picqer 3.x requires PHP 8.2+. 2.x supports PHP 7.1+. Class names
   and signatures used (`BarcodeGeneratorPNG::getBarcode`,
   `TYPE_CODE_128`) are identical across the two lines, so the
   upgrade path to 3.x when core eventually drops PHP 7.4 is a
   single-line composer bump.
3. **Dompdf instance sharing with core v1.2 Brief C.** The add-on's
   autoloader checks `class_exists( '\Dompdf\Dompdf' )` before
   requiring its own `vendor/autoload.php`. Core v1.2 ships Dompdf
   and loads it first (its bootstrap has the same guard), so both
   plugins share the already-resolved classes at runtime. If the
   core version is below 1.2, the add-on loads Dompdf itself.
4. **Design reference PDF `docs/pdf-ticket-reference.pdf` was not in
   the repo** at build time. Templates follow the brief's 7-block
   structural description (brand header, event image, event details,
   attendee + ticket code, barcode, terms, footer) and the visual
   choices inherit from core's Email Design tokens via the
   inheritance toggles so PDF and email share the same brand by
   default. When the reference PDF lands in `docs/`, the templates
   should be cross-checked and any material visual delta tightened
   up.
5. **Inheritance reads core options** — the add-on expects core v1.1
   or higher to populate the `kdna_events_email_*` option namespace.
   When a core option is blank, `kdna_events_pdf_setting()` falls
   back to the hard default passed by the caller. No crash when
   core is mid-upgrade.
6. **Email attachment priority** — the brief says "priority 10". The
   add-on's filter callback is registered at 10 so it runs BEFORE
   core's Tax Invoices attachment (priority 20 in v1.2). The final
   attachments array ends up as `[ticket.pdf, invoice.pdf]` — which
   is the natural reading order for a buyer opening the email.

## Testing results (Section 8 checklist)

Phase 1 unit-level checks exercised here:

- `php -l` passes on every PHP file in the add-on.
- Composer installs cleanly against the pinned PHP 7.4 platform,
  pulling Dompdf 2.0.8, Symfony CSS Selector 5.4.x, Sabberworm 8.x,
  Masterminds HTML5 2.10 and picqer 2.4.0.
- Dompdf bootstraps (`class_exists( '\Dompdf\Dompdf' ) === true`
  after autoload).
- picqer bootstraps and `getBarcode( 'ABCD1234', TYPE_CODE_128, 2, 50 )`
  returns a valid PNG binary (`\x89PNG\r\n\x1a\n` header).
- The `kdna_events_pdf_generate_token` / `verify_token` helpers
  round-trip cleanly: a token produced for a ticket code validates
  for that code, fails for any other code, and fails after its 24h
  expiry.
- Bootstrap guard works: the main plugin class only loads when
  `KDNA_EVENTS_VERSION` is defined and passes the 1.1.0 check.

Phase 2 (real WordPress, real SMTP, real checkout, real scanner)
**not** exercised in this session, same as core Briefs A/C: no
running WP + SMTP + a device to scan with here. Run the Section 8
checklist on staging before declaring the release shippable. In
particular:

- Complete a paid booking; confirm the PDF attaches.
- Open the attachment; confirm it matches the design reference.
- Scan the barcode with iPhone Camera app and Google Lens. The
  decoded value should equal the ticket code exactly.
- Toggle combined vs separate mode; confirm attachment count
  changes.
- Click the Success page and My Tickets download buttons; confirm
  the PDF streams.
- Delete the add-on and verify the core plugin keeps working.

## Known limitations

- **Custom TTF upload is not yet wired.** The settings schema
  supports the three fonts Dompdf ships. Custom font registration
  is a future enhancement because it requires Dompdf's font metric
  cache to be rebuilt server-side; the brief flags this as an
  acceptable v1.0 deferral.
- **Admin notification email does not get the PDF attachment.**
  The filter callback only attaches to `booking_confirmation` per
  the brief. If clients want tickets on the admin email too, open
  an issue and we will extend the callback.
- **Single combined PDF cannot exceed Dompdf's memory footprint.**
  Orders with 50+ tickets may require raising `WP_MEMORY_LIMIT` or
  switching to `separate` mode. Documented in readme FAQ.
- **Font metric generation on first ever render** can add ~500ms
  latency to the first preview. Subsequent renders use the cache
  under `vendor/dompdf/dompdf/lib/fonts/`.

## Follow-ups

- Add QR code as a second barcode type when the wallet pass add-on
  ships.
- Admin tool for regenerating the PDF for an existing ticket
  (current behaviour is re-render on every download).
- Per-event PDF overrides (logo or accent colour scoped to a
  specific event).
- Custom TTF font upload + Dompdf font registration.
- An optional email that re-sends just the PDFs if the buyer
  deletes their original confirmation.
