<?php
/* * *
 *  php-phantomjs - a PHP5 wrapper for PhantomJS
 *  Copyright 2015 by Martin Zeitler, Bavaria. 
 *  Some rights reserved. See LICENSE.txt
 */

class phantomjs {
    
    protected $page;
    protected $capture;
    protected $scripts;
    protected $script;
    
    protected $basedir;
    protected $request;
    protected $response;
    
    protected $url              = null;
    protected $method           = 'GET';
    protected $output           = 'json';
    protected $cmd              = 'phantomjs';
    protected $tag              = 'phantomjs';
    
    private $client_ua;
    private $browser_ua         = true;
    private $screenshot_clip    = false;
    
    private $screenshot_file;
    private $supported_formats  = array('png', 'gif', 'jpeg', 'pdf');
    private $screenshot_format  = 'png';
    private $screenshot_quality = 100;
    
    function __construct(){
        $this->client_ua = $_SERVER["HTTP_USER_AGENT"];
        $this->setup_dirs();
        $this->setup_webpage();
        $this->sanity_check();
    }
    
    /* http://phantomjs.org/api/webpage/ */
    private function setup_webpage(){
        
        /* Page Abstraction */
        $this->page = (object)array(
            'viewportSize'  => (object)array('width' => 1920, 'height' => 1080),
            'clipRect'      => (object)array('width' => 1920, 'height' => 1080, 'top' => 0, 'left' => 0),
            'paperSize'     => (object)array('format' => 'A4', 'orientation' => 'portrait', 'margin' => '10mm'),
            'customHeaders' => (object)array(),
            'settings'      => (object)array(),
            'cookies'       => array(),
            'windowName'    => '',
            'title'         => '',
            'content'       => '',
            'plainText'     => ''
        );
    }
    
    private function setup_dirs(){
        $this->basedir = dirname(__FILE__).DIRECTORY_SEPARATOR;
        $this->scripts = $this->basedir.'scripts'.DIRECTORY_SEPARATOR;
        $this->capture = $this->basedir.'capture'.DIRECTORY_SEPARATOR;
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
        if(! $url){if(! $output){return false;} else {return false;}}
        if(in_array($format, $this->supported_formats)){$this->screenshot_format = $format;}
        if($quality > 0 && $quality <= 100){$this->screenshot_quality = $quality;}
        
        /* Viewport Dimensions */
        $width  = $this->getViewportWidth();
        $height = $this->getViewportHeight();
        
        /* Clip Rectangle Dimensions */
        $crw    = $this->getClipRectWidth();
        $crh    = $this->getClipRectHeight();
        $crt    = $this->getClipRectTop();
        $crl    = $this->getClipRectLeft();
        
        /* JavaScript Generation */
        $parts  = parse_url($url);
        $this->screenshot_file = $this->capture.str_replace('www.', '', $parts['host']).'_'.crc32($url).'_'.$width.'x'.$height.'.'.$format;
        $this->script = "var page = require('webpage').create();\n";
        $this->script.= "page.viewportSize = {width: {$width}, height: {$height}};\n";
        if($this->screenshot_clip) {$this->script.= "page.clipRect = {top: {$crt}, left: {$crl}, width: {$crw}, height: {$crh}};\n";}
        if($this->browser_ua)      {$this->script.= "page.settings.userAgent = '{$this->client_ua}';\n";}
        $this->script.= "page.open('{$url}', function() {\n\tpage.render('{$this->screenshot_file}', {format: '{$this->screenshot_format}', quality: '{$this->screenshot_quality}'});\n\tphantom.exit();\n});";
        $task = $this->scripts.str_replace('www.', '', $parts['host']).'_'.crc32($this->script).'.js';
        
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
    private function getViewportSize(){
        return $this->page->viewportSize;
    }
    private function getViewportWidth(){
        return $this->page->viewportSize->width;
    }
    private function getViewportHeight(){
        return $this->page->viewportSize->width;
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