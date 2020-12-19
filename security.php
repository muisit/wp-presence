<?php

/**
 * Role Based Media Protector Security routines
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

 class Security {
    public function __construct() {
        if (filter_input(INPUT_GET, 'rbam-media')) {
            // find the media file we are supposed to protect
            $media = $this->findMedia();

            if($media === null) {
                // file not found, although the .htaccess redirect should only work for files that exist
                http_response_code(404); 
                exit;
            }

            if($this->authorized($media)) {
                $this->outputFile($media);
            }
            else {
                http_response_code(401); 
                exit;
            }
        }
    }

    private function authorized($media) {
        $wpuploads = wp_get_upload_dir();
        $uploaddir = $wpuploads['basedir'];

        // check that the media file is located under the basedir, so we know for sure
        // we are in the upload directory
        if(mb_strpos($media,$uploaddir) !== 0) {            
            return false;
        }

        // Find media in database and apply authorization
        // The media path is stored in the metadata table and is based on the relative directory
        // of the upload wrt the contents directory (uploaddir)
        $relative_path = ltrim(mb_substr($media,mb_strlen($uploaddir)), DIRECTORY_SEPARATOR);

        $id = $this->findFromPath($relative_path);
        
        if($id === null) {
            // file not found. This could be an image that was resized and cropped and stuff. 
            $id = $this->findFromOriginal($relative_path);
        }

        if($id !== null) {
            $file = get_post($id);
            if(!empty($file)) {
                $data = $meta_value = get_post_meta( $id, "_rbammedia", true );
                if(!empty($data)) {
                    $roles = explode(',',$data);

                    // check that the current user has at least one of the roles
                    $user = wp_get_current_user();
                    $userroles = ( array ) $user->roles;

                    $matches = array_intersect($roles,$userroles);
                    if(empty($matches)) {
                        return false;
                    }

                    // we found at least 1 match, so allow it
                    return true;
                }
                else {
                    // we found the post, but no security metadata, so it is open for public
                    return true;
                }
            }

            // although we found an ID, we could not get the proper post, so this looks like a
            // stray file. As we are not in the business of cleaning up messes, allow access.
            // Note: this could be a security issue: a file that was closed off is now suddenly
            // available after/during the removal of the post node.
            return true;
        }
        else {
            // fallback: all files that are not in the database are publicly accessible
            return true;
        }
    }

    private function findFromOriginal($path) {
        $base = basename($path);
        $basedir = dirname($path);

        global $wpdb;
        $sql  = "SELECT m1.post_id, m1.meta_value, m2.meta_value as value2 ".
                " FROM {$wpdb->postmeta} m1 ".
                " INNER JOIN {$wpdb->postmeta} m2 on m2.post_id=m1.post_id ".
                " WHERE m1.meta_key = %s AND m1.meta_value LIKE %s and m2.meta_key = %s";

        $results = $wpdb->get_results($wpdb->prepare($sql, array(
                '_wp_attachment_backup_sizes', 
                "%$base%",
                '_wp_attachment_metadata',
            )));

        // unknown upload, not in the database
        if(empty($results)) {
            return null;
        }

        // this is what we expect: only one file with such a filename
        if(sizeof($results) == 1) {
            return $results[0]->post_id;
        }

        // several files with the same basename. We need to match the original on the full path
        foreach($results as $r) {
            $value = unserialize($r->meta_value);
            $value2 = dirname($r->value2);

            if(is_array($value) && !empty($value2)) {
                foreach($value as $versions) {
                    if(isset($version['file']) && !strcmp($base,$version['file']) && !strcmp($basedir,$value2)) {
                        return $r->post_id;
                    }
                }
            }
        }
        return null;
    }

    private function findFromPath($path) {
        global $wpdb;
        $sql  = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s";

        $id = $wpdb->get_var($wpdb->prepare($sql, array('_wp_attached_file', ltrim($path, '/'))));

        if(empty($id)) {
            return null;
        }
        return $id;
    }

    // case insensitive file-exists
    // see: https://stackoverflow.com/questions/3964793/php-case-insensitive-version-of-file-exists
    private function fileExists($fileName)
    {
        if (file_exists($fileName)) {
            return $fileName;
        }

        // Handle case insensitive requests            
        $directoryName = dirname($fileName);
        $fileArray = glob($directoryName . '/*', GLOB_NOSORT);
        $fileNameLowerCase = strtolower($fileName);
        foreach ($fileArray as $file) {
            if (strtolower($file) == $fileNameLowerCase) {
                return $file;
            }
        }
        return false;
    }

    private function findMedia() {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        if($uri === null) {
            return null; // failed $_SERVER constitution
        }
        $uri = mb_strtolower($uri);

        $file = $this->fileExists(ABSPATH . $uri);
        if($file === false) {
            return null;
        }
        else {
            return realpath($file);
        }
    }

    private function outputFile($media) {
        $mimetype = wp_check_filetype(basename($media));
        if(isset($mimetype['ext']) && !empty($mimetype['ext'])) {
            if (function_exists('mime_content_type')) {
                $mime = mime_content_type($media);
            } else {
                $mime = 'application/octet-stream';
            }

            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($media));

            // Calculate etag
            $last_modified = gmdate('D, d M Y H:i:s', filemtime($media));
            $etag = '"' . md5( $last_modified ) . '"';

            header("Last-Modified: $last_modified GMT");
            header("ETag: {$etag}");
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 100000000) . ' GMT');

            // Finally read the file
            readfile($media);
        }
        else {
            http_response_code(403);
        }
        exit;
    }
 }
