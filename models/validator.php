<?php

/**
 * Wp-Presence Validator Model
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

class Validator {
    public $model=null;
    public $errors=array();

    public function __construct($model) {
        $this->model = $model;
    }

    private function loadModel($data) {
        if(isset($data['id']) && intval($data['id'])>0) {
            $this->model->setKey($data['id']);
            $this->model->load();

            if($this->model->isNew()) {
                $this->model=null;
            }
        }
        else {
            // model is new
            $this->model->setKey();
        }
    }

    public function validate($data) {
        $this->loadModel($data);
        if(empty($this->model)) {
            $this->errors[]="No object found";
            return false;
        }

        $inToOut=array_flip($this->model->fieldToExport);
        $allgood=true;
        foreach($data as $field=>$value)  {
            $exp = isset($inToOut[$field]) ? $inToOut[$field] : $field;
            $allgood = $this->validateField($exp, $value) && $allgood;
        }

        if(!$allgood) {
            if(!is_array($this->errors) || !sizeof($this->errors)) {
                $this->errors = array("There were errors");
            }
            return false;
        }
        return true;
    }

    public function validateField($field,$value) {
        $rules = isset($this->model->rules[$field]) ? $this->model->rules[$field] : 'fail';
        $label = $field;
        $msg=null;
        if(is_array($rules)) {
            if(isset($rules['label'])) $label=$rules['label'];
            if(isset($rules['message'])) $msg=$rules['message'];
            if(isset($rules['rules'])) {
                $rules=$rules['rules'];
            }
            else {
                if(isset($rules['label'])) unset($rules['label']);
                if(isset($rules['message'])) unset($rules['message']);
            }
        }
        if(is_string($rules)) {
            $rules = explode('|',$rules);
        }
        $allgood = true;
        $isskip = false;
        foreach($rules as $rule) {
            $rule = $this->expandRule($rule,$label,$msg);
            if($rule["rule"] == "skip") {
                $isskip=true;
            }
            else {
                $allgood = $this->validateRule($value,$rule) && $allgood;
            }
        }
        if($allgood && !$isskip) {
            if($this->is_date($value)) {
                $value = sprintf("%04d-%02d-%02d",$value['year'],$value['month'],$value['day']);
            }
            $this->model->{$field} = $value;
        }
        return $allgood;
    }

    public function expandRule($rule,$label,$msg) {
        $ruleelements=array("label" => $label, "message"=>$msg, "parameters"=>array());
        if(is_string($rule)) {
            $rule = explode('=',$rule,2);
            $ruleelements["rule"]=$rule[0];

            if(sizeof($rule) > 1) {
                $ruleelements["parameters"] = explode(',',$rule[1]);
            }
        }
        else {
            $ruleelements = array_merge($rule, $ruleelements);
        }
        return $ruleelements;
    }

    public function validateRule(&$value,$ruleelements) {
        $rule=$ruleelements['rule'];
        $params=$ruleelements['parameters'];
        $msg = isset($ruleelements['message']) ? $ruleelements['message'] : null;
        $label = $ruleelements['label'];
        $p1='';
        $p2='';
        // always pass if we have an empty value and this is not the required rule
        if($rule != 'required' && empty($value)) {
            return true;
        }

        // if the rule is a method on the model, invoke it
        if(method_exists($this->model, $rule)) {
            return $this->model->$rule($this, $value,$ruleelements);
        }
        error_log("checking $rule on ".json_encode($value)."/".json_encode($params));

        $retval=true;
        switch($rule) {
        case 'required':
            // value must be present and have content
            $retval = !(empty($value) && $value !==false);
            if($msg === null) $msg = "{label} is a required field";
            break;
        case 'skip': break;
        case 'fail': 
            $retval = false;
            if($msg === null) $msg = "{label} is an unsupported field";
        case 'int': $value = intval($value); break;
        case 'float':
            // convert and format
            $format = sizeof($params)==1 ? "%".$params[0]."f" : "%f";
            $value = floatval(sprintf($format,floatval($value)));
            break;
        case 'bool':            
            $tst=strtolower($value);
            if($tst == 'y' || $tst == 't' || $tst == 'yes' || $tst == 'true' || $tst == 'on') {
                $value = 'Y';
            }
            else {
                $value = 'N';
            }
        case 'lt':
            if(sizeof($params) == 1) {
                if(is_string($value)) {
                    $p1 = intval($params[0]);
                    if($msg === null) $msg="{label} should contain less than {p1} characters";
                    $retval = strlen($value) < $p1;
                }
                else if(is_numeric($value)) {
                    $p1 = floatval($params[0]);
                    if($msg === null) $msg="{label} should be less than {p1}";
                    $retval = floatval($value) < $p1;
                }
                else if($this->is_date($value)) {
                    $p1 = date_parse($params[0]);
                    $dt1=sprintf("%04d-%02d-%02d",$p1['year'],$p1['month'],$p1['day']);
                    $tm1=strtotime(sprintf("%04d-%02d-%02d",$value['year'],$value['month'],$value['day']));
                    $tm2=strtotime($dt1);
                    $p1=$dt1;
                    if($msg === null) $msg="{label} should be before {p1}";
                    $retval = $tm1 < $tm2;
                }
            }
            break;
        case 'lte':
            if(sizeof($params) == 1) {
                if(is_string($value)) {
                    $p1 = intval($params[0]);
                    if($msg === null) $msg="{label} should contain no more than {p1} characters";
                    $retval = strlen($value) <= $p1;
                }
                else if(is_numeric($value)) {
                    $p1 = floatval($params[0]);
                    if($msg === null) $msg="{label} should be less than or equal to {p1}";
                    $retval = floatval($value) <= $p1;
                }
                else if($this->is_date($value)) {
                    $p1 = date_parse($params[0]);
                    $dt1=sprintf("%04d-%02d-%02d",$p1['year'],$p1['month'],$p1['day']);
                    $tm1=strtotime(sprintf("%04d-%02d-%02d",$value['year'],$value['month'],$value['day']));
                    $tm2=strtotime($dt1);
                    $p1=$dt1;
                    if($msg === null) $msg="{label} should be at or before {p1}";
                    $retval = $tm1 <= $tm2;
                }
            }
            break;
        case 'eq':
            if(sizeof($params) == 1) {
                if(is_string($value)) {
                    $p1 = intval($params[0]);
                    if($msg === null) $msg="{label} should contain exactly {p1} characters";
                    $retval = strlen($value) == $p1;
                }
                else if(is_numeric($value)) {
                    $p1 = floatval($params[0]);
                    if($msg === null) $msg="{label} should be equal to {p1}";
                    $retval = abs(floatval($value) - $p1) < 0.00001;
                }
                else if($this->is_date($value)) {
                    $p1 = date_parse($params[0]);
                    $dt1=sprintf("%04d-%02d-%02d",$p1['year'],$p1['month'],$p1['day']);
                    $tm1=strtotime(sprintf("%04d-%02d-%02d",$value['year'],$value['month'],$value['day']));
                    $tm2=strtotime($dt1);
                    $p1=$dt1;
                    if($msg === null) $msg="{label} should be at {p1}";
                    $retval = $tm1 == $tm2;
                }
            }
            break;
        case 'gt':
            if(sizeof($params) == 1) {
                if(is_string($value)) {
                    $p1 = intval($params[0]);
                    if($msg === null) $msg="{label} should contain more than {p1} characters";
                    $retval = strlen($value) > $p1;
                }
                else if(is_numeric($value)) {
                    $p1 = floatval($params[0]);
                    if($msg === null) $msg="{label} should be more than {p1}";
                    $retval = floatval($value) > $p1;
                }
                else if($this->is_date($value)) {
                    $p1 = date_parse($params[0]);
                    $dt1=sprintf("%04d-%02d-%02d",$p1['year'],$p1['month'],$p1['day']);
                    $tm1=strtotime(sprintf("%04d-%02d-%02d",$value['year'],$value['month'],$value['day']));
                    $tm2=strtotime($dt1);
                    $p1=$dt1;
                    if($msg === null) $msg="{label} should be after {p1}";
                    $retval = $tm1 > $tm2;
                }
            }
            break;
        case 'gte':
            if(sizeof($params) == 1) {
                if(is_string($value)) {
                    $p1 = intval($params[0]);
                    if($msg === null) $msg="{label} should contain no less than {p1} characters";
                    $retval = strlen($value) >= $p1;
                }
                else if(is_numeric($value)) {
                    $p1 = floatval($params[0]);
                    if($msg === null) $msg="{label} should be more than or equal to {p1}";
                    $retval = floatval($value) >= $p1;
                }
                else if($this->is_date($value)) {
                    $p1 = date_parse($params[0]);
                    $dt1=sprintf("%04d-%02d-%02d",$p1['year'],$p1['month'],$p1['day']);
                    $tm1=strtotime(sprintf("%04d-%02d-%02d",$value['year'],$value['month'],$value['day']));
                    $tm2=strtotime($dt1);
                    $p1=$dt1;
                    if($msg === null) $msg="{label} should be at or after {p1}";
                    $retval = $tm1 >= $tm2;
                }
            }
            break;
        case 'trim':
            $value = trim("$value");
            break;
        case 'upper':
            $value = strtoupper($value);
            break;
        case 'ucfirst':
            $value = ucfirst($value);
            break;
        case 'lower':
            $value = strtolower($value);
            break;
        case 'email':  
            $retval = filter_var($value, FILTER_VALIDATE_EMAIL); 
            if($msg === null) $msg = "{label} is not a correct e-mail address";
            break;
        case 'url': 
            $retval = filter_var($value, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED|FILTER_FLAG_HOST_REQUIRED); 
            if($msg === null) $msg = "{label} is not a correct website";
            break;
        case 'date': 
            $value = $this->sanitize_date($value);
            if($value === null) {
                $retval=false;
                if($msg === null) $msg = "{label} is not a date";
                break;
            }
            break;
        case 'datetime': 
            $value = $this->sanitize_datetime($value);
            if($value === null) {
                $retval=false;
                if($msg === null) $msg = "{label} is not a date + time";
                break;
            }
            break;
        case 'json':
            $value = json_encode($value);
            break;
        case 'enum':
            $retval = in_array($value, $params);
            if($msg === null) $msg = "{label} should be one of ".json_encode($params);
            break;
        case 'model':
            $cname = $this->model->loadModel($params[0]);
            try {
                $id=intval($value);
                $attrmodel = new $cname($id);
                $attrmodel->load();

                if($attrmodel->{$attrmodel->pk} != $id) {
                    $retval=false;                    
                }
                if($msg === null) $msg = "Please select a valid value for {label}";
            }
            catch(Exception $e) {
                if($msg === null) $msg = "{label} caused internal model error";
                $retval=false;
            }
            break;
        case 'contains':
            // value is a list of contained models
            $name = $this->model->loadModel($params[0]);
            try {
                $lst=array();
                foreach($value as $objvals) {
                    $id=intval($objvals['id']);
                    if(empty($id)) $id=-1;

                    $obj = new $name($id);
                    $validator=new Validator($obj);

                    $result = $validator->validate($objvals);
                    $retval = $result && $retval;
                    if(!$result && isset($validator->errors) && sizeof($validator->errors)) {
                        $this->errors=array_merge($this->errors,$validator->errors);
                    }
                    $lst[]=$obj;
                }
                $addfield = sizeof($params) > 1 ? $params[1] : "sublist";
                $this->model->$addfield = $lst;
            }
            catch(Exception $e) {
                if($msg === null) $msg = "{label} caused internal model error";
                error_log("caught exception on contained model ".$e->getMessage());
                $retval=false;
            }
            break;
        default:
            if($msg === null) $msg = "Invalid rule {rule} found";
            $retval=false;
            break;
        }

        if($retval == false) {
            $msg = str_replace(array("{label}","{rule}","{p1}","{p2}"),array($label,$rule,$p1,$p2),$msg);
            error_log("validation of rule $rule failed with message $msg");
            $this->errors[] = $msg;
        }
        return $retval;
    }

    protected function sanitize_name($name) {
        // names contain only alphabetic characters, dash, apostrophe and spaces
        return mb_ereg_replace("([^\w \-'])", '', $name);
    }
    protected function sanitize_date($date){
        // we expect yyyy-mm-dd, but we'll let the date_parse function manage this
        $vals = date_parse($date);        
        if($this->is_date($vals)) {
            return $vals;
        }
        return null;
    }

    public function is_date($value) {
        return is_array($value) && isset($value['year']) && isset($value['month']) && isset($value['day'])
            && is_numeric($value['year']) && $value['year']!==false
            && is_numeric($value['month']) && $value['month']!==false
            && is_numeric($value['day']) && $value['day']!==false;
    }

    protected function sanitize_datetime($date){
        // we expect yyyy-mm-dd HH:MM:SS, but we'll let the strtotime function manage this
        $ts = strtotime($date);
        if($ts === false) {
            return null;
        }
        return strftime('%F %T',$ts);
    }

}
  