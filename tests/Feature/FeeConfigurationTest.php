<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Fee setup: without a fee type and a class amount there is nothing to bill,
 * so these tests walk from an empty catalogue all the way to an invoice.
 */
class FeeConfigurationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $accountant;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->fixture();
    }

    public function test_an_empty_catalogue_leaves_the_invoice_screen_without_billable_lines(): void
    {
        $this->assertSame(0, FeeType::query()->count());

        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertViewHas('lines', fn (array $lines): bool => $lines === [])
            ->assertSee(__('There is no active fee type to bill yet'))
            ->assertSee(__('Set the class fees'))
            ->assertSee('so this button stays off')
            ->assertSee('disabled');
    }

    public function test_a_fee_type_and_a_class_fee_created_from_the_ui_make_the_student_billable(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), [
                'name' => 'Monthly Tuition',
            ])
            ->assertRedirect();

        $type = FeeType::query()->where('name', 'Monthly Tuition')->firstOrFail();

        $this->assertSame('MONTHLY_TUITION', $type->code);
        $this->assertSame('monthly', $type->frequency);
        $this->assertTrue($type->is_active);
        // The screen asks for a name and nothing else, so the report group is
        // read out of that name rather than left blank until somebody edits it.
        $this->assertSame('tuition', $type->category);
        $this->assertNull($type->starts_on);
        $this->assertNull($type->ends_on);
        $this->assertNull($type->description);
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_type.created')->count());

        $this->actingAs($this->accountant)
            ->get(route('fees.types.index'))
            ->assertOk()
            ->assertSee('Monthly Tuition');

        $this->actingAs($this->accountant)
            ->get(route('fees.structures.index', ['session_id' => $this->session->id]))
            ->assertOk()
            ->assertSee('Monthly Tuition')
            ->assertSee($this->class->name)
            ->assertSee('Save class fee');

        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'fee_type_id' => $type->id,
                'amount' => 500,
                'due_day' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $structure = FeeStructure::query()->firstOrFail();
        $this->assertSame('500.00', $structure->amount);
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_structure.created')->count());

        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertViewHas('lines', function (array $lines) use ($type): bool {
                return count($lines) === 1
                    && $lines[0]['fee_type_id'] === $type->id
                    && (float) $lines[0]['amount'] === 500.0
                    && $lines[0]['source'] === 'structure';
            })
            ->assertSee('৳ 500.00')
            ->assertDontSee('so this button stays off');

        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$type->id],
                'billing_period' => '2026-10',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame($this->student->id, $invoice->student_id);
        $this->assertSame('500.00', $invoice->total);
        $this->assertSame('500.00', $invoice->due_total);
        $this->assertSame(1, $invoice->items()->count());
    }

    public function test_the_fee_type_forms_only_ask_for_the_name(): void
    {
        $type = $this->makeFeeType();

        $response = $this->actingAs($this->accountant)->get(route('fees.types.index'))->assertOk();

        $response->assertSee('name="name"', false)
            ->assertDontSee('name="code"', false)
            ->assertDontSee('name="category"', false)
            ->assertDontSee('name="frequency"', false)
            ->assertDontSee('name="starts_on"', false)
            ->assertDontSee('name="ends_on"', false)
            ->assertDontSee('name="description"', false)
            ->assertDontSee('name="is_active"', false);

        // The edit modal is the same single field.
        $response->assertSee('action="'.route('fees.types.update', $type).'"', false);
    }

    public function test_renaming_a_fee_type_reaches_the_screens_that_list_it(): void
    {
        $type = $this->makeFeeType();

        $this->actingAs($this->accountant)
            ->put(route('fees.types.update', $type), [
                'name' => 'Tuition Fee Revised',
                'frequency' => 'yearly',
                'category' => 'transport',
            ])
            ->assertRedirect();

        $fresh = FeeType::query()->findOrFail($type->id);

        $this->assertSame('Tuition Fee Revised', $fresh->name);
        // Anything the form no longer owns is left exactly as it was.
        $this->assertSame('monthly', $fresh->frequency);
        $this->assertSame('tuition', $fresh->category);

        $this->assertSame(1, AuditLog::query()->where('event', 'fee_type.updated')->count());

        $this->actingAs($this->accountant)
            ->get(route('fees.types.index'))
            ->assertOk()
            ->assertSee('Tuition Fee Revised');

        $this->actingAs($this->accountant)
            ->get(route('fees.structures.index', ['session_id' => $this->session->id]))
            ->assertOk()
            ->assertSee('Tuition Fee Revised');
    }

    public function test_an_unused_fee_type_can_be_deleted_for_good(): void
    {
        $type = $this->makeFeeType();

        $this->actingAs($this->accountant)
            ->delete(route('fees.types.destroy', $type))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(0, FeeType::withTrashed()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_type.deleted')->count());

        $this->actingAs($this->accountant)
            ->get(route('fees.types.index'))
            ->assertOk()
            ->assertSee('No fee types yet')
            ->assertSee('was deleted');
    }

    public function test_a_fee_type_used_by_a_class_fee_cannot_be_deleted(): void
    {
        $type = $this->makeFeeType();

        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($type))
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->delete(route('fees.types.destroy', $type))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'class fee setups')
                && str_contains($message, 'Deactivate it instead'));

        // The refusal has to reach the admin's screen, not only the session.
        $this->actingAs($this->accountant)
            ->get(route('fees.types.index'))
            ->assertOk()
            ->assertSee('cannot be deleted')
            ->assertSee('Deactivate it instead');

        $this->assertSame(1, FeeType::query()->count());
        $this->assertSame(1, FeeStructure::query()->count());
        $this->assertSame(0, AuditLog::query()->where('event', 'fee_type.deleted')->count());
    }

    public function test_a_billed_fee_type_keeps_its_history_and_is_deactivated_instead(): void
    {
        $type = $this->makeFeeType();

        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($type))
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$type->id],
                'billing_period' => '2026-10',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasNoErrors();

        $invoice = FeeInvoice::query()->firstOrFail();

        $this->actingAs($this->accountant)
            ->delete(route('fees.types.destroy', $type))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'invoice lines'));

        // Nothing about the money moved.
        $this->assertSame(1, FeeType::query()->count());
        $this->assertSame(1, FeeInvoice::query()->count());
        $this->assertSame('500.00', FeeInvoice::query()->firstOrFail()->total);
        $this->assertSame(1, $invoice->items()->count());

        // The safe exit is deactivation.
        $this->actingAs($this->accountant)
            ->post(route('fees.types.toggle', $type))
            ->assertRedirect();

        $this->assertFalse(FeeType::query()->findOrFail($type->id)->is_active);
        $this->assertSame(1, FeeInvoice::query()->count());
    }

    public function test_deleting_a_fee_type_needs_the_manage_permission(): void
    {
        $type = $this->makeFeeType();
        $principal = $this->makeUser($this->school, 'principal');

        $this->actingAs($principal)
            ->delete(route('fees.types.destroy', $type))
            ->assertForbidden();

        $this->assertSame(1, FeeType::query()->count());
    }

    public function test_a_generated_code_stays_unique_when_names_slug_to_the_same_value(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), ['name' => 'Monthly Tuition'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), ['name' => 'Monthly-Tuition'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['MONTHLY_TUITION', 'MONTHLY_TUITION_2'],
            FeeType::query()->orderBy('id')->pluck('code')->all(),
        );
    }

    public function test_a_duplicate_class_fee_for_the_same_session_class_and_type_is_rejected(): void
    {
        $payload = $this->classFeePayload($this->makeFeeType());

        $this->actingAs($this->accountant)->post(route('fees.structures.store'), $payload)->assertRedirect();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $payload)
            ->assertSessionHasErrors('fee_type_id');

        $this->assertSame(1, FeeStructure::query()->count());
    }

    public function test_editing_a_class_fee_does_not_rewrite_an_invoice_already_issued(): void
    {
        $type = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($type))
            ->assertRedirect();

        $structure = FeeStructure::query()->firstOrFail();

        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$type->id],
                'billing_period' => '2026-10',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasNoErrors();

        $invoice = FeeInvoice::query()->firstOrFail();

        $this->actingAs($this->accountant)
            ->put(route('fees.structures.update', $structure), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'fee_type_id' => $type->id,
                'amount' => 750,
                'due_day' => 12,
                'is_active' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('750.00', $structure->fresh()->amount);
        $this->assertSame('500.00', $invoice->fresh()->total);
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_structure.updated')->count());

        // And the next quote follows the new amount.
        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertSee('৳ 750.00');
    }

    public function test_deactivating_a_class_fee_leaves_the_fee_unpriced_instead_of_billed_at_the_old_amount(): void
    {
        $type = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($type))
            ->assertRedirect();

        $structure = FeeStructure::query()->firstOrFail();

        $this->actingAs($this->accountant)
            ->post(route('fees.structures.toggle', $structure))
            ->assertRedirect();

        $this->assertFalse($structure->fresh()->is_active);
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_structure.toggled')->count());

        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertViewHas('lines', fn (array $lines): bool => count($lines) === 1
                && $lines[0]['amount'] === null
                && $lines[0]['source'] === 'manual');
    }

    public function test_class_fees_copy_into_a_new_session_without_duplicates(): void
    {
        $type = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($type))
            ->assertRedirect();

        $next = AcademicSession::query()->create([
            'name' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'active',
            'is_current' => false,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('fees.structures.copy'), [
                'from_session_id' => $this->session->id,
                'to_session_id' => $next->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, FeeStructure::query()->count());
        $this->assertSame(1, FeeStructure::query()->where('academic_session_id', $next->id)->count());
        $this->assertSame('500.00', FeeStructure::query()->where('academic_session_id', $next->id)->firstOrFail()->amount);

        // Re-running the copy skips what already exists instead of duplicating it.
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.copy'), [
                'from_session_id' => $this->session->id,
                'to_session_id' => $next->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, FeeStructure::query()->count());
    }

    public function test_a_principal_can_read_the_setup_pages_but_cannot_change_them(): void
    {
        $principal = $this->makeUser($this->school, 'principal');
        $type = $this->makeFeeType();

        $this->actingAs($principal)->get(route('fees.types.index'))->assertOk();
        $this->actingAs($principal)
            ->get(route('fees.structures.index'))
            ->assertOk()
            ->assertSee($this->session->name)
            ->assertDontSee('Save class fee');

        $this->actingAs($principal)
            ->post(route('fees.types.store'), ['name' => 'Library Fee', 'code' => 'LIB', 'frequency' => 'yearly'])
            ->assertForbidden();

        $this->actingAs($principal)
            ->post(route('fees.structures.store'), $this->classFeePayload($type))
            ->assertForbidden();

        $this->assertSame(1, FeeType::query()->count());
        $this->assertSame(0, FeeStructure::query()->count());
    }

    public function test_a_fee_type_from_another_school_cannot_be_billed_to_this_class(): void
    {
        $foreign = $this->otherSchoolFeeType();

        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'fee_type_id' => $foreign->id,
                'amount' => 500,
            ])
            ->assertSessionHasErrors('fee_type_id');

        $this->assertSame(0, FeeStructure::query()->count());
    }

    public function test_a_fee_type_created_from_the_ui_is_offered_to_the_student_at_once(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), ['name' => 'Annual Picnic Fee'])
            ->assertSessionHasNoErrors();

        $picnic = FeeType::query()->where('name', 'Annual Picnic Fee')->firstOrFail();

        // No class fee, no assignment, no database edit — the new fee is already
        // on the billing screen, waiting for its amount.
        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertSee('Annual Picnic Fee')
            ->assertSee(__('Price it now'))
            ->assertSee('name="amounts['.$picnic->id.']"', false);

        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$picnic->id],
                'amounts' => [$picnic->id => '350.00'],
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame('350.00', $invoice->total);

        $item = $invoice->items()->firstOrFail();
        $this->assertSame($picnic->id, (int) $item->fee_type_id);
        $this->assertSame('Annual Picnic Fee', $item->description);
        $this->assertSame(350.0, (float) $item->unit_amount);
    }

    public function test_a_configured_class_fee_and_a_new_fee_are_billed_together(): void
    {
        $tuition = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($tuition))
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), ['name' => 'Library Fine'])
            ->assertSessionHasNoErrors();

        $fine = FeeType::query()->where('name', 'Library Fine')->firstOrFail();

        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertViewHas('lines', fn (array $lines): bool => count($lines) === 2
                && (float) $lines[0]['amount'] === 500.0
                && $lines[0]['source'] === 'structure'
                && $lines[1]['amount'] === null
                && $lines[1]['source'] === 'manual')
            ->assertSee('৳ 500.00')
            ->assertSee('Monthly Tuition')
            ->assertSee('Library Fine');

        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [(string) $tuition->id, (string) $fine->id],
                'amounts' => [$fine->id => '120'],
                'billing_period' => '2026-10',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasNoErrors();

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame('620.00', $invoice->total);
        $this->assertSame(2, $invoice->items()->count());
    }

    public function test_a_checked_fee_with_no_amount_anywhere_is_refused_by_name(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), ['name' => 'Annual Picnic Fee'])
            ->assertSessionHasNoErrors();

        $picnic = FeeType::query()->where('name', 'Annual Picnic Fee')->firstOrFail();

        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$picnic->id],
                'amounts' => [$picnic->id => ''],
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasErrors('fee_type_ids');

        $this->assertStringContainsString(
            'Annual Picnic Fee',
            (string) session('errors')->first('fee_type_ids'),
        );
        $this->assertSame(0, FeeInvoice::query()->count());
    }

    public function test_a_renamed_fee_type_shows_the_new_name_on_every_billing_screen(): void
    {
        $tuition = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($tuition))
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->put(route('fees.types.update', $tuition), ['name' => 'Tuition Fee 2026'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertSee('Tuition Fee 2026')
            ->assertDontSee('Monthly Tuition');

        $this->actingAs($this->accountant)
            ->get(route('fees.single', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertSee('Tuition Fee 2026');

        // The class fee screen follows the name too.
        $this->actingAs($this->accountant)
            ->get(route('fees.structures.index', ['session_id' => $this->session->id]))
            ->assertOk()
            ->assertSee('Tuition Fee 2026');
    }

    public function test_deactivated_and_deleted_fee_types_stay_out_of_new_fee_setup(): void
    {
        $tuition = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($tuition))
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), ['name' => 'Annual Picnic Fee'])
            ->assertSessionHasNoErrors();

        $picnic = FeeType::query()->where('name', 'Annual Picnic Fee')->firstOrFail();

        // Switching a priced fee off takes its class-fee line out of the form.
        $this->actingAs($this->accountant)
            ->post(route('fees.types.toggle', $tuition))
            ->assertRedirect();

        // An unused fee that is deleted cannot come back at all.
        $this->actingAs($this->accountant)
            ->delete(route('fees.types.destroy', $picnic))
            ->assertSessionHasNoErrors();

        $this->assertFalse(FeeType::query()->findOrFail($tuition->id)->is_active);
        $this->assertDatabaseMissing('fee_types', ['id' => $picnic->id]);

        // Drain the flash messages first: they name the fee that was just
        // switched off or deleted, which would otherwise look like a live line.
        $this->actingAs($this->accountant)->get(route('fees.index'))->assertOk();

        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertViewHas('lines', fn (array $lines): bool => $lines === [])
            ->assertDontSee('Monthly Tuition')
            ->assertDontSee('Annual Picnic Fee');

        // Both screens refuse them, so a hand-crafted post cannot bill either.
        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$tuition->id],
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasErrors('fee_type_ids.*');

        $this->actingAs($this->accountant)
            ->post(route('fees.single.store'), [
                'student_id' => $this->student->id,
                'academic_session_id' => $this->session->id,
                'month' => 10,
                'fee_type_id' => $tuition->id,
                'amount' => 500,
            ])
            ->assertSessionHasErrors('fee_type_id');

        $this->assertSame(0, FeeInvoice::query()->count());
    }

    public function test_an_issued_invoice_keeps_its_lines_after_a_fee_is_renamed_deactivated_or_deleted(): void
    {
        $tuition = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($tuition))
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$tuition->id],
                'billing_period' => '2026-10',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
            ])
            ->assertSessionHasNoErrors();

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame('500.00', $invoice->total);

        $this->actingAs($this->accountant)
            ->post(route('fees.types.store'), ['name' => 'Annual Picnic Fee'])
            ->assertSessionHasNoErrors();

        $picnic = FeeType::query()->where('name', 'Annual Picnic Fee')->firstOrFail();
        $this->actingAs($this->accountant)->post(route('fees.types.toggle', $tuition));
        $this->actingAs($this->accountant)->delete(route('fees.types.destroy', $picnic));

        $this->assertSame('500.00', $invoice->fresh()->total);
        $this->assertSame('500.00', $invoice->fresh()->due_total);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('Monthly Tuition', $invoice->items()->firstOrFail()->description);
    }

    public function test_the_student_fee_form_no_longer_asks_for_notes(): void
    {
        $tuition = $this->makeFeeType();
        $this->actingAs($this->accountant)
            ->post(route('fees.structures.store'), $this->classFeePayload($tuition))
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $this->student->id]))
            ->assertOk()
            ->assertDontSee('name="notes"', false)
            ->assertDontSee('for="notes"', false)
            ->assertSee(__('Invoice options'));

        // A note posted anyway is dropped instead of written into the invoice.
        $this->actingAs($this->accountant)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'student_ids' => [$this->student->id],
                'fee_type_ids' => [$tuition->id],
                'billing_period' => '2026-10',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
                'notes' => 'A note the form no longer has.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(FeeInvoice::query()->firstOrFail()->notes);
    }

    private function makeFeeType(): FeeType
    {
        return FeeType::query()->create([
            'name' => 'Monthly Tuition',
            'code' => 'TUI',
            'category' => 'tuition',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function classFeePayload(FeeType $type): array
    {
        return [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $type->id,
            'amount' => 500,
            'due_day' => 10,
            'is_active' => 1,
        ];
    }

    private function otherSchoolFeeType(): FeeType
    {
        $other = $this->makeSchool(['name' => 'Other School']);

        return app(SchoolContext::class)->run(
            $other->id,
            fn (): FeeType => FeeType::query()->create([
                'name' => 'Monthly Tuition',
                'code' => 'TUI',
                'frequency' => 'monthly',
                'is_active' => true,
            ]),
        );
    }

    private function fixture(): void
    {
        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->accountant = $this->makeUser($this->school, 'accountant');

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::query()->create([
            'name' => 'Class Six',
            'level' => 6,
            'is_active' => true,
        ]);

        $this->section = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->student = Student::factory()->create();

        Enrollment::query()->create([
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
    }
}
