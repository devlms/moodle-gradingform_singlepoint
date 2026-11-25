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
 * Edit a single-point rubric.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/edit_form.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$areaid = required_param('areaid', PARAM_INT);
$manager = get_grading_manager($areaid);
[$context, $course, $cm] = get_context_info_array($manager->get_context()->id);

require_login($course, true, $cm);
require_capability('moodle/grade:managegradingforms', $context);

$controller = $manager->get_controller('singlepoint');
$url = new moodle_url('/grade/grading/form/singlepoint/edit.php', ['areaid' => $areaid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('define', 'gradingform_singlepoint'));
$PAGE->set_heading(get_string('define', 'gradingform_singlepoint'));

$definition = $controller->get_definition_for_editing(true);
$returnurl = optional_param('returnurl', $manager->get_management_url(), PARAM_LOCALURL);
$definition->returnurl = $returnurl;

$form = new gradingform_singlepoint_editform($url, [
    'context' => $context,
    'criteriacount' => count($definition->singlepoint['criteria']) + 1,
    'allowdraft' => !$controller->has_active_instances(),
    'hasgrades' => $controller->has_active_instances(),
]);
$form->set_definition($definition);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $controller->update_definition($data);
    redirect($returnurl);
}

\core\session\manager::keepalive();
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
