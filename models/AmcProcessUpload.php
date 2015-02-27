<?php
/**
 * @package    mod
 * @subpackage automultiplechoice
 * @copyright  2014 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod\automultiplechoice;

require_once __DIR__ . '/AmcProcess.php';
require_once __DIR__ . '/Log.php';

class AmcProcessUpload extends AmcProcess
{
    public $nbPages = 0;

    public function upload($filename) {
        if ($this->quizz->hasScans()) {
            $this->deleteGrades();
        }

        $this->nbPages = $this->amcGetimages($filename);
        if (!$this->nbPages) {
            $this->errors[] = "Erreur découpage scan (amc getimages)";
        }

        $analyse = $this->amcAnalyse(true);
        if (!$analyse) {
            $this->errors[] = "Erreur lors de l'analyse (amc analyse).";
        }
    }

    /**
     * @return boolean
     */
    private function deleteGrades() {
        $scoringFile = $this->workdir . "/data/scoring.sqlite";
        $a = array_map('unlink', glob($this->workdir . '/exports/*.csv'));
        /**
         * @todo Delete Moodle grades!
         */
        if (file_exists($scoringFile)) {
            return unlink($scoringFile);
        } else {
            return true;
        }
    }

    /**
     * @return boolean
     */
    public function deleteUploads() {
        array_map('unlink', $this->findScannedFiles());
        array_map('unlink', glob($this->workdir . '/cr/*.jpg'));
        if (is_dir($this->workdir . '/cr/corrections')) {
            array_map('unlink', glob($this->workdir . '/cr/corrections/jpg/*'));
            array_map('unlink', glob($this->workdir . '/cr/corrections/pdf/*'));
        }
        if (is_dir($this->workdir . '/cr/zooms')) {
            array_map('unlink', glob($this->workdir . '/cr/zooms/*'));
        }
        $captureFile = $this->workdir . "/data/capture.sqlite";
        if (file_exists($captureFile)) {
            unlink($captureFile);
        }
        return $this->deleteGrades();
    }

    /**
     * @return boolean
     */
    public function deleteFailed($scan) {
        if (extension_loaded('sqlite3')){   
            $capture = new \SQLite3($this->workdir . '/data/capture.sqlite',SQLITE3_OPEN_READWRITE);
            if ($scan=='all'){
                $results = $capture->query('SELECT * FROM capture_failed');
                while ($row = $results->fetchArray()) {
                    $scan = substr($row[0],14);
                    array_map('unlink', glob($this->workdir . '/scans/'.$scan));
                }
                return  $capture->exec('DELETE FROM capture_failed ');
            }else{
                $result = $capture->querySingle('SELECT * FROM capture_failed WHERE filename LIKE %'.$scan);
                if ($result){
                    array_map('unlink', glob($this->workdir . '/scans/'.$scan));
                    return  $capture->exec('DELETE FROM capture_failed WHERE filename LIKE %'.$scan);
                }
            }
        return false;
        }
    }
/**
     * @return boolean
     */
    public function downloadFailed() {
        if (extension_loaded('sqlite3')){   
            $capture = new \SQLite3($this->workdir . '/data/capture.sqlite',SQLITE3_OPEN_READWRITE);
            $results = $capture->query('SELECT * FROM capture_failed');
            $scans = array();
            while ($row = $results->fetchArray()) {
                $scans[] = substr($row[0],14);
                
            }
            $output = $this->normalizeFilename('failed');
            $scans[] = $output;
            $res = $this->shellExec('convert ',$scan);
            if ($res){
                redirect($this->getFileUrl($this->normalizeFilename('failed')));
            }
            return $res;
        }
        return false;
    }



    /**
    *      * @return string
    *           */
    public function list_failed() {
    global $OUTPUT;    
    if (extension_loaded('sqlite3')){   
        $capture = new \SQLite3($this->workdir . '/data/capture.sqlite',SQLITE3_OPEN_READONLY);
        $results = $capture->query('SELECT * FROM capture_failed');
        $failedoutput = $OUTPUT->heading('Scans non reconnus',3,'helptitle');
        $failedoutput .= \html_writer::start_div('box generalbox boxaligncenter');
        $deleteallurl = new \moodle_url('uploadscans.php', array('a' => $this->quizz->id, 'action' => 'delete','scan'=>'all'));
        $failedoutput .= $OUTPUT->single_button($deleteallurl, 'Effacer tous les scans non reconnus', array('action'=>new \confirm_action(get_string('confirm'))));
        $downloadfailedurl = new \moodle_url('uploadscans.php', array('a' => $this->quizz->id, 'action' => 'failed'));
        $failedoutput .= $OUTPUT->single_button($deleteallurl, 'Effacer tous les scans non reconnus', array('action'=>new \confirm_action(get_string('confirm'))));
        
        $failedoutput .= \html_writer::start_tag('ul',array('class'=>'unlist'));
        while ($row = $results->fetchArray()) {
            $scan = substr($row[0],14);
            $url = new \moodle_url('uploadscans.php', array('a'=>$this->quizz->id,'action'=>'delete', 'scan'=>$scan));
            $deleteicon = $OUTPUT->action_icon($url,new \pix_icon('t/delete',get_string('delete')),new \confirm_action(get_string('confirm')));
            $scanoutput = \html_writer::link($this->getFileUrl($scan),$scan); 
            $failedoutput .= \html_writer::tag('li', $scanoutput . $deleteicon);

        }
        $failedoutput .= \html_writer::end_tag('ul' );
        $failedoutput .= \html_writer::end_div();
    }else{
        $failedoutput = 'Demandez à votre administrateur système d\'installer php-sqlite3 pour voir les fichiers non reconnus';
    }

        return $failedoutput;
    }
    /**
     * Shell-executes 'amc getimages'
     * @param string $scanfile name, uploaded by the user
     * @return bool
     */
    private function amcGetimages($scanfile) {
        $pre = $this->workdir;
        $scanlist = $pre . '/scanlist';
        if (file_exists($scanlist)) {
            unlink($scanlist);
        }

        $res = $this->shellExecAmc('getimages', array(
            '--progression-id', 'analyse',
            //'--vector-density', '250',
            //'--debug=/tmp/amc-debug.txt',
            '--use-pdfimages',
            '--orientation', 'portrait',
            '--list', $scanlist,
            '--copy-to', $pre . '/scans/',
            '--force-convert', '1',
            $scanfile
            )
        );
        if ($res) {
            $nscans = count(file($scanlist));
            $this->log('getimages', $nscans . ' pages');
            return $nscans;
        }
        return $res;
    }

    /**
     * Shell-executes 'amc analyse'
     * @param bool $multiple (opt, true) If false, AMC will check that all the blank answer sheets were distinct.
     * @return bool
     */
    private function amcAnalyse($multiple = true) {
        $pre = $this->workdir;
        $scanlist = $pre . '/scanlist';
        $parammultiple = '--' . ($multiple ? '' : 'no-') . 'multiple';
        $parameters = array(
            $parammultiple,
            '--tol-marque', '0.2,0.2',
            '--prop', '0.8',
            '--bw-threshold', '0.6',
            '--progression-id' , 'analyse',
            '--progression', '1',
            '--n-procs', '0',
            '--data', $pre . '/data',
            '--projet', $pre,
            '--cr', $pre . '/cr',
            '--liste-fichiers', $scanlist,
            '--no-ignore-red',
            );
        //echo "\n<br> auto-multiple-choice analyse " . join (' ', $parameters) . "\n<br>";
        $res = $this->shellExecAmc('analyse', $parameters);
        if ($res) {
            $this->log('analyse', 'OK.');
        }
        return $res;
    }
}
