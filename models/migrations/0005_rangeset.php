<?php

namespace WPPresence;

class Migration0005 extends MigrationObject {
    public function up() {
        global $wpdb;
        if(!$this->columnExists("wppres_presence","rangeset")) {
            $this->rawQuery("ALTER TABLE " . $wpdb->base_prefix . "wppres_presence ADD column rangeset DATETIME NULL;");
            $this->rawQuery("update ".$wpdb->base_prefix . "wppres_presence set rangeset=created");
        }
        return true;
    }

    public function down() {
        global $wpdb;
        if ($this->columnExists("wppres_presence", "rangeset")) {
            $this->rawQuery("ALTER TABLE ". $wpdb->base_prefix . "wppres_presence DROP column rangeset;");
        }
        return true;
    }
}