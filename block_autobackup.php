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
 * Main file for block_autobackup.
 *
 * This block allows ANY user to download an activity backup (moodlet)
 * of the activity being viewed. The backup won't include users info, nor
 * other extras. Just the activity itself. Note that, for some activities,
 * this leads to near-empty backups (entries are missing in glossaries, for
 * example), but that's another story not covered by this block.
 *
 * @package   block_autobackup
 * @copyright 2013 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_autobackup extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_autobackup');
    }

    public function specialization() {
        $this->title = '';
    }

    public function has_config() {
        return false;
    }

    public function instance_allow_config() {
        return true;
    }

    public function applicable_formats() {
        return array('all' => true);
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function instance_can_be_docked() {
        return false;
    }

    public function get_content() {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/blocks/autobackup/lib.php');

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // Ensure we have a config
        if (!isset($this->config->cmid)) {
            $this->config->cmid = 0;
        }
        if (!isset($this->config->fieldid)) {
            $this->config->fieldid = 0;
        }

        // Only if we are in an activity page.
        if (!block_autobackup_is_activity_page($this->page->context)) {
            return $this->content;
        }

        // Only if the configured databse is valid and accesible.
        if (!block_autobackup_is_database_valid($this->config->cmid, $this->config->fieldid)) {
            return $this->content;
        }

        // Arrived here, we may have some content to show.
        $output = block_autobackup_get_database_link($this); // The link to the database.
        $output.= block_autobackup_get_download_link($this); // The link to the download.

        // If there are output, let's return it.
        if (!empty($output)) {
            $this->content->text = $output;
        }

        return $this->content;
    }


    protected function is_activity_page() {
        // Get the current context of the page where the block is displayed.
        if (!$context = $this->page->context) {
            return false;
        }

        // The context is not module, we are not in activity page.
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return false;
        }

        // Arrived here, we are in activity page.
        return true;
    }

}
