<?php

namespace mod_accredible\local;

/**
 * Unit tests for mod/accredible/classes/local/accredible.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accredible_test extends \advanced_testcase {
    protected $accredible;

    /**
     * Setup testcase.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->accredible = new accredible();
    }

    /**
     * Test save_record method when creating a new record.
     */
    public function test_save_record_new() {
        global $DB;
    
        // Create a mock of the $DB object.
        $DB = $this->createMock(\moodle_database::class);
    
        $post = new \stdClass();
        $post->name = 'New Certificate';
        $post->course = 101;
        $post->finalquiz = 5;
        $post->passinggrade = 70;
        $post->completionactivities = null;
        $post->includegradeattribute = 1;
        $post->gradeattributegradeitemid = 10;
        $post->gradeattributekeyname = 'Final Grade';
        $post->groupid = 1;
        $post->coursefieldmapping = [];
        $post->coursecustomfieldmapping = [];
        $post->userfieldmapping = [];
        $post->instance = null;
    
        // Set up the expectation for the insert_record method.
        $DB->expects($this->once())
            ->method('insert_record')
            ->with('accredible', $this->anything())
            ->willReturn(1);
    
        $result = $this->accredible->save_record($post);
        $this->assertEquals(1, $result);
    }

    /**
     * Test save_record method when updating an existing record.
     */
    public function test_save_record_update() {
        global $DB;

        // Create a mock of the $DB object.
        $DB = $this->createMock(\moodle_database::class);

        $post = new \stdClass();
        $post->instance = 1; // Existing record ID.
        $post->name = 'Updated Certificate';
        $post->course = 102;
        $post->finalquiz = 6;
        $post->passinggrade = 75;
        $post->completionactivities = null;
        $post->includegradeattribute = 1;
        $post->gradeattributegradeitemid = 11;
        $post->gradeattributekeyname = 'Updated Final Grade';
        $post->groupid = 2;
        $post->coursefieldmapping = [];
        $post->coursecustomfieldmapping = [];
        $post->userfieldmapping = [];

        $accrediblecertificate = new \stdClass();
        $accrediblecertificate->achievementid = null;

        // Simulating update_record return value.
        $DB->expects($this->once())
            ->method('update_record')
            ->with('accredible', $this->anything())
            ->willReturn(true);

        $result = $this->accredible->save_record($post, $accrediblecertificate);
        $this->assertTrue($result);
    }

    public function test_build_attribute_mapping_list_empty() {
        $post = new \stdClass();
        $post->coursefieldmapping = [];
        $post->coursecustomfieldmapping = [];
        $post->userfieldmapping = [];
    
        $result = $this->invoke_method($this->accredible, 'build_attribute_mapping_list', [$post]);
        $this->assertNull($result);
    }    

    protected function invoke_method(&$object, $methodName, array $parameters = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
