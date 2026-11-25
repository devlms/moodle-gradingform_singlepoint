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
 * Backup of single-point rubrics.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds criteria to grading definitions and marks to grading instances.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_gradingform_singlepoint_plugin extends backup_gradingform_plugin {
    /**
     * Criteria of the definition.
     *
     * @return backup_plugin_element
     */
    protected function define_definition_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../method', 'singlepoint');
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);
        $criteria = new backup_nested_element('singlepointcriteria');
        $criterion = new backup_nested_element(
            'singlepointcriterion',
            ['id'],
            ['sortorder', 'shortname', 'description', 'descriptionformat', 'maxscore']
        );
        $wrapper->add_child($criteria);
        $criteria->add_child($criterion);
        $criterion->set_source_table('gradingform_singlepoint_crit', ['definitionid' => backup::VAR_PARENTID]);
        return $plugin;
    }

    /**
     * Marks of the instance.
     *
     * @return backup_plugin_element
     */
    protected function define_instance_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../../../method', 'singlepoint');
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);
        $fillings = new backup_nested_element('fillings');
        $filling = new backup_nested_element('filling', ['id'], ['criterionid', 'achieved', 'remark', 'remarkformat']);
        $wrapper->add_child($fillings);
        $fillings->add_child($filling);
        $filling->set_source_table('gradingform_singlepoint_fill', ['instanceid' => backup::VAR_PARENTID]);
        return $plugin;
    }
}
