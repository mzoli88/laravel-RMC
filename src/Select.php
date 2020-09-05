<?php

namespace api;

class Select
{

    static function do($query, Array $params){
        $select = [];
        foreach($params as $key => $value){
            $select[] = is_numeric($key) ? $value : $value.' as '.$key;
        }
        $query->select($select);
    }

}