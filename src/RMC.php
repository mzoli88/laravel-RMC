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


namespace api;

use Exception;
use DynamicRouter\Router;
use Illuminate\Database\Eloquent\Collection;
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
            Select::do($query,call_user_func_array([$this,'select'],['list']));
        }
        if(method_exists($query->getModel(),'search')){
            new Search($query,$query->getModel()->search());
        }
        return $query;
    }

    public function doView($id){
        $query = $this->model::query();
        $query->where($query->getModel()->getKeyName(),$id);
        if(method_exists($this,'select')){
            Select::do($query,call_user_func_array([$this,'select'],['view']));
        }
        return $query;
    }

    public function doCollection($data, $dopage = true){
        
        //view esetén
        if(!$data) throw new Exception('A rekord nem található!');

        //query vagy model esetén
        if ($data instanceof EloquentBuilder) {
            $collection = $dopage ? Pager::do($data,$total)->get() : $data->get();
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

    static function route(){
        Router::$controller_ending = 'RMC';
        Router::route();
    }
  
}
