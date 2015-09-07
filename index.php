<?php
/* * *
 *  php-phantomjs - a PHP5 wrapper for PhantomJS
 *  Copyright 2015 by Martin Zeitler, Bavaria. 
 *  Some rights reserved. See LICENSE.txt
 */

$path=dirname(__FILE__).DIRECTORY_SEPARATOR.'phantomjs.php';
if(file_exists($path)){
    
    require_once($path);
    $phantomjs = new phantomjs();
    
    // $phantomjs->screenshot("https://www.google.com", true);
    $phantomjs->jasmine("http://phantomjs/specs.html", true);
    
} else {
    die('file absent: '.$path);
}
?>