<?php

/**
 * Role Based Media Protector Editor routines
 *
 * @package             rbam-media
 * @author              Michiel Uitdehaag
 * @copyright           2020 Michiel Uitdehaag for muis IT
 * @licenses            GPL-3.0-or-later
 *
 * This file is part of rbam-media.
 *
 * rbam-media is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * rbam-media is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with rbam-media.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Gracefully based loosely on the AAM Protected Media Files plugin
 * by Vasyl Martyniuk <vasyl@vasyltech.com>
 */
namespace RBAM;

class Editor {

    private function sanitizeMeta($value) {
        $retval=[];
        $fields = explode(',',$value);

        if(is_array($fields) && sizeof($fields)>0) {
            $roleskey = [];
            foreach($fields as $r) $roleskey[$r]=true;
    
            $retval=[];
            $allroles = wp_roles()->roles;
            foreach($allroles as $r => $value) {
                if(isset($roleskey[$value["name"]])) {
                    $retval[]=$r;
                }
            }
        }
        return implode(',',$retval);
    }

    public function save($post_id) {
        if (  !isset( $_POST['rbammedia_class_nonce'] ) 
           || !wp_verify_nonce( $_POST['rbammedia_class_nonce'], basename( __FILE__ ) ) ) {
            return $post_id;
        }

        $post = get_post($post_id);

        $post_type = get_post_type_object( $post->post_type );
        if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) {
            return $post_id;
        }

        $new_meta_value = $_POST['rbammedia_roles'] ?? '';
        $new_meta_value = $this->sanitizeMeta($new_meta_value);

        $meta_key = '_rbammedia';
        $meta_value = get_post_meta( $post_id, $meta_key, true );

        if(!empty($new_meta_value) && empty($meta_value)) {
            add_post_meta( $post_id, $meta_key, $new_meta_value, true );
        }
        elseif ( !empty($new_meta_value) && $new_meta_value != $meta_value ) {
            update_post_meta( $post_id, $meta_key, $new_meta_value );
        }
        elseif ( empty($new_meta_value) && !empty($meta_value) ) {
            delete_post_meta( $post_id, $meta_key, $meta_value );
        }
        return $post_id;
    }

    public function metaBox($type, $post) {
        if( "attachment" == $type ) {
		    add_meta_box( 'rbammedia_metabox', __('Authorization'), array($this,'createMetaBox'), null, 'side');
        }
    }

    private function convertRolesToDisplay($metavalue) {
        $roles = explode(',',$metavalue);
        if(empty($roles)) {
            return '';
        }
        $retval=[];
        $allroles = wp_roles()->roles;
        foreach($roles as $r) {
            if(isset($allroles[$r])) {
                $retval[]=$allroles[$r]["name"];
            }
        }
        return implode(',',$retval);
    }

    public function createMetaBox($post) {
        wp_enqueue_script('rbammedia-scripts', plugins_url('/metabox.js', __FILE__), array('jquery'), "1.0.0", false);

        // the following is largely copied from wp-admin/includes/meta-boxes.php, post_tags_meta_box()
        $comma = _x( ',', 'tag delimiter' );
        $name = "rbammedia_roles";
        $user_can_assign_roles = true; // TODO: check on capabilities
        $meta_key = '_rbammedia';
        $terms_to_edit = get_post_meta( $post->ID, $meta_key, true );
        $terms_to_edit = $this->convertRolesToDisplay($terms_to_edit);

?>
        <div class="rbammediabox" id="rbammediabox">
          <div id="rbammedia-security">
            <?php wp_nonce_field( basename( __FILE__ ), 'rbammedia_class_nonce' ); ?>
            <?php do_action( 'rbammedia_security_actions', $post ); ?>
        
            <div id="select-role-or-user" class='tagsdiv'>
              <?php _e('Select roles'); ?>
              <div class="jaxtag">
	            <div class="nojs-tags hide-if-js">
		          <label for="<?php echo $name; ?>"><?php _e('Add or Remove items'); ?></label>
		          <p><textarea name="<?php echo "$name"; ?>" rows="3" cols="20" class="the-tags" id="<?php echo $name; ?>" <?php disabled( ! $user_can_assign_roles ); ?> aria-describedby="new-security-<?php echo $name; ?>-desc"><?php echo str_replace( ',', $comma . ' ', $terms_to_edit ); // textarea_escaped by esc_attr() ?></textarea></p>
	            </div>
	            <?php if ( $user_can_assign_roles ) : ?>
	            <div class="ajaxtag hide-if-no-js">
		          <label class="screen-reader-text" for="new-security-<?php echo $name; ?>"><?php _e('Add new role'); ?></label>
		          <input type="text" id="new-security-<?php echo $name; ?>" class="newtag form-input-tip" size="16" autocomplete="off" aria-describedby="new-security-<?php echo $name; ?>-desc" value="" />		          
	            </div>
                <?php endif; ?>
                <div class='clear'></div>
	          </div>
	          <ul class="tagchecklist" role="list"></ul>
            </div>
          </div>
        </div>
<?php
    }

    public function ajaxSearch() {
        // largely copied from wp-admin/includes/ajax-actions.php, wp_ajax_ajax_tag_search

        // get the query term and cleanup
        $s = wp_unslash( $_GET['q'] );
        $comma = _x( ',', 'tag delimiter' );
        if ( ',' !== $comma ) {
            $s = str_replace( $comma, ',', $s );
        }
        if ( false !== strpos( $s, ',' ) ) {
            $s = explode( ',', $s );
            $s = $s[ count( $s ) - 1 ];
        }
        $s = trim( $s );
        
        $term_search_min_chars = 2;
        if ( strlen( $s ) < $term_search_min_chars ) {
            wp_die();
        }
    
        $roles = wp_roles()->get_names();
        $results=[];
        foreach($roles as $role) {
            $rname = mb_strtolower($role);
            if(mb_strpos($rname, $s) === 0) {
                $results[]=$role;
            }
        }
        sort($results);
        echo join( "\n",  $results);
        wp_die();        
    }

};

