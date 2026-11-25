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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Privacy, backup and restore tests.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \gradingform_singlepoint\privacy\provider
 * @covers     \backup_gradingform_singlepoint_plugin
 * @covers     \restore_gradingform_singlepoint_plugin
 */
final class data_test extends \core_privacy\tests\provider_testcase {
    /**
     * Make an assignment with a ready rubric and one graded student.
     *
     * @return array [course, assign, controller, teacher, student]
     */
    protected function setup_graded(): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->setUser($teacher);
        $manager = get_grading_manager(\context_module::instance($assign->cmid), 'mod_assign', 'submissions');
        $manager->set_active_method('singlepoint');
        $controller = $manager->get_controller('singlepoint');
        $controller->update_definition((object) [
            'name' => 'Essay', 'status' => gradingform_controller::DEFINITION_STATUS_READY,
            'singlepoint' => ['criteria' => ['NEWID1' => ['sortorder' => 1, 'shortname' => 'Thesis',
                'description' => 'Clear claim', 'maxscore' => 10]], 'options' => []],
        ]);
        $controller = $manager->get_controller('singlepoint');
        $controller->set_grade_range(make_grades_menu(100), false);
        $id = array_key_first($controller->get_definition()->singlepoint_criteria);
        $instance = $controller->create_instance($teacher->id, $student->id);
        $instance->submit_and_get_grade(['criteria' => [$id => ['level' => 0, 'remark' => 'Narrow the claim']]], $student->id);
        return [$course, $assign, $controller, $teacher, $student, $instance];
    }

    public function test_export_and_delete(): void {
        global $DB;
        [, $assign, , , , $instance] = $this->setup_graded();
        $context = \context_module::instance($assign->cmid);

        privacy\provider::export_gradingform_instance_data($context, $instance->get_id(), ['Test']);
        $data = (array) \core_privacy\local\request\writer::with_context($context)
            ->get_data(['Test', get_string('pluginname', 'gradingform_singlepoint'), $instance->get_id()]);
        $row = (array) reset($data);
        $this->assertSame('Thesis', $row['criterion']);
        $this->assertSame(get_string('levelnotyet', 'gradingform_singlepoint'), $row['mark']);
        $this->assertSame('Narrow the claim', $row['comment']);

        privacy\provider::delete_gradingform_for_instances([$instance->get_id()]);
        $this->assertSame(0, $DB->count_records('gradingform_singlepoint_fill'));
    }

    public function test_backup_and_restore_keep_rubric_and_marks(): void {
        global $DB, $USER;
        [$course] = $this->setup_graded();
        $this->setAdminUser();

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Copy', 'COPY', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $this->assertSame(2, $DB->count_records('gradingform_singlepoint_crit', ['shortname' => 'Thesis']));
        $newassign = $DB->get_record('assign', ['course' => $newcourseid]);
        $cm = get_coursemodule_from_instance('assign', $newassign->id);
        $controller = get_grading_manager(\context_module::instance($cm->id), 'mod_assign', 'submissions')
            ->get_controller('singlepoint');
        $this->assertTrue($controller->is_form_available());
        $criterion = reset($controller->get_definition()->singlepoint_criteria);
        $this->assertSame('Clear claim', $criterion['description']);
    }
}
