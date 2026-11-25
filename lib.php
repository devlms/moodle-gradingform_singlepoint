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
 * Single-point rubric: controller and instance classes.
 *
 * Built on the structure of core's marking guide (gradingform_guide).
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');
require_once(__DIR__ . '/instance.php');

use core_external\external_format_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Manages the definition of a single-point rubric: criteria, each with one description of the standard.
 *
 * @package    gradingform_singlepoint
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_singlepoint_controller extends gradingform_controller {
    /** Display mode: teacher previews the rubric. */
    public const DISPLAY_PREVIEW = 3;
    /** Display mode: student previews the rubric. */
    public const DISPLAY_PREVIEW_GRADED = 8;
    /** Display mode: teacher grades a student. */
    public const DISPLAY_EVAL = 4;
    /** Display mode: grading form frozen with hidden fields. */
    public const DISPLAY_EVAL_FROZEN = 5;
    /** Display mode: teacher reviews a filled rubric. */
    public const DISPLAY_REVIEW = 6;
    /** Display mode: student sees the filled rubric. */
    public const DISPLAY_VIEW = 7;

    /** The work does not meet the standard yet. */
    public const LEVEL_NOTYET = 0;
    /** The work meets the standard. */
    public const LEVEL_MEETS = 1;
    /** The work goes beyond the standard. */
    public const LEVEL_BEYOND = 2;

    /** @var stdClass|false the activity record, to read its grade */
    protected $moduleinstance = false;

    /**
     * Link to the editor in the activity's settings menu.
     *
     * @param settings_navigation $settingsnav
     * @param navigation_node|null $node
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, ?navigation_node $node = null) {
        $node->add(
            get_string('define', 'gradingform_singlepoint'),
            $this->get_editor_url(),
            settings_navigation::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('i/edit', '')
        );
    }

    /**
     * Link to the preview for people who cannot edit the rubric.
     *
     * @param global_navigation $navigation
     * @param navigation_node|null $node
     */
    public function extend_navigation(global_navigation $navigation, ?navigation_node $node = null) {
        if (has_capability('moodle/grade:managegradingforms', $this->get_context())) {
            return;
        }
        if ($this->is_form_defined() && !empty($this->get_options()['alwaysshowdefinition'])) {
            $node->add(
                get_string('gradingof', 'gradingform_singlepoint', get_grading_manager($this->get_areaid())->get_area_title()),
                new moodle_url('/grade/grading/form/singlepoint/preview.php', ['areaid' => $this->get_areaid()]),
                settings_navigation::TYPE_CUSTOM
            );
        }
    }

    /**
     * Save the definition.
     *
     * @param stdClass $newdefinition as from the editor form's get_data()
     * @param int|null $usermodified
     */
    public function update_definition(stdClass $newdefinition, $usermodified = null) {
        $this->update_or_check_singlepoint($newdefinition, $usermodified, true);
        if (!empty($newdefinition->singlepoint['regrade'])) {
            $this->mark_for_regrade();
        }
    }

    /**
     * Save the definition, or only report how much it would change.
     *
     * Change levels as in core's rubric and guide: 0 none, 1 texts or order only,
     * 3 points changed or criteria removed, 5 criteria added.
     *
     * @param stdClass $newdefinition
     * @param int|null $usermodified
     * @param bool $doupdate false to only check
     * @return int the change level
     */
    public function update_or_check_singlepoint(stdClass $newdefinition, $usermodified = null, $doupdate = false) {
        global $DB;

        if ($this->definition === false) {
            if (!$doupdate) {
                return 5;
            }
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }
        $options = $newdefinition->singlepoint['options'] ?? [];
        $newdefinition->options = json_encode(array_merge(self::get_default_options(), (array) $options));
        if (isset($newdefinition->description_editor)) {
            $newdefinition = file_postupdate_standard_editor(
                $newdefinition,
                'description',
                self::description_form_field_options($this->get_context()),
                $this->get_context(),
                'grading',
                'description',
                $this->definition->id
            );
        }

        $current = $this->get_definition(true);
        $currentcriteria = $current->singlepoint_criteria;
        $newcriteria = $newdefinition->singlepoint['criteria'] ?? [];
        $fields = ['sortorder', 'shortname', 'description', 'maxscore'];
        $changes = [];

        foreach ($newcriteria as $id => $criterion) {
            if (preg_match('/^NEWID\d+$/', $id)) {
                $record = ['definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_PLAIN];
                foreach ($fields as $key) {
                    if (array_key_exists($key, $criterion)) {
                        $record[$key] = $criterion[$key];
                    }
                }
                if ($doupdate) {
                    $DB->insert_record('gradingform_singlepoint_crit', $record);
                }
                $changes[5] = true;
                continue;
            }
            if (!isset($currentcriteria[$id])) {
                continue;
            }
            $record = [];
            foreach ($fields as $key) {
                if (array_key_exists($key, $criterion) && $criterion[$key] != $currentcriteria[$id][$key]) {
                    $record[$key] = $criterion[$key];
                }
            }
            if ($record) {
                $changes[array_key_exists('maxscore', $record) ? 3 : 1] = true;
                if ($doupdate) {
                    $record['id'] = $id;
                    $DB->update_record('gradingform_singlepoint_crit', $record);
                }
            }
        }
        foreach (array_keys($currentcriteria) as $id) {
            if (!array_key_exists($id, $newcriteria)) {
                if ($doupdate) {
                    $DB->delete_records('gradingform_singlepoint_fill', ['criterionid' => $id]);
                    $DB->delete_records('gradingform_singlepoint_crit', ['id' => $id]);
                }
                $changes[3] = true;
            }
        }
        $oldoptions = $this->get_options();
        if ((int) ($oldoptions['notyetpercent'] ?? 0) !== (int) json_decode($newdefinition->options, true)['notyetpercent']) {
            $changes[3] = true;
        }
        foreach (['status', 'description', 'descriptionformat', 'name', 'options'] as $key) {
            if (isset($newdefinition->$key) && $newdefinition->$key != $this->definition->$key) {
                $changes[1] = true;
            }
        }
        if ($usermodified && $usermodified != $this->definition->usermodified) {
            $changes[1] = true;
        }
        if (!$changes) {
            return 0;
        }
        if ($doupdate) {
            parent::update_definition($newdefinition, $usermodified);
            $this->load_definition();
        }
        return max(array_keys($changes));
    }

    /**
     * Mark every active grading with this rubric as needing a regrade.
     */
    public function mark_for_regrade() {
        global $DB;
        if ($this->has_active_instances()) {
            $DB->set_field(
                'grading_instances',
                'status',
                gradingform_instance::INSTANCE_STATUS_NEEDUPDATE,
                ['definitionid' => $this->definition->id, 'status' => gradingform_instance::INSTANCE_STATUS_ACTIVE]
            );
        }
    }

    /**
     * Load the definition with its criteria (as singlepoint_criteria).
     */
    protected function load_definition() {
        global $DB;
        $definition = $DB->get_record('grading_definitions', ['areaid' => $this->areaid, 'method' => $this->get_method_name()]);
        if (!$definition) {
            $this->definition = false;
            return false;
        }
        $this->definition = $definition;
        $this->definition->singlepoint_criteria = [];
        $criteria = $DB->get_records('gradingform_singlepoint_crit', ['definitionid' => $definition->id], 'sortorder, id');
        foreach ($criteria as $criterion) {
            $this->definition->singlepoint_criteria[$criterion->id] = [
                'id' => $criterion->id,
                'sortorder' => $criterion->sortorder,
                'shortname' => $criterion->shortname,
                'description' => $criterion->description,
                'descriptionformat' => $criterion->descriptionformat,
                'maxscore' => (float) $criterion->maxscore,
            ];
        }
        if (empty($this->moduleinstance)) {
            $component = $this->get_component();
            if (strpos($component, 'mod_') === 0) {
                $cm = get_coursemodule_from_id(substr($component, 4), $this->get_context()->instanceid);
                if ($cm) {
                    $this->moduleinstance = $DB->get_record($cm->modname, ['id' => $cm->instance]);
                }
            }
        }
    }

    /**
     * Default options.
     *
     * @return array
     */
    public static function get_default_options() {
        return [
            'alwaysshowdefinition' => 1,
            'notyetpercent' => 0,
        ];
    }

    /**
     * Options of this rubric, with defaults for missing ones.
     *
     * @return array
     */
    public function get_options() {
        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            foreach ((array) json_decode($this->definition->options, true) as $key => $value) {
                $options[$key] = $value;
            }
        }
        return $options;
    }

    /**
     * The three level names, by level.
     *
     * @return string[]
     */
    public static function level_labels(): array {
        return [
            self::LEVEL_NOTYET => get_string('levelnotyet', 'gradingform_singlepoint'),
            self::LEVEL_MEETS => get_string('levelmeets', 'gradingform_singlepoint'),
            self::LEVEL_BEYOND => get_string('levelbeyond', 'gradingform_singlepoint'),
        ];
    }

    /**
     * Points a criterion earns at a level.
     *
     * @param float $maxscore the criterion's points
     * @param int $level one of the LEVEL_ constants
     * @return float
     */
    public function score_for_level(float $maxscore, int $level): float {
        if ($level === self::LEVEL_NOTYET) {
            return $maxscore * (int) $this->get_options()['notyetpercent'] / 100;
        }
        return $maxscore;
    }

    /**
     * The definition in the shape the editor form expects.
     *
     * @param bool $addemptycriterion add one empty criterion to a new rubric
     * @return stdClass
     */
    public function get_definition_for_editing($addemptycriterion = false) {
        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;
        if ($definition) {
            foreach (['id', 'name', 'description', 'descriptionformat', 'status'] as $key) {
                $properties->$key = $definition->$key;
            }
            $properties = file_prepare_standard_editor(
                $properties,
                'description',
                self::description_form_field_options($this->get_context()),
                $this->get_context(),
                'grading',
                'description',
                $definition->id
            );
        }
        $properties->singlepoint = ['criteria' => [], 'options' => $this->get_options()];
        if (!empty($definition->singlepoint_criteria)) {
            $properties->singlepoint['criteria'] = $definition->singlepoint_criteria;
        } else if (!$definition && $addemptycriterion) {
            $properties->singlepoint['criteria'] = ['NEWID1' => ['sortorder' => 1, 'shortname' => '', 'description' => '',
                'maxscore' => '']];
        }
        return $properties;
    }

    /**
     * The definition ready to be saved into another area (templates, copies).
     *
     * @param gradingform_controller $target
     * @return stdClass
     */
    public function get_definition_copy(gradingform_controller $target) {
        $new = parent::get_definition_copy($target);
        $old = $this->get_definition_for_editing();
        $new->description_editor = $old->description_editor;
        $new->singlepoint = ['criteria' => [], 'options' => $old->singlepoint['options']];
        $i = 1;
        foreach ($old->singlepoint['criteria'] as $criterion) {
            unset($criterion['id']);
            $new->singlepoint['criteria']['NEWID' . $i++] = $criterion;
        }
        return $new;
    }

    /**
     * Editor options for the rubric description.
     *
     * @param context $context
     * @return array
     */
    public static function description_form_field_options($context) {
        global $CFG;
        return ['maxfiles' => -1, 'maxbytes' => get_max_upload_file_size($CFG->maxbytes), 'context' => $context];
    }

    /**
     * The rubric description, formatted.
     *
     * @return string
     */
    public function get_formatted_description() {
        if (!isset($this->definition->description)) {
            return '';
        }
        $context = $this->get_context();
        $description = file_rewrite_pluginfile_urls(
            $this->definition->description,
            'pluginfile.php',
            $context->id,
            'grading',
            'description',
            $this->definition->id,
            self::description_form_field_options($context)
        );
        return format_text($description, $this->definition->descriptionformat, ['context' => $context]);
    }

    /**
     * The plugin renderer.
     *
     * @param moodle_page $page
     * @return gradingform_singlepoint_renderer
     */
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('gradingform_singlepoint');
    }

    /**
     * The rubric as shown before grading.
     *
     * @param moodle_page $page
     * @return string
     */
    public function render_preview(moodle_page $page) {
        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }
        $options = $this->get_options();
        $canmanage = has_capability('moodle/grade:managegradingforms', $page->context);
        if (empty($options['alwaysshowdefinition']) && !$canmanage) {
            return '';
        }
        $output = $this->get_renderer($page);
        $html = $output->box($this->get_formatted_description(), 'gradingform_singlepoint-description');
        $html .= $output->display_singlepoint(
            $this->definition->singlepoint_criteria,
            $options,
            $canmanage ? self::DISPLAY_PREVIEW : self::DISPLAY_PREVIEW_GRADED,
            'singlepoint'
        );
        return $html;
    }

    /**
     * Delete the criteria and fillings of this definition.
     */
    protected function delete_plugin_definition() {
        global $DB;
        $instances = array_keys($DB->get_records('grading_instances', ['definitionid' => $this->definition->id], '', 'id'));
        $DB->delete_records_list('gradingform_singlepoint_fill', 'instanceid', $instances);
        $DB->delete_records_list('grading_instances', 'id', $instances);
        $DB->delete_records('gradingform_singlepoint_crit', ['definitionid' => $this->definition->id]);
    }

    /**
     * Get the instance to edit, reusing the rater's newer draft like core's guide.
     *
     * @param int $instanceid
     * @param int $raterid
     * @param int $itemid
     * @return gradingform_instance
     */
    public function get_or_create_instance($instanceid, $raterid, $itemid) {
        global $DB;
        if (
            $instanceid && ($instance = $DB->get_record(
                'grading_instances',
                ['id' => $instanceid, 'raterid' => $raterid, 'itemid' => $itemid]
            ))
        ) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            $records = $DB->get_records(
                'grading_instances',
                ['definitionid' => $this->definition->id, 'raterid' => $raterid, 'itemid' => $itemid],
                'timemodified DESC',
                '*',
                0,
                1
            );
            if ($records) {
                $record = reset($records);
                $current = $this->get_current_instance($raterid, $itemid);
                if (
                    $record->status == gradingform_instance::INSTANCE_STATUS_INCOMPLETE &&
                        (!$current || $record->timemodified > $current->get_data('timemodified'))
                ) {
                    $record->isrestored = true;
                    return $this->get_instance($record);
                }
            }
        }
        return $this->create_instance($raterid, $itemid);
    }

    /**
     * The filled rubric in the student's feedback.
     *
     * @param moodle_page $page
     * @param int $itemid
     * @param array $gradinginfo
     * @param string $defaultcontent
     * @param bool $cangrade
     * @return string
     */
    public function render_grade($page, $itemid, $gradinginfo, $defaultcontent, $cangrade) {
        return $this->get_renderer($page)->display_instances($this->get_active_instances($itemid), $defaultcontent, $cangrade);
    }

    /**
     * Search support: join the criteria.
     *
     * @param string $gdid alias of grading_definitions.id
     * @return string
     */
    public static function sql_search_from_tables($gdid) {
        return " LEFT JOIN {gradingform_singlepoint_crit} spc ON (spc.definitionid = $gdid)";
    }

    /**
     * Search support: match criterion names and descriptions.
     *
     * @param string $token
     * @return array
     */
    public static function sql_search_where($token) {
        global $DB;
        $like = '%' . $DB->sql_like_escape($token) . '%';
        return [[$DB->sql_like('spc.description', '?', false, false), $DB->sql_like('spc.shortname', '?', false, false)],
            [$like, $like]];
    }

    /**
     * Lowest and highest points: 0 and the sum of the criteria.
     *
     * @return array|null
     */
    public function get_min_max_score() {
        if (!$this->is_form_available()) {
            return null;
        }
        $result = ['minscore' => 0, 'maxscore' => (float) array_sum(array_column(
            $this->get_definition()->singlepoint_criteria,
            'maxscore'
        ))];
        if (!$this->is_shared_template()) {
            $fieldname = \core_grades\component_gradeitems::get_field_name_for_itemname($this->component, $this->area, 'grade');
            if (!empty($this->moduleinstance->{$fieldname})) {
                $result['modulegrade'] = count(make_grades_menu($this->moduleinstance->{$fieldname})) - 1;
            }
        }
        return $result;
    }

    /**
     * Web service structure of the definition.
     *
     * @return array
     */
    public static function get_external_definition_details() {
        return ['singlepoint_criteria' => new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'criterion id', VALUE_OPTIONAL),
            'sortorder' => new external_value(PARAM_INT, 'sortorder', VALUE_OPTIONAL),
            'shortname' => new external_value(PARAM_TEXT, 'criterion name'),
            'description' => new external_value(PARAM_RAW, 'what meeting the standard looks like', VALUE_OPTIONAL),
            'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
            'maxscore' => new external_value(PARAM_FLOAT, 'points for meeting the standard'),
        ]))];
    }

    /**
     * Web service structure of a filling.
     *
     * @return array
     */
    public static function get_external_instance_filling_details() {
        return ['criteria' => new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'filling id'),
            'criterionid' => new external_value(PARAM_INT, 'criterion id'),
            'achieved' => new external_value(PARAM_INT, '0 not yet, 1 meets, 2 beyond'),
            'remark' => new external_value(PARAM_RAW, 'remark', VALUE_OPTIONAL),
            'remarkformat' => new external_format_value('remark', VALUE_OPTIONAL),
        ]), 'filling', VALUE_OPTIONAL)];
    }
}
