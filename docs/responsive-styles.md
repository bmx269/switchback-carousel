# Moving per-device layout to core responsive styles

Switchback lets each carousel set **slides side by side**, **slides per move** and
**peek** separately for desktop, tablet and phones. Today those values live in our
own attribute and follow the editor's device preview. WordPress 7.1 added
responsive style states, which store per-viewport values on a block's `style`
attribute. Once third-party blocks get a public API for them, our layout should move
there, so it uses the same viewport switcher, breakpoints and storage as every core
block.

The groundwork is in place and switched off. This doc explains how it works and
how to switch over.

## Where the API stands

| Piece | Status (Nov 2026) |
| --- | --- |
| Responsive style states in core | Shipped in WordPress 7.1 |
| `style["@tablet"]` / `style["@mobile"]` storage | Shipped; Gallery stores its own `columns` there |
| Theme breakpoints, `settings.viewport` | Shipped; defaults `mobile: 480px`, `tablet: 782px` |
| `InspectorControls group="viewport"` slot | Merged in Gutenberg [#82003](https://github.com/WordPress/gutenberg/pull/82003) |
| Public `useBlockStyleState` and helpers | Planned in [#80388](https://github.com/WordPress/gutenberg/issues/80388), still private |
| Block supports opt-in for style states | Planned in [#85243](https://github.com/WordPress/gutenberg/issues/85243) |
| Device preview widths separate from breakpoints | [#84535](https://github.com/WordPress/gutenberg/issues/84535) |

Switch over once `useBlockStyleState` is exported from `@wordpress/block-editor`
in a stable WordPress release.

## Storage shapes

Legacy (written today):

```json
"layout": {
  "desktop": { "columns": 3, "step": 1, "peek": 0 },
  "tablet":  { "columns": 2 },
  "mobile":  { "columns": 1, "step": 1, "peek": 0 }
}
```

Core responsive styles (written after the switch), following core's Gallery block:

```json
"layout": {
  "desktop": { "columns": 3, "step": 1, "peek": 0 }
},
"style": {
  "@tablet": { "columns": 2 },
  "@mobile": { "columns": 1, "step": 1, "peek": 0 }
}
```

Gallery stores its per-viewport `columns` directly in `style["@tablet"]` and
`style["@mobile"]`, and keeps its base `columns` in its own attribute. We do the same:
`columns`, `step` and `peek` sit directly in each viewport state, and desktop stays in
`layout.desktop`. A viewport state can also hold core's own styles for that viewport,
such as `spacing`, so writes and the migration only touch our three fields.

On the track block the legacy value is `layout`. On a Query Loop carousel it is
`switchback.layout`, which also holds the non-device `fluid` flag. Desktop and `fluid`
stay there.

Both shapes resolve the same way. Tablet gaps fill from desktop; mobile gaps fill
with one slide, not with desktop.

## How the adapter works

All reads and writes go through two mirrored files:

- `includes/responsive.php`
  - `resolve_device_layouts()`: both render paths and the controls state.
  - `viewport_breakpoints()`: Splide breakpoints.
  - `migrate_*()`: migration.
- `src/shared/responsive.js`
  - `storedLayouts()` and `layoutFor()`: reading.
  - `writeDeviceLayout()`: writing.

`src/shared/device.js` adds `useEditingDevice()`, which tells the controls which
device is being edited.

**Reading** accepts both shapes. When a field is set in both, `style` wins. Content
renders the same before, during and after migration, and nobody has to run the
migration for their site to keep working.

**Writing** goes to one shape, picked by the `switchback_carousel_core_responsive`
filter (default `false`). Each write also clears that device's fields from the other
shape, so an old value can't hide a new one.

**Breakpoints** stay at 781px and 480px while the filter is off. Once it's on, they
come from the theme's `settings.viewport`, the same values core uses for `@tablet`
and `@mobile`.

**The editor** gets the flag as `window.switchbackResponsive.core`. With the flag on:

- `useEditingDevice()` follows core's selected viewport state when
  `useBlockStyleState` is public, and the device preview otherwise.
- The track's layout controls also render in `InspectorControls group="viewport"`.

## Trying it before the switch

On WordPress 7.1 or later, in a test site only:

```php
add_filter( 'switchback_carousel_core_responsive', '__return_true' );
```

New tablet and phone edits then write `style` states. Turning the filter off again is safe: reads
still accept both shapes, and the next edit moves that device back.

## Switch-over checklist

Run `grep -rn "responsive-switch" src includes` to find the code markers.

1. **Confirm the API.** Check that `useBlockStyleState` and the `viewport` inspector
   group are public in a stable release. Update `src/shared/device.js` and the
   `InspectorControls group="viewport"` block in `src/track/inspector.js` if names
   or behaviour changed. If core added a block support opt-in (#85243), add it to
   `src/track/block.json`, and to core/query through the variation's
   `blocks.registerBlockType` filter.
2. **Turn it on.** Change the filter default in `uses_core_responsive()` to `true`.
   Keep the filter, so a site can turn it back off.
3. **Trim the old default.** In `src/track/block.json`, reduce the `layout` default
   to `{ "desktop": { "columns": 3, "step": 1, "peek": 0 } }`. Without a `mobile`
   entry the resolver already falls back to one slide. The migration writes
   tablet and phone values out in full on tracks that relied on the default, so
   those tracks no longer depend on it.
4. **Patterns.** Move the `tablet` and `mobile` entries in `includes/patterns.php`
   to `style["@tablet"]` and `style["@mobile"]`.
5. **No-JS CSS.** The fallback column rules in `src/carousel/style.css` and
   `src/carousel/editor.css` use fixed 781px and 480px media queries. Either print
   per-carousel media queries from `viewport_breakpoints()`, or accept a few pixels
   of difference before JavaScript loads on themes that change the breakpoints.
6. **Release notes.** Add a changelog entry and an upgrade notice. The notice should
   say that carousels keep working as they are, and that editing a carousel saves
   it in the new format.
7. **Optional bulk migration.** Site owners with WP-CLI can rewrite saved content in
   one pass:

   ```sh
   wp switchback migrate-responsive --dry-run
   wp switchback migrate-responsive
   ```

   The command covers posts, pages, synced patterns, templates and template parts in
   the database. It saves through `wp_update_post()`, so revisions keep the previous
   content. Theme files on disk are not rewritten; they keep working through the
   dual reader. Without the filter on, the command refuses to run unless `--force`
   is passed.

## Tests

- JS: `src/shared/test/responsive.test.js`. Covers reading both shapes, resolving,
  and writing in each mode. Also covers clearing a value from the other shape,
  keeping core's own keys in a viewport state, and keeping desktop in `layout`.
- PHP: `tests/phpunit/test-responsive.php`. Covers resolving, migrating (which must
  leave the resolved layout unchanged and do nothing the second time), content
  migration across tracks and Query Loop carousels, and breakpoints.
