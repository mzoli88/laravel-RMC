<?php

namespace api;

class Pager
{

    static function do($query,&$total){
        $request = app('request');
        $per_page = $request->query('per-page') ?: 25;
        $current_page = $request->query('page') ?: 1;
        $total = $query->count();
        $query->skip($current_page-1)->take($per_page);
        return $query;
    }

}