<?php
/*
    SearchTrait, Megyik Zoltán
    Használat:

    A tratet (eloquent) modellen kell behúzni és kell egy kereső paraméter metódust definiálni.
    public function search(){} és egy tömböt kell vissza adnia
        A tömb
            kulcs - értéke határozza meg, hogy milyen értéket keres (GET) request()->guery() paramraméterben (url)
            érték - határozzameg, hogy milyen típusra keresünk. Operátor pl <,>,=,IN,LIKE ...stb.

        beégetett keresés típusok:
            like - zsabványos % tesz elé és utána
            datum - pontos dátumra keres
            datumtol - dátum formátum -tól keres
            datumig - dátum formátum -ig keres
        
        A kulcsból kivágjuk a '<' és a '>' karaktereket így azok nem kerülnek bele a táblanévbe.
        
        Relationships esetén a paraméter tömben a reláció elnevezést kell megadni:
            pl.:
                'camp.camp_name' => 'like',
                vagy 
                'worl.camp.camp_name' => 'like',
        Lekérdezéskor meg kell hívni a search scope-ot.
            Restaurant::with('camp')->search()->get()


    Példa:
    #################################################
    
    namespace App;
    use Illuminate\Database\Eloquent\Model;
    
    class Restaurant extends Model
    {
        
        protected $table = 'erta_restaurants';
        
        public function search(){
            return [
                'camp_id' => '=',
                'restaurant_name' => 'like',
                'camp.camp_name' => 'like',
                'camp.camp_name|campname' => 'like',
                'updated_at>' => 'datumtol',
                'updated_at<' => 'datumig',
                'updated_at' => 'datum',
                'mindentORal' => [
                    'camp_id' => '=',
                    'restaurant_name' => 'like',
                    'camp.camp_name' => 'like',
                ]
            ];
        }
        
        public function camp()
        {
            return $this->belongsTo('App\Camp');
        }
    }

    
*/

namespace api;

use Illuminate\Database\Eloquent\Builder;

class Search
{

    public function __construct($query, $SearchParam, array $baseparam = [])
    {
        if (is_array($SearchParam) && !empty($SearchParam)) {
            $search_data = [];
            $or_count = 0;
            foreach ($SearchParam as $key => $type) {
                $tmp = explode('|', $key);
                $key = $tmp['0'];

                $prop = $this->mzGetProp($key);
                $rel = $this->mzGetRel($key);
                
                if (isset($baseparam[$prop])){
                    $v = $baseparam[$prop];
                }else{
                    $v = request()->query(isset($tmp[1]) ? $tmp[1] : $prop);
                }
                if ($v) {
                    if(!$rel)$prop = $query->getModel()->table . "." . $prop;
                    $prop = str_replace(['<', '>'], '', $prop);
                    if(is_array($type)){
                        $or_count++;
                        foreach($type as $tkey => $ttype){
                            $prop = $this->mzGetProp($tkey);
                            $rel = $this->mzGetRel($tkey);
                            $this->mzMake($search_data,$ttype,$rel,$prop,$v,$or_count);
                        }
                    }else{
                        $this->mzMake($search_data,$type,$rel,$prop,$v);
                    }
                }
            }

            //where feltételek hozzáadása
            if(!empty($search_data)){
                foreach($search_data as $key => $value){                    
                    $this->mzWhere($query, $value, $key);
                }
            }
        }
        return $query;
    }

    
    private function mzWhere($query, Array $searchData, $rel = 0, $or = false)
    {
        if (is_numeric($rel)) {
            if($rel == 0){
                if(!empty($searchData)){
                    foreach($searchData as $s){
                        call_user_func_array([$query,$s[1]],$s[0]);
                    }
                }
            }else{
                $query->where(function($query3) use ($searchData,$rel){
                    if(!empty($searchData)){
                        foreach($searchData as $k => $v){
                            $this->mzWhere($query3,$v,$k,true);
                        }
                    }
                });
            }
        } else {
            if($or){
                $query->orWhereHas($rel, function (Builder $query2) use ($searchData) {
                    $query2->where(function($query4) use ($searchData) {
                        if(!empty($searchData)){
                            foreach($searchData as $s){
                                call_user_func_array([$query4,$s[1]],$s[0]);
                            }
                        }
                    });
                });
            }else{
                $query->whereHas($rel, function (Builder $query2) use ($searchData) {
                    if(!empty($searchData)){
                        foreach($searchData as $s){
                            call_user_func_array([$query2,$s[1]],$s[0]);
                        }
                    }
                });
            }
        }
    }

    private function mzMake(Array &$search_data,$type,$rel,$prop,$v,$or = false)
    {
        switch ($type) {
            case 'in':
            case 'IN':
                $tmp = [[$prop, explode(',',$v)], $or ? 'orWhereIn' : 'whereIn'];
                break;
            case 'like':
            case 'LIKE':
                $tmp = [[$prop, 'LIKE', '%' . $v . '%'], $or ? 'orWhere' : 'where'];
                break;
            case 'datum':
                $tmp = [[$prop, '=', $v], $or ? 'orWhereDate' : 'whereDate'];
            break;
            case 'datumtol':
                $tmp = [[$prop, '>=', $v], $or ? 'orWhereDate' : 'whereDate'];
                break;
            case 'datumig':
                $tmp = [[$prop, '<=', $v], $or ? 'orWhereDate' : 'whereDate'];
                break;
            default:
                $tmp = [[$prop, $type, $v], $or ? 'orWhere' : 'where'];
                break;
        }
        if($or){
            $search_data[$or][$rel][] = $tmp;
        }else{
            $search_data[$rel][] = $tmp;
        }
    }

    private function mzGetRel($key)
    {
        if (preg_match('/\./', $key)) {
            $keyData = explode('.', $key);
            $prop = end($keyData);
            $rel = str_replace('.' . $prop, '', $key);
        } else {
            $prop = $key;
            $rel = 0;
        }
        return $rel;
    }

    private function mzGetProp($key)
    {
        if (preg_match('/\./', $key)) {
            $keyData = explode('.', $key);
            $prop = end($keyData);
        } else {
            $prop = $key;
        }
        return $prop;
    }

}
