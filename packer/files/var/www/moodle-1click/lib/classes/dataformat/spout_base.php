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
 * Common Spout class for dataformat.
 *
 * @package    core
 * @subpackage dataformat
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\dataformat;

/**
 * Common Spout class for dataformat.
 *
 * @package    core
 * @subpackage dataformat
 * @copyright  2016 Brendan Heywood (brendan@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class spout_base extends \core\dataformat\base {

    /** @var $spouttype */
    protected $spouttype = '';

    /** @var $writer */
    protected $writer;

    /** @var $sheettitle */
    protected $sheettitle;

    /** @var $renamecurrentsheet */
    protected $renamecurrentsheet = false;

    /**
     * Output file headers to initialise the download of the file.
     */
    public function send_http_headers() {
        $this->writer = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createWriter($this->spouttype);
        if (method_exists($this->writer, 'setTempFolder')) {
            $this->writer->setTempFolder(make_request_directory());
        }
        $filename = $this->filename . $this->get_extension();
        if (PHPUNIT_TEST) {
            $this->writer->openToFile('php://output');
        } else {
            $this->writer->openToBrowser($filename);
        }

        // By default one sheet is always created, but we want to rename it when we call start_sheet().
        $this->renamecurrentsheet = true;
    }

    /**
     * Set the dataformat to be output to current file
     */
    public function start_output_to_file(): void {
        $this->writer = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createWriter($this->spouttype);
        if (method_exists($this->writer, 'setTempFolder')) {
            $this->writer->setTempFolder(make_request_directory());
        }

        $this->writer->openToFile($this->filepath);

        // By default one sheet is always created, but we want to rename it when we call start_sheet().
        $this->renamecurrentsheet = true;

        $this->start_output();
    }

    /**
     * Set the title of the worksheet inside a spreadsheet
     *
     * For some formats this will be ignored.
     *
     * @param string $title
     */
    public function set_sheettitle($title) {
        $this->sheettitle = $title;
    }

    /**
     * Write the start of the sheet we will be adding data to.
     *
     * @param array $columns
     */
    public function start_sheet($columns) {
        if ($this->sheettitle && $this->writer instanceof \Box\Spout\Writer\WriterMultiSheetsAbstract) {
            if ($this->renamecurrentsheet) {
                $sheet = $this->writer->getCurrentSheet();
                $this->renamecurrentsheet = false;
            } else {
                $sheet = $this->writer->addNewSheetAndMakeItCurrent();
            }
            $sheet->setName($this->sheettitle);
        }
        $row = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray((array)$columns);
        $this->writer->addRow($row);
    }

    /**
     * Write a single record
     *
     * @param array $record
     * @param int $rownum
     */
    public function write_record($record, $rownum) {
        $row = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray($this->format_record($record));
        $this->writer->addRow($row);
    }

    /**
     * Write the end of the file.
     */
    public function close_output() {
        $this->writer->close();
        $this->writer = null;
    }

    /**
     * Write data to disk
     *
     * @return bool
     */
    public function close_output_to_file(): bool {
        $this->close_output();

        return true;
    }
}
