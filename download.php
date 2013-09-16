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
 * Generate activity backup and download utility for block_autobackup.
 *
 * @package   block_autobackup
 * @copyright 2013 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);

// Verify the course has an "autobackup" block.
$coursectx = context_course::instance($course->id);
$params = array(
    'parentcontextid' => $coursectx->id,
    'blockname' => 'autobackup');
if (!$blockrec = $DB->get_record('block_instances', $params)) {
    die;
}

// Verify the user has perms to download the activity in the block.
$blockctx = context_block::instance($blockrec->id);
if (!has_capability('block/autobackup:download', $blockctx)) {
    die;
}

// Let's calculate the final name of the backup file.
$backupname = core_text::strtolower(core_text::specialtoascii($cm->name));
$backupname = str_replace(' ', '_', $backupname) . '.mbz';

// Check if there is any 1d or newer backup available. We'll be using it
// to avoid much processing.
$cachedfound = false;
$cmcontext = context_module::instance($cm->id);
$fs = get_file_storage();
foreach ($fs->get_area_files($cmcontext->id, 'backup', 'automated', 0) as $file) {
    if ($file->get_filename() == $backupname && (int)$file->get_timemodified() > (time() - (24 * 60 * 60))) {
        $cachedfound = true;
        break;
    }
}

// If there are not valid cached file available, let's generate it.
if (!$cachedfound) {
    // Arrived here, everything is ok, let's generate the backup and download.
    // note it's generated by admin user that has all the required perms. Still
    // the backup is very restricted via settings (no users..).
    $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cmid, backup::FORMAT_MOODLE,
                                backup::INTERACTIVE_NO, backup::MODE_AUTOMATED, get_admin()->id);
    // Set the predefined settings
    $users = $bc->get_plan()->get_setting('users');
    $users->set_value(false);
    $blocks = $bc->get_plan()->get_setting('blocks');
    $blocks->set_value(false);
    $filters = $bc->get_plan()->get_setting('filters');
    $filters->set_value(false);
    $name = $bc->get_plan()->get_setting('filename');
    $name->set_value($backupname);
    // Ready.
    $bc->set_status(backup::STATUS_AWAITING);
    // Perform.
    $bc->execute_plan();
    // Get backup results (stored_file).
    $results = $bc->get_results();
    $file = $results['backup_destination'];
    // Clean.
    $bc->destroy();
}

// Download the file.
send_stored_file($file, 5*60);