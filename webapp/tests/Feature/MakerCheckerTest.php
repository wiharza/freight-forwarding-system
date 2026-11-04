<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MakerChecker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pembuat_tidak_boleh_menyetujui_karyanya_sendiri(): void
    {
        $user = User::factory()->create();

        $record = new class extends Model
        {
            protected $table = 'jobs';
        };
        $record->created_by_user_id = $user->id;

        $this->expectException(ValidationException::class);

        MakerChecker::assertApproverDiffers($user, $record);
    }

    #[Test]
    public function penyetuju_berbeda_dari_pembuat_diloloskan(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $record = new class extends Model
        {
            protected $table = 'jobs';
        };
        $record->created_by_user_id = $creator->id;

        MakerChecker::assertApproverDiffers($approver, $record);

        $this->assertNotSame($creator->id, $approver->id);
    }

    #[Test]
    public function record_tanpa_pembuat_diloloskan(): void
    {
        $user = User::factory()->create();

        MakerChecker::assertApproverDiffers($user, (object) []);

        $this->assertTrue(true);
    }
}
