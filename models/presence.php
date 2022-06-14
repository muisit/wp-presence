<?php

/**
 * Wp-Presence Presence Model
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

 class Presence extends Base {
    public $table = "wppres_presence";
    public $pk="id";
    public $fields=array("id","item_id","created","state","creator","rangeset");
    public $rules=array(
        "id" => "skip",
        "item_id" => "required|int|model=Item",
        "created" => "required|datetime",
        "creator" => "skip",
        "state" => "required|trim|max=20",
        "remark" => "trim",
        "rangeset"=>"skip"
    );

    public function __construct($id=null) {
        parent::__construct($id);
    }

    private function addSort($sort) {
        if(empty($sort)) $sort="C";
        $orderBy=array();
        for($i=0;$i<strlen($sort);$i++) {
            $c=$sort[$i];
            switch($c) {
            default:
            case 'i': $orderBy[]="id asc"; break;
            case 'I': $orderBy[]="id desc"; break;
            case 't': $orderBy[]="item_id asc"; break;
            case 'T': $orderBy[]="item_id desc"; break;
            case 'c': $orderBy[]="created asc"; break;
            case 'C': $orderBy[]="created desc"; break;
            case 's': $orderBy[]="state asc"; break;
            case 'S': $orderBy[]="state desc"; break;
            }
        }
        return $orderBy;
    }

    private function addFilter($qb, $filter,$specials) {
        $item=null;
        if(isset($filter["item_id"])) {
            error_log("item id is set");
            $cname=$this->loadModel('Item');
            $item=new $cname($filter["item_id"]);
            if(!empty($item)) {
                $item->load();
                if(!$item->isNew()) {
                    $qb->where("item_id",$item->getKey());
                }
                else {
                    return;
                }
            }
        }

        if(isset($filter["type"])) {
            error_log("type is set");
            $cname=$this->loadModel('Item');
            $model=new $cname();
            $item = $model->templateByType($filter['type']);
        }

        foreach($specials as $special_str) {
            if(strpos($special_str,"full presence") === 0) {
                $withpresence = strftime('%F',strtotime(substr($special_str,14)));
                error_log("selecting presence around ".$withpresence);
                $range=1;
                if(!empty($item)) {
                    $cfg=json_decode($item->config);
                    if($cfg !== FALSE && isset($cfg->range)) {
                        error_log("found configuration for range ".$item->config);
                        $range=$cfg->range;
                    }
                }
                error_log("range is $range");
                $start=31 * 24 * 60 * 60;
                if($range > 30) {
                    // months or quarters, show 2 years total
                    $start = 2 * 365 * 24 * 60 * 60;
                    if($range > 180) {
                        // years, half-years: show 10 years
                        $start = 10 * 365 * 24 * 60 * 60;
                    }
                }
                
                $qb->where("created",">",strftime("%F", strtotime($withpresence) - $start));
                $qb->where("created","<=",strftime("%F", strtotime($withpresence) + 2*24*60*60));
                $qb->where_in("item_id",function($qb2) use($item) {
                    $qb2->select('id')->from('wppres_item')->where('type',$item->name);                    
                });
                $qb->groupBy("id,item_id,state,remark,rangeset");
            }
        }
    }

    public function selectAll($offset=0,$pagesize=0,$filter='',$sort='', $special='') {
        $qb = $this->select('*')->offset($offset)->limit($pagesize)->orderBy($this->addSort($sort));
        $specials=explode('/',$special);
        $this->addFilter($qb,$filter, $specials);
        return $qb->get();
    }

    public function count($filter=null, $special=null) {
        $qb=$this->numrows();
        $specials=explode('/',$special);
        $this->addFilter($qb,$filter,$specials);
        return $qb->count();
    }

    public function byItem($item) {
        $id = is_object($item) ? $item->id : intval($item);
        $qb = $this->select('*')->where("item_id",$id)->orderBy($this->addSort("C"));
        return $qb->get();
    }

    public function byItems($ids) {
        $qb = $this->select('*')->where_in("item_id",$ids)->orderBy($this->addSort("tc"))->where("created",">",strftime("%F %T",time()-365*24*60*60));
        return $qb->get();
    }

    public function mark($item, $date, $ispresent,$state) {
        $template=$item->getTemplate();
        $rangeset=$template->rangeSet($date);
        $this->query()->where("item_id",$item->getKey())->where("rangeset",$rangeset)->delete();
        $user = wp_get_current_user();
        if($ispresent) {
           $this->query()->from("wppres_presence")->set(array(
               "item_id"=>$item->getKey(),
               "created" => $date,
               "creator" => ($user && $user->ID) ? $user->ID : -1,
               "state" => $state,
               "rangeset"=>$rangeset,
               "remark" => null 
           ))->insert();
        }
    }

    public function updateRangeSets($item, $range) {
        // this only works on relatively small sets... for larger sets do no change the range setting
        $res=$this->select('p.*')->from('wppres_presence p')->join("wppres_item","i","i.id=p.item_id")->where("i.type",$item->name)->get();
        $ids=array();
        if(!empty($res)) {
            foreach($res as $row) {
                $rangeset = $item->rangeToSet($range,$row->created);
                error_log("setting rangeset to $rangeset");
                $this->query()->set("rangeset",$rangeset)->where("id",$row->id)->update();
                $ids[]=$row->item_id;
            }
        }

        $res=$this->select('item_id, max(created) as created, rangeset, count(*) as cnt')->where_in("item_id",$ids)->groupBy("item_id,rangeset")->having("count(*) > 1")->get();
        if(!empty($res)) {
            foreach($res as $row) {
                $this->query()->where("rangeset",$row->rangeset)->where("item_id",$row->item_id)->where("created","<>",$row->created)->delete();
            }
        }
    }

}
