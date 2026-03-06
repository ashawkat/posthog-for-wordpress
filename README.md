# PostHog for WordPress

Send WooCommerce events to PostHog with customizable event names. Integrates with PostHog for unified analytics across platforms.

**Author:** [Launchtitans Team](https://launchtitans.com)

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce 7.0+

## Installation

1. Download the plugin or clone this repository into `wp-content/plugins/posthog-for-wp/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Go to **WooCommerce → PostHog** to configure

## Configuration

1. **Enable tracking** – Toggle to start sending events
2. **Project token** – Your PostHog project API key (from PostHog Project Settings)
3. **PostHog host** – e.g. `https://us.i.posthog.com` or `https://eu.i.posthog.com`
4. **Distinct ID source** – User ID or Email (use the same as your other platforms for unified tracking)
5. **Custom event names** – Rename events to match your mobile app or other platforms

## Tracked Events

| Event            | Default Name        | Trigger                     |
|------------------|---------------------|-----------------------------|
| Page view        | `wc_page_view`      | Shop, account pages         |
| Product viewed   | `wc_product_viewed` | Single product page         |
| Add to cart      | `wc_add_to_cart`    | Item added to cart          |
| Cart viewed      | `wc_cart_viewed`    | Cart page                   |
| Checkout started | `wc_checkout_started` | Checkout page load        |
| Order completed  | `wc_order_completed`  | Order confirmation         |
| Search           | `wc_search`         | Product search              |
| Form submitted   | `form_submitted`    | Form submissions            |

## Form Integrations

Tracks submissions from:

- **Elementor Pro Forms**
- **Contact Form 7**
- **WPForms**
- **Gravity Forms**
- **Funnel Builder (WooFunnels)** optin forms

## HPOS Compatibility

The plugin is compatible with WooCommerce [High-Performance Order Storage (HPOS)](https://woocommerce.com/document/high-performance-order-storage/).

## License

GPL-2.0+

## Links

- [PostHog Documentation](https://posthog.com/docs)
- [Plugin Repository](https://github.com/ashawkat/posthog-for-wordpress)
