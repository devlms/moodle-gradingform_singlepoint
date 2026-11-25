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
 * English strings.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['alwaysshowdefinition'] = 'Show the rubric to students before grading';
$string['comment'] = 'Comment';
$string['commenton'] = 'Comment on {$a}';
$string['criterion'] = 'Criterion and standard';
$string['criterionadd'] = 'Add a criterion';
$string['criteriondelete'] = 'Remove this criterion';
$string['criteriondescription'] = 'What meeting the standard looks like';
$string['criterionname'] = 'Criterion';
$string['criterionno'] = 'Criterion {$a}';
$string['criterionpoints'] = 'Points';
$string['define'] = 'Define single-point rubric';
$string['errornocriteria'] = 'Add at least one criterion.';
$string['errorpoints'] = 'Enter points greater than 0.';
$string['gradingof'] = '{$a} single-point rubric';
$string['howitworks'] = 'Describe each criterion once, at the standard you expect. When grading you mark each one Not yet, Meets or Beyond, and write a comment where it helps.';
$string['levelbeyond'] = 'Beyond';
$string['levelmeets'] = 'Meets';
$string['levelnotyet'] = 'Not yet';
$string['mark'] = 'Mark';
$string['name'] = 'Name';
$string['needregrade'] = 'The rubric changed after this student was graded. Check each criterion and save again.';
$string['notcompleted'] = 'Choose Not yet, Meets or Beyond for every criterion.';
$string['notyetpercent'] = 'Not yet earns';
$string['notyetpercent_help'] = 'The share of a criterion\'s points given for Not yet. Meets and Beyond always earn all of its points: Beyond is feedback, not extra credit.';
$string['options'] = 'Options';
$string['pluginname'] = 'Single-point rubric';
$string['points'] = 'Points';
$string['pointscount'] = '{$a} points';
$string['privacy:metadata'] = 'The single-point rubric stores how each criterion was marked and the comment on it, as part of the grading data of the activity.';
$string['privacy:metadata:achieved'] = 'Whether the work was Not yet, Meets or Beyond the standard.';
$string['privacy:metadata:criterionid'] = 'The criterion that was marked.';
$string['privacy:metadata:fillings'] = 'How each criterion of a single-point rubric was marked for a student.';
$string['privacy:metadata:instanceid'] = 'The grading this mark belongs to.';
$string['privacy:metadata:remark'] = 'The comment on the criterion.';
$string['regrade'] = 'Students already graded';
$string['regrade_help'] = 'If you changed points or criteria, mark the students already graded so their grades are checked again. Their current grades stay until then.';
$string['regradekeep'] = 'Keep their grades as they are';
$string['regrademark'] = 'Mark them for regrading';
$string['savedraft'] = 'Save as draft';
$string['saveready'] = 'Save rubric and make it ready';
$string['scoring'] = 'Meets and Beyond earn the criterion\'s points. Not yet earns {$a}%.';
