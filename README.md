# Switchback Carousel

Carousel blocks for the WordPress block editor. Build slides by hand, or let a carousel make one slide per post from any post type, with the first slide as the design. Posts can be filtered by terms, authors, keyword and **core or SCF/ACF field values**. Sliding is powered by [Splide](https://splidejs.com/).

## Blocks

| Block | What it does |
|---|---|
| `switchback-carousel/carousel` | The wrapper. Holds the track plus controls, headings, buttons or anything else. Can float the arrows over the slides. |
| `switchback-carousel/track` | The moving part: hand-built slides, or slides generated from posts. Layout, motion, autoplay and edge settings live here. |
| `switchback-carousel/slide` | One slide. In a query carousel, the first slide is the design repeated for every post. |
| `switchback-carousel/controls` | Previous/next arrows, pagination (dots, bars or segments), a progress bar, a slide counter, or a play/pause button. Put it anywhere inside the carousel, including inside a slide. Colour and alignment use core's own controls. |

Saved content never contains Splide class names; the front-end script adds them when it mounts, so content stays independent of the sliding engine.

## Settings

- **Layout per device**: slides side by side, slides per move, and a peek at the next slide (%) — for desktop, tablet (≤781px) and phones (≤480px), set by switching the editor's device preview. Tablet falls back to desktop; phones fall back to one slide. Narrowest/widest slide width, and slides aligned from the start or centered.
- **Motion**: slide or fade, transition time, what happens at the last slide (stop, rewind, endless), dragging (snap, glide freely, off), mouse wheel/trackpad, hand cursor, direction (site language, LTR, RTL).
- **Autoplay**: step by step or a continuous flow; pause on hover. Always pauses while a visitor focuses the carousel and for visitors who prefer reduced motion.
- **Edges**: let slides show past the track edge (for full-bleed layouts), fade both edges or the trailing edge.
- **Linked layout**: in hand-built tracks, rearranging or restyling one slide updates the others while each keeps its content. Each slide also has **Copy layout to other slides**.
- **Accessibility**: Splide's carousel semantics and live announcements, labelled slides ("3 of 8"), a focusable track that responds to arrow keys, disabled states at the ends, a real play/pause control, and visible focus styles.
- **Without JavaScript** the slides remain a native horizontally scrolling row.

## Slides from posts

Set **Slides → Content** to *From posts*. The first slide becomes the design; add Post Featured Image, Post Title, Post Date, or paragraphs bound to fields, and every post renders through it.

The editor preview comes from `POST /switchback-carousel/v1/preview`, which runs the same PHP as the front end, so what editors see is what visitors get.

### Sources

| Source | Shows |
|---|---|
| **Posts** | Any public post type. Sort by publish date, last updated, title, page order, random, comment count or a field. Narrow by terms, authors, keyword, parent page, sticky posts, leaving out the current post, and field rules. |
| **Related to this post** | Posts sharing a term with the post being viewed. |
| **Relationship field** | The posts picked in an SCF/ACF Relationship or Post Object field (or any field storing post IDs) on the post being viewed, in the order picked. |

### Field rules (core meta and SCF/ACF)

Pick a field from the post type's registered meta and SCF/ACF fields (or type any key), a condition — *is, is not, contains, doesn't contain, is more than, is at least, is less than, is at most, is one of, is none of, is between, is outside, is filled in, is empty* — a value, and how to compare it (text, whole number, decimal, date, date and time, time). Posts can match every rule or at least one.

Values can use tokens, with an optional day offset:

| Token | Becomes |
|---|---|
| `{today}`, `{today+7}` | `2026-09-26` |
| `{today_ymd}` | `20260926` — SCF/ACF date pickers store dates this way |
| `{now}` | `2026-09-26 14:05:00` |
| `{current_post_id}`, `{current_user_id}` | `42` |

Keys starting with an underscore are protected and ignored unless allowed with the `switchback_carousel_field_allowed` filter.

## Extending

### Add a slide source

```php
add_action( 'switchback_carousel_register_sources', function () {
	switchback_carousel_register_source( 'upcoming-events', array(
		'label'       => __( 'Upcoming events', 'my-plugin' ),
		'description' => __( 'Events from today onward, soonest first.', 'my-plugin' ),
		'controls'    => array( 'limit' ), // Editor controls to show; leave out for the general set.
		'callback'    => function ( array $query, int $context_post, ?WP_Block $block ): array {
			return array(
				'post_type'      => 'event',
				'posts_per_page' => $query['limit'],
				'meta_key'       => 'start_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array( array( 'key' => 'start_date', 'compare' => '>=', 'value' => wp_date( 'Ymd' ) ) ),
			);
		},
	) );
} );
```

The callback returns `WP_Query` arguments. Controls: `postType`, `limit`, `skip`, `sort`, `terms`, `authors`, `keyword`, `parent`, `stickies`, `excludeCurrentPost`, `fields`, `relationshipField`.

### Hooks

| Hook | Use |
|---|---|
| `switchback_carousel_query_args( $args, $source, $query, $block )` | Change the `WP_Query` arguments of any query carousel. `$block` is null in editor previews. |
| `switchback_carousel_field_allowed( $allowed, $key )` | Allow or block a meta key in field rules and sorting. |
| `switchback_carousel_fields( $fields, $post_type )` | Change the fields offered in the editor. |

### JavaScript

`window.switchback.mount( scope )` mounts carousels added to the page later (carousels inserted into the DOM are picked up automatically), `window.switchback.unmount( node )` tears them down, and `window.switchback.get( trackElement )` returns the Splide instance.

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
