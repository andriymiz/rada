---
paths:
  - app/Filament/**
  - app/Providers/Filament/**
  - resources/css/filament/**
---

# Filament Styling

- Keep shared Filament styles and design tokens in `resources/css/filament/rada/theme.css`, registered by `RadaPanelProvider`.
- Prefer Filament's documented CSS hooks when customizing component styles. Check the available hooks for the target component and place shared overrides in the Filament theme.
- Use Tailwind theme tokens for shared design values such as colors, fonts, and radii.
- Avoid selectors tied to page-specific markup or undocumented internal DOM structure when a CSS hook is available.
- Do not add page-specific body classes or page-scoped CSS overrides for styles that should be shared. Use page-specific styling only for intentional design differences.
