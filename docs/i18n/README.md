# i18n — README translations

**Languages:** [**English**](en/README.md) | [简体中文](../../README.zh-CN.md) | [한국어](ko/README.md) | [Русский](ru/README.md) | [Deutsch](de/README.md) | [Français](fr/README.md) | [Español](es/README.md) | [Português](pt/README.md) | [हिन्दी](hi/README.md) | [العربية](ar/README.md) | [বাংলা](bn/README.md) | [Bahasa Indonesia](id/README.md) | [日本語](ja/README.md)

Translated copies of the [project README](../../README.md), one folder per language. Each folder carries its own localised copies of the design diagrams, so the pictures are translated too — not just the prose.

| Language | Code | README | Localised diagrams |
|----------|------|--------|--------------------|
| English | `en` | [README.md](en/README.md) (mirror of the root README) | shared: [`docs/*.svg`](../) |
| 简体中文 | `zh-CN` | [README.zh-CN.md](../../README.zh-CN.md) | shared: [`docs/*.svg`](../) |
| 한국어 | `ko` | [README.md](ko/README.md) | `ko/*.svg` |
| Русский | `ru` | [README.md](ru/README.md) | `ru/*.svg` |
| Deutsch | `de` | [README.md](de/README.md) | `de/*.svg` |
| Français | `fr` | [README.md](fr/README.md) | `fr/*.svg` |
| Español | `es` | [README.md](es/README.md) | `es/*.svg` |
| Português | `pt` | [README.md](pt/README.md) | `pt/*.svg` |
| हिन्दी | `hi` | [README.md](hi/README.md) | `hi/*.svg` |
| العربية | `ar` | [README.md](ar/README.md) | `ar/*.svg` |
| বাংলা | `bn` | [README.md](bn/README.md) | `bn/*.svg` |
| Bahasa Indonesia | `id` | [README.md](id/README.md) | `id/*.svg` |
| 日本語 | `ja` | [README.md](ja/README.md) | `ja/*.svg` |

## How the translated diagrams are built

Every diagram is generated from the English original in [`docs/`](../) plus a label dictionary, so a changed diagram can be re-translated with one command per language:

```
docs/i18n/labels/en.json      ← master dictionary: "translate" holds prose, "keep" holds identifiers
docs/i18n/labels/<lang>.json  ← same keys, translated values
scripts/i18n-build-svg.php    ← renders docs/*.svg → docs/i18n/<lang>/*.svg
```

```bash
php scripts/i18n-build-svg.php <lang>            # build docs/i18n/<lang>/*.svg, report leftovers
php scripts/i18n-build-svg.php <lang> --check    # report only, write nothing
php scripts/i18n-build-svg.php --extract         # dump every text node of the templates
```

The script never touches class names, method names, algorithm identifiers or code snippets — those are listed under `keep` and copied verbatim. When a translation is wider than the English original, the font size is scaled down automatically to keep the label inside its box; the build reports labels that would end up cramped so they can be reworded.

Diagram titles and card headers live in wide-open space, so their size is pinned in the `"font_size"` block of `labels/en.json` — that block is layout, not language, and is read for every language (a language file may override single entries). It keeps headers the same size across languages, where word length differs a lot (compare *Encrypt* with *Verschlüsseln*).

## Adding a language

1. `cp docs/i18n/labels/en.json docs/i18n/labels/<lang>.json` and translate the values in `"translate"` (keys and the `"keep"` list stay as they are).
2. `php scripts/i18n-build-svg.php <lang>` — it writes `docs/i18n/<lang>/*.svg` and reports anything untranslated or too long.
3. `mkdir docs/i18n/<lang>` is done by the script; copy `en/README.md` there, translate the prose, keep the code blocks, identifiers and links, and point the images at `./mascot.svg`, `./architecture-design.svg`, `./functional-design.svg`, `./lifecycle.svg`.
4. Add the language to the switcher line at the top of every README and to the table above.

Rendered SVGs are generated artifacts — edit the dictionaries, not the localised SVGs.
