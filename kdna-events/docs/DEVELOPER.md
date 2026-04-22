# KDNA Events developer guide

Version: 1.2.0

This guide covers the public surface that third-party add-ons and
bespoke themes should rely on. Everything not documented here is
internal and may change between releases.

## Contents

1. [Plugin constants](#plugin-constants)
2. [Helper functions](#helper-functions)
3. [Actions](#actions)
4. [Filters](#filters)
5. [REST API endpoints](#rest-api-endpoints)
6. [AJAX actions](#ajax-actions)
7. [Building a CRM integration](#building-a-crm-integration)
8. [Overriding email templates](#overriding-email-templates)
9. [Overriding the event card partial](#overriding-the-event-card-partial)

---

## Plugin constants

| Constant | Description |
| --- | --- |
| `KDNA_EVENTS_VERSION` | Plugin semver. |
| `KDNA_EVENTS_FILE` | Absolute path to the main plugin file. |
| `KDNA_EVENTS_PATH` | Absolute directory path with trailing slash. |
| `KDNA_EVENTS_URL` | Directory URL with trailing slash. |
| `KDNA_EVENTS_BASENAME` | Plugin basename (folder/file.php). |

## Helper functions

Defined in `includes/helpers.php`:

| Function | Purpose |
| --- | --- |
| `kdna_events_get_event_meta($post_id, $key, $default = '')` | Fetch an event meta value. Leading underscore on the key is optional. |
| `kdna_events_get_event_location($post_id)` | Return the parsed location array `{name, address, lat, lng}`. |
| `kdna_events_is_free($post_id)` | True when the event price is zero or empty. |
| `kdna_events_format_datetime($iso, $format, $post_id = 0)` | Format an ISO 8601 datetime in the event or site timezone. |
| `kdna_events_format_price($amount, $currency)` | Apply the symbol map and `number_format_i18n`. |
| `kdna_events_generate_order_reference()` | Return `KDNA-EV-YYYY-XXXXXX`, collision-checked. |
| `kdna_events_generate_ticket_code()` | Return a unique 8 character uppercase alphanumeric code. |
| `kdna_events_get_tickets_sold($event_id)` | Cached count of valid tickets, 5 minute transient. |
| `kdna_events_invalidate_sold_count($event_id)` | Clear the cached count. Every ticket status change already calls this. |
| `kdna_events_is_registration_open($event_id)` | Central gate used by the Register Button and the checkout AJAX handler. |
| `kdna_events_get_page_url($slug)` | Return the permalink of the assigned Checkout, Success or My Tickets page. Accepts `'checkout'`, `'success'`, `'my_tickets'`. |

## Actions

### `kdna_events_register_widgets`

Fires inside the `elementor/widgets/register` action so concrete widgets
can be added without touching core.

```php
add_action( 'kdna_events_register_widgets', function ( $widgets_manager ) {
	$widgets_manager->register( new My_Custom_Widget() );
} );
```

### `kdna_events_register_crm_integrations`

Fires on `plugins_loaded` priority 20 with the registry. Register
integrations here.

```php
add_action( 'kdna_events_register_crm_integrations', function ( $registry ) {
	$registry->register( new My_HubSpot_Integration() );
} );
```

### `kdna_events_ticket_created`

Fires for every ticket after `finalise_order` has created the row,
invalidated the sold-count transient and attempted emails.

Signature: `function ( int $ticket_id, int $order_id, int $event_id )`.

### `kdna_events_after_crm_sync`

Fires after each `sync_ticket` call so observers can audit outcomes
without wrapping the bridge itself.

Signature: `function ( string $integration_id, array $payload, $result, int $ticket_id )`.
`$result` is `true` on success or a `WP_Error`.

### `kdna_events_after_success_ticket` (v1.1)

Fires after each ticket's body has rendered inside the Success Tickets
widget's per-ticket loop. Add-ons can inject per-ticket UI (download
buttons, wallet passes, calendar links) without forking core.

Signature: `function ( object $ticket, object $order, array $settings )`.

```php
add_action( 'kdna_events_after_success_ticket', function ( $ticket, $order, $settings ) {
	echo '<a href="' . esc_url( my_addon_pdf_url( $ticket ) ) . '">Download PDF</a>';
}, 10, 3 );
```

### `kdna_events_after_my_ticket` (v1.1)

Fires inside the per-ticket loop of the My Tickets widget. Same shape
as `kdna_events_after_success_ticket` so add-ons can share handlers.

Signature: `function ( object $ticket, null $order, array $settings )`.
`$order` is null because the My Tickets list joins orders on user /
email across many orders; all order context lives on `$ticket`.

```php
add_action( 'kdna_events_after_my_ticket', function ( $ticket, $order, $settings ) {
	echo '<button type="button">Add to calendar</button>';
}, 10, 3 );
```

### `kdna_events_pending_order_created`

Fires after a pending order row is inserted but before Stripe or the
free-event path continues. Useful for audit trails or pre-payment
analytics.

Signature: `function ( int $order_id, array $context )`.
`$context` contains `order_reference`, `event_id`, `is_free`,
`quantity`, `total`, `attendees`.

## Filters

### `kdna_events_crm_sync_data`

Modify the Section 7 payload before it reaches every CRM integration.

```php
add_filter( 'kdna_events_crm_sync_data', function ( $payload, $ticket_id, $order_id ) {
	$payload['custom_source'] = 'my-funnel';
	return $payload;
}, 10, 3 );
```

### `kdna_events_email_attachments` (v1.1)

Applied to the `$attachments` array passed to `wp_mail` for every KDNA
Events booking confirmation and admin notification send. Lets add-ons
inject additional files (PDF tickets from the upcoming
`kdna-events-pdf-tickets` add-on, CSV rosters, etc.) into outgoing
emails without modifying core.

```php
add_filter( 'kdna_events_email_attachments', function ( $attachments, $order_id, $email_type ) {
	if ( 'booking_confirmation' !== $email_type ) {
		return $attachments;
	}
	$path = my_addon_build_pdf_for_order( $order_id );
	if ( $path && file_exists( $path ) ) {
		$attachments[] = $path;
	}
	return $attachments;
}, 10, 3 );
```

Parameters:

- `array $attachments` Array of absolute file paths. Empty by default.
- `int $order_id` The order being emailed.
- `string $email_type` `'booking_confirmation'` or `'admin_notification'`.

### `kdna_events_crm_integrations`

Reorder, drop or replace the registered integrations map.

```php
add_filter( 'kdna_events_crm_integrations', function ( $map ) {
	unset( $map['some-old-integration'] );
	return $map;
} );
```

## REST API endpoints

| Route | Method | Purpose |
| --- | --- | --- |
| `/wp-json/kdna-events/v1/stripe-webhook` | POST | Stripe webhook endpoint. Signature-validated. Returns 200 on success, 400 on signature failure, 500 on processing errors. Idempotent. |
| `/wp-json/kdna-events/v1/confirm-order?ref=X` | GET | Success page race-mitigation polling endpoint. Returns `{status, finalised}`. |

## AJAX actions

| Action | Auth | Purpose |
| --- | --- | --- |
| `kdna_events_filter_grid` | Public, nonce-protected | Event Grid AJAX filter. |
| `kdna_events_create_order` | Public, nonce-protected | Checkout submit. Re-validates registration window, min/max and capacity server-side. |
| `kdna_events_send_test_email` | Admin, nonce-protected | Triggers an admin notification using sample data. |
| `kdna_events_crm_test` | Admin, nonce-protected | Runs the integration's `test_connection()`. |
| `kdna_events_crm_clear_log` | Admin, nonce-protected | Clears the rolling sync log. |

All nonces live on the `kdna_events_frontend` action except the admin
ones which use `kdna_events_test_email` and `kdna_events_crm_test`.

## Building a CRM integration

Section 7 of the project brief defines the contract. In short:

1. Put your add-on in its own plugin or mu-plugin.
2. Extend `KDNA_Events_CRM_Integration`.
3. Register on `kdna_events_register_crm_integrations`.

Minimal HubSpot example:

```php
<?php
/*
 * Plugin Name: KDNA Events HubSpot
 * Requires Plugins: kdna-events
 */

add_action( 'kdna_events_register_crm_integrations', function ( $registry ) {
	$registry->register( new KDNA_Events_HubSpot() );
} );

final class KDNA_Events_HubSpot extends KDNA_Events_CRM_Integration {

	public function get_id()          { return 'hubspot'; }
	public function get_name()        { return 'HubSpot'; }
	public function get_description() { return 'Push attendees to a HubSpot contact list.'; }

	public function get_settings_fields() {
		return array(
			array( 'key' => 'token',   'label' => 'Private app token', 'type' => 'password' ),
			array( 'key' => 'list_id', 'label' => 'List ID',           'type' => 'text' ),
		);
	}

	public function test_connection() {
		$token = $this->get_setting( 'token' );
		if ( ! $token ) {
			return new WP_Error( 'no_token', 'Token required.' );
		}
		$response = wp_remote_get(
			'https://api.hubapi.com/account-info/v3/details',
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'http', wp_remote_retrieve_body( $response ) );
		}
		return true;
	}

	public function sync_ticket( array $payload ) {
		$token   = $this->get_setting( 'token' );
		$list_id = $this->get_setting( 'list_id' );
		if ( ! $token || ! $list_id ) {
			return new WP_Error( 'not_configured', 'Token and list id required.' );
		}

		$response = wp_remote_post(
			'https://api.hubapi.com/contacts/v1/contact/batch/',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					array(
						'email'      => $payload['attendee']['email'],
						'properties' => array(
							array( 'property' => 'firstname',       'value' => $payload['attendee']['name'] ),
							array( 'property' => 'kdna_event_ref',  'value' => $payload['order_reference'] ),
							array( 'property' => 'kdna_ticket',     'value' => $payload['ticket_code'] ),
						),
					),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( (int) wp_remote_retrieve_response_code( $response ) >= 300 ) {
			return new WP_Error( 'hubspot', wp_remote_retrieve_body( $response ) );
		}
		return true;
	}
}
```

### Payload reference

Section 7 of the brief is authoritative. Live shape:

```php
array(
  'ticket_code'     => 'ABCDEF12',
  'order_reference' => 'KDNA-EV-2026-ABCDEF',
  'event' => array(
    'id'        => 123,
    'title'     => 'Advanced WordPress Summit',
    'subtitle'  => 'An evening with industry leaders',
    'start'     => '2026-05-10T09:00',
    'end'       => '2026-05-10T17:00',
    'type'      => 'in-person',
    'location'  => array( 'name' => '...', 'address' => '...', 'lat' => 0.0, 'lng' => 0.0 ),
    'price'     => 199.00,
    'currency'  => 'AUD',
    'organiser' => array( 'name' => '...', 'email' => '...' ),
  ),
  'attendee' => array(
    'name'          => 'Jane Doe',
    'email'         => 'jane@example.com',
    'phone'         => '+61...',
    'custom_fields' => array( 'dietary' => 'vegetarian' ),
  ),
  'purchaser' => array( 'name' => '...', 'email' => '...' ),
  'is_free'   => false,
  'total'     => 199.00,
)
```

## Email Design (v1.1)

v1.1 Brief A replaces the v1.0 plain body textarea with a full branded
HTML email system under Events, Settings, Email Design. Every visual
property (logo, colours, typography, layout, button styling, footer,
content strings) is controllable from that tab, with a live preview
panel and a 'Send test to ...' field for real-world SMTP testing.

Per-event overrides live on the Event Details meta box under the
'Email Overrides' heading:

- Email subject line
- Email heading
- Email content 1
- Email content 2
- Email footer text

Any override is merge-tag enabled and falls back to the matching
global default when left blank.

### Merge tags

Use these in subject lines, headings, content 1 / 2, footer text and
the admin notification intro. Unknown tags render as empty strings.

| Tag | Value |
| --- | --- |
| `{event_title}` | Event post title |
| `{event_subtitle}` | Event subtitle |
| `{event_date}` | Formatted start date (no time) |
| `{event_time}` | Formatted start time |
| `{event_end_date}` / `{event_end_time}` | End date/time |
| `{event_type}` | Human label, In-person / Virtual / Hybrid |
| `{event_location}` | Name + address, joined |
| `{event_location_name}` / `{event_address}` | Individual parts |
| `{event_url}` | Permalink |
| `{virtual_url}` | Event's virtual URL (empty on in-person events) |
| `{organiser_name}` / `{organiser_email}` | Organiser details |
| `{purchaser_name}` / `{purchaser_first_name}` / `{purchaser_email}` | Buyer |
| `{attendee_name}` | Per-ticket recipient (falls back to purchaser) |
| `{ticket_code}` | Single ticket code, or all codes joined |
| `{order_ref}` / `{quantity}` / `{total}` | Order meta |
| `{my_tickets_url}` | URL of the assigned My Tickets page |
| `{business_name}` / `{support_email}` | From Email Design + Emails settings |

### Header image field

Each event has an Email Header Image meta field (`_kdna_event_image`,
WordPress attachment ID) on the Event Details, Basics section. The
plugin registers a custom image size `kdna-events-email-header` at
1200x600 centre-cropped, rendered at 600x300 in the email for retina
sharpness.

Resolve the URL in your own code via:

```php
$url = kdna_events_get_email_header_image_url( $event_id );
// Falls back through: event image, plugin default, empty string.
```

Uploads made before the plugin was active get their cropped size
auto-regenerated on activation and on demand via a scheduled
`kdna_events_regenerate_email_image_crops` task.

## Overriding email templates

Both email templates live in `templates/emails/`. They can be
overridden by copying the file into a child theme and filtering the
path if needed, but the simplest approach is to duplicate the shipped
file and load a custom version directly from your theme.

If you need surgical control without forking the whole template, hook
the payload and body:

```php
// Append a footer note to the plain body that feeds both email templates.
add_filter( 'option_kdna_events_booking_email_body', function ( $body ) {
	return $body . "\n\nTo view your tickets online, visit https://example.com/my-tickets";
} );
```

For a full template swap, use the `kdna_events_email_template_path`
filter the plugin exposes automatically when the `KDNA_Events_Emails`
class calls its `load_template()` helper. Example:

```php
add_filter( 'kdna_events_email_template_path', function ( $path, $name ) {
	$candidate = get_stylesheet_directory() . '/kdna-events/' . $name . '.php';
	return file_exists( $candidate ) ? $candidate : $path;
}, 10, 2 );
```

## Overriding the event card partial

The shared card partial at `templates/partials/event-card.php` drives
both initial Event Grid render and the AJAX filter response, so any
override is picked up in both paths. Copy the file into
`your-theme/kdna-events/partials/event-card.php` and filter the path:

```php
add_filter( 'kdna_events_event_card_template', function ( $path ) {
	$override = get_stylesheet_directory() . '/kdna-events/partials/event-card.php';
	return file_exists( $override ) ? $override : $path;
} );
```

## Support

* Plugin repository: https://github.com/krulldna/kdna-events
* Issues and feature requests: open a GitHub issue.
* Commercial support: hello@krulldna.com

## Tax Invoices (v1.2, Brief C)

### Options

All invoice options are keyed under the `kdna_events_invoice_*` and `kdna_events_invoices_enabled` namespace. The canonical schema lives on `KDNA_Events_Invoices::options_schema()` with types and defaults. Notable entries:

- `kdna_events_invoices_enabled` (bool) — master switch.
- `kdna_events_invoice_business_legal_name`, `..._trading_name`, `..._tax_id`, `..._business_address`, `..._business_email`, `..._business_phone`, `..._business_website` — seller identity.
- `kdna_events_invoice_tax_registered` (bool), `..._jurisdiction` (`au|uk|eu|nz|us|other`), `..._tax_label`, `..._tax_rate` (decimal as string), `..._document_heading`, `..._tax_inclusive_statement` — tax configuration.
- `kdna_events_invoice_number_prefix`, `..._suffix`, `..._start`, `..._padding`, `..._current_sequence` — numbering. `..._start` is locked once `..._current_sequence` exceeds zero.
- `kdna_events_invoice_line_item_template`, `..._paid_label`, `..._pending_label`, `..._payment_method_label`, `..._payment_terms`, `..._notes`, `..._date_source` — content strings. Template supports `{quantity}`, `{event_title}`, `{event_subtitle}`, `{event_date}` via `kdna_events_render_merge_tags`.
- `kdna_events_invoice_design_*` — design tokens. Each inheritable token pairs with `kdna_events_invoice_design_inherit_*` (default on) so edits on Email Design cascade into invoices automatically.

### Database table

`wp_kdna_events_invoices` (one row per paid order, `UNIQUE invoice_number / sequence_number / order_id`). Columns: `invoice_id`, `invoice_number`, `sequence_number`, `order_id`, `issued_at`, `tax_rate_applied`, `tax_label_applied`, `subtotal_ex_tax`, `tax_amount`, `total_inc_tax`, `currency`, `business_snapshot` (JSON), `status` (`issued|voided|replaced`), `notes`, `created_at`, `updated_at`. Schema version tracked in the `kdna_events_invoices_db_version` option; migrations run via `KDNA_Events_Invoices_DB::maybe_upgrade()` on `admin_init`.

### Hooks

| Name | Type | Signature |
| --- | --- | --- |
| `kdna_events_invoice_payload` | filter | `function ( array $payload, object $invoice, object|null $order )` — mutate the render context (line items, merge tags, snapshot) before templates consume it. |
| `kdna_events_invoice_html` | filter | `function ( string $html, array $payload, object $invoice )` — post-process the raw HTML before Dompdf ingests it. Useful for add-ons that want to inject a watermark, promotional block, etc. |
| `kdna_events_invoice_pdf_options` | filter | `function ( \Dompdf\Options $options )` — override Dompdf options (paper, fonts, security, remote loading). |

### REST endpoint

- `GET /wp-json/kdna-events/v1/invoice/{invoice_number}.pdf`
- Auth: the current user owns the underlying order (user ID or purchaser email match), OR they can `manage_options`, OR the query string carries a valid signed token `?t=...`.
- Token helpers: `kdna_events_invoice_generate_token( $number )` and `kdna_events_invoice_verify_token( $number, $token )`. Lifetime defaults to 24 hours (`KDNA_Events_Invoices::TOKEN_LIFETIME`).
- Convenience URL builder: `kdna_events_invoice_download_url( $number, $signed = false )`.

### Idempotency + snapshots

Calling `KDNA_Events_Invoices::generate( $order_id )` multiple times for the same order always returns the same number and the same rendered PDF, because `business_snapshot`, `tax_rate_applied` and `tax_label_applied` are frozen at creation time. Changes to Tax Invoices settings only affect invoices created after the change. This meets ATO immutability requirements for historical invoices.
