<?php
/* * *
 *  php-phantomjs - a PHP5 wrapper for PhantomJS
 *  Copyright 2015 by Martin Zeitler, Bavaria. 
 *  Some rights reserved. See LICENSE.txt
 */

class phantomjs {
    
    /* configurable */
    protected $cmd                = 'phantomjs';
    protected $tag                = 'phantomjs';
    protected $dirname_scripts    = 'scripts';
    protected $dirname_capture    = 'capture';
    protected $screenshot_format  = 'png';
    protected $screenshot_quality = 100;
    protected $screenshot_clip    = false;
    protected $browser_ua         = true;
    
    /* accessible */
    protected $page;
    
    /* private */
    private $url                = null;
    private $method             = 'GET';
    private $output             = 'json';
    private $screenshot_formats = array('png', 'gif', 'jpeg', 'pdf');
    private $screenshot_file;
    private $user_agent;
    private $basedir;
    private $capture;
    private $scripts;
    private $script;
    
    private $request;
    private $response;

    function __construct(){
        $this->user_agent = $_SERVER["HTTP_USER_AGENT"];
        $this->setup_dirs();
        $this->sanity_check();
        $this->setup_page();
    }
    
    /* http://phantomjs.org/api/webpage/ */
    private function setup_page(){
        
        /* TODO: Page Abstraction */
        $this->page = (object)array(
            'scrollPosition'   => (object)array('top' => 0, 'left' => 0),
            'viewportSize'     => (object)array('width' => 1920, 'height' => 1080),
            'clipRect'         => (object)array('width' => 1920, 'height' => 1080, 'top' => 0, 'left' => 0),
            'paperSize'        => (object)array('format' => 'A4', 'orientation' => 'portrait', 'margin' => '10mm'),
            'customHeaders'    => (object)array(),
            'cookies'          => array(),
            'libraryPath'      => '',
            'devicePixelRatio' => 1.0,
            'zoomFactor'       => 1.0,
            'settings'         => (object)array(
                'javascriptEnabled'             => true,
                'loadImages'                    => true,
                'localToRemoteUrlAccessEnabled' => false,
                'userAgent'                     => $this->user_agent,
                // 'username'                      => null,
                // 'password'                      => null,
                'XSSAuditingEnabled'            => false,
                'webSecurityEnabled'            => true,
                'resourceTimeout'               => 20000
            ),
            'windowName'       => '',
            'url'              => '',
            'title'            => '',
            'content'          => '',
            'plainText'        => ''
        );
    }
    
    private function setup_dirs(){
        $this->basedir = dirname(__FILE__).DIRECTORY_SEPARATOR;
        $this->scripts = $this->basedir.$this->dirname_scripts.DIRECTORY_SEPARATOR;
        $this->capture = $this->basedir.$this->dirname_capture.DIRECTORY_SEPARATOR;
        $this->create_dir($this->scripts, true);
        $this->create_dir($this->capture, false);
    }
    private function create_dir($directory='', $htaccess=false){
        if(! is_dir($directory)){
            if(mkdir(rtrim($directory), 0775)){
                if($htaccess){
                    file_put_contents($directory.'.htaccess', 'deny from all');
                }
            }
        }
    }
    private function sanity_check(){
        $version = explode('.', shell_exec('phantomjs -v'));
        if((int)$version[0] < 2){
            die('['.$this->tag.'] version >= 2.0.0 is required.');
        }
        if(! function_exists('shell_exec')){
            die('['.$this->tag.'] function shell_exec() is required.');
        }
        if(! is_dir(rtrim($this->scripts, DIRECTORY_SEPARATOR))){
            die('['.$this->tag.'] directory '.$this->scripts.' is absent.');
        }
        if(! is_dir(rtrim($this->capture, DIRECTORY_SEPARATOR))){
            die('['.$this->tag.'] directory '.$this->capture.' is absent.');
        }
    }
    
    public function screenshot($url=false, $output=false, $format='png', $quality=100){
        
        /* Parameters */
        
        /* TODO */
        if(! $url){if(! $output){return false;} else {return false;}}
        else {$parts = parse_url($url);}
        
        if(in_array($format, $this->screenshot_formats)){$this->screenshot_format = $format;}
        if($quality > 0 && $quality <= 100){$this->screenshot_quality = $quality;}
        
        /* Phantom WebPage Module */
        $this->script = "var page = require('webpage').create();\n";
        
        /* Viewport Dimensions */
        $width  = $this->getViewportWidth();
        $height = $this->getViewportHeight();
        $this->script.= "page.viewportSize = {width: {$width}, height: {$height}};\n";
        
        /* Clip Rectangle, if applicable */
        if($this->screenshot_clip) {
            $crw = $this->getClipRectWidth();
            $crh = $this->getClipRectHeight();
            $crt = $this->getClipRectTop();
            $crl = $this->getClipRectLeft();
            $this->script.= "page.clipRect = {top: {$crt}, left: {$crl}, width: {$crw}, height: {$crh}};\n";
        }
        
        /* User Agent */
        if($this->browser_ua){
            $this->script.= "page.settings.userAgent = '{$this->user_agent}';\n";
        }
        
        /* Viewport Zoom Factor */
        if($this->page->zoomFactor != 1.0){
            $this->script.= "page.zoomFactor = {$this->page->zoomFactor};\n";
        }
        
        /* Scroll Position */
        if($this->page->scrollPosition->left > 0 || $this->page->scrollPosition->top > 0){
            $this->script.= "page.scrollPosition = {left: {$this->page->scrollPosition->left}, top: {$this->page->scrollPosition->top}};\n";
        }
        
        /* Pixel Ratio (not yet supported) */
        if($this->page->devicePixelRatio > 1.0){
            $this->script.= "page.devicePixelRatio = {$this->page->devicePixelRatio};\n";
        }
        
        /* Capture File */
        $this->screenshot_file = $this->dirname_capture.DIRECTORY_SEPARATOR.str_replace('www.', '', $parts['host']).'_'.crc32($url).'_'.$width.'x'.$height.'.'.$format;
        $this->script.= "page.open('{$url}', function() {\n\tpage.render('{$this->screenshot_file}', {format: '{$this->screenshot_format}', quality: '{$this->screenshot_quality}'});\n\tphantom.exit();\n});";
        $task = $this->scripts.str_replace('www.', '', $parts['host']).'_'.crc32($this->script).'.js';
        
        /* Save & Run */
        file_put_contents($task, $this->script);
        $this->exec($task, $output);
    }
    public function jasmine($url = false, $output= false){
        
        /* JavaScript Generation */
        $parts  = parse_url($url);
        $this->script = "var url  = '$url'\n;var system=require('system'), page=require('webpage').create();\npage.onConsoleMessage=function(msg) {console.log(msg);};\n";
        $this->script.= "page.open(url, function(status) {
            if (status !== 'success') {
                console.log('Unable to open ' + url );
                phantom.exit(1);
            } else {
                console.log('opened ' + url );
                phantom.exit(0);
            }
        });";
        $task = $this->scripts.str_replace('www.', '', $parts['host']).'_jasmine_'.crc32($this->script).'.js';
        file_put_contents($task, $this->script);
        $this->exec($task, $output);
    }
    
    /* Getters */
    private function getScrollPosition(){
        return $this->page->scrollPosition;
    }
    private function getScrollPositionTop(){
        return $this->page->scrollPosition->top;
    }
    private function getScrollPositionLeft(){
        return $this->page->scrollPosition->left;
    }
    private function getDevicePixelRatio(){
        return $this->page->devicePixelRatio;
    }
    private function getZoomFactor(){
        return $this->page->zoomFactor;
    }
    private function getViewportSize(){
        return $this->page->viewportSize;
    }
    private function getViewportWidth(){
        return $this->page->viewportSize->width;
    }
    private function getViewportHeight(){
        return $this->page->viewportSize->height;
    }
    private function getClipRectWidth(){
        return $this->page->clipRect->width;
    }
    private function getClipRectHeight(){
        return $this->page->clipRect->height;
    }
    private function getClipRectTop(){
        return $this->page->clipRect->top;
    }
    private function getClipRectLeft(){
        return $this->page->clipRect->left;
    }
    private function getPaperSize(){
        return $this->page->PaperSize;
    }
    private function getPaperFormat(){
        return $this->page->paperSize->format;
    }
    private function getPaperOrientation(){
        return $this->page->paperSize->orientation;
    }
    private function getPaperMargin(){
        return $this->page->paperSize->margin;
    }
    
    /* Setters */
    private function setScrollPosition($top=0, $left=0){
        $this->page->scrollPosition = (object)array('top' => $top, 'left' => $left);
    }
    private function setScrollPositionTop($value=0){
        $this->page->scrollPosition->top=$value;
    }
    private function setScrollPositionLeft($value=0){
        $this->page->scrollPosition->left=$value;
    }
    private function setDevicePixelRatio($ratio = 1.0){
        if(is_float($ratio) && $ratio >= 1.0){
            $this->page->devicePixelRatio=$ratio;
        }
    }
    private function setZoomFactor($factor = 1.0){
        if(is_float($factor) && $factor > 0.0 && $factor <= 1.0){
            $this->page->zoomFactor=$factor;
        }
    }
    private function setViewportSize($width, $height){
        if(is_numeric($width) && is_numeric($height)){
            $this->page->viewportSize->width  = ceil($width);
            $this->page->viewportSize->height = ceil($height);
        }
    }
    private function setPaperSize($format, $orientation, $margin){
        if(isset($format) && isset($orientation) &&isset($margin)){
            $this->page->paperSize->format      = $format;
            $this->page->paperSize->orientation = $orientation;
            $this->page->paperSize->margin      = $margin;
        }
    }
    
    /* TODO: check if the clipRect not exceeds the viewportSize ? */
    private function setClipRect($width=false, $height=false, $top=0, $left=0){
        if(is_numeric($width) && is_numeric($height)){
            $this->page->clipRect->width  = ceil($width);
            $this->page->clipRect->height = ceil($height);
        }
        if(is_numeric($top) && is_numeric($left)){
            $this->page->clipRect->top    = ceil($top);
            $this->page->clipRect->left   = ceil($left);
        }
    }
    
    private function setRequestMethod($method){
        if(in_array($method, array('POST', 'GET'))){
            $this->method = $method;
        }
    }
    
    private function setRequestUrl($url){
        if(in_array(parse_url($url, PHP_URL_SCHEME), array('http','https'))){
            if(filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $this->url = $url;
            }else{
                $this->response=array('error' => 'not valid url');
                $this->render();
            }
        }else{
            $this->response=array('error' => 'no protocol');
            $this->render();
        }
    }
    
    
    private function exec($task, $output){
        if(file_exists($task) && is_readable($task)){
            shell_exec($this->cmd.' '.$task);
            if($output){
                $this->response=array('success' => true, 'script' => $task);
                $this->render();
            }
        } else {
            die('['.$this->tag.'] script '.$task.' is absent.');
        }
    }
    
    protected function render(){
        switch($this->output){
            case 'json':
                $ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
                if(isset($ua) && !preg_match('/msie\s(\d+)/', $ua)){
                    header('content-type: application/json; charset=utf8;');
                }
                die(json_encode((object)$this->response));
                break;
            case 'html':
                header('Content-type: text/html; Charset=utf8;');
                die(preg_replace('(\t|\n)', '', $this->response));
                break;
        }
    }
}
?>