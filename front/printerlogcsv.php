<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
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

require_once(__DIR__ . '/_check_webserver_config.php');

use Glpi\Csv\CsvResponse;
use Glpi\Csv\PrinterLogCsvExport;
use Glpi\Csv\PrinterLogCsvExportComparison;
use Safe\DateTime;

Session::checkRight("printer", READ);

if (isset($_GET["id"])) {
    $printers = array_map(fn($id) => Printer::getById($id), $_GET["id"]);
    $interval = $_GET['interval'] ?? 'P1Y';
    $start = empty($_GET['start']) ? null : new DateTime($_GET['start']);
    $end = empty($_GET['end']) ? new DateTime() : new DateTime($_GET['end']);
    $format = $_GET['format'] ?? 'dynamic';

    if (count($printers) > 1) {
        CsvResponse::output(
            new PrinterLogCsvExportComparison(
                $printers,
                $interval,
                $start,
                $end,
                $format,
                $_GET['statistic'] ?? 'total_pages'
            ),
        );
    } else {
        CsvResponse::output(
            new PrinterLogCsvExport(
                array_shift($printers),
                $interval,
                $start,
                $end,
                $format
            ),
        );
    }
}
