# SafeContracts — Visual Identity Guide

## Brand name

**SafeContracts**

## Product personality

Professional, reliable, financial, operational and easy to scan. The UI should feel like a modern corporate/FinTech operations product rather than a generic WordPress site.

## Core visual metaphor

**Contract + Clipboard + Checkmark**

The metaphor communicates a contract that is actively tracked and financially followed up.

## Color direction

Initial design tokens for implementation prototypes:

```text
Navy 900     #102A43
Navy 800     #173F67
Blue 600     #2F6FB2
Blue 100     #EAF2FB
Surface      #FFFFFF
Background   #F4F7FA
Text Primary #102A43
Text Muted   #627D98
Success      #24B34B
Warning      #D99000
Danger       #D64545
Info         #2F6FB2
Border       #D9E2EC
```

Exact token values may be tuned during implementation, but semantic use must remain consistent.

## Status semantics

- Paid / successful / tracked: Green
- Due soon: Amber
- Overdue / failed: Red
- Upcoming / informational: Blue
- Neutral/inactive: Gray
- Partially paid: Amber/Blue semantic treatment, consistently defined in the component library

## Typography

Use an Arabic/English-compatible professional sans-serif family. Typography must support RTL and LTR layouts, readable financial tables and strong numeric hierarchy.

Required typographic roles:

- Page title
- Section heading
- KPI value
- Table/header text
- Body text
- Caption/helper text

## Components

One design system must be shared conceptually between WordPress Admin and Mobile:

- Buttons
- Inputs
- Select/dropdown
- Search
- Date filters/pickers
- Cards
- KPI cards
- Status badges
- Tables/lists
- Tabs
- Modals/sheets
- Toasts/notifications
- Charts
- Empty/error/loading states

## WordPress white-label direction

Operational users should experience **SafeContracts**, not default WordPress.

- SafeContracts logo/name on login and admin shell
- Navy/blue navigation
- Clean light content surfaces
- Green primary success/positive action treatment
- Hide irrelevant WordPress navigation for operational roles
- Remove default dashboard widgets and replace with SafeContracts KPIs/work queues
- Keep security enforced by capabilities, never by visual hiding alone

## Mobile direction

Mobile should not copy desktop layouts. It uses the same tokens/identity but a mobile-first information hierarchy:

- Clear KPI summary
- Customer dropdown
- Dependent contract dropdown
- Due/overdue action lists
- Large tap targets
- Light-edit flows
- Strong status badges
- Server-generated Excel export action

## Reference concepts

The three approved concept directions are represented in `assets/brand/reference/` as cleaned SafeContracts SVG references:

1. Mobile splash / phone concept
2. WordPress/admin dashboard concept
3. Clipboard/contract brand illustration

The production versions intentionally remove:

- Cryptocurrency / Ethereum imagery
- Visible WordPress branding
- `SmartContractPress` naming

They use **SafeContracts** branding instead.

## Dynamic identity settings

SafeContracts Settings in WordPress may expose:

- System name
- Logo
- Mobile logo
- App icon reference
- Primary/secondary colors
- Login background
- Header/footer text
- Support/contact text

Mobile receives appropriate configurable identity/content values from WordPress APIs. Security-critical layout/behavior is not remotely arbitrary.
