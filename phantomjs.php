<?php
/* * *
 *  php-phantomjs - a PHP5 wrapper for PhantomJS
 *  Copyright 2015 by Martin Zeitler, Bavaria. 
 *  Some rights reserved. See LICENSE.txt
 */

class phantomjs {
    
    protected $page;
    protected $scripts;
    protected $script;
    
    protected $basedir;
    protected $request;
    protected $response;
    
    protected $url             = null;
    protected $method          = 'GET';
    protected $output          = 'json';
    protected $tag             = 'phantomjs';
    protected $clip_screenshot = false;
    
    function __construct(){
        
        $this->setup_directories();
        $this->sanity_check();
        
        $this->page = (object) array(
            'viewportSize' => (object)array('width' => 1024, 'height' => 768),
                'clipRect' => (object)array('width' => 1024, 'height' => 768, 'top' => 0, 'left' => 0),
        );
    }
    
    private function setup_directories(){
        $this->basedir   = dirname(__FILE__).DIRECTORY_SEPARATOR;
        $this->scripts   = $this->basedir.'scripts'.DIRECTORY_SEPARATOR;
        if(! is_dir($this->scripts)){
            
            // setsebool -P httpd_unified 1
            // setsebool -P httpd_execmem 1
             
            if(mkdir(rtrim($this->scripts, DIRECTORY_SEPARATOR), 0775)){
                file_put_contents($this->scripts.'.htaccess', 'deny from all');
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
    }
    
    /* TODO: rather display an index.html */
    public function index(){
        $this->response='<html><head><title>php-phantomjs</title></head><body>'.''.'</body></html>';
        $this->output='html';
        $this->render();
    }
    
    public function screenshot($url = false, $output= false){
        
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
        $screen = str_replace('www.', '', $parts['host']).'_'.crc32($url).'_'.$width.'x'.$height.'.jpg';
        $this->script = "var page = require('webpage').create();\npage.viewportSize = {width: {$width}, height: {$height}};\n";
        if($this->clip_screenshot) {
            $this->script .= "page.clipRect = {top: {$crt}, left: {$crl}, width: {$crw}, height: {$crh}};\n";
        }
        $this->script .= "page.open('{$url}', function() {\n\tpage.render('{$screen}');\n\tphantom.exit();\n});";
        $task = $this->scripts.str_replace('www.', '', $parts['host']).'_'.crc32($this->script).'.js';
        file_put_contents($task, $this->script);
        $this->exec($task, $output);
    }
    
    public function jasmine($url = false, $output= false){
        
        /* JavaScript Generation */
        $parts  = parse_url($url);
        
        /* TODO */
        $this->script = "var system = require('system');\n";
        
        $task = $this->scripts.str_replace('www.', '', $parts['host']).'_'.crc32($this->script).'.js';
        file_put_contents($task, $this->script);
        $this->exec($task, $output);
    }
    
    /* Property Getters */
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
    
    /* Property Setters */
    private function setViewportSize($width, $height){
        if(is_numeric($width) && is_numeric($height)){
            $this->page->viewportSize->width  = ceil($width);
            $this->page->viewportSize->height = ceil($height);
        }
    }
    
    /* TODO: check if the clipRect not exceeds the viewportSize */
    private function setClipRect($width=false, $height=false, $top=false, $left=false){
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
            $stdOut = shell_exec('phantomjs '.$task);
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