<?php

/**
 * Wp-Presence API Interface
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

 class API {
    public function createNonceText() {
        $user = wp_get_current_user();        
        if(!empty($user)) {
            return "wppresence".$user->ID;
        }
        return "wppresence";
    }

    public function authenticate($nonce) {
        error_log('checking nonce '.$nonce.' using '.$this->createNonceText());
        $result = wp_verify_nonce( $nonce, $this->createNonceText() );
        if(!($result === 1 || $result === 2)) {
            error_log('die because nonce does not match');
            die(403);
        }

        if( ! current_user_can( 'manage_wppresence' ) ) {
            error_log("unauthenticated");
            die(403);
        }
    }
    private function fromGet($var, $def = null)
    {
        if (isset($_GET[$var])) return $_GET[$var];
        return $def;
    }
    private function fromPost($var, $def = null)
    {
        if (isset($_POST[$var])) return $_POST[$var];
        return $def;
    }

    public function resolve() {
        error_log("ajax resolver");
        $json = file_get_contents('php://input');
        $data = json_decode($json,true);
        error_log('resolving call: '.json_encode($data));

        if(empty($data) || !isset($data['path'])) {
            // see if we have the proper GET requests for a download
            error_log(json_encode($_GET));
            if (!empty($this->fromGet("action")) && !empty($this->fromGet("nonce"))) {
                $retval = $this->handleGet($this->fromGet("action"), $this->fromGet("nonce"));
            }

            error_log('die because no path nor nonce');
            die(403);
        } 
        else {
            $retval = $this->handlePost($data);
        }
        if (!isset($retval["error"])) {
            wp_send_json_success($retval);
        } else {
            wp_send_json_error($retval);
        }
        wp_die();
    }

    private function handleGet($action,$nonce) {
        $this->authenticate($nonce);
        if ($action == "wppresence") {
            $modelname = $this->fromGet("model");
            $this->export($modelname);
        }    
        die(403);
    }

    private function export($model) {
        $base = $this->loadModel("Item");
        $template = $base->selectAll(0,0,array("all"=>true,"type"=>"template","name"=>$model),"n");
        if(empty($template) && sizeof($template)==0) die(403);
        $template=$template[0];
        $template=new $base($template);

        $models=$base->selectAll(0,0,array("all"=>true, "type"=>$template->name),"n");
        $newmodels=array();
        $ids=array();
        $attrbag=array();
        $presencebag=array();
        foreach($models as $m) {
            $m=new $base($m);
            $ids[]=$m->getKey();
            $attrbag["i".$m->getKey()]=array();
            $presencebag["i" . $m->getKey()] = array();
            $newmodels[]=$m;
        }
        $models=$newmodels;
        $modelattributes=$template->listAttributes();
        $header=array("ID","name","created","modified","deleted");
        foreach($modelattributes as $a) {
            $header[]=$a->name;
        }

        $eva = $this->loadModel("EVA");
        $attributes=$eva->selectAllAttributes($ids);
        foreach($attributes as $a) {
            $key="i".$a->item_id;
            $attrbag[$key][$a->name]=$a;
        }

        $alldates=array();
        $presence = $this->loadModel("Presence");
        $allmarks = $presence->byItems($ids);
        foreach($allmarks as $mark) {
            $key="i".$mark->item_id;
            $dt=strftime("%F",strtotime($mark->created));
            $alldates[$dt]=true;
            $presencebag[$key][$dt]=$mark;
        }

        $header[]="";
        $dates=array_keys($alldates);
        sort($dates);
        foreach($dates as $d) $header[]=$d;

        $data=array();
        $data[]=$header;
        $defattribute=$this->loadModel("EVA");
        foreach($models as $m) {
            $line = array();
            $line[]=$m->getKey();
            $line[]=$m->name;
            $line[]= strftime('%F %T', strtotime($m->created));
            $line[]= !empty($m->modified) ? strftime('%F %T', strtotime($m->modified)) : '';
            $line[]= !empty($m->softdeleted) ? strftime('%F %T', strtotime($m->softdeleted)) : '';

            $key="i".$m->getKey();
            foreach($modelattributes as $ta) {
                $a = isset($attrbag[$key][$ta->name]) ? $attrbag[$key][$ta->name] : $defattribute;
                $line[]=$a->getValue($ta,$attrbag[$key]);
            }

            $line[]="";
            foreach($dates as $d) {
                $v = isset($presencebag[$key][$d]) ? "X": "";
                $line[]=$v;
            }
            $data[]=$line;
        }

        header('Content-Disposition: attachment; filename="' . $template->name . '.csv";');
        header('Content-Type: application/csv; charset=UTF-8');

        $f = fopen('php://output', 'w');
        foreach ($data as $line) {
            fputcsv($f, $line, "\t");
        }
        fpassthru($f);
        fclose($f);
        ob_flush();
        exit();
    }

    private function handlePost($data) {
        $modeldata = isset($data['model']) ? $data['model'] : array();
        error_log("modeldata is ".json_encode($modeldata));
        $offset = isset($modeldata['offset']) ? intval($modeldata['offset']) : 0;
        $pagesize = isset($modeldata['pagesize']) ? intval($modeldata['pagesize']) : 20;
        $filter = isset($modeldata['filter']) ? $modeldata['filter'] : array();
        $sort = isset($modeldata['sort']) ? $modeldata['sort'] : "";
        $special = isset($modeldata['special']) ? $modeldata['special'] : "";
        $nonce = isset($data['nonce']) ? $data['nonce'] : null;

        $path=$data['path'];
        if(empty($path)) {
            $path="index";
        }
        $path=explode('/',trim($path,'/'));
        if(!is_array($path) || sizeof($path) == 0) {
            $path=array("index");
        }

        $retval=array();
        switch($path[0]) {
        default:
        case "index":
            // unsupported paths are unauthenticated
            die(403);
            break;
        case "user":
            // these paths are unauthenticated
            if($path[1] == "login") {
                $username = $modeldata["username"];
                $password = $modeldata["password"];

                $user = wp_get_current_user();
                error_log("user is ".json_encode($user));
                if( ! current_user_can( 'manage_wppresence' ) ) {
                    error_log("trying authentication");
                    $user = wp_signon(array("user_login"=>$username,"user_password"=>$password,"remember"=>true));
                    if(! is_wp_error( $user ) ) {
                        wp_set_current_user($user->ID);
                    }
                }

                if ( current_user_can( 'manage_wppresence' ) ) {
                    error_log("current user can manage presence");
                    $retval=array("loggedin"=>true);
                }
                else {
                    $retval["error"]="login failed";
                }
                error_log("returning ".json_encode($retval));
            }
            else if($path[1] == "logout") {
                wp_logout();
                $retval=array("success"=>true);
            }
            else if($path[1] == "session") {
                if ( current_user_can( 'manage_wppresence' ) ) {
                    $retval=array("loggedin"=>true, "nonce"=>wp_create_nonce($this->createNonceText()));
                }
                else {
                    $retval["error"]="login failed";
                }
            }
            break;
        // full-fledged CRUD
        case "item":
        case "eva":
        case "presence":
            $this->authenticate($nonce);
    
            switch($path[0]) {
            case 'item': $model = $this->loadModel("Item"); break;
            case 'eva': $model = $this->loadModel("EVA"); break;
            case 'presence': $model = $this->loadModel("Presence"); break;
            }
                
            if(isset($path[1]) && $path[1] == "save") {
                $retval=array_merge($retval, $this->save($model,$modeldata));
            }
            else if(isset($path[1]) && $path[1] == "delete") {
                $retval=array_merge($retval, $this->delete($model,$modeldata));
            }
            else if(isset($path[1]) && $path[1] == "softdelete" && $path[0] == "item") {
                $retval = array_merge($retval, $model->softDelete($model, $modeldata));
            }
            else if(isset($path[1]) && $path[1] == "mark") {
                $retval=array_merge($retval, $model->mark($modeldata["model"]["id"],$modeldata["date"],empty($modeldata["checked"]) ? false: true, $modeldata['state']));
            }
            else {
                $retval=array_merge($retval, $this->listAll($model,$offset,$pagesize,$filter,$sort,$special));
            }
            break;
        }
        return $retval;
    }

    private function save($model, $data) {
        $retval=array();
        if(!$model->saveFromObject($data)) {
            error_log('save failed');
            $retval["error"]=true;
            $retval["messages"]=$model->errors;
        }
        else {
            error_log('save successful');
            $retval["id"] = $model->{$model->pk};
        }
        return $retval;
    }

    private function delete($model, $data) {
        $retval=array();
        if(!$model->delete($data['id'])) {
            $retval["error"]=true;
            $retval["messages"]=array("Internal database error");
            if(isset($model->errors) && is_array($model->errors)) {
                $retval["messages"]=$model->errors;
            }
        }
        else {
            $retval["id"] = $model->{$model->pk};
        }
        return $retval;
    }

    private function listAll($model,$offset,$pagesize,$filter,$sort,$special) {
        return $this->listResults($model, $model->selectAll($offset,$pagesize,$filter,$sort,$special), $model->count($filter,$special));
    }

    private function listResults($model, $lst,$total=null, $noexport=FALSE) {
        if($total === null) {
            $total = sizeof($lst);
        }

        $retval=array();
        $retval["list"]=array();

        if(!empty($lst) && is_array($lst)) {
            array_walk($lst,function($v,$k) use (&$retval,$model,$noexport) {
                $retval["list"][]=$noexport ? $v : $model->export($v);
            });
            $retval["total"] = $total;
        }
        else {
            error_log('empty result, checking errors');
            global $wpdb;
            $str = mysqli_error( $wpdb->dbh );
            error_log('ERROR:' .$str);
            $retval['list']=array();
            $retval['total']=0;
        }
        return $retval;
    }

    private function loadModel($name) {
        require_once(__DIR__ . '/models/base.php');
        error_log('requiring model '.$name);
        require_once(__DIR__ . "/models/".strtolower($name).".php");
        error_log('instantiation');
        $name="\\WPPresence\\$name";
        return new $name();
    }
}