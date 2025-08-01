<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

use Glpi\DBAL\QueryExpression;

use function Safe\preg_replace;

/**
 * Update from 9.4.1 to 9.4.2
 *
 * @return bool
 **/
function update941to942()
{
    /**
     * @var DBmysql $DB
     * @var Migration $migration
     */
    global $DB, $migration;

    $updateresult     = true;

    $migration->setVersion('9.4.2');

    /* Remove trailing slash from 'url_base' config */
    $migration->addPostQuery(
        $DB->buildUpdate(
            'glpi_configs',
            [
                'value' => new QueryExpression(
                    'TRIM(TRAILING ' . $DB->quoteValue('/') . ' FROM ' . $DB->quoteName('value') . ')'
                ),
            ],
            [
                'context' => 'core',
                'name'    => 'url_base',
            ]
        )
    );
    /* /Remove trailing slash from 'url_base' config */

    /** Fix URL of images inside ITIL objects contents */
    // This is an exact copy of the same process used in "update940to941()" which was working
    // on MariaDB but not on MySQL due to usage of "\d" in a REGEXP expression.
    // It has been fixed there for people who had not yet updated to 9.4.1 but have to
    // be put back here for people already having updated to 9.4.1.
    $migration->displayMessage(__('Fix URL of images in ITIL tasks, followups and solutions.'));

    // Search for contents that does not contains the itil object parameter after the docid parameter
    // (i.e. having a quote that ends the href just after the docid param value).
    // 1st capturing group is the end of href attribute value
    // 2nd capturing group is the href attribute ending quote
    $quotes_possible_exp   = ['\'', '&apos;', '&#39;', '&#x27;', '"', '&quot', '&#34;', '&#x22;'];
    $missing_param_pattern = '(document\.send\.php\?docid=[0-9]+)(' . implode('|', $quotes_possible_exp) . ')';

    $itil_mappings = [
        'Change' => [
            'itil_table' => 'glpi_changes',
            'itil_fkey'  => 'changes_id',
            'task_table' => 'glpi_changetasks',
        ],
        'Problem' => [
            'itil_table' => 'glpi_problems',
            'itil_fkey'  => 'problems_id',
            'task_table' => 'glpi_problemtasks',
        ],
        'Ticket' => [
            'itil_table' => 'glpi_tickets',
            'itil_fkey'  => 'tickets_id',
            'task_table' => 'glpi_tickettasks',
        ],
    ];

    $fix_content_fct = (fn($content, $itil_id, $itil_fkey) =>
        // Add itil object param between docid param ($1) and ending quote ($2)
        preg_replace(
            '/' . $missing_param_pattern . '/',
            '$1&amp;' . http_build_query([$itil_fkey => $itil_id]) . '$2',
            $content
        ));

    foreach ($itil_mappings as $itil_type => $itil_specs) {
        $itil_fkey  = $itil_specs['itil_fkey'];
        $task_table = $itil_specs['task_table'];

        // Fix followups and solutions
        foreach (['glpi_itilfollowups', 'glpi_itilsolutions'] as $itil_element_table) {
            $elements_to_fix = $DB->request(
                [
                    'SELECT'    => ['id', 'items_id', 'content'],
                    'FROM'      => $itil_element_table,
                    'WHERE'     => [
                        'itemtype' => $itil_type,
                        'content'  => ['REGEXP', $DB->escape($missing_param_pattern)],
                    ],
                ]
            );
            foreach ($elements_to_fix as $data) {
                $data['content'] = $DB->escape($fix_content_fct($data['content'], $data['items_id'], $itil_fkey));
                $DB->update($itil_element_table, $data, ['id' => $data['id']]);
            }
        }

        // Fix tasks
        $tasks_to_fix = $DB->request(
            [
                'SELECT'    => ['id', $itil_fkey, 'content'],
                'FROM'      => $task_table,
                'WHERE'     => [
                    'content'  => ['REGEXP', $DB->escape($missing_param_pattern)],
                ],
            ]
        );
        foreach ($tasks_to_fix as $data) {
            $data['content'] = $DB->escape($fix_content_fct($data['content'], $data[$itil_fkey], $itil_fkey));
            $DB->update($task_table, $data, ['id' => $data['id']]);
        }
    }
    /** /Fix URL of images inside ITIL objects contents */

    // ************ Keep it at the end **************
    $migration->executeMigration();

    return $updateresult;
}
