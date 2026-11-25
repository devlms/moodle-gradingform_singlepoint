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

namespace gradingform_singlepoint;

use gradingform_controller;
use gradingform_instance;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Tests for the single-point rubric controller and instance.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \gradingform_singlepoint_controller
 * @covers     \gradingform_singlepoint_instance
 */
final class controller_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $assign;
    /** @var \context_module */
    protected $context;
    /** @var \stdClass */
    protected $teacher;
    /** @var \stdClass */
    protected $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->student = $gen->create_and_enrol($course, 'student');
        $this->assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->context = \context_module::instance($this->assign->cmid);
        $this->setUser($this->teacher);
    }

    /**
     * Get the controller for the assignment's submissions area.
     *
     * @return \gradingform_singlepoint_controller
     */
    protected function controller(): \gradingform_singlepoint_controller {
        $manager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        $manager->set_active_method('singlepoint');
        return $manager->get_controller('singlepoint');
    }

    /**
     * Define a ready form with the given criteria.
     *
     * @param array $criteria list of [shortname, maxscore]
     * @param array $options
     * @return \gradingform_singlepoint_controller
     */
    protected function define(array $criteria, array $options = []): \gradingform_singlepoint_controller {
        $controller = $this->controller();
        $data = (object) [
            'name' => 'Essay',
            'description_editor' => ['text' => 'How the essay is marked', 'format' => FORMAT_HTML, 'itemid' => 0],
            'status' => gradingform_controller::DEFINITION_STATUS_READY,
            'singlepoint' => ['criteria' => [], 'options' => $options + \gradingform_singlepoint_controller::get_default_options()],
        ];
        foreach ($criteria as $i => [$name, $max]) {
            $data->singlepoint['criteria']['NEWID' . ($i + 1)] = [
                'sortorder' => $i + 1, 'shortname' => $name, 'description' => "$name meets the standard", 'maxscore' => $max,
            ];
        }
        $controller->update_definition($data);
        return $this->controller();
    }

    /**
     * Ids of the criteria in order.
     *
     * @param \gradingform_singlepoint_controller $controller
     * @return int[]
     */
    protected function ids(\gradingform_singlepoint_controller $controller): array {
        return array_keys($controller->get_definition()->singlepoint_criteria);
    }

    /**
     * Grade the student with levels per criterion, in order.
     *
     * @param \gradingform_singlepoint_controller $controller
     * @param int[] $levels
     * @return float|int the grade
     */
    protected function grade(\gradingform_singlepoint_controller $controller, array $levels) {
        $filling = ['criteria' => []];
        foreach ($this->ids($controller) as $i => $id) {
            $filling['criteria'][$id] = ['level' => $levels[$i], 'remark' => "remark $i"];
        }
        // The activity sets the grade range before grading (mod_assign does it from its grade setting).
        $controller->set_grade_range(make_grades_menu(100), false);
        $instance = $controller->get_or_create_instance(0, $this->teacher->id, 1);
        return $instance->submit_and_get_grade($filling, 1);
    }

    public function test_definition_is_saved_and_loaded(): void {
        $controller = $this->define([['Thesis', 10], ['Evidence', 20]]);
        $this->assertTrue($controller->is_form_available());
        $criteria = array_values($controller->get_definition()->singlepoint_criteria);
        $this->assertSame(['Thesis', 'Evidence'], array_column($criteria, 'shortname'));
        $this->assertEquals([10, 20], array_column($criteria, 'maxscore'));
        $this->assertSame('Thesis meets the standard', $criteria[0]['description']);
        $this->assertSame(['minscore' => 0, 'maxscore' => 30.0, 'modulegrade' => 100], $controller->get_min_max_score());
    }

    public function test_meets_earns_full_points_and_not_yet_none(): void {
        $controller = $this->define([['Thesis', 10], ['Evidence', 20]]);
        $this->assertEquals(33, $this->grade($controller, [
            \gradingform_singlepoint_controller::LEVEL_MEETS, \gradingform_singlepoint_controller::LEVEL_NOTYET]));
    }

    public function test_beyond_earns_the_same_as_meets(): void {
        $controller = $this->define([['Thesis', 10], ['Evidence', 20]]);
        $this->assertEquals(100, $this->grade($controller, [
            \gradingform_singlepoint_controller::LEVEL_BEYOND, \gradingform_singlepoint_controller::LEVEL_MEETS]));
    }

    public function test_not_yet_can_earn_part_of_the_points(): void {
        $controller = $this->define([['Thesis', 10], ['Evidence', 10]], ['notyetpercent' => 50]);
        $this->assertEquals(50, $this->grade($controller, [
            \gradingform_singlepoint_controller::LEVEL_NOTYET, \gradingform_singlepoint_controller::LEVEL_NOTYET]));
    }

    public function test_every_criterion_needs_a_level(): void {
        $controller = $this->define([['Thesis', 10], ['Evidence', 20]]);
        $ids = $this->ids($controller);
        $instance = $controller->create_instance($this->teacher->id, 1);
        $this->assertFalse($instance->validate_grading_element(['criteria' => [$ids[0] => ['level' => 1, 'remark' => '']]]));
        $this->assertFalse($instance->validate_grading_element(['criteria' => [
            $ids[0] => ['level' => 1, 'remark' => ''], $ids[1] => ['level' => 7, 'remark' => '']]]));
        $this->assertTrue($instance->validate_grading_element(['criteria' => [
            $ids[0] => ['level' => 1, 'remark' => ''], $ids[1] => ['level' => 0, 'remark' => 'Cite sources']]]));
    }

    public function test_filling_is_stored_and_updated(): void {
        $controller = $this->define([['Thesis', 10]]);
        $this->grade($controller, [\gradingform_singlepoint_controller::LEVEL_NOTYET]);
        $instance = $controller->get_current_instance($this->teacher->id, 1);
        $filling = $instance->get_singlepoint_filling();
        $row = reset($filling['criteria']);
        $this->assertEquals(0, $row['level']);
        $this->assertSame('remark 0', $row['remark']);
    }

    public function test_copy_keeps_the_filling(): void {
        global $DB;
        $controller = $this->define([['Thesis', 10]]);
        $this->grade($controller, [\gradingform_singlepoint_controller::LEVEL_BEYOND]);
        $instance = $controller->get_current_instance($this->teacher->id, 1);
        $newid = $instance->copy($this->teacher->id, 2);
        $this->assertEquals(2, $DB->get_field('gradingform_singlepoint_fill', 'achieved', ['instanceid' => $newid]));
    }

    public function test_changing_points_asks_for_regrade(): void {
        $controller = $this->define([['Thesis', 10]]);
        $this->grade($controller, [\gradingform_singlepoint_controller::LEVEL_MEETS]);
        $data = $controller->get_definition_for_editing();
        $id = $this->ids($controller)[0];
        $data->singlepoint['criteria'][$id]['description'] = 'New words';
        $this->assertEquals(1, $controller->update_or_check_singlepoint(clone $data));
        $data->singlepoint['criteria'][$id]['maxscore'] = 15;
        $this->assertEquals(3, $controller->update_or_check_singlepoint(clone $data));
        $data->singlepoint['criteria']['NEWID1'] = [
            'sortorder' => 2, 'shortname' => 'Style', 'description' => 'x', 'maxscore' => 5,
        ];
        $this->assertEquals(5, $controller->update_or_check_singlepoint(clone $data));
    }

    public function test_regrade_marks_instances(): void {
        $controller = $this->define([['Thesis', 10]]);
        $this->grade($controller, [\gradingform_singlepoint_controller::LEVEL_MEETS]);
        $data = $controller->get_definition_for_editing();
        $data->singlepoint['criteria'][$this->ids($controller)[0]]['maxscore'] = 15;
        $data->singlepoint['regrade'] = 1;
        $controller->update_definition($data);
        $instance = $this->controller()->get_current_instance($this->teacher->id, 1);
        $this->assertEquals(gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $instance->get_status());
    }

    public function test_definition_copy_and_delete(): void {
        global $DB;
        $controller = $this->define([['Thesis', 10], ['Evidence', 20]]);
        $this->grade($controller, [1, 1]);
        $other = $this->getDataGenerator()->create_module('assign', ['course' => $this->assign->course]);
        $target = get_grading_manager(\context_module::instance($other->cmid), 'mod_assign', 'submissions')
            ->get_controller('singlepoint');
        $copy = $controller->get_definition_copy($target);
        $this->assertSame(['NEWID1', 'NEWID2'], array_keys($copy->singlepoint['criteria']));
        $this->assertSame('Evidence', $copy->singlepoint['criteria']['NEWID2']['shortname']);

        $controller->delete_definition();
        $this->assertSame(0, $DB->count_records('gradingform_singlepoint_crit'));
        $this->assertSame(0, $DB->count_records('gradingform_singlepoint_fill'));
    }
}
