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
use core_external\external_single_structure;
use core_external\external_value;
use core_grades\component_gradeitem as gradeitem;
use core_grades\component_gradeitems;
use core_user;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/form/singlepoint/lib.php');

/**
 * Save marks from the forum grader's grading panel.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store extends external_api {
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
            'notifyuser' => new external_value(PARAM_BOOL, 'Whether to notify the user or not', VALUE_DEFAULT, false),
            'formdata' => new external_value(PARAM_RAW, 'The serialised form data representing the grade'),
        ]);
    }

    /**
     * Store the marks and return the fresh panel data.
     *
     * @param string $component
     * @param int $contextid
     * @param string $itemname
     * @param int $gradeduserid
     * @param bool $notifyuser
     * @param string $formdata
     * @return array
     */
    public static function execute(
        string $component,
        int $contextid,
        string $itemname,
        int $gradeduserid,
        bool $notifyuser,
        string $formdata
    ): array {
        global $USER;
        [
            'component' => $component,
            'contextid' => $contextid,
            'itemname' => $itemname,
            'gradeduserid' => $gradeduserid,
            'notifyuser' => $notifyuser,
            'formdata' => $formdata,
        ] = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'contextid' => $contextid,
            'itemname' => $itemname,
            'gradeduserid' => $gradeduserid,
            'notifyuser' => $notifyuser,
            'formdata' => $formdata,
        ]);
        $context = context::instance_by_id($contextid);
        self::validate_context($context);
        if (!component_gradeitems::is_valid_itemname($component, $itemname)) {
            throw new coding_exception("The '{$itemname}' item is not valid for the '{$component}' component");
        }
        $gradeitem = gradeitem::instance($component, $context, $itemname);
        if (!$gradeitem->is_grading_enabled()) {
            throw new moodle_exception("Grading is not enabled for {$itemname} in this context");
        }
        $gradeduser = core_user::get_user($gradeduserid, '*', MUST_EXIST);
        $gradeitem->require_user_can_grade($gradeduser, $USER);
        if ($gradeitem->get_advanced_grading_method() !== 'singlepoint') {
            throw new moodle_exception("The {$itemname} item in {$component}/{$contextid} does not use a single-point rubric");
        }
        $data = [];
        parse_str($formdata, $data);
        $gradeitem->store_grade_from_formdata($gradeduser, $USER, (object) $data);
        if ($notifyuser) {
            $gradeitem->send_student_notification($gradeduser, $USER);
        }
        return fetch::get_fetch_data($gradeitem, $gradeduser);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return fetch::execute_returns();
    }
}
