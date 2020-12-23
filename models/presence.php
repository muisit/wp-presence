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
    public $fields=array("id","item_id","created","state","creator");
    public $rules=array(
        "id" => "skip",
        "item_id" => "required|int|model=Item",
        "created" => "required|datetime",
        "creator" => "skip",
        "state" => "required|trim|max=20",
        "remark" => "trim",
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
        if(isset($filter["item_id"])) {
            $qb->where("item_id",intval($filter["item_id"]));
        }

        foreach($specials as $special_str) {
            if(strpos($special_str,"full presence") === 0) {
                $withpresence = strftime('%F',strtotime(substr($special_str,14)));
                error_log("selecting presence around ".$withpresence);
                $qb->where("created",">",strftime("%F", strtotime($withpresence) - 29*24*60*60));
                $qb->where("created","<=",strftime("%F", strtotime($withpresence) + 25*60*60));
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

}
