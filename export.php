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
 * This file contains the Export Quiz: exporting the file related code.
 *
 * @package    block_export_quiz
 * @copyright  2019 onwards Ashish Pawar (github : CustomAP)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format.php');

// Get the parameters from the URL.
$quizid = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$format = required_param('format', PARAM_ALPHANUMEXT);

if ($courseid) {
    require_login($courseid);
    $thiscontext = context_course::instance($courseid);
    $urlparams['courseid'] = $courseid;
} else {
    print_error('missingcourseorcmid', 'question');
}

require_sesskey();
$modinfo = get_fast_modinfo($courseid);
$cm = $modinfo->instances["quiz"][$quizid];
$context = context_module::instance($cm->id);
$quizcontextid = $context->id;
// Load the necessary data.
$contexts = new question_edit_contexts($thiscontext);
$params = [
    'quizcontextid' => $quizcontextid,
    'quizcontextid2' => $quizcontextid,
    'quizcontextid3' => $quizcontextid,
    'quizid' => $quizid,
    'quizid2' => $quizid,
];

$questions=$DB->get_records_sql("
            SELECT slot.slot,
                slot.id AS slotid,
                slot.page,
                slot.maxmark,
                slot.requireprevious,
                qsr.filtercondition,
                qv.status,
                qv.id AS versionid,
                qv.version,
                qr.version AS requestedversion,
                qv.questionbankentryid,
                q.id AS questionid,
                q.*,
                qc.id AS category,
                COALESCE(qc.contextid, qsr.questionscontextid) AS contextid
            FROM {quiz_slots} slot
            -- case where a particular question has been added to the quiz.
            LEFT JOIN {question_references} qr ON qr.usingcontextid = :quizcontextid AND qr.component = 'mod_quiz'
                                    AND qr.questionarea = 'slot' AND qr.itemid = slot.id
            LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            -- This way of getting the latest version for each slot is a bit more complicated
            -- than we would like, but the simpler SQL did not work in Oracle 11.2.
            -- (It did work fine in Oracle 19.x, so once we have updated our min supported
            -- version we could consider digging the old code out of git history from
            -- just before the commit that added this comment.
            -- For relevant question_bank_entries, this gets the latest non-draft slot number.
            LEFT JOIN (
            SELECT lv.questionbankentryid, MAX(lv.version) AS version
                FROM {quiz_slots} lslot
                JOIN {question_references} lqr ON lqr.usingcontextid = :quizcontextid2 AND lqr.component = 'mod_quiz'
                                    AND lqr.questionarea = 'slot' AND lqr.itemid = lslot.id
                JOIN {question_versions} lv ON lv.questionbankentryid = lqr.questionbankentryid
                WHERE lslot.quizid = :quizid2
                AND lqr.version IS NULL
                AND lv.status ='ready'
            GROUP BY lv.questionbankentryid
            ) latestversions ON latestversions.questionbankentryid = qr.questionbankentryid
            LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                -- Either specified version, or latest ready version.
                                AND qv.version = COALESCE(qr.version, latestversions.version)
            LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            LEFT JOIN {question} q ON q.id = qv.questionid
            -- Case where a random question has been added.
            LEFT JOIN {question_set_references} qsr ON qsr.usingcontextid = :quizcontextid3 AND qsr.component = 'mod_quiz'
                                    AND qsr.questionarea = 'slot' AND qsr.itemid = slot.id
            WHERE slot.quizid = :quizid
            ORDER BY slot.slot
            ", $params); 
            
$questiondata = array();
if ($questions) {
    foreach ($questions as $question) {
        array_push($questiondata, question_bank::load_question_data($question->questionid));
    }
}

/**
 * Check if the Quiz is visible to the user only then display it :
 * Teacher can choose to hide the quiz from the students in that case it should not be visible to students
 */


if(!$cm->uservisible)
    print_error('noaccess', 'block_export_quiz');

// Initialise $PAGE.
$nexturl = new moodle_url('/question/type/stack/questiontestrun.php', $urlparams);
$PAGE->set_url('/blocks/export_quiz/export.php', $urlparams);
$PAGE->set_heading(get_string('pluginname','block_export_quiz'));
$PAGE->set_pagelayout('admin');

// Check if the question format is readable, if yes import it : This way support is added for any third-party question format installed.
if (!is_readable($CFG->dirroot . "/question/format/{$format}/format.php")) {
    print_error('unknowformat', '', '', $format);
} else {
    require_once($CFG->dirroot . "/question/format/{$format}/format.php");
}

// Set up the export format.
$classname = 'qformat_' . $format;
$qformat = new $classname();
$qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
$qformat->setCourse($COURSE);
$qformat->setCattofile(false);
$qformat->setContexttofile(false);
$qformat->setQuestions($questiondata);

// Get quiz name to assign it to file name used for exporting.
$filename = $cm->name. $qformat->export_file_extension();

// Pre-processing the export.
if (!$qformat->exportpreprocess()) {
    send_file_not_found();
}

/* Actual export process to get the converted string
 * Check capabilites set to false since already checks done for quiz availability
 * This also adds the functionality of exporting the quiz for the students
 */
if (!$content = $qformat->exportprocess(false)) {
    send_file_not_found();
}

send_file($content, $filename, 0, 0, true, true, $qformat->mime_type());
