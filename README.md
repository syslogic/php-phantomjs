![alt text](http://www.codefx.biz/favicon.ico "php-phantomjs")
# php-phantomjs
##a PHP5 wrapper for PhantomJS

### Overview
This class is still rather basic - might extend it, while there is some practical use for certain methods.

### Usage

#### getting a Handle
```php
require_once('phantomjs.php');
$phantomjs = new phantomjs();
```

#### taking Screenshots (generally working)
```php
$phantomjs->screenshot("https://www.google.com", true);
```

the PhantomJS script, which is being generated:
```javascript
var page = require('webpage').create();
page.viewportSize = {width: 1024, height: 768};
page.open('https://www.google.com', function() {
	page.render('google.com_857627499_1024x768.jpg');
	phantom.exit();
});
```
the returned JSON (while `$output=true` is passed):
```javascript
{"success":true,"script":"\/php-phantomjs\/scripts\/google.com_1293740674.js"}

```

#### running Jasmine Unit Tests (under development)
```php
$phantomjs->jasmine("http:/localhost/specs.html", true);
```

the PhantomJS script, which is being generated:
```javascript
var url  = "http:/localhost/specs.html";
var system = require('system'), page = require('webpage').create();

...
```
the returned JSON (while `$output=true` is passed):
```javascript
{"success":true,"script":"\/php-phantomjs\/specs.html"}

```