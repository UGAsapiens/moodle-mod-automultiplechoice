<?php

namespace mod_automultiplechoice\local\helpers;


class flash_message_manager {

    private static $toMoodleClass = [
        'success' => 'success',
        'error' => 'problem',
        'warning' => 'problem',
        'info' => 'tiny',
    ];

    static public function init() {
        self::clearMessages();
    }

    static public function addMessage($category, $message) {
        global $SESSION;
        $SESSION->flashmessages[$category][] = $message;
    }

    static public function displayMessages($clearAfterwards = true) {
        global $SESSION;
        global $OUTPUT;
        /* @var $OUTPUT \renderer_base */
        if (empty($SESSION->flashmessages)) {
            return;
        }
        foreach ($SESSION->flashmessages as $status => $messages) {
            if ($messages) {
                $class = "notify" . self::$toMoodleClass[$status];
                foreach ($messages as $message) {
                    echo $OUTPUT->notification($message, $class);
                }
            }
        }
        if ($clearAfterwards) {
            self::clearMessages();
        }
    }

    static public function clearMessages() {
        global $SESSION;
        $SESSION->flashmessages = array(
            'success' => array(),
            'error' => array(),
            'warning' => array(),
            'info' => array(),
        );
    }
}
