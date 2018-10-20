# WP Styleguide

This plugin maps requests to `/styleguide/` to a `styleguide` directory in your WordPress theme. this allows you to craft a styelguide and make it accessible via the front end of your website. This comes in handy for keeping a collection of static components that you can visually QA. All of the functions and classes available to your theme should be available to your styleguide pages as well.

For example, given this directory structure...

```
my-theme/
    - index.php
    - style.css
    - functions.php
    styleguide/
        - index.php
		- colors.php
```

A request to `/styleguide/colors/` would look for `my-theme/styelguide/colors.php`. What you put in those files is up to you and your styleguide.

## Sass Color Helper function
To get color values from a Sass file you can call `WP_Styleguide::get_sass_colors()` passing in the path to a sss file to parse.

You'll get back an array of hex colors keyed by their Sass variables.

Example:

```
array(
    '$white' => '#ffffff',
    '$black' => '#000000',    
);
```
