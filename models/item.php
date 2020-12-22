<?php

/**
 * Wp-Presence Item Model
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

 class Item extends Base {
    public $table = "wppres_item";
    public $pk="id";
    public $fields=array("id","name","type","created","creator","modified","modifier","state");
    public $rules=array(
        "id" => "skip",
        "name" => "required|trim|lte=255",
        "type" => "required|trim|lte=20",
        "created" => "skip",
        "creator" => "skip",
        "modified" => "skip",
        "modifier" => "skip",
        "state"=>"required|trim|lte=20",
        "attributes"=> "contains=EVA,attributes_list"
    );

    public function __construct($id=null) {
        parent::__construct($id);
    }

    public function save() {
        $user = wp_get_current_user();
        if($this->isNew()) {
            $this->created = strftime('%F %T');            
            if($user !== null) {
                $this->creator = $user->ID;
            }
            else  {
                $this->creator = -1;
            }
        }
        $this->modified = strftime('%F %T');            
        if($user !== null) {
            $this->modifier = $user->ID;
        }
        else  {
            $this->modifier = -1;
        }
        return parent::save();
    }

    public function postSave() {
        error_log("postsave for item, testing attributes_list: ".(isset($this->attributes_list)?"set":"not set"));
        if(isset($this->attributes_list)) {
            error_log("attributes list is set for saving");
            $oldlist = $this->listAttributes();
            $sorting=1;
            foreach($this->attributes_list as $c) {
                $c->item_id = $this->{$this->pk};
                $c->sorting=$sorting;
                $sorting+=1;
                $c->save();

                for($i=0;$i<sizeof($oldlist);$i++) {
                    if($oldlist[$i]->identical($c)) {
                        unset($oldlist[$i]);
                        $oldlist = array_values($oldlist);
                    }
                }
            }
            foreach($oldlist as $c) {
                $c->delete();
            }
        }
        return true;
    }

    private function addSort($sort) {
        if(empty($sort)) $sort="i";
        $orderBy=array();
        for($i=0;$i<strlen($sort);$i++) {
            $c=$sort[$i];
            switch($c) {
            default:
            case 'i': $orderBy[]="id asc"; break;
            case 'I': $orderBy[]="id desc"; break;
            case 'n': $orderBy[]="name asc"; break;
            case 'N': $orderBy[]="name desc"; break;
            case 'c': $orderBy[]="created asc"; break;
            case 'C': $orderBy[]="created desc"; break;
            case 'm': $orderBy[]="modified asc"; break;
            case 'M': $orderBy[]="modified desc"; break;
            case 's': $orderBy[]="state asc"; break;
            case 'S': $orderBy[]="state desc"; break;
            case 't': $orderBy[]="type asc"; break;
            case 'T': $orderBy[]="type desc"; break;
            }
        }
        return $orderBy;
    }

    private function addFilter($qb, $filter) {
        if(isset($filter['name']) && !empty(trim($filter['name']))) {
            $name=str_replace("%","%%",trim($filter['name']));
            $qb->where("name","like","%$name%");
        }
        if(isset($filter['type']) && !empty(trim($filter['type']))) {
            $name=trim($filter['type']);
            $qb->where("type",$name);
        }
    }

    public function selectAll($offset=0,$pagesize=0,$filter=array(),$sort='', $special='') {
        $qb = $this->select('*')->offset($offset)->limit($pagesize)->orderBy($this->addSort($sort));
        $this->addFilter($qb,$filter);
        return $qb->get();
    }

    public function count($filter=null) {
        $qb=$this->numrows();
        $this->addFilter($qb,$filter);
        return $qb->count();
    }

    public function listAttributes() {
        $cname = $this->loadModel("EVA");
        $model = new $cname();
        return $model->attributes($this);
    }
}
