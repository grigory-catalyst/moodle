<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Health tests.
 *
 * @package    tool_health
 * @copyright  2016 Grigory Baleevskiy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class core_tools_healthcheck_00013_update
 * @copyright Grigory Baleevskiy
 * @license GPL
 */
class core_tools_healthcheck_00013_update extends database_driver_testcase {
    /**
     *  Tests whether old and new problem classes do the same logic
     */
    public function test_valid_data() {
        global $DB;
        for ($i = 1; $i <= 5; $i++) {
            $parent = $DB->insert_record('question',
                (object) array(
                    'parent' => '0',
                    'questiontext' => 'test',
                    'generalfeedback' => 'test',
                    'createdby' => 0,
                    'modifiedby' => 0,
                )
            );
            $mqa = array();
            for ($j = $i * 5 + 1; $j < ($i + 1) * 5; $j++) {
                $mqa[] = $DB->insert_record('question',
                    (object) array(
                        'parent' => $parent,
                        'questiontext' => 'testChild',
                        'generalfeedback' => 'testChild',
                        'createdby' => 0,
                        'modifiedby' => 0,
                    )
                );
            }

            $DB->insert_record('question_multianswer',
                (object) array(
                    'question' => $parent,
                    'sequence' => implode(',', $mqa),
                )
            );
        }

        $old = new problem_000013_old();
        $new = new problem_000013_new();

        $this->assertEquals(25, count($DB->get_records('question')));
        $this->assertEquals(5, count($DB->get_records('question_multianswer')));

        $this->assertEquals(false, $new->exists());
        $this->assertEquals(false, $old->exists());

        $DB->update_record('question', (object) array(
            'id' => 3,
            'parent' => 6,
            'questiontext' => 'testChild',
            'generalfeedback' => 'testChild',
            'createdby' => 0,
            'modifiedby' => 0,
        ));

        $this->assertEquals(true, $new->exists());
        $this->assertEquals(true, $old->exists());

    }
}

/**
 * Class problem_000013_new
 * @copyright Grigory Baleevskiy
 * @license GPL
 */
class problem_000013_new {
    /**
     * checks db consistency
     * @return bool
     */
    public function exists() {
        global $DB;

        $grouplen = $DB->sql_length($DB->sql_group_concat('id', 'question'));
        $tableshouldbe = "(SELECT $grouplen as group_len, parent FROM {question} as qp where parent>0 group by parent)";
        $seqlensql = $DB->sql_length('sequence');
        $query = "SELECT 1 FROM {question_multianswer} qma"
            . " LEFT JOIN $tableshouldbe as should_be on (should_be.parent = qma.question)"
            . " WHERE $seqlensql <> should_be.group_len or should_be.group_len IS NULL";

        $idpositionsql = $DB->sql_position(
            $DB->sql_concat("','", 'q.id', "','"),
            $DB->sql_concat("','", 'qma.sequence', "','")
        );

        return $DB->record_exists_sql($query) ||
        $DB->record_exists_sql("
            SELECT * FROM {question} q
             JOIN {question_multianswer} qma ON (qma.question = q.parent)
             WHERE $idpositionsql = 0
            ");
    }
}

/**
 * Class problem_000013_new
 * @copyright Grigory Baleevskiy
 * @license GPL
 */
class problem_000013_old {
    /**
     * checks db consistency
     * @return bool
     */
    public function exists() {
        global $DB;
        $positionexpr = $DB->sql_position($DB->sql_concat("','", "q.id", "','"),
                $DB->sql_concat("','", "qma.sequence", "','"));

        return $DB->record_exists_sql("
                SELECT * FROM {question} q
                    JOIN {question_multianswer} qma ON $positionexpr > 0
                WHERE qma.question <> q.parent");
    }
}
