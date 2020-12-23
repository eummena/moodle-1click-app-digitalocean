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
 * Unit tests for format_text defined in weblib.php.
 *
 * @package   core
 * @category  test
 * @copyright 2015 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Unit tests for format_text defined in weblib.php.
 *
 * @copyright 2015 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class core_weblib_format_text_testcase extends advanced_testcase {

    public function test_format_text_format_html() {
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertRegExp('~^<p><img class="icon emoticon" alt="smile" title="smile" ' .
                'src="https://www.example.com/moodle/theme/image.php/_s/boost/core/1/s/smiley" /></p>$~',
                format_text('<p>:-)</p>', FORMAT_HTML));
    }

    public function test_format_text_format_html_no_filters() {
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertEquals('<p>:-)</p>',
                format_text('<p>:-)</p>', FORMAT_HTML, array('filter' => false)));
    }

    public function test_format_text_format_plain() {
        // Note FORMAT_PLAIN does not filter ever, no matter we ask for filtering.
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertEquals(':-)',
                format_text(':-)', FORMAT_PLAIN));
    }

    public function test_format_text_format_plain_no_filters() {
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertEquals(':-)',
                format_text(':-)', FORMAT_PLAIN, array('filter' => false)));
    }

    public function test_format_text_format_markdown() {
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertRegExp('~^<p><em><img class="icon emoticon" alt="smile" title="smile" ' .
                'src="https://www.example.com/moodle/theme/image.php/_s/boost/core/1/s/smiley" />' .
                '</em></p>\n$~',
                format_text('*:-)*', FORMAT_MARKDOWN));
    }

    public function test_format_text_format_markdown_nofilter() {
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertEquals("<p><em>:-)</em></p>\n",
                format_text('*:-)*', FORMAT_MARKDOWN, array('filter' => false)));
    }

    public function test_format_text_format_moodle() {
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertRegExp('~^<div class="text_to_html"><p>' .
                '<img class="icon emoticon" alt="smile" title="smile" ' .
                'src="https://www.example.com/moodle/theme/image.php/_s/boost/core/1/s/smiley" /></p></div>$~',
                format_text('<p>:-)</p>', FORMAT_MOODLE));
    }

    public function test_format_text_format_moodle_no_filters() {
        $this->resetAfterTest();
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->assertEquals('<div class="text_to_html"><p>:-)</p></div>',
                format_text('<p>:-)</p>', FORMAT_MOODLE, array('filter' => false)));
    }

    public function test_format_text_overflowdiv() {
        $this->assertEquals('<div class="no-overflow"><p>Hello world</p></div>',
                format_text('<p>Hello world</p>', FORMAT_HTML, array('overflowdiv' => true)));
    }

    /**
     * Test adding blank target attribute to links
     *
     * @dataProvider format_text_blanktarget_testcases
     * @param string $link The link to add target="_blank" to
     * @param string $expected The expected filter value
     */
    public function test_format_text_blanktarget($link, $expected) {
        $actual = format_text($link, FORMAT_MOODLE, array('blanktarget' => true, 'filter' => false, 'noclean' => true));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for the test_format_text_blanktarget testcase
     *
     * @return array of testcases
     */
    public function format_text_blanktarget_testcases() {
        return [
            'Simple link' => [
                '<a href="https://www.youtube.com/watch?v=JeimE8Wz6e4">Hey, that\'s pretty good!</a>',
                '<div class="text_to_html"><a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" target="_blank"' .
                    ' rel="noreferrer">Hey, that\'s pretty good!</a></div>'
            ],
            'Link with rel' => [
                '<a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" rel="nofollow">Hey, that\'s pretty good!</a>',
                '<div class="text_to_html"><a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" rel="nofollow noreferrer"' .
                    ' target="_blank">Hey, that\'s pretty good!</a></div>'
            ],
            'Link with rel noreferrer' => [
                '<a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" rel="noreferrer">Hey, that\'s pretty good!</a>',
                '<div class="text_to_html"><a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" rel="noreferrer"' .
                 ' target="_blank">Hey, that\'s pretty good!</a></div>'
            ],
            'Link with target' => [
                '<a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" target="_self">Hey, that\'s pretty good!</a>',
                '<div class="text_to_html"><a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" target="_self">' .
                    'Hey, that\'s pretty good!</a></div>'
            ],
            'Link with target blank' => [
                '<a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" target="_blank">Hey, that\'s pretty good!</a>',
                '<div class="text_to_html"><a href="https://www.youtube.com/watch?v=JeimE8Wz6e4" target="_blank"' .
                    ' rel="noreferrer">Hey, that\'s pretty good!</a></div>'
            ],
            'Link with Frank\'s casket inscription' => [
                '<a href="https://en.wikipedia.org/wiki/Franks_Casket">ᚠᛁᛋᚳ᛫ᚠᛚᚩᛞᚢ᛫ᚪᚻᚩᚠᚩᚾᚠᛖᚱᚷ ᛖᚾᛒᛖᚱᛁᚷ ᚹᚪᚱᚦᚷᚪ᛬ᛋᚱᛁᚳᚷᚱᚩᚱᚾᚦᚫᚱᚻᛖᚩᚾᚷᚱᛖᚢᛏᚷᛁᛋᚹᚩᛗ ᚻ' .
                    'ᚱᚩᚾᚫᛋᛒᚪᚾ ᛗᚫᚷᛁᚠᛁᛋᚳ᛫ᚠᛚᚩᛞᚢ᛫ᚪᚻᚩᚠᚩᚾᚠᛖᚱᚷ ᛖᚾᛒᛖᚱᛁᚷ ᚹᚪᚱᚦᚷᚪ᛬ᛋᚱᛁᚳᚷᚱᚩᚱᚾᚦᚫᚱᚻᛖᚩᚾᚷᚱᛖᚢᛏᚷᛁᛋᚹᚩᛗ ᚻᚱᚩᚾᚫᛋᛒᚪᚾ ᛗᚫᚷᛁ</a>',
                '<div class="text_to_html"><a href="https://en.wikipedia.org/wiki/Franks_Casket" target="_blank" ' .
                    'rel="noreferrer">ᚠᛁᛋᚳ᛫ᚠᛚᚩᛞᚢ᛫ᚪᚻᚩᚠᚩᚾᚠᛖᚱᚷ ᛖᚾᛒᛖᚱᛁᚷ ᚹᚪᚱᚦᚷᚪ᛬ᛋᚱᛁᚳᚷᚱᚩᚱᚾᚦᚫᚱᚻᛖᚩᚾᚷᚱᛖᚢᛏᚷᛁᛋᚹᚩᛗ ᚻᚱᚩᚾᚫᛋᛒᚪᚾ ᛗᚫᚷᛁᚠᛁᛋᚳ᛫ᚠᛚᚩᛞᚢ᛫ᚪᚻᚩᚠᚩᚾᚠᛖᚱᚷ ᛖᚾ' .
                    'ᛒᛖᚱᛁᚷ ᚹᚪᚱᚦᚷᚪ᛬ᛋᚱᛁᚳᚷᚱᚩᚱᚾᚦᚫᚱᚻᛖᚩᚾᚷᚱᛖᚢᛏᚷᛁᛋᚹᚩᛗ ᚻᚱᚩᚾᚫᛋᛒᚪᚾ ᛗᚫᚷᛁ</a></div>'
             ],
            'No link' => [
                'Some very boring text written with the Latin script',
                '<div class="text_to_html">Some very boring text written with the Latin script</div>'
            ],
            'No link with Thror\'s map runes' => [
                'ᛋᛏᚫᚾᛞ ᛒᚣ ᚦᛖ ᚷᚱᛖᚣ ᛋᛏᚩᚾᛖ ᚻᚹᛁᛚᛖ ᚦᛖ ᚦᚱᚢᛋᚻ ᚾᚩᚳᛋ ᚫᚾᛞ ᚦᛖ ᛋᛖᛏᛏᛁᚾᚷ ᛋᚢᚾ ᚹᛁᚦ ᚦᛖ ᛚᚫᛋᛏ ᛚᛁᚷᚻᛏ ᚩᚠ ᛞᚢᚱᛁᚾᛋ ᛞᚫᚣ ᚹᛁᛚᛚ ᛋᚻᛁᚾᛖ ᚢᛈᚩᚾ ᚦᛖ ᚳᛖᚣᚻᚩᛚᛖ',
                '<div class="text_to_html">ᛋᛏᚫᚾᛞ ᛒᚣ ᚦᛖ ᚷᚱᛖᚣ ᛋᛏᚩᚾᛖ ᚻᚹᛁᛚᛖ ᚦᛖ ᚦᚱᚢᛋᚻ ᚾᚩᚳᛋ ᚫᚾᛞ ᚦᛖ ᛋᛖᛏᛏᛁᚾᚷ ᛋᚢᚾ ᚹᛁᚦ ᚦᛖ ᛚᚫᛋᛏ ᛚᛁᚷᚻᛏ ᚩᚠ ᛞᚢᚱᛁᚾᛋ ᛞᚫᚣ ᚹ' .
                'ᛁᛚᛚ ᛋᚻᛁᚾᛖ ᚢᛈᚩᚾ ᚦᛖ ᚳᛖᚣᚻᚩᛚᛖ</div>'
            ]
        ];
    }

    /**
     * Test ability to force cleaning of otherwise non-cleaned content.
     *
     * @dataProvider format_text_cleaning_testcases
     *
     * @param string $input Input text
     * @param string $nocleaned Expected output of format_text() with noclean=true
     * @param string $cleaned Expected output of format_text() with noclean=false
     */
    public function test_format_text_cleaning($input, $nocleaned, $cleaned) {
        global $CFG;
        $this->resetAfterTest();

        $CFG->forceclean = false;
        $actual = format_text($input, FORMAT_HTML, ['filter' => false, 'noclean' => false]);
        $this->assertEquals($cleaned, $actual);

        $CFG->forceclean = true;
        $actual = format_text($input, FORMAT_HTML, ['filter' => false, 'noclean' => false]);
        $this->assertEquals($cleaned, $actual);

        $CFG->forceclean = false;
        $actual = format_text($input, FORMAT_HTML, ['filter' => false, 'noclean' => true]);
        $this->assertEquals($nocleaned, $actual);

        $CFG->forceclean = true;
        $actual = format_text($input, FORMAT_HTML, ['filter' => false, 'noclean' => true]);
        $this->assertEquals($cleaned, $actual);
    }

    /**
     * Data provider for the test_format_text_cleaning testcase
     *
     * @return array of testcases (string)testcasename => [(string)input, (string)nocleaned, (string)cleaned]
     */
    public function format_text_cleaning_testcases() {
        return [
            'JavaScript' => [
                'Hello <script type="text/javascript">alert("XSS");</script> world',
                'Hello <script type="text/javascript">alert("XSS");</script> world',
                'Hello  world',
            ],
            'Inline frames' => [
                'Let us go phishing! <iframe src="https://1.2.3.4/google.com"></iframe>',
                'Let us go phishing! <iframe src="https://1.2.3.4/google.com"></iframe>',
                'Let us go phishing! ',
            ],
            'Malformed A tags' => [
                '<a onmouseover="alert(document.cookie)">xxs link</a>',
                '<a onmouseover="alert(document.cookie)">xxs link</a>',
                '<a>xxs link</a>',
            ],
            'Malformed IMG tags' => [
                '<IMG """><SCRIPT>alert("XSS")</SCRIPT>">',
                '<IMG """><SCRIPT>alert("XSS")</SCRIPT>">',
                '"&gt;',
            ],
            'On error alert' => [
                '<IMG SRC=/ onerror="alert(String.fromCharCode(88,83,83))"></img>',
                '<IMG SRC=/ onerror="alert(String.fromCharCode(88,83,83))"></img>',
                '<img src="/" alt="" />',
            ],
            'IMG onerror and javascript alert encode' => [
                '<img src=x onerror="&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000083&#0000083&#0000039&#0000041">',
                '<img src=x onerror="&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000083&#0000083&#0000039&#0000041">',
                '<img src="x" alt="x" />',
            ],
            'DIV background-image' => [
                '<DIV STYLE="background-image: url(javascript:alert(\'XSS\'))">',
                '<DIV STYLE="background-image: url(javascript:alert(\'XSS\'))">',
                '<div></div>',
            ],
        ];
    }
}
