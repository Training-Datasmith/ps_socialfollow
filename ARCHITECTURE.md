# Architecture: ps_socialfollow

## Purpose

A PrestaShop front-office widget module that displays social network follow buttons (Facebook, Twitter, Instagram, YouTube, etc.) configured by the merchant in the back office.

## Directory Structure

```
ps_socialfollow.php    - Module class; config management, hook listeners, widget rendering
assets/icon/           - Social network icon assets
upgrade/               - SQL/PHP migration scripts
tests/                 - PHPUnit test stubs and PHPStan bootstrap
translations/          - Locale string overrides
```

## Key Design Decisions

- **WidgetInterface**: Implements `WidgetInterface` for theme-editor positioning.
- **Configuration-driven**: Each social network URL is stored as a `Configuration` key (`PSSOCIALFOLLOW_FACEBOOK_URL`, etc.) and edited via the module config form.
- **No JavaScript dependency**: Outputs plain anchor tags styled as buttons — no third-party SDK is loaded.

## Extension Points

- Add new social networks by adding new `Configuration` keys and updating both `getContent()` and `getWidgetVariables()`.

## Dependency Flow

```
ps_socialfollow (Module + WidgetInterface)
  └─> renderWidget()          — renders the follow buttons template
        └─> getWidgetVariables() — reads URLs from Configuration
  └─> getContent()            — admin config form for each social URL
```
