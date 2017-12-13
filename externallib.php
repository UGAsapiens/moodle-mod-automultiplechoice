<?php


defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

// https://docs.moodle.org/dev/Adding_a_web_service_to_a_plugin

class mod_automultiplechoice_external extends external_api {

      /**
       * Returns description of method parameters
       * @return external_function_parameters
       */
      public static function call_amc_parameters() {
        return new external_function_parameters(
            array(
              'action' => new external_value(PARAM_TEXT, PARAM_REQUIRED),
              'params' => new external_value(PARAM_TEXT, PARAM_REQUIRED), // json_encoded data
            )
        );
      }

      /**
       * Returns a json encoded string containing usefull data
       * @return external_value
       */
      public static function call_amc_returns() {
        return new external_value(PARAM_TEXT, 'json encoded response from server');
      }

      /**
       * Call amc commands and returns usefull data for the user
       * @param  string $action the action to launch
       * @param  string $params json encoded data for the action
       * @return string json encoded data
       */
      public static function call_amc($action, $params) {
          $requestdata = self::validate_parameters(self::call_amc_parameters(), array('action' => $action, 'params' => $params));

          $action = $requestdata['action'];
          $params = json_decode($requestdata['params']);

          // depending on action call the right process.
          switch($action) {
              case 'hello':
                $quizhelper = new \mod_automultiplechoice\local\models\quiz();
                $quizrecord = $quizhelper->findById($params->id);
                $quiz = $quizhelper->readFromRecord($quizrecord);
                // could call any specific process and handle response properly.
                $fakeprocess = new \mod_automultiplechoice\local\amc\process($quiz);
                $result = [
                  'status' => 200,
                  'errors' => [],
                  'data' => 'Hello ' . $params->firstname . ' ' . $params->lastname . ' you are accessing the quiz ' . $quiz->name
                ];
                return json_encode($result);
              break;
              case 'meptex':
              break;
              case 'getimages':
              break;
              case 'prepare':
              break;
              case 'note':
              break;
              case 'analyse':
              break;
              case 'association-auto':
              break;
              case 'association':
              break;
              case 'annote':
              break;
              case 'scan-stats':
              break;
          }

      }

}
