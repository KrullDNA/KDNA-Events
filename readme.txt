=== KDNA Events ===
Contributors: kdna
Tags: events, ticketing, elementor, stripe, bookings
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress events and ticketing plugin built entirely on Elementor widgets, with Stripe Checkout for paid events and a pluggable CRM framework.

== Description ==

KDNA Events is a self-contained WordPress plugin for creating, displaying, and selling tickets to events. Every front-end element is delivered as a fully styleable Elementor widget. Paid events are processed through Stripe Checkout, free events skip payment but still capture attendee details. A pluggable CRM framework lets future add-on plugins push attendee data to any external CRM without touching core.

Core capabilities:

* Custom Events post type with structured meta fields (dates, registration window, price, type, capacity, location, organiser).
* Full suite of Elementor widgets for building single event pages, archive pages, checkout flow, success page, and user dashboard.
* Google Maps integration for in-person event locations.
* Stripe Checkout Sessions for paid events, webhook-driven order fulfilment.
* Multi-attendee checkout, collecting name, email, phone, and custom per-event fields for every ticket.
* Min/max tickets per order enforced at checkout.
* Registration window controls (opens at / closes at) distinct from event start.
* Free event flow that mirrors paid checkout without Stripe.
* Booking confirmation emails with ticket codes, optional notifications to per-event organiser.
* Pluggable CRM integration framework for future add-ons (HubSpot, ActiveCampaign, Mailchimp, Salesforce, etc).

== Installation ==

1. Ensure Elementor is installed and active.
2. Upload the plugin folder to /wp-content/plugins/ or install via the Plugins screen.
3. Activate the plugin.
4. An 'Events' menu appears in the WordPress admin sidebar. Add your first event from there.

== Frequently Asked Questions ==

= Does this plugin require Elementor? =

Yes. Every front-end element is an Elementor widget. The plugin deactivates itself with a notice if Elementor is not active.

= Does it work with free events? =

Yes. Set the event price to 0 and the plugin will skip Stripe entirely while still collecting attendee details, generating tickets and sending confirmation emails.

== Changelog ==

= 1.0.0 =
* Stage 1 foundation: custom post type, taxonomy, meta fields, admin Event Details meta box, custom list columns, custom tables for orders and tickets, helper library, uninstall cleanup.
