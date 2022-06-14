<?php

/**
 * Wp-Presence EVA Model
 * 
 * @package             wp-presence
 * @author              Michiel Uitdehaag
 * @copyright           2020 Michiel Uitdehaag for muis IT
 * @licenses            GPL-3.0-or-later
 *
 * This file is part of wp-presence.
 *
 * wp-presence is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * wp-presence is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with wp-presence.  If not, see <https://www.gnu.org/licenses/>.
 */


 namespace WPPresence;

 class EVA extends Base {
    public $table = "wppres_eva";
    public $pk="id";
    public $fields=array("id","item_id","name","value","type","sorting","remark","modified","modifier");
    public $rules=array(
        "id" => "skip",
        "item_id" => "required|int|model=Item",
        "name" => "required|trim|lte=255",
        "value" => "trim",
        "type" => "required|trim|lte=20",
        "remark" => "json",
        "sorting" => "int",
        "modified" => "skip",
        "modifier" => "skip",
    );

    public function __construct($id=null) {
        parent::__construct($id);
    }

    public function save() {
        $user = wp_get_current_user();
        $this->modified = strftime('%F %T');            
        if($user !== null) {
            $this->modifier = $user->ID;
        }
        else  {
            $this->modifier = -1;
        }
        if(!isset($this->sorting) || empty($this->sorting)) {
            $this->sorting=1;
        }
        return parent::save();        
    }

    public function export($result=null) {
        $retval = parent::export($result);
        error_log("exporting ".json_encode($retval));
        if(isset($retval['remark'])) {
            error_log("json decoding remark");
            $retval['remark']=json_decode($retval['remark']);
        }
        return $retval;
    }

    private function addSort($sort) {
        return array("sorting asc");
    }

    private function addFilter($qb, $filter) {
        if(isset($filter["name"]) && !empty(trim($filter["name"]))) {
            $name=str_replace("%","%%",trim($filter["name"]));
            $qb->where("name","like","%$name%");
        }
        if(isset($filter["item_id"])) {
            $qb->where("item_id",intval($filter["item_id"]));
        }
    }

    public function selectAll($offset=0,$pagesize=0,$filter='',$sort='', $special='') {
        $qb = $this->select('*')->offset($offset)->limit($pagesize)->orderBy($this->addSort($sort));
        $this->addFilter($qb,$filter);
        return $qb->get();
    }

    public function count($filter=null) {
        $qb=$this->numrows();
        $this->addFilter($qb,$filter);
        return $qb->count();
    }

    public function attributes($item,$export=false) {
        $id = is_object($item) ? $item->id : intval($item);
        $qb = $this->select('*')->where("item_id",$id)->orderBy($this->addSort("s"));
        $attrs=$qb->get();
        $retval=array();
        foreach($attrs as $a) {
            $model = new EVA($a);
            if($export) {
                $retval[]=$model->export();
            }
            else {
                $retval[]=$model;
            }
        }
        return $retval;
    }

    public function selectAllAttributes($ids) {
        $attrs = $this->select('*')->where_in("item_id", $ids)->get();
        $retval = array();
        foreach ($attrs as $a) {
            $model = new EVA($a);
            $retval[] = $model;
        }
        return $retval;
    }

    public function getValue($templateattribute, $values) {
        $value = !empty($this->value) ? $this->value : null;
        $def = $templateattribute->value;
        if (empty($value)) $value = $def;
        
        switch($templateattribute->type) {
        case 'string': 
            break;
        case 'number': 
            $value=floatval($value);
            break;
        case 'int':
            $value=intval($value);
            break;
        case 'year':
            $value=intval(strftime('%Y',strtotime($value."-01-01")));
            break;
        case 'date':
            $value=strftime('%F',strtotime($value));
            break;
        case 'enum':
            if(!empty($def) && $def === $value) {
                $vals=explode(" ",$def);
                $value=$vals[0];
            }
            break;
        case 'check':
            if(empty($value)) $value="no";
            break;
        case 'category':
            error_log("searching for $value");
            if(isset($values[$value])) {
                error_log("createing cat based on ".$values[$value]->value);
                $value = $this->date_to_category($values[$value]->value);
            }
            break;
        case 'byear':
            if(isset($values[$value])) {
                $value = intval(strftime('%Y',strtotime($values[$value]->value)));
            }
            break;
        }
        return $value;
    }

    private function date_to_category($dt) {
        // see the same function in the front-end functions.js file
        $dt=strtotime($dt);
        $dt2=time();

        $yearold=intval(strftime("%Y",$dt));
        $yearnew = intval(strftime('%Y',$dt2));
        $diff=$yearnew-$yearold;

        if(intval(strftime('%m',$dt2)) > 7) {
            // add 1 if we are 'in the next season'
            $diff+=1;
        }

        if($diff >= 80) {
            return "V5" . ($diff == 89 ? 'L':'');
        }
        if($diff >= 70) {
            return "V4" . ($diff == 79 ? 'L':'');
        }
        if($diff >= 60) {
            return "V3" . ($diff == 69 ? 'L':'');
        }
        if($diff >= 50) {
            return "V2" . ($diff == 59 ? 'L':'');
        }
        if($diff >= 40) {
            return "V1" . ($diff == 49 ? 'L':'');
        }
        if($diff < 11) {
            $stage='';
            if($diff==10) $stage='L';
            return "K" . $stage;
        }
        if($diff < 13) {
            $stage=$diff - 10;
            if($stage==2) $stage='L';
            return "B" . $stage;
        }
        if($diff < 15) {
            $stage=$diff - 12;
            if($stage==2) $stage='L';
            return "P" . $stage;
        }
        if($diff < 18) {
            $stage=$diff - 14;
            if($stage==3) $stage='L';
            return "C" . $stage;
        }
        if($diff < 21) {
            $stage=$diff - 17;
            if($stage==3) $stage='L';
            return "J" . $stage;
        }
        return 'S' . ($diff >=30 ? 'F':'');         
    }
}
