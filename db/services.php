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
 * Web services for the forum grader's grading panel.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'gradingform_singlepoint_grader_gradingpanel_fetch' => [
        'classname' => 'gradingform_singlepoint\\grades\\grader\\gradingpanel\\external\\fetch',
        'description' => 'Fetch the single-point rubric and current marks for the grader grading panel.',
        'type' => 'write',
        'ajax' => true,
    ],
    'gradingform_singlepoint_grader_gradingpanel_store' => [
        'classname' => 'gradingform_singlepoint\\grades\\grader\\gradingpanel\\external\\store',
        'description' => 'Store single-point rubric marks from the grader grading panel.',
        'type' => 'write',
        'ajax' => true,
    ],
];
