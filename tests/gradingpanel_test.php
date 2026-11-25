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

use core_external\external_api;
use core_grades\component_gradeitem;
use gradingform_controller;
use gradingform_singlepoint\grades\grader\gradingpanel\external\fetch;
use gradingform_singlepoint\grades\grader\gradingpanel\external\store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Tests for the forum grader's grading panel services.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \gradingform_singlepoint\grades\grader\gradingpanel\external\fetch
 * @covers     \gradingform_singlepoint\grades\grader\gradingpanel\external\store
 */
final class gradingpanel_test extends \advanced_testcase {
    /**
     * A graded forum using a single-point rubric with two criteria of 5 points.
     *
     * @return array
     */
    protected function setup_forum(): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $forum = $gen->create_module('forum', ['course' => $course->id, 'grade_forum' => 10]);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $context = \context_module::instance($forum->cmid);
        $manager = get_grading_manager($context, 'mod_forum', 'forum');
        $manager->set_active_method('singlepoint');
        $manager->get_controller('singlepoint')->update_definition((object) [
            'name' => 'Discussion', 'status' => gradingform_controller::DEFINITION_STATUS_READY,
            'singlepoint' => ['options' => [], 'criteria' => [
                'NEWID1' => ['sortorder' => 1, 'shortname' => 'Reply', 'description' => 'Answers a peer', 'maxscore' => 5],
                'NEWID2' => ['sortorder' => 2, 'shortname' => 'Source', 'description' => 'Cites a reading', 'maxscore' => 5],
            ]],
        ]);
        $ids = array_keys($manager->get_controller('singlepoint')->get_definition()->singlepoint_criteria);
        return [$forum, $context, $teacher, $student, $ids];
    }

    public function test_store_then_fetch(): void {
        [, $context, $teacher, $student, $ids] = $this->setup_forum();
        $gradeitem = component_gradeitem::instance('mod_forum', $context, 'forum');
        $instance = $gradeitem->get_advanced_grading_instance($teacher, $gradeitem->get_grade_for_user($student, $teacher));
        $formdata = http_build_query([
            'instanceid' => $instance->get_id(),
            'advancedgrading' => ['criteria' => [
                $ids[0] => ['level' => 2, 'remark' => 'Built on Sam\'s point'],
                $ids[1] => ['level' => 0, 'remark' => 'No reading cited'],
            ]],
        ], '', '&'); // Moodle 4.5 sets arg_separator.output to &amp;.
        $result = store::execute('mod_forum', $context->id, 'forum', $student->id, false, $formdata);
        $result = external_api::clean_returnvalue(store::execute_returns(), $result);

        $this->assertSame('gradingform_singlepoint/grades/grader/gradingpanel', $result['templatename']);
        $this->assertTrue($result['hasgrade']);
        $this->assertStringStartsWith('5.00', $result['grade']['usergrade']);
        [$reply, $source] = $result['grade']['criteria'];
        $this->assertSame(2, $reply['level']);
        $this->assertSame([false, false, true], array_column($reply['levels'], 'checked'));
        $this->assertSame('No reading cited', $source['remark']);

        $fetched = external_api::clean_returnvalue(
            fetch::execute_returns(),
            fetch::execute('mod_forum', $context->id, 'forum', $student->id)
        );
        $this->assertSame(0, $fetched['grade']['criteria'][1]['level']);
    }

    public function test_a_student_cannot_fetch_another_students_marks(): void {
        [$forum, $context, , $student] = $this->setup_forum();
        $other = $this->getDataGenerator()->create_and_enrol(get_course($forum->course), 'student');
        $this->setUser($other);
        $this->expectException(\required_capability_exception::class);
        fetch::execute('mod_forum', $context->id, 'forum', $student->id);
    }

    public function test_other_methods_are_refused(): void {
        [, $context, , $student] = $this->setup_forum();
        get_grading_manager($context, 'mod_forum', 'forum')->set_active_method('guide');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('does not use a single-point rubric');
        fetch::execute('mod_forum', $context->id, 'forum', $student->id);
    }
}
