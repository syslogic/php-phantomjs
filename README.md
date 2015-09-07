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

the PhantomJS script, which is (soon to be) being generated:
```javascript
var url  = "http:/localhost/specs.html";
var system = require('system'), page = require('webpage').create();

function waitFor(testFx, onReady, timeOutMillis) {
    var maxtimeOutMillis = timeOutMillis ? timeOutMillis : 3001, //< Default Max Timeout is 3s
        start = new Date().getTime(),
        condition = false,
        interval = setInterval(function() {
            if ((new Date().getTime() - start < maxtimeOutMillis) && !condition ) {
                // If not time-out yet and condition not yet fulfilled
                condition = (typeof(testFx) === "string" ? eval(testFx) : testFx()); //< defensive code
            } else {
                if(!condition) {
                    // If condition still not fulfilled (timeout but condition is 'false')
                    console.log("'waitFor()' timeout");
                    phantom.exit(1);
                } else {
                    // Condition fulfilled (timeout and/or condition is 'true')
                    console.log("'waitFor()' finished in " + (new Date().getTime() - start) + "ms.");
                    typeof(onReady) === "string" ? eval(onReady) : onReady(); //< Do what it's supposed to do once the condition is fulfilled
                    clearInterval(interval); //< Stop this interval
                }
            }
        }, 100); //< repeat check every 100ms
};

page.onConsoleMessage = function(msg) {console.log(msg);};
page.open(url , function(status) {
    
    if (status !== "success") {
        console.log("Unable to open " + url );
        phantom.exit(1);
    } else {
        waitFor(function(){
            
            return page.evaluate(function(){
                return document.body.querySelector('.symbolSummary .pending') === null
            });
            
        }, function(){
            
            var exitCode = page.evaluate(function(){
                try {
                    console.log('');
                    console.log(document.body.querySelector('.description').innerText);
                    var list = document.body.querySelectorAll('.results > #details > .specDetail.failed');
                    if (list && list.length > 0) {
                      console.log('');
                      console.log(list.length + ' test(s) FAILED:');
                      for (i = 0; i < list.length; ++i) {
                          var el = list[i],
                              desc = el.querySelector('.description'),
                              msg = el.querySelector('.resultMessage.fail');
                          console.log('');
                          console.log(desc.innerText);
                          console.log(msg.innerText);
                          console.log('');
                      }
                      return 1;
                    } else {
                      console.log(document.body.querySelector('.alert > .passingAlert.bar').innerText);
                      return 0;
                    }
                } catch (ex) {
                    console.log(ex);
                    return 1;
                }
            });
            
            phantom.exit(exitCode);
        });
    }
});
```
the returned JSON (while `$output=true` is passed):
```javascript
{"success":true,"script":"\/php-phantomjs\/specs.html"}

```