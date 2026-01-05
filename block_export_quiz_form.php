<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the Export Quiz Block.
 *
 * @package    block_export_quiz
 * @copyright  2019 onwards Ashish Pawar (github : CustomAP)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/questionlib.php');


/**
 * Form to export questions from the quiz.
 *
 *  @copyright  2019 onwards Ashish Pawar (github : CustomAP)
 *     @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class block_export_quiz_form extends moodleform {

    function definition() {
        global $PAGE;

        $mform = $this->_form;
        $quizzes = $this->_customdata['quiz'] ?? [];
        $quizhasquestions = $this->_customdata['quizhasquestions'] ?? [];

// Build quiz options
$quizoptions = ['' => get_string('selectquiz', 'block_export_quiz')];
$emptyquizids = [];

    foreach ($quizzes as $quizid => $name) {
            if (empty($quizhasquestions[$quizid])) {
                $quizoptions[$quizid] = $name . ' (No questions)';
                $emptyquizids[] = (string)$quizid;
            } else {
                $quizoptions[$quizid] = $name;
            }
        }


        // Quiz select
        $mform->addElement('select', 'quiz', get_string('quiz', 'block_export_quiz'), $quizoptions);
        $mform->addElement(
            'html',
            '<div id="export-quiz-warning"
                class="alert alert-warning d-none"
                role="alert">
                <strong>Note:</strong> This quiz has no questions and cannot be exported.
            </div>'
        );
        // Format select
        $formats = get_import_export_formats('export');
        $formatoptions = [];
        foreach ($formats as $shortname => $name) {
            $formatoptions[$shortname] = $name;
        }

        $mform->addElement('select', 'format', get_string('format', 'block_export_quiz'), $formatoptions);
        $mform->setDefault('format', 'xml');

        // Submit
        $this->add_action_buttons(false, get_string('export', 'block_export_quiz'));

        // Static note
        $mform->addElement('html', '
            <div class="alert alert-warning" role="alert">
                <strong>Note:</strong> Random questions are not supported in the export.
            </div>
        ');

        $PAGE->requires->js_call_amd(
            'block_export_quiz/form_warning',
            'init',
            [
                 ['emptyquizids' => $emptyquizids]
            ]
        );
    }

    function validation($data, $files) {
        $errors = [];
        $quizhasquestions = $this->_customdata['quizhasquestions'] ?? [];

        if (empty($data['quiz'])) {
            $errors['quiz'] = get_string('required');
            return $errors;
        }

        $quizid = (int)($data['quiz'] ?? 0);

        if ($quizid && empty($quizhasquestions[$quizid])) {
            $errors['quiz'] = get_string('quizhasnoquestions', 'block_export_quiz');
        }

        return $errors;
    }
}
