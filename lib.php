<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * This is a repository class used to browse Amazon S3 content.
 *
 * @since 2.0
 * @package    repository
 * @subpackage riak
 * @copyright  2012 Alice Kaerast
 * @author     Alice Kaerast <alice@kaerast.info>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('riak.php');

class repository_riak extends repository {

    /**
     * Constructor
     * @param int $repositoryid
     * @param object $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
        $this->hostname = get_config('riak', 'hostname');
        $this->port = get_config('riak', 'port');
        $this->r = new RiakClient($this->hostname, $this->port);
    }

    /**
     * Get Riak file list
     *
     * @param string $path
     * @return array The file list and options
     */
    public function get_listing($path = '') {
        global $CFG, $OUTPUT;
        if (empty($this->hostname)) {
            die(json_encode(array('e'=>get_string('needhostname', 'repository_riak'))));
        }
        $list = array();
        $list['list'] = array();
        // the management interface url
        $list['manage'] = false;
        // dynamically loading
        $list['dynload'] = true;
        // the current path of this list.
        // set to true, the login link will be removed
        $list['nologin'] = true;
        // set to true, the search button will be removed
        $list['nosearch'] = true;
        $tree = array();
        if (empty($path)) {
            $buckets = $this->r->buckets();
            foreach ($buckets as $bucket) {
                $folder = array(
                    'title' => $bucket->getName(),
                    'children' => array(),
                    'thumbnail'=>$OUTPUT->pix_url('f/folder-32')->out(false),
                    'path'=>$bucket->getName()
                    );
                $tree[] = $folder;
            }
        } else {
            $bucket = $this->r->bucket($path);
            $contents = $bucket->getKeys(); // we should expect this to be slow
            foreach ($contents as $file) {
                $info = $bucket->get($file);
                $tree[] = array(
                    'title'=>$file,
                    'source'=>$path.'/'.$file,
                    'thumbnail' => $OUTPUT->pix_url(file_extension_icon($file['name'], 32))->out(false)
                    );
            }
        }

        $list['list'] = $tree;

        return $list;
    }

    /**
     * Download Riak files to moodle
     *
     * @param string $filepath
     * @param string $file The file path in moodle
     * @return array The local stored path
     */
    public function get_file($filepath, $file) {
        global $CFG;
        $arr = explode('/', $filepath);
        $bucket   = $this->r->bucket($arr[0]);
        $filename = $arr[1];
        $path = $this->prepare_file($file);
        $content = $bucket->getBinary($filename);
        $fh = fopen($path,'w');
        fwrite($fh, $content->getData());
        fclose($fh);
        return array('path'=>$path);
    }

    /**
     * Riak doesn't require login
     *
     * @return bool
     */
    public function check_login() {
        return true;
    }

    /**
     * This library doesn't do searching yet
     *
     * @return bool
     */
    public function global_search() {
        return false;
    }

    public static function get_type_option_names() {
        return array('hostname', 'port', 'pluginname');
    }

    public function type_config_form($mform) {
        parent::type_config_form($mform);
        $strrequired = get_string('required');
        $mform->addElement('text', 'hostname', get_string('hostname', 'repository_riak'));
        $mform->addElement('text', 'port', get_string('port', 'repository_riak'));
        $mform->addRule('hostname', $strrequired, 'required', null, 'client');
        $mform->addRule('port', $strrequired, 'required', null, 'client');
    }

    /**
     * Riak plugins doesn't support return links of files
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL;
    }
}
