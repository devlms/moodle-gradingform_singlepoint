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

namespace gradingform_singlepoint\grades\grader\gradingpanel\external;

use coding_exception;
use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_grades\component_gradeitem as gradeitem;
use core_grades\component_gradeitems;
use core_user;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/form/singlepoint/lib.php');

/**
 * Data for the single-point rubric in the forum grader's grading panel.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_ALPHANUMEXT, 'The name of the component'),
            'contextid' => new external_value(PARAM_INT, 'The ID of the context being graded'),
            'itemname' => new external_value(PARAM_ALPHANUM, 'The grade item itemname being graded'),
            'gradeduserid' => new external_value(PARAM_INT, 'The ID of the user show'),
        ]);
    }

    /**
     * Fetch the rubric and the current marks for a user.
     *
     * @param string $component
     * @param int $contextid
     * @param string $itemname
     * @param int $gradeduserid
     * @return array
     */
    public static function execute(string $component, int $contextid, string $itemname, int $gradeduserid): array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/gradelib.php');
        [
            'component' => $component,
            'contextid' => $contextid,
            'itemname' => $itemname,
            'gradeduserid' => $gradeduserid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'contextid' => $contextid,
            'itemname' => $itemname,
            'gradeduserid' => $gradeduserid,
        ]);
        $context = context::instance_by_id($contextid);
        self::validate_context($context);
        if (!component_gradeitems::is_valid_itemname($component, $itemname)) {
            throw new coding_exception("The '{$itemname}' item is not valid for the '{$component}' component");
        }
        $gradeitem = gradeitem::instance($component, $context, $itemname);
        if ($gradeitem->get_advanced_grading_method() !== 'singlepoint') {
            throw new moodle_exception("The {$itemname} item in {$component}/{$contextid} does not use a single-point rubric");
        }
        $gradeduser = core_user::get_user($gradeduserid, '*', MUST_EXIST);
        if ($gradeduserid != $USER->id) {
            $gradeitem->require_user_can_grade($gradeduser, $USER);
        }
        return self::get_fetch_data($gradeitem, $gradeduser);
    }

    /**
     * Build the panel data.
     *
     * @param gradeitem $gradeitem
     * @param stdClass $gradeduser
     * @return array
     */
    public static function get_fetch_data(gradeitem $gradeitem, stdClass $gradeduser): array {
        global $USER;
        $hasgrade = $gradeitem->user_has_grade($gradeduser);
        $grade = $gradeitem->get_formatted_grade_for_user($gradeduser, $USER);
        $instance = $gradeitem->get_advanced_grading_instance($USER, $grade);
        if (!$instance) {
            throw new moodle_exception('error:gradingunavailable', 'grading');
        }
        $controller = $instance->get_controller();
        $definition = $controller->get_definition();
        $filling = $instance->get_singlepoint_filling();
        $labels = \gradingform_singlepoint_controller::level_labels();

        $criteria = [];
        foreach ($definition->singlepoint_criteria as $id => $criterion) {
            $current = $filling['criteria'][$id] ?? null;
            $levels = [];
            foreach ($labels as $value => $label) {
                $levels[] = ['value' => $value, 'label' => $label, 'checked' => $current && $current['level'] === $value];
            }
            $criteria[] = [
                'id' => $id,
                'name' => format_string($criterion['shortname']),
                'description' => format_text($criterion['description'] ?? '', FORMAT_PLAIN),
                'maxscore' => (float) $criterion['maxscore'],
                'level' => $current ? $current['level'] : null,
                'levellabel' => $current ? $labels[$current['level']] : '',
                'remark' => $current ? (string) $current['remark'] : '',
                'levels' => $levels,
            ];
        }
        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->get_grade_item()->id, 'userid' => $gradeduser->id]);
        return [
            'templatename' => 'gradingform_singlepoint/grades/grader/gradingpanel',
            'hasgrade' => $hasgrade,
            'grade' => [
                'instanceid' => $instance->get_id(),
                'criteria' => $criteria,
                'scoring' => get_string('scoring', 'gradingform_singlepoint', (int) $controller->get_options()['notyetpercent']),
                'usergrade' => $grade->usergrade,
                'maxgrade' => max(array_keys($controller->get_grade_range())),
                'gradedby' => $gradegrade ? fullname(core_user::get_user($gradegrade->usermodified)) : null,
                'timecreated' => $grade->timecreated,
                'timemodified' => $grade->timemodified,
            ],
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'templatename' => new external_value(PARAM_SAFEPATH, 'The template to use when rendering this data'),
            'hasgrade' => new external_value(PARAM_BOOL, 'Does the user have a grade?'),
            'grade' => new external_single_structure([
                'instanceid' => new external_value(PARAM_INT, 'The id of the current grading instance'),
                'criteria' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Criterion id'),
                    'name' => new external_value(PARAM_RAW, 'Criterion name'),
                    'description' => new external_value(PARAM_RAW, 'What meeting the standard looks like'),
                    'maxscore' => new external_value(PARAM_FLOAT, 'Points for meeting the standard'),
                    'level' => new external_value(PARAM_INT, 'Chosen level: 0 not yet, 1 meets, 2 beyond', VALUE_OPTIONAL),
                    'levellabel' => new external_value(PARAM_TEXT, 'Name of the chosen level'),
                    'remark' => new external_value(PARAM_RAW, 'Comment on this criterion'),
                    'levels' => new external_multiple_structure(new external_single_structure([
                        'value' => new external_value(PARAM_INT, 'Level value'),
                        'label' => new external_value(PARAM_TEXT, 'Level name'),
                        'checked' => new external_value(PARAM_BOOL, 'Whether this level is chosen'),
                    ])),
                ])),
                'scoring' => new external_value(PARAM_TEXT, 'How levels turn into points'),
                'usergrade' => new external_value(PARAM_RAW, 'Current user grade'),
                'maxgrade' => new external_value(PARAM_RAW, 'Max possible grade'),
                'gradedby' => new external_value(PARAM_RAW, 'The assumed grader of this grading instance'),
                'timecreated' => new external_value(PARAM_INT, 'The time that the grade was created'),
                'timemodified' => new external_value(PARAM_INT, 'The time that the grade was last updated'),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }
}
