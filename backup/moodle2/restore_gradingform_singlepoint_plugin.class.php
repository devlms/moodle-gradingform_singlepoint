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

/**
 * Restore of single-point rubrics.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores criteria and marks.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_gradingform_singlepoint_plugin extends restore_gradingform_plugin {
    /**
     * Paths in the definition.
     *
     * @return restore_path_element[]
     */
    protected function define_definition_plugin_structure() {
        return [new restore_path_element(
            'gradingform_singlepoint_criterion',
            $this->get_pathfor('/singlepointcriteria/singlepointcriterion')
        )];
    }

    /**
     * Paths in the instance.
     *
     * @return restore_path_element[]
     */
    protected function define_instance_plugin_structure() {
        return [new restore_path_element('gradingform_singlepoint_filling', $this->get_pathfor('/fillings/filling'))];
    }

    /**
     * Restore one criterion.
     *
     * @param array $data
     */
    public function process_gradingform_singlepoint_criterion($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->definitionid = $this->get_new_parentid('grading_definition');
        $newid = $DB->insert_record('gradingform_singlepoint_crit', $data);
        $this->set_mapping('gradingform_singlepoint_criterion', $oldid, $newid);
    }

    /**
     * Restore one mark.
     *
     * @param array $data
     */
    public function process_gradingform_singlepoint_filling($data) {
        global $DB;
        $data = (object) $data;
        $data->instanceid = $this->get_new_parentid('grading_instance');
        $data->criterionid = $this->get_mappingid('gradingform_singlepoint_criterion', $data->criterionid);
        if ($data->criterionid) {
            $DB->insert_record('gradingform_singlepoint_fill', $data);
        }
    }
}
