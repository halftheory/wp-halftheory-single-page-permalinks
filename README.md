# wp-halftheory-single-page-permalinks
Wordpress plugin for dynamically loading posts/pages via Javascript.

Features:
- Automatically creates named anchors for posts (e.g. http://home_url/#post_name).
- Ability to restrict single page functions to specific post types.
- Selection of post loading behaviors slideIn/slideOut top/right/bottom/left.
- Escape key to close post.

Available classes:
- "singlepagepermalinks-post" on content element.
- "singlepagepermalinks-close" on close buttons.

# Custom filters

The following filters are available for plugin/theme customization:
- halftheory_admin_menu_parent
- singlepagepermalinks_admin_menu_parent
- singlepagepermalinks_deactivation
- singlepagepermalinks_uninstall
- singlepagepermalinks_the_content
- singlepagepermalinks_template

<!---
TODO:
- title tag.
- filter links in content.
- back/forward buttons.
- progress wheel.
-->