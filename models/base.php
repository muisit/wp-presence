<?php

/**
 * Wp-Presence Base Model
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

 class Base {

    public $table="";
    public $fields=array();
    public $fieldToExport=array();
    public $rules=array();
    public $pk="";
    public $last_id=-1;

    // validation and saving
    public $errors=null;
    public $model=null;

    private $_state="new";
    private $_ori_fields=array();

    public function __construct($id=null) {
        $this->_state = "new";
        if(!empty($id)) {
            if(is_array($id) || is_object($id)) {
                $this->read($id);
            }
            else {
                $this->{$this->pk} = $id;
                $this->_state="pending";
            }
        }
        if(sizeof($this->fieldToExport) == 0) {
            foreach($this->fields as $fld) {
                $this->fieldToExport[$fld]=$fld;
            }
        }
    }

    public function getKey() {
        return $this->{$this->pk};
    }

    public function setKey($id=null) {
        if($id === null) {
            $id=-1;
        }
        else if($id <=0) {
            $id=-1;
        }
        $this->{$this->pk} = $id;
        $this->_state = $id <= 0 ? "new": "pending";
    }

    public function get($id) {
        $obj = new static($id);
        $obj->load();
        $pk=$obj->pk;
        if(empty($obj->$pk)) {
            return null;
        }
        return $obj;
    }

    public function isNew() {
        return $this->_state == 'new' || $this->{$this->pk} <= 0;
    }

    public function load() {
        if($this->_state == "loaded" || $this->_state == "new") { 
            return;
        }

        global $wpdb;
        $pkval = $this->{$this->pk};
        $sql="select * from " . $wpdb->base_prefix . $this->table." where ".$this->pk."=%d";
        $sql = $wpdb->prepare($sql,array($pkval));
        $results = $wpdb->get_results($sql);

        if(empty($results) || sizeof($results) != 1) {
            $this->{$this->pk} = null;
            $this->_state = "new";
        }
        else {
            $this->read($results[0]);
        }
    }

    public function export($result=null) {
        if(empty($result)) {
            $result=$this;
        }
        $retval=array();
        $this->load();
        foreach($this->fieldToExport as $fld=>$exp) {
            //error_log('exporting field '.$fld.' as '.$exp);            
            if(isset($result->$fld)) {
                $retval[$exp] = $result->$fld;
            }
        }
        return $retval;
    }

    private function read($values) {
        $values=(array)$values;
        $this->_state = "reading";
        $values=(array)$values;
        foreach($this->fields as $fld) {
            if(isset($values[$fld])) {
                $this->{$fld} = $values[$fld];
                $this->_ori_fields[$fld]=$values[$fld];
            }
        }
        $this->_state = "loaded";
        if(!isset($this->{$this->pk}) ||  $this->{$this->pk} < 0) {
            $this->_state = "new";
            $this->_ori_fields=array();
        }
    }

    public function save() {
        $fieldstosave=array();
        foreach($this->fields as $f) {
            if($this->differs($f)) {
                $fieldstosave[$f]=$this->$f;
            }
        }
        if(empty($fieldstosave)) {
            error_log("no fields to save");
        }
        else {
            global $wpdb;
            if($this->isNew()) {
                error_log("inserting new object in ".$this->table);
                $wpdb->insert($wpdb->base_prefix . $this->table,$fieldstosave);
                $this->{$this->pk} = $wpdb->insert_id;
            }
            else {
                error_log("calling update on ".$this->table." for ".json_encode($fieldstosave));
                $wpdb->update($wpdb->base_prefix . $this->table, $fieldstosave, array($this->pk => $this->{$this->pk}));
            }
        }
        // save attached objects
        $this->postSave();

        return true;
    }

    public function postSave() {
        return true;
    }

    public function identical($other) {
        // if id's match, we're identical
        if(!$this->isNew() && $this->{$this->pk} == $other->{$this->pk}) {
            return true;
        }
        // else, compare all fields
        foreach($this->fields as $field) {
            $v1 = $this->{$field};
            $v2 = $other->{$field};

            if(is_bool($v1)) {
                if(!is_bool($v2) || ($v1 !== $v2)) {
                    return false;
                }
            }
            else if(is_numeric($v1)) {
                $v1 = floatval($v1);
                $v2 = floatval($v2);
                if(abs($v1 - $v2) > 0.000000001) {
                    return false;
                }
            }
            else if(strcmp($v1,$v2)) {
                return false;
            }
        }
        return true;
    }

    private function differs($field) {
        if (!property_exists($this, $field)) {
            return false; // unset fields are never different
        }
        if ($field === $this->pk && (!$this->isNew() || $this->{$this->pk} <= 0)) {
            return false; // cannot reset the PK
        }
        if (!isset($this->_ori_fields[$field])) {
            return true; // no original found, so always different
        }

        $value=$this->$field;
        $original = $this->_ori_fields[$field];

        if(is_bool($value)) {
            return !is_bool($original) || ($original !== $value);
        }
        if(is_numeric($value)) {
            $value = floatval($value);
            $original=floatval($original);
            return abs($value-$original) > 0.000000001;
        }
        // if we have a null-allowed field and it is filled/cleared, always differs
        if(  ($value === null && $original !== null)
          || ($original === null && $value !== null)) {
            return true;
        }
        return strcmp(strval($value),$original) != 0;
    }

    public function delete($id=null)
    {
        if($id === null) $id = $this->{$this->pk};
        global $wpdb;
        $retval = $wpdb->delete($wpdb->base_prefix . $this->table, array($this->pk => $id));
        return ($retval !== FALSE || intval($retval) < 1);
    }

    public function __get($key) {
        if(!isset($this->$key) && $this->_state == "pending") {
            $this->load();
        }
        if(isset($this->$key)) {
            return $this->$key;
        }
        return null;
    }

    public function __set($key,$value) {
        if(!isset($this->$key) && $key != $this->pk && $this->_state == "pending") {
            $this->load();
        }
        $this->$key = $value;
    }

    public function query() {
        require_once(__DIR__ . '/querybuilder.php');
        $qb=new QueryBuilder($this);
        return $qb->from($this->table);
    }
    public function select($p=null) {
        return $this->query()->select($p);
    }
    public function numrows() {
        return $this->select('count(*) as cnt');
    }

    public function prepare($query,$values,$dofirst=false) {
        global $wpdb;

        if(!empty($values)) {
            // find all the variables and replace them with proper markers based on the values
            // then prepare the query
            $pattern = "/{[a-f0-9]+}/";
            $matches=array();
            $replvals=array();
            if(preg_match_all($pattern, $query, $matches)) {
                $keys=array_keys($values);
                foreach($matches[0] as $m) {
                    $match=trim($m,'{}');
                    if(in_array($match,$keys)) {
                        $v = $values[$match];
                        if(is_float($v)) {
                            $query=str_replace($m,"%f",$query);
                            $replvals[]=$v;
                        }
                        else if(is_int($v)) {
                            $query=str_replace($m,"%d",$query);
                            $replvals[]=$v;
                        }
                        else if(is_null($v)) {
                            $query=str_replace($m,"NULL",$query);
                        }
                        else {
                            $query=str_replace($m,"%s",$query);
                            $replvals[]="$v";
                        }
                    }
                }
            }

            error_log("SQL: $query");
            error_log("VAL: ".json_encode($replvals));
            $query = $wpdb->prepare($query,$replvals);
        }
        else {
            error_log("SQL: $query");
        }

        $results = $wpdb->get_results($query);
        if($dofirst) {
            if(is_array($results) && sizeof($results)>0) {
                return $results[0];
            }
            return array();
        }
        return $results;
    }

    public function saveFromObject($obj) {
        //error_log('save from object using data '.json_encode($obj));
        require_once(__DIR__ . "/validator.php");
        $validator = new Validator($this);

        if(!$validator->validate($obj)) {
            $this->errors=$validator->errors;
            return false;
        }
        if(!$this->save()) {
            global $wpdb;
            error_log('error saving object to database '.$wpdb->last_error);
            $this->errors = array("Internal database error: ".$wpdb->last_error);
            return false;
        }
        return true;
    }

    public function loadModel($name) {
        require_once(__DIR__ . "/".strtolower($name).".php");
        $cname = "\\WPPresence\\".$name;
        return $cname;
    }
}
 