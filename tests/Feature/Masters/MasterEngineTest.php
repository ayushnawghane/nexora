<?php

use App\Enums\ConditionStage;
use App\Models\ConditionDocument;
use App\Models\Department;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use App\Models\Vertical;
use App\Support\Masters\MasterRegistry;
use Database\Seeders\StateSeeder;
use Spatie\Activitylog\Models\Activity;

test('masters need permission', function () {
    signIn();

    $this->get('/masters')->assertForbidden();
    $this->get('/masters/departments')->assertForbidden();
});

test('view-only users can list but not change masters', function () {
    signIn(permissions: ['masters.view']);

    $this->get('/masters')->assertOk();
    $this->get('/masters/departments')->assertOk();
    $this->post('/masters/departments', ['name' => 'Legal'])->assertForbidden();
});

test('unknown masters return 404', function () {
    signIn(permissions: ['masters.view']);

    $this->get('/masters/nonsense')->assertNotFound();
});

test('every configured master renders', function () {
    signIn(permissions: ['masters.view']);
    $this->seed(StateSeeder::class);

    foreach (app(MasterRegistry::class)->keys() as $key) {
        $this->get("/masters/{$key}")->assertOk()->assertInertia(fn ($page) => $page->component('Masters/Show'));
    }
});

test('records are created with normalised input', function () {
    signIn(permissions: ['masters.manage']);

    $this->post('/masters/departments', ['name' => '  Legal  ', 'code' => 'lgl'])->assertSessionHasNoErrors();

    $department = Department::query()->firstOrFail();
    expect($department->name)->toBe('Legal')->and($department->code)->toBe('LGL');
});

test('uniqueness is enforced on create and ignores the record itself on update', function () {
    signIn(permissions: ['masters.manage']);
    $legal = Department::query()->create(['name' => 'Legal', 'code' => 'LGL']);
    Department::query()->create(['name' => 'Audit', 'code' => 'AUD']);

    $this->post('/masters/departments', ['name' => 'Legal'])->assertSessionHasErrors('name');
    $this->put("/masters/departments/{$legal->id}", ['name' => 'Legal', 'code' => 'LGL'])->assertSessionHasNoErrors();
    $this->put("/masters/departments/{$legal->id}", ['name' => 'Audit', 'code' => 'LGL'])->assertSessionHasErrors('name');
});

test('select and multiselect fields are validated and saved through relations', function () {
    signIn(permissions: ['masters.manage']);
    $signatory = User::factory()->create();
    $dt = Product::query()->create(['code' => 'DEB', 'name' => 'Debenture Trustee']);
    $st = Product::query()->create(['code' => 'SEC', 'name' => 'Security Trustee']);

    $this->post('/masters/verticals', [
        'code' => 'dt', 'name' => 'Debenture Trustee', 'signatory_id' => $signatory->id, 'product_ids' => [$dt->id, $st->id],
    ])->assertSessionHasNoErrors();

    $vertical = Vertical::query()->with('products')->firstOrFail();
    expect($vertical->code)->toBe('DT')
        ->and($vertical->signatory->is($signatory))->toBeTrue()
        ->and($vertical->products->pluck('code')->sort()->values()->all())->toBe(['DEB', 'SEC']);

    $this->post('/masters/verticals', ['code' => 'X', 'name' => 'X', 'signatory_id' => 999])->assertSessionHasErrors('signatory_id');
    $this->post('/masters/verticals', ['code' => 'Y', 'name' => 'Y', 'product_ids' => [999]])->assertSessionHasErrors('product_ids.0');
});

test('pincode and city must be unique together', function () {
    signIn(permissions: ['masters.manage']);
    $this->seed(StateSeeder::class);
    $state = State::query()->where('gst_code', '27')->firstOrFail();

    $this->post('/masters/pincodes', ['pincode' => '400001', 'city' => 'Mumbai', 'state_id' => $state->id])->assertSessionHasNoErrors();
    $this->post('/masters/pincodes', ['pincode' => '400001', 'city' => 'Mumbai', 'state_id' => $state->id])->assertSessionHasErrors('pincode');
    $this->post('/masters/pincodes', ['pincode' => '400001', 'city' => 'Fort', 'state_id' => $state->id])->assertSessionHasNoErrors();
    $this->post('/masters/pincodes', ['pincode' => '012345', 'city' => 'Bad', 'state_id' => $state->id])->assertSessionHasErrors('pincode');

    expect(Pincode::query()->count())->toBe(2);
});

test('records can be deactivated and are hidden from form options', function () {
    signIn(permissions: ['masters.view', 'masters.manage']);
    $product = Product::query()->create(['code' => 'DEB', 'name' => 'Debenture Trustee']);

    $this->post("/masters/products/{$product->id}/toggle")->assertRedirect();
    expect($product->fresh()->is_active)->toBeFalse();

    $this->get('/masters/verticals')->assertInertia(fn ($page) => $page
        ->where('schema.3.name', 'product_ids')
        ->where('schema.3.options', []));
});

test('records in use cannot be deleted', function () {
    signIn(permissions: ['masters.manage']);
    $department = Department::query()->create(['name' => 'Legal']);
    User::factory()->create(['department_id' => $department->id]);

    $this->delete("/masters/departments/{$department->id}")->assertSessionHas('error');
    expect($department->fresh()->trashed())->toBeFalse();

    $unused = Department::query()->create(['name' => 'Audit']);
    $this->delete("/masters/departments/{$unused->id}")->assertSessionHas('success');
    expect($unused->fresh()->trashed())->toBeTrue();
});

test('master changes are recorded in the activity log', function () {
    $actor = signIn(permissions: ['masters.manage']);

    $this->post('/masters/departments', ['name' => 'Legal']);

    $activity = Activity::query()->latest('id')->firstOrFail();
    expect($activity->subject_type)->toBe(Department::class)
        ->and($activity->causer_id)->toBe($actor->id);
});

test('boolean fields are checkboxes saved as true or false; CP/CS names are unique per stage', function () {
    signIn(permissions: ['masters.view', 'masters.manage']);

    $this->post('/masters/condition-documents', [
        'stage' => 'precedent', 'name' => 'Rating letter', 'listed_secured' => true, 'listed_unsecured' => '0',
        'unlisted_secured' => 'true', 'unlisted_unsecured' => false,
    ])->assertSessionHasNoErrors();
    $this->post('/masters/condition-documents', ['stage' => 'precedent', 'name' => 'Rating letter', 'listed_secured' => 'maybe'])
        ->assertSessionHasErrors(['name', 'listed_secured']);
    $this->post('/masters/condition-documents', [
        'stage' => 'subsequent', 'name' => 'Rating letter', 'listed_secured' => false, 'listed_unsecured' => false,
        'unlisted_secured' => false, 'unlisted_unsecured' => false,
    ])->assertSessionHasNoErrors();

    $cp = ConditionDocument::query()->where('stage', ConditionStage::Precedent)->sole();
    expect($cp->listed_secured)->toBeTrue()
        ->and($cp->listed_unsecured)->toBeFalse()
        ->and($cp->unlisted_secured)->toBeTrue()
        ->and($cp->unlisted_unsecured)->toBeFalse();

    $this->get('/masters/condition-documents')->assertInertia(fn ($page) => $page
        ->where('schema.3.type', 'boolean')
        ->where('schema.3.required', false));
});
