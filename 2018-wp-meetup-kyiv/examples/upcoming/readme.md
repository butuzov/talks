# Example 6 - Complex Example - "Upcoming Events/Movies/Books"
This plugin shows one of the way to show posts (using build in `future` post status) that going to happen in future.

### Example Covers
* Query Vars & Custom `$where` condition filters
* Rewrite Rules map altering
* Links Generation.

### Example Do Not Covers
* How to show such posts on taxonomy selection.
* Support for demo post type from `movies.php` hardcoded in `upcoming.php`.


### Functionality

 * You can register `any public post type (that has archive page)` to support `upcoming` functionality.

  ```php
    // Adding out sample post type plugin.
    add_action( 'init', function() {
      Upcoming::getInstance()->post_type_add( 'mpte' );
    });
  ```

 * Display logic for taxonomies is kinda complicated, and its up to you to show `protected` or `private` posts on taxonomy terms pages.
