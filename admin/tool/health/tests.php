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
 * DML layer tests.
 *
 * @package    admin_tools_health
 * @category   phpunit
 * @copyright  2016 Grigory Baleevskiy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class core_tools_healthcheck_00013_update extends database_driver_testcase {

    public function test_valid_data(){
        global $DB;
        for($i=1; $i<=5; $i++) {
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
            for($j = $i * 5 + 1; $j< ($i + 1) * 5; $j++){
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


class problem_000013_new {
    function exists()
    {
        global $DB;

        $group_len = $DB->sql_length($DB->sql_group_concat('id', 'question'));
        $table_should_be = "(SELECT $group_len as group_len, parent FROM {question} as qp where parent>0 group by parent)";
        $seq_len_sql = $DB->sql_length('sequence');
        $query = "SELECT 1 FROM {question_multianswer}  AS qma"
            . " LEFT JOIN $table_should_be as should_be on (should_be.parent = qma.question)"
            . " WHERE $seq_len_sql <> should_be.group_len or should_be.group_len IS NULL";

        $id_position_sql = $DB->sql_position(
            $DB->sql_concat("','", 'q.id', "','"),
            $DB->sql_concat("','", 'qma.sequence', "','")
        );

        return $DB->record_exists_sql($query) ||
        $DB->record_exists_sql("
            SELECT * FROM {question} q
             JOIN {question_multianswer} qma ON (qma.question = q.parent)
             WHERE $id_position_sql = 0
            ");
    }
}


class problem_000013_old {
    function exists()
    {
        global $DB;
        $positionexpr = $DB->sql_position($DB->sql_concat("','", "q.id", "','"),
                $DB->sql_concat("','", "qma.sequence", "','"));

        return $DB->record_exists_sql("
                SELECT * FROM {question} q
                    JOIN {question_multianswer} qma ON $positionexpr > 0
                WHERE qma.question <> q.parent");
    }
}

