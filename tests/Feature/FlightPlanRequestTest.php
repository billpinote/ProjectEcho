<?php

namespace Tests\Feature;

use App\Http\Requests\StoreFlightPlanRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightPlanRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that other info tags are properly extracted from the other_information field.
     */
    public function test_other_info_tags_are_extracted_correctly(): void
    {
        $data = [
            'other_information' => 'RMK/This is a remark PBN/B1 C1 D1 RTE/Route information TYP/Aircraft type REG/Registration ALTN/Alternate1 ALTN2/Alternate2 OPR/Operator DOF/20240408',
            'date_of_flight' => '2024-04-08',
            'persons_on_board' => '2',
            'dinghies_number' => '1',
            'dinghies_capacity' => '4',
        ];

        $request = StoreFlightPlanRequest::create('/flightplan', 'POST', $data);
        $request->setContainer(app());
        $request->validateResolved();

        $this->assertEquals('This is a remark', $request->input('other_info_rmk'));
        $this->assertEquals('B1 C1 D1', $request->input('other_info_pbn'));
        $this->assertEquals('Route information', $request->input('other_info_route'));
        $this->assertEquals('Aircraft type', $request->input('other_info_typ'));
        $this->assertEquals('Registration', $request->input('other_info_reg'));
        $this->assertEquals('Alternate1', $request->input('other_info_altn_1'));
        $this->assertEquals('Alternate2', $request->input('other_info_altn_2'));
        $this->assertEquals('Operator', $request->input('other_info_opr'));
        $this->assertEquals('20240408', $request->input('other_info_dof'));
    }

    /**
     * Test that tags with no content are handled correctly.
     */
    public function test_empty_tags_are_handled_correctly(): void
    {
        $data = [
            'other_information' => 'RMK/ PBN/ TYP/',
            'date_of_flight' => '2024-04-08',
        ];

        $request = StoreFlightPlanRequest::create('/flightplan', 'POST', $data);
        $request->setContainer(app());
        $request->validateResolved();

        $this->assertNull($request->input('other_info_rmk'));
        $this->assertNull($request->input('other_info_pbn'));
        $this->assertNull($request->input('other_info_typ'));
    }

    /**
     * Test that missing tags return null.
     */
    public function test_missing_tags_return_null(): void
    {
        $data = [
            'other_information' => 'RMK/Only remark here',
            'date_of_flight' => '2024-04-08',
        ];

        $request = StoreFlightPlanRequest::create('/flightplan', 'POST', $data);
        $request->setContainer(app());
        $request->validateResolved();

        $this->assertEquals('Only remark here', $request->input('other_info_rmk'));
        $this->assertNull($request->input('other_info_pbn'));
        $this->assertNull($request->input('other_info_route'));
        $this->assertNull($request->input('other_info_typ'));
        $this->assertNull($request->input('other_info_reg'));
        $this->assertNull($request->input('other_info_altn_1'));
        $this->assertNull($request->input('other_info_altn_2'));
        $this->assertNull($request->input('other_info_opr'));
        $this->assertNull($request->input('other_info_dof'));
    }

    /**
     * Test that similar tag names (ALTN/ vs ALTN2/) are properly distinguished.
     */
    public function test_similar_tags_are_properly_isolated(): void
    {
        $data = [
            'other_information' => 'ALTN/FirstAlternate ALTN2/SecondAlternate OPR/MyOperator',
            'date_of_flight' => '2024-04-08',
        ];

        $request = StoreFlightPlanRequest::create('/flightplan', 'POST', $data);
        $request->setContainer(app());
        $request->validateResolved();

        // Verify ALTN/ only gets its own value, not ALTN2/ or OPR/ values
        $this->assertEquals('FirstAlternate', $request->input('other_info_altn_1'));
        // Verify ALTN2/ only gets its own value, not OPR/ value
        $this->assertEquals('SecondAlternate', $request->input('other_info_altn_2'));
        // Verify OPR/ gets correct value
        $this->assertEquals('MyOperator', $request->input('other_info_opr'));
    }

    /**
     * Test that multi-word values are correctly extracted without including next tag.
     */
    public function test_multi_word_values_stop_at_next_tag_boundary(): void
    {
        $data = [
            'other_information' => 'RMK/This is a long remark PBN/B1 C1 D1',
            'date_of_flight' => '2024-04-08',
        ];

        $request = StoreFlightPlanRequest::create('/flightplan', 'POST', $data);
        $request->setContainer(app());
        $request->validateResolved();

        // RMK should only get "This is a long remark", not include "PBN/"
        $this->assertEquals('This is a long remark', $request->input('other_info_rmk'));
        // PBN should only get "B1 C1 D1"
        $this->assertEquals('B1 C1 D1', $request->input('other_info_pbn'));
    }

    /**
     * Test that tags are reorganized according to the hierarchy regardless of input order.
     */
    public function test_tags_are_reorganized_by_hierarchy(): void
    {
        // Input tags in random order: RMK, ALTN2, DEP, DOF, PBN, TYP
        $data = [
            'other_information' => 'RMK/remarks ALTN2/alt2 DEP/departure DOF/20240408 PBN/pbn values TYP/aircraft',
            'date_of_flight' => '2024-04-08',
        ];

        $request = StoreFlightPlanRequest::create('/flightplan', 'POST', $data);
        $request->setContainer(app());
        $request->validateResolved();

        // Get the reorganized other_information
        $reorganizedInfo = $request->input('other_information');

        // The tags should now be in hierarchy order: DOF, RMK, TYP, DEP, ALTN2, PBN
        // Expected: DOF/20240408 RMK/remarks TYP/aircraft DEP/departure ALTN2/alt2 PBN/pbn values
        $this->assertEquals('DOF/20240408 RMK/remarks TYP/aircraft DEP/departure ALTN2/alt2 PBN/pbn values', $reorganizedInfo);

        // Verify individual tag values are still correctly extracted
        $this->assertEquals('remarks', $request->input('other_info_rmk'));
        $this->assertEquals('alt2', $request->input('other_info_altn_2'));
        $this->assertEquals('departure', $request->input('other_info_dep'));
        $this->assertEquals('20240408', $request->input('other_info_dof'));
        $this->assertEquals('pbn values', $request->input('other_info_pbn'));
        $this->assertEquals('aircraft', $request->input('other_info_typ'));
    }
}