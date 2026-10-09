# Switchback Carousel

Carousel blocks for WordPress. Build slides by hand, or turn any Query Loop into a carousel, including one powered by [Advanced Query Loop](https://wordpress.org/plugins/advanced-query-loop/). Sliding is powered by [Splide](https://splidejs.com/).

## Blocks

| Block | What it does |
|---|---|
| `switchback-carousel/carousel` | The wrapper. Holds the slides plus controls, headings, buttons or anything else. Can float the arrows over the slides. |
| `switchback-carousel/track` | The moving part for hand-built slides. Layout, motion, autoplay and edge settings live here. |
| `switchback-carousel/slide` | One hand-built slide. |
| `switchback-carousel/controls` | Previous/next arrows, pagination (dots, bars or segments), a progress bar, a slide counter, or a play/pause button. Put it anywhere inside the carousel, including inside a slide. Colour and alignment use core's own controls. |

Post carousels don't need a block of their own. Switchback adds a **Display as carousel** setting to the core Query Loop block (see [Carousels from posts](#carousels-from-posts)).

Saved content never contains Splide class names. The front-end script adds them when it mounts, so content doesn't depend on the slider library.

## Settings

- **Layout per device**: slides side by side, slides per move, and a peek at the next slide (%) for desktop, tablet (≤781px) and phones (≤480px), set by switching the editor's device preview. Tablet falls back to desktop; phones fall back to one slide. Narrowest/widest slide width, and slides aligned from the start or centered.
- **Motion**: slide or fade, transition time, what happens at the last slide (stop, rewind, endless), dragging (snap, glide freely, off), mouse wheel/trackpad, hand cursor, direction (site language, LTR, RTL).
- **Autoplay**: step by step or a continuous flow; pause on hover. Always pauses while a visitor focuses the carousel and for visitors who prefer reduced motion.
- **Edges**: let slides show past the track edge (for full-bleed layouts), fade both edges or the trailing edge.
- **Linked layout**: rearranging or restyling one hand-built slide updates the others while each keeps its content. Each slide also has **Copy layout to other slides**.
- **Accessibility**: Splide's carousel semantics and live announcements, labelled slides ("3 of 8"), a focusable track that responds to arrow keys, disabled states at the ends, a real play/pause control, and visible focus styles.
- **Without JavaScript** the slides remain a native horizontally scrolling row.

## Carousels from posts

Any Query Loop block can render as a carousel. WordPress picks the posts and Switchback handles the display:

1. Add a **Switchback Carousel** block and replace its track with a **Query Loop**, or start from the **Latest posts** pattern, which is already set up this way.
2. Select the Query Loop and turn on **Carousel display → Display as carousel**.
3. Design one post inside the Post Template. Every post becomes a slide.
4. Add **Carousel Controls** anywhere inside the Switchback Carousel for arrows, pagination and the rest.

The **Carousel display** panel on the Query Loop offers:

- **Columns** for desktop, tablet and mobile, or **Fluid columns**: set a minimum slide width and the carousel fits as many slides as the viewport allows, with no fixed breakpoints.
- **At the end**: stop, rewind or endless loop.
- **Dragging**: snap, glide freely or off.
- **Autoplay**: step by step or continuous, with an interval.
- **Edges**: let slides spill past the edge, or fade the trailing or both edges.

The gap between slides comes from the Query Loop's **Block spacing** setting.

The **Switchback Carousel (Query)** variation in the inserter is a shortcut for steps 1 and 2 with plain core queries.

### How it works

Switchback stores its settings in a `switchback` attribute on `core/query` and reads them in a `render_block_core/query` filter. The filter adds Splide's track markup and a `data-switchback` options blob around the rendered `core/post-template` list, then enqueues the same front-end script that hand-built tracks use. Switchback only changes the HTML after it is rendered. It doesn't touch the query, so anything that changes the query keeps working.

Carousel display switches on with `switchback.enabled`, not with the block's `namespace`. That matters for Advanced Query Loop, which identifies its blocks by `namespace: "advanced-query-loop"`. Because of this, a single Query Loop can be an AQL block and a Switchback carousel at the same time.

In the editor, the preview is a horizontal row styled with the same column and gap variables the front end uses. Splide only mounts on the front end.

### Advanced Query Loop

[Advanced Query Loop](https://wordpress.org/plugins/advanced-query-loop/) (AQL) is the recommended way to filter beyond what core's Query Loop offers: post meta and SCF/ACF field queries, date queries, multiple post types, including or excluding posts by ID, child posts, sorting by meta, and more. Switchback doesn't depend on AQL. Everything works on a plain Query Loop, and AQL adds query options on top.

To use them together:

1. Insert an **Advanced Query Loop** block inside a Switchback Carousel and set up the query with AQL's controls.
2. In the same block's sidebar, turn on **Carousel display → Display as carousel**.

The **Latest posts** pattern saves its Query Loop with AQL's namespace and carousel display on. With AQL active, AQL picks it up. Without AQL, it renders as a normal core Query Loop.

Start from an Advanced Query Loop block rather than the **Switchback Carousel (Query)** variation. The variation sets Switchback's own namespace, so AQL's controls won't appear on it.

### Relative dates and tokens

Switchback expands tokens in the final query arguments of every Query Loop. It hooks `query_loop_block_query_vars` for core and `aql_query_vars` for AQL, both at priority 20, after AQL has built its arguments. You can type relative values into AQL's meta and date fields instead of fixed dates. For example, a meta query of "`event_date` is at least `{today_ymd}`" makes an upcoming-events carousel that never goes stale.

| Token | Becomes |
|---|---|
| `{today}`, `{today+7}`, `{today-30}` | `2026-09-26` |
| `{today_ymd}` | `20260926`. SCF/ACF date pickers store dates this way. |
| `{now}` | `2026-09-26 14:05:00` |
| `{current_post_id}` | ID of the post being viewed |
| `{current_user_id}` | ID of the logged-in user |

Date tokens take a day offset. Tokens expand in any Query Loop, not only in carousels.

## Extending

### JavaScript

`window.switchback.mount( scope )` mounts carousels added to the page later (carousels inserted into the DOM are picked up automatically), `window.switchback.unmount( node )` tears them down, and `window.switchback.get( trackElement )` returns the Splide instance. Query Loop carousels count as tracks: the `.wp-block-query` element is the track.

### Changing queries

Post carousels are ordinary Query Loops, so use WordPress's `query_loop_block_query_vars` filter, or AQL's `aql_query_vars`, to change the query.

## Development

```bash
nvm use
npm install
npm run start        # watch
npm run build        # icons + production build
npm run build:icons  # regenerate includes/icons.json from @phosphor-icons/core
npm run test:unit
npm run lint:js
npm run lint:css
npm run plugin-zip   # distributable zip (honours .distignore)
```

Requires WordPress 6.7+ and PHP 8.0+.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) and [CREDITS.md](CREDITS.md).
