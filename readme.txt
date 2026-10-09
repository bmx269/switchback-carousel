=== Switchback Carousel ===
Contributors: bmx269
Tags: carousel, slider, slideshow, posts carousel, blocks
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Carousel blocks for WordPress. Build slides by hand, or turn any Query Loop into a carousel. Works with Advanced Query Loop.

== Description ==

Switchback Carousel adds carousel blocks built from parts you arrange freely in the editor: the **track** that moves, the **slides**, and **controls** that can sit anywhere inside the carousel, even inside a slide. Controls include previous/next arrows, pagination, a progress bar, a slide counter and a play/pause button.

**[Try it in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/bmx269/switchback-carousel/main/blueprint.json)**. No installation needed.

= Hand-built or from your posts =

Build slides yourself with any blocks. For posts, select a **Query Loop** and turn on **Display as carousel**: every post becomes a slide, designed once in the Post Template. Post Title, Featured Image, Excerpt, Date and anything else you'd put in a Query Loop work as usual, and the editor shows the posts as a row of slides while you design.

= Works with Advanced Query Loop =

Need more than core's Query Loop filters? Install [Advanced Query Loop](https://wordpress.org/plugins/advanced-query-loop/) and filter by post meta and SCF/ACF fields, dates, multiple post types, specific posts and more. Switchback handles the display and AQL handles the query, both on the same block.

In AQL's meta and date fields, use relative values like `{today}`, `{today+7}` or `{today_ymd}` for "upcoming" carousels that never go stale.

= Settings =

* Slides side by side, slides per move and a peek at the next slide, set separately for desktop, tablet and phones by switching the editor's device preview
* Fluid columns for post carousels: set a minimum slide width and fit as many slides as the screen allows
* Slides aligned from the start or centered; narrowest and widest slide width
* Slide or fade; stop, rewind or keep going at the last slide
* Snap to slides or glide freely; mouse wheel and trackpad scrolling
* Step-by-step or continuous autoplay, with pause on hover
* Let slides show past the edges for full-bleed layouts, or fade the edges
* Linked layout: rearrange or restyle one slide and the others follow, while each keeps its own content
* Five starter designs: feature cards, quote rotator, latest posts, logo marquee and a gallery strip

= Accessible by default =

* Carousel and slide semantics with labelled slides ("3 of 8") and live announcements
* Arrow-key navigation on the focused carousel
* Real buttons with disabled states at the ends, and a play/pause control for autoplay
* Autoplay pauses while a visitor focuses the carousel and stays off for visitors who prefer reduced motion
* Without JavaScript, slides remain a native scrollable row instead of disappearing

= For developers =

* Post carousels are ordinary Query Loops: change their queries with `query_loop_block_query_vars` or AQL's `aql_query_vars`
* Saved content contains no slider-library class names, so the markup stays yours
* `window.switchback.mount()`, `unmount()` and `get()` for carousels added to the page after load

= Source code =

The full, human-readable source of the editor and front-end scripts is included in the plugin's `src` folder, with the build configuration in `package.json` (built with `@wordpress/scripts`). Development happens at [github.com/bmx269/switchback-carousel](https://github.com/bmx269/switchback-carousel).

== Installation ==

1. In your dashboard go to **Plugins → Add New**, search for "Switchback Carousel", then install and activate it. Or upload the plugin folder to `/wp-content/plugins/` and activate it on the Plugins screen.
2. In the editor, add a **Switchback Carousel** block, or pick one of the carousel designs from the pattern inserter.
3. Select the **Carousel Track** to set layout, motion and autoplay for hand-built slides, or select a **Query Loop** and turn on **Display as carousel** for posts.

== Frequently Asked Questions ==

= How do I make a carousel of posts or a custom post type? =

Start from the **Latest posts** pattern, or put a Query Loop inside a Switchback Carousel block. Select the Query Loop, turn on **Carousel display → Display as carousel**, and choose the post type and filters in the Query Loop's usual settings. Design one post in the Post Template and every post uses that design.

= Does it work with Advanced Query Loop? =

Yes. Insert an Advanced Query Loop block, set up the query, then turn on **Display as carousel** in the same block's sidebar. AQL handles which posts appear and Switchback handles how they're shown. AQL is optional, and plain Query Loops work too.

= Can I filter by a Secure Custom Fields or ACF field? =

Yes, with Advanced Query Loop's meta query controls. SCF/ACF date pickers store dates as `Ymd`, so compare them with the `{today_ymd}` token. For example, "`event_date` is greater than or equal to `{today_ymd}`" shows upcoming events.

= Where do the arrows and dots go? =

Anywhere inside the carousel. Controls are their own block, so you can place them above or below the slides, add several, or put them inside a slide. Turn on **Arrows over the slides** on the Switchback Carousel block to float the arrows on either side.

= Does it work without JavaScript? =

Yes. Without JavaScript the slides show as a row you can scroll sideways, so no content is hidden.

= Is it accessible? =

The carousel follows the WAI-ARIA carousel pattern through Splide: slides are labelled, the carousel responds to arrow keys when focused, controls are real buttons, autoplay pauses on focus and for reduced-motion users, and a play/pause control is available.

== Screenshots ==

1. A posts carousel on the front end, with arrows over the slides.
2. Designing a carousel from posts: edit the first slide while the editor previews your real posts.
3. Filtering posts by field values from registered meta and SCF/ACF fields.
4. Feature cards and a fading quote rotator with pagination and a pause button.
5. Controls: arrows in seven icons and five weights, pagination, progress, counter and play/pause.
6. Layout per device: slides side by side, slides per move and a peek at the next slide.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Credits ==

* Sliding powered by [Splide](https://splidejs.com/) and the Splide Auto Scroll extension, © Naotoshi Fujita, MIT license.
* Control icons from [Phosphor Icons](https://phosphoricons.com/), MIT license.
