<?php

namespace api;

use Exception;
use Illuminate\Support\Facades\Route;

class Router
{
    static function doRouter(){
        // php artisan route:cache
        // php artisan route:clear
        // php artisan optimize
        // php artisan optimize:clear
        Route::namespace('\\api')->group(function () {
            
            $classmap = (new \Facade\Ignition\Support\ComposerClassMap)->listClasses();
            $group_prefix = str_replace('/','\\\\',Route::getLastGroupPrefix());
            if(empty($group_prefix) || $group_prefix == "api")throw new Exception('no group');
            
            if(self::isConsole('route:cache')){
                //összes Route beállítása
                foreach($classmap as $class => $file){
                    if(preg_match('/^'.$group_prefix.'.*RMC$/',$class)){
                        if($class=='api\RMC')continue;
                        echo " - ".$class."\n";
                        self::_routeformat($class,$group_prefix);
                    }
                }
            }else{
                //aktuális route beállítása Autoloader alapján
                $full_ct = self::getCtFromRequest();
                self::_routeformat($full_ct,$group_prefix);
            }


        });
    }

    static function isConsole($command){
        return app()->runningInConsole() ? in_array($command,$_SERVER['argv']) : false;
    }

    static function getCtFromRequest(){
        $request = app('request');
        $ct = preg_replace(['/^\//'],'',$request->getRequestUri());
        $tmpct = explode('/',$ct);
        $lastCtname = ucfirst(end($tmpct));
        $tmpct[key($tmpct)] = $lastCtname;
        $ct = implode('\\',$tmpct) . 'RMC';
        return $ct;
    }

    static function _routeformat($class,$group_prefix){
        $ct = preg_replace(['/^api/','/^\\\\/'],'',$class);
        $uri = strtolower(preg_replace(['/^'.$group_prefix.'/','/^\\\\/','/RMC$/'],'',$class));

        //file betöltése, ha autoladerben nem létezik
        if(!class_exists($class)){
            $fullpath = realpath(app()->basePath().'/'.$class.'.php');
            if($fullpath) require_once($fullpath);
        }
        // dd($ct,$uri,$class, class_exists($class) ,method_exists($class,'list'));
        self::_route($class,$uri,$ct);
    }

    static function _route($class,$uri,$ct){
        if(method_exists($class,'list'))Route::get($uri,$ct.'@list');
        if(method_exists($class,'view'))Route::get($uri.'/{id}',$ct.'@view');
        if(method_exists($class,'create'))Route::post($uri,$ct.'@create');
        if(method_exists($class,'update'))Route::put($uri.'/{id}',$ct.'@update');
        if(method_exists($class,'delete'))Route::delete($uri.'/{id}',$ct.'@delete');
    }
}