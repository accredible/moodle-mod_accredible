<?php
// This file is part of the Accredible Certificate module for Moodle - http://moodle.org/
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

namespace mod_accredible\local;

use mod_accredible\apirest\apirest;
use mod_accredible\client\client;

/**
 * Unit tests for error-cause preservation on the credential issuance path.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_api_exception_test extends \advanced_testcase {
    /**
     * Every error key the plugin throws.
     * @var array
     */
    private const ERRORKEYS = [
        'groupsyncerror',
        'getcredentialserror',
        'credentialcreateerror',
        'evidenceadderror',
        'getgroupserror',
        'gettemplateserror',
        'getattributekeysserror',
    ];

    /**
     * Setup before every test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('accredible_api_key', 'sometestapikey');
        set_config('is_eu', 0);
        putenv('ACCREDIBLE_DEV_API_ENDPOINT');
    }

    /**
     * Put the site into the debugging state a customer's site runs in.
     *
     * PHPUNIT_TEST stays defined, so moodle_exception's $debuginfo gate cannot be closed from
     * here. That is precisely why nothing in the plugin passes $debuginfo any more: the assertions
     * below hold because the cause travels in $a, which get_string() renders unconditionally.
     */
    private function set_production_debugging(): void {
        global $CFG;
        $CFG->debug = DEBUG_NONE;
        $CFG->debugdisplay = 0;
    }

    /**
     * The message a site with debugging switched off would actually log.
     *
     * moodle_exception appends " ($debuginfo)" to the message under PHPUNIT_TEST or
     * DEBUG_DEVELOPER, and PHPUNIT_TEST is always defined in this suite. Asserting on the raw
     * getMessage() therefore passes for a fix that carries the cause in $debuginfo and shows a
     * customer nothing at all - the failure mode this whole ticket exists to avoid. Strip that
     * tail so the assertions see only what $a rendered.
     *
     * @param \moodle_exception $e the exception under test.
     * @return string
     */
    private function production_message(\moodle_exception $e): string {
        $message = $e->getMessage();
        if (empty($e->debuginfo)) {
            return $message;
        }
        $suffix = ' (' . $e->debuginfo . ')';
        if (substr($message, -strlen($suffix)) === $suffix) {
            return substr($message, 0, -strlen($suffix));
        }
        return $message;
    }

    /**
     * Build a credentials client whose apirest behaves as the closure dictates.
     *
     * @param callable $getcredentials stands in for apirest::get_credentials().
     * @param string|null $errmsg what detect_error() should report.
     * @return credentials
     */
    private function credentials_with(callable $getcredentials, $errmsg = null) {
        $apirest = new class ($getcredentials, $errmsg) {
            /** @var callable */
            private $getcredentials;
            /** @var string|null */
            private $errmsg;

            /**
             * Constructor.
             * @param callable $getcredentials stands in for apirest::get_credentials().
             * @param string|null $errmsg what detect_error() should report.
             */
            public function __construct(callable $getcredentials, $errmsg) {
                $this->getcredentials = $getcredentials;
                $this->errmsg = $errmsg;
            }

            /**
             * Stand in for apirest::get_credentials().
             * @param string|null $groupid
             * @param string|null $email
             * @param string|null $pagesize
             * @param int $page
             * @return \stdClass
             */
            public function get_credentials($groupid = null, $email = null, $pagesize = null, $page = 1) {
                return ($this->getcredentials)();
            }

            /**
             * Stand in for apirest::detect_error().
             * @param \stdClass|null $response
             * @param int|null $userid
             * @return string|null
             */
            public function detect_error($response, $userid = null) {
                return $this->errmsg;
            }
        };
        return new credentials($apirest);
    }

    /**
     * Every error key the plugin throws must resolve to a real sentence.
     *
     * @covers \mod_accredible\local\api_exception
     */
    public function test_every_error_key_has_a_lang_string(): void {
        foreach (self::ERRORKEYS as $key) {
            $this->assertTrue(
                get_string_manager()->string_exists($key, 'accredible'),
                "Missing lang string for '{$key}', so Moodle would log the raw key instead."
            );
        }
    }

    /**
     * The normalized API message must reach the exception message on a site with debugging off.
     *
     * @covers \mod_accredible\local\credentials::check_for_existing_credential
     */
    public function test_api_error_message_reaches_the_exception_message(): void {
        $this->set_production_debugging();
        $credentials = $this->credentials_with(fn() => (object) ['credentials' => []], 'Invalid Group');

        try {
            $credentials->check_for_existing_credential('999999999', 'learner@example.com');
            $this->fail('Expected an api_exception.');
        } catch (api_exception $e) {
            $message = $this->production_message($e);
            $this->assertStringContainsString('Invalid Group', $message);
            $this->assertStringNotContainsString('accredible/groupsyncerror', $message);
            // The group belongs to the event description, not to this message - asserted in
            // tests/event/mod_accredible_event_test.php.
            $this->assertStringNotContainsString('999999999', $message);
        }
    }

    /**
     * A PHP Error must be caught rather than escaping the catch, and keep its own class.
     *
     * @covers \mod_accredible\local\credentials::check_for_existing_credential
     */
    public function test_php_error_is_wrapped_and_keeps_its_class(): void {
        $this->set_production_debugging();
        $credentials = $this->credentials_with(function () {
            throw new \Error('Object of class stdClass could not be converted to string');
        });

        try {
            $credentials->check_for_existing_credential('999999999', 'learner@example.com');
            $this->fail('Expected an api_exception.');
        } catch (api_exception $e) {
            $this->assertSame('Error', api_exception::origin_class($e));
            $this->assertStringContainsString('could not be converted to string', $this->production_message($e));
        }
    }

    /**
     * An exception the plugin did not wrap must still report its own class.
     *
     * @covers \mod_accredible\local\api_exception::origin_class
     */
    public function test_origin_class_of_an_unwrapped_exception(): void {
        $this->assertSame(\RuntimeException::class, api_exception::origin_class(new \RuntimeException('boom')));
    }

    /**
     * A bare Exception relays an already normalized API message, so its class adds nothing.
     *
     * @covers \mod_accredible\local\api_exception::cause_text
     */
    public function test_cause_text_names_only_real_php_faults(): void {
        $this->assertSame('Invalid Group', api_exception::cause_text(new \Exception('Invalid Group')));
        $this->assertSame('Error: boom', api_exception::cause_text(new \Error('boom')));
        $this->assertSame('TypeError: boom', api_exception::cause_text(new \TypeError('boom')));
    }

    /**
     * debug_detail must surface a foreign exception's hidden diagnosis and nothing of our own.
     *
     * @covers \mod_accredible\local\api_exception::debug_detail
     */
    public function test_debug_detail_only_reports_foreign_diagnoses(): void {
        $foreign = new \moodle_exception('invalidcourseid', 'error', '', null, 'SELECT * FROM blew_up');
        $this->assertSame('SELECT * FROM blew_up', api_exception::debug_detail($foreign));

        $ours = new api_exception('groupsyncerror', '', (object) ['groupid' => '1', 'cause' => 'x']);
        $this->assertNull(api_exception::debug_detail($ours));
        $this->assertNull(api_exception::debug_detail(new \Error('boom')));
    }

    /**
     * No issuance failure may write a learner's email address into its message.
     *
     * @covers \mod_accredible\local\credentials::check_for_existing_credential
     * @covers \mod_accredible\local\credentials::get_credentials
     */
    public function test_no_error_message_contains_a_learner_email(): void {
        $this->set_production_debugging();
        $email = 'learner@example.com';

        foreach (['check_for_existing_credential', 'get_credentials'] as $method) {
            $credentials = $this->credentials_with(fn() => (object) ['credentials' => []], 'Invalid Group');
            try {
                $credentials->$method('999999999', $email);
                $this->fail("Expected an api_exception from {$method}().");
            } catch (api_exception $e) {
                $this->assertStringNotContainsString(
                    '@',
                    $this->production_message($e),
                    "{$method}() put an email address into a message the handlers log."
                );
            }
        }
    }

    /**
     * A failed lookup must not write the learner's email address into the log.
     *
     * @covers \mod_accredible\apirest\apirest::detect_error
     */
    public function test_logged_endpoint_carries_no_learner_email(): void {
        $mockclient = $this->getMockBuilder(client::class)->onlyMethods(['get'])->getMock();
        $mockclient->lasturl = 'https://api.accredible.com/v1/all_credentials?group_id=9549&email='
            . rawurlencode('learner@example.com') . '&page_size=50&page=1';
        $mockclient->respcode = 422;
        $api = new apirest($mockclient);

        $sink = $this->redirectEvents();
        $api->detect_error((object) ['errors' => 'Invalid Group'], 14);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $endpoint = $events[0]->other['endpoint'];
        $this->assertStringNotContainsString('@', $endpoint);
        $this->assertStringNotContainsString('learner', $endpoint);
        // The parts that actually help diagnose must survive.
        $this->assertStringContainsString('all_credentials', $endpoint);
        $this->assertStringContainsString('group_id=9549', $endpoint);
        $this->assertStringContainsString('page=1', $endpoint);
    }

    /**
     * A URL carrying no email must be logged exactly as it was requested.
     *
     * @covers \mod_accredible\apirest\apirest::detect_error
     */
    public function test_logged_endpoint_without_an_email_is_untouched(): void {
        $url = 'https://api.accredible.com/v1/credentials';
        $mockclient = $this->getMockBuilder(client::class)->onlyMethods(['get'])->getMock();
        $mockclient->lasturl = $url;
        $mockclient->respcode = 422;
        $api = new apirest($mockclient);

        $sink = $this->redirectEvents();
        $api->detect_error((object) ['errors' => 'Invalid Group'], 14);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame($url, $events[0]->other['endpoint']);
    }

    /**
     * An API-layer failure must leave an api_request_failed row beside the issuance failure.
     *
     * @covers \mod_accredible\apirest\apirest::detect_error
     */
    public function test_api_failure_also_fires_api_request_failed(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->onlyMethods(['get'])
            ->getMock();
        $mockclient->error = 'The requested URL returned error: 401 Unauthorized';
        $mockclient->respcode = 401;
        $mockclient->get('');
        $credentials = new credentials(new apirest($mockclient));

        $sink = $this->redirectEvents();
        try {
            $credentials->check_for_existing_credential('999999999', 'learner@example.com');
            $this->fail('Expected an api_exception.');
        } catch (api_exception $e) {
            $names = array_map('get_class', $sink->get_events());
            $this->assertContains(\mod_accredible\event\api_request_failed::class, $names);
        } finally {
            $sink->close();
        }
    }
}
