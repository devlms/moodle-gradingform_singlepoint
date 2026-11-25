<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace gradingform_singlepoint\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * Privacy provider: marks and comments are exported and deleted through core_grading.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_grading\privacy\gradingform_provider_v2, \core_privacy\local\metadata\provider {
    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('gradingform_singlepoint_fill', [
            'instanceid' => 'privacy:metadata:instanceid',
            'criterionid' => 'privacy:metadata:criterionid',
            'achieved' => 'privacy:metadata:achieved',
            'remark' => 'privacy:metadata:remark',
        ], 'privacy:metadata:fillings');
        return $collection;
    }

    /**
     * Export the marks of one grading instance.
     *
     * @param \context $context
     * @param int $instanceid
     * @param array $subcontext
     */
    public static function export_gradingform_instance_data(\context $context, int $instanceid, array $subcontext) {
        global $DB;
        $sql = "SELECT f.id, c.shortname, c.description, c.maxscore, f.achieved, f.remark
                  FROM {gradingform_singlepoint_fill} f
                  JOIN {gradingform_singlepoint_crit} c ON c.id = f.criterionid
                 WHERE f.instanceid = :instanceid
              ORDER BY c.sortorder";
        $records = $DB->get_records_sql($sql, ['instanceid' => $instanceid]);
        if (!$records) {
            return;
        }
        $labels = [
            get_string('levelnotyet', 'gradingform_singlepoint'),
            get_string('levelmeets', 'gradingform_singlepoint'),
            get_string('levelbeyond', 'gradingform_singlepoint'),
        ];
        $rows = [];
        foreach ($records as $record) {
            $rows[] = (object) [
                'criterion' => $record->shortname,
                'standard' => $record->description,
                'points' => (float) $record->maxscore,
                'mark' => $labels[(int) $record->achieved] ?? '',
                'comment' => $record->remark,
            ];
        }
        $subcontext = array_merge($subcontext, [get_string('pluginname', 'gradingform_singlepoint'), $instanceid]);
        writer::with_context($context)->export_data($subcontext, (object) $rows);
    }

    /**
     * Delete the marks of the given grading instances.
     *
     * @param array $instanceids
     */
    public static function delete_gradingform_for_instances(array $instanceids) {
        global $DB;
        $DB->delete_records_list('gradingform_singlepoint_fill', 'instanceid', $instanceids);
    }
}
