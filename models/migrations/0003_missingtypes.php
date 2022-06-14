<?php

namespace WPPresence;

class Migration0003 extends MigrationObject {
    public function up() {
        global $wpdb;
        $results = $wpdb->get_results("select * from ".$wpdb->base_prefix."wppres_eva where type='' or type is NULL");

        require_once(__DIR__ . '/../base.php');
        require_once(__DIR__ . "/../item.php");
        $item=new \WPPresence\Item();
        $templates=$item->selectAll(0,null,array("type"=>"template","all"=>true),'',"with attributes");
        $tBI = array();
        foreach($templates as $t) {
            $tBI[$t->name]=$t;
        }
        $items=array();
        if(empty($results)) return true;

        foreach($results as $row) {
            $id = intval($row->item_id);
            $key="i".$id;
            if(!isset($items[$key])) {
                $items[$key]=new \WPPresence\Item($id);
                $items[$key]->load();
            }
            $tid=$items[$key]->type;
            $t=isset($tBI[$tid]) ? $tBI[$tid] : null;
            if(!empty($t)) {
                foreach($t->attributes as $a) {
                    if($a["name"] == $row->name) {
                        $attr = new \WPPresence\EVA($row);
                        $attr->type = $a["type"];
                        $attr->save();
                    }
                }
            }
        }
        return true;
    }

    public function down() {
        return true;
    }
}