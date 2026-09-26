=== Switchback Carousel ===
Contributors: bmx269
Tags: carousel, slider, slideshow, posts carousel, blocks
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Carousel blocks with hand-built slides or one slide per post, filtered by terms and core or SCF/ACF fields. Accessible and built on Splide.

== Description ==

Switchback Carousel adds a **Carousel** block built from parts you arrange freely in the editor: the **track** that moves, the **slides**, and **controls** — previous/next arrows, pagination, a progress bar, a slide counter or a play/pause button — that can sit anywhere inside the carousel, even inside a slide.

**[Try it in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/bmx269/switchback-carousel/main/blueprint.json)** — no installation needed.

= Hand-built or from your posts =

Build slides yourself with any blocks, or switch a carousel to **From posts** and it shows one slide per post from any post type. The first slide is the design every post uses, so Post Title, Featured Image, Post Excerpt, Post Date and fields bound to blocks all work inside it. The editor previews your real posts while you design.

= Filter by fields, including SCF and ACF =

Narrow posts by terms, authors, keyword, parent page, sticky posts and **field values** — registered post meta and Secure Custom Fields / ACF fields — with conditions such as *is*, *contains*, *is more than*, *is between*, *is one of* and *is filled in*. Compare as text, numbers, dates or times, and use date tokens like `{today}` or `{today+7}` for "upcoming" carousels. Sort by date, title, page order, random or any field.

Other sources show posts **related** to the one being viewed, or the posts picked in an SCF/ACF **Relationship** field. Developers can register their own sources.

= Settings that make sense =

* Slides side by side, slides per move and a peek at the next slide — separately for desktop, tablet and phones, set by switching the editor's device preview
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

* Register slide sources with `switchback_carousel_register_source()`
* Change any query with the `switchback_carousel_query_args` filter
* Control which fields can be filtered with `switchback_carousel_field_allowed` and `switchback_carousel_fields`
* Saved content contains no slider-library class names, so the markup stays yours

= Source code =

The full, human-readable source of the editor and front-end scripts is included in the plugin's `src` folder, with the build configuration in `package.json` (built with `@wordpress/scripts`). Development happens at [github.com/bmx269/switchback-carousel](https://github.com/bmx269/switchback-carousel).

== Installation ==

1. In your dashboard go to **Plugins → Add New**, search for "Switchback Carousel", then install and activate it. Or upload the plugin folder to `/wp-content/plugins/` and activate it on the Plugins screen.
2. In the editor, add a **Carousel** block, or pick one of the carousel designs from the pattern inserter.
3. Select the **Carousel Track** to choose hand-built slides or slides from posts, and to set layout, motion and autoplay.

== Frequently Asked Questions ==

= How do I make a carousel of posts or a custom post type? =

Select the Carousel Track and set **Slides → Content** to **From posts**. Under **Which posts**, choose the post type, sorting and filters. The first slide becomes the design: add Post Featured Image, Post Title and any other blocks to it, and every post uses that design.

= Can I filter by a Secure Custom Fields or ACF field? =

Yes. Under **Which posts → Narrow it down → Field values**, add a rule, pick the field (fields from SCF/ACF field groups on that post type are listed by name), choose a condition and enter a value. SCF/ACF date pickers store dates as `Ymd`, so compare them with the `{today_ymd}` token, for example "is at least `{today_ymd}`" for upcoming events.

= Can I show the posts chosen in a Relationship field? =

Yes. Choose **Get posts from → Relationship field** and pick the field. The carousel shows the posts picked on the page being viewed, in the order they were picked.

= Where do the arrows and dots go? =

Anywhere inside the carousel. Controls are their own block, so you can place them above or below the slides, add several, or put them inside a slide. Turn on **Arrows over the slides** on the Carousel block to float the arrows on either side.

= Does it work without JavaScript? =

Yes. Without JavaScript the slides show as a row you can scroll sideways, so no content is hidden.

= Is it accessible? =

The carousel follows the WAI-ARIA carousel pattern through Splide: slides are labelled, the carousel responds to arrow keys when focused, controls are real buttons, autoplay pauses on focus and for reduced-motion users, and a play/pause control is available.

= Can I add my own slide source? =

Yes. Call `switchback_carousel_register_source()` on the `switchback_carousel_register_sources` action with a label and a callback that returns `WP_Query` arguments. See the README in the source repository for an example.

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
