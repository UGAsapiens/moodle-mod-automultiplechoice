<?php

/**
 * Shows details of a particular instance of automultiplechoice
 *
 * @package    mod_automultiplechoice
 * @copyright  2013 Silecs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* @var $DB moodle_database */
/* @var $PAGE moodle_page */
/* @var $OUTPUT core_renderer */

global $DB, $OUTPUT, $PAGE, $CFG;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->libdir/formslib.php");
require_once(dirname(__FILE__).'/lib.php');
require_once __DIR__ . '/models/Quizz.php';
require_once __DIR__ . '/models/AmcProcess.php';
require_once __DIR__ . '/models/HtmlHelper.php';

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$a  = optional_param('a', 0, PARAM_INT);  // automultiplechoice instance ID

if ($id) {
    $cm         = get_coursemodule_from_id('automultiplechoice', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $quizz = \mod\automultiplechoice\Quizz::findById($cm->instance);
} elseif ($a) {
    $quizz = \mod\automultiplechoice\Quizz::findById($a);
    $course     = $DB->get_record('course', array('id' => $quizz->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('automultiplechoice', $quizz->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

if (!count($quizz->questions)) {
    redirect(new moodle_url('qselect.php', array('a' => $quizz->id)));
}
require_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/automultiplechoice:view', $context);

add_to_log($course->id, 'automultiplechoice', 'view', "view.php?id={$cm->id}", $quizz->name, $cm->id);

/// Print the page header

$PAGE->set_url('/mod/automultiplechoice/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($quizz->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$PAGE->requires->jquery();
$PAGE->requires->js(new moodle_url('assets/scoring.js'));

// Output starts here
echo $OUTPUT->header();

if (!$quizz->validate()) {
    echo $OUTPUT->box_start('errorbox');
    echo '<p>' . get_string('someerrorswerefound') . '</p>';
    echo '<dl>';
    foreach ($quizz->errors as $field => $error) {
        echo "<dt>" . get_string($field, 'automultiplechoice') . "</dt>\n"
                . "<dd>" . get_string($error, 'automultiplechoice') . "</dd>\n";
    }
    echo "</dl>\n";
    echo $OUTPUT->box_end();
}

echo $OUTPUT->box_start();

echo $OUTPUT->heading($quizz->name);
echo '<table class="flexible boxaligncenter generaltable">';
echo '<tbody>';
echo '<tr><th>' . get_string('description', 'automultiplechoice') . '</th><td>' . format_string($quizz->description) . '</td></tr>';
echo '<tr><th>' . get_string('comment', 'automultiplechoice') . '</th><td>' . format_string($quizz->comment) . '</td></tr>';
echo '<tr><th>' . get_string('qnumber', 'automultiplechoice') . '</th><td>' . $quizz->qnumber . '</td></tr>';
echo '<tr><th>' . get_string('score', 'automultiplechoice') . '</th><td>' . $quizz->score . '</td></tr>';
echo '</tbody></table>';

echo $OUTPUT->box_start();
echo $OUTPUT->heading(
        html_writer::link(new moodle_url('qselect.php', array('a' => $quizz->id)), "Questions"),
        3
);

HtmlHelper::printFormFullQuestions($quizz);

echo '<p class="continuebutton">';
echo html_writer::link(
        new moodle_url('qselect.php', array('a' => $quizz->id)),
        get_string('editselection', 'automultiplechoice')
);
echo '</p>';

echo $OUTPUT->box_end();

echo $OUTPUT->box_start();

// Display prepared files (source & pdf)

echo "<ul>";
$process = new \mod\automultiplechoice\AmcProcess($quizz);
$srcprepared = $process->lastlog('prepare:source');
if ($srcprepared) {
    echo "<li>Un fichier source préparé le " . $srcprepared . "</li>\n";
} else {
    echo "<li>Aucun fichier source préparé.</li>\n";
}
$pdfprepared = $process->lastlog('prepare:pdf');
if ($pdfprepared) {
    echo "<li>Deux fichiers PDF préparés le " . $pdfprepared . "</li>\n";
} else {
    echo "<li>Aucun fichier PDF préparé.</li>\n";
}
echo "</ul>";


// Display available actions

$actions = array('prepare', 'analyse', 'note', 'export');
if (empty($quizz->errors)) {
    $options = array();
} else {
    echo '<p>' . get_string('functiondisabled') . '</p>';
    $options = array('disabled' => 'disabled');
}

foreach ($actions as $action) {
    $url = new moodle_url('/mod/automultiplechoice/' . $action. '.php', array('a' => $quizz->id));
    echo $OUTPUT->single_button($url, get_string($action, 'automultiplechoice') , 'post', $options);
    // tmp: disable buttons after the first one
    $options = array('disabled' => 'disabled');
}

echo $OUTPUT->box_end();

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
