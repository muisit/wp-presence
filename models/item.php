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
    public $fields=array("id","name","type","created","creator","modified","modifier","state","softdeleted","deletor","config");
    
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
        "config"=>"config"
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
        "softdeleted" => "skip",
        "deletor" => "skip",
        // specials
        "attributes"=> "contains=EVA,attributes_list",
        "a1"=>"skip",
        "a2"=>"skip",
        "a3"=>"skip",
        "presence"=>"skip",
        "checked"=>"skip",
        "config" => "json"
    );

    public function __construct($id=null) {
        parent::__construct($id);
    }

    public function export($result=null) {
        $retval = parent::export($result);
        if(isset($retval['config'])) {
            $retval['config']=json_decode($retval['config']);
        }
        return $retval;
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
        //error_log("postsave for item, testing attributes_list: ".(isset($this->attributes_list)?"set":"not set"));
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

        if(isset($this->_ori_fields["config"])) {
            $cfg1=json_decode($this->config);
            $cfg2=json_decode($this->_ori_fields["config"]);
            if($cfg1 !== FALSE && $cfg2 !== FALSE && isset($cfg1->range) && isset($cfg2->range)) {
                if($cfg1->range != $cfg2->range) {
                    $this->updateRangeSets($cfg1->range,$cfg2->range);
                }
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
            case 'a':
                $i++;
                $idx=$sort[$i];
                $orderBy[]="a$idx asc";
                break;
            case 'A':
                $i++;
                $idx=$sort[$i];
                $orderBy[]="a$idx desc";
                break;
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
        if(isset($filter['ids'])) {
            $qb->where_in('id',$filter['ids']);
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
                $range=1;
                if(isset($filter["type"])) {
                    $template = $this->templateByType($filter["type"]);
                    if(!empty($template)) {
                        error_log("config is ".json_encode($template->config));
                        $cfg=json_decode($template->config);
                        if($cfg!== FALSE && isset($cfg->range)) {
                            $range=$cfg->range;
                        }
                    }
                }
                $start = $this->rangeToSet($range,$withpresence);
                $end=strftime('%F',strtotime($start) + 24*60*60);
                if($range > 360) {
                    $end = strftime('%Y-01-01',strtotime($start) + 367 * 24*60*60);
                }
                else if($range > 180) {
                    $end = strftime('%Y-%m-01',strtotime($start) + 186 * 24*60*60);
                }
                else if($range > 90) {
                    $end = strftime('%Y-%m-01',strtotime($start) + 94*24*60*60);
                }
                else if($range > 30) {
                    $end = strftime('%Y-%m-01',strtotime($start) + 32*24*60*60);
                }
                error_log("joining presence on ".$withpresence);
                $qb->join("wppres_presence","p","i.id=p.item_id and p.created>='$start' and p.created<'$end'","left");
                $qb->select("max(p.state) as presence");
                $qb->groupBy("i.id, i.name,i.type,i.created,i.creator,i.modified,i.modifier,i.state,i.softdeleted,i.deletor,i.config");
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
        $item = new Item($itemid);
        $item->load();
        $model = $this->createModel("Presence");        
        // always attempt to delete any existing element
        if(!in_array($state,array("present","absent"))) {
            $state="present";
        }
        $model->mark($item,$date,$ispresent,$state);
        return array("state"=>$state);
    }

    public function rangeSet($date) {
        error_log("config is ".json_encode($this->config));
        $range=1;
        if(isset($this->config)) {
            $cfg=json_decode($this->config);
            if($cfg!==FALSE && isset($cfg->range)) {
                $range=$cfg->range;
            }
        }
        error_log("creating range set for range $range");
        return $this->rangeToSet($range,$date);
    }

    public function rangeToSet($range,$date) {
        if($range > 360) {
            $date = strftime('%Y-01-01',strtotime($date));
        }
        else if($range > 180) {
            $m=intval(strftime('%m',strtotime($date)));
            if($m<7) $date = strftime('%Y-01-01',strtotime($date));
            else $date = strftime('%Y-07-01',strtotime($date));
        }
        else if($range > 90) {
            $m=intval(strftime('%m',strtotime($date)));
            if($m<4) $date = strftime('%Y-01-01',strtotime($date));
            else if($m<7) $date = strftime('%Y-04-01',strtotime($date));
            else if($m<10) $date = strftime('%Y-07-01',strtotime($date));
            else $date = strftime('%Y-10-01',strtotime($date));
        }
        else if($range > 30) {
            $date =strftime('%Y-%m-01',strtotime($date));
        }
        else {
            $date = strftime('%F',strtotime($date));
        }
        return $date;
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

        // add all missing template elements and set the correct types
        $template = $this->templateByType($obj["type"] ?? "none");
        if(!empty($template)) {
            $template_attributes = $template->listAttributes();
            $newlist=[];
            $abyname=[];
            foreach($obj['attributes'] as $a) {
                $abyname[$a['name']]=$a;
            }

            foreach($template_attributes as $tattr) {
                $a=[];
                if(isset($abyname[$tattr->name])) {
                    $a = $abyname[$tattr->name];
                }
                else {
                    $a=[
                        "value"=>$tattr->getValue($tattr,null)
                    ];
                }
                $a['name']=$tattr->name;
                $a['item_id']=$obj['id'];
                $a['type']=$tattr->type;
                $a['remark']=null;
                $a['sorting']=$tattr->sorting;

                // do not save the computed values
                if(!in_array($a['type'], ['byear','category'])) {
                    $newlist[]=$a;
                }
            }

            $obj['attributes']=$newlist;
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

    public function templateByType($type) {
        $sql=$this->select('*')->from($this->table)->where("type","template")->where("name",$type)->get();
        if(!empty($sql) && is_array($sql) && sizeof($sql)>0) {
            return new Item($sql[0]);
        }
        return null;
    }

    public function getTemplate() {
        if($this->type === "template") return $this;
        return $this->templateByType($this->type);
    }

    private function updateRangeSets($newrange,$oldrange) {
        $model=$this->createModel("Presence");
        $model->updateRangeSets($this,intval($newrange));
    }
}
