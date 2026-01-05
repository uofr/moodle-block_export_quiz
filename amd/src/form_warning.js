/**
 * Export Quiz – Empty Quiz Warning & Button Guard
 *
 * This AMD module enhances the Export Quiz form UX by:
 *
 *  - Detecting quizzes that contain no questions
 *  - Displaying a warning message when such a quiz is selected
 *  - Disabling the Export button to prevent invalid submissions
 *
 * Data flow:
 *  - PHP passes an array of quiz IDs with no questions via js_call_amd()
 *  - The quiz <select> value contains the quiz ID
 *  - JS compares the selected quiz ID against the empty quiz ID list
 *
 * Behaviour:
 *  - No quiz selected       → warning hidden, export disabled
 *  - Quiz has questions    → warning hidden, export enabled
 *  - Quiz has no questions → warning shown, export disabled
 *
 * This logic is purely client-side UX enhancement.
 * Server-side validation still enforces the same rule.
 *
 * @module     block_export_quiz/form_warning
 * @author     Joel Dapiawen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        init: function(params) {
             // eslint-disable-next-line no-console
            console.log('Export quiz warning JS loaded');

            const emptyIds = (params.emptyquizids || []).map(String);
             // eslint-disable-next-line no-console
            console.log('Empty quizzes:', emptyIds);

            const $quiz = $('#id_quiz');
            const $warning = $('#export-quiz-warning');
            const $submit = $('#id_submitbutton');

            function updateWarning() {
                const val = $quiz.val();
                 // eslint-disable-next-line no-console
                console.log('Selected quiz:', val);

                // No quiz selected
                if (!val) {
                    $warning.addClass('d-none');
                    $submit.prop('disabled', true);
                    return;
                }

                // Quiz has no questions
                if (emptyIds.includes(val)) {
                    $warning.removeClass('d-none');
                    $submit.prop('disabled', true);
                } else {
                // Valid quiz
                    $warning.addClass('d-none');
                    $submit.prop('disabled', false);
                }
            }

            $quiz.on('change', updateWarning);
            updateWarning();
        }
    };
});
