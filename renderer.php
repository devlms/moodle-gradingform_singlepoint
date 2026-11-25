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
 * Renderer for the single-point rubric.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Renders the rubric in every mode: preview, grading, review and the student's view.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_singlepoint_renderer extends plugin_renderer_base {
    /**
     * Template context for the rubric.
     *
     * @param array $criteria criterion id => criterion
     * @param array $options rubric options
     * @param int $mode a DISPLAY_ constant of the controller
     * @param string $elementname name of the form element ('advancedgrading')
     * @param array|null $value the filling: ['criteria' => [id => ['level' => int, 'remark' => string]]]
     * @param array $errors criterion id => true for criteria missing a level
     * @return array
     */
    public static function export_context(
        array $criteria,
        array $options,
        int $mode,
        string $elementname,
        ?array $value = null,
        array $errors = []
    ): array {
        $editable = $mode === gradingform_singlepoint_controller::DISPLAY_EVAL;
        $frozen = $mode === gradingform_singlepoint_controller::DISPLAY_EVAL_FROZEN;
        $filled = in_array($mode, [
            gradingform_singlepoint_controller::DISPLAY_EVAL,
            gradingform_singlepoint_controller::DISPLAY_EVAL_FROZEN,
            gradingform_singlepoint_controller::DISPLAY_REVIEW,
            gradingform_singlepoint_controller::DISPLAY_VIEW,
        ], true);
        $labels = gradingform_singlepoint_controller::level_labels();
        $uniq = html_writer::random_id('sp');
        $rows = [];
        foreach ($criteria as $id => $criterion) {
            $current = $value['criteria'][$id] ?? [];
            $chosen = isset($current['level']) && $current['level'] !== '' ? (int) $current['level'] : null;
            $levels = [];
            foreach ($labels as $level => $label) {
                $levels[] = [
                    'value' => $level,
                    'label' => $label,
                    'checked' => $chosen === $level,
                    'inputid' => "{$uniq}-{$id}-{$level}",
                ];
            }
            $remark = (string) ($current['remark'] ?? '');
            $rows[] = [
                'id' => $id,
                'shortname' => format_string($criterion['shortname']),
                'description' => format_text($criterion['description'] ?? '', FORMAT_PLAIN),
                'points' => format_float($criterion['maxscore'], -1),
                'levels' => $levels,
                'chosen' => $chosen !== null ? $labels[$chosen] : '',
                'chosenvalue' => $chosen,
                'chosenclass' => $chosen !== null ? ['notyet', 'meets', 'beyond'][$chosen] : '',
                'remark' => $remark,
                'remarkformatted' => $remark === '' ? '' : format_text($remark, FORMAT_PLAIN),
                'remarkid' => "{$uniq}-{$id}-remark",
                'error' => !empty($errors[$id]),
            ];
        }
        return [
            'name' => $elementname,
            'criteria' => $rows,
            'editable' => $editable,
            'frozen' => $frozen,
            'filled' => $filled,
            'notyetpercent' => (int) ($options['notyetpercent'] ?? 0),
            'scoring' => get_string('scoring', 'gradingform_singlepoint', (int) ($options['notyetpercent'] ?? 0)),
        ];
    }

    /**
     * Render the rubric.
     *
     * @param array $criteria
     * @param array $options
     * @param int $mode
     * @param string $elementname
     * @param array|null $value
     * @param array $errors
     * @return string
     */
    public function display_singlepoint(
        array $criteria,
        array $options,
        int $mode,
        string $elementname,
        ?array $value = null,
        array $errors = []
    ): string {
        return $this->render_from_template(
            'gradingform_singlepoint/singlepoint',
            self::export_context($criteria, $options, $mode, $elementname, $value, $errors)
        );
    }

    /**
     * Render the active fillings for the student's feedback.
     *
     * @param gradingform_singlepoint_instance[] $instances
     * @param string $defaultcontent
     * @param bool $cangrade
     * @return string
     */
    public function display_instances($instances, $defaultcontent, $cangrade) {
        $html = '';
        foreach ($instances as $instance) {
            $controller = $instance->get_controller();
            $mode = $cangrade ? gradingform_singlepoint_controller::DISPLAY_REVIEW
                : gradingform_singlepoint_controller::DISPLAY_VIEW;
            $html .= $this->display_singlepoint(
                $controller->get_definition()->singlepoint_criteria,
                $controller->get_options(),
                $mode,
                'singlepoint' . $instance->get_id(),
                $instance->get_singlepoint_filling()
            );
        }
        return $html === '' ? $defaultcontent : $html . $defaultcontent;
    }
}
