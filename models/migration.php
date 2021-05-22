<?php

/**
 * Wp-Presence Migration Model
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

 class Migration extends Base {
    public $table = "wppres_migration";
    public $pk="id";
    public $fields=array("id","name","status");
    public $rules=array(
        "id" => "skip",
        "name" => "skip",
        "status"=>"int"
    );

    public function __construct($id=null) {
        parent::__construct($id);
    }

    public function activate() {
        if(!$this->tableExists($this->table)) {
            $this->createTable($this->table, "( 
                `id` INT NOT NULL AUTO_INCREMENT , 
                `name` VARCHAR(255) NOT NULL , 
                `status` INT NOT NULL,
                PRIMARY KEY (`id`)) ENGINE = InnoDB; ");
        }

        // load all the migration objects from the migrations subfolder
        $objects=scandir(dirname(__FILE__).'/migrations');
        error_log("migration objects are ".json_encode($objects));
        $allmigrations=array();
        foreach($objects as $filename) {
            $path = dirname(__FILE__)."/migrations/".$filename;
            error_log("loading $filename / $path");
            if($filename != '.' && $filename != '..' && is_file($path)) {
                error_log("loading class file");
                $model = $this->loadClassFile($path);
                if(!empty($model)) {
                    error_log("model checks DB");
                    $model->checkDb();
                    $allmigrations[$model->name]=$model;
                }
            }
        }

        foreach($allmigrations as $model) {
            $dbmodel = $model->find();
            //error_log(json_encode($dbmodel->export()));
            //error_log("migration " . $dbmodel->name . " has status " . $dbmodel->status);
            if(intval($dbmodel->status) == 0) {
                $retval = $this->execute($dbmodel,$model);
                if($retval !== 1) {
                    // failure to execute a migration means we need to stop
                    error_log("breaking off migrations at ".$dbmodel->name);
                    break;
                }
            }
        }
    }

    public function uninstall() {
        $allmigrations = array_reverse($this->selectAll());
        foreach ($allmigrations as $model) {
            if ($model->status == '1') {
                $retval = $this->execute(new Migration($model)); // this should run the 'down' version
                if ($retval !== 0) {
                    // failure to execute a migration is no reason to stop
                    error_log("failed rolling back a migration at " . $model->name);
                }
            }
        }

        // finally, remove our own table
        global $wpdb;
        $tablename = $wpdb->base_prefix . $this->table;
        $sql="DROP TABLE `$tablename`;";
        $wpdb->query($sql);
    }

    private function loadClassFile($filename) {
        $classes = get_declared_classes();
        error_log("including migration");
        require_once($filename);
        $diff = array_diff(get_declared_classes(), $classes);
        $class = reset($diff);
        error_log("diff is ".json_encode($class));
        if(!empty($class)) {
            error_log("creating class $class");
            $model = new $class();
            $base = basename($filename,".php");
            $model->name=$base;
            return $model;
        }
        return null;
    }

    public function execute($data,$model=null) {
        $retval=-1;
        ob_start();
        try {
            if(empty($model)) {
                $model = $this->loadClassFile(dirname(__FILE__)."/migrations/". $data->name . '.php');
            }
            if (!empty($model)) {
                error_log("checking state of migration ". $data->status);
                if (intval($data->status) == 0) {
                    error_log("running up");
                    if($model->up()) {
                        error_log("saving state");
                        $data->status=1;
                        $data->save();
                        $retval = 1;
                    }
                } else {
                    if($model->down()) {
                        $data->status=0;
                        $data->save();
                        $retval=0;
                    }
                }
            }
        }
        catch(Exception $e) {
            error_log("caught exception on migration: ".$e->getMessage());
        }
        ob_end_clean();
        return $retval;
    }

    public function selectAll($offset=0,$pagesize=0,$filter='',$sort='', $special='') {
        $qb = $this->select('*');
        if($pagesize>0) {
            $qb = $qb->offset($offset)->limit($pagesize);
        }
        return $qb->orderBy(array("name"))->get();
    }

    public function count($filter=null) {
        return $this->select("count(*) as cnt")->count();
    }

    public function export($result=null) {
        if(empty($result)) {
            $result=$this;
        }
        return array(
            "id" => $result->id,
            "name" => $result->name,
            "status" => $result->status
        );
    }

    public function tableName($name) {
        global $wpdb;
        return $wpdb->base_prefix . $name;
    }

    public function tableExists($tablename) {
        global $wpdb;
        $table_name = $this->tableName($tablename);
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));
        return $wpdb->get_var($query) == $table_name;
    }

    public function columnExists($tablename, $columnname) {
        global $wpdb;
        $query = $wpdb->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = %s', $wpdb->esc_like($this->tableName($tablename)), $wpdb->esc_like($columnname));
        return $wpdb->get_var($query) == $columnname;
    }

    public function createTable($tablename,$content) {
        global $wpdb;
        $table_name = $this->tableName($tablename);
        return $wpdb->query("CREATE TABLE $table_name $content;");
    }

    public function dropTable($tablename) {
        global $wpdb;
        $table_name = $this->tableName($tablename);
        return $wpdb->query("DROP TABLE $table_name;");
    }    
}

class MigrationObject extends Migration
{
    public function save() {
        // when we save the MigrationObject, it is always new and unexecuted
        $this->{$this->pk} = null;
        $this->state='N';
        parent::save();

        // to actually interface with the DB object, first Find it and then
        // use that base model
    }

    public function exists() {
        $results = $this->numrows()->where('name', $this->name)->count();
        return $results == 0;
    }

    public function checkDb() {
        if (!$this->exists() == 0) {
            // this migrates filename and classname to the database
            $this->save();
        }
    }

    public function find() {
        $res = $this->select("*")->where("name",$this->name)->get();
        if(sizeof($res) > 0) {
            return new Migration($res[0]);
        }
        return new Migration();
    }

    public function rawQuery($txt) {
        global $wpdb;
        return $wpdb->query($txt);
    }

    public function up() {
        error_log("abstract parent UP");
    }

    public function down() {
        error_log("abstract parent DOWN");
    }
}