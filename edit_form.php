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
 * The single-point rubric editor.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Editor form: name, description, options and one row per criterion. Works without JavaScript.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_singlepoint_editform extends moodleform {
    /**
     * Form fields.
     */
    public function definition() {
        $form = $this->_form;
        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);
        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_LOCALURL);

        $form->addElement('text', 'name', get_string('name', 'gradingform_singlepoint'), ['size' => 52, 'maxlength' => 255]);
        $form->addRule('name', get_string('required'), 'required', null, 'client');
        $form->setType('name', PARAM_TEXT);

        $form->addElement(
            'editor',
            'description_editor',
            get_string('description'),
            null,
            gradingform_singlepoint_controller::description_form_field_options($this->_customdata['context'])
        );
        $form->setType('description_editor', PARAM_RAW);

        $form->addElement('static', 'howitworks', '', get_string('howitworks', 'gradingform_singlepoint'));

        $repeat = [
            $form->createElement('header', 'criterionheader', get_string('criterionno', 'gradingform_singlepoint', '{no}')),
            $form->createElement('hidden', 'critid'),
            $form->createElement('text', 'critname', get_string('criterionname', 'gradingform_singlepoint'), ['size' => 52]),
            $form->createElement(
                'textarea',
                'critdescription',
                get_string('criteriondescription', 'gradingform_singlepoint'),
                ['rows' => 3, 'cols' => 60]
            ),
            $form->createElement('text', 'critpoints', get_string('criterionpoints', 'gradingform_singlepoint'), ['size' => 6]),
            $form->createElement('advcheckbox', 'critdelete', get_string('criteriondelete', 'gradingform_singlepoint')),
        ];
        $options = [
            'critid' => ['type' => PARAM_ALPHANUM],
            'critname' => ['type' => PARAM_TEXT],
            'critdescription' => ['type' => PARAM_TEXT],
            'critpoints' => ['type' => PARAM_RAW],
            'criterionheader' => ['expanded' => true],
        ];
        $count = max(1, $this->_customdata['criteriacount'] ?? 1);
        $this->repeat_elements(
            $repeat,
            $count,
            $options,
            'critrepeats',
            'critadd',
            1,
            get_string('criterionadd', 'gradingform_singlepoint'),
            true
        );

        $form->addElement('header', 'optionsheader', get_string('options', 'gradingform_singlepoint'));
        $form->addElement(
            'select',
            'notyetpercent',
            get_string('notyetpercent', 'gradingform_singlepoint'),
            [0 => '0%', 25 => '25%', 50 => '50%', 75 => '75%']
        );
        $form->addHelpButton('notyetpercent', 'notyetpercent', 'gradingform_singlepoint');
        $form->addElement('advcheckbox', 'alwaysshowdefinition', get_string('alwaysshowdefinition', 'gradingform_singlepoint'));
        $form->setDefault('alwaysshowdefinition', 1);

        if (!empty($this->_customdata['hasgrades'])) {
            $form->addElement('select', 'regrade', get_string('regrade', 'gradingform_singlepoint'), [
                0 => get_string('regradekeep', 'gradingform_singlepoint'),
                1 => get_string('regrademark', 'gradingform_singlepoint'),
            ]);
            $form->addHelpButton('regrade', 'regrade', 'gradingform_singlepoint');
        }

        $buttons = [$form->createElement('submit', 'saveready', get_string('saveready', 'gradingform_singlepoint'))];
        if (!empty($this->_customdata['allowdraft'])) {
            $buttons[] = $form->createElement('submit', 'savedraft', get_string('savedraft', 'gradingform_singlepoint'));
        }
        $buttons[] = $form->createElement('cancel');
        $form->addGroup($buttons, 'buttonar', '', [' '], false);
        $form->closeHeaderBefore('buttonar');
    }

    /**
     * Put the definition into the repeated fields.
     *
     * @param stdClass $definition from the controller's get_definition_for_editing()
     */
    public function set_definition(stdClass $definition): void {
        $data = clone $definition;
        $i = 0;
        foreach ($definition->singlepoint['criteria'] as $id => $criterion) {
            $data->critid[$i] = (string) $id;
            $data->critname[$i] = $criterion['shortname'];
            $data->critdescription[$i] = $criterion['description'];
            $data->critpoints[$i] = $criterion['maxscore'] === '' ? '' : format_float($criterion['maxscore'], -1);
            $i++;
        }
        $data->notyetpercent = $definition->singlepoint['options']['notyetpercent'];
        $data->alwaysshowdefinition = $definition->singlepoint['options']['alwaysshowdefinition'];
        unset($data->singlepoint);
        $this->set_data($data);
    }

    /**
     * Rows that have a name or points or a description and are not deleted.
     *
     * @param array $data
     * @return array index => [critid, name, description, points]
     */
    protected static function used_rows(array $data): array {
        $rows = [];
        foreach ($data['critname'] ?? [] as $i => $name) {
            $name = trim((string) $name);
            $description = trim((string) ($data['critdescription'][$i] ?? ''));
            $points = trim((string) ($data['critpoints'][$i] ?? ''));
            if (!empty($data['critdelete'][$i]) || ($name === '' && $description === '' && $points === '')) {
                continue;
            }
            $rows[$i] = [(string) ($data['critid'][$i] ?? ''), $name, $description, $points];
        }
        return $rows;
    }

    /**
     * Each used row needs a name and positive points; the rubric needs one criterion.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $rows = self::used_rows($data);
        if (!$rows) {
            $errors['critname[0]'] = get_string('errornocriteria', 'gradingform_singlepoint');
        }
        foreach ($rows as $i => [, $name, , $points]) {
            if ($name === '') {
                $errors["critname[$i]"] = get_string('required');
            }
            $value = unformat_float($points, true);
            if ($value === false || $value === null || $value <= 0) {
                $errors["critpoints[$i]"] = get_string('errorpoints', 'gradingform_singlepoint');
            }
        }
        return $errors;
    }

    /**
     * Submitted data in the shape the controller saves.
     *
     * @return stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return null;
        }
        $criteria = [];
        $sortorder = 1;
        $new = 1;
        foreach (self::used_rows((array) $data) as [$id, $name, $description, $points]) {
            $key = ctype_digit($id) ? (int) $id : 'NEWID' . $new++;
            $criteria[$key] = ['sortorder' => $sortorder++, 'shortname' => $name, 'description' => $description,
                'maxscore' => unformat_float($points, true)];
        }
        $data->singlepoint = [
            'criteria' => $criteria,
            'options' => [
                'notyetpercent' => (int) $data->notyetpercent,
                'alwaysshowdefinition' => (int) $data->alwaysshowdefinition,
            ],
            'regrade' => !empty($data->regrade),
        ];
        $data->status = empty($data->savedraft) ? gradingform_controller::DEFINITION_STATUS_READY
            : gradingform_controller::DEFINITION_STATUS_DRAFT;
        return $data;
    }
}
