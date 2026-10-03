# Nexora Design System

Design reference for the Nexora fintech dashboard. Visual direction: a dark, layered dashboard with a single orange accent, based on the "Fintix" reference. Every token has a dark and a light value. Components only use semantic tokens, so switching themes is just swapping the token set.

---

## 1. Principles

1. **One accent, used sparingly.** Orange is for primary actions, the active nav item, the highlighted data point and positive emphasis. Everything else stays neutral.
2. **Depth through surfaces, not shadows.** Hierarchy comes from stepped surface colors and 1px hairline borders. Shadows are subtle and mostly used in light mode.
3. **Numbers first.** Monetary values are the largest, heaviest text on each card. Labels sit above them, small and muted.
4. **Dense but calm.** Dashboard density, with consistent spacing and rhythm so nothing feels crowded.
5. **Glow means focus.** A soft orange glow marks the single most important point (the active chart bar, the active nav item). Use at most one per view.

---

## 2. Color Tokens

### 2.1 Brand

| Token | Value | Use |
|---|---|---|
| `--brand-500` | `#FF6B1A` | Primary buttons, active bar, accent dot |
| `--brand-400` | `#FF8A4C` | Hover on primary, gradient top |
| `--brand-600` | `#E85A0C` | Pressed state, gradient bottom |
| `--brand-300` | `#FFB088` | Secondary segment in spending bar |
| `--brand-200` | `#FFD4BC` | Tertiary segment |
| `--brand-glow` | `rgba(255,107,26,0.45)` | Glow / blur behind active elements |

The brand palette is the same in both themes. Only its surroundings change.

### 2.2 Semantic tokens: dark (default)

| Token | Value | Use |
|---|---|---|
| `--bg-canvas` | `#0A0A0A` | App background |
| `--bg-surface-1` | `#121212` | Sidebar, main panels |
| `--bg-surface-2` | `#181818` | Cards (stats, chart, table) |
| `--bg-surface-3` | `#202020` | Inputs, segmented controls, table header |
| `--bg-hover` | `#262626` | Row / item hover |
| `--border-subtle` | `#1F1F1F` | Card outlines, dividers |
| `--border-default` | `#2A2A2A` | Inputs, outline buttons |
| `--border-strong` | `#3A3A3A` | Focused/selected neutral controls |
| `--text-primary` | `#F5F5F5` | Values, headings |
| `--text-secondary` | `#A3A3A3` | Labels, nav items |
| `--text-tertiary` | `#6B6B6B` | Axis labels, section headings, placeholders |
| `--text-on-brand` | `#FFFFFF` | Text on orange |
| `--chart-bar` | `#2A2A2A` → `#1C1C1C` | Inactive bar gradient (top → bottom) |
| `--chart-grid` | `#1F1F1F` | Gridlines (dashed) |

### 2.3 Semantic tokens: light

| Token | Value | Use |
|---|---|---|
| `--bg-canvas` | `#F4F4F5` | App background |
| `--bg-surface-1` | `#FFFFFF` | Sidebar, main panels |
| `--bg-surface-2` | `#FFFFFF` | Cards (separated from canvas by border + shadow) |
| `--bg-surface-3` | `#F4F4F5` | Inputs, segmented controls, table header |
| `--bg-hover` | `#EDEDEF` | Row / item hover |
| `--border-subtle` | `#ECECEE` | Card outlines, dividers |
| `--border-default` | `#E1E1E4` | Inputs, outline buttons |
| `--border-strong` | `#C9C9CE` | Focused/selected neutral controls |
| `--text-primary` | `#111111` | Values, headings |
| `--text-secondary` | `#5A5A60` | Labels, nav items |
| `--text-tertiary` | `#9A9AA0` | Axis labels, section headings, placeholders |
| `--text-on-brand` | `#FFFFFF` | Text on orange |
| `--chart-bar` | `#E8E8EB` → `#F2F2F4` | Inactive bar gradient |
| `--chart-grid` | `#EDEDEF` | Gridlines (dashed) |

In light mode, `--brand-glow` drops to `rgba(255,107,26,0.25)` so it doesn't look washed out on white.

### 2.4 Status colors

Pills use a tinted background, a 1px tinted border and a colored label.

| Status | Dark: bg / border / text | Light: bg / border / text |
|---|---|---|
| Success (Received) | `#0F2A17` / `#1F5A30` / `#4ADE80` | `#ECFDF3` / `#ABEFC6` / `#067647` |
| Danger (Failed) | `#2A1010` / `#5A1F1F` / `#F87171` | `#FEF3F2` / `#FECDCA` / `#B42318` |
| Warning (Processed / Pending) | `#2A2610` / `#5A521F` / `#E5D36B` | `#FEFBE8` / `#FEEE95` / `#A15C07` |
| Neutral (Scheduled) | `#1F1F1F` / `#333333` / `#A3A3A3` | `#F4F4F5` / `#E1E1E4` / `#5A5A60` |
| Positive delta (+8%) | `#1E1A14` / `#3D3020` / `#E8C9A0` | `#FFF4EC` / `#FFD9C2` / `#C2410C` |

Never use color alone to show status. Every pill also has a text label.

### 2.5 Data visualization palette

For categorical charts (spending breakdowns, legends), in order:

1. `--brand-500` `#FF6B1A`
2. `--brand-300` `#FFB088`
3. `--brand-200` `#FFD4BC`
4. Neutral: dark `#F5F5F5` / light `#3F3F46`

In single-series bar charts, only the focused or current bar gets the brand gradient. All other bars use `--chart-bar`.

---

## 3. Typography

**Font:** `Inter` (fallback: `system-ui, -apple-system, Segoe UI, sans-serif`). Turn on tabular numbers (`font-variant-numeric: tabular-nums`) for every monetary value, table cell and chart label.

| Role | Size / Line height | Weight | Tracking | Example |
|---|---|---|---|---|
| Display value | 28 / 34 | 600 | -0.02em | `$19,270.56` (cash flow) |
| Stat value | 24 / 30 | 600 | -0.02em | Stat card values |
| Page title | 16 / 24 | 600 | -0.01em | "Dashboard" |
| Card title | 14 / 20 | 500 | 0 | "Quick Action", "Daily Limit" |
| Body | 14 / 20 | 400 | 0 | Table cells, nav items |
| Label | 13 / 18 | 400 | 0 | "Total Revenue", button text |
| Caption | 12 / 16 | 400 | 0 | Dates, legends, card expiry |
| Overline | 11 / 16 | 500 | 0.06em, uppercase | "MAIN", "FEATURES", "TOOLS" |

Rules:
- Labels go above values and use `--text-secondary`.
- Secondary qualifiers sit next to a value (e.g. "from $2,000 limit") in Caption size with `--text-tertiary`.
- Never use a weight above 600.

---

## 4. Spacing, Radius, Elevation

### Spacing scale (4px base)
`4, 8, 12, 16, 20, 24, 32, 40, 48`

- Card padding: `16` (compact) / `20` (default)
- Gap between cards: `12`
- Section gap inside a card: `16`
- Sidebar item padding: `8 12`
- Table row height: `44`

### Radius
| Token | Value | Use |
|---|---|---|
| `--radius-sm` | `6px` | Pills, checkboxes, small icon buttons |
| `--radius-md` | `8px` | Buttons, inputs, nav items |
| `--radius-lg` | `12px` | Cards, panels |
| `--radius-xl` | `16px` | Payment card, app shell |
| `--radius-full` | `9999px` | Avatars, status pills, round icon buttons |

### Elevation
| Token | Dark | Light |
|---|---|---|
| `--shadow-card` | `none` (border only) | `0 1px 2px rgba(16,16,20,0.04), 0 1px 3px rgba(16,16,20,0.06)` |
| `--shadow-pop` | `0 8px 24px rgba(0,0,0,0.5)` | `0 8px 24px rgba(16,16,20,0.10)` |
| `--shadow-brand` | `0 4px 16px var(--brand-glow)` | `0 4px 12px var(--brand-glow)` |

Every card gets an inset top highlight in dark mode: `inset 0 1px 0 rgba(255,255,255,0.03)`.

---

## 5. Layout

```
┌──────────┬───────────────────────────────────────┬──────────────┐
│ Sidebar  │ Top bar (title · icons · avatar)                     │
│ 240px    ├───────────────────────────────────────┬──────────────┤
│          │ Action row (buttons)                  │ Search       │
│ Nav      │ Stat cards ×3                         │ Card tabs    │
│ groups   │ Cash flow chart                       │ Payment card │
│          │ Transactions table                    │ Quick action │
│ Upgrade  │                                       │ Daily limit  │
│ card     │                                       │ Bills        │
└──────────┴───────────────────────────────────────┴──────────────┘
             main: fluid (min 640)                  rail: 320px
```

- **Breakpoints:** `sm 640`, `md 768`, `lg 1024`, `xl 1280`, `2xl 1536`.
- **≥1280:** sidebar + main + right rail.
- **1024–1279:** the right rail moves under the main content as a 2-column grid.
- **768–1023:** the sidebar collapses to a 72px icon rail.
- **<768:** the sidebar becomes a drawer, stat cards stack vertically, and the table scrolls horizontally inside its card. The page itself never scrolls sideways.

---

## 6. Components

### Buttons
| Variant | Background | Border | Text | Notes |
|---|---|---|---|---|
| Primary | `--brand-500` | none | `--text-on-brand` | Hover `--brand-400`, pressed `--brand-600`, `--shadow-brand` on hover |
| Secondary (outline) | `--bg-surface-2` | `--border-default` | `--text-primary` | Leading icon 16px. Used for "Manage Balance", "Export" |
| Ghost | transparent | none | `--text-secondary` | Used for "Learn More" and table "Export" |
| Icon (round) | `--bg-surface-3` | `--border-default` | `--text-secondary` | 32px, `--radius-full` |

Heights: `32` (sm, default in dashboards), `36` (md), `40` (lg). Text uses the 13px Label style, weight 500.

### Sidebar nav
- Group heading: Overline, `--text-tertiary`, with a chevron to collapse.
- Items are connected by a 1px vertical tree line in `--border-default` on the left.
- **Active item:** a horizontal gradient from `rgba(255,107,26,0.18)` to transparent, plus a faint orange right-edge glow, `--text-primary`, icon tinted `--brand-500`.
- Inactive items: `--text-secondary`. Hover changes the background to `--bg-hover`.

### Stat card
- Surface `--bg-surface-2`, `--radius-lg`, padding 16.
- Label (13, secondary) on top, then the stat value, with a delta pill on the right aligned to the value baseline.

### Delta pill
- `--radius-full`, 4×8 padding, Caption size, positive-delta colors from §2.4. Negative deltas use the Danger colors.

### Segmented control / tabs
- Track `--bg-surface-3`, `--radius-md`, 2px inner padding.
- Selected segment: `--bg-surface-2` with `--border-strong`, `--text-primary`. Unselected: `--text-secondary`.
- An optional status dot (6px, green) inside the selected segment, as in "• Credit".

### Bar chart
- Bars: `--radius-md` on top corners, gap 8px.
- Inactive bars use the `--chart-bar` gradient.
- Active bar: vertical gradient `--brand-400 → --brand-600 → transparent` at the bottom, a small white glowing dot at the top, and a value label above it in Caption, `--text-primary`.
- Y-axis labels: Caption, `--text-tertiary`. Gridlines dashed `--chart-grid`.
- Animation: bars grow from the baseline over 400ms with `cubic-bezier(0.22, 1, 0.36, 1)`, staggered 20ms.

### Data table
- Sits inside a card. The header row uses `--bg-surface-3` with Caption headers in `--text-secondary`.
- Rows: 44px tall, `--border-subtle` divider, hover `--bg-hover`.
- Leading column has a checkbox (16px, `--radius-sm`).
- User cell: 28px avatar + name.
- Amount column is right-aligned and tabular.
- Status column uses status pills (§2.4).

### Payment card (credit/debit visual)
- Aspect ratio 1.586:1, `--radius-xl`.
- **Dark:** a brushed-metal gradient `linear-gradient(135deg, #3A3A3A 0%, #1A1A1A 45%, #2C2C2C 100%)` plus a soft radial highlight in the top-left corner.
- **Light:** `linear-gradient(135deg, #2A2A2E 0%, #111114 100%)`. The card stays dark in light mode on purpose, like a physical card.
- Contents: contactless icon, masked number `**** **** 6541`, expiry, chip, holder label + name, network logo.

### Spending limit bar
- A 6px segmented bar with 4px gaps between segments, each segment `--radius-sm`, colored with the dataviz palette (§2.5).
- Legend underneath: 8px square swatch, Caption label with the percentage.

### Upgrade / promo card
- Background: a radial orange gradient from the top-left (`rgba(255,107,26,0.35)`) over `--bg-surface-2`, with a `--border-default` outline.
- Primary button (sm) + Ghost "Learn More". A dismiss icon in the top-right corner.

### Inputs / search
- Height 36, `--bg-surface-3`, `--border-default`, `--radius-md`, leading 16px search icon in `--text-tertiary`.
- Focus: border `--brand-500` + `0 0 0 3px rgba(255,107,26,0.2)` ring.

### List item (bills)
- Surface `--bg-surface-3`, `--radius-md`, 40px brand logo tile, title (Body 500) + date (Caption tertiary), chevron on the right. The footer row shows the amount and a status pill.

---

## 7. Iconography

- **Set:** Lucide (or a similar 1.5px-stroke outline set).
- **Sizes:** 16px inline / in buttons, 20px in the nav, 24px for standalone icons.
- **Color:** inherits `currentColor`. Icons are `--text-secondary` by default, and `--brand-500` only in the active nav state.
- No filled icons, except logos and the payment card chip.

---

## 8. Motion

| Token | Value | Use |
|---|---|---|
| `--ease-out` | `cubic-bezier(0.22, 1, 0.36, 1)` | Enters, chart growth |
| `--ease-in-out` | `cubic-bezier(0.65, 0, 0.35, 1)` | Theme / layout changes |
| `--dur-fast` | `120ms` | Hover, press |
| `--dur-base` | `200ms` | Tabs, dropdowns, toggles |
| `--dur-slow` | `400ms` | Chart entry, drawer |

- The theme switch crossfades background and text colors over `--dur-base`. Shadows and gradients are not animated.
- Respect `prefers-reduced-motion`: turn off the chart growth and stagger and keep the opacity fades.

---

## 9. Theming Implementation

Theme follows the system by default, with a manual override stored per user.

```css
:root,
:root[data-theme="dark"] {
  color-scheme: dark;
  --bg-canvas: #0A0A0A;
  --bg-surface-1: #121212;
  --bg-surface-2: #181818;
  --bg-surface-3: #202020;
  --bg-hover: #262626;
  --border-subtle: #1F1F1F;
  --border-default: #2A2A2A;
  --border-strong: #3A3A3A;
  --text-primary: #F5F5F5;
  --text-secondary: #A3A3A3;
  --text-tertiary: #6B6B6B;
  --brand-glow: rgba(255, 107, 26, 0.45);
}

:root[data-theme="light"] {
  color-scheme: light;
  --bg-canvas: #F4F4F5;
  --bg-surface-1: #FFFFFF;
  --bg-surface-2: #FFFFFF;
  --bg-surface-3: #F4F4F5;
  --bg-hover: #EDEDEF;
  --border-subtle: #ECECEE;
  --border-default: #E1E1E4;
  --border-strong: #C9C9CE;
  --text-primary: #111111;
  --text-secondary: #5A5A60;
  --text-tertiary: #9A9AA0;
  --brand-glow: rgba(255, 107, 26, 0.25);
}

@media (prefers-color-scheme: light) {
  :root:not([data-theme]) {
    /* same values as [data-theme="light"] */
  }
}

:root {
  --brand-200: #FFD4BC;
  --brand-300: #FFB088;
  --brand-400: #FF8A4C;
  --brand-500: #FF6B1A;
  --brand-600: #E85A0C;
  --text-on-brand: #FFFFFF;
}
```

Notes:
- Dark is the default and the brand-defining theme. Design screens in dark first, then check them in light.
- Set `data-theme` on `<html>` before first paint (an inline script reading the saved preference) so the page doesn't flash the wrong theme.
- If the project uses Tailwind, map these variables in `theme.extend.colors` (e.g. `canvas: 'var(--bg-canvas)'`) and don't use `dark:` variants for colors. The tokens already handle the switch.

---

## 10. Accessibility

- Text contrast is at least 4.5:1 for body and labels, and at least 3:1 for large values and UI boundaries, in both themes. `--text-tertiary` is only for non-essential text (axis labels, placeholders).
- `--brand-500` on `#FFFFFF` is below 4.5:1, so in light mode orange is never used as body text color. Use `#C2410C` for orange text on light surfaces.
- Focus ring is always visible: 2px `--brand-500` outline with a 2px offset.
- Charts have an accessible table or `aria-label` summary. The highlighted bar's value is announced.
- Hit targets are at least 32px in dense dashboard areas and at least 44px on touch layouts.

---

## 11. Imagery

- Avatars: 28px in tables, 32px in the top bar, `--radius-full`, 1px `--border-default` ring.
- Marketing and empty-state photography comes from the Unsplash API (hotlinked URLs, `ixid` kept intact, photographer credited). Use dark, low-saturation shots that work with the neutral UI. Request sized versions via `urls.raw` + `w`/`dpr` params.
- Merchant/brand logos (e.g. in bills) go on a 40px tile with `--radius-md`.

---

## 12. Do / Don't

**Do**
- Keep a single orange focal point per section.
- Put stats in a row of equal-width cards.
- Show every monetary value with currency and two decimals, using tabular numbers.

**Don't**
- Introduce a second accent color (blue, purple) for UI chrome.
- Use pure `#000000` backgrounds or pure `#FFFFFF` text in dark mode.
- Stack cards inside cards more than one level deep.
- Use heavy drop shadows in dark mode.
