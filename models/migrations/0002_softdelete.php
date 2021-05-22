<?php

namespace WPPresence;

class Migration0002 extends MigrationObject {
    public function up() {
        global $wpdb;
        if(!$this->columnExists("wppres_item","deleted")) {
            $this->rawQuery("ALTER TABLE " . $wpdb->base_prefix . "wppres_item ADD column softdeleted datetime NULL;");
        }
        if (!$this->columnExists("wppres_item", "deletor")) {
            $this->rawQuery("ALTER TABLE " . $wpdb->base_prefix . "wppres_item ADD column deletor int(11) NULL;");
        }
        return true;
    }

    public function down() {
        global $wpdb;
        if ($this->columnExists("wppres_item", "deleted")) {
            $this->rawQuery("ALTER TABLE ". $wpdb->base_prefix . "wppres_item DROP column softdeleted;");
        }
        if ($this->columnExists("wppres_item", "deletor")) {
            $this->rawQuery("ALTER TABLE ". $wpdb->base_prefix . "wppres_item DROP column deletor;");
        }
        return true;
    }
}