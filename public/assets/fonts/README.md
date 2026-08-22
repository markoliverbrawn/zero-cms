# Self-hosted Web Fonts

Every typeface the platform uses is served from this directory rather than a third-party CDN. That
keeps rendering independent of an external service being reachable, avoids leaking visitor IP
addresses to one, and removes a cross-origin connection from the critical path.

Declare a font with `@font-face` in one of two places:

- **`assets/css/fonts.css`** — typefaces available to every theme (Inter, JetBrains Mono, Material
  Symbols). Bundled first, so a theme can still override which family a selector uses.
- **A theme's own stylesheet** — a typeface belonging to a single theme. `@font-face` is valid at
  any position in a stylesheet, so it works there, and it keeps the font with the theme that wants
  it (including a theme registered from outside this repo via `App::registerThemePath()`).

Never use `@import`. The compiled bundle concatenates raw stylesheets and opens with `@font-face`
declarations; CSS requires `@import` to precede all other rules, so an imported stylesheet arriving
later is invalid and silently discarded by the browser. A font referenced that way will appear to
be configured and never load.

## Licences

| File(s) | Family | Licence |
| :--- | :--- | :--- |
| `inter-*.woff2` | Inter | SIL Open Font License 1.1 |
| `jetbrains-mono-*.woff2` | JetBrains Mono | SIL Open Font License 1.1 |
| `material-symbols-outlined-*.woff2` | Material Symbols | Apache License 2.0 |

The SIL Open Font License requires that its copyright and licence notice be retained with any
redistribution of the font files, which is what this file records. It permits bundling in a
commercial product; it does not permit selling the fonts on their own, and a modified derivative may
not use the original Reserved Font Name. Full text: <https://openfontlicense.org/>

## Adding a font

Prefer a **variable font**: one file spanning a whole weight axis, declared with a range descriptor
such as `font-weight: 200 800`. A stylesheet may then request any weight in that range and the
browser downloads exactly one file.

Ship each subset separately with its own `unicode-range`, so a browser fetches only what the page
actually contains. `latin-ext` is not optional for this platform: Croatian (`č ć ž š đ`) and Māori
(`ā ē ī ō ū`) both sit outside the basic latin range, and both are supported languages.

> **Known issue.** The `inter-*` files are four byte-identical
> copies of one variable font, as are the three `jetbrains-mono-*` files, each declared with a
> single static `font-weight`. A page using two weights of Inter therefore downloads the same 48 KB
> twice under different names. Collapsing each family to one file with a range descriptor — the
> pattern described above — would fix that. Those families also ship no `unicode-range`
> at all, so it is unclear whether their subset covers Croatian and Māori diacritics.
