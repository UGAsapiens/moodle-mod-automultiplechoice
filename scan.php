<?php

/**
 * Upload then analyzes the scanned pages
 *
 * @package    mod_automultiplechoice
 * @copyright  2013 Silecs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* @var $DB moodle_database */
/* @var $PAGE moodle_page */
/* @var $OUTPUT core_renderer */

global $DB, $OUTPUT, $PAGE;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once __DIR__ . '/models/Quizz.php';
require_once __DIR__ . '/models/AmcProcess.php';

$a  = optional_param('a', 0, PARAM_INT);  // automultiplechoice instance ID

if ($a) {
    $quizz = \mod\automultiplechoice\Quizz::findById($a);
    $course     = $DB->get_record('course', array('id' => $quizz->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('automultiplechoice', $quizz->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify an instance ID');
}

require_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/automultiplechoice:view', $context);

/// Print the page header

$PAGE->set_url('/mod/automultiplechoice/scan.php', array('id' => $cm->id));
$PAGE->set_title(format_string($quizz->name . " - envoi des scans"));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$PAGE->requires->css(new moodle_url('assets/amc.css'));

// Output starts here
echo $OUTPUT->header();
echo $OUTPUT->heading($quizz->name . " - envoi des scans");

$process = new \mod\automultiplechoice\AmcProcess($quizz);
//var_dump($process);

if (isset ($_FILES['scanfile']) ) { // Fichier reçu
    if ($_FILES['scanfile']["error"] > 0) {
        echo "Erreur: " . $_FILES['scanfile']['error'] . "<br>";
    } else {
        $filename = '/tmp/' . $_FILES['scanfile']['name'];
        rename($_FILES['scanfile']['tmp_name'], $filename);

        echo "Upload : " . $_FILES['scanfile']['name'] . "<br>";
        echo "Type : " . $_FILES['scanfile']['type'] . "<br>";
        echo "Taille : " . round($_FILES['scanfile']['size'] / 1024) . " ko<br>";
        echo "Emplacement : " . $filename;
        echo "<br><br>\n";

        /** @todo ce bloc meptex est-il nécessaire ? **/
        $diag = $process->amcMeptex();
        if ($diag) {
            echo $OUTPUT->heading("Mise en page / initialisation sqlite (amc meptex) terminée.");
        } else {
            echo "<p>Erreur lors du calcul de mise en page (amc meptex).</p>\n";
        }

        $npages = $process->amcGetimages($filename);
        if ($npages) {
            echo "Pages : " . $npages ."<br>";
        } else {
            echo "Erreur découpage scan (amc getimages) <br>";
        }

        $analyse = $process->amcAnalyse(true);
        if ($analyse) {
            echo "Analyse terminée. <br>";
        } else {
            echo "Erreur analyse (amc analyse) <br>.";
        }

    }

} else {

    // Upload du fichier
    echo '<form action="scan.php?a='. $quizz->id .'" method="post" enctype="multipart/form-data">' . "\n";
    echo '<label for="scanfile">Fichier scan (PDF, TIFF...)</label>'. "\n" ;
    echo '<input type="file" name="scanfile" id="scanfile"><br>' . "\n" ;
    echo '<input type="submit" name="submit" value="Envoyer">'. "\n" ;
    echo '</form>' . "\n" ;

}

echo button_back_to_activity($quizz->id);

echo $OUTPUT->footer();
