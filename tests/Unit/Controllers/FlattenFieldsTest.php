<?php

declare(strict_types=1);

namespace Xfa\Pdf\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Xfa\Pdf\Http\Controllers\XfaPdfController;
use Xfa\Pdf\XfaPdfManager;

class FlattenFieldsTest extends TestCase
{
    private \Closure $flattenFields;

    protected function setUp(): void
    {
        parent::setUp();

        // Access private method via reflection
        $manager = $this->createMock(XfaPdfManager::class);
        $controller = new XfaPdfController($manager);
        $method = new ReflectionMethod($controller, 'flattenFields');
        $method->setAccessible(true);
        $this->flattenFields = function (array $fields) use ($controller, $method) {
            return $method->invoke($controller, $fields);
        };
    }

    /** @test */
    public function it_flattens_simple_nested_fields()
    {
        $fn = $this->flattenFields;

        $result = $fn([
            'Section_3' => [
                'name' => 'John',
                'year' => '2024',
            ],
        ]);

        $this->assertSame('John', $result['Section_3/name']);
        $this->assertSame('2024', $result['Section_3/year']);
    }

    /** @test */
    public function it_skips_numeric_keys()
    {
        $fn = $this->flattenFields;

        $result = $fn([
            'Section' => [
                0 => ['field' => 'val'],
                'name' => 'John',
            ],
        ]);

        $this->assertSame('John', $result['Section/name']);
        $this->assertArrayNotHasKey('Section/0/field', $result);
    }

    /** @test */
    public function it_skips_bracket_keys()
    {
        $fn = $this->flattenFields;

        $result = $fn([
            'Section' => [
                'medicalQualifications[0' => '2022',
                'name' => 'John',
            ],
        ]);

        $this->assertSame('John', $result['Section/name']);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_does_not_skip_entire_section_when_child_has_bracket_key()
    {
        // This was the bug: sections with ANY bracket key child were entirely skipped
        $fn = $this->flattenFields;

        $result = $fn([
            'Section_3_AppraisalDetails' => [
                'appraiseeName' => '245591771678',
                'GMCNumber' => null,
                'medicalQualifications[0' => '2022',
                'appraisalYear' => 'Please select...',
            ],
        ]);

        $this->assertSame('245591771678', $result['Section_3_AppraisalDetails/appraiseeName']);
        $this->assertSame('Please select...', $result['Section_3_AppraisalDetails/appraisalYear']);
        $this->assertArrayNotHasKey('Section_3_AppraisalDetails/GMCNumber', $result);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_skips_null_values()
    {
        $fn = $this->flattenFields;

        $result = $fn([
            'Section' => [
                'name' => 'John',
                'email' => null,
                'phone' => '',
            ],
        ]);

        $this->assertSame('John', $result['Section/name']);
        $this->assertSame('', $result['Section/phone']);
        $this->assertArrayNotHasKey('Section/email', $result);
    }

    /** @test */
    public function it_recurses_into_arrays_with_mixed_keys()
    {
        // Simulates real form data: section with both simple fields and repeatable groups
        $fn = $this->flattenFields;

        $result = $fn([
            'Section_7_CPD' => [
                'commentaryOnActivities' => 'Some CPD notes',
                'appraiserComments' => 'Appraiser note',
                'cpdList' => [
                    0 => ['role' => 'GP', 'credits' => '5'],
                    1 => ['role' => 'Hospital', 'credits' => '3'],
                ],
            ],
        ]);

        $this->assertSame('Some CPD notes', $result['Section_7_CPD/commentaryOnActivities']);
        $this->assertSame('Appraiser note', $result['Section_7_CPD/appraiserComments']);
        // Indexed items inside cpdList should be skipped
        $this->assertArrayNotHasKey('Section_7_CPD/cpdList/0/role', $result);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_handles_deeply_nested_fields()
    {
        $fn = $this->flattenFields;

        $result = $fn([
            'CommonSections' => [
                'FormMetaData' => [
                    'formId' => 'GMC_Appraisal',
                    'formVersion' => '1',
                ],
                'UserProfile' => [
                    'Name' => [
                        'foreName' => 'Jane',
                        'surName' => 'Doe',
                    ],
                ],
            ],
        ]);

        $this->assertSame('GMC_Appraisal', $result['CommonSections/FormMetaData/formId']);
        $this->assertSame('Jane', $result['CommonSections/UserProfile/Name/foreName']);
    }
}
