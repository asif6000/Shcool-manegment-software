<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The cash desk is a single-page portal: nothing may render a navigation column,
 * a hamburger, a drawer or an overlay for an accountant, at any screen size.
 * The markup is refused at the layout, so these tests read the delivered HTML.
 */
class AccountantShellTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->accountant = $this->makeUser($this->school, 'accountant');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function deskPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'fee collection desk' => ['fees.collect'],
            'fee counter' => ['fees.counter'],
            'fees and payments' => ['fees.index'],
            'payment receipts' => ['fees.receipts'],
            'dues' => ['fees.dues'],
            'fee ledger' => ['fees.ledger'],
            'discounts' => ['fees.discounts.index'],
            'expense register' => ['expenses.index'],
            'cashbook' => ['cashbook.index'],
            'vouchers' => ['vouchers.index'],
            'bank accounts' => ['bank.index'],
            'collection report' => ['reports.collection'],
            'account statement' => ['reports.statement'],
            'students' => ['students.index'],
            'my profile' => ['profile.edit'],
            'notifications' => ['notifications.index'],
        ];
    }

    #[DataProvider('deskPages')]
    public function test_no_navigation_markup_reaches_the_accountant(string $routeName): void
    {
        $html = $this->actingAs($this->accountant)
            ->get(route($routeName))
            ->assertOk()
            ->getContent();

        $markup = self::visibleMarkup($html);

        foreach ([
            'app-sidebar',
            'sidebar-toggle',
            'sidebar-close',
            'data-sidebar-toggle',
            'data-sidebar-close',
            'app-backdrop',
            'sidebar-open',
            'offcanvas',
            'off-canvas',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $markup,
                "The {$routeName} page still ships {$forbidden}."
            );
        }
    }

    public function test_a_module_page_carries_the_accountant_flag_and_no_column(): void
    {
        $html = $this->actingAs($this->accountant)
            ->get(route('fees.collect'))
            ->assertOk()
            ->getContent();

        self::assertStringContainsString('is-accountant', $html);
        self::assertStringContainsString('no-sidebar', $html);
    }

    public function test_the_dashboard_offers_the_ten_action_cards(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fee Collection', false)
            ->assertSee('Student Fees', false)
            ->assertSee('Payment History', false)
            ->assertSee('Expenses', false)
            ->assertSee('Cashbook', false)
            ->assertSee('Discounts', false)
            ->assertSee('Salary Payment', false)
            ->assertSee('Reports', false)
            ->assertSee('My Profile', false)
            ->assertSee('Collection Summary', false)
            ->assertSee('Cashbook Summary', false)
            ->assertSee('Recent Expenses', false);
    }

    public function test_the_dashboard_summaries_read_the_database(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('dashboard'))
            ->assertOk()
            // Nothing has been collected in this fresh database, and the panel says so.
            ->assertSee('No fee collection is recorded for this month yet.', false);
    }

    public function test_an_admin_keeps_the_navigation_column(): void
    {
        $admin = $this->makeUser($this->school, 'school_admin');

        $html = $this->actingAs($admin)
            ->get(route('fees.index'))
            ->assertOk()
            ->getContent();

        self::assertStringContainsString('app-sidebar', self::visibleMarkup($html));
        self::assertStringNotContainsString('is-accountant', $html);
    }

    public function test_an_accountant_still_cannot_reach_administration(): void
    {
        $this->actingAs($this->accountant)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('roles.index'))->assertForbidden();
    }

    /**
     * Only what the browser can lay out matters here: the accountant layout keeps a
     * defensive CSS rule naming the sidebar, which must not be mistaken for markup.
     */
    private static function visibleMarkup(string $html): string
    {
        $withoutStyles = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;

        return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $withoutStyles) ?? $withoutStyles;
    }
}
