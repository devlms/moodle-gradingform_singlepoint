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
 * Single-point rubric: the grading instance class.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');

/**
 * One student's single-point rubric, filled by one rater.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_singlepoint_instance extends gradingform_instance {
    /** @var array|null cached filling */
    protected $filling = null;

    /** @var array criterion id => true for criteria without a valid level */
    protected $validationerrors = [];

    /**
     * Delete this incomplete instance and its filling.
     */
    public function cancel() {
        global $DB;
        parent::cancel();
        $DB->delete_records('gradingform_singlepoint_fill', ['instanceid' => $this->get_id()]);
    }

    /**
     * Copy the instance with its filling.
     *
     * @param int $raterid
     * @param int $itemid
     * @return int the new instance id
     */
    public function copy($raterid, $itemid) {
        global $DB;
        $instanceid = parent::copy($raterid, $itemid);
        foreach ($this->get_singlepoint_filling()['criteria'] as $criterionid => $row) {
            $DB->insert_record('gradingform_singlepoint_fill', ['instanceid' => $instanceid, 'criterionid' => $criterionid,
                'achieved' => $row['level'], 'remark' => $row['remark'], 'remarkformat' => $row['remarkformat']]);
        }
        return $instanceid;
    }

    /**
     * Whether nothing was chosen or written.
     *
     * @param array $elementvalue
     * @return bool
     */
    public function is_empty_form($elementvalue) {
        foreach ($this->get_controller()->get_definition()->singlepoint_criteria as $id => $criterion) {
            $row = $elementvalue['criteria'][$id] ?? [];
            if ((isset($row['level']) && $row['level'] !== '') || trim($row['remark'] ?? '') !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Every criterion needs one of the three levels.
     *
     * @param array $elementvalue
     * @return bool
     */
    public function validate_grading_element($elementvalue) {
        $this->validationerrors = [];
        $levels = [gradingform_singlepoint_controller::LEVEL_NOTYET, gradingform_singlepoint_controller::LEVEL_MEETS,
            gradingform_singlepoint_controller::LEVEL_BEYOND];
        foreach (array_keys($this->get_controller()->get_definition()->singlepoint_criteria) as $id) {
            $level = $elementvalue['criteria'][$id]['level'] ?? null;
            if ($level === null || $level === '' || !in_array((int) $level, $levels, true) || !is_numeric($level)) {
                $this->validationerrors[$id] = true;
            }
        }
        return !$this->validationerrors;
    }

    /**
     * The stored filling: criterion id => [id, level, remark, remarkformat].
     *
     * @param bool $force reload from the database
     * @return array
     */
    public function get_singlepoint_filling($force = false) {
        global $DB;
        if ($this->filling === null || $force) {
            $this->filling = ['criteria' => []];
            foreach ($DB->get_records('gradingform_singlepoint_fill', ['instanceid' => $this->get_id()]) as $record) {
                $this->filling['criteria'][$record->criterionid] = ['id' => $record->id, 'criterionid' => $record->criterionid,
                    'level' => (int) $record->achieved, 'remark' => $record->remark, 'remarkformat' => $record->remarkformat];
            }
        }
        return $this->filling;
    }

    /**
     * Save the data from the grading form.
     *
     * @param array $data
     */
    public function update($data) {
        global $DB;
        $current = $this->get_singlepoint_filling();
        parent::update($data);
        foreach ($data['criteria'] ?? [] as $criterionid => $row) {
            if (!isset($row['level']) || $row['level'] === '') {
                continue;
            }
            $record = ['achieved' => (int) $row['level'], 'remark' => trim($row['remark'] ?? ''), 'remarkformat' => FORMAT_PLAIN];
            if (isset($current['criteria'][$criterionid])) {
                $record['id'] = $current['criteria'][$criterionid]['id'];
                $DB->update_record('gradingform_singlepoint_fill', $record);
            } else {
                $record += ['instanceid' => $this->get_id(), 'criterionid' => $criterionid];
                $DB->insert_record('gradingform_singlepoint_fill', $record);
            }
        }
        foreach ($current['criteria'] as $criterionid => $row) {
            if (!isset($data['criteria'][$criterionid])) {
                $DB->delete_records('gradingform_singlepoint_fill', ['id' => $row['id']]);
            }
        }
        $this->get_singlepoint_filling(true);
    }

    /**
     * Remove the filling of an attempt.
     *
     * @param array $data
     */
    public function clear_attempt($data) {
        global $DB;
        foreach (array_keys($data['criteria'] ?? []) as $criterionid) {
            $DB->delete_records('gradingform_singlepoint_fill', ['criterionid' => $criterionid, 'instanceid' => $this->get_id()]);
        }
    }

    /**
     * The grade for the gradebook, mapped from points to the activity's grade range.
     *
     * @return float|int
     */
    public function get_grade() {
        $controller = $this->get_controller();
        if (!($scores = $controller->get_min_max_score()) || $scores['maxscore'] <= $scores['minscore']) {
            return -1;
        }
        $graderange = array_keys($controller->get_grade_range());
        if (empty($graderange)) {
            return -1;
        }
        sort($graderange);
        $mingrade = $graderange[0];
        $maxgrade = $graderange[count($graderange) - 1];

        $criteria = $controller->get_definition()->singlepoint_criteria;
        $points = 0;
        foreach ($this->get_singlepoint_filling()['criteria'] as $criterionid => $row) {
            if (isset($criteria[$criterionid])) {
                $points += $controller->score_for_level($criteria[$criterionid]['maxscore'], $row['level']);
            }
        }
        $offset = ($points - $scores['minscore']) / ($scores['maxscore'] - $scores['minscore']) * ($maxgrade - $mingrade);
        if ($controller->get_allow_grade_decimals()) {
            return $offset + $mingrade;
        }
        return round($offset, 0) + $mingrade;
    }

    /**
     * The grading form element.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        if (!$gradingformelement->_flagFrozen) {
            $mode = gradingform_singlepoint_controller::DISPLAY_EVAL;
        } else if ($gradingformelement->_persistantFreeze) {
            $mode = gradingform_singlepoint_controller::DISPLAY_EVAL_FROZEN;
        } else {
            $mode = gradingform_singlepoint_controller::DISPLAY_REVIEW;
        }
        $controller = $this->get_controller();
        $value = $gradingformelement->getValue();
        $html = '';
        if ($value === null) {
            $value = $this->get_singlepoint_filling();
        } else if (!$this->validate_grading_element($value)) {
            $html .= html_writer::div(
                get_string('notcompleted', 'gradingform_singlepoint'),
                'alert alert-danger',
                ['role' => 'alert']
            );
        }
        $current = $this->get_current_instance();
        if ($current && $current->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::div(
                get_string('needregrade', 'gradingform_singlepoint'),
                'alert alert-warning',
                ['role' => 'alert']
            );
        }
        $html .= html_writer::div($controller->get_formatted_description(), 'gradingform_singlepoint-description');
        $html .= $controller->get_renderer($page)->display_singlepoint(
            $controller->get_definition()->singlepoint_criteria,
            $controller->get_options(),
            $mode,
            $gradingformelement->getName(),
            $value,
            $this->validationerrors
        );
        return $html;
    }
}
