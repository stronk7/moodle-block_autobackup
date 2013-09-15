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
 * Utility functions for block_autobackup.
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

function block_autobackup_is_activity_page($pagecontext) {
    // No pagecontext, we are not in activity page.
    if (!$pagecontext) {
        return false;
    }

    // The context is not module, we are not in activity page.
    if ($pagecontext->contextlevel !== CONTEXT_MODULE) {
        return false;
    }

    // Arrived here, we are in activity page.
    return true;
}

function block_autobackup_is_database_valid($cmid, $fieldid) {
    global $DB;

    // Only if the database module exists.
    if (!$databasemodule = get_coursemodule_from_id('data', $cmid)) {
        return false;
    }

    // Only if the database has the specified field defined.
    if (!$databasefield = $DB->get_record('data_fields',
            array('dataid' => $databasemodule->instance, 'id' => $fieldid))) {
        return false;
    }

    // Only if the user has perms to access to the target database.
    if (!$databasecontext = context_module::instance($cmid)) {
        return false;
    }

    // Arrived here, the databse is valid.
    return true;
}

function block_autobackup_get_database_link($block) {
    global $DB, $OUTPUT;

    // Only if the user can view the link to the database.
    if (!has_capability('block/autobackup:link', $block->context)) {
        return '';
    }

    // Get the target database id.
    $databasemodule = get_coursemodule_from_id('data', $block->config->cmid);
    $databaseid = $databasemodule->instance;

    // Get the current coursemodule we are viewing.
    $currentmodule = $block->page->cm;

    // Search the database for any fieldid ending with 'view.php?id=$currentcmid'
    $params = array(
        'dataid' => $databaseid,
        'fieldid' => $block->config->fieldid,
        'search' => '%/view.php?id=' . $currentmodule->id);
    $record = $DB->get_record_sql('
            SELECT r.id
            FROM {data_records} r
            JOIN {data_content} c ON (c.recordid = r.id)
            WHERE r.dataid = :dataid
              AND c.fieldid = :fieldid
              AND ' . $DB->sql_like('content', ':search', false), $params);
    // Matching record found, let's create the link.
    if ($record) {
        $url = new moodle_url('/mod/data/view.php', array('d' => $databaseid, 'rid' => $record->id));
        $pix = new pix_icon('help', null, null, array('class' => 'actionicon'));
        $title = get_string('linktoactivityinfo', 'block_autobackup');
        $link = $OUTPUT->action_link($url, null, null, array('title' => $title), $pix);
        $html = html_writer::tag('div', $link, array('class' => 'linktoactivityinfo'));
        return $html;
    }

    // Arrived here, no matching record found. Return nothing.
    return '';
}

function block_autobackup_get_download_link($block) {
    global $OUTPUT;

    // Only if the user can download the activity backup.
    if (!has_capability('block/autobackup:download', $block->context)) {
        return '';
    }

    // Get the current coursemodule we are viewing.
    $currentmodule = $block->page->cm;

    // Let's create the link to download the activity backup.
    $url = new moodle_url('/block/autobackup/download.php', array('cmid' => $currentmodule->id));
    $pix = new pix_icon('t/download', null, null, array('class' => 'actionicon'));
    $title = get_string('linktodownload', 'block_autobackup');
    $link = $OUTPUT->action_link($url, null, null, array('title' => $title), $pix);
    $html = html_writer::tag('div', $link, array('class' => 'linktodownload'));
    return $html;
}
