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


defined('MOODLE_INTERNAL') || die();

require_once('block_export_quiz_form.php');

class block_export_quiz extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_export_quiz');
    }

    public function applicable_formats() {
        return ['course-view' => true, 'mod-quiz' => true];
    }

    public function get_content_type() {
        return BLOCK_TYPE_TEXT;
    }

    public function get_content() {
        global $COURSE, $DB, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }
        // 1. Get the correct context
        $context = context_course::instance($COURSE->id);

        // 2. Check capability
        // We also allow Site Admins to always see it.
        if (!has_capability('block/export_quiz:addinstance', $context) && !is_siteadmin()) {
            return ''; 
        }

        $this->content = new stdClass();
        $this->content->text = '';

        $courseid = $this->page->course->id;
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $quizzes = $modinfo->instances['quiz'] ?? [];

        if (empty($quizzes)) {
            $this->content->text = get_string('noquizzes', 'block_export_quiz');
            return $this->content;
        }

        $quizids = array_keys($quizzes);
        list($in_sql, $params) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED);

        // Find quizzes with at least one question
        $sql = "SELECT DISTINCT slot.quizid
                FROM {quiz_slots} slot
                LEFT JOIN {question_references} qr ON qr.component = 'mod_quiz' AND qr.questionarea = 'slot' AND qr.itemid = slot.id
                LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.version = (SELECT MAX(v.version)
                                    FROM {question_versions} v
                                    JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                    WHERE be.id = qbe.id)
                AND slot.quizid $in_sql";

        $quizzes_with_questions = $DB->get_records_sql($sql, $params);
        $validquizids = array_keys($quizzes_with_questions);

        // Prepare arrays for form
        $quiztags = [];
        $quizhasquestions = [];

        foreach ($quizzes as $quiz) {
            if (!$quiz->uservisible) {
                continue;
            }

            $quiztags[$quiz->instance] = $quiz->name;
            $quizhasquestions[$quiz->instance] = in_array($quiz->instance, $validquizids);
        }

        // Create the form
        $export_quiz_form = new block_export_quiz_form(
            (string)$this->page->url,
            [
                'quiz' => $quiztags,
                'quizhasquestions' => $quizhasquestions
            ]
        );

        $export_quiz_form->set_data('');
        $this->content->text = $export_quiz_form->render();

        if ($export_quiz_form->is_cancelled()) {
            // do nothing
        } else if ($from_form = $export_quiz_form->get_data()) {
           $url = new moodle_url('/blocks/export_quiz/export.php', [
        'courseid' => $COURSE->id,
        'id'       => $from_form->quiz,
        'format'   => $from_form->format,
        'sesskey'  => sesskey()
        ]);


            if (!defined('BEHAT_SITE_RUNNING')) {
                $PAGE->requires->js_function_call('document.location.replace', [$url->out(false)], false, 1);
            }
        }

        return $this->content;
    }
}
