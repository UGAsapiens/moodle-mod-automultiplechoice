<?php

/**
 * @package    mod_automultiplechoice
 * @copyright  2014 Silecs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \mod\automultiplechoice as amc;

require_once(__DIR__ . '/locallib.php');

require_once __DIR__ . '/models/AmcProcessAssociate.php';

global $DB, $OUTPUT, $PAGE;
/* @var $DB moodle_database */
/* @var $PAGE moodle_page */
/* @var $OUTPUT core_renderer */

$controller = new amc\Controller();
$quizz = $controller->getQuizz();
$cm = $controller->getCm();
$course = $controller->getCourse();
$output = $controller->getRenderer('associating');

$action = optional_param('action', '', PARAM_ALPHA);
$mode = optional_param('mode', 'unknown', PARAM_ALPHA);
$usermode = optional_param('usermode', 'without', PARAM_ALPHA);
$idnumber= optional_param('idnumber', '',PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 20, PARAM_INT);        // how many per page
    
require_capability('mod/automultiplechoice:update', $controller->getContext());

/// Print the page header

$PAGE->set_url('/mod/automultiplechoice/associating.php', array('id' => $cm->id));
$PAGE->requires->css(new moodle_url('assets/amc.css'));
$url = new moodle_url('associating.php', array('a' => $quizz->id));
$process = new amc\AmcProcessAssociate($quizz);
 if ($action === 'associate') {
    if ($process->associate()) {
        redirect($url);
    }
} 
$process->get_association();


// Output starts here
echo $output->header();

echo $OUTPUT->box_start('informationbox well');
echo $OUTPUT->heading("Association", 2)
    . "<p>" . count($process->copyauto)." copies automatiquement identifiés, ".count($process->copymanual) . " copies manuellement identifiées et " . count($process->copyunknown) . " non identifiées. </p>";
$warnings = amc\Log::build($quizz->id)->check('associating');
if ($warnings) {
    echo '<div class="informationbox notifyproblem alert alert-error">';
    foreach ($warnings as $warning) {
        echo $warning;
    }

    echo "<br /><br />";
    echo HtmlHelper::buttonWithAjaxCheck('Relancer l\'associtation automatique', $quizz->id, 'associating', 'associate', 'process');
    echo "</div>";
}else if (count($process->copyauto)){
echo HtmlHelper::buttonWithAjaxCheck('Lancer l\'association', $quizz->id, 'associating', 'associate', 'process');
}else{
echo HtmlHelper::buttonWithAjaxCheck('Relancer l\'association', $quizz->id, 'associating', 'associate', 'process');
}
$optionsmode =  array ('unknown'  => get_string('unknown', 'automultiplechoice'),
                  'manual' => get_string('manual', 'automultiplechoice'),
                  'auto' => get_string('auto', 'automultiplechoice'),
                  'all'   => get_string('all', 'automultiplechoice'));
$selectmode = new single_select($url, 'mode', $optionsmode, $mode, null, "mode");
$selectmode->set_label(get_string('associationmode', 'automultiplechoice'));
if ($mode=='unknown'){
    $namedisplay = $this->copyunknown;
}else if ($mode=='manual'){
    $namedisplay = $this->copyamnual;
}else if ($mode=='auto'){
    $namedisplay = $this->copymanual;
}else if ($mode=='all'){
    $namedisplay = array_merge($this->copyunknown,$this->copymanual,$this->copyauto);
}
$optionsusermode =  array ('without'  => get_string('without', 'automultiplechoice'),
                  'all'   => get_string('all', 'automultiplechoice'));
$selectusermode = new single_select($url, 'usermode', $optionsusermode, $usermode, null, "usermode");
$selectusermode->set_label(get_string('associationusermode', 'automultiplechoice'));
$paging =  new paging_bar(count($namedisplay), $page, 20, $url, 'page');


echo $OUTPUT->render($selectmode);
echo $OUTPUT->render($selectusermode);
echo $OUTPUT->render($paging);
$namedisplay = array_slice($namedisplay,$page*$perpage, $perpage);
echo html_writer::start_tag('ul',array('class'=>'thumbnails'));
foreach ($namedisplay as $name=>$idnumber){
    $selectuser=  amc_get_students_select($url, $cm, $idnumber, $groupid, $usermode,array_merge($this->copymanual,$this->copyauto));
    $selectuser->set_label(get_string('associationuser', 'automultiplechoice'));

    $thumbnailnutput = \html_writer::img("name-".$process->getFileUrl($name).".jpg",$name);
    $thumbnailnutput .= \html_writer::img("name-".$process->getFileUrl($name).".jpg",$name);
    $thumbnaildiv= \html_writer::div($thumbnailnutput,'thumbnail');
    echo html_writer::tag('li', $sthumbnaildiv ,array('class'=>'span4')); 
}
echo html_writer::end_tag('ul');
echo $OUTPUT->box_end();


echo $output->footer();