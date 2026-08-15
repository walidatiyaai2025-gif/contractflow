# SafeContracts One Page WordPress Theme

Public bilingual WordPress landing theme for SafeContracts.

## Isolation

This theme lives entirely under:

`wordpress-theme/safecontracts-onepage/`

It does not contain or duplicate SafeContracts plugin business logic. The existing `wordpress-plugin/` remains the backend/source-of-truth implementation.

## Public homepage

`front-page.php` is a public one-page experience and does not require authentication. It contains:

- Hero
- Benefits
- Use Cases
- How It Works
- Smart Dashboards
- Security & Control
- FAQ
- Final CTA
- Footer

## Arabic / English

Use the header language switcher or append:

- `?lang=ar` for Arabic/RTL
- `?lang=en` for English/LTR

Language selection is intentionally theme-local and does not change the WordPress admin locale or plugin behavior.

## CTA configuration

In **Appearance → Customize → SafeContracts Public Page** configure:

- Demo / primary CTA URL
- Login URL

The default login target uses WordPress' own login URL. No credentials are embedded in the theme.

## Assets

Landing visuals are stored under `assets/images/` and follow the approved SafeContracts blue/teal SaaS direction. Assets are local to the theme; no third-party image CDN is required.

The client-contract illustration is a web-optimized derivative of the AI-generated concept. Dashboard, reminder, document, analytics and security visuals are lightweight SVG interpretations of the approved AI design direction so the public page remains fast and easy to maintain.

## Accessibility / responsive behavior

- Semantic sections and headings
- Skip link
- Keyboard-safe native FAQ disclosure controls
- Accessible mobile menu state
- Desktop, tablet and phone breakpoints
- RTL/LTR logical layout
- `prefers-reduced-motion` support

## Install

Copy `safecontracts-onepage` to `wp-content/themes/`, activate **SafeContracts One Page**, and use it for the public site front page.

## Development validation

At minimum run PHP syntax validation across all `.php` files before publishing a theme candidate. Browser/device visual QA should cover Arabic RTL and English LTR at desktop, tablet and mobile widths.
