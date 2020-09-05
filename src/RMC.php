<?php

/*
RouteModelController v1.0.0

Telepítés: composer.json fájba be kell tenni classmaphez az RMC-t
    "autoload": {
        "classmap": [
            "api"
        ]
    },

Route cache:
    Előtte frissíteni kell az autoload-ot! Classmap-ből állítja elő a route-ot.

*/


namespace rmc;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class RMC
{

    public $model;

    /*
    TODO

    - transaction
    - create
    - update
    - delete

    - sort
    - filter


    
    */

/*
    
    public function list(Request $request){
        $query = $this->doList();
        return $this->doCollection($query,true);
    }

    public function view(Request $request,$id){
        $query = $this->doView($id);
        $record = $query->first();
        return $this->doCollection($record,false);
    }
    
*/



    public function doList(){
        $query = $this->model::query();
        if(method_exists($this,'select')){
            self::doSelect($query,call_user_func_array([$this,'select'],['list']));
        }
        return $query;
    }

    public function doView($id){
        $model = new $this->model;
        $query = $this->model::where($model->getKeyName(),$id);
        if(method_exists($this,'select')){
            self::doSelect($query,call_user_func_array([$this,'select'],['view']));
        }
        return $query;
    }

    public function doCollection($data, $dopage = true){
        
        //view esetén
        if(!$data) throw new Exception('A rekord nem található!');

        //query vagy model esetén
        if ($data instanceof EloquentBuilder) {
            $collection = $dopage ? self::doPaginate($data,$total)->get() : $data->get();
            $isView = false;
        }else{
            $isView = true;
            $collection = new Collection([$data]);
        }

        if(method_exists($this,'collection')){
            $collection = $collection->transform([$this,'collection']);
        }

        if($isView) $collection = $collection->first();

        $out = [];
        if(isset($total)) $out['total'] = $total;
        $out['data'] = $collection;
        return $out;
    }

    static function doPaginate($query,&$total){
        $request = app('request');
        $per_page = $request->query('per-page') ?: 25;
        $current_page = $request->query('page') ?: 1;
        $total = $query->count();
        $query->skip($current_page-1)->take($per_page);
        return $query;
    }

    static function doSelect($query, Array $params){
        $select = [];
        foreach($params as $key => $value){
            $select[] = is_numeric($key) ? $value : $value.' as '.$key;
        }
        $query->select($select);
    }

    

    static function addRoute(){
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
