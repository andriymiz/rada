# UI styles

Shared Filament styles are defined in [`resources/css/filament/rada/theme.css`](../resources/css/filament/rada/theme.css). Keep the conventions below when changing or adding panel UI.

## Buttons

- Every button has a 2px solid black border. Keeping the border on primary buttons reserves the same space and keeps their dimensions consistent with other buttons.
- Buttons without the `fi-color-primary` class have a transparent background, black text, and black icons.
- Buttons with `fi-color-primary` have a black background and white text.
- On hover or keyboard focus (`:focus-visible`), enabled buttons use the blue-to-green gradient from the `rada-blue-strong` and `rada-green-strong` theme tokens, white text and icons, and a transparent border.
- Disabled buttons retain their normal appearance and do not show the interaction gradient.
- Apply the shared rules to `.fi-btn`; do not make `outlined` buttons a separate style category unless the design requirements change.
- Prefer Tailwind utilities through `@apply`. Avoid `!important` and transitions for these shared button states; use selector specificity and the normal cascade.

## Form fields

- Text inputs use a bottom-only, 2px gray border; focus changes it to black, and validation errors use the danger color.
- Labels and placeholders use gray text; entered values use dark text.
- Remove horizontal padding from text inputs, textareas, and the selected-value control of a select. Keep padding inside the dropdown search field.

