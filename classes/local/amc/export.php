<?php

namespace mod_automultiplechoice\local\amc;

require_once(__DIR__ . './../../../locallib.php');

class export extends \mod_automultiplechoice\local\amc\process
{
    /**
     * Shell-executes 'amc prepare' for creating pdf files
     *
     * @param string $formatName "txt" | "latex"
     * @return bool
     */
    public function amcCreatePdf($formatName) {
        $pre = $this->workdir;
        $file = $pre . '/' . $this->normalizeFilename('sujet');
        $this->errors = array();
        $path = get_config('mod_automultiplechoice', 'xelatexpath');
        if ($path === '') {
            $path = '/usr/bin/xelatex';
        }
        $amclog = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id);
        // check if any change(s) occured (scoring changed / questions changed)
        $res = $amclog->check('pdf');
        if (!$res && file_exists($file)) {
            return true;
        }
        $format = $this->saveFormat($formatName);

        if (!$format) {
            return false;
        }
        $this->getLogger()->clear();
        $res = $this->shellExecAmc('prepare',
            array(
                '--n-copies', (string) $this->quiz->amcparams->copies,
                '--with', $path,
                '--filter', $format->getFiltername(),
                '--mode', 's[c]',
                '--prefix', $pre,
                '--out-sujet', $file,
                '--out-catalog', $pre . '/' . $this->normalizeFilename('catalog'),
                '--out-calage', $pre . '/prepare-calage.xy',
                '--latex-stdout',
                $pre . '/' . $format->getFilename()
            )
        );

        if ($res) {
            $amclog = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id);
            $this->log('prepare:pdf', 'catalog corrige sujet');
            $amclog->write('pdf');
        } else {
            $this->errors[] = get_string('export_amc_cmd_failed', 'mod_automultiplechoice', ['cmd' => 'auto-multiple-choice prepare']);
        }
        return $res;
    }
    /**
     * Shell-executes 'amc prepare' for creating pdf files
     *
     * @param string $formatName "txt" | "latex"
     * @return bool
     */
    public function amcCreateCorrection() {
        $pre = $this->workdir;
        $file = $pre . '/' . $this->normalizeFilename('corriges');
        $this->errors = array();
        $path = get_config('mod_automultiplechoice', 'xelatexpath');
        if ($path === '') {
            $path = '/usr/bin/xelatex';
        }
        $amclog = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id);
        $res = $amclog->check('corrected');
        if (!$res && file_exists($file)) {
            return true;
        }
        $res = $this->shellExecAmc('prepare',
            array(
                '--n-copies', (string) $this->quiz->amcparams->copies,
                '--with', $path,
                '--filter', $this->format->getFiltername(),
                '--mode', 'k',
                '--prefix', $pre,
                '--out-corrige', $file,
                '--latex-stdout',
                $pre . '/' . $this->format->getFilename()
            )
        );
        if ($res) {
            // /!\ this->log calls moodle logs api ... but is deprecated
            $this->log('corrected:pdf', 'corrige sujet');
            $amclog->write('corrected');
        } else {
            $this->errors[] = get_string('export_amc_cmd_failed', 'mod_automultiplechoice', ['cmd' => 'auto-multiple-choice prepare']);
        }
        return $res;
    }
    /**
     * Executes "amc imprime" then zip the resulting files
     * @return bool
     */
    public function zip() {
        $pre = $this->workdir;
        $zipfile = $pre . '/' . $this->normalizeFilename('sujets');
        if (file_exists($zipfile)) {
            return true;
        }
        // Clean up, or some obsolete files will stay in the zip
        $mask = $pre . "/imprime/*.pdf";
        $zip = new \ZipArchive();
        $ret = $zip->open($zipfile, \ZipArchive::CREATE);
        if (!$ret) {
            $this->errors[] = get_string('export_archive_open_failed', 'mod_automultiplechoice', ['error' => $ret]);
        } else {
            $options = array('add_path' => 'sujets_amc/', 'remove_all_path' => true);
            $zip->addGlob($mask, GLOB_BRACE, $options);
            $this->errors[] = "<p>Zip de [" . $zip->numFiles . "] fichiers dans [" . basename($zip->filename) . "]</p>\n";
            $zip->close();
        }
        if (!file_exists($zipfile)) {
            $this->errors[] = '<strong>'. get_string('export_archive_create_failed', 'mod_automultiplechoice', ['mask' => $mask]) .'</strong>';
        }
        return $ret;
    }
    /**
     * Shell-executes 'amc imprime'
     * @return bool
     */
    public function amcImprime() {
        $pre = $this->workdir;
        $file = $pre . '/' . $this->normalizeFilename('sujets');
        if (file_exists($file)) {
            return true;
        }
        if (!is_dir($pre . '/imprime')) {
            mkdir($pre . '/imprime');
        }
        if (!$this->amcMeptex()) {
            $this->errors[] = get_string('documents_meptex_error', 'mod_automultiplechoice');
            return false;
        }
        $params = array(
            '--data', $pre . '/data',
            '--sujet', $pre . '/' . $this->normalizeFilename('sujet'),
            '--methode', 'file',
            '--output', $pre . '/imprime/sujet-%e.pdf'
        );
        $res = $this->shellExecAmc('imprime', $params);
        if ($res) {
            $this->log('imprime', '');
        }
        return $res;
    }
    /**
     *      * @return boolean
     *           */
    public function makeFailedPdf() {
        $file = $this->workdir.'/' .$this->normalizeFilename('failed');
        $amclog = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id);
        $res = $amclog->check('failed');
        if (!$res and file_exists($file)){
            return true;
        }
        if (extension_loaded('sqlite3')){
            $capture = new \SQLite3($this->workdir . '/data/capture.sqlite',SQLITE3_OPEN_READWRITE);
            $results = $capture->query('SELECT * FROM capture_failed');
            $scans = array();
            while ($row = $results->fetchArray()) {
                $scans[] = $this->workdir.substr($row[0],7);
            }
            $scans[] = $file;
            $res = $this->shellExec('convert ',$scans);
            return $res;
        }
        return $res;
    }
    /**
     * Shell-executes 'amc export' to get a csv file
     * @return bool
     */
    public function amcExport($type='csv') {
        $pre = $this->workdir;
        $file = ($type === 'csv') ? $pre . self::PATH_AMC_CSV : $pre . self::PATH_AMC_ODS;
        $warnings = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id)->check('exporting');
        if (!$warnings and file_exists($file)) {
            return true;
        }
        if (file_exists($file)) {
            if (!unlink($file)) {
                $this->errors[] = get_string('export_file_write_access_error', 'mod_automultiplechoice', ['file' => strtoupper($type)]);
                return false;
            }
        }
        if (!is_writable($pre . '/exports')) {
            $this->errors[] = get_string('export_dir_access_error', 'mod_automultiplechoice');
        }
        $oldcwd = getcwd();
        chdir($pre . '/exports');
        $csv = $this->get_students_list();
        $parameters = array(
            '--data', $pre . '/data',
            '--useall', '0',
            '--sort', 'n',
            '--no-rtl',
            '--output', $file,
            '--option-out', 'encodage=UTF-8',
            '--noms-encodage', 'UTF-8',
        );
        if ($csv != ' ') {
            $parameters[] = '--fich-noms';
            $parameters[] = $csv;
        }
        $parametersCsv = array_merge($parameters, array(
            '--module', 'CSV',
            '--csv-build-name', '(nom|surname) (prenom|name)',
            '--option-out', 'separateur=' . self::CSV_SEPARATOR,
            '--option-out', 'decimal=,',
            '--option-out', 'ticked=',
        ));
        $parametersOds = array_merge($parameters, array(
            '--module', 'ods',
            '--option-out', 'stats=1',
        ));
        if ($csv !== ' ') {
            $parametersCsv[] = '--option-out';
            $parametersCsv[] = 'columns=student.copy,student.key,name,surname,moodleid,groupslist';
            $parametersOds[] = '--option-out';
            $parametersOds[] = 'columns=student.copy,student.key,name,surname,groupslist';
        } else {
            $parametersCsv[] = '--option-out';
            $parametersCsv[] = 'columns=student.copy,student.key';
            $parametersOds[] = '--option-out';
            $parametersOds[] = 'columns=student.copy,student.key';
        }
        if ($type === 'csv') {
            $res = $this->shellExecAmc('export', $parametersCsv);
        } else {
            $res = $this->shellExecAmc('export', $parametersOds);
        }
        chdir($oldcwd);
        if ($res) {
            $this->log('export', 'scoring.csv');
            \mod_automultiplechoice\local\helpers\log::build($this->quiz->id)->write('exporting');
            return true;
        }
        if (!file_exists($csvfile) || !file_exists($odsfile)) {
            $this->errors[] = "Le fichier n'a pu être généré. Consultez l'administrateur.";
            return false;
        }
    }
    /**
     * Return an array of students with added fields for identified users.
     *
     * Initialize $this->grades.
     * Sets $this->usersknown and $this->usersunknown.
     *
     *
     * @return boolean Success?
     */
    public function writeFileApogeeCsv() {
        $input = $this->fopenRead($this->workdir . self::PATH_AMC_CSV);
        if (!$input) {
            return false;
        }
        $output = fopen($this->workdir . self::PATH_APOGEE_CSV, 'w');
        if (!$output) {
            return false;
        }
        $header = fgetcsv($input, 0, self::CSV_SEPARATOR);
        if (!$header) {
            return false;
        }
        $getCol = array_flip($header);
        fputcsv($output, array('id','name','surname','groups', 'mark'), self::CSV_SEPARATOR);
        while (($data = fgetcsv($input, 0, self::CSV_SEPARATOR)) !== FALSE) {
            $idnumber = $data[$getCol['student.number']];
            if ($data[$getCol['A:id']]!='NONE'){
                fputcsv($output, array($data[$getCol['A:id']],$data[$getCol['name']],$data[$getCol['surname']],$data[$getCol['groupslist']], $data[6]), self::CSV_SEPARATOR);
            }
        }
        fclose($input);
        fclose($output);
        return true;
    }
  /**
      *      * lowl-level Shell-executes 'amc regroupe'
      *           * fills the cr/corrections/pdf directory with a global pdf file (parameter single==true) for all copies
      *                * or one pdf per student (single==false)
      *                     * @single bool
      *                          * @return bool
      *                               */
    protected function amcRegroupe() {
        $pre = $this->workdir;
        $parameters = array(
            /*'--id-file',  '', // undocumented option: only work with students whose ID is in this file*/
            '--no-compose',
            '--projet',  $pre,
            '--sujet', $pre. '/' . $this->normalizeFilename('sujet'),
            '--data', $pre.'/data',
            '--progression-id', 'regroupe',
            '--progression', '1',
            '--fich-noms', $this->get_students_list(),
            '--noms-encodage', 'UTF-8',
            '--sort', 'n',
            '--register',
            '--no-force-ascii',
            '--modele', 'cr-(N).pdf'
            /* // useless with no-compose
                    '--tex-src', $pre . '/' . $this->format->getFilename(),
                    '--filter', $this->format->getFilterName(),
                    '--with', 'xelatex',
                    '--filtered-source', $pre.'/prepare-source_filtered.tex',
                    '--n-copies', (string) $this->quiz->amcparams->copies,
                */
        );
        $res = $this->shellExecAmc('regroupe', $parameters);
        if ($res) {
            $this->log('regroup', '');
            $amclog = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id);
            $amclog->write('correction');
        }
        return $res;
    }
    /**
     *      * (high-level) executes "amc annote" then "amc regroupe" to get one or several pdf files
     *           * for the moment, only one variant is possible : ONE global file, NO compose
     *                * @todo (maybe) manages all variants
     *                     * @return bool
     *                          */
    public function amcAnnotePdf() {
        $pre = $this->workdir;
        $file = $pre.'/' .$this->normalizeFilename('corrections');
        $amclog = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id);
        $res = $amclog->check('annotatePdf');
        if (!$res and file_exists($file)){
            return true;
        }
        array_map('unlink', glob($pre.  "/cr/corrections/pdf/*.pdf"));
        if (!$this->amcRegroupe()) {
            return false;
        }
        $cmd  = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite "
            ." -sOutputFile=".$pre.'/'.$this->normalizeFilename('corrections')
            ." ".$pre."/cr/corrections/pdf/cr-*.pdf";
        $lines = array();
        $returnVal = 0;
        exec($cmd, $lines, $returnVal);
        $this->getLogger()->write($this->formatShellOutput($cmd, $lines, $returnVal));
        if ($returnVal === 0) {
            return true;
            $amclog = \mod_automultiplechoice\local\helpers\log::build($this->quiz->id);
            $amclog->write('annotePdf');
        } else {
            /**
             *              * @todo Fill $this->errors instead of outputing HTML on the fly
             *                           */
            $this->displayShellOutput($cmd, $lines, $returnVal, DEBUG_NORMAL);
            return false;
        }
    }
}