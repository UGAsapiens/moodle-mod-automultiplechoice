<?php

/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html  GNU GPL v3
 */

namespace mod\automultiplechoice;

require_once __DIR__ . '/ScoringGroup.php';

/**
 * Scoring system
 *
 * @author François Gannaz <francois.gannaz@silecs.info>
 */
class ScoringSystem
{
    /**
     * @var array of ScoringGroup.
     */
    protected static $groups = array();

    /**
     * @var boolean
     */
    protected static $parsedConfig = false;

    /**
     * Constructor. Reads Moodle config if necessary.
     */
    public function __construct() {
        if (!self::$parsedConfig) {
            $text = get_config('mod_automultiplechoice', 'scorings');
            self::$groups = $this->parseConfig($text);
        }
    }

    /**
     * Creates a new Scoring System from the module configuration in the DB.
     *
     * @return \mod\automultiplechoice\ScoringSystem
     */
    public static function read($forceRead=false) {
        $system = new self;
        if (!self::$parsedConfig || $forceRead) {
            $text = get_config('mod_automultiplechoice', 'scorings');
            self::$groups = $system->parseConfig($text);
        }
        return $system;
    }

    /**
     * Return the HTML for a select element.
     *
     * @param string $name HTML name.
     * @param string $value Current value (rank selected).
     * @return string HTML
     */
    public function toHtmlSelect($name, $value) {
        $html = '<select name="' . $name . '">'
                . '<option value=""></option>';
        foreach($this->groups as $rank => $scoringGroup) {
            /* @var $scoringGroup ScoringGroup */
            $html .= '<option value="' . $rank . '"'
                . ($value !== '' && $value == $rank ? ' selected="selected">' : '>')
                . htmlspecialchars($scoringGroup->name)
                . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * Gets a ScoringGroup by its rank in the config.
     *
     * @param integer $rank
     * @return ScoringGroup
     * @throws Exception
     */
    public function getScoringGroup($rank) {
        if (!isset(self::$groups[$rank])) {
            throw new Exception("This scoring group does not exist.");
        }
        return self::$groups[$rank];
    }

    /**
     * Parses the config text.
     *
     * @return array of ScoringGroup instances.
     */
    protected function parseConfig($rawText) {
        $blocks = preg_split('/\n-{3,}\s*\n/', $rawText);
        $scoringGroups = array();
        foreach ($blocks as $block) {
            $scoringGroups[] = ScoringGroup::buildFromConfig($block);
        }
        return $scoringGroups;
    }
}