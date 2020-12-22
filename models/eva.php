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
        "remark" => "trim",
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

    public function attributes($item) {
        $id = is_object($item) ? $item->id : intval($item);
        $qb = $this->select('*')->where("item_id",$id)->orderBy($this->addSort("s"));
        $attrs=$qb->get();
        $retval=array();
        foreach($attrs as $a) {
            $retval[]=new EVA($a);
        }
        return $retval;
    }

}
