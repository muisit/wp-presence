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
    public $fields=array("id","name","type","created","creator","modified","modifier","state","softdeleted","deletor");
    
    // list all fields and include the special attributes list
    public $fieldToExport = array(
        "id" => "id",
        "name" => "name",
        "type" => "type",
        "created" => "created",
        "creator" => "creator",
        "modified" => "modified",
        "modifier" => "modifier",
        "softdeleted" => "deleted",
        "deletor" => "deletor",
        //"state" => "state",
        // specials
        "attributes" => "attributes",
        "a1" => "a1",
        "a2" => "a2",
        "a3" => "a3",
        "presence"=>"presence",
    );

    public $rules=array(
        "id" => "skip",
        "name" => "required|trim|lte=255",
        "type" => "required|trim|lte=20",
        "created" => "skip",
        "creator" => "skip",
        "modified" => "skip",
        "modifier" => "skip",
        "state"=>"required|trim|lte=20",
        // specials
        "attributes"=> "contains=EVA,attributes_list",
        "a1"=>"skip",
        "a2"=>"skip",
        "a3"=>"skip",
        "presence"=>"skip",
        "checked"=>"skip"
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
            case 'i': $orderBy[]="i.id asc"; break;
            case 'I': $orderBy[]="i.id desc"; break;
            case 'n': $orderBy[]="i.name asc"; break;
            case 'N': $orderBy[]="i.name desc"; break;
            case 'c': $orderBy[]="i.created asc"; break;
            case 'C': $orderBy[]="i.created desc"; break;
            case 'm': $orderBy[]="i.modified asc"; break;
            case 'M': $orderBy[]="i.modified desc"; break;
            case 'd': $orderBy[]="i.softdeleted asc"; break;
            case 'D': $orderBy[]="i.softdeleted desc"; break;
            case 's': $orderBy[]="i.state asc"; break;
            case 'S': $orderBy[]="i.state desc"; break;
            case 't': $orderBy[]="i.type asc"; break;
            case 'T': $orderBy[]="i.type desc"; break;
            }
        }
        return $orderBy;
    }

    private function addFilter($qb, $filter) {
        if(isset($filter['name']) && !empty(trim($filter['name']))) {
            $name=str_replace("%","%%",trim($filter['name']));
            $qb->where("i.name","like","%$name%");
        }
        if(isset($filter['type']) && !empty(trim($filter['type']))) {
            $name=trim($filter['type']);
            $qb->where("i.type",$name);
        }
        if(!isset($filter['all'])) {
            $qb->where('softdeleted is NULL');
        }
    }

    public function selectAll($offset=0,$pagesize=0,$filter=array(),$sort='', $special='') {
        $filter=(array) $filter;
        $qb = $this->select('i.*')->from($this->table." i")->offset($offset)->limit($pagesize)->orderBy($this->addSort($sort));
        $this->addFilter($qb,$filter);
        $specials=explode('/',$special);
        error_log("specials is ".json_encode($specials));

        $cname = $this->loadModel("EVA");
        if(in_array("include_3",$specials)) {
            error_log("include_3 for ".json_encode($filter));
            // include the first three attributes of this template
            // for that we need to get the template first
            if(isset($filter["type"])) {                
                $item=$this->select('id')->where('type','template')->where('name',$filter['type'])->first();
                error_log("template item is ".json_encode($item));
                $model = new $cname();
                $attrs = $model->attributes($item);
                //error_log("attributes is ".json_encode($attrs));
                $a1=null;
                $a2=null;
                $a3=null;
                if(sizeof($attrs) > 0) {
                    $a1=$attrs[0]->name;
                    if(sizeof($attrs)>1) {
                        $a2=$attrs[1]->name;
                        if(sizeof($attrs)>2) {
                            $a3=$attrs[2]->name;
                        }
                    }
                }
                error_log("attrs $a1 $a2 $a3");
                // by default, select empty values
                $a1select="'' as a1";
                $a2select="'' as a2";
                $a3select="'' as a3";
                if(!empty($a1)) {
                    $qb->join("wppres_eva","a1","i.id=a1.item_id and a1.name='$a1'","left");
                    $a1select="a1.value as a1";
                }
                if(!empty($a2)) {
                    $qb->join("wppres_eva","a2","i.id=a2.item_id and a2.name='$a2'","left");
                    $a2select="a2.value as a2";
                }
                if(!empty($a3)) {
                    $qb->join("wppres_eva","a3","i.id=a3.item_id and a3.name='$a3'","left");
                    $a3select="a3.value as a3";
                }
                $qb->select(array($a1select,$a2select,$a3select));
            }
        }

        $withpresence = null;
        foreach($specials as $special_str) {
            if(strpos($special_str,"with presence") === 0) {
                $withpresence = strftime('%F',strtotime(substr($special_str,14)));
                error_log("joining presence on ".$withpresence);
                $qb->join("wppres_presence","p","i.id=p.item_id and p.created='$withpresence'","left");
                $qb->select("p.state as presence");
            }
        }

        $results = $qb->get();

        if(in_array("with attributes",$specials)) {            
            error_log("loading additional attributes for each item");
            // for each entry, load all the attributes as well
            $cname = $this->loadModel("EVA");
            $model = new $cname();

            $retval = $results;
            $results=array();
            foreach($retval as $values) {
                error_log("item is ".json_encode($values));
                $values->attributes = $model->attributes($values->id,true);
                $results[]=$values;
            }
        }

        return $results;
    }

    public function count($filter=null) {
        $qb=$this->numrows()->from($this->table." i");
        $this->addFilter($qb,$filter);
        return $qb->count();
    }

    public function listAttributes($id=null,$export=false) {
        if($id === null) $id=$this;
        $cname = $this->loadModel("EVA");
        $model = new $cname();
        return $model->attributes($id,$export);
    }

    public function mark($itemid,$date,$ispresent,$state) {
        // always attempt to delete any existing element
        if(!in_array($state,array("present","absent"))) {
            $state="present";
        }
        $this->query()->from("wppres_presence")->where("item_id",$itemid)->where("created",$date)->delete();
        $user = wp_get_current_user();
        if($ispresent) {
           $this->query()->from("wppres_presence")->set(array(
               "item_id"=>$itemid,
               "created" => $date,
               "creator" => ($user && $user->ID) ? $user->ID : -1,
               "state" => $state,
               "remark" => null 
           ))->insert();
        }
        return array("state"=>$state);
    }

    public function saveFromObject($obj) {
        if(!isset($obj['attributes'])) {
            $attrs=$this->listAttributes($obj['id'],true);
            $obj['attributes']=array();
            foreach($attrs as $attr) {
                if(isset($obj[$attr['name']])) {
                    $attr['value']=$obj[$attr['name']];
                    unset($obj[$attr['name']]);
                }
                $obj['attributes'][]=$attr;
            }
        }
        return parent::saveFromObject($obj);
    }

    public function softDelete($model,$modeldata) {
        $id=isset($modeldata['id']) ? intval($modeldata['id']) : -1;
        $dodel = isset($modeldata['softdelete']) ? boolval($modeldata['softdelete']) : true;
        error_log("dodel is ".json_encode($dodel));
        $model=new Item($id);
        $model->load();
        if($model->getKey() > 0 && intval($model->getKey()) == $id) {
            $user = wp_get_current_user();
            if($dodel) {
                $model->deletor = ($user && $user->ID) ? $user->ID : -1;
                $model->softdeleted = strftime('%F %T');
            }
            else {
                $model->deletor=null;
                $model->softdeleted=null;
            }
            if($model->save()) {
                return array("id"=> $model->getKey());
            }
        }            
        return array("error"=>true,"messages"=>"Failed to softdelete");
    }
}
