<?php

/*
 * @license http://www.gnu.org/licenses/gpl-3.0.html  GNU GPL v3
 */

global $CFG;

require_once dirname(dirname(dirname(__DIR__))) . '/config.php';
require_once dirname(__DIR__) . '/models/Quizz.php';
require_once dirname(__DIR__) . '/models/AmcProcessPrepare.php';

$a  = optional_param('a', 0, PARAM_INT);  // automultiplechoice instance ID
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$redirect = optional_param('redirect', false, PARAM_BOOL);

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

$process = new \mod\automultiplechoice\AmcProcessPrepare($quizz);

if ($action == 'prepare') {
    if ($process->saveAmctxt()) {
        debugging("Fichier source enregistré.", DEBUG_NORMAL);
    } else {
        error("Erreur sur fichier source.");
    }

    if ($process->createPdf()) {
        echo "<h3>Fichiers PDF nouvellement créés</h3>";
        echo $process->htmlPdfLinks();
    } else {
        error("Erreur lors de la création des fichiers PDF.");
    }

    if ($process->amcMeptex()) {
        debugging("Mise en page (amc meptex) terminée.", DEBUG_NORMAL);
    } else {
        error("Erreur lors du calcul de mise en page (amc meptex).");
    }
}

if ($redirect) {
    redirect('/mod/automultiplechoice/prepare.php?a=' . $quizz->id);
}