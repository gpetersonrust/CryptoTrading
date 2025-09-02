# Apple-Inspired Design Guide for Coin Analysis

## Principles

-   **Clarity first**: One primary action per view; ruthless hierarchy.
-   **Comfortable whitespace**: 8px base spacing, generous paddings.
-   **Quiet chrome, loud content**: Charts and numbers pop; UI recedes.
-   **Delightful but subtle motion**: 120--200ms, eased; never flashy.
-   **Accessibility baked in**: 4.5:1 contrast minimum, keyboard focus
    rings, reduced motion support.

## Design Tokens (CSS Custom Properties)

Drop these in your child theme stylesheet or enqueue a small
`apple-ui.css`. Tweak `--accent` to match your brand if needed.

``` css
:root{
  /* color */
  --accent: #0A84FF;            /* iOS blue */
  --bg: #FBFBFD;                /* near-white */
  --surface: #FFFFFF;           
  --text: #0C0C0D;             
  --muted: #6B7280;             /* slate/gray */
  --border: #E5E7EB;
  /* radius & spacing */
  --radius: 16px;
  --radius-lg: 24px;
  --space-1: 8px; --space-2: 12px; --space-3: 16px;
  --space-4: 24px; --space-5: 32px; --space-6: 48px;
}
```

## Shell & Navigation

Aim for a translucent header with blur and a single-row nav.

``` css
.ast-primary-header-bar{
  backdrop-filter: saturate(180%) blur(20px);
  background: color-mix(in oklab, var(--surface) 80%, transparent);
  border-bottom: 1px solid var(--border);
}
```

## Page Structure (HTML Suggestion)

``` html
<header class="page-hero">
  <div class="hero-inner">
    <h1 class="hero-title">Coin Analysis</h1>
    <p class="hero-sub">Real-time trends, clean insights.</p>
    <div class="hero-actions">
      <button class="btn btn-primary">Add Coin</button>
      <button class="btn btn-ghost">Configure View</button>
    </div>
  </div>
</header>
```

## Motion & States

-   Hover: lift 1--2px, add shadow.
-   Focus: 2px accent ring, offset 2px.
-   Reduced motion supported.

``` css
@media (prefers-reduced-motion: reduce){
  *{ animation:none !important; transition:none !important; }
}
```

## Chart Styling

-   Primary series color = `--accent`
-   Y-axis labels in `--muted`
-   Grid lines faint and minimal
-   Rounded panel containers

## Accessibility Checklist

-   Minimum body text: 16px
-   Tap targets ≥44×44px
-   Visible focus rings
-   Dark mode supported
-   Clear empty state copy
