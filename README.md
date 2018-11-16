CSS Tree Shaking
================

Helps you to eliminate the portions of CSS you aren't using. Usually should be used to generate AMP pages, where is the fixed limit for maximum styles size.

Installation
------------

```
composer require drupaljedi/css-tree-shaking
```

Usage
-----
Pretty simple to use, just create the object and shake it :)
```php
<?php

include 'vendor/autoload.php';

use DrupalJedi\CssTreeShaking;

$cssShaker = new CssTreeShaking($html);
$optimizedHtml = $cssShaker->shakeIt();
```
Where `$html` is raw HTML with inliny styles.

By default, styles will be shaken only if the limit (50kb) is exceeded.
If you want to shake the styles in any case, just call the `shakeIt()` with a `TRUE` argument:
```php
$optimizedHtml = $cssShaker->shakeIt(TRUE);
```

Features
--------

* PSR-4 autoloading compliant structure
* Easy to use to any framework or even a plain php file
