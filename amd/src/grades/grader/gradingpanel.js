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
 * Grading panel for the single-point rubric in the forum grader.
 *
 * @module     gradingform_singlepoint/grades/grader/gradingpanel
 * @copyright  2026 DevLMS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {normaliseResult} from 'core_grades/grades/grader/gradingpanel/normalise';
import {compareData} from 'core_grades/grades/grader/gradingpanel/comparison';

/**
 * Fetch the rubric and current marks for a user.
 *
 * @param {String} component
 * @param {Number} contextid
 * @param {String} itemname
 * @param {Number} gradeduserid
 * @returns {Promise}
 */
export const fetchCurrentGrade = (component, contextid, itemname, gradeduserid) => fetchMany([{
    methodname: 'gradingform_singlepoint_grader_gradingpanel_fetch',
    args: {component, contextid, itemname, gradeduserid},
}])[0];

/**
 * Store the marks in the panel's form, when they changed.
 *
 * @param {String} component
 * @param {Number} contextid
 * @param {String} itemname
 * @param {Number} gradeduserid
 * @param {Boolean} notifyUser
 * @param {HTMLElement} rootNode
 * @returns {Promise}
 */
export const storeCurrentGrade = async(component, contextid, itemname, gradeduserid, notifyUser, rootNode) => {
    const form = rootNode.querySelector('form');
    if (compareData(form) !== true) {
        return '';
    }
    const formdata = new URLSearchParams(new FormData(form)).toString();
    return normaliseResult(await fetchMany([{
        methodname: 'gradingform_singlepoint_grader_gradingpanel_store',
        args: {component, contextid, itemname, gradeduserid, notifyuser: notifyUser, formdata},
    }])[0]);
};
